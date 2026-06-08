<?php

declare(strict_types=1);

use Luna\Connections\ConnectionProfileData;
use Luna\Connections\ConnectionTester;
use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Core\Application;
use Luna\Dataset\DatasetRegistry;
use Luna\Deployment\DeploymentTargetUrlBuilder;
use Luna\Export\EndpointExportContractService;
use Luna\Export\EndpointExportArchiveService;
use Luna\Export\EndpointRuntimeExporter;
use Luna\Export\WooCommerceExportService;
use Luna\Http\Request;
use Luna\Http\Response;
use Luna\Jobs\JobRunner;
use Luna\Mapping\LookupKeyTemplateRenderer;
use Luna\Mapping\MappingValidator;
use Luna\Mapping\TransformType;
use Luna\Process\ProcessRunner;
use Luna\Process\ProcessTriggerException;
use Luna\Process\ProcessTriggerRunner;
use Luna\Process\ProcessTriggerService;
use Luna\Process\TriggerUrlBuilder;
use Luna\Reports\ReportMailer;
use Luna\Repository\AuditLogRepository;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\DatasetTransferRepository;
use Luna\Repository\DeploymentTargetRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\ExportProfileRepository;
use Luna\Repository\JobRepository;
use Luna\Repository\JobRunRepository;
use Luna\Repository\MappingRepository;
use Luna\Repository\ProcessRepository;
use Luna\Repository\ProcessRunRepository;
use Luna\Repository\ProcessTriggerRepository;
use Luna\Repository\ReportRepository;
use Luna\Repository\SchemaMetadataRepository;
use Luna\Repository\SchemaRegistryRepository;
use Luna\Repository\TargetActionRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Repository\WooCommerceIntegrationRepository;
use Luna\Routing\RouteCollection;
use Luna\Schema\SampleDataReader;
use Luna\Schema\SchemaDefinitionValidator;
use Luna\Schema\SchemaInspector;
use Luna\Schema\SchemaValidator;
use Luna\Schema\TableNameReader;
use Luna\Transfer\DatasetTransferRunner;
use Luna\Transfer\MappingSourceRowProvider;
use Luna\TargetAction\TargetActionConfigValidator;
use Luna\View\ViewRenderer;
use Luna\WooCommerce\WooCommerceHposValidator;
use Luna\WooCommerce\WooCommerceTransferRunner;
use Luna\WooCommerce\WooCommerceWebhookHandler;

if (! function_exists('safeList')) {
    function safeList(Closure $repositoryFactory): array
    {
        try {
            return $repositoryFactory()->all();
        } catch (Throwable) {
            return [];
        }
    }
}

if (! function_exists('deploymentTargetValues')) {
    function deploymentTargetValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'environment' => (string) $request->post('environment', 'custom'),
            'public_base_url' => (string) $request->post('public_base_url', ''),
            'endpoint_base_url' => (string) $request->post('endpoint_base_url', ''),
            'webhook_base_url' => (string) $request->post('webhook_base_url', ''),
            'license_server_url' => (string) $request->post('license_server_url', ''),
            'is_default' => $request->post('is_default') !== null ? '1' : '',
            'is_active' => $request->post('is_active') !== null ? '1' : '',
            'origin' => (string) $request->post('origin', 'customer_created'),
            'support_status' => (string) $request->post('support_status', 'unverified'),
            'module_key' => (string) $request->post('module_key', ''),
            'requires_entitlement' => $request->post('requires_entitlement') !== null ? '1' : '',
        ];
    }
}

if (! function_exists('deploymentTargetErrors')) {
    function deploymentTargetErrors(array $values, DeploymentTargetUrlBuilder $urlBuilder): array
    {
        $errors = [];
        if (trim((string) ($values['name'] ?? '')) === '') {
            $errors[] = 'Name ist erforderlich.';
        }
        if (empty($values['workspace_id'])) {
            $errors[] = 'Workspace ist erforderlich.';
        }
        if (! in_array((string) ($values['environment'] ?? ''), ['local', 'staging', 'production', 'custom'], true)) {
            $errors[] = 'Environment ist ungültig.';
        }

        $environment = (string) ($values['environment'] ?? 'custom');
        foreach (['public_base_url', 'endpoint_base_url', 'webhook_base_url', 'license_server_url'] as $field) {
            $value = trim((string) ($values[$field] ?? ''));
            if ($field === 'public_base_url' || $value !== '') {
                try {
                    $normalized = $urlBuilder->normalizeBaseUrl($value);
                    $urlBuilder->assertProductionUrlAllowed($environment, $normalized);
                } catch (Throwable $exception) {
                    $errors[] = match ($field) {
                        'public_base_url' => 'Public Base URL: ' . $exception->getMessage(),
                        'endpoint_base_url' => 'Endpoint Base URL: ' . $exception->getMessage(),
                        'webhook_base_url' => 'Webhook Base URL: ' . $exception->getMessage(),
                        default => 'License Server URL: ' . $exception->getMessage(),
                    };
                }
            }
        }

        return $errors;
    }
}

if (! function_exists('currentEndpointUrl')) {
    function currentEndpointUrl(Request $request, DeploymentTargetUrlBuilder $urlBuilder, string $endpointKey): string
    {
        $https = (string) $request->server('HTTPS', '');
        $scheme = $https !== '' && strtolower($https) !== 'off' ? 'https' : (string) $request->server('REQUEST_SCHEME', 'http');
        $host = (string) $request->server('HTTP_HOST', $request->server('SERVER_NAME', 'localhost'));
        $scriptName = (string) $request->server('SCRIPT_NAME', '');

        return $urlBuilder->currentRequestBaseUrl($scheme, $host, $scriptName) . $urlBuilder->endpointPath($endpointKey);
    }
}

if (! function_exists('endpointTargetRows')) {
    function endpointTargetRows(?array $endpoint, DeploymentTargetRepository $deploymentTargets, DeploymentTargetUrlBuilder $urlBuilder): array
    {
        if ($endpoint === null) {
            return [];
        }

        $rows = [];
        foreach ($deploymentTargets->activeForWorkspace(empty($endpoint['workspace_id']) ? null : (int) $endpoint['workspace_id']) as $target) {
            $rows[] = $target + [
                'endpoint_url' => $urlBuilder->endpointUrl($target, (string) $endpoint['endpoint_key']),
            ];
        }

        return $rows;
    }
}

if (! function_exists('processValues')) {
    function processValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'process_key' => (string) $request->post('process_key', ''),
            'description' => (string) $request->post('description', ''),
            'status' => (string) $request->post('status', 'draft'),
            'default_mode' => (string) $request->post('default_mode', 'run'),
        ];
    }
}

if (! function_exists('processErrors')) {
    function processErrors(array $values): array
    {
        $errors = [];
        if (empty($values['workspace_id'])) {
            $errors[] = 'Workspace ist erforderlich.';
        }
        if (trim((string) ($values['name'] ?? '')) === '') {
            $errors[] = 'Name ist erforderlich.';
        }
        if (! in_array((string) ($values['status'] ?? ''), ['draft', 'active', 'inactive'], true)) {
            $errors[] = 'Status ist ungültig.';
        }
        if (! in_array((string) ($values['default_mode'] ?? ''), ['run', 'dry_run'], true)) {
            $errors[] = 'Standardmodus ist ungültig.';
        }
        $key = trim((string) ($values['process_key'] ?? ''));
        if ($key !== '' && preg_match('/^[a-z0-9_\\-]+$/', $key) !== 1) {
            $errors[] = 'Key darf nur Kleinbuchstaben, Zahlen, Bindestriche und Unterstriche enthalten.';
        }

        return $errors;
    }
}

if (! function_exists('processStepValues')) {
    function processStepValues(Request $request): array
    {
        return [
            'position' => (int) $request->post('position', 10),
            'name' => (string) $request->post('name', ''),
            'step_type' => (string) $request->post('step_type', 'mapping_run'),
            'reference_id' => $request->post('reference_id'),
            'config_json' => (string) $request->post('config_json', ''),
            'is_enabled' => $request->post('is_enabled') !== null ? '1' : '',
            'continue_on_error' => $request->post('continue_on_error') !== null ? '1' : '',
        ];
    }
}

if (! function_exists('processStepErrors')) {
    function processStepErrors(array $values, ?TargetActionRepository $targetActions = null, ?SchemaRegistryRepository $schemas = null): array
    {
        $errors = [];
        if (trim((string) ($values['name'] ?? '')) === '') {
            $errors[] = 'Schrittname ist erforderlich.';
        }
        $stepType = (string) ($values['step_type'] ?? '');
        if (! in_array($stepType, ['mapping_run', 'target_action', 'schema_validation'], true)) {
            $errors[] = 'Step-Typ ist ungültig.';
        }
        if ($stepType === 'mapping_run' && (int) ($values['reference_id'] ?? 0) <= 0) {
            $errors[] = 'Ein Mapping muss ausgewählt werden.';
        }
        if ($stepType === 'target_action') {
            $actionId = (int) ($values['reference_id'] ?? 0);
            if ($actionId <= 0) {
                $errors[] = 'Eine Target Action muss ausgewählt werden.';
            } elseif ($targetActions !== null && $targetActions->find($actionId) === null) {
                $errors[] = 'Target Action wurde nicht gefunden.';
            }
        }
        if ($stepType === 'schema_validation') {
            $schemaId = (int) ($values['reference_id'] ?? 0);
            if ($schemaId <= 0) {
                $errors[] = 'Ein Schema muss ausgewählt werden.';
            } elseif ($schemas !== null && $schemas->find($schemaId) === null) {
                $errors[] = 'Schema wurde nicht gefunden.';
            }
        }
        $configJson = trim((string) ($values['config_json'] ?? ''));
        if ($configJson !== '' && json_decode($configJson, true) === null && json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'Konfiguration muss gültiges JSON sein.';
        }

        return $errors;
    }
}

if (! function_exists('targetActionValues')) {
    function targetActionValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'action_key' => (string) $request->post('action_key', ''),
            'action_type' => (string) $request->post('action_type', 'http_get'),
            'is_active' => $request->post('is_active') !== null ? '1' : '',
            'config_json' => (string) $request->post('config_json', ''),
        ];
    }
}

if (! function_exists('mappingSetValues')) {
    function mappingSetValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'description' => (string) $request->post('description', ''),
            'mapping_mode' => (string) $request->post('mapping_mode', 'transfer'),
            'source_connection_id' => $request->post('source_connection_id'),
            'source_table' => (string) $request->post('source_table', ''),
            'target_connection_id' => $request->post('target_connection_id'),
            'target_table' => (string) $request->post('target_table', ''),
            'status' => (string) $request->post('status', 'draft'),
        ];
    }
}

if (! function_exists('mappingSetErrors')) {
    function mappingSetErrors(array $values): array
    {
        $errors = [];
        $mode = (string) ($values['mapping_mode'] ?? 'transfer');

        if (trim((string) $values['name']) === '') {
            $errors[] = 'Name ist erforderlich.';
        }

        if (! in_array((string) $values['status'], ['draft', 'active', 'archived'], true)) {
            $errors[] = 'Status ist ungültig.';
        }

        if (! in_array($mode, ['transfer', 'json_endpoint'], true)) {
            $errors[] = 'Mapping-Modus ist ungültig.';
        }

        if (empty($values['source_connection_id'])) {
            $errors[] = 'Primary Source Connection ist erforderlich.';
        }

        if ($mode === 'transfer' && empty($values['target_connection_id'])) {
            $errors[] = 'Target Connection ist für Transfer-Mappings erforderlich.';
        }

        return $errors;
    }
}

if (! function_exists('mappingFieldValues')) {
    function mappingFieldValues(Request $request): array
    {
        $targetColumn = trim((string) $request->post('target_column', ''));
        $sourceColumns = trim((string) $request->post('source_columns', ''));
        $transformType = (string) $request->post('transform_type', 'direct');

        return [
            'source_column' => $transformType === 'first_non_empty' && $sourceColumns !== '' ? $sourceColumns : (string) $request->post('source_column', ''),
            'source_json_path' => (string) $request->post('source_json_path', ''),
            'target_column' => $targetColumn,
            'transform_type' => $transformType,
            'default_value' => (string) $request->post('default_value', ''),
            'lookup_connection_id' => $request->post('lookup_connection_id'),
            'lookup_table' => (string) $request->post('lookup_table', ''),
            'lookup_key_column' => (string) $request->post('lookup_key_column', ''),
            'lookup_value_column' => (string) $request->post('lookup_value_column', ''),
            'lookup_key_template' => (string) $request->post('lookup_key_template', ''),
            'fallback_value' => (string) $request->post('fallback_value', ''),
            'missing_behavior' => (string) $request->post('missing_behavior', 'error'),
            'is_required' => $request->post('is_required') !== null ? '1' : '',
            'notes' => (string) $request->post('notes', ''),
            'sort_order' => (int) $request->post('sort_order', 0),
        ];
    }
}

if (! function_exists('mappingFieldErrors')) {
    function mappingFieldErrors(?array $set, array $values, ConnectionProfileRepository $connections): array
    {
        if ($set === null) {
            return ['Mapping wurde nicht gefunden.'];
        }

        $transformType = (string) ($values['transform_type'] ?? 'direct');
        $lookupConnectionId = (int) ($values['lookup_connection_id'] ?? 0);
        $targetColumn = trim((string) ($values['target_column'] ?? ''));

        if ($targetColumn === '') {
            return ['Ausgabe-Feld ist erforderlich.'];
        }

        if (preg_match('/^[A-Za-z0-9_]+$/', $targetColumn) !== 1) {
            return ['Ausgabe-Feld darf nur Buchstaben, Zahlen und Unterstriche enthalten.'];
        }

        if (! in_array($transformType, ['lookup_value', 'key_value_map_by_prefix'], true)) {
            return [];
        }

        if ($lookupConnectionId <= 0) {
            return ['Lookup Connection ist erforderlich.'];
        }

        $lookupConnection = $connections->find($lookupConnectionId);
        if ($lookupConnection === null) {
            return ['Lookup Connection wurde nicht gefunden.'];
        }

        if ((int) ($lookupConnection['workspace_id'] ?? 0) !== (int) ($set['workspace_id'] ?? 0)) {
            return ['Lookup Connection gehört nicht zum Workspace des Mappings.'];
        }

        return [];
    }
}

if (! function_exists('mappingViewData')) {
    function mappingViewData(Closure $mappings, int $id, array $extra = []): array
    {
        try {
            $mapping = $mappings()->find($id);

            return $extra + [
                'mapping' => $mapping,
                'fields' => $mapping === null ? [] : $mappings()->fieldsForSet($id),
                'error' => null,
            ];
        } catch (Throwable) {
            return $extra + [
                'mapping' => null,
                'fields' => [],
                'error' => 'Mapping konnte nicht geladen werden.',
            ];
        }
    }
}

if (! function_exists('mappingFieldsData')) {
    function mappingFieldsData(Closure $mappings, Closure $connections, Closure $pdoFactory, Closure $sourceRows, int $id, array $previewValues = [], array $extra = []): array
    {
        $data = mappingViewData($mappings, $id, $extra);
        $data['sourceColumns'] = [];
        $data['sourceSamples'] = [];
        $data['lookupColumns'] = [];
        $data['lookupSamples'] = [];
        $data['lookupTestResults'] = [];
        $data['lookupTestRequested'] = ! empty($previewValues['lookup_test']);
        $data['previewValues'] = mappingPreviewValues($previewValues);
        $data['sourceFilters'] = [];
        $data['connections'] = [];
        $data['columnWarning'] = null;
        $data['lookupWarning'] = null;

        if ($data['mapping'] === null) {
            return $data;
        }

        $data['sourceFilters'] = $mappings()->sourceFiltersForSet($id);
        if ($data['sourceFilters'] === []) {
            $data['sourceFilters'] = (new MappingSourceRowProvider())->filtersFromMappingSet($data['mapping']);
        }
        $data['previewValues'] = mappingPreviewValues($previewValues, $data['mapping']);

        try {
            $source = $connections()->find((int) $data['mapping']['source_connection_id']);
            $data['connections'] = $connections()->all();

            if ($source !== null && ! empty($data['mapping']['source_table'])) {
                $sourceConfig = ExternalDatabaseConfig::fromProfile($source, $connections()->secretsFor((int) $source['id']));
                $sourcePdo = $pdoFactory()->create($sourceConfig);
                $data['sourceColumns'] = (new SchemaInspector($sourcePdo))->columns((string) $data['mapping']['source_table']);
                $filterSet = $data['mapping'];
                $filterSet['source_filters'] = $data['sourceFilters'];
                $data['sourceSamples'] = $sourceRows()->rows($sourcePdo, (string) $data['mapping']['source_table'], $filterSet, 10);
            }

            if ((int) $data['previewValues']['lookup_connection_id'] > 0 && (string) $data['previewValues']['lookup_table'] !== '') {
                try {
                    $lookupProfile = $connections()->find((int) $data['previewValues']['lookup_connection_id']);

                    if ($lookupProfile !== null) {
                        $lookupPdo = $pdoFactory()->create(ExternalDatabaseConfig::fromProfile($lookupProfile, $connections()->secretsFor((int) $lookupProfile['id'])), false);
                        $data['lookupColumns'] = (new SchemaInspector($lookupPdo))->columns((string) $data['previewValues']['lookup_table']);
                        $data['lookupSamples'] = (new SampleDataReader($lookupPdo))->sampleRows((string) $data['previewValues']['lookup_table'], null, 10);
                    }
                } catch (Throwable) {
                    $data['lookupWarning'] = 'Lookup-Tabelle konnte nicht gelesen werden.';
                }
            }

            if ($data['lookupTestRequested']) {
                $data['lookupTestResults'] = mappingLookupTestResults($data['sourceSamples'], $data['previewValues'], $connections, $pdoFactory);
            }
        } catch (Throwable) {
            $data['columnWarning'] = 'Spalten oder Beispieldaten konnten nicht gelesen werden.';
        }

        return $data;
    }
}

if (! function_exists('endpointMappingSummary')) {
    function endpointMappingSummary(?array $endpoint, Closure $mappings, Closure $connections): array
    {
        $mappingId = (int) ($endpoint['mapping_set_id'] ?? 0);
        if ($mappingId <= 0) {
            return [
                'mapping' => null,
                'filters' => [],
                'fields' => [],
                'message' => 'Für diesen Endpoint ist kein Mapping ausgewählt.',
            ];
        }

        try {
            $mapping = $mappings()->find($mappingId);
            if ($mapping === null) {
                return [
                    'mapping' => null,
                    'filters' => [],
                    'fields' => [],
                    'message' => 'Das ausgewählte Mapping wurde nicht gefunden.',
                ];
            }

            $connectionNames = [];
            foreach ($connections()->all() as $connection) {
                $connectionNames[(int) $connection['id']] = (string) $connection['name'];
            }

            $operatorLabels = mappingSourceFilterOperatorLabels();
            $filters = array_map(static function (array $filter) use ($operatorLabels): array {
                $operator = (string) ($filter['operator'] ?? '');

                return [
                    'source_column' => (string) ($filter['source_column'] ?? ''),
                    'operator' => $operator,
                    'operator_label' => $operatorLabels[$operator] ?? $operator,
                    'filter_value' => (string) ($filter['filter_value'] ?? ''),
                    'sort_order' => (int) ($filter['sort_order'] ?? 0),
                ];
            }, $mappings()->sourceFiltersForSet($mappingId));

            $fields = [];
            foreach ($mappings()->fieldsForSet($mappingId) as $field) {
                $lookupConnectionId = (int) ($field['lookup_connection_id'] ?? 0);
                $fields[] = [
                    'id' => (int) $field['id'],
                    'sort_order' => (int) ($field['sort_order'] ?? 0),
                    'source_column' => (string) ($field['source_column'] ?? ''),
                    'target_column' => (string) ($field['target_column'] ?? ''),
                    'transform_type' => (string) ($field['transform_type'] ?? ''),
                    'transform_label' => TransformType::label((string) ($field['transform_type'] ?? '')),
                    'default_value' => (string) ($field['default_value'] ?? ''),
                    'lookup_connection' => $lookupConnectionId > 0 ? ($connectionNames[$lookupConnectionId] ?? ('#' . $lookupConnectionId)) : '',
                    'lookup_table' => (string) ($field['lookup_table'] ?? ''),
                    'lookup_key_column' => (string) ($field['lookup_key_column'] ?? ''),
                    'lookup_value_column' => (string) ($field['lookup_value_column'] ?? ''),
                    'lookup_key_template' => (string) ($field['lookup_key_template'] ?? ''),
                    'missing_behavior' => (string) ($field['missing_behavior'] ?? ''),
                    'value_rules' => $mappings()->valueRulesForField((int) $field['id']),
                ];
            }

            return [
                'mapping' => $mapping,
                'filters' => $filters,
                'fields' => $fields,
                'message' => null,
            ];
        } catch (Throwable) {
            return [
                'mapping' => null,
                'filters' => [],
                'fields' => [],
                'message' => 'Mapping-Zusammenfassung konnte nicht geladen werden.',
            ];
        }
    }
}

if (! function_exists('mappingPreviewValues')) {
    function mappingPreviewValues(array $values, array $mapping = []): array
    {
        $operator = (string) ($values['source_filter_operator'] ?? ($mapping['source_filter_operator'] ?? 'is_numeric_gt_zero'));
        $sourceColumn = (string) ($values['source_column'] ?? '');
        $sourceFilterColumn = (string) ($values['source_filter_column'] ?? ($mapping['source_filter_column'] ?? ''));

        if (! in_array($operator, ['none', 'is_numeric_gt_zero', 'numeric_gt', 'gt', 'gte', 'eq'], true)) {
            $operator = 'is_numeric_gt_zero';
        }

        return [
            'source_column' => $sourceColumn,
            'source_filter_column' => $sourceFilterColumn === '' ? $sourceColumn : $sourceFilterColumn,
            'source_filter_operator' => $operator,
            'source_filter_value' => (string) ($values['source_filter_value'] ?? ($mapping['source_filter_value'] ?? '0')),
            'target_column' => trim((string) ($values['target_column'] ?? '')),
            'transform_type' => (string) ($values['transform_type'] ?? 'lookup_value'),
            'source_columns' => (string) ($values['source_columns'] ?? ''),
            'lookup_connection_id' => (int) ($values['lookup_connection_id'] ?? 0),
            'lookup_table' => (string) ($values['lookup_table'] ?? ''),
            'lookup_key_column' => (string) ($values['lookup_key_column'] ?? ''),
            'lookup_value_column' => (string) ($values['lookup_value_column'] ?? ''),
            'lookup_key_template' => (string) ($values['lookup_key_template'] ?? ''),
            'fallback_value' => (string) ($values['fallback_value'] ?? ''),
            'missing_behavior' => (string) ($values['missing_behavior'] ?? 'nullable'),
            'sort_order' => (int) ($values['sort_order'] ?? 0),
            'lookup_test' => ! empty($values['lookup_test']),
        ];
    }
}

if (! function_exists('mappingPreviewFilterSet')) {
    function mappingPreviewFilterSet(array $values): array
    {
        return [
            'source_filter_column' => $values['source_filter_column'] ?? '',
            'source_filter_operator' => $values['source_filter_operator'] ?? 'none',
            'source_filter_value' => $values['source_filter_value'] ?? '',
        ];
    }
}

if (! function_exists('mappingSourceFilterValues')) {
    function mappingSourceFilterValues(Request $request): array
    {
        $columns = $request->post('source_column', []);
        $operators = $request->post('operator', []);
        $values = $request->post('filter_value', []);

        if (is_array($columns) || is_array($operators) || is_array($values)) {
            $rows = [];
            $max = max(count(is_array($columns) ? $columns : []), count(is_array($operators) ? $operators : []), count(is_array($values) ? $values : []));
            for ($index = 0; $index < $max; $index++) {
                $rows[] = [
                    'source_column' => is_array($columns) ? (string) ($columns[$index] ?? '') : '',
                    'operator' => is_array($operators) ? (string) ($operators[$index] ?? 'none') : 'none',
                    'filter_value' => is_array($values) ? (string) ($values[$index] ?? '') : '',
                    'sort_order' => $index,
                ];
            }

            return $rows;
        }

        return [
            [
                'source_column' => (string) $request->post('source_filter_column', ''),
                'operator' => (string) $request->post('source_filter_operator', 'none'),
                'filter_value' => (string) $request->post('source_filter_value', ''),
                'sort_order' => 0,
            ],
        ];
    }
}

if (! function_exists('mappingSourceFilterOperatorLabels')) {
    function mappingSourceFilterOperatorLabels(): array
    {
        return [
            'equals' => 'ist gleich',
            'not_equals' => 'ist nicht gleich',
            'contains' => 'enthält',
            'not_contains' => 'enthält nicht',
            'starts_with' => 'beginnt mit',
            'not_starts_with' => 'beginnt nicht mit',
            'ends_with' => 'endet mit',
            'not_ends_with' => 'endet nicht mit',
            'like' => 'LIKE',
            'not_like' => 'nicht LIKE',
            'is_empty' => 'ist leer',
            'is_not_empty' => 'ist nicht leer',
            'numeric_equals' => 'numerisch =',
            'numeric_not_equals' => 'numerisch !=',
            'numeric_greater_than' => 'numerisch >',
            'numeric_greater_or_equal' => 'numerisch >=',
            'numeric_less_than' => 'numerisch <',
            'numeric_less_or_equal' => 'numerisch <=',
            'in' => 'in Liste',
            'not_in' => 'nicht in Liste',
        ];
    }
}

if (! function_exists('woocommerceConnectionValues')) {
    function woocommerceConnectionValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'connection_id' => $request->post('connection_id'),
            'name' => trim((string) $request->post('name', '')),
        ];
    }
}

if (! function_exists('woocommerceConnectionErrors')) {
    function woocommerceConnectionErrors(array $values): array
    {
        $errors = [];
        if ((string) ($values['name'] ?? '') === '') {
            $errors[] = 'Name ist erforderlich.';
        }
        if (empty($values['connection_id'])) {
            $errors[] = 'WooCommerce-Connection ist erforderlich.';
        }

        return $errors;
    }
}

if (! function_exists('woocommerceWebhookValues')) {
    function woocommerceWebhookValues(Request $request, array $connection): array
    {
        $topic = (string) $request->post('topic', 'order.updated');
        if (! array_key_exists($topic, woocommerceWebhookTopicLabels())) {
            $topic = 'order.updated';
        }

        $name = trim((string) $request->post('webhook_name', ''));
        if ($name === '') {
            $name = woocommerceWebhookDefaultNames()[$topic] ?? 'Luna Order Updated';
        }

        return [
            'workspace_id' => $connection['workspace_id'] ?? null,
            'woocommerce_connection_id' => (int) ($connection['id'] ?? 0),
            'webhook_name' => $name,
            'topic' => $topic,
            'delivery_url' => trim((string) $request->post('delivery_url', '')),
            'expected_status' => (string) $request->post('expected_status', 'active'),
            'api_version' => 'WP REST API Integration v3',
            'is_required' => $request->post('is_required') !== null,
            'validation_status' => 'manual',
            'validation_message' => 'Webhook wurde in Luna konfiguriert. Bitte in WooCommerce manuell prüfen.',
        ];
    }
}

if (! function_exists('woocommerceExpectedWebhooks')) {
    function woocommerceExpectedWebhooks(string $deliveryUrl): array
    {
        return [
            [
                'name' => 'Luna Order Created',
                'topic' => 'order.created',
                'expected_status' => 'Active',
                'delivery_url' => $deliveryUrl,
                'api_version' => 'WP REST API Integration v3',
                'required' => true,
                'shop_check' => 'REST-Credentials fehlen',
            ],
            [
                'name' => 'Luna Order Updated',
                'topic' => 'order.updated',
                'expected_status' => 'Active',
                'delivery_url' => $deliveryUrl,
                'api_version' => 'WP REST API Integration v3',
                'required' => true,
                'shop_check' => 'REST-Credentials fehlen',
            ],
            [
                'name' => 'Luna Order Deleted',
                'topic' => 'order.deleted',
                'expected_status' => 'Active',
                'delivery_url' => $deliveryUrl,
                'api_version' => 'WP REST API Integration v3',
                'required' => false,
                'shop_check' => 'REST-Credentials fehlen',
            ],
        ];
    }
}

if (! function_exists('woocommerceWebhookTopicLabels')) {
    function woocommerceWebhookTopicLabels(): array
    {
        return [
            'order.created' => 'Bestellung erstellt (order.created)',
            'order.updated' => 'Bestellung aktualisiert (order.updated)',
            'order.deleted' => 'Bestellung gelöscht (order.deleted)',
        ];
    }
}

if (! function_exists('woocommerceWebhookDefaultNames')) {
    function woocommerceWebhookDefaultNames(): array
    {
        return [
            'order.created' => 'Luna Order Created',
            'order.updated' => 'Luna Order Updated',
            'order.deleted' => 'Luna Order Deleted',
        ];
    }
}

if (! function_exists('woocommerceDeliveryUrlInfo')) {
    function woocommerceDeliveryUrlInfo(Application $app, Request $request, array $connection): array
    {
        $baseUrl = '';
        $source = 'request';
        foreach (['APP_URL', 'LUNA_BASE_URL', 'PUBLIC_BASE_URL'] as $key) {
            $configured = trim($app->config()->string($key, ''));
            if ($configured !== '') {
                $baseUrl = $configured;
                $source = $key;
                break;
            }
        }

        if ($baseUrl === '') {
            $scheme = $request->server('HTTPS') === 'on' ? 'https' : 'http';
            $baseUrl = $scheme . '://' . (string) $request->server('HTTP_HOST', 'localhost');
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $deliveryUrl = rtrim($baseUrl, '/') . '/api/webhooks/woocommerce/' . (string) $connection['connection_token'];

        return [
            'base_url' => $baseUrl,
            'source' => $source,
            'delivery_url' => $deliveryUrl,
            'is_localhost' => $isLocalhost,
        ];
    }
}

if (! function_exists('woocommerceExportUrl')) {
    function woocommerceExportUrl(Application $app, Request $request, string $profileKey): string
    {
        $baseUrl = '';
        foreach (['APP_URL', 'LUNA_BASE_URL', 'PUBLIC_BASE_URL'] as $key) {
            $configured = trim($app->config()->string($key, ''));
            if ($configured !== '') {
                $baseUrl = $configured;
                break;
            }
        }

        if ($baseUrl === '') {
            $scheme = $request->server('HTTPS') === 'on' ? 'https' : 'http';
            $baseUrl = $scheme . '://' . (string) $request->server('HTTP_HOST', 'localhost');
        }

        return rtrim($baseUrl, '/') . '/api/exports/woocommerce/' . rawurlencode($profileKey);
    }
}

if (! function_exists('woocommerceExportParams')) {
    function woocommerceExportParams(Request $request): array
    {
        return [
            'since' => $request->query('since', ''),
            'until' => $request->query('until', ''),
            'limit' => $request->query('limit', ''),
            'offset' => $request->query('offset', ''),
            'include_raw_meta' => $request->query('include_raw_meta', ''),
            'include_item_raw_meta' => $request->query('include_item_raw_meta', ''),
            'order_id' => $request->query('order_id', ''),
            'status' => $request->query('status', ''),
        ];
    }
}

if (! function_exists('woocommerceExportProfilesForView')) {
    function woocommerceExportProfilesForView(Application $app, Request $request, array $profiles): array
    {
        foreach ($profiles as &$profile) {
            $profile['export_url'] = woocommerceExportUrl($app, $request, (string) ($profile['profile_key'] ?? ''));
        }

        return $profiles;
    }
}

if (! function_exists('mappingSampleRows')) {
    function mappingSampleRows(PDO $pdo, string $tableName, array $values): array
    {
        return (new MappingSourceRowProvider())->rows($pdo, $tableName, mappingPreviewFilterSet($values), 10);
    }
}

if (! function_exists('mappingLookupTestResults')) {
    function mappingLookupTestResults(array $sourceRows, array $values, Closure $connections, Closure $pdoFactory): array
    {
        $sourceColumn = (string) ($values['source_column'] ?? '');
        $connectionId = (int) ($values['lookup_connection_id'] ?? 0);
        $table = (string) ($values['lookup_table'] ?? '');
        $keyColumn = (string) ($values['lookup_key_column'] ?? '');
        $valueColumn = (string) ($values['lookup_value_column'] ?? '');
        $template = (string) ($values['lookup_key_template'] ?? '');
        $transformType = (string) ($values['transform_type'] ?? 'lookup_value');
        $results = [];

        if ($sourceRows === [] || $sourceColumn === '' || $connectionId <= 0 || $table === '' || $keyColumn === '' || $valueColumn === '' || $template === '') {
            return $results;
        }

        try {
            mappingAssertIdentifier($table);
            mappingAssertIdentifier($keyColumn);
            mappingAssertIdentifier($valueColumn);

            $profile = $connections()->find($connectionId);

            if ($profile === null) {
                return $results;
            }

            $pdo = $pdoFactory()->create(ExternalDatabaseConfig::fromProfile($profile, $connections()->secretsFor($connectionId)), false);
            $statement = $pdo->prepare($transformType === 'key_value_map_by_prefix'
                ? sprintf('SELECT `%s` AS lookup_key, `%s` AS lookup_value FROM `%s` WHERE `%s` LIKE :lookup_key ORDER BY `%s` LIMIT 10', $keyColumn, $valueColumn, $table, $keyColumn, $keyColumn)
                : sprintf('SELECT `%s` AS lookup_value FROM `%s` WHERE `%s` = :lookup_key LIMIT 1', $valueColumn, $table, $keyColumn));
            $renderer = new LookupKeyTemplateRenderer();

            foreach ($sourceRows as $row) {
                $sourceValue = $row[$sourceColumn] ?? null;
                $sourceValueText = trim((string) $sourceValue);
                $result = [
                    'source_column' => $sourceColumn,
                    'source_value' => $sourceValue,
                    'template' => $template,
                    'rendered_key' => '',
                    'lookup_table' => $table,
                    'lookup_key_column' => $keyColumn,
                    'lookup_value_column' => $valueColumn,
                    'status' => 'übersprungen',
                    'message' => '',
                    'found_value' => null,
                ];

                if ($sourceValueText === '' || $sourceValueText === '-' || ($transformType !== 'key_value_map_by_prefix' && (! ctype_digit($sourceValueText) || (int) $sourceValueText <= 0))) {
                    $result['message'] = 'Source-Wert ist leer, nicht numerisch oder nicht größer als 0.';
                    $results[] = $result;
                    continue;
                }

                $rendered = $renderer->render($template, $row, []);
                $result['rendered_key'] = $rendered->value;

                if (! $rendered->isValid()) {
                    $result['status'] = 'Template-Platzhalter fehlt';
                    $result['message'] = implode(', ', $rendered->missingPlaceholders);
                    $results[] = $result;
                    continue;
                }

                $statement->execute(['lookup_key' => $transformType === 'key_value_map_by_prefix' ? $rendered->value . '%' : $rendered->value]);
                $lookupRow = $transformType === 'key_value_map_by_prefix' ? $statement->fetchAll() : $statement->fetch();

                if ($transformType === 'key_value_map_by_prefix' && is_array($lookupRow) && $lookupRow !== []) {
                    $result['status'] = 'gefunden';
                    $result['found_value'] = count($lookupRow) . ' Treffer';
                    $results[] = $result;
                    continue;
                }

                if ($transformType !== 'key_value_map_by_prefix' && is_array($lookupRow)) {
                    $result['status'] = 'gefunden';
                    $result['found_value'] = $lookupRow['lookup_value'] ?? null;
                    $results[] = $result;
                    continue;
                }

                $result['status'] = 'nicht gefunden';
                $results[] = $result;
            }
        } catch (Throwable) {
            return $results;
        }

        return $results;
    }
}

if (! function_exists('mappingAssertIdentifier')) {
    function mappingAssertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Invalid SQL identifier.');
        }
    }
}

if (! function_exists('mappingQuoteIdentifier')) {
    function mappingQuoteIdentifier(string $identifier): string
    {
        mappingAssertIdentifier($identifier);

        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}

if (! function_exists('mappingLookupPreview')) {
    function mappingLookupPreview(array $field, array $sourceSample, Closure $connections, Closure $pdoFactory, LookupKeyTemplateRenderer $renderer): array
    {
        $preview = [
            'field' => (string) ($field['target_column'] ?? ''),
            'connection' => null,
            'table' => (string) ($field['lookup_table'] ?? ''),
            'columns' => [],
            'samples' => [],
            'rendered_key' => null,
            'missing_placeholders' => [],
            'found_value' => null,
            'status' => 'not_available',
            'message' => 'Lookup-Preview ist erst nach gespeicherter Lookup Connection und Lookup Tabelle verfügbar.',
        ];

        $connectionId = (int) ($field['lookup_connection_id'] ?? 0);
        $table = (string) ($field['lookup_table'] ?? '');

        if ($connectionId <= 0 || $table === '') {
            return $preview;
        }

        try {
            $profile = $connections()->find($connectionId);

            if ($profile === null) {
                $preview['status'] = 'connection_missing';
                $preview['message'] = 'Lookup Connection wurde nicht gefunden.';
                return $preview;
            }

            $preview['connection'] = ['id' => (int) $profile['id'], 'name' => (string) $profile['name']];
            $pdo = $pdoFactory()->create(ExternalDatabaseConfig::fromProfile($profile, $connections()->secretsFor($connectionId)), false);
            $preview['columns'] = (new SchemaInspector($pdo))->columns($table);
            $preview['samples'] = (new SampleDataReader($pdo))->sampleRows($table, null, 10);

            $rendered = $renderer->render((string) ($field['lookup_key_template'] ?? ''), $sourceSample, []);
            $preview['rendered_key'] = $rendered->value;
            $preview['missing_placeholders'] = $rendered->missingPlaceholders;

            if (! $rendered->isValid()) {
                $preview['status'] = 'template_placeholder_missing';
                $preview['message'] = 'Lookup-Key konnte wegen fehlender Platzhalter nicht gerendert werden.';
                return $preview;
            }

            $keyColumn = (string) ($field['lookup_key_column'] ?? '');
            $valueColumn = (string) ($field['lookup_value_column'] ?? '');

            if ($keyColumn === '' || $valueColumn === '' || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $keyColumn) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $valueColumn) !== 1) {
                $preview['status'] = 'invalid_lookup_config';
                $preview['message'] = 'Lookup-Spalten oder Tabelle sind ungültig.';
                return $preview;
            }

            $statement = $pdo->prepare(sprintf('SELECT `%s` AS lookup_value FROM `%s` WHERE `%s` = :lookup_key LIMIT 1', $valueColumn, $table, $keyColumn));
            $statement->execute(['lookup_key' => $rendered->value]);
            $row = $statement->fetch();

            if (is_array($row)) {
                $preview['found_value'] = $row['lookup_value'] ?? null;
                $preview['status'] = 'found';
                $preview['message'] = 'Lookup-Key wurde gefunden.';
                return $preview;
            }

            $preview['status'] = 'not_found';
            $preview['message'] = 'Lookup-Key wurde in der Lookup Source nicht gefunden.';
            return $preview;
        } catch (Throwable) {
            $preview['status'] = 'preview_failed';
            $preview['message'] = 'Lookup-Preview konnte nicht geladen werden.';
            return $preview;
        }
    }
}

if (! function_exists('connectionValues')) {
    function connectionValues(Request $request): array
    {
        $values = [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'type' => (string) $request->post('type', 'source'),
            'driver' => (string) $request->post('driver', 'mysql'),
            'host' => (string) $request->post('host', ''),
            'port' => (string) $request->post('port', '3306'),
            'database_name' => (string) $request->post('database_name', ''),
            'username' => (string) $request->post('username', ''),
            'charset' => (string) $request->post('charset', 'utf8mb4'),
            'notes' => (string) $request->post('notes', ''),
        ];

        if ($request->post('read_only') !== null) {
            $values['read_only'] = '1';
        }

        return ConnectionProfileData::normalize($values);
    }
}

if (! function_exists('workspaceCreateSuccessRedirect')) {
    function workspaceCreateSuccessRedirect(): Response
    {
        return new Response('', 302, ['Location' => '/admin/workspaces']);
    }
}

if (! function_exists('deleteConfirmed')) {
    function deleteConfirmed(Request $request): bool
    {
        return (string) $request->post('confirm_delete', '') === '1';
    }
}

if (! function_exists('deleteBlockedMessage')) {
    function deleteBlockedMessage(string $message, array $names = []): string
    {
        if ($names === []) {
            return $message;
        }

        return $message . ' Betroffen: ' . implode(', ', array_slice(array_map('strval', $names), 0, 5)) . '.';
    }
}

if (! function_exists('connectionTablesJsonResponse')) {
    function connectionTablesJsonResponse(Closure $connections, Closure $pdoFactory, Closure $configFor, int $connectionId): Response
    {
        try {
            $profile = $connections()->find($connectionId);

            if ($profile === null) {
                return Response::json([
                    'success' => false,
                    'connection_id' => $connectionId,
                    'tables' => [],
                    'message' => 'Connection nicht gefunden.',
                ], 404);
            }

            $tables = (new TableNameReader($pdoFactory()->create($configFor($profile), false)))->tableNames();

            return Response::json([
                'success' => true,
                'connection_id' => $connectionId,
                'tables' => array_map(static fn (array $table): array => [
                    'name' => (string) $table['name'],
                    'label' => (string) $table['name'],
                ], $tables),
            ]);
        } catch (Throwable) {
            return Response::json([
                'success' => false,
                'connection_id' => $connectionId,
                'tables' => [],
                'message' => 'Tabellen konnten nicht geladen werden.',
            ], 500);
        }
    }
}

if (! function_exists('connectionTableColumnsJsonResponse')) {
    function connectionTableColumnsJsonResponse(Closure $connections, Closure $pdoFactory, Closure $configFor, int $connectionId, string $tableName): Response
    {
        try {
            $profile = $connections()->find($connectionId);

            if ($profile === null || $tableName === '') {
                return Response::json([
                    'success' => false,
                    'connection_id' => $connectionId,
                    'table' => $tableName,
                    'columns' => [],
                    'message' => 'Connection oder Tabelle nicht gefunden.',
                ], 404);
            }

            $columns = (new SchemaInspector($pdoFactory()->create($configFor($profile), false)))->columns($tableName);

            return Response::json([
                'success' => true,
                'connection_id' => $connectionId,
                'table' => $tableName,
                'columns' => array_map(static fn (array $column): array => [
                    'name' => (string) $column['column_name'],
                    'label' => (string) $column['column_name'],
                ], $columns),
            ]);
        } catch (Throwable) {
            return Response::json([
                'success' => false,
                'connection_id' => $connectionId,
                'table' => $tableName,
                'columns' => [],
                'message' => 'Spalten konnten nicht geladen werden.',
            ], 500);
        }
    }
}

if (! function_exists('datasetTransferValues')) {
    function datasetTransferValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'description' => (string) $request->post('description', ''),
            'status' => (string) $request->post('status', 'draft'),
            'source_dataset' => (string) $request->post('source_dataset', ''),
            'target_connection_id' => $request->post('target_connection_id'),
            'target_table' => (string) $request->post('target_table', ''),
            'operation_type' => (string) $request->post('operation_type', 'upsert'),
            'upsert_key' => (string) $request->post('upsert_key', ''),
        ];
    }
}

if (! function_exists('datasetTransferFieldValues')) {
    function datasetTransferFieldValues(Request $request): array
    {
        return [
            'group_id' => $request->post('group_id'),
            'dataset_field' => (string) $request->post('dataset_field', ''),
            'target_column' => (string) $request->post('target_column', ''),
            'sort_order' => (int) $request->post('sort_order', 0),
        ];
    }
}

if (! function_exists('datasetTransferGroupValues')) {
    function datasetTransferGroupValues(Request $request): array
    {
        return [
            'name' => (string) $request->post('name', ''),
            'group_type' => (string) $request->post('group_type', 'root'),
            'source_path' => (string) $request->post('source_path', '$'),
            'target_table' => (string) $request->post('target_table', ''),
            'operation_type' => (string) $request->post('operation_type', 'upsert'),
            'upsert_key' => (string) $request->post('upsert_key', ''),
            'parent_link_source' => (string) $request->post('parent_link_source', ''),
            'parent_link_target' => (string) $request->post('parent_link_target', ''),
            'sort_order' => (int) $request->post('sort_order', 0),
        ];
    }
}

if (! function_exists('datasetTransferErrors')) {
    function datasetTransferErrors(array $values, array $fields, DatasetTransferRunner $runner): array
    {
        if ((string) ($values['status'] ?? 'draft') !== 'active' && $fields === []) {
            $errors = [];
            if (trim((string) ($values['name'] ?? '')) === '') {
                $errors[] = 'Name ist erforderlich.';
            }
            if (trim((string) ($values['source_dataset'] ?? '')) === '') {
                $errors[] = 'Source Dataset ist erforderlich.';
            }
            if (empty($values['target_connection_id'])) {
                $errors[] = 'Target Connection ist für Transfers erforderlich.';
            }
            if (trim((string) ($values['target_table'] ?? '')) === '') {
                $errors[] = 'Target Table ist für Transfers erforderlich.';
            }

            return $errors;
        }

        return $runner->validate($values, $fields);
    }
}

if (! function_exists('datasetTransferGroupsWithFields')) {
    function datasetTransferGroupsWithFields(DatasetTransferRepository $transfers, int $transferId): array
    {
        $groups = $transfers->groupsForTransfer($transferId);
        foreach ($groups as $index => $group) {
            $groups[$index]['fields'] = $transfers->fieldsForGroup((int) $group['id']);
        }

        return $groups;
    }
}

if (! function_exists('jobValues')) {
    function jobValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'mapping_set_id' => $request->post('mapping_set_id'),
            'name' => (string) $request->post('name', ''),
            'type' => 'mapping_transfer',
            'status' => (string) $request->post('status', 'draft'),
            'run_mode' => (string) $request->post('run_mode', 'manual'),
            'transfer_mode' => (string) $request->post('transfer_mode', 'insert'),
            'dry_run_default' => $request->post('dry_run_default') !== null ? '1' : '',
            'batch_size' => (int) $request->post('batch_size', 100),
            'row_limit' => $request->post('row_limit'),
            'report_enabled' => $request->post('report_enabled') !== null ? '1' : '',
            'report_recipients' => (string) $request->post('report_recipients', ''),
            'notes' => (string) $request->post('notes', ''),
        ];
    }
}

if (! function_exists('endpointValues')) {
    function endpointValues(Request $request): array
    {
        $staticResponse = trim((string) $request->post('static_response', ''));
        $config = [];
        if ($staticResponse !== '') {
            $decoded = json_decode($staticResponse, true);
            if (is_array($decoded)) {
                $config['static_response'] = $decoded;
            }
        }

        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'endpoint_key' => EndpointRepository::normalizeEndpointKey((string) $request->post('endpoint_key', '')),
            'description' => (string) $request->post('description', ''),
            'method' => (string) $request->post('method', 'GET'),
            'visibility' => 'public',
            'status' => (string) $request->post('status', 'inactive'),
            'secret_mode' => (string) $request->post('secret_mode', 'none'),
            'response_type' => 'json',
            'source_type' => 'mapping',
            'mapping_set_id' => $request->post('mapping_set_id'),
            'job_id' => $request->post('job_id'),
            'schema_id' => $request->post('schema_id'),
            'config_json' => $config === [] ? '' : (json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            'cache_enabled' => $request->post('cache_enabled') !== null ? '1' : '',
            'cache_ttl_seconds' => $request->post('cache_ttl_seconds'),
            'rate_limit_per_minute' => $request->post('rate_limit_per_minute'),
            'notes' => (string) $request->post('notes', ''),
            'static_response' => $staticResponse,
        ];
    }
}

if (! function_exists('schemaValues')) {
    function schemaValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'schema_key' => SchemaRegistryRepository::normalizeKey((string) $request->post('schema_key', '')),
            'version' => (string) $request->post('version', '1'),
            'status' => (string) $request->post('status', 'draft'),
            'description' => (string) $request->post('description', ''),
            'definition_json' => (string) $request->post('definition_json', ''),
            'example_json' => (string) $request->post('example_json', ''),
            'change_summary' => (string) $request->post('change_summary', ''),
            'validation_json' => (string) $request->post('validation_json', ''),
        ];
    }
}

if (! function_exists('schemaValidationPayload')) {
    function schemaValidationPayload(array $schema, string $postedJson): string
    {
        $postedJson = trim($postedJson);
        if ($postedJson !== '') {
            return $postedJson;
        }

        return trim((string) ($schema['example_json'] ?? ''));
    }
}

if (! function_exists('endpointErrors')) {
    function endpointErrors(array $values, bool $requireSecret, string $staticResponse, MappingRepository $mappings, ?SchemaRegistryRepository $schemas = null): array
    {
        $errors = [];
        if (trim((string) $values['name']) === '') {
            $errors[] = 'Name ist erforderlich.';
        }
        if (trim((string) $values['endpoint_key']) === '') {
            $errors[] = 'Endpoint Key ist erforderlich.';
        }
        if (! in_array((string) $values['method'], ['GET'], true)) {
            $errors[] = 'Method ist ungültig.';
        }
        if (! in_array((string) $values['status'], ['inactive', 'active'], true)) {
            $errors[] = 'Status ist ungültig.';
        }
        if (! in_array((string) $values['secret_mode'], ['none', 'optional', 'required'], true)) {
            $errors[] = 'Secret-Modus ist ungültig.';
        }
        if (empty($values['workspace_id'])) {
            $errors[] = 'Workspace ist erforderlich.';
        }
        if (empty($values['mapping_set_id'])) {
            $errors[] = 'Mapping ist erforderlich.';
        } else {
            $mapping = $mappings->find((int) $values['mapping_set_id']);
            if ($mapping === null) {
                $errors[] = 'Mapping wurde nicht gefunden.';
            } elseif ((int) ($mapping['workspace_id'] ?? 0) !== (int) ($values['workspace_id'] ?? 0)) {
                $errors[] = 'Mapping gehört nicht zum gewählten Workspace.';
            }
        }
        if ($requireSecret && (string) $values['secret_mode'] === 'required') {
            $errors[] = 'Für Secret-Modus required muss ein Secret gesetzt werden.';
        }
        if ($staticResponse !== '' && json_decode($staticResponse, true) === null && json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'Static Response JSON ist ungültig.';
        }

        if (! empty($values['schema_id']) && $schemas !== null) {
            $schema = $schemas->find((int) $values['schema_id']);
            if ($schema === null) {
                $errors[] = 'Schema wurde nicht gefunden.';
            } elseif ((int) ($schema['workspace_id'] ?? 0) !== (int) ($values['workspace_id'] ?? 0)) {
                $errors[] = 'Schema gehört nicht zum gewählten Workspace.';
            }
        }
        return $errors;
    }
}

if (! function_exists('workspaceValues')) {
    function workspaceValues(Request $request): array
    {
        $name = trim((string) $request->post('name', ''));
        $slug = WorkspaceRepository::normalizeSlug((string) $request->post('slug', ''));

        return [
            'name' => $name,
            'slug' => $slug !== '' ? $slug : WorkspaceRepository::normalizeSlug($name),
            'description' => trim((string) $request->post('description', '')),
            'status' => (string) $request->post('status', 'active'),
        ];
    }
}

if (! function_exists('workspaceErrors')) {
    function workspaceErrors(array $values, WorkspaceRepository $workspaces, ?int $ignoreId = null): array
    {
        $errors = [];

        if ((string) $values['name'] === '') {
            $errors[] = 'Name ist erforderlich.';
        }

        if ((string) $values['slug'] === '') {
            $errors[] = 'Slug konnte nicht erzeugt werden.';
        }

        if (! in_array((string) $values['status'], ['active', 'archived', 'disabled'], true)) {
            $errors[] = 'Status ist ungültig.';
        }

        if ((string) $values['slug'] !== '' && $workspaces->slugExists((string) $values['slug'], $ignoreId)) {
            $errors[] = 'Slug ist bereits vergeben.';
        }

        return $errors;
    }
}

return static function (RouteCollection $routes, Application $app): void {
    $view = $app->services()->get('view');

    if (! $view instanceof ViewRenderer) {
        throw new RuntimeException('View service is not available.');
    }

    $admin = static fn (string $template, array $data = []): Response => Response::html($view->render(
        $template,
        array_merge(['appName' => $app->config()->string('APP_NAME', 'Luna V3')], $data),
        'layouts/admin',
    ));

    $connections = static fn (): ConnectionProfileRepository => $app->services()->get('repository.connections');
    $workspaces = static fn (): WorkspaceRepository => $app->services()->get('repository.workspaces');
    $metadata = static fn (): SchemaMetadataRepository => $app->services()->get('repository.schema_metadata');
    $mappings = static fn (): MappingRepository => $app->services()->get('repository.mappings');
    $audit = static fn (): AuditLogRepository => $app->services()->get('repository.audit_log');
    $jobs = static fn (): JobRepository => $app->services()->get('repository.jobs');
    $runs = static fn (): JobRunRepository => $app->services()->get('repository.job_runs');
    $processes = static fn (): ProcessRepository => $app->services()->get(ProcessRepository::class);
    $processRuns = static fn (): ProcessRunRepository => $app->services()->get(ProcessRunRepository::class);
    $processTriggers = static fn (): ProcessTriggerRepository => $app->services()->get(ProcessTriggerRepository::class);
    $processTriggerService = static fn (): ProcessTriggerService => $app->services()->get(ProcessTriggerService::class);
    $processTriggerRunner = static fn (): ProcessTriggerRunner => $app->services()->get(ProcessTriggerRunner::class);
    $targetActions = static fn (): TargetActionRepository => $app->services()->get(TargetActionRepository::class);
    $targetActionValidator = static fn (): TargetActionConfigValidator => $app->services()->get(TargetActionConfigValidator::class);
    $schemas = static fn (): SchemaRegistryRepository => $app->services()->get(SchemaRegistryRepository::class);
    $schemaDefinitionValidator = static fn (): SchemaDefinitionValidator => $app->services()->get(SchemaDefinitionValidator::class);
    $schemaValidator = static fn (): SchemaValidator => $app->services()->get(SchemaValidator::class);
    $reports = static fn (): ReportRepository => $app->services()->get('repository.reports');
    $endpoints = static fn (): EndpointRepository => $app->services()->get('repository.endpoints');
    $woocommerce = static fn (): WooCommerceIntegrationRepository => $app->services()->get(WooCommerceIntegrationRepository::class);
    $woocommerceValidator = static fn (): WooCommerceHposValidator => $app->services()->get(WooCommerceHposValidator::class);
    $woocommerceTransferRunner = static fn (): WooCommerceTransferRunner => $app->services()->get(WooCommerceTransferRunner::class);
    $woocommerceWebhookHandler = static fn (): WooCommerceWebhookHandler => $app->services()->get(WooCommerceWebhookHandler::class);
    $exportProfiles = static fn (): ExportProfileRepository => $app->services()->get(ExportProfileRepository::class);
    $woocommerceExport = static fn (): WooCommerceExportService => $app->services()->get(WooCommerceExportService::class);
    $datasets = static fn (): DatasetRegistry => $app->services()->get(DatasetRegistry::class);
    $datasetTransfers = static fn (): DatasetTransferRepository => $app->services()->get(DatasetTransferRepository::class);
    $datasetTransferRunner = static fn (): DatasetTransferRunner => $app->services()->get(DatasetTransferRunner::class);
    $endpointExporter = static fn (): EndpointRuntimeExporter => $app->services()->get(EndpointRuntimeExporter::class);
    $endpointContractExporter = static fn (): EndpointExportContractService => $app->services()->get(EndpointExportContractService::class);
    $endpointArchive = static fn (): EndpointExportArchiveService => $app->services()->get(EndpointExportArchiveService::class);
    $deploymentTargets = static fn (): DeploymentTargetRepository => $app->services()->get(DeploymentTargetRepository::class);
    $targetUrlBuilder = static fn (): DeploymentTargetUrlBuilder => $app->services()->get(DeploymentTargetUrlBuilder::class);
    $sourceRows = static fn (): MappingSourceRowProvider => $app->services()->get(MappingSourceRowProvider::class);
    $jobRunner = static fn (): JobRunner => $app->services()->get('jobs.runner');
    $processRunner = static fn (): ProcessRunner => $app->services()->get(ProcessRunner::class);
    $triggerUrlBuilder = static fn (): TriggerUrlBuilder => $app->services()->get(TriggerUrlBuilder::class);
    $reportMailer = static fn (): ReportMailer => $app->services()->get('reports.mailer');
    $validator = static fn (): MappingValidator => $app->services()->get('mapping.validator');
    $pdoFactory = static fn (): ExternalPdoConnectionFactory => $app->services()->get('connections.pdo_factory');
    $configFor = static function (array $profile) use ($connections): ExternalDatabaseConfig {
        return ExternalDatabaseConfig::fromProfile($profile, $connections()->secretsFor((int) $profile['id']));
    };

    $processShowData = static function (array $process, array $extra = []) use ($processes, $processRuns, $processTriggers, $targetActions, $schemas, $workspaces, $mappings, $triggerUrlBuilder): array {
        $processId = (int) $process['id'];
        $triggers = $processTriggers()->forProcess($processId);
        $target = $triggerUrlBuilder()->defaultTargetForWorkspace(empty($process['workspace_id']) ? null : (int) $process['workspace_id']);
        $triggerUrls = [];
        foreach ($triggers as $trigger) {
            $triggerUrls[(int) $trigger['id']] = $triggerUrlBuilder()->urlForTrigger($trigger, $target);
        }

        return $extra + [
            'title' => 'Prozess',
            'active' => 'processes',
            'process' => $process,
            'steps' => $processes()->stepsForProcess($processId),
            'runs' => $processRuns()->runsForProcess($processId),
            'triggers' => $triggers,
            'targetActions' => $targetActions()->forWorkspace(empty($process['workspace_id']) ? null : (int) $process['workspace_id']),
            'schemas' => $schemas()->forWorkspace(empty($process['workspace_id']) ? null : (int) $process['workspace_id']),
            'triggerTypes' => ProcessTriggerRepository::TYPES,
            'triggerValues' => [
                'name' => '',
                'trigger_type' => 'manual',
                'trigger_key' => '',
                'is_active' => '1',
                'config_json' => '',
            ],
            'triggerErrors' => [],
            'triggerUrls' => $triggerUrls,
            'triggerTarget' => $target,
            'workspaces' => safeList($workspaces),
            'mappings' => safeList($mappings),
            'values' => $process,
            'stepValues' => ['position' => 10, 'step_type' => 'mapping_run', 'is_enabled' => '1'],
            'errors' => [],
            'stepErrors' => [],
            'alert' => null,
        ];
    };

    $dashboardData = static fn (): array => [
        'workspaceCount' => count(safeList($workspaces)),
        'connectionCount' => count(safeList($connections)),
        'mappingCount' => count(safeList($mappings)),
        'jobCount' => count(safeList($jobs)),
    ];

    $woocommerceShowResponse = static function (Request $request, array $connection, ?array $alert = null, ?array $validation = null) use ($admin, $app, $woocommerce, $exportProfiles): Response {
        $id = (int) $connection['id'];
        $deliveryUrlInfo = woocommerceDeliveryUrlInfo($app, $request, $connection);

        return $admin('admin/woocommerce/show', [
            'title' => 'WooCommerce - Anbindung',
            'active' => 'woocommerce',
            'connection' => $connection,
            'webhooks' => $woocommerce()->webhooksForConnection($id),
            'queue' => $woocommerce()->transferQueueForConnection($id),
            'runs' => $woocommerce()->recentRunsForConnection($id),
            'lastSuccessfulRun' => $exportProfiles()->latestSuccessfulRunForWooCommerceConnection($id),
            'exportProfiles' => woocommerceExportProfilesForView($app, $request, $exportProfiles()->wooCommerceProfilesForConnection($id)),
            'exportRuns' => $exportProfiles()->recentRunsForWooCommerceConnection($id),
            'webhookEvents' => $woocommerce()->recentWebhookEventsForConnection($id),
            'expectedWebhooks' => woocommerceExpectedWebhooks((string) $deliveryUrlInfo['delivery_url']),
            'deliveryUrlInfo' => $deliveryUrlInfo,
            'topicLabels' => woocommerceWebhookTopicLabels(),
            'defaultNames' => woocommerceWebhookDefaultNames(),
            'validation' => $validation,
            'alert' => $alert,
        ]);
    };

    $routes->get('/', static fn (): Response => Response::html($view->render(
        'admin/dashboard',
        $dashboardData() + [
            'appName' => $app->config()->string('APP_NAME', 'Luna V3'),
            'title' => 'Dashboard',
            'active' => 'dashboard',
        ],
        'layouts/admin',
    )), 'web.home', 'web');

    $routes->get('/admin', static fn (): Response => $admin('admin/dashboard', $dashboardData() + [
        'title' => 'Dashboard',
        'active' => 'dashboard',
    ]), 'admin.dashboard', 'web');

    $routes->get('/admin/workspaces', static function () use ($admin, $workspaces): Response {
        try {
            $items = $workspaces()->all();
            $error = null;
        } catch (Throwable) {
            $items = [];
            $error = 'Luna-Systemdatenbank ist nicht erreichbar.';
        }

        return $admin('admin/workspaces', [
            'title' => 'Workspaces',
            'active' => 'workspaces',
            'workspaces' => $items,
            'error' => $error,
        ]);
    }, 'admin.workspaces', 'web');

    $routes->get('/admin/workspaces/create', static fn (): Response => $admin('admin/workspaces/create', [
        'title' => 'Workspace anlegen',
        'active' => 'workspaces',
        'values' => ['status' => 'active'],
        'errors' => [],
    ]), 'admin.workspaces.create', 'web');

    $routes->post('/admin/workspaces', static function (Request $request) use ($admin, $workspaces, $audit): Response {
        $values = workspaceValues($request);
        $errors = workspaceErrors($values, $workspaces());

        if ($errors !== []) {
            return $admin('admin/workspaces/create', [
                'title' => 'Workspace anlegen',
                'active' => 'workspaces',
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        $id = $workspaces()->create((string) $values['slug'], (string) $values['name'], trim((string) $values['description']) ?: null);
        if ((string) $values['status'] !== 'active') {
            $workspaces()->update($id, (string) $values['slug'], (string) $values['name'], trim((string) $values['description']) ?: null, (string) $values['status']);
        }
        $audit()->log($id, 'workspace.created', 'workspace', (string) $id, 'Workspace erstellt.', [
            'slug' => $values['slug'],
            'name' => $values['name'],
            'status' => $values['status'],
        ]);

        return workspaceCreateSuccessRedirect();
    }, 'admin.workspaces.store', 'web');

    $routes->get('/admin/workspaces/{id}', static function (Request $request) use ($admin, $workspaces): Response {
        $workspace = $workspaces()->find((int) $request->route('id'));

        return $admin('admin/workspaces/show', [
            'title' => $workspace['name'] ?? 'Workspace',
            'active' => 'workspaces',
            'workspace' => $workspace,
            'values' => $workspace ?? ['status' => 'active'],
            'errors' => [],
        ]);
    }, 'admin.workspaces.show', 'web');

    $routes->post('/admin/workspaces/{id}', static function (Request $request) use ($admin, $workspaces, $audit): Response {
        $id = (int) $request->route('id');
        $workspace = $workspaces()->find($id);

        if ($workspace === null) {
            return Response::notFound();
        }

        $values = workspaceValues($request);
        $errors = workspaceErrors($values, $workspaces(), $id);

        if ($errors !== []) {
            return $admin('admin/workspaces/show', [
                'title' => 'Workspace bearbeiten',
                'active' => 'workspaces',
                'workspace' => $workspace,
                'values' => $values + ['id' => $id],
                'errors' => $errors,
            ]);
        }

        $workspaces()->update($id, (string) $values['slug'], (string) $values['name'], trim((string) $values['description']) ?: null, (string) $values['status']);
        $audit()->log($id, 'workspace.updated', 'workspace', (string) $id, 'Workspace aktualisiert.', [
            'slug' => $values['slug'],
            'name' => $values['name'],
            'status' => $values['status'],
        ]);

        return new Response('', 302, ['Location' => '/admin/workspaces/' . $id]);
    }, 'admin.workspaces.update', 'web');

    $routes->post('/admin/workspaces/{id}/delete', static function (Request $request) use ($admin, $workspaces, $audit): Response {
        $id = (int) $request->route('id');
        $workspace = $workspaces()->find($id);

        if ($workspace === null) {
            return Response::notFound();
        }

        if (! deleteConfirmed($request)) {
            return $admin('admin/workspaces/show', [
                'title' => 'Workspace bearbeiten',
                'active' => 'workspaces',
                'workspace' => $workspace,
                'values' => $workspace,
                'errors' => ['Löschen wurde nicht bestätigt.'],
            ]);
        }

        try {
            $check = $workspaces()->canDelete($id);
        } catch (Throwable) {
            return $admin('admin/workspaces/show', [
                'title' => 'Workspace bearbeiten',
                'active' => 'workspaces',
                'workspace' => $workspace,
                'values' => $workspace,
                'errors' => ['Workspace konnte nicht gelöscht werden.'],
            ]);
        }

        if (! $check->allowed) {
            return $admin('admin/workspaces/show', [
                'title' => 'Workspace bearbeiten',
                'active' => 'workspaces',
                'workspace' => $workspace,
                'values' => $workspace,
                'errors' => [deleteBlockedMessage($check->message, $check->blockingNames)],
            ]);
        }

        try {
            $workspaces()->delete($id);
            $audit()->log($id, 'workspace.deleted', 'workspace', (string) $id, 'Workspace gelöscht.', [
                'slug' => $workspace['slug'] ?? '',
                'name' => $workspace['name'] ?? '',
            ]);
        } catch (Throwable) {
            return $admin('admin/workspaces/show', [
                'title' => 'Workspace bearbeiten',
                'active' => 'workspaces',
                'workspace' => $workspace,
                'values' => $workspace,
                'errors' => ['Workspace konnte nicht gelöscht werden.'],
            ]);
        }

        return new Response('', 302, ['Location' => '/admin/workspaces']);
    }, 'admin.workspaces.delete', 'web');

    $routes->get('/admin/connections', static function () use ($admin, $connections): Response {
        try {
            $profiles = $connections()->all();
            $error = null;
        } catch (Throwable) {
            $profiles = [];
            $error = 'Luna-Systemdatenbank ist nicht erreichbar.';
        }

        return $admin('admin/connections/index', [
            'title' => 'Connections',
            'active' => 'connections',
            'connections' => $profiles,
            'error' => $error,
        ]);
    }, 'admin.connections', 'web');

    $routes->get('/admin/connections/create', static function () use ($admin, $workspaces): Response {
        try {
            $workspaceItems = $workspaces()->all();
            $error = null;
        } catch (Throwable) {
            $workspaceItems = [];
            $error = 'Luna-Systemdatenbank ist nicht erreichbar.';
        }

        return $admin('admin/connections/create', [
            'title' => 'Connection anlegen',
            'active' => 'connections',
            'workspaces' => $workspaceItems,
            'error' => $error,
            'values' => ['type' => 'source', 'driver' => 'mysql', 'port' => '3306', 'charset' => 'utf8mb4', 'read_only' => '1'],
            'roles' => ConnectionProfileData::roles(),
            'drivers' => ConnectionProfileData::drivers(),
            'errors' => [],
        ]);
    }, 'admin.connections.create', 'web');

    $routes->post('/admin/connections', static function (Request $request) use ($admin, $connections, $workspaces): Response {
        $values = connectionValues($request);
        $errors = ConnectionProfileData::validate($values);

        if ($errors !== []) {
            return $admin('admin/connections/create', [
                'title' => 'Connection anlegen',
                'active' => 'connections',
                'workspaces' => safeList($workspaces),
                'values' => $values,
                'roles' => ConnectionProfileData::roles(),
                'drivers' => ConnectionProfileData::drivers(),
                'errors' => $errors,
                'error' => null,
            ]);
        }

        try {
            $id = $connections()->create($values, ConnectionProfileData::secretsFromPassword((string) $request->post('password', '')));

            return new Response('', 302, ['Location' => '/admin/connections/' . $id]);
        } catch (Throwable) {
            return $admin('admin/connections/create', [
                'title' => 'Connection anlegen',
                'active' => 'connections',
                'workspaces' => safeList($workspaces),
                'values' => $values,
                'roles' => ConnectionProfileData::roles(),
                'drivers' => ConnectionProfileData::drivers(),
                'errors' => ['Connection konnte nicht gespeichert werden. APP_KEY und Systemdatenbank prüfen.'],
                'error' => null,
            ]);
        }
    }, 'admin.connections.store', 'web');

    $routes->get('/admin/connections/{id}/edit', static function (Request $request) use ($admin, $connections, $workspaces): Response {
        $profile = $connections()->find((int) $request->route('id'));

        if ($profile === null) {
            return Response::notFound();
        }

        return $admin('admin/connections/edit', [
            'title' => 'Connection bearbeiten',
            'active' => 'connections',
            'connection' => $profile,
            'workspaces' => safeList($workspaces),
            'values' => $profile,
            'roles' => ConnectionProfileData::roles(),
            'drivers' => ConnectionProfileData::drivers(),
            'errors' => [],
            'error' => null,
        ]);
    }, 'admin.connections.edit', 'web');

    $routes->post('/admin/connections/{id}/edit', static function (Request $request) use ($admin, $connections, $workspaces): Response {
        $id = (int) $request->route('id');
        $profile = $connections()->find($id);

        if ($profile === null) {
            return Response::notFound();
        }

        $values = connectionValues($request);
        $errors = ConnectionProfileData::validate($values);

        if ($errors !== []) {
            return $admin('admin/connections/edit', [
                'title' => 'Connection bearbeiten',
                'active' => 'connections',
                'connection' => $profile,
                'workspaces' => safeList($workspaces),
                'values' => $values + ['id' => $id],
                'roles' => ConnectionProfileData::roles(),
                'drivers' => ConnectionProfileData::drivers(),
                'errors' => $errors,
                'error' => null,
            ]);
        }

        try {
            $connections()->update($id, $values, ConnectionProfileData::secretsFromPassword((string) $request->post('password', '')));
        } catch (Throwable) {
            return $admin('admin/connections/edit', [
                'title' => 'Connection bearbeiten',
                'active' => 'connections',
                'connection' => $profile,
                'workspaces' => safeList($workspaces),
                'values' => $values + ['id' => $id],
                'roles' => ConnectionProfileData::roles(),
                'drivers' => ConnectionProfileData::drivers(),
                'errors' => ['Connection konnte nicht gespeichert werden. Konfiguration prüfen.'],
                'error' => null,
            ]);
        }

        return new Response('', 302, ['Location' => '/admin/connections']);
    }, 'admin.connections.update', 'web');

    $routes->get('/admin/connections/{id}', static function (Request $request) use ($admin, $connections): Response {
        try {
            $profile = $connections()->find((int) $request->route('id'));
        } catch (Throwable) {
            return $admin('admin/connections/show', [
                'title' => 'Connection',
                'active' => 'connections',
                'connection' => null,
                'alert' => ['type' => 'danger', 'message' => 'Luna-Systemdatenbank ist nicht erreichbar.'],
            ]);
        }

        return $admin('admin/connections/show', [
            'title' => $profile['name'] ?? 'Connection',
            'active' => 'connections',
            'connection' => $profile,
            'alert' => null,
        ]);
    }, 'admin.connections.show', 'web');

    $routes->post('/admin/connections/{id}/test', static function (Request $request) use ($admin, $app, $connections, $configFor): Response {
        try {
            $profile = $connections()->find((int) $request->route('id'));

            if ($profile === null) {
                throw new RuntimeException('Connection nicht gefunden.');
            }

            /** @var ConnectionTester $tester */
            $tester = $app->services()->get('connections.tester');
            $result = $tester->test($configFor($profile));
            $alert = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
        } catch (Throwable) {
            $profile = $profile ?? null;
            $alert = ['type' => 'danger', 'message' => 'Verbindungstest konnte nicht ausgeführt werden.'];
        }

        return $admin('admin/connections/show', [
            'title' => $profile['name'] ?? 'Connection',
            'active' => 'connections',
            'connection' => $profile,
            'alert' => $alert,
        ]);
    }, 'admin.connections.test', 'web');

    $routes->post('/admin/connections/{id}/delete', static function (Request $request) use ($admin, $connections, $audit): Response {
        $id = (int) $request->route('id');
        $profile = $connections()->find($id);

        if ($profile === null) {
            return Response::notFound();
        }

        if (! deleteConfirmed($request)) {
            return $admin('admin/connections/show', [
                'title' => $profile['name'] ?? 'Connection',
                'active' => 'connections',
                'connection' => $profile,
                'alert' => ['type' => 'danger', 'message' => 'Löschen wurde nicht bestätigt.'],
            ]);
        }

        try {
            $check = $connections()->canDelete($id);
        } catch (Throwable) {
            return $admin('admin/connections/show', [
                'title' => $profile['name'] ?? 'Connection',
                'active' => 'connections',
                'connection' => $profile,
                'alert' => ['type' => 'danger', 'message' => 'Connection konnte nicht gelöscht werden.'],
            ]);
        }

        if (! $check->allowed) {
            return $admin('admin/connections/show', [
                'title' => $profile['name'] ?? 'Connection',
                'active' => 'connections',
                'connection' => $profile,
                'alert' => ['type' => 'danger', 'message' => deleteBlockedMessage($check->message, $check->blockingNames)],
            ]);
        }

        try {
            $connections()->delete($id);
            $audit()->log(
                empty($profile['workspace_id']) ? null : (int) $profile['workspace_id'],
                'connection.deleted',
                'connection_profile',
                (string) $id,
                'Connection gelöscht.',
                ['name' => $profile['name'] ?? '', 'type' => $profile['type'] ?? '', 'driver' => $profile['driver'] ?? ''],
            );
        } catch (Throwable) {
            return $admin('admin/connections/show', [
                'title' => $profile['name'] ?? 'Connection',
                'active' => 'connections',
                'connection' => $profile,
                'alert' => ['type' => 'danger', 'message' => 'Connection konnte nicht gelöscht werden.'],
            ]);
        }

        return new Response('', 302, ['Location' => '/admin/connections']);
    }, 'admin.connections.delete', 'web');

    $routes->get('/admin/schema', static function () use ($admin, $connections): Response {
        try {
            $profiles = array_filter($connections()->all(), static fn (array $profile): bool => (int) $profile['is_active'] === 1);
            $error = null;
        } catch (Throwable) {
            $profiles = [];
            $error = 'Luna-Systemdatenbank ist nicht erreichbar.';
        }

        return $admin('admin/schema/index', [
            'title' => 'Schema Explorer',
            'active' => 'schema',
            'connections' => $profiles,
            'error' => $error,
        ]);
    }, 'admin.schema', 'web');

    $connectionTablesJson = static fn (int $connectionId): Response => connectionTablesJsonResponse(
        $connections,
        $pdoFactory,
        $configFor,
        $connectionId,
    );

    $routes->get('/admin/schema/{connectionId}/tables.json', static function (Request $request) use ($connectionTablesJson): Response {
        return $connectionTablesJson((int) $request->route('connectionId', 0));
    }, 'admin.schema.tables_json', 'web');

    $routes->get('/admin/api/connection-tables', static function (Request $request) use ($connectionTablesJson): Response {
        return $connectionTablesJson((int) $request->query('connection_id', 0));
    }, 'admin.api.connection_tables', 'web');

    $routes->get('/admin/api/connection-table-columns', static function (Request $request) use ($connections, $pdoFactory, $configFor): Response {
        return connectionTableColumnsJsonResponse(
            $connections,
            $pdoFactory,
            $configFor,
            (int) $request->query('connection_id', 0),
            (string) $request->query('table', ''),
        );
    }, 'admin.api.connection_table_columns', 'web');

    $routes->get('/admin/schema/{connectionId}/table', static function (Request $request) use ($admin, $connections, $configFor, $pdoFactory, $metadata): Response {
        $tableName = (string) $request->query('table', '');

        try {
            $profile = $connections()->find((int) $request->route('connectionId'));

            if ($profile === null || $tableName === '') {
                throw new RuntimeException('Tabelle nicht gefunden.');
            }

            $pdo = $pdoFactory()->create($configFor($profile));
            $columns = (new SchemaInspector($pdo))->columns($tableName);
            $samples = (new SampleDataReader($pdo))->sampleRows($tableName);
            $tableNote = $metadata()->tableNote((int) $profile['id'], null, $tableName);
            $columnNotes = [];

            foreach ($columns as $column) {
                $columnNotes[$column['column_name']] = $metadata()->columnNote((int) $profile['id'], null, $tableName, $column['column_name']);
            }

            $alert = null;
        } catch (Throwable) {
            $profile = $profile ?? null;
            $columns = [];
            $samples = [];
            $tableNote = null;
            $columnNotes = [];
            $alert = ['type' => 'danger', 'message' => 'Tabellenanalyse konnte nicht geladen werden.'];
        }

        return $admin('admin/schema/table', [
            'title' => 'Tabellenanalyse',
            'active' => 'schema',
            'connection' => $profile,
            'tableName' => $tableName,
            'columns' => $columns,
            'samples' => $samples,
            'tableNote' => $tableNote,
            'columnNotes' => $columnNotes,
            'alert' => $alert,
        ]);
    }, 'admin.schema.table', 'web');

    $routes->post('/admin/schema/{connectionId}/table-note', static function (Request $request) use ($connections, $metadata): Response {
        $connectionId = (int) $request->route('connectionId');
        $tableName = (string) $request->post('table_name', '');
        $note = trim((string) $request->post('note', '')) ?: null;
        $profile = $connections()->find($connectionId);
        $metadata()->saveTableNote($connectionId, isset($profile['workspace_id']) ? (int) $profile['workspace_id'] : null, null, $tableName, $note);

        return new Response('', 302, ['Location' => '/admin/schema/' . $connectionId . '/table?table=' . rawurlencode($tableName)]);
    }, 'admin.schema.table_note', 'web');

    $routes->post('/admin/schema/{connectionId}/column-note', static function (Request $request) use ($connections, $metadata): Response {
        $connectionId = (int) $request->route('connectionId');
        $tableName = (string) $request->post('table_name', '');
        $columnName = (string) $request->post('column_name', '');
        $note = trim((string) $request->post('note', '')) ?: null;
        $profile = $connections()->find($connectionId);
        $metadata()->saveColumnNote($connectionId, isset($profile['workspace_id']) ? (int) $profile['workspace_id'] : null, null, $tableName, $columnName, $note);

        return new Response('', 302, ['Location' => '/admin/schema/' . $connectionId . '/table?table=' . rawurlencode($tableName)]);
    }, 'admin.schema.column_note', 'web');

    $routes->get('/admin/schema/{connectionId}', static function (Request $request) use ($admin, $connections, $configFor, $pdoFactory): Response {
        try {
            $profile = $connections()->find((int) $request->route('connectionId'));

            if ($profile === null) {
                throw new RuntimeException('Connection nicht gefunden.');
            }

            $tables = (new SchemaInspector($pdoFactory()->create($configFor($profile))))->tables();
            $alert = null;
        } catch (Throwable) {
            $profile = $profile ?? null;
            $tables = [];
            $alert = ['type' => 'danger', 'message' => 'Schema konnte nicht gelesen werden. Verbindung und Berechtigungen prüfen.'];
        }

        return $admin('admin/schema/index', [
            'title' => 'Schema Explorer',
            'active' => 'schema',
            'connections' => [],
            'connection' => $profile,
            'tables' => $tables,
            'alert' => $alert,
            'error' => null,
        ]);
    }, 'admin.schema.connection', 'web');

    $routes->get('/admin/mappings', static function () use ($admin, $mappings): Response {
        try {
            $items = $mappings()->all();
            $error = null;
        } catch (Throwable) {
            $items = [];
            $error = 'Luna-Systemdatenbank ist nicht erreichbar.';
        }

        return $admin('admin/mappings/index', [
            'title' => 'Mappings',
            'active' => 'mappings',
            'mappings' => $items,
            'error' => $error,
        ]);
    }, 'admin.mappings', 'web');

    $routes->get('/admin/mappings/create', static fn (): Response => $admin('admin/mappings/create', [
        'title' => 'Mapping anlegen',
        'active' => 'mappings',
        'workspaces' => safeList($workspaces),
        'connections' => safeList($connections),
        'values' => ['status' => 'draft', 'mapping_mode' => 'transfer'],
        'errors' => [],
    ]), 'admin.mappings.create', 'web');

    $routes->post('/admin/mappings', static function (Request $request) use ($admin, $mappings, $audit, $workspaces, $connections): Response {
        $values = mappingSetValues($request);
        $errors = mappingSetErrors($values);

        if ($errors !== []) {
            return $admin('admin/mappings/create', [
                'title' => 'Mapping anlegen',
                'active' => 'mappings',
                'workspaces' => safeList($workspaces),
                'connections' => safeList($connections),
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        try {
            $id = $mappings()->createSet($values);
            $audit()->log(
                empty($values['workspace_id']) ? null : (int) $values['workspace_id'],
                'mapping_set.created',
                'mapping_set',
                (string) $id,
                'Mapping Set erstellt.',
                ['name' => $values['name']],
            );

            return new Response('', 302, ['Location' => '/admin/mappings/' . $id]);
        } catch (Throwable) {
            return $admin('admin/mappings/create', [
                'title' => 'Mapping anlegen',
                'active' => 'mappings',
                'workspaces' => safeList($workspaces),
                'connections' => safeList($connections),
                'values' => $values,
                'errors' => ['Mapping Set konnte nicht gespeichert werden.'],
            ]);
        }
    }, 'admin.mappings.store', 'web');

    $routes->get('/admin/mappings/{id}', static function (Request $request) use ($admin, $mappings): Response {
        $id = (int) $request->route('id');

        return $admin('admin/mappings/show', mappingViewData($mappings, $id, [
            'title' => 'Mapping',
            'active' => 'mappings',
            'alert' => null,
            'validation' => null,
        ]));
    }, 'admin.mappings.show', 'web');

    $routes->post('/admin/mappings/{id}', static function (Request $request) use ($admin, $mappings, $audit, $workspaces, $connections): Response {
        $id = (int) $request->route('id');
        $values = mappingSetValues($request);
        $errors = mappingSetErrors($values);

        if ($errors !== []) {
            return $admin('admin/mappings/create', [
                'title' => 'Mapping bearbeiten',
                'active' => 'mappings',
                'workspaces' => safeList($workspaces),
                'connections' => safeList($connections),
                'values' => $values + ['id' => $id],
                'errors' => $errors,
            ]);
        }

        $mappings()->updateSet($id, $values);
        $audit()->log(
            empty($values['workspace_id']) ? null : (int) $values['workspace_id'],
            'mapping_set.updated',
            'mapping_set',
            (string) $id,
            'Mapping Set aktualisiert.',
            ['name' => $values['name']],
        );

        return $admin('admin/mappings/show', mappingViewData($mappings, $id, [
            'title' => 'Mapping',
            'active' => 'mappings',
            'alert' => ['type' => 'warning', 'message' => 'Mapping aktualisiert. Bitte erneut validieren.'],
            'validation' => null,
        ]));
    }, 'admin.mappings.update', 'web');

    $routes->post('/admin/mappings/{id}/delete', static function (Request $request) use ($admin, $mappings, $audit): Response {
        $id = (int) $request->route('id');
        $set = $mappings()->find($id);

        if ($set === null) {
            return Response::notFound();
        }

        if (! deleteConfirmed($request)) {
            return $admin('admin/mappings/show', mappingViewData($mappings, $id, [
                'title' => 'Mapping',
                'active' => 'mappings',
                'alert' => ['type' => 'danger', 'message' => 'Löschen wurde nicht bestätigt.'],
                'validation' => null,
            ]));
        }

        try {
            $check = $mappings()->canDeleteSet($id);
        } catch (Throwable) {
            return $admin('admin/mappings/show', mappingViewData($mappings, $id, [
                'title' => 'Mapping',
                'active' => 'mappings',
                'alert' => ['type' => 'danger', 'message' => 'Mapping konnte nicht gelöscht werden.'],
                'validation' => null,
            ]));
        }

        if (! $check->allowed) {
            return $admin('admin/mappings/show', mappingViewData($mappings, $id, [
                'title' => 'Mapping',
                'active' => 'mappings',
                'alert' => ['type' => 'danger', 'message' => deleteBlockedMessage($check->message, $check->blockingNames)],
                'validation' => null,
            ]));
        }

        try {
            $mappings()->deleteSet($id);
            $audit()->log(
                empty($set['workspace_id']) ? null : (int) $set['workspace_id'],
                'mapping_set.deleted',
                'mapping_set',
                (string) $id,
                'Mapping Set gelöscht.',
                ['name' => $set['name'] ?? ''],
            );
        } catch (Throwable) {
            return $admin('admin/mappings/show', mappingViewData($mappings, $id, [
                'title' => 'Mapping',
                'active' => 'mappings',
                'alert' => ['type' => 'danger', 'message' => 'Mapping konnte nicht gelöscht werden.'],
                'validation' => null,
            ]));
        }

        return new Response('', 302, ['Location' => '/admin/mappings']);
    }, 'admin.mappings.delete', 'web');

    $routes->get('/admin/mappings/{id}/fields', static function (Request $request) use ($admin, $mappings, $connections, $pdoFactory, $sourceRows): Response {
        $id = (int) $request->route('id');
        $previewValues = [
            'source_column' => $request->query('source_column', ''),
            'source_filter_column' => $request->query('source_filter_column', ''),
            'source_filter_operator' => $request->query('source_filter_operator', 'is_numeric_gt_zero'),
            'source_filter_value' => $request->query('source_filter_value', '0'),
            'target_column' => $request->query('target_column', ''),
            'transform_type' => $request->query('transform_type', 'lookup_value'),
            'source_columns' => $request->query('source_columns', ''),
            'lookup_connection_id' => $request->query('lookup_connection_id', 0),
            'lookup_table' => $request->query('lookup_table', ''),
            'lookup_key_column' => $request->query('lookup_key_column', ''),
            'lookup_value_column' => $request->query('lookup_value_column', ''),
            'lookup_key_template' => $request->query('lookup_key_template', ''),
            'fallback_value' => $request->query('fallback_value', ''),
            'missing_behavior' => $request->query('missing_behavior', 'nullable'),
            'sort_order' => $request->query('sort_order', 0),
            'lookup_test' => $request->query('lookup_test', ''),
        ];

        return $admin('admin/mappings/fields', mappingFieldsData($mappings, $connections, $pdoFactory, $sourceRows, $id, $previewValues, [
            'title' => 'Feldzuordnungen',
            'active' => 'mappings',
            'transformTypes' => TransformType::formLabels(),
            'sourceFilterOperators' => mappingSourceFilterOperatorLabels(),
        ]));
    }, 'admin.mappings.fields', 'web');

    $routes->post('/admin/mappings/{id}/source-filters', static function (Request $request) use ($admin, $mappings, $connections, $pdoFactory, $sourceRows, $audit): Response {
        $id = (int) $request->route('id');
        $set = $mappings()->find($id);

        if ($set === null) {
            return Response::notFound();
        }

        try {
            $mappings()->replaceSourceFilters($id, mappingSourceFilterValues($request));
            $audit()->log(
                isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
                'mapping_source_filters.updated',
                'mapping_set',
                (string) $id,
                'Source Filters aktualisiert.',
                ['mapping_set_id' => $id],
            );
        } catch (Throwable) {
            return $admin('admin/mappings/fields', mappingFieldsData($mappings, $connections, $pdoFactory, $sourceRows, $id, [], [
                'title' => 'Feldzuordnungen',
                'active' => 'mappings',
                'transformTypes' => TransformType::formLabels(),
                'sourceFilterOperators' => mappingSourceFilterOperatorLabels(),
                'alert' => ['type' => 'danger', 'message' => 'Source Filters konnten nicht gespeichert werden.'],
            ]));
        }

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields']);
    }, 'admin.mappings.source_filters.update', 'web');

    $routes->post('/admin/mappings/{id}/fields', static function (Request $request) use ($admin, $mappings, $connections, $pdoFactory, $sourceRows, $audit): Response {
        $id = (int) $request->route('id');
        $set = $mappings()->find($id);
        $values = mappingFieldValues($request);
        $errors = mappingFieldErrors($set, $values, $connections());
        if ($errors !== []) {
            return $admin('admin/mappings/fields', mappingFieldsData($mappings, $connections, $pdoFactory, $sourceRows, $id, $values, [
                'title' => 'Feldzuordnungen',
                'active' => 'mappings',
                'transformTypes' => TransformType::formLabels(),
                'sourceFilterOperators' => mappingSourceFilterOperatorLabels(),
                'alert' => ['type' => 'danger', 'message' => implode(' ', $errors)],
            ]));
        }

        $fieldId = $mappings()->addField($id, $values);
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_field.created',
            'mapping_field',
            (string) $fieldId,
            'Mapping Field erstellt.',
            ['mapping_set_id' => $id],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields']);
    }, 'admin.mappings.fields.store', 'web');

    $routes->post('/admin/mappings/{id}/fields/{fieldId}', static function (Request $request) use ($admin, $mappings, $connections, $pdoFactory, $sourceRows, $audit): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');
        $set = $mappings()->find($id);
        $values = mappingFieldValues($request);
        $errors = mappingFieldErrors($set, $values, $connections());
        if ($errors !== []) {
            return $admin('admin/mappings/fields', mappingFieldsData($mappings, $connections, $pdoFactory, $sourceRows, $id, $values, [
                'title' => 'Feldzuordnungen',
                'active' => 'mappings',
                'transformTypes' => TransformType::formLabels(),
                'sourceFilterOperators' => mappingSourceFilterOperatorLabels(),
                'alert' => ['type' => 'danger', 'message' => implode(' ', $errors)],
            ]));
        }

        $mappings()->updateField($fieldId, $values);
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_field.updated',
            'mapping_field',
            (string) $fieldId,
            'Mapping Field aktualisiert.',
            ['mapping_set_id' => $id],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields']);
    }, 'admin.mappings.fields.update', 'web');

    $routes->post('/admin/mappings/{id}/fields/{fieldId}/sort-order', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');
        $set = $mappings()->find($id);
        $field = $mappings()->findField($fieldId);

        if ($set === null || $field === null || (int) ($field['mapping_set_id'] ?? 0) !== $id) {
            return Response::notFound();
        }

        $sortOrder = (int) $request->post('sort_order', 0);
        $mappings()->updateFieldSortOrder($fieldId, $sortOrder);
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_field.sort_order_updated',
            'mapping_field',
            (string) $fieldId,
            'Mapping Field Sortierung aktualisiert.',
            ['mapping_set_id' => $id, 'sort_order' => $sortOrder],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields']);
    }, 'admin.mappings.fields.sort_order', 'web');

    $routes->post('/admin/mappings/{id}/fields/{fieldId}/delete', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');
        $set = $mappings()->find($id);
        $mappings()->deleteField($fieldId);
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_field.deleted',
            'mapping_field',
            (string) $fieldId,
            'Mapping Field gelöscht.',
            ['mapping_set_id' => $id],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields']);
    }, 'admin.mappings.fields.delete', 'web');

    $routes->get('/admin/mappings/{id}/fields/{fieldId}/value-rules', static function (Request $request) use ($admin, $mappings): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');

        return $admin('admin/mappings/value-rules', [
            'title' => 'Value Rules',
            'active' => 'mappings',
            'mapping' => $mappings()->find($id),
            'field' => $mappings()->findField($fieldId),
            'rules' => $mappings()->valueRulesForField($fieldId),
        ]);
    }, 'admin.mappings.value_rules', 'web');

    $routes->post('/admin/mappings/{id}/fields/{fieldId}/value-rules', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');
        $set = $mappings()->find($id);
        $ruleId = $mappings()->addValueRule(
            $fieldId,
            (string) $request->post('source_value', ''),
            (string) $request->post('target_value', ''),
            trim((string) $request->post('notes', '')) ?: null,
        );
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_value_rule.created',
            'mapping_value_rule',
            (string) $ruleId,
            'Value Rule erstellt.',
            ['mapping_set_id' => $id, 'mapping_field_id' => $fieldId],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields/' . $fieldId . '/value-rules']);
    }, 'admin.mappings.value_rules.store', 'web');

    $routes->post('/admin/mappings/{id}/fields/{fieldId}/value-rules/{ruleId}/delete', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');
        $ruleId = (int) $request->route('ruleId');
        $set = $mappings()->find($id);
        $mappings()->deleteValueRule($ruleId);
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_value_rule.deleted',
            'mapping_value_rule',
            (string) $ruleId,
            'Value Rule gelöscht.',
            ['mapping_set_id' => $id, 'mapping_field_id' => $fieldId],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields/' . $fieldId . '/value-rules']);
    }, 'admin.mappings.value_rules.delete', 'web');

    $routes->post('/admin/mappings/{id}/validate', static function (Request $request) use ($admin, $mappings, $audit, $validator): Response {
        $id = (int) $request->route('id');
        $set = $mappings()->find($id);
        $result = $validator()->validate($id);
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping.validation_run',
            'mapping_set',
            (string) $id,
            'Mapping-Validierung ausgeführt.',
            ['valid' => $result->isValid()],
        );

        return $admin('admin/mappings/show', mappingViewData($mappings, $id, [
            'title' => 'Mapping',
            'active' => 'mappings',
            'alert' => null,
            'validation' => $result,
        ]));
    }, 'admin.mappings.validate', 'web');

    $routes->post('/admin/mappings/{id}/dry-run', static function (Request $request) use ($jobRunner): Response {
        $runId = $jobRunner()->runMappingOnce((int) $request->route('id'), true, 25);
        return new Response('', 302, ['Location' => '/admin/jobs/runs/' . $runId]);
    }, 'admin.mappings.dry_run', 'web');

    $routes->post('/admin/mappings/{id}/run', static function (Request $request) use ($jobRunner): Response {
        if ($request->post('confirm') !== 'run') {
            return Response::text('Transfer confirmation missing.', 400);
        }
        $runId = $jobRunner()->runMappingOnce((int) $request->route('id'), false);
        return new Response('', 302, ['Location' => '/admin/jobs/runs/' . $runId]);
    }, 'admin.mappings.run', 'web');

    $routes->get('/admin/jobs', static fn (): Response => $admin('admin/jobs/index', [
        'title' => 'Jobs',
        'active' => 'jobs',
        'jobs' => safeList($jobs),
    ]), 'admin.jobs', 'web');

    $routes->get('/admin/jobs/create', static fn (): Response => $admin('admin/jobs/create', [
        'title' => 'Job anlegen',
        'active' => 'jobs',
        'workspaces' => safeList($workspaces),
        'mappings' => safeList($mappings),
        'values' => ['status' => 'draft', 'run_mode' => 'manual', 'transfer_mode' => 'insert', 'dry_run_default' => '1', 'batch_size' => 100],
        'errors' => [],
    ]), 'admin.jobs.create', 'web');

    $routes->post('/admin/jobs', static function (Request $request) use ($admin, $jobs, $audit, $workspaces, $mappings): Response {
        $values = jobValues($request);
        $errors = trim($values['name']) === '' ? ['Name ist erforderlich.'] : [];
        if ($errors !== []) {
            return $admin('admin/jobs/create', ['title' => 'Job anlegen', 'active' => 'jobs', 'workspaces' => safeList($workspaces), 'mappings' => safeList($mappings), 'values' => $values, 'errors' => $errors]);
        }
        $id = $jobs()->create($values);
        $audit()->log(empty($values['workspace_id']) ? null : (int) $values['workspace_id'], 'job.created', 'job', (string) $id, 'Job erstellt.');
        return new Response('', 302, ['Location' => '/admin/jobs/' . $id]);
    }, 'admin.jobs.store', 'web');

    $routes->get('/admin/jobs/{id}', static function (Request $request) use ($admin, $jobs, $runs): Response {
        $id = (int) $request->route('id');
        return $admin('admin/jobs/show', ['title' => 'Job', 'active' => 'jobs', 'job' => $jobs()->find($id), 'runs' => $runs()->runsForJob($id)]);
    }, 'admin.jobs.show', 'web');

    $routes->post('/admin/jobs/{id}', static function (Request $request) use ($jobs, $audit): Response {
        $id = (int) $request->route('id');
        $values = jobValues($request);
        $jobs()->update($id, $values);
        $audit()->log(empty($values['workspace_id']) ? null : (int) $values['workspace_id'], 'job.updated', 'job', (string) $id, 'Job aktualisiert.');
        return new Response('', 302, ['Location' => '/admin/jobs/' . $id]);
    }, 'admin.jobs.update', 'web');

    $routes->post('/admin/jobs/{id}/dry-run', static function (Request $request) use ($jobRunner): Response {
        $runId = $jobRunner()->runJob((int) $request->route('id'), true);
        return new Response('', 302, ['Location' => '/admin/jobs/runs/' . $runId]);
    }, 'admin.jobs.dry_run', 'web');

    $routes->post('/admin/jobs/{id}/run', static function (Request $request) use ($jobRunner): Response {
        if ($request->post('confirm') !== 'run') {
            return Response::text('Transfer confirmation missing.', 400);
        }
        $runId = $jobRunner()->runJob((int) $request->route('id'), false);
        return new Response('', 302, ['Location' => '/admin/jobs/runs/' . $runId]);
    }, 'admin.jobs.run', 'web');

    $routes->get('/admin/jobs/{id}/runs', static function (Request $request) use ($admin, $jobs, $runs): Response {
        $id = (int) $request->route('id');
        return $admin('admin/jobs/runs', ['title' => 'Job Runs', 'active' => 'jobs', 'job' => $jobs()->find($id), 'runs' => $runs()->runsForJob($id)]);
    }, 'admin.jobs.runs', 'web');

    $routes->get('/admin/jobs/runs/{runId}', static function (Request $request) use ($admin, $runs): Response {
        $runId = (int) $request->route('runId');
        return $admin('admin/jobs/run', ['title' => 'Job Run', 'active' => 'jobs', 'run' => $runs()->findRun($runId), 'logs' => $runs()->logsForRun($runId)]);
    }, 'admin.jobs.run_show', 'web');

    $routes->get('/admin/schemas', static fn (): Response => $admin('admin/schemas/index', [
        'title' => 'Schemas',
        'active' => 'schemas',
        'schemas' => $schemas()->all(),
        'workspaces' => safeList($workspaces),
        'values' => [
            'status' => 'draft',
            'version' => '1',
            'definition_json' => "{\n  \"type\": \"object\",\n  \"fields\": {}\n}",
        ],
        'errors' => [],
    ]), 'admin.schemas', 'web');

    $routes->post('/admin/schemas', static function (Request $request) use ($admin, $schemas, $schemaDefinitionValidator, $workspaces): Response {
        $values = schemaValues($request);
        $errors = $schemaDefinitionValidator()->validateForm($values, $schemas());
        if ($errors !== []) {
            return $admin('admin/schemas/index', [
                'title' => 'Schemas',
                'active' => 'schemas',
                'schemas' => $schemas()->all(),
                'workspaces' => safeList($workspaces),
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        $id = $schemas()->create($values);

        return new Response('', 302, ['Location' => '/admin/schemas/' . $id]);
    }, 'admin.schemas.store', 'web');

    $routes->get('/admin/schemas/{id}', static function (Request $request) use ($admin, $schemas, $workspaces): Response {
        $id = (int) $request->route('id');
        $schema = $schemas()->find($id);
        if ($schema === null) {
            return $admin('admin/schemas/show', ['title' => 'Schema', 'active' => 'schemas', 'schema' => null]);
        }

        return $admin('admin/schemas/show', [
            'title' => 'Schema',
            'active' => 'schemas',
            'schema' => $schema,
            'workspaces' => safeList($workspaces),
            'values' => $schema,
            'errors' => [],
            'validationInput' => (string) ($schema['example_json'] ?? ''),
            'validationResult' => null,
            'revisions' => $schemas()->revisionsForSchema($id),
        ]);
    }, 'admin.schemas.show', 'web');

    $routes->post('/admin/schemas/{id}', static function (Request $request) use ($admin, $schemas, $schemaDefinitionValidator, $workspaces): Response {
        $id = (int) $request->route('id');
        $schema = $schemas()->find($id);
        if ($schema === null) {
            return Response::text('Schema not found.', 404);
        }
        $values = schemaValues($request);
        $errors = $schemaDefinitionValidator()->validateForm($values, $schemas(), $id);
        if ($errors !== []) {
            return $admin('admin/schemas/show', [
                'title' => 'Schema',
                'active' => 'schemas',
                'schema' => $schema,
                'workspaces' => safeList($workspaces),
                'values' => $values,
                'errors' => $errors,
                'validationInput' => (string) ($values['validation_json'] ?? ''),
                'validationResult' => null,
                'revisions' => $schemas()->revisionsForSchema($id),
            ]);
        }

        $schemas()->update($id, $values);

        return new Response('', 302, ['Location' => '/admin/schemas/' . $id]);
    }, 'admin.schemas.update', 'web');

    $routes->post('/admin/schemas/{id}/validate', static function (Request $request) use ($admin, $schemas, $schemaValidator, $workspaces): Response {
        $id = (int) $request->route('id');
        $schema = $schemas()->find($id);
        if ($schema === null) {
            return Response::text('Schema not found.', 404);
        }

        $validationInput = schemaValidationPayload($schema, (string) $request->post('validation_json', ''));
        $validationResult = null;
        try {
            $definition = json_decode((string) ($schema['definition_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode($validationInput, true, 512, JSON_THROW_ON_ERROR);
            $validationResult = is_array($definition) ? $schemaValidator()->validate($payload, $definition) : [
                'valid' => false,
                'errors' => [['path' => '$', 'message' => 'Schema-Definition ist ungültig.']],
                'warnings' => [],
            ];
        } catch (Throwable $exception) {
            $validationResult = [
                'valid' => false,
                'errors' => [['path' => '$', 'message' => 'JSON ist ungültig: ' . $exception->getMessage()]],
                'warnings' => [],
            ];
        }

        return $admin('admin/schemas/show', [
            'title' => 'Schema',
            'active' => 'schemas',
            'schema' => $schema,
            'workspaces' => safeList($workspaces),
            'values' => $schema,
            'errors' => [],
            'validationInput' => $validationInput,
            'validationResult' => $validationResult,
            'revisions' => $schemas()->revisionsForSchema($id),
        ]);
    }, 'admin.schemas.validate', 'web');

    $routes->post('/admin/schemas/{id}/status', static function (Request $request) use ($schemas): Response {
        $id = (int) $request->route('id');
        $schemas()->updateStatus($id, (string) $request->post('status', 'draft'));

        return new Response('', 302, ['Location' => '/admin/schemas/' . $id]);
    }, 'admin.schemas.status', 'web');

    $routes->post('/admin/schemas/{id}/delete', static function (Request $request) use ($schemas): Response {
        $id = (int) $request->route('id');
        if ($request->post('confirm_delete') === '1') {
            $schemas()->delete($id);
        }

        return new Response('', 302, ['Location' => '/admin/schemas']);
    }, 'admin.schemas.delete', 'web');

    $routes->get('/admin/target-actions', static fn (): Response => $admin('admin/target-actions/index', [
        'title' => 'Target Actions',
        'active' => 'target-actions',
        'actions' => safeList($targetActions),
        'workspaces' => safeList($workspaces),
        'types' => TargetActionRepository::ALL_TYPES,
        'values' => ['action_type' => 'http_get', 'is_active' => '1'],
        'errors' => [],
    ]), 'admin.target_actions', 'web');

    $routes->post('/admin/target-actions', static function (Request $request) use ($admin, $targetActions, $targetActionValidator, $workspaces): Response {
        $values = targetActionValues($request);
        $errors = $targetActionValidator()->validate($values);
        if ($errors !== []) {
            return $admin('admin/target-actions/index', [
                'title' => 'Target Actions',
                'active' => 'target-actions',
                'actions' => safeList($targetActions),
                'workspaces' => safeList($workspaces),
                'types' => TargetActionRepository::ALL_TYPES,
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        $id = $targetActions()->create($values);

        return new Response('', 302, ['Location' => '/admin/target-actions/' . $id]);
    }, 'admin.target_actions.store', 'web');

    $routes->get('/admin/target-actions/{id}', static function (Request $request) use ($admin, $targetActions, $workspaces): Response {
        $action = $targetActions()->find((int) $request->route('id'));
        if ($action === null) {
            return Response::notFound();
        }

        return $admin('admin/target-actions/show', [
            'title' => 'Target Action',
            'active' => 'target-actions',
            'action' => $action,
            'workspaces' => safeList($workspaces),
            'types' => TargetActionRepository::ALL_TYPES,
            'values' => $action,
            'errors' => [],
        ]);
    }, 'admin.target_actions.show', 'web');

    $routes->post('/admin/target-actions/{id}', static function (Request $request) use ($admin, $targetActions, $targetActionValidator, $workspaces): Response {
        $id = (int) $request->route('id');
        $action = $targetActions()->find($id);
        if ($action === null) {
            return Response::notFound();
        }

        $values = targetActionValues($request);
        $errors = $targetActionValidator()->validate($values);
        if ($errors !== []) {
            return $admin('admin/target-actions/show', [
                'title' => 'Target Action',
                'active' => 'target-actions',
                'action' => $action,
                'workspaces' => safeList($workspaces),
                'types' => TargetActionRepository::ALL_TYPES,
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        $targetActions()->update($id, $values);

        return new Response('', 302, ['Location' => '/admin/target-actions/' . $id]);
    }, 'admin.target_actions.update', 'web');

    $routes->post('/admin/target-actions/{id}/toggle', static function (Request $request) use ($targetActions): Response {
        $id = (int) $request->route('id');
        $action = $targetActions()->find($id);
        if ($action === null) {
            return Response::notFound();
        }

        $targetActions()->setActive($id, empty($action['is_active']));

        return new Response('', 302, ['Location' => '/admin/target-actions']);
    }, 'admin.target_actions.toggle', 'web');

    $routes->post('/admin/target-actions/{id}/delete', static function (Request $request) use ($targetActions): Response {
        $id = (int) $request->route('id');
        if ($targetActions()->find($id) === null) {
            return Response::notFound();
        }

        $targetActions()->delete($id);

        return new Response('', 302, ['Location' => '/admin/target-actions']);
    }, 'admin.target_actions.delete', 'web');

    $routes->get('/admin/processes', static fn (): Response => $admin('admin/processes/index', [
        'title' => 'Prozesse',
        'active' => 'processes',
        'processes' => safeList($processes),
    ]), 'admin.processes', 'web');

    $routes->get('/admin/processes/create', static fn (): Response => $admin('admin/processes/create', [
        'title' => 'Prozess anlegen',
        'active' => 'processes',
        'workspaces' => safeList($workspaces),
        'values' => ['status' => 'draft', 'default_mode' => 'run'],
        'errors' => [],
    ]), 'admin.processes.create', 'web');

    $routes->post('/admin/processes', static function (Request $request) use ($admin, $processes, $workspaces): Response {
        $values = processValues($request);
        $errors = processErrors($values);
        if ($errors !== []) {
            return $admin('admin/processes/create', [
                'title' => 'Prozess anlegen',
                'active' => 'processes',
                'workspaces' => safeList($workspaces),
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        try {
            $id = $processes()->create($values);
        } catch (Throwable $exception) {
            return $admin('admin/processes/create', [
                'title' => 'Prozess anlegen',
                'active' => 'processes',
                'workspaces' => safeList($workspaces),
                'values' => $values,
                'errors' => [$exception->getMessage()],
            ]);
        }

        return new Response('', 302, ['Location' => '/admin/processes/' . $id]);
    }, 'admin.processes.store', 'web');

    $routes->get('/admin/processes/runs/{runId}', static function (Request $request) use ($admin, $processRuns): Response {
        $runId = (int) $request->route('runId');
        $run = $processRuns()->findRun($runId);
        if ($run === null) {
            return Response::notFound();
        }

        return $admin('admin/processes/run', [
            'title' => 'Prozesslauf',
            'active' => 'processes',
            'run' => $run,
            'logs' => $processRuns()->logsForRun($runId),
        ]);
    }, 'admin.processes.run_show', 'web');

    $routes->get('/admin/processes/{id}', static function (Request $request) use ($admin, $processes, $processShowData): Response {
        $id = (int) $request->route('id');
        $process = $processes()->find($id);
        if ($process === null) {
            return Response::notFound();
        }

        return $admin('admin/processes/show', $processShowData($process));
    }, 'admin.processes.show', 'web');

    $routes->post('/admin/processes/{id}', static function (Request $request) use ($admin, $processes, $processShowData): Response {
        $id = (int) $request->route('id');
        $process = $processes()->find($id);
        if ($process === null) {
            return Response::notFound();
        }

        $values = processValues($request);
        $errors = processErrors($values);
        if ($errors !== []) {
            return $admin('admin/processes/show', $processShowData($process, [
                'values' => $values,
                'errors' => $errors,
            ]));
        }

        try {
            $processes()->update($id, $values);
        } catch (Throwable $exception) {
            return $admin('admin/processes/show', $processShowData($process, [
                'values' => $values,
                'errors' => [$exception->getMessage()],
            ]));
        }

        return new Response('', 302, ['Location' => '/admin/processes/' . $id]);
    }, 'admin.processes.update', 'web');

    $routes->post('/admin/processes/{id}/steps', static function (Request $request) use ($admin, $processes, $targetActions, $schemas, $processShowData): Response {
        $id = (int) $request->route('id');
        $process = $processes()->find($id);
        if ($process === null) {
            return Response::notFound();
        }

        $values = processStepValues($request);
        $errors = processStepErrors($values, $targetActions(), $schemas());
        if ($errors !== []) {
            return $admin('admin/processes/show', $processShowData($process, [
                'stepValues' => $values,
                'stepErrors' => $errors,
            ]));
        }

        $processes()->addStep($id, $values);

        return new Response('', 302, ['Location' => '/admin/processes/' . $id]);
    }, 'admin.processes.steps.store', 'web');

    $routes->post('/admin/processes/{id}/steps/{stepId}', static function (Request $request) use ($processes, $targetActions, $schemas): Response {
        $id = (int) $request->route('id');
        $stepId = (int) $request->route('stepId');
        $step = $processes()->findStep($stepId);
        if ($step === null || (int) $step['process_id'] !== $id) {
            return Response::notFound();
        }

        $values = processStepValues($request);
        $errors = processStepErrors($values, $targetActions(), $schemas());
        if ($errors !== []) {
            return new Response('', 302, ['Location' => '/admin/processes/' . $id]);
        }

        $processes()->updateStep($stepId, $values);

        return new Response('', 302, ['Location' => '/admin/processes/' . $id]);
    }, 'admin.processes.steps.update', 'web');

    $routes->post('/admin/processes/{id}/steps/{stepId}/delete', static function (Request $request) use ($processes): Response {
        $id = (int) $request->route('id');
        $stepId = (int) $request->route('stepId');
        $step = $processes()->findStep($stepId);
        if ($step === null || (int) $step['process_id'] !== $id) {
            return Response::notFound();
        }

        $processes()->deleteStep($stepId);

        return new Response('', 302, ['Location' => '/admin/processes/' . $id]);
    }, 'admin.processes.steps.delete', 'web');

    $routes->post('/admin/processes/{id}/triggers', static function (Request $request) use ($admin, $processes, $processTriggerService, $processShowData): Response {
        $id = (int) $request->route('id');
        $process = $processes()->find($id);
        if ($process === null) {
            return Response::notFound();
        }

        $values = $processTriggerService()->valuesForProcess($id, $process, $request);
        $errors = $processTriggerService()->validate($values);
        if ($errors !== []) {
            return $admin('admin/processes/show', $processShowData($process, [
                'triggerValues' => $values,
                'triggerErrors' => $errors,
            ]));
        }

        try {
            $processTriggerService()->create($values, (string) $request->post('secret', ''));
        } catch (Throwable $exception) {
            return $admin('admin/processes/show', $processShowData($process, [
                'triggerValues' => $values,
                'triggerErrors' => [$exception->getMessage()],
            ]));
        }

        return new Response('', 302, ['Location' => '/admin/processes/' . $id]);
    }, 'admin.processes.triggers.store', 'web');

    $routes->post('/admin/processes/{id}/triggers/{triggerId}', static function (Request $request) use ($admin, $processes, $processTriggers, $processTriggerService, $processShowData): Response {
        $id = (int) $request->route('id');
        $triggerId = (int) $request->route('triggerId');
        $process = $processes()->find($id);
        $trigger = $processTriggers()->find($triggerId);
        if ($process === null || $trigger === null || (int) $trigger['process_id'] !== $id) {
            return Response::notFound();
        }

        $values = $processTriggerService()->valuesForProcess($id, $process, $request);
        $errors = $processTriggerService()->validate($values);
        if ($errors !== []) {
            return $admin('admin/processes/show', $processShowData($process, [
                'triggerValues' => $values,
                'triggerErrors' => $errors,
            ]));
        }

        try {
            $secret = (string) $request->post('secret', '');
            $processTriggerService()->update($triggerId, $values, $secret, trim($secret) !== '');
        } catch (Throwable $exception) {
            return $admin('admin/processes/show', $processShowData($process, [
                'triggerValues' => $values,
                'triggerErrors' => [$exception->getMessage()],
            ]));
        }

        return new Response('', 302, ['Location' => '/admin/processes/' . $id]);
    }, 'admin.processes.triggers.update', 'web');

    $routes->post('/admin/processes/{id}/triggers/{triggerId}/toggle', static function (Request $request) use ($processTriggers): Response {
        $id = (int) $request->route('id');
        $triggerId = (int) $request->route('triggerId');
        $trigger = $processTriggers()->find($triggerId);
        if ($trigger === null || (int) $trigger['process_id'] !== $id) {
            return Response::notFound();
        }

        $processTriggers()->setActive($triggerId, empty($trigger['is_active']));

        return new Response('', 302, ['Location' => '/admin/processes/' . $id]);
    }, 'admin.processes.triggers.toggle', 'web');

    $routes->post('/admin/processes/{id}/triggers/{triggerId}/delete', static function (Request $request) use ($processTriggers): Response {
        $id = (int) $request->route('id');
        $triggerId = (int) $request->route('triggerId');
        $trigger = $processTriggers()->find($triggerId);
        if ($trigger === null || (int) $trigger['process_id'] !== $id) {
            return Response::notFound();
        }

        $processTriggers()->delete($triggerId);

        return new Response('', 302, ['Location' => '/admin/processes/' . $id]);
    }, 'admin.processes.triggers.delete', 'web');

    $routes->post('/admin/processes/{id}/triggers/{triggerId}/run', static function (Request $request) use ($admin, $processes, $processTriggerRunner, $processTriggers, $processShowData): Response {
        $id = (int) $request->route('id');
        $triggerId = (int) $request->route('triggerId');
        $mode = (string) $request->post('mode', 'run');
        if (! in_array($mode, ['run', 'dry_run'], true)) {
            $mode = 'run';
        }

        $trigger = $processTriggers()->find($triggerId);
        if ($trigger === null || (int) $trigger['process_id'] !== $id) {
            return Response::notFound();
        }

        try {
            $runId = $processTriggerRunner()->runTrigger($trigger, $mode, 'ui', null, [], $id);

            return new Response('', 302, ['Location' => '/admin/processes/runs/' . $runId]);
        } catch (ProcessTriggerException $exception) {
            $process = $processes()->find($id);
            if ($process === null) {
                return Response::notFound();
            }

            return $admin('admin/processes/show', $processShowData($process, [
                'alert' => ['type' => 'danger', 'message' => $exception->getMessage()],
            ]));
        }
    }, 'admin.processes.triggers.run', 'web');

    $routes->post('/admin/processes/{id}/run', static function (Request $request) use ($admin, $processRunner, $processes, $processShowData): Response {
        $id = (int) $request->route('id');
        $mode = (string) $request->post('mode', 'run');
        if (! in_array($mode, ['run', 'dry_run'], true)) {
            $mode = 'run';
        }

        try {
            $runId = $processRunner()->run($id, $mode, 'manual');

            return new Response('', 302, ['Location' => '/admin/processes/runs/' . $runId]);
        } catch (Throwable $exception) {
            $process = $processes()->find($id);
            if ($process === null) {
                return Response::notFound();
            }

            return $admin('admin/processes/show', $processShowData($process, [
                'alert' => ['type' => 'danger', 'message' => $exception->getMessage()],
            ]));
        }
    }, 'admin.processes.run', 'web');

    $routes->get('/admin/transfers', static function () use ($admin, $datasetTransfers): Response {
        try {
            $items = $datasetTransfers()->all();
            $error = null;
        } catch (Throwable) {
            $items = [];
            $error = 'Transfers konnten nicht geladen werden.';
        }

        return $admin('admin/transfers/index', [
            'title' => 'Transfers',
            'active' => 'transfers',
            'transfers' => $items,
            'error' => $error,
        ]);
    }, 'admin.transfers', 'web');

    $routes->get('/admin/transfers/create', static function () use ($admin, $workspaces, $connections, $datasets): Response {
        return $admin('admin/transfers/create', [
            'title' => 'Transfer anlegen',
            'active' => 'transfers',
            'workspaces' => safeList($workspaces),
            'connections' => safeList($connections),
            'datasets' => $datasets()->all(),
            'values' => ['status' => 'draft', 'operation_type' => 'upsert'],
            'errors' => [],
        ]);
    }, 'admin.transfers.create', 'web');

    $routes->post('/admin/transfers', static function (Request $request) use ($admin, $workspaces, $connections, $datasets, $datasetTransfers, $datasetTransferRunner): Response {
        $values = datasetTransferValues($request);
        $errors = datasetTransferErrors($values, [], $datasetTransferRunner());

        if ($errors !== []) {
            return $admin('admin/transfers/create', [
                'title' => 'Transfer anlegen',
                'active' => 'transfers',
                'workspaces' => safeList($workspaces),
                'connections' => safeList($connections),
                'datasets' => $datasets()->all(),
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        $id = $datasetTransfers()->create($values);

        return new Response('', 302, ['Location' => '/admin/transfers/' . $id]);
    }, 'admin.transfers.store', 'web');

    $routes->get('/admin/transfers/{id}', static function (Request $request) use ($admin, $workspaces, $connections, $datasets, $datasetTransfers): Response {
        $id = (int) $request->route('id');
        $transfer = $datasetTransfers()->find($id);

        if ($transfer === null) {
            return Response::notFound();
        }

        return $admin('admin/transfers/show', [
            'title' => 'Transfer',
            'active' => 'transfers',
            'transfer' => $transfer,
            'fields' => $datasetTransfers()->fieldsForTransfer($id),
            'groups' => datasetTransferGroupsWithFields($datasetTransfers(), $id),
            'workspaces' => safeList($workspaces),
            'connections' => safeList($connections),
            'datasets' => $datasets()->all(),
            'datasetFields' => $datasets()->fields((string) $transfer['source_dataset']),
            'result' => null,
            'errors' => [],
        ]);
    }, 'admin.transfers.show', 'web');

    $routes->post('/admin/transfers/{id}', static function (Request $request) use ($admin, $workspaces, $connections, $datasets, $datasetTransfers, $datasetTransferRunner): Response {
        $id = (int) $request->route('id');
        $transfer = $datasetTransfers()->find($id);
        if ($transfer === null) {
            return Response::notFound();
        }

        $values = datasetTransferValues($request);
        $fields = $datasetTransfers()->fieldsForTransfer($id);
        $errors = datasetTransferErrors($values, $fields, $datasetTransferRunner());

        if ($errors !== []) {
            return $admin('admin/transfers/show', [
                'title' => 'Transfer',
                'active' => 'transfers',
                'transfer' => $values + ['id' => $id],
                'fields' => $fields,
                'groups' => datasetTransferGroupsWithFields($datasetTransfers(), $id),
                'workspaces' => safeList($workspaces),
                'connections' => safeList($connections),
                'datasets' => $datasets()->all(),
                'datasetFields' => $datasets()->fields((string) $values['source_dataset']),
                'result' => null,
                'errors' => $errors,
            ]);
        }

        $datasetTransfers()->update($id, $values);

        return new Response('', 302, ['Location' => '/admin/transfers/' . $id]);
    }, 'admin.transfers.update', 'web');

    $routes->post('/admin/transfers/{id}/fields', static function (Request $request) use ($datasetTransfers): Response {
        $id = (int) $request->route('id');
        if ($datasetTransfers()->find($id) === null) {
            return Response::notFound();
        }

        $datasetTransfers()->addField($id, datasetTransferFieldValues($request));

        return new Response('', 302, ['Location' => '/admin/transfers/' . $id]);
    }, 'admin.transfers.fields.store', 'web');

    $routes->post('/admin/transfers/{id}/fields/{fieldId}', static function (Request $request) use ($datasetTransfers): Response {
        $id = (int) $request->route('id');
        if ($datasetTransfers()->find($id) === null) {
            return Response::notFound();
        }

        $datasetTransfers()->updateField((int) $request->route('fieldId'), datasetTransferFieldValues($request));

        return new Response('', 302, ['Location' => '/admin/transfers/' . $id]);
    }, 'admin.transfers.fields.update', 'web');

    $routes->post('/admin/transfers/{id}/fields/{fieldId}/delete', static function (Request $request) use ($datasetTransfers): Response {
        $id = (int) $request->route('id');
        if ($datasetTransfers()->find($id) === null) {
            return Response::notFound();
        }

        $datasetTransfers()->deleteField((int) $request->route('fieldId'));

        return new Response('', 302, ['Location' => '/admin/transfers/' . $id]);
    }, 'admin.transfers.fields.delete', 'web');

    $routes->post('/admin/transfers/{id}/groups', static function (Request $request) use ($datasetTransfers): Response {
        $id = (int) $request->route('id');
        if ($datasetTransfers()->find($id) === null) {
            return Response::notFound();
        }

        $datasetTransfers()->addGroup($id, datasetTransferGroupValues($request));

        return new Response('', 302, ['Location' => '/admin/transfers/' . $id]);
    }, 'admin.transfers.groups.store', 'web');

    $routes->post('/admin/transfers/{id}/groups/{groupId}', static function (Request $request) use ($datasetTransfers): Response {
        $id = (int) $request->route('id');
        if ($datasetTransfers()->find($id) === null) {
            return Response::notFound();
        }

        $datasetTransfers()->updateGroup((int) $request->route('groupId'), datasetTransferGroupValues($request));

        return new Response('', 302, ['Location' => '/admin/transfers/' . $id]);
    }, 'admin.transfers.groups.update', 'web');

    $routes->post('/admin/transfers/{id}/groups/{groupId}/delete', static function (Request $request) use ($datasetTransfers): Response {
        $id = (int) $request->route('id');
        if ($datasetTransfers()->find($id) === null) {
            return Response::notFound();
        }

        $datasetTransfers()->deleteGroup((int) $request->route('groupId'));

        return new Response('', 302, ['Location' => '/admin/transfers/' . $id]);
    }, 'admin.transfers.groups.delete', 'web');

    $routes->post('/admin/transfers/{id}/groups/{groupId}/fields', static function (Request $request) use ($datasetTransfers): Response {
        $id = (int) $request->route('id');
        if ($datasetTransfers()->find($id) === null) {
            return Response::notFound();
        }

        $values = datasetTransferFieldValues($request);
        $values['group_id'] = (int) $request->route('groupId');
        $datasetTransfers()->addField($id, $values);

        return new Response('', 302, ['Location' => '/admin/transfers/' . $id]);
    }, 'admin.transfers.groups.fields.store', 'web');

    $routes->post('/admin/transfers/{id}/dry-run', static function (Request $request) use ($admin, $workspaces, $connections, $datasets, $datasetTransfers, $datasetTransferRunner): Response {
        $id = (int) $request->route('id');
        $transfer = $datasetTransfers()->find($id);
        if ($transfer === null) {
            return Response::notFound();
        }

        $result = $datasetTransferRunner()->run($id, true, 25)->toArray();

        return $admin('admin/transfers/show', [
            'title' => 'Transfer',
            'active' => 'transfers',
            'transfer' => $transfer,
            'fields' => $datasetTransfers()->fieldsForTransfer($id),
            'groups' => datasetTransferGroupsWithFields($datasetTransfers(), $id),
            'workspaces' => safeList($workspaces),
            'connections' => safeList($connections),
            'datasets' => $datasets()->all(),
            'datasetFields' => $datasets()->fields((string) $transfer['source_dataset']),
            'result' => $result,
            'errors' => [],
        ]);
    }, 'admin.transfers.dry_run', 'web');

    $routes->post('/admin/transfers/{id}/run', static function (Request $request) use ($admin, $workspaces, $connections, $datasets, $datasetTransfers, $datasetTransferRunner): Response {
        $id = (int) $request->route('id');
        $transfer = $datasetTransfers()->find($id);
        if ($transfer === null) {
            return Response::notFound();
        }

        if ($request->post('confirm') !== 'run') {
            return Response::text('Transfer confirmation missing.', 400);
        }

        $result = $datasetTransferRunner()->run($id, false)->toArray();

        return $admin('admin/transfers/show', [
            'title' => 'Transfer',
            'active' => 'transfers',
            'transfer' => $transfer,
            'fields' => $datasetTransfers()->fieldsForTransfer($id),
            'groups' => datasetTransferGroupsWithFields($datasetTransfers(), $id),
            'workspaces' => safeList($workspaces),
            'connections' => safeList($connections),
            'datasets' => $datasets()->all(),
            'datasetFields' => $datasets()->fields((string) $transfer['source_dataset']),
            'result' => $result,
            'errors' => [],
        ]);
    }, 'admin.transfers.run', 'web');

    $routes->get('/admin/woocommerce', static fn (): Response => $admin('admin/woocommerce/index', [
        'title' => 'WooCommerce - Anbindung',
        'active' => 'woocommerce',
        'items' => safeList(static fn (): WooCommerceIntegrationRepository => $woocommerce()),
    ]), 'admin.woocommerce', 'web');

    $routes->get('/admin/woocommerce/create', static fn (): Response => $admin('admin/woocommerce/create', [
        'title' => 'WooCommerce-Anbindung anlegen',
        'active' => 'woocommerce',
        'workspaces' => safeList($workspaces),
        'connections' => safeList($connections),
        'values' => ['name' => '', 'workspace_id' => '', 'connection_id' => ''],
        'errors' => [],
    ]), 'admin.woocommerce.create', 'web');

    $routes->post('/admin/woocommerce', static function (Request $request) use ($admin, $woocommerce, $workspaces, $connections): Response {
        $values = woocommerceConnectionValues($request);
        $errors = woocommerceConnectionErrors($values);

        if ($errors !== []) {
            return $admin('admin/woocommerce/create', [
                'title' => 'WooCommerce-Anbindung anlegen',
                'active' => 'woocommerce',
                'workspaces' => safeList($workspaces),
                'connections' => safeList($connections),
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        $id = $woocommerce()->createConnection($values);

        return new Response('', 302, ['Location' => '/admin/woocommerce/' . $id]);
    }, 'admin.woocommerce.store', 'web');

    $routes->get('/admin/woocommerce/{id}', static function (Request $request) use ($admin, $app, $woocommerce, $exportProfiles): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        if ($connection === null) {
            return Response::notFound();
        }

        $deliveryUrlInfo = woocommerceDeliveryUrlInfo($app, $request, $connection);

        return $admin('admin/woocommerce/show', [
            'title' => 'WooCommerce - Anbindung',
            'active' => 'woocommerce',
            'connection' => $connection,
            'webhooks' => $woocommerce()->webhooksForConnection($id),
            'queue' => $woocommerce()->transferQueueForConnection($id),
            'runs' => $woocommerce()->recentRunsForConnection($id),
            'lastSuccessfulRun' => $exportProfiles()->latestSuccessfulRunForWooCommerceConnection($id),
            'exportProfiles' => woocommerceExportProfilesForView($app, $request, $exportProfiles()->wooCommerceProfilesForConnection($id)),
            'exportRuns' => $exportProfiles()->recentRunsForWooCommerceConnection($id),
            'webhookEvents' => $woocommerce()->recentWebhookEventsForConnection($id),
            'expectedWebhooks' => woocommerceExpectedWebhooks((string) $deliveryUrlInfo['delivery_url']),
            'deliveryUrlInfo' => $deliveryUrlInfo,
            'topicLabels' => woocommerceWebhookTopicLabels(),
            'defaultNames' => woocommerceWebhookDefaultNames(),
            'validation' => null,
            'alert' => null,
        ]);
    }, 'admin.woocommerce.show', 'web');

    $routes->post('/admin/woocommerce/{id}/validate', static function (Request $request) use ($admin, $app, $woocommerce, $woocommerceValidator, $connections, $pdoFactory, $configFor, $exportProfiles): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        if ($connection === null) {
            return Response::notFound();
        }

        $validation = null;
        $alert = null;

        try {
            $profile = $connections()->find((int) $connection['connection_id']);
            if ($profile === null) {
                throw new RuntimeException('Connection wurde nicht gefunden.');
            }

            $pdo = $pdoFactory()->create($configFor($profile));
            $validationResult = $woocommerceValidator()->validate($pdo);
            $validation = $validationResult->toArray();
            $woocommerce()->updateConnectionValidation($id, $validation);
            $connection = $woocommerce()->findConnection($id) ?? $connection;
            $alert = [
                'type' => $validationResult->transferReady ? 'success' : 'warning',
                'message' => $validationResult->transferReady ? 'WooCommerce-Validierung erfolgreich.' : 'WooCommerce-Validierung hat Blocker oder Warnungen gefunden.',
            ];
        } catch (Throwable) {
            $alert = ['type' => 'danger', 'message' => 'WooCommerce-Validierung konnte nicht ausgeführt werden.'];
        }

        $deliveryUrlInfo = woocommerceDeliveryUrlInfo($app, $request, $connection);

        return $admin('admin/woocommerce/show', [
            'title' => 'WooCommerce - Anbindung',
            'active' => 'woocommerce',
            'connection' => $connection,
            'webhooks' => $woocommerce()->webhooksForConnection($id),
            'queue' => $woocommerce()->transferQueueForConnection($id),
            'runs' => $woocommerce()->recentRunsForConnection($id),
            'lastSuccessfulRun' => $exportProfiles()->latestSuccessfulRunForWooCommerceConnection($id),
            'exportProfiles' => woocommerceExportProfilesForView($app, $request, $exportProfiles()->wooCommerceProfilesForConnection($id)),
            'exportRuns' => $exportProfiles()->recentRunsForWooCommerceConnection($id),
            'webhookEvents' => $woocommerce()->recentWebhookEventsForConnection($id),
            'expectedWebhooks' => woocommerceExpectedWebhooks((string) $deliveryUrlInfo['delivery_url']),
            'deliveryUrlInfo' => $deliveryUrlInfo,
            'topicLabels' => woocommerceWebhookTopicLabels(),
            'defaultNames' => woocommerceWebhookDefaultNames(),
            'validation' => $validation,
            'alert' => $alert,
        ]);
    }, 'admin.woocommerce.validate', 'web');

    $routes->post('/admin/woocommerce/{id}/webhooks', static function (Request $request) use ($woocommerce): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        if ($connection === null) {
            return Response::notFound();
        }

        $woocommerce()->createWebhookConfig(
            woocommerceWebhookValues($request, $connection),
            trim((string) $request->post('secret', '')),
        );

        return new Response('', 302, ['Location' => '/admin/woocommerce/' . $id]);
    }, 'admin.woocommerce.webhooks.store', 'web');

    $routes->post('/admin/woocommerce/{id}/webhooks/{webhookId}', static function (Request $request) use ($woocommerce): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        if ($connection === null) {
            return Response::notFound();
        }

        $woocommerce()->updateWebhookConfig(
            (int) $request->route('webhookId'),
            woocommerceWebhookValues($request, $connection),
            trim((string) $request->post('secret', '')),
        );

        return new Response('', 302, ['Location' => '/admin/woocommerce/' . $id]);
    }, 'admin.woocommerce.webhooks.update', 'web');

    $routes->post('/admin/woocommerce/{id}/initial-transfer', static function (Request $request) use ($admin, $app, $woocommerce, $exportProfiles): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        if ($connection === null) {
            return Response::notFound();
        }

        $ready = ! empty($connection['hpos_enabled'])
            && ! empty($connection['hpos_authoritative'])
            && version_compare((string) ($connection['detected_woocommerce_version'] ?? '0'), '10.7.0', '>=');

        $alert = ['type' => 'danger', 'message' => 'Initialer WooCommerce-Transfer wurde blockiert, weil die HPOS-Validierung nicht erfolgreich ist.'];
        if ($ready) {
            $queueId = $woocommerce()->queueTransfer([
                'workspace_id' => $connection['workspace_id'] ?? null,
                'woocommerce_connection_id' => (int) $connection['id'],
                'source_order_id' => '*',
                'topic' => 'initial_import',
                'reason' => 'initial WooCommerce HPOS import',
                'status' => 'pending',
            ]);
            $alert = ['type' => 'success', 'message' => 'Initialer WooCommerce-Transfer wurde vorgemerkt. Queue-ID: ' . $queueId . '. Status: pending.'];
        }

        $deliveryUrlInfo = woocommerceDeliveryUrlInfo($app, $request, $connection);

        return $admin('admin/woocommerce/show', [
            'title' => 'WooCommerce - Anbindung',
            'active' => 'woocommerce',
            'connection' => $connection,
            'webhooks' => $woocommerce()->webhooksForConnection($id),
            'queue' => $woocommerce()->transferQueueForConnection($id),
            'runs' => $woocommerce()->recentRunsForConnection($id),
            'lastSuccessfulRun' => $exportProfiles()->latestSuccessfulRunForWooCommerceConnection($id),
            'exportProfiles' => woocommerceExportProfilesForView($app, $request, $exportProfiles()->wooCommerceProfilesForConnection($id)),
            'exportRuns' => $exportProfiles()->recentRunsForWooCommerceConnection($id),
            'webhookEvents' => $woocommerce()->recentWebhookEventsForConnection($id),
            'expectedWebhooks' => woocommerceExpectedWebhooks((string) $deliveryUrlInfo['delivery_url']),
            'deliveryUrlInfo' => $deliveryUrlInfo,
            'topicLabels' => woocommerceWebhookTopicLabels(),
            'defaultNames' => woocommerceWebhookDefaultNames(),
            'validation' => null,
            'alert' => $alert,
        ]);
    }, 'admin.woocommerce.initial_transfer', 'web');

    $routes->post('/admin/woocommerce/{id}/queue/run', static function (Request $request) use ($admin, $app, $woocommerce, $woocommerceTransferRunner, $exportProfiles): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        if ($connection === null) {
            return Response::notFound();
        }

        $result = $woocommerceTransferRunner()->run($id, null, 10, false);
        $deliveryUrlInfo = woocommerceDeliveryUrlInfo($app, $request, $connection);

        return $admin('admin/woocommerce/show', [
            'title' => 'WooCommerce - Anbindung',
            'active' => 'woocommerce',
            'connection' => $woocommerce()->findConnection($id) ?? $connection,
            'webhooks' => $woocommerce()->webhooksForConnection($id),
            'queue' => $woocommerce()->transferQueueForConnection($id),
            'runs' => $woocommerce()->recentRunsForConnection($id),
            'lastSuccessfulRun' => $exportProfiles()->latestSuccessfulRunForWooCommerceConnection($id),
            'exportProfiles' => woocommerceExportProfilesForView($app, $request, $exportProfiles()->wooCommerceProfilesForConnection($id)),
            'exportRuns' => $exportProfiles()->recentRunsForWooCommerceConnection($id),
            'webhookEvents' => $woocommerce()->recentWebhookEventsForConnection($id),
            'expectedWebhooks' => woocommerceExpectedWebhooks((string) $deliveryUrlInfo['delivery_url']),
            'deliveryUrlInfo' => $deliveryUrlInfo,
            'topicLabels' => woocommerceWebhookTopicLabels(),
            'defaultNames' => woocommerceWebhookDefaultNames(),
            'validation' => null,
            'alert' => ['type' => (int) $result['failed'] > 0 ? 'warning' : 'success', 'message' => 'WooCommerce-Transfers ausgeführt. Verarbeitet: ' . (int) $result['processed'] . ', erfolgreich: ' . (int) $result['success'] . ', fehlgeschlagen: ' . (int) $result['failed'] . '.'],
        ]);
    }, 'admin.woocommerce.queue.run', 'web');

    $routes->post('/admin/woocommerce/{id}/queue/{queueId}/run', static function (Request $request) use ($admin, $app, $woocommerce, $woocommerceTransferRunner, $exportProfiles): Response {
        $id = (int) $request->route('id');
        $queueId = (int) $request->route('queueId');
        $connection = $woocommerce()->findConnection($id);
        if ($connection === null) {
            return Response::notFound();
        }

        $retry = $request->post('retry') !== null;
        $result = $woocommerceTransferRunner()->run($id, $queueId, 1, $retry);
        $deliveryUrlInfo = woocommerceDeliveryUrlInfo($app, $request, $connection);

        return $admin('admin/woocommerce/show', [
            'title' => 'WooCommerce - Anbindung',
            'active' => 'woocommerce',
            'connection' => $woocommerce()->findConnection($id) ?? $connection,
            'webhooks' => $woocommerce()->webhooksForConnection($id),
            'queue' => $woocommerce()->transferQueueForConnection($id),
            'runs' => $woocommerce()->recentRunsForConnection($id),
            'lastSuccessfulRun' => $exportProfiles()->latestSuccessfulRunForWooCommerceConnection($id),
            'exportProfiles' => woocommerceExportProfilesForView($app, $request, $exportProfiles()->wooCommerceProfilesForConnection($id)),
            'exportRuns' => $exportProfiles()->recentRunsForWooCommerceConnection($id),
            'webhookEvents' => $woocommerce()->recentWebhookEventsForConnection($id),
            'expectedWebhooks' => woocommerceExpectedWebhooks((string) $deliveryUrlInfo['delivery_url']),
            'deliveryUrlInfo' => $deliveryUrlInfo,
            'topicLabels' => woocommerceWebhookTopicLabels(),
            'defaultNames' => woocommerceWebhookDefaultNames(),
            'validation' => null,
            'alert' => ['type' => (int) $result['failed'] > 0 ? 'warning' : 'success', 'message' => 'Queue-Eintrag ausgeführt. Verarbeitet: ' . (int) $result['processed'] . ', erfolgreich: ' . (int) $result['success'] . ', fehlgeschlagen: ' . (int) $result['failed'] . '.'],
        ]);
    }, 'admin.woocommerce.queue.run_one', 'web');

    $routes->post('/admin/woocommerce/{id}/exports/defaults', static function (Request $request) use ($woocommerce, $exportProfiles, $woocommerceShowResponse): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        if ($connection === null) {
            return Response::notFound();
        }

        $ids = $exportProfiles()->createDefaultWooCommerceProfiles($connection);

        return $woocommerceShowResponse($request, $woocommerce()->findConnection($id) ?? $connection, [
            'type' => 'success',
            'message' => 'WooCommerce-Exportprofile wurden angelegt oder bestätigt. Profile: ' . count($ids) . '.',
        ]);
    }, 'admin.woocommerce.exports.defaults', 'web');

    $routes->post('/admin/woocommerce/{id}/exports/{profileId}/token', static function (Request $request) use ($woocommerce, $exportProfiles, $woocommerceShowResponse): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        $profile = $exportProfiles()->find((int) $request->route('profileId'));
        if ($connection === null || $profile === null || (int) ($profile['connection_id'] ?? 0) !== $id) {
            return Response::notFound();
        }

        $token = $exportProfiles()->generateToken();
        $exportProfiles()->setToken((int) $profile['id'], $token);

        return $woocommerceShowResponse($request, $connection, [
            'type' => 'warning',
            'message' => 'Neuer Export-Token erzeugt. Nur jetzt kopieren: ' . $token,
        ]);
    }, 'admin.woocommerce.exports.token', 'web');

    $routes->post('/admin/woocommerce/{id}/exports/{profileId}/secret', static function (Request $request) use ($woocommerce, $exportProfiles, $woocommerceShowResponse): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        $profile = $exportProfiles()->find((int) $request->route('profileId'));
        if ($connection === null || $profile === null || (int) ($profile['connection_id'] ?? 0) !== $id) {
            return Response::notFound();
        }

        $secret = $exportProfiles()->generateSecret();
        $exportProfiles()->setSecret((int) $profile['id'], $secret);

        return $woocommerceShowResponse($request, $connection, [
            'type' => 'warning',
            'message' => 'Neues Export-HMAC-Secret erzeugt. Nur jetzt kopieren: ' . $secret,
        ]);
    }, 'admin.woocommerce.exports.secret', 'web');

    $routes->post('/admin/woocommerce/{id}/exports/{profileId}/toggle', static function (Request $request) use ($woocommerce, $exportProfiles): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        $profile = $exportProfiles()->find((int) $request->route('profileId'));
        if ($connection === null || $profile === null || (int) ($profile['connection_id'] ?? 0) !== $id) {
            return Response::notFound();
        }

        $exportProfiles()->toggleEnabled((int) $profile['id']);

        return new Response('', 302, ['Location' => '/admin/woocommerce/' . $id]);
    }, 'admin.woocommerce.exports.toggle', 'web');

    $routes->post('/admin/woocommerce/{id}/exports/{profileId}/test', static function (Request $request) use ($woocommerce, $exportProfiles, $woocommerceExport, $woocommerceShowResponse): Response {
        $id = (int) $request->route('id');
        $connection = $woocommerce()->findConnection($id);
        $profile = $exportProfiles()->find((int) $request->route('profileId'));
        if ($connection === null || $profile === null || (int) ($profile['connection_id'] ?? 0) !== $id) {
            return Response::notFound();
        }

        $result = $woocommerceExport()->export($profile, ['limit' => 5], 'ui');
        $success = ! empty($result['success']);

        return $woocommerceShowResponse($request, $connection, [
            'type' => $success ? 'success' : 'danger',
            'message' => $success
                ? 'Test-Export erfolgreich. Datensätze: ' . (int) ($result['count'] ?? 0) . '.'
                : 'Test-Export fehlgeschlagen: ' . (string) ($result['error']['message'] ?? 'unbekannter Fehler'),
        ]);
    }, 'admin.woocommerce.exports.test', 'web');

    $routes->get('/admin/datasets', static function () use ($admin, $datasets): Response {
        try {
            $items = $datasets()->all();
            $error = null;
        } catch (Throwable) {
            $items = [];
            $error = 'Datasets konnten nicht geladen werden.';
        }

        return $admin('admin/datasets/index', [
            'title' => 'Datasets',
            'active' => 'datasets',
            'datasets' => $items,
            'error' => $error,
        ]);
    }, 'admin.datasets', 'web');

    $routes->get('/admin/datasets/{name}', static function (Request $request) use ($admin, $datasets): Response {
        $name = (string) $request->route('name');
        $dataset = $datasets()->find($name);

        if ($dataset === null) {
            return Response::notFound();
        }

        try {
            $preview = $datasets()->preview($name, 10);
            $error = null;
        } catch (Throwable) {
            $preview = ['rows' => [], 'summary' => ['error_count' => 1, 'errors' => ['Dataset Preview konnte nicht erzeugt werden.']]];
            $error = 'Dataset Preview konnte nicht erzeugt werden.';
        }

        return $admin('admin/datasets/show', [
            'title' => 'Dataset',
            'active' => 'datasets',
            'dataset' => $dataset,
            'fields' => $datasets()->fields($name),
            'sourceFilters' => $datasets()->sourceFilters($name),
            'preview' => $preview,
            'error' => $error,
        ]);
    }, 'admin.datasets.show', 'web');

    $routes->get('/admin/reports', static fn (): Response => $admin('admin/reports/index', [
        'title' => 'Reports',
        'active' => 'reports',
        'reports' => safeList($reports),
    ]), 'admin.reports', 'web');

    $routes->get('/admin/reports/{id}', static function (Request $request) use ($admin, $reports): Response {
        return $admin('admin/reports/show', ['title' => 'Report', 'active' => 'reports', 'report' => $reports()->find((int) $request->route('id')), 'result' => null]);
    }, 'admin.reports.show', 'web');

    $routes->post('/admin/reports/{id}/send', static function (Request $request) use ($admin, $reports, $reportMailer): Response {
        $id = (int) $request->route('id');
        $result = $reportMailer()->send($id);
        return $admin('admin/reports/show', ['title' => 'Report', 'active' => 'reports', 'report' => $reports()->find($id), 'result' => $result]);
    }, 'admin.reports.send', 'web');

    $routes->get('/admin/deployment-targets', static fn (): Response => $admin('admin/deployment-targets/index', [
        'title' => 'Deployment Targets',
        'active' => 'deployment-targets',
        'targets' => safeList($deploymentTargets),
        'alert' => null,
    ]), 'admin.deployment_targets', 'web');

    $routes->get('/admin/deployment-targets/create', static fn (): Response => $admin('admin/deployment-targets/create', [
        'title' => 'Deployment Target anlegen',
        'active' => 'deployment-targets',
        'workspaces' => safeList($workspaces),
        'values' => ['environment' => 'production', 'origin' => 'customer_created', 'support_status' => 'unverified', 'is_active' => '1'],
        'errors' => [],
    ]), 'admin.deployment_targets.create', 'web');

    $routes->post('/admin/deployment-targets', static function (Request $request) use ($admin, $deploymentTargets, $targetUrlBuilder, $workspaces): Response {
        $values = deploymentTargetValues($request);
        $errors = deploymentTargetErrors($values, $targetUrlBuilder());
        if ($errors !== []) {
            return $admin('admin/deployment-targets/create', [
                'title' => 'Deployment Target anlegen',
                'active' => 'deployment-targets',
                'workspaces' => safeList($workspaces),
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        try {
            $id = $deploymentTargets()->create($values);
        } catch (Throwable $exception) {
            return $admin('admin/deployment-targets/create', [
                'title' => 'Deployment Target anlegen',
                'active' => 'deployment-targets',
                'workspaces' => safeList($workspaces),
                'values' => $values,
                'errors' => [$exception->getMessage()],
            ]);
        }

        return new Response('', 302, ['Location' => '/admin/deployment-targets/' . $id . '/edit']);
    }, 'admin.deployment_targets.store', 'web');

    $routes->get('/admin/deployment-targets/{id}/edit', static function (Request $request) use ($admin, $deploymentTargets, $workspaces): Response {
        $target = $deploymentTargets()->find((int) $request->route('id'));
        if ($target === null) {
            return Response::notFound();
        }

        return $admin('admin/deployment-targets/edit', [
            'title' => 'Deployment Target bearbeiten',
            'active' => 'deployment-targets',
            'workspaces' => safeList($workspaces),
            'target' => $target,
            'values' => $target,
            'errors' => [],
        ]);
    }, 'admin.deployment_targets.edit', 'web');

    $routes->post('/admin/deployment-targets/{id}', static function (Request $request) use ($admin, $deploymentTargets, $targetUrlBuilder, $workspaces): Response {
        $id = (int) $request->route('id');
        $target = $deploymentTargets()->find($id);
        if ($target === null) {
            return Response::notFound();
        }

        $values = deploymentTargetValues($request);
        $errors = deploymentTargetErrors($values, $targetUrlBuilder());
        if ($errors !== []) {
            return $admin('admin/deployment-targets/edit', [
                'title' => 'Deployment Target bearbeiten',
                'active' => 'deployment-targets',
                'workspaces' => safeList($workspaces),
                'target' => $target,
                'values' => $values + ['id' => $id],
                'errors' => $errors,
            ]);
        }

        try {
            $deploymentTargets()->update($id, $values);
        } catch (Throwable $exception) {
            return $admin('admin/deployment-targets/edit', [
                'title' => 'Deployment Target bearbeiten',
                'active' => 'deployment-targets',
                'workspaces' => safeList($workspaces),
                'target' => $target,
                'values' => $values + ['id' => $id],
                'errors' => [$exception->getMessage()],
            ]);
        }

        return new Response('', 302, ['Location' => '/admin/deployment-targets']);
    }, 'admin.deployment_targets.update', 'web');

    $routes->post('/admin/deployment-targets/{id}/default', static function (Request $request) use ($deploymentTargets): Response {
        $deploymentTargets()->setDefault((int) $request->route('id'));

        return new Response('', 302, ['Location' => '/admin/deployment-targets']);
    }, 'admin.deployment_targets.default', 'web');

    $routes->post('/admin/deployment-targets/{id}/toggle', static function (Request $request) use ($deploymentTargets): Response {
        $id = (int) $request->route('id');
        $target = $deploymentTargets()->find($id);
        if ($target !== null) {
            $deploymentTargets()->setActive($id, empty($target['is_active']));
        }

        return new Response('', 302, ['Location' => '/admin/deployment-targets']);
    }, 'admin.deployment_targets.toggle', 'web');

    $routes->post('/admin/deployment-targets/{id}/delete', static function (Request $request) use ($deploymentTargets): Response {
        $deploymentTargets()->delete((int) $request->route('id'));

        return new Response('', 302, ['Location' => '/admin/deployment-targets']);
    }, 'admin.deployment_targets.delete', 'web');

    $routes->get('/admin/endpoints', static fn (): Response => $admin('admin/endpoints/index', [
        'title' => 'Endpoints',
        'active' => 'endpoints',
        'endpoints' => safeList($endpoints),
    ]), 'admin.endpoints', 'web');

    $routes->get('/admin/endpoints/create', static fn (): Response => $admin('admin/endpoints/create', [
        'title' => 'Endpoint anlegen',
        'active' => 'endpoints',
        'workspaces' => safeList($workspaces),
        'mappings' => safeList($mappings),
        'jobs' => safeList($jobs),
        'schemas' => $schemas()->all(),
            'values' => ['method' => 'GET', 'visibility' => 'public', 'status' => 'inactive', 'secret_mode' => 'none', 'source_type' => 'mapping', 'response_type' => 'json'],
        'errors' => [],
    ]), 'admin.endpoints.create', 'web');

    $routes->post('/admin/endpoints', static function (Request $request) use ($admin, $endpoints, $audit, $workspaces, $mappings, $jobs, $schemas): Response {
        $values = endpointValues($request);
        $secret = (string) $request->post('secret', '');
        $errors = endpointErrors($values, $secret === '', (string) $values['static_response'], $mappings(), $schemas());

        if ($errors !== []) {
            return $admin('admin/endpoints/create', [
                'title' => 'Endpoint anlegen',
                'active' => 'endpoints',
                'workspaces' => safeList($workspaces),
                'mappings' => safeList($mappings),
                'jobs' => safeList($jobs),
                'schemas' => $schemas()->all(),
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        $id = $endpoints()->create($values, ['secret' => $secret]);
        $audit()->log(empty($values['workspace_id']) ? null : (int) $values['workspace_id'], 'endpoint.created', 'endpoint', (string) $id, 'Endpoint erstellt.', [
            'endpoint_key' => $values['endpoint_key'],
            'visibility' => $values['visibility'],
        ]);

        return new Response('', 302, ['Location' => '/admin/endpoints/' . $id]);
    }, 'admin.endpoints.store', 'web');

    $routes->get('/admin/endpoints/{id}', static function (Request $request) use ($admin, $endpoints, $endpointExporter, $deploymentTargets, $targetUrlBuilder, $workspaces, $mappings, $connections, $jobs, $schemas): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);
        $config = $endpoint === null ? [] : (json_decode((string) ($endpoint['config_json'] ?? '{}'), true) ?: []);
        $currentUrl = $endpoint === null ? null : currentEndpointUrl($request, $targetUrlBuilder(), (string) $endpoint['endpoint_key']);

        return $admin('admin/endpoints/show', [
            'title' => 'Endpoint',
            'active' => 'endpoints',
            'endpoint' => $endpoint,
            'workspaces' => safeList($workspaces),
            'mappings' => safeList($mappings),
            'jobs' => safeList($jobs),
            'schemas' => $schemas()->all(),
            'staticResponse' => isset($config['static_response']) ? (json_encode($config['static_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') : '',
            'hasSecret' => $endpoint !== null && $endpoints()->hasSecret((int) $endpoint['id']),
            'exportStatus' => $endpoint !== null ? $endpointExporter()->exportStatusForEndpoint((int) $endpoint['id']) : null,
            'contractTargets' => $endpoint === null ? [] : endpointTargetRows($endpoint, $deploymentTargets(), $targetUrlBuilder()),
            'currentEndpointUrl' => $currentUrl,
            'contractExportStatus' => null,
            'mappingSummary' => endpointMappingSummary($endpoint, $mappings, $connections),
            'alert' => null,
        ]);
    }, 'admin.endpoints.show', 'web');

    $routes->post('/admin/endpoints/{id}', static function (Request $request) use ($admin, $endpoints, $audit, $workspaces, $mappings, $connections, $jobs, $schemas): Response {
        $id = (int) $request->route('id');
        $existing = $endpoints()->find($id);
        if ($existing === null) {
            return Response::notFound();
        }

        $values = endpointValues($request);
        $secret = (string) $request->post('secret', '');
        $errors = endpointErrors($values, $secret === '' && ! $endpoints()->hasSecret($id), (string) $values['static_response'], $mappings(), $schemas());
        if ($errors !== []) {
            return $admin('admin/endpoints/show', [
                'title' => 'Endpoint',
                'active' => 'endpoints',
                'endpoint' => $values + ['id' => $id],
                'workspaces' => safeList($workspaces),
                'mappings' => safeList($mappings),
                'jobs' => safeList($jobs),
                'schemas' => $schemas()->all(),
                'staticResponse' => (string) $values['static_response'],
                'hasSecret' => $endpoints()->hasSecret($id),
                'exportStatus' => null,
                'mappingSummary' => endpointMappingSummary($values + ['id' => $id], $mappings, $connections),
                'alert' => ['type' => 'danger', 'message' => implode(' ', $errors)],
            ]);
        }

        $endpoints()->update($id, $values, ['secret' => $secret]);
        $audit()->log(empty($values['workspace_id']) ? null : (int) $values['workspace_id'], 'endpoint.updated', 'endpoint', (string) $id, 'Endpoint aktualisiert.', [
            'endpoint_key' => $values['endpoint_key'],
            'visibility' => $values['visibility'],
        ]);

        return new Response('', 302, ['Location' => '/admin/endpoints/' . $id]);
    }, 'admin.endpoints.update', 'web');

    $routes->post('/admin/endpoints/{id}/export', static function (Request $request) use ($admin, $endpoints, $endpointExporter, $endpointArchive, $audit, $workspaces, $mappings, $connections, $jobs, $schemas): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);

        if ($endpoint === null) {
            return Response::notFound();
        }

        try {
            $manifest = $endpointExporter()->exportEndpointToWorkspaceStorage($id, true, ! empty($request->post('local_env')));
            $archiveMessage = '';
            try {
                $endpointArchive()->createArchive((string) $manifest['absolute_target_path'], (string) $manifest['absolute_archive_path'], true);
            } catch (Throwable) {
                $archiveMessage = ' Endpoint wurde exportiert, aber das ZIP-Archiv konnte nicht erzeugt werden, weil die PHP-ZIP-Erweiterung fehlt oder das Archiv nicht geschrieben werden konnte.';
            }
            $audit()->log(isset($endpoint['workspace_id']) ? (int) $endpoint['workspace_id'] : null, 'endpoint.exported', 'endpoint', (string) $id, 'Endpoint Runtime exportiert.', [
                'endpoint_key' => $endpoint['endpoint_key'] ?? '',
                'target_path' => $manifest['target_path'] ?? '',
                'archive_path' => $manifest['archive_path'] ?? '',
                'local_env_written' => ! empty($manifest['local_env_written']),
            ]);

            $config = json_decode((string) ($endpoint['config_json'] ?? '{}'), true) ?: [];

            return $admin('admin/endpoints/show', [
                'title' => 'Endpoint',
                'active' => 'endpoints',
                'endpoint' => $endpoints()->find($id) ?? $endpoint,
                'workspaces' => safeList($workspaces),
                'mappings' => safeList($mappings),
                'jobs' => safeList($jobs),
                'schemas' => $schemas()->all(),
                'staticResponse' => isset($config['static_response']) ? (json_encode($config['static_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') : '',
                'hasSecret' => $endpoints()->hasSecret($id),
                'exportStatus' => $endpointExporter()->exportStatusForEndpoint($id),
                'mappingSummary' => endpointMappingSummary($endpoints()->find($id) ?? $endpoint, $mappings, $connections),
                'alert' => ['type' => $archiveMessage === '' ? 'success' : 'warning', 'message' => 'Endpoint Runtime wurde exportiert nach: ' . (string) ($manifest['target_path'] ?? '') . $archiveMessage],
            ]);
        } catch (Throwable) {
            $config = json_decode((string) ($endpoint['config_json'] ?? '{}'), true) ?: [];

            return $admin('admin/endpoints/show', [
                'title' => 'Endpoint',
                'active' => 'endpoints',
                'endpoint' => $endpoint,
                'workspaces' => safeList($workspaces),
                'mappings' => safeList($mappings),
                'jobs' => safeList($jobs),
                'schemas' => $schemas()->all(),
                'staticResponse' => isset($config['static_response']) ? (json_encode($config['static_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') : '',
                'hasSecret' => $endpoints()->hasSecret($id),
                'exportStatus' => $endpointExporter()->exportStatusForEndpoint($id),
                'mappingSummary' => endpointMappingSummary($endpoint, $mappings, $connections),
                'alert' => ['type' => 'danger', 'message' => 'Endpoint Runtime konnte nicht exportiert werden.'],
            ]);
        }
    }, 'admin.endpoints.export', 'web');

    $routes->post('/admin/endpoints/{id}/contract-export', static function (Request $request) use ($admin, $endpoints, $endpointContractExporter, $deploymentTargets, $targetUrlBuilder, $endpointExporter, $workspaces, $mappings, $connections, $jobs, $schemas): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);
        if ($endpoint === null) {
            return Response::notFound();
        }

        $environment = trim((string) $request->post('target_environment', '')) ?: null;
        try {
            $manifest = $endpointContractExporter()->exportEndpoint($id, $environment);
            $alert = ['type' => 'success', 'message' => 'Exportpaket wurde erzeugt: ' . (string) ($manifest['target_path'] ?? '')];
            $contractExportStatus = $manifest;
        } catch (Throwable $exception) {
            $alert = ['type' => 'danger', 'message' => $exception->getMessage()];
            $contractExportStatus = null;
        }

        $config = json_decode((string) ($endpoint['config_json'] ?? '{}'), true) ?: [];

        return $admin('admin/endpoints/show', [
            'title' => 'Endpoint',
            'active' => 'endpoints',
            'endpoint' => $endpoint,
            'workspaces' => safeList($workspaces),
            'mappings' => safeList($mappings),
            'jobs' => safeList($jobs),
            'schemas' => $schemas()->all(),
            'staticResponse' => isset($config['static_response']) ? (json_encode($config['static_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') : '',
            'hasSecret' => $endpoints()->hasSecret($id),
            'exportStatus' => $endpointExporter()->exportStatusForEndpoint($id),
            'contractTargets' => endpointTargetRows($endpoint, $deploymentTargets(), $targetUrlBuilder()),
            'currentEndpointUrl' => currentEndpointUrl($request, $targetUrlBuilder(), (string) $endpoint['endpoint_key']),
            'contractExportStatus' => $contractExportStatus,
            'mappingSummary' => endpointMappingSummary($endpoint, $mappings, $connections),
            'alert' => $alert,
        ]);
    }, 'admin.endpoints.contract_export', 'web');

    $routes->get('/admin/endpoints/{id}/export/download', static function (Request $request) use ($endpoints, $endpointExporter): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);

        if ($endpoint === null) {
            return Response::notFound();
        }

        try {
            $archivePath = $endpointExporter()->archivePathForEndpoint($endpoint);
        } catch (Throwable) {
            return Response::notFound();
        }

        if (! is_file($archivePath)) {
            return Response::notFound('Export archive not found.');
        }

        $body = file_get_contents($archivePath);
        if ($body === false) {
            return Response::notFound('Export archive not found.');
        }

        return new Response($body, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . basename($archivePath) . '"',
            'Content-Length' => (string) filesize($archivePath),
        ]);
    }, 'admin.endpoints.export.download', 'web');

    $routes->post('/admin/endpoints/{id}/delete', static function (Request $request) use ($admin, $endpoints, $audit, $workspaces, $mappings, $jobs, $schemas): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);

        if ($endpoint === null) {
            return Response::notFound();
        }

        if (! deleteConfirmed($request)) {
            return new Response('', 302, ['Location' => '/admin/endpoints/' . $id]);
        }

        try {
            $endpoints()->delete($id);
            $audit()->log(isset($endpoint['workspace_id']) ? (int) $endpoint['workspace_id'] : null, 'endpoint.deleted', 'endpoint', (string) $id, 'Endpoint gelöscht.', [
                'name' => $endpoint['name'] ?? '',
                'endpoint_key' => $endpoint['endpoint_key'] ?? '',
            ]);
        } catch (Throwable) {
            $config = json_decode((string) ($endpoint['config_json'] ?? '{}'), true) ?: [];

            return $admin('admin/endpoints/show', [
                'title' => 'Endpoint',
                'active' => 'endpoints',
                'endpoint' => $endpoint,
                'workspaces' => safeList($workspaces),
                'mappings' => safeList($mappings),
                'jobs' => safeList($jobs),
                'schemas' => $schemas()->all(),
                'staticResponse' => isset($config['static_response']) ? (json_encode($config['static_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') : '',
                'hasSecret' => false,
                'alert' => ['type' => 'danger', 'message' => 'Endpoint konnte nicht gelöscht werden.'],
            ]);
        }

        return new Response('', 302, ['Location' => '/admin/endpoints']);
    }, 'admin.endpoints.delete', 'web');

    $routes->post('/admin/endpoints/{id}/test', static function (Request $request) use ($admin, $endpoints, $app): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);
        if ($endpoint === null) {
            return Response::notFound();
        }

        $result = json_decode($app->services()->get('api.endpoint_response_builder')->build($endpoint)->body(), true) ?: [];

        return $admin('admin/endpoints/test', [
            'title' => 'Endpoint testen',
            'active' => 'endpoints',
            'endpoint' => $endpoint,
            'result' => $result,
        ]);
    }, 'admin.endpoints.test', 'web');

    $routes->post('/admin/endpoints/{id}/validate-schema', static function (Request $request) use ($admin, $endpoints, $app, $schemas, $schemaValidator, $endpointExporter, $deploymentTargets, $targetUrlBuilder, $workspaces, $mappings, $connections, $jobs): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);
        if ($endpoint === null) {
            return Response::notFound();
        }
        if (empty($endpoint['schema_id'])) {
            return new Response('', 302, ['Location' => '/admin/endpoints/' . $id]);
        }

        $schema = $schemas()->find((int) $endpoint['schema_id']);
        $config = json_decode((string) ($endpoint['config_json'] ?? '{}'), true) ?: [];
        $alert = ['type' => 'danger', 'message' => 'Schema wurde nicht gefunden.'];
        if ($schema !== null) {
            try {
                $payload = json_decode($app->services()->get('api.endpoint_response_builder')->build($endpoint)->body(), true, 512, JSON_THROW_ON_ERROR);
                $definition = json_decode((string) ($schema['definition_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
                $result = is_array($definition) ? $schemaValidator()->validate($payload, $definition) : ['valid' => false, 'errors' => [], 'warnings' => []];
                $alert = [
                    'type' => ! empty($result['valid']) ? 'success' : 'danger',
                    'message' => ! empty($result['valid'])
                        ? 'Endpoint-Ergebnis ist schema-valide.'
                        : 'Endpoint-Ergebnis ist nicht schema-valide: ' . count((array) ($result['errors'] ?? [])) . ' Fehler.',
                ];
            } catch (Throwable $exception) {
                $alert = ['type' => 'danger', 'message' => 'Schema-Validierung fehlgeschlagen: ' . $exception->getMessage()];
            }
        }

        return $admin('admin/endpoints/show', [
            'title' => 'Endpoint',
            'active' => 'endpoints',
            'endpoint' => $endpoint,
            'workspaces' => safeList($workspaces),
            'mappings' => safeList($mappings),
            'jobs' => safeList($jobs),
            'schemas' => $schemas()->all(),
            'staticResponse' => isset($config['static_response']) ? (json_encode($config['static_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') : '',
            'hasSecret' => $endpoints()->hasSecret($id),
            'exportStatus' => $endpointExporter()->exportStatusForEndpoint($id),
            'contractTargets' => endpointTargetRows($endpoint, $deploymentTargets(), $targetUrlBuilder()),
            'currentEndpointUrl' => currentEndpointUrl($request, $targetUrlBuilder(), (string) $endpoint['endpoint_key']),
            'contractExportStatus' => null,
            'mappingSummary' => endpointMappingSummary($endpoint, $mappings, $connections),
            'alert' => $alert,
        ]);
    }, 'admin.endpoints.validate_schema', 'web');

    $routes->get('/admin/audit', static fn (): Response => $admin('admin/audit/index', [
        'title' => 'Audit',
        'active' => 'audit',
        'entries' => $audit()->recent(100),
    ]), 'admin.audit', 'web');

    $routes->post('/api/webhooks/woocommerce/{connection_token}', static function (Request $request) use ($woocommerceWebhookHandler): Response {
        $rawBody = file_get_contents('php://input');
        $result = $woocommerceWebhookHandler()->handle(
            (string) $request->route('connection_token'),
            [
                'X-WC-Webhook-Signature' => (string) $request->header('X-WC-Webhook-Signature', ''),
                'X-WC-Webhook-Topic' => (string) $request->header('X-WC-Webhook-Topic', ''),
                'X-WC-Webhook-Resource' => (string) $request->header('X-WC-Webhook-Resource', ''),
                'X-WC-Webhook-Event' => (string) $request->header('X-WC-Webhook-Event', ''),
                'X-WC-Webhook-Delivery-ID' => (string) $request->header('X-WC-Webhook-Delivery-ID', ''),
            ],
            $rawBody === false ? '' : $rawBody,
        );

        return Response::json($result['payload'], $result['status']);
    }, 'api.webhooks.woocommerce', 'web');

    $routes->get('/api/exports/woocommerce/{profile_key}', static function (Request $request) use ($exportProfiles, $woocommerceExport): Response {
        $profile = $exportProfiles()->findEnabledWooCommerceProfile((string) $request->route('profile_key'));
        if ($profile === null) {
            return Response::json([
                'success' => false,
                'error' => ['code' => 'not_found', 'message' => 'Export profile not found.'],
            ], 404);
        }

        $authorized = $woocommerceExport()->authenticate($profile, 'GET', $request->path(), (string) $request->server('QUERY_STRING', ''), '', (string) $request->header('Authorization', ''), [
            'x-luna-export-token' => (string) $request->header('X-Luna-Export-Token', ''),
            'x-luna-timestamp' => (string) $request->header('X-Luna-Timestamp', ''),
            'x-luna-signature' => (string) $request->header('X-Luna-Signature', ''),
        ]);
        if (! $authorized) {
            return Response::json([
                'success' => false,
                'error' => ['code' => 'unauthorized', 'message' => 'Export authentication failed.'],
            ], 401);
        }

        $result = $woocommerceExport()->export($profile, woocommerceExportParams($request), 'api');

        return Response::json($result, ! empty($result['success']) ? 200 : 500);
    }, 'api.exports.woocommerce', 'web');

    $routes->post('/api/exports/woocommerce/{profile_key}', static function (Request $request) use ($exportProfiles, $woocommerceExport): Response {
        $profile = $exportProfiles()->findEnabledWooCommerceProfile((string) $request->route('profile_key'));
        if ($profile === null) {
            return Response::json([
                'success' => false,
                'error' => ['code' => 'not_found', 'message' => 'Export profile not found.'],
            ], 404);
        }

        $rawBody = file_get_contents('php://input');
        $rawBody = $rawBody === false ? '' : $rawBody;
        $authorized = $woocommerceExport()->authenticate($profile, 'POST', $request->path(), (string) $request->server('QUERY_STRING', ''), $rawBody, (string) $request->header('Authorization', ''), [
            'x-luna-export-token' => (string) $request->header('X-Luna-Export-Token', ''),
            'x-luna-timestamp' => (string) $request->header('X-Luna-Timestamp', ''),
            'x-luna-signature' => (string) $request->header('X-Luna-Signature', ''),
        ]);
        if (! $authorized) {
            return Response::json([
                'success' => false,
                'error' => ['code' => 'unauthorized', 'message' => 'Export authentication failed.'],
            ], 401);
        }

        $result = $woocommerceExport()->export($profile, woocommerceExportParams($request), 'api');

        return Response::json($result, ! empty($result['success']) ? 200 : 500);
    }, 'api.exports.woocommerce_post', 'web');

    $routes->get('/health', static fn (): Response => Response::json([
        'status' => 'ok',
        'app' => $app->config()->string('APP_NAME', 'Luna V3'),
    ]), 'web.health', 'web');
};
