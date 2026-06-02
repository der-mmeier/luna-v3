<?php

declare(strict_types=1);

namespace Luna\Dataset;

use Closure;
use Luna\Repository\EndpointRepository;
use Luna\Repository\MappingRepository;
use Luna\Transfer\MappingExecutionResult;

final class DatasetRegistry
{
    private Closure $mappingExecutor;

    public function __construct(
        private readonly EndpointRepository $endpoints,
        private readonly MappingRepository $mappings,
        ?callable $mappingExecutor = null,
    ) {
        $this->mappingExecutor = Closure::fromCallable($mappingExecutor ?? static fn (): ?MappingExecutionResult => null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $datasets = [];
        $seenMappings = [];

        foreach ($this->endpoints->all() as $endpoint) {
            $mappingId = (int) ($endpoint['mapping_set_id'] ?? 0);
            if ($mappingId <= 0) {
                continue;
            }

            $mapping = $this->mappings->find($mappingId);
            if ($mapping === null) {
                continue;
            }

            $datasets[] = $this->fromEndpoint($endpoint, $mapping);
            $seenMappings[$mappingId] = true;
        }

        foreach ($this->mappings->all() as $mapping) {
            $mappingId = (int) ($mapping['id'] ?? 0);
            if ($mappingId <= 0 || isset($seenMappings[$mappingId])) {
                continue;
            }

            if ((string) ($mapping['mapping_mode'] ?? 'transfer') !== 'json_endpoint') {
                continue;
            }

            $datasets[] = $this->fromMapping($mapping);
        }

        usort($datasets, static fn (array $left, array $right): int => (string) $left['name'] <=> (string) $right['name']);

        return $datasets;
    }

    public function find(string $name): ?array
    {
        foreach ($this->all() as $dataset) {
            if ((string) $dataset['name'] === $name) {
                return $dataset;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(string $name): array
    {
        $dataset = $this->find($name);
        if ($dataset === null) {
            return [];
        }

        return $this->fieldsForMapping((int) $dataset['mapping_set_id']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourceFilters(string $name): array
    {
        $dataset = $this->find($name);
        if ($dataset === null) {
            return [];
        }

        return $this->mappings->sourceFiltersForSet((int) $dataset['mapping_set_id']);
    }

    /**
     * @return array{dataset: array<string, mixed>, rows: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function preview(string $name, int $limit = 10): array
    {
        $dataset = $this->find($name);
        if ($dataset === null) {
            return [
                'dataset' => [],
                'rows' => [],
                'summary' => ['error_count' => 1, 'errors' => ['Dataset wurde nicht gefunden.']],
            ];
        }

        $execution = ($this->mappingExecutor)((int) $dataset['mapping_set_id'], true, max(1, min($limit, 100)));
        if (! $execution instanceof MappingExecutionResult) {
            return [
                'dataset' => $dataset,
                'rows' => [],
                'summary' => ['error_count' => 1, 'errors' => ['Dataset Preview ist nicht verfügbar.']],
            ];
        }

        $executionSummary = $execution->toSummaryArray();
        $rows = $executionSummary['preview_rows'] ?? [];

        return [
            'dataset' => $dataset,
            'rows' => is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [],
            'summary' => [
                'dry_run' => (bool) ($executionSummary['dry_run'] ?? true),
                'source_count' => (int) ($executionSummary['source_count'] ?? 0),
                'transformed_count' => (int) ($executionSummary['transformed_count'] ?? 0),
                'written_count' => (int) ($executionSummary['written_count'] ?? 0),
                'skipped_count' => (int) ($executionSummary['skipped_count'] ?? 0),
                'error_count' => (int) ($executionSummary['error_count'] ?? 0),
                'errors' => is_array($executionSummary['errors'] ?? null) ? $executionSummary['errors'] : [],
                'warnings' => is_array($executionSummary['warnings'] ?? null) ? $executionSummary['warnings'] : [],
                'diagnostics' => is_array($executionSummary['diagnostics'] ?? null) ? $executionSummary['diagnostics'] : [],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $name, ?int $limit = null): array
    {
        $dataset = $this->find($name);
        if ($dataset === null) {
            return [];
        }

        $execution = ($this->mappingExecutor)((int) $dataset['mapping_set_id'], true, $limit);
        if (! $execution instanceof MappingExecutionResult) {
            return [];
        }

        $executionSummary = $execution->toSummaryArray();
        $rows = $executionSummary['output_rows'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fieldsForMapping(int $mappingId): array
    {
        return array_map(static fn (array $field): array => [
            'name' => (string) ($field['target_column'] ?? ''),
            'source_column' => (string) ($field['source_column'] ?? ''),
            'transform_type' => (string) ($field['transform_type'] ?? ''),
            'lookup_key_template' => (string) ($field['lookup_key_template'] ?? ''),
            'sort_order' => (int) ($field['sort_order'] ?? 0),
        ], $this->mappings->fieldsForSet($mappingId));
    }

    /**
     * @param array<string, mixed> $endpoint
     * @param array<string, mixed> $mapping
     *
     * @return array<string, mixed>
     */
    private function fromEndpoint(array $endpoint, array $mapping): array
    {
        $name = (string) ($endpoint['endpoint_key'] ?? '');

        return [
            'name' => $name,
            'label' => (string) ($endpoint['name'] ?? $name),
            'description' => (string) ($endpoint['description'] ?? ''),
            'source_type' => 'endpoint',
            'source_id' => (int) ($endpoint['id'] ?? 0),
            'mapping_set_id' => (int) ($mapping['id'] ?? $endpoint['mapping_set_id'] ?? 0),
            'mapping_name' => (string) ($mapping['name'] ?? $endpoint['mapping_name'] ?? ''),
            'status' => (string) ($endpoint['status'] ?? ''),
            'mapping_status' => (string) ($mapping['status'] ?? ''),
            'workspace_name' => (string) ($endpoint['workspace_name'] ?? $mapping['workspace_name'] ?? ''),
            'source_connection_name' => (string) ($mapping['source_connection_name'] ?? ''),
            'source_table' => (string) ($mapping['source_table'] ?? ''),
            'is_source_available' => true,
            'fields' => $this->fieldsForMapping((int) ($mapping['id'] ?? $endpoint['mapping_set_id'] ?? 0)),
            'source_filters' => $this->mappings->sourceFiltersForSet((int) ($mapping['id'] ?? $endpoint['mapping_set_id'] ?? 0)),
        ];
    }

    /**
     * @param array<string, mixed> $mapping
     *
     * @return array<string, mixed>
     */
    private function fromMapping(array $mapping): array
    {
        $name = $this->mappingDatasetName($mapping);

        return [
            'name' => $name,
            'label' => (string) ($mapping['name'] ?? $name),
            'description' => (string) ($mapping['description'] ?? ''),
            'source_type' => 'mapping_set',
            'source_id' => (int) ($mapping['id'] ?? 0),
            'mapping_set_id' => (int) ($mapping['id'] ?? 0),
            'mapping_name' => (string) ($mapping['name'] ?? ''),
            'status' => (string) ($mapping['status'] ?? ''),
            'mapping_status' => (string) ($mapping['status'] ?? ''),
            'workspace_name' => (string) ($mapping['workspace_name'] ?? ''),
            'source_connection_name' => (string) ($mapping['source_connection_name'] ?? ''),
            'source_table' => (string) ($mapping['source_table'] ?? ''),
            'is_source_available' => true,
            'fields' => $this->fieldsForMapping((int) ($mapping['id'] ?? 0)),
            'source_filters' => $this->mappings->sourceFiltersForSet((int) ($mapping['id'] ?? 0)),
        ];
    }

    /**
     * @param array<string, mixed> $mapping
     */
    private function mappingDatasetName(array $mapping): string
    {
        $name = strtolower(trim((string) ($mapping['name'] ?? '')));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = trim($name, '_');

        return $name !== '' ? $name : 'mapping_' . (int) ($mapping['id'] ?? 0);
    }
}
