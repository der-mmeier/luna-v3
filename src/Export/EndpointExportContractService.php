<?php

declare(strict_types=1);

namespace Luna\Export;

use DateTimeImmutable;
use Luna\Deployment\DeploymentTargetUrlBuilder;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\DeploymentTargetRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\MappingRepository;
use Luna\Repository\SchemaRegistryRepository;
use Luna\Repository\WorkspaceRepository;
use RuntimeException;

final class EndpointExportContractService
{
    public function __construct(
        private readonly EndpointRepository $endpoints,
        private readonly MappingRepository $mappings,
        private readonly ConnectionProfileRepository $connections,
        private readonly WorkspaceRepository $workspaces,
        private readonly DeploymentTargetRepository $deploymentTargets,
        private readonly DeploymentTargetUrlBuilder $urlBuilder,
        private readonly EndpointSchemaBuilder $schemaBuilder,
        private readonly EndpointExportSanitizer $sanitizer,
        private readonly string $basePath,
        private readonly ?SchemaRegistryRepository $schemas = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function exportEndpoint(int $endpointId, ?string $environment = null, ?string $outputBasePath = null): array
    {
        $endpoint = $this->endpoints->find($endpointId);
        if ($endpoint === null) {
            throw new RuntimeException('Endpoint konnte nicht gefunden werden.');
        }

        $mappingId = (int) ($endpoint['mapping_set_id'] ?? 0);
        if ($mappingId <= 0) {
            throw new RuntimeException('Endpoint konnte nicht exportiert werden, weil das Mapping fehlt.');
        }

        $mapping = $this->mappings->find($mappingId);
        if ($mapping === null) {
            throw new RuntimeException('Endpoint konnte nicht exportiert werden, weil das Mapping fehlt.');
        }

        $workspace = empty($endpoint['workspace_id']) ? null : $this->workspaces->find((int) $endpoint['workspace_id']);
        $fields = $this->mappings->fieldsForSet($mappingId);
        $filters = $this->mappings->sourceFiltersForSet($mappingId);
        $target = $environment === null || trim($environment) === ''
            ? null
            : $this->deploymentTargets->findActiveByEnvironment(empty($endpoint['workspace_id']) ? null : (int) $endpoint['workspace_id'], $environment);

        $timestamp = (new DateTimeImmutable())->format('Ymd-His');
        $slug = EndpointRepository::normalizeEndpointKey((string) ($endpoint['endpoint_key'] ?? ('endpoint-' . $endpointId)));
        $outputRoot = $outputBasePath === null || trim($outputBasePath) === ''
            ? $this->basePath . '/storage/exports/endpoints'
            : $this->absolutePath($outputBasePath);
        $targetPath = rtrim(str_replace('\\', '/', $outputRoot), '/') . '/' . str_replace('/', '-', $slug) . '-' . $timestamp;

        if (! is_dir($targetPath) && ! mkdir($targetPath, 0775, true) && ! is_dir($targetPath)) {
            throw new RuntimeException('Exportverzeichnis konnte nicht erstellt werden.');
        }

        $registeredSchema = $this->registeredSchema($endpoint);
        $schema = $registeredSchema === null ? $this->schemaBuilder->build($endpoint, $fields) : $this->schemaDocument($registeredSchema);
        $schemaReference = $registeredSchema === null ? null : [
            'schema_key' => (string) $registeredSchema['schema_key'],
            'version' => (string) $registeredSchema['version'],
            'id' => (int) $registeredSchema['id'],
        ];
        $endpointDocument = $this->endpointDocument($endpoint);
        if ($schemaReference !== null) {
            $endpointDocument['schema'] = $schemaReference;
        }
        $endpointDocument['runtime_storage'] = $this->runtimeStorageDocument();
        $mappingDocument = $this->mappingDocument($mapping, $fields, $filters);
        $targetDocument = $target === null ? null : [
            'environment' => (string) $target['environment'],
            'name' => (string) $target['name'],
            'endpoint_url' => $this->urlBuilder->endpointUrl($target, $slug),
        ];

        $files = [
            'schema.json' => $schema,
            'endpoint.json' => $endpointDocument,
            'mapping.json' => $mappingDocument,
            'README.md' => $this->readme($endpoint, $workspace, $mappingDocument, $targetDocument),
        ];

        foreach ($files as $file => $content) {
            $this->writeFile($targetPath . '/' . $file, $content);
        }

        $manifest = [
            'export_contract_version' => '1.0',
            'artifact_type' => 'endpoint',
            'exported_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'luna_version' => '2.2.0',
            'workspace' => $workspace === null ? null : [
                'id' => (int) $workspace['id'],
                'name' => (string) $workspace['name'],
            ],
            'endpoint' => [
                'id' => (int) $endpoint['id'],
                'name' => (string) $endpoint['name'],
                'slug' => $slug,
                'method' => (string) ($endpoint['method'] ?? 'GET'),
            ],
            'schema' => $schemaReference,
            'mapping' => [
                'id' => (int) $mapping['id'],
                'name' => (string) $mapping['name'],
            ],
            'target' => $targetDocument,
            'files' => ['schema.json', 'endpoint.json', 'mapping.json', 'README.md', 'checksums.json'],
            'security' => [
                'contains_secrets' => false,
                'connections_exported_as_references_only' => true,
            ],
            'runtime_storage' => $this->runtimeStorageDocument(),
        ];
        $this->writeFile($targetPath . '/manifest.json', $manifest);

        $checksums = $this->checksums($targetPath, ['manifest.json', 'schema.json', 'endpoint.json', 'mapping.json', 'README.md']);
        $this->writeFile($targetPath . '/checksums.json', $checksums);

        return $manifest + [
            'target_path' => $this->relativePath($targetPath),
            'absolute_target_path' => $targetPath,
            'checksums' => $checksums,
        ];
    }

    /**
     * @param array<string, mixed> $endpoint
     * @return array<string, mixed>
     */
    private function endpointDocument(array $endpoint): array
    {
        $slug = EndpointRepository::normalizeEndpointKey((string) ($endpoint['endpoint_key'] ?? ''));

        return $this->sanitize([
            'id' => (int) $endpoint['id'],
            'name' => (string) $endpoint['name'],
            'slug' => $slug,
            'method' => (string) ($endpoint['method'] ?? 'GET'),
            'path' => $this->urlBuilder->endpointPath($slug),
            'response_wrapper' => [
                'success' => true,
                'generated_at' => true,
                'count' => true,
                'items' => true,
            ],
            'mapping_id' => empty($endpoint['mapping_set_id']) ? null : (int) $endpoint['mapping_set_id'],
            'cache' => [
                'enabled' => ! empty($endpoint['cache_enabled']),
                'ttl_seconds' => empty($endpoint['cache_ttl_seconds']) ? null : (int) $endpoint['cache_ttl_seconds'],
            ],
            'status' => (string) ($endpoint['status'] ?? 'draft'),
            'runtime_storage' => $this->runtimeStorageDocument(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeStorageDocument(): array
    {
        return [
            'requires_transfer_db' => true,
            'runtime_storage' => 'transfer_db',
            'tables' => [
                'luna_transferdb_migrations',
                'luna_webhook_events',
                'luna_endpoint_snapshots',
                'luna_endpoint_snapshot_records',
                'luna_transfer_runs',
                'luna_transfer_run_logs',
                'luna_transfer_sources',
                'luna_transfer_records',
            ],
            'secrets_exported' => false,
        ];
    }

    /**
     * @param array<string, mixed> $endpoint
     * @return array<string, mixed>|null
     */
    private function registeredSchema(array $endpoint): ?array
    {
        if ($this->schemas === null || empty($endpoint['schema_id'])) {
            return null;
        }

        return $this->schemas->find((int) $endpoint['schema_id']);
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function schemaDocument(array $schema): array
    {
        $definition = json_decode((string) ($schema['definition_json'] ?? ''), true);
        if (! is_array($definition)) {
            $definition = ['type' => 'mixed'];
        }

        return $this->sanitize([
            'schema_key' => (string) ($schema['schema_key'] ?? ''),
            'version' => (string) ($schema['version'] ?? ''),
            'name' => (string) ($schema['name'] ?? ''),
            'status' => (string) ($schema['status'] ?? ''),
            'definition' => $definition,
            'example' => $this->decodeOptionalJson((string) ($schema['example_json'] ?? '')),
        ]);
    }

    /**
     * @param array<string, mixed> $mapping
     * @param list<array<string, mixed>> $fields
     * @param list<array<string, mixed>> $filters
     * @return array<string, mixed>
     */
    private function mappingDocument(array $mapping, array $fields, array $filters): array
    {
        $documentFields = [];
        foreach ($fields as $field) {
            $documentFields[] = $this->sanitize([
                'target_field' => (string) ($field['target_column'] ?? ''),
                'source_column' => (string) ($field['source_column'] ?? ''),
                'type' => (string) ($field['transform_type'] ?? 'direct'),
                'sources' => $this->sources((string) ($field['source_column'] ?? '')),
                'default_value' => $field['default_value'] ?? null,
                'lookup_connection_ref' => empty($field['lookup_connection_id']) ? null : $this->connectionReference((int) $field['lookup_connection_id']),
                'lookup_table' => $field['lookup_table'] ?? null,
                'lookup_key_column' => $field['lookup_key_column'] ?? null,
                'lookup_value_column' => $field['lookup_value_column'] ?? null,
                'lookup_key_template' => $field['lookup_key_template'] ?? null,
                'missing_behavior' => $field['missing_behavior'] ?? null,
                'schema_type' => $field['schema_type'] ?? 'auto',
                'schema_required' => ! empty($field['schema_required']),
                'schema_description' => $field['schema_description'] ?? null,
                'schema_example' => $field['schema_example'] ?? null,
                'sort_order' => (int) ($field['sort_order'] ?? 0),
            ]);
        }

        $documentFilters = [];
        foreach ($filters as $filter) {
            $documentFilters[] = $this->sanitize([
                'source_column' => (string) ($filter['source_column'] ?? ''),
                'operator' => (string) ($filter['operator'] ?? ''),
                'filter_value' => (string) ($filter['filter_value'] ?? ''),
                'sort_order' => (int) ($filter['sort_order'] ?? 0),
            ]);
        }

        return $this->sanitize([
            'id' => (int) $mapping['id'],
            'name' => (string) $mapping['name'],
            'mode' => (string) ($mapping['mapping_mode'] ?? ''),
            'source' => [
                'connection_ref' => empty($mapping['source_connection_id']) ? null : $this->connectionReference((int) $mapping['source_connection_id']),
                'table' => $mapping['source_table'] ?? null,
                'filters' => $documentFilters,
            ],
            'fields' => $documentFields,
            'lookups' => array_values(array_filter(array_map(
                fn (array $field): ?array => empty($field['lookup_connection_id']) ? null : [
                    'connection_ref' => $this->connectionReference((int) $field['lookup_connection_id']),
                    'table' => $field['lookup_table'] ?? null,
                    'key_column' => $field['lookup_key_column'] ?? null,
                    'value_column' => $field['lookup_value_column'] ?? null,
                ],
                $fields,
            ))),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function connectionReference(int $connectionId): ?array
    {
        $connection = $this->connections->find($connectionId);
        if ($connection === null) {
            return null;
        }

        return [
            'id' => (int) $connection['id'],
            'name' => (string) $connection['name'],
            'type' => (string) ($connection['type'] ?? $connection['driver'] ?? ''),
            'driver' => (string) ($connection['driver'] ?? ''),
            'read_only' => ! empty($connection['read_only']),
            'secret_free' => true,
        ];
    }

    /**
     * @return list<string>
     */
    private function sources(string $sourceColumn): array
    {
        if ($sourceColumn === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $sourceColumn)), static fn (string $source): bool => $source !== ''));
    }

    /**
     * @param array<string, mixed>|null $workspace
     * @param array<string, mixed> $mappingDocument
     * @param array<string, mixed>|null $target
     */
    private function readme(array $endpoint, ?array $workspace, array $mappingDocument, ?array $target): string
    {
        $lines = [
            '# Endpoint Export Contract',
            '',
            'Endpoint: ' . (string) $endpoint['name'],
            'Workspace: ' . (string) ($workspace['name'] ?? 'ohne Workspace'),
            'Methode und Pfad: ' . (string) ($endpoint['method'] ?? 'GET') . ' ' . $this->urlBuilder->endpointPath((string) $endpoint['endpoint_key']),
        ];

        if ($target !== null) {
            $lines[] = 'Target-URL: ' . (string) $target['endpoint_url'];
        }

        $lines[] = '';
        $lines[] = '## Response-Struktur';
        $lines[] = 'Die Antwort nutzt den Wrapper `success`, `generated_at`, `count` und `items`.';
        $lines[] = '';
        $lines[] = '## Mapping-Felder';
        foreach (($mappingDocument['fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }
            $lines[] = '- `' . (string) ($field['target_field'] ?? '') . '` aus `' . (string) ($field['source_column'] ?? '') . '` über `' . (string) ($field['type'] ?? '') . '`';
        }
        $lines[] = '';
        $lines[] = 'Dieses Exportpaket enthält keine Zugangsdaten, Tokens oder Passwörter. Connections werden nur als Referenzen beschrieben.';
        $lines[] = 'Kundeneigene Mappings, Endpoints und produktive Datenflüsse liegen in der Verantwortung des Betreibers der Luna-Installation.';
        $lines[] = 'Ein Deployment Target ist nur URL- und Umgebungsmetadatum und enthält keine Secrets.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function sanitize(array $value): array
    {
        $sanitized = $this->sanitizer->sanitize($value);

        return is_array($sanitized) ? $sanitized : [];
    }

    private function decodeOptionalJson(string $json): mixed
    {
        $json = trim($json);
        if ($json === '') {
            return null;
        }

        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private function writeFile(string $path, mixed $content): void
    {
        if (is_array($content)) {
            $encoded = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            file_put_contents($path, $encoded . "\n");

            return;
        }

        file_put_contents($path, (string) $content);
    }

    /**
     * @param list<string> $files
     * @return array<string, string>
     */
    private function checksums(string $targetPath, array $files): array
    {
        $checksums = [];
        foreach ($files as $file) {
            $hash = hash_file('sha256', $targetPath . '/' . $file);
            if (is_string($hash)) {
                $checksums[$file] = $hash;
            }
        }

        ksort($checksums);

        return $checksums;
    }

    private function absolutePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if (preg_match('/^[A-Za-z]:\//', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim(str_replace('\\', '/', $this->basePath), '/') . '/' . ltrim($path, '/');
    }

    private function relativePath(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', $this->basePath), '/') . '/';
        $normalized = str_replace('\\', '/', $path);

        return str_starts_with($normalized, $base) ? substr($normalized, strlen($base)) : $normalized;
    }
}
