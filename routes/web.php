<?php

declare(strict_types=1);

use Luna\Connections\ConnectionProfileData;
use Luna\Connections\ConnectionTester;
use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Core\Application;
use Luna\Http\Request;
use Luna\Http\Response;
use Luna\Jobs\JobRunner;
use Luna\Mapping\LookupKeyTemplateRenderer;
use Luna\Mapping\MappingValidator;
use Luna\Mapping\TransformType;
use Luna\Reports\ReportMailer;
use Luna\Repository\AuditLogRepository;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\JobRepository;
use Luna\Repository\JobRunRepository;
use Luna\Repository\MappingRepository;
use Luna\Repository\ReportRepository;
use Luna\Repository\SchemaMetadataRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Routing\RouteCollection;
use Luna\Schema\SampleDataReader;
use Luna\Schema\SchemaInspector;
use Luna\Schema\TableNameReader;
use Luna\View\ViewRenderer;

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

if (! function_exists('mappingSetValues')) {
    function mappingSetValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'description' => (string) $request->post('description', ''),
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

        if (trim((string) $values['name']) === '') {
            $errors[] = 'Name ist erforderlich.';
        }

        if (! in_array((string) $values['status'], ['draft', 'active', 'archived'], true)) {
            $errors[] = 'Status ist ungültig.';
        }

        return $errors;
    }
}

if (! function_exists('mappingFieldValues')) {
    function mappingFieldValues(Request $request): array
    {
        $targetColumn = trim((string) $request->post('target_column', ''));

        return [
            'source_column' => (string) $request->post('source_column', ''),
            'source_json_path' => (string) $request->post('source_json_path', ''),
            'target_column' => $targetColumn === '' ? 'resolved_value' : $targetColumn,
            'transform_type' => (string) $request->post('transform_type', 'direct'),
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
    function mappingFieldsData(Closure $mappings, Closure $connections, Closure $pdoFactory, int $id, array $previewValues = [], array $extra = []): array
    {
        $data = mappingViewData($mappings, $id, $extra);
        $data['sourceColumns'] = [];
        $data['sourceSamples'] = [];
        $data['lookupColumns'] = [];
        $data['lookupSamples'] = [];
        $data['lookupTestResults'] = [];
        $data['lookupTestRequested'] = ! empty($previewValues['lookup_test']);
        $data['previewValues'] = mappingPreviewValues($previewValues);
        $data['connections'] = [];
        $data['columnWarning'] = null;
        $data['lookupWarning'] = null;

        if ($data['mapping'] === null) {
            return $data;
        }

        try {
            $source = $connections()->find((int) $data['mapping']['source_connection_id']);
            $data['connections'] = $connections()->all();

            if ($source !== null && ! empty($data['mapping']['source_table'])) {
                $sourceConfig = ExternalDatabaseConfig::fromProfile($source, $connections()->secretsFor((int) $source['id']));
                $sourcePdo = $pdoFactory()->create($sourceConfig);
                $data['sourceColumns'] = (new SchemaInspector($sourcePdo))->columns((string) $data['mapping']['source_table']);
                $data['sourceSamples'] = mappingSampleRows($sourcePdo, (string) $data['mapping']['source_table'], $data['previewValues']);
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

if (! function_exists('mappingPreviewValues')) {
    function mappingPreviewValues(array $values): array
    {
        $operator = (string) ($values['source_filter_operator'] ?? 'is_numeric_gt_zero');
        $sourceColumn = (string) ($values['source_column'] ?? '');
        $sourceFilterColumn = (string) ($values['source_filter_column'] ?? '');

        if (! in_array($operator, ['none', 'is_numeric_gt_zero', 'gt', 'gte', 'eq'], true)) {
            $operator = 'is_numeric_gt_zero';
        }

        return [
            'source_column' => $sourceColumn,
            'source_filter_column' => $sourceFilterColumn === '' ? $sourceColumn : $sourceFilterColumn,
            'source_filter_operator' => $operator,
            'source_filter_value' => (string) ($values['source_filter_value'] ?? '0'),
            'target_column' => trim((string) ($values['target_column'] ?? '')),
            'transform_type' => (string) ($values['transform_type'] ?? 'lookup_value'),
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

if (! function_exists('mappingSampleRows')) {
    function mappingSampleRows(PDO $pdo, string $tableName, array $values): array
    {
        $operator = (string) ($values['source_filter_operator'] ?? 'none');
        $column = (string) ($values['source_filter_column'] ?? '');

        if ($operator === 'none' || $column === '') {
            return (new SampleDataReader($pdo))->sampleRows($tableName, null, 10);
        }

        mappingAssertIdentifier($tableName);
        mappingAssertIdentifier($column);

        $table = mappingQuoteIdentifier($tableName);
        $quotedColumn = mappingQuoteIdentifier($column);

        if ($operator === 'is_numeric_gt_zero') {
            $statement = $pdo->query(sprintf(
                "SELECT * FROM %s WHERE TRIM(CAST(%s AS CHAR)) REGEXP '^[0-9]+$' AND CAST(%s AS SIGNED) > 0 LIMIT 10",
                $table,
                $quotedColumn,
                $quotedColumn,
            ));

            return $statement->fetchAll();
        }

        $value = (string) ($values['source_filter_value'] ?? '');
        $sqlOperator = ['gt' => '>', 'gte' => '>=', 'eq' => '='][$operator] ?? '=';
        $statement = $pdo->prepare(sprintf('SELECT * FROM %s WHERE %s %s :filter_value LIMIT 10', $table, $quotedColumn, $sqlOperator));
        $statement->execute(['filter_value' => $value]);

        return $statement->fetchAll();
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
            $statement = $pdo->prepare(sprintf('SELECT `%s` AS lookup_value FROM `%s` WHERE `%s` = :lookup_key LIMIT 1', $valueColumn, $table, $keyColumn));
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

                if ($sourceValueText === '' || $sourceValueText === '-' || ! ctype_digit($sourceValueText) || (int) $sourceValueText <= 0) {
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

                $statement->execute(['lookup_key' => $rendered->value]);
                $lookupRow = $statement->fetch();

                if (is_array($lookupRow)) {
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
            'visibility' => (string) $request->post('visibility', 'private'),
            'status' => (string) $request->post('status', 'draft'),
            'response_type' => 'json',
            'source_type' => (string) $request->post('source_type', 'static'),
            'mapping_set_id' => $request->post('mapping_set_id'),
            'job_id' => $request->post('job_id'),
            'config_json' => $config === [] ? '' : (json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            'rate_limit_per_minute' => $request->post('rate_limit_per_minute'),
            'notes' => (string) $request->post('notes', ''),
            'static_response' => $staticResponse,
        ];
    }
}

if (! function_exists('endpointErrors')) {
    function endpointErrors(array $values, bool $requireSecret, string $staticResponse): array
    {
        $errors = [];
        if (trim((string) $values['name']) === '') {
            $errors[] = 'Name ist erforderlich.';
        }
        if (trim((string) $values['endpoint_key']) === '') {
            $errors[] = 'Endpoint Key ist erforderlich.';
        }
        if (! in_array((string) $values['method'], ['GET', 'POST'], true)) {
            $errors[] = 'Method ist ungültig.';
        }
        if (! in_array((string) $values['visibility'], ['public', 'private'], true)) {
            $errors[] = 'Visibility ist ungültig.';
        }
        if (! in_array((string) $values['status'], ['draft', 'active', 'disabled'], true)) {
            $errors[] = 'Status ist ungültig.';
        }
        if (! in_array((string) $values['source_type'], ['static', 'version', 'mapping_dry_run', 'job_status', 'latest_report'], true)) {
            $errors[] = 'Source Type ist ungültig.';
        }
        if ($requireSecret && (string) $values['visibility'] === 'private') {
            $errors[] = 'Private Endpoints sollten ein Secret erhalten.';
        }
        if ($staticResponse !== '' && json_decode($staticResponse, true) === null && json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'Static Response JSON ist ungültig.';
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
    $reports = static fn (): ReportRepository => $app->services()->get('repository.reports');
    $endpoints = static fn (): EndpointRepository => $app->services()->get('repository.endpoints');
    $jobRunner = static fn (): JobRunner => $app->services()->get('jobs.runner');
    $reportMailer = static fn (): ReportMailer => $app->services()->get('reports.mailer');
    $validator = static fn (): MappingValidator => $app->services()->get('mapping.validator');
    $pdoFactory = static fn (): ExternalPdoConnectionFactory => $app->services()->get('connections.pdo_factory');
    $configFor = static function (array $profile) use ($connections): ExternalDatabaseConfig {
        return ExternalDatabaseConfig::fromProfile($profile, $connections()->secretsFor((int) $profile['id']));
    };

    $dashboardData = static fn (): array => [
        'workspaceCount' => count(safeList($workspaces)),
        'connectionCount' => count(safeList($connections)),
        'mappingCount' => count(safeList($mappings)),
        'jobCount' => count(safeList($jobs)),
    ];

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
        'values' => ['status' => 'draft'],
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

    $routes->get('/admin/mappings/{id}/fields', static function (Request $request) use ($admin, $mappings, $connections, $pdoFactory): Response {
        $id = (int) $request->route('id');
        $previewValues = [
            'source_column' => $request->query('source_column', ''),
            'source_filter_column' => $request->query('source_filter_column', ''),
            'source_filter_operator' => $request->query('source_filter_operator', 'is_numeric_gt_zero'),
            'source_filter_value' => $request->query('source_filter_value', '0'),
            'target_column' => $request->query('target_column', ''),
            'transform_type' => $request->query('transform_type', 'lookup_value'),
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

        return $admin('admin/mappings/fields', mappingFieldsData($mappings, $connections, $pdoFactory, $id, $previewValues, [
            'title' => 'Feldzuordnungen',
            'active' => 'mappings',
            'transformTypes' => TransformType::formLabels(),
        ]));
    }, 'admin.mappings.fields', 'web');

    $routes->post('/admin/mappings/{id}/fields', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $set = $mappings()->find($id);
        $fieldId = $mappings()->addField($id, mappingFieldValues($request));
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

    $routes->post('/admin/mappings/{id}/fields/{fieldId}', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');
        $set = $mappings()->find($id);
        $mappings()->updateField($fieldId, mappingFieldValues($request));
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
        'values' => ['method' => 'GET', 'visibility' => 'private', 'status' => 'draft', 'source_type' => 'static', 'response_type' => 'json'],
        'errors' => [],
    ]), 'admin.endpoints.create', 'web');

    $routes->post('/admin/endpoints', static function (Request $request) use ($admin, $endpoints, $audit, $workspaces, $mappings, $jobs): Response {
        $values = endpointValues($request);
        $secret = (string) $request->post('secret', '');
        $errors = endpointErrors($values, $secret === '', (string) $values['static_response']);

        if ($errors !== []) {
            return $admin('admin/endpoints/create', [
                'title' => 'Endpoint anlegen',
                'active' => 'endpoints',
                'workspaces' => safeList($workspaces),
                'mappings' => safeList($mappings),
                'jobs' => safeList($jobs),
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

    $routes->get('/admin/endpoints/{id}', static function (Request $request) use ($admin, $endpoints, $workspaces, $mappings, $jobs): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);
        $config = $endpoint === null ? [] : (json_decode((string) ($endpoint['config_json'] ?? '{}'), true) ?: []);

        return $admin('admin/endpoints/show', [
            'title' => 'Endpoint',
            'active' => 'endpoints',
            'endpoint' => $endpoint,
            'workspaces' => safeList($workspaces),
            'mappings' => safeList($mappings),
            'jobs' => safeList($jobs),
            'staticResponse' => isset($config['static_response']) ? (json_encode($config['static_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') : '',
            'hasSecret' => $endpoint !== null && $endpoints()->hasSecret((int) $endpoint['id']),
            'alert' => null,
        ]);
    }, 'admin.endpoints.show', 'web');

    $routes->post('/admin/endpoints/{id}', static function (Request $request) use ($admin, $endpoints, $audit, $workspaces, $mappings, $jobs): Response {
        $id = (int) $request->route('id');
        $existing = $endpoints()->find($id);
        if ($existing === null) {
            return Response::notFound();
        }

        $values = endpointValues($request);
        $secret = (string) $request->post('secret', '');
        $errors = endpointErrors($values, false, (string) $values['static_response']);
        if ($errors !== []) {
            return $admin('admin/endpoints/show', [
                'title' => 'Endpoint',
                'active' => 'endpoints',
                'endpoint' => $values + ['id' => $id],
                'workspaces' => safeList($workspaces),
                'mappings' => safeList($mappings),
                'jobs' => safeList($jobs),
                'staticResponse' => (string) $values['static_response'],
                'hasSecret' => $endpoints()->hasSecret($id),
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

    $routes->post('/admin/endpoints/{id}/delete', static function (Request $request) use ($endpoints, $audit): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);
        $endpoints()->delete($id);
        $audit()->log(isset($endpoint['workspace_id']) ? (int) $endpoint['workspace_id'] : null, 'endpoint.deleted', 'endpoint', (string) $id, 'Endpoint gelöscht.');

        return new Response('', 302, ['Location' => '/admin/endpoints']);
    }, 'admin.endpoints.delete', 'web');

    $routes->post('/admin/endpoints/{id}/test', static function (Request $request) use ($admin, $endpoints, $app): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);
        if ($endpoint === null) {
            return Response::notFound();
        }

        $result = ['notice' => 'Private Endpoints benötigen ein Secret. Secrets werden hier nicht angezeigt.'];
        if ((string) $endpoint['visibility'] === 'public') {
            $result = json_decode($app->services()->get('api.endpoint_response_builder')->build($endpoint)->body(), true) ?: [];
        }

        return $admin('admin/endpoints/test', [
            'title' => 'Endpoint testen',
            'active' => 'endpoints',
            'endpoint' => $endpoint,
            'result' => $result,
        ]);
    }, 'admin.endpoints.test', 'web');

    $routes->get('/admin/audit', static fn (): Response => $admin('admin/audit/index', [
        'title' => 'Audit',
        'active' => 'audit',
        'entries' => $audit()->recent(100),
    ]), 'admin.audit', 'web');

    $routes->get('/health', static fn (): Response => Response::json([
        'status' => 'ok',
        'app' => $app->config()->string('APP_NAME', 'Luna V3'),
    ]), 'web.health', 'web');
};
