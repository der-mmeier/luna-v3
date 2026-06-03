<?php

declare(strict_types=1);

namespace Luna\Transfer;

final class DatasetTransferResult
{
    public int $sourceCount = 0;
    public int $plannedCount = 0;
    public int $writtenCount = 0;
    public int $skippedCount = 0;

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<array<string, mixed>> */
    private array $previewOperations = [];

    /** @var list<array<string, mixed>> */
    private array $targetGroups = [];

    public function __construct(
        public readonly bool $dryRun,
        public readonly string $sourceDataset,
        public readonly string $targetTable,
        public readonly string $operationType,
        public readonly string $upsertKey,
    ) {
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * @param array<string, mixed> $operation
     */
    public function addPreviewOperation(array $operation): void
    {
        if (count($this->previewOperations) < 20) {
            $this->previewOperations[] = $operation;
        }
    }

    /**
     * @param list<array<string, mixed>> $operations
     */
    public function addTargetGroup(array $group, array $operations): void
    {
        $preview = array_slice($operations, 0, 20);
        $this->targetGroups[] = [
            'name' => (string) ($group['name'] ?? ''),
            'type' => (string) ($group['group_type'] ?? 'root'),
            'source_path' => (string) ($group['source_path'] ?? '$'),
            'target_table' => (string) ($group['target_table'] ?? ''),
            'operation' => (string) ($group['operation_type'] ?? ''),
            'upsert_key' => (string) ($group['upsert_key'] ?? ''),
            'planned_count' => count($operations),
            'preview_operations' => $preview,
        ];
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'source_dataset' => $this->sourceDataset,
            'target_table' => $this->targetTable,
            'operation' => $this->operationType,
            'upsert_key' => $this->upsertKey,
            'source_count' => $this->sourceCount,
            'planned_count' => $this->plannedCount,
            'written_count' => $this->writtenCount,
            'skipped_count' => $this->skippedCount,
            'error_count' => $this->errorCount(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'preview_operations' => $this->previewOperations,
            'target_groups' => $this->targetGroups,
        ];
    }
}
