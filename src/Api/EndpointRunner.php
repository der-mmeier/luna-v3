<?php

declare(strict_types=1);

namespace Luna\Api;

use Luna\Http\Response;
use Luna\Transfer\MappingExecutionResult;
use Closure;
use Throwable;

final class EndpointRunner
{
    private Closure $mappingFinder;
    private Closure $mappingExecutor;

    public function __construct(
        private readonly EndpointJsonResponseFactory $responses,
        callable $mappingFinder,
        callable $mappingExecutor,
    ) {
        $this->mappingFinder = Closure::fromCallable($mappingFinder);
        $this->mappingExecutor = Closure::fromCallable($mappingExecutor);
    }

    public function run(array $endpoint): Response
    {
        if ((string) ($endpoint['source_type'] ?? 'mapping') !== 'mapping') {
            return $this->responses->error('unsupported_endpoint_source', 'Endpoint source type is not supported.', 422);
        }

        $mappingId = (int) ($endpoint['mapping_set_id'] ?? 0);
        if ($mappingId <= 0) {
            return $this->responses->error('mapping_not_found', 'Mapping not found.', 404);
        }

        $mapping = ($this->mappingFinder)($mappingId);
        if (! is_array($mapping)) {
            return $this->responses->error('mapping_not_found', 'Mapping not found.', 404);
        }

        $workspaceId = (int) ($endpoint['workspace_id'] ?? 0);
        if ($workspaceId <= 0 || (int) ($mapping['workspace_id'] ?? 0) !== $workspaceId) {
            return $this->responses->error('mapping_workspace_mismatch', 'Mapping does not belong to the endpoint workspace.', 422);
        }

        try {
            $execution = ($this->mappingExecutor)($mappingId, $this->rowLimit($endpoint));
        } catch (Throwable) {
            return $this->responses->error('mapping_execution_failed', 'Mapping execution failed.', 500);
        }

        if (! $execution instanceof MappingExecutionResult || ! $execution->isSuccessful()) {
            return $this->responses->error('mapping_execution_failed', 'Mapping execution failed.', 500);
        }

        $summary = $execution->toSummaryArray();
        $items = $summary['output_rows'] ?? $summary['transfer_preview'] ?? $summary['preview_rows'] ?? [];

        return $this->responses->success(is_array($items) ? array_values(array_filter($items, 'is_array')) : []);
    }

    private function rowLimit(array $endpoint): ?int
    {
        $config = json_decode((string) ($endpoint['config_json'] ?? ''), true);
        $limit = is_array($config) ? (int) ($config['row_limit'] ?? 0) : 0;

        return $limit > 0 ? min($limit, 1000) : null;
    }
}
