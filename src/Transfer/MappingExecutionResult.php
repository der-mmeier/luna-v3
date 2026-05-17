<?php

declare(strict_types=1);

namespace Luna\Transfer;

final class MappingExecutionResult
{
    public int $sourceCount = 0;
    public int $transformedCount = 0;
    public int $writtenCount = 0;
    public int $skippedCount = 0;
    public int $errorCount = 0;
    private array $previewRows = [];
    private array $errors = [];
    private array $warnings = [];
    private array $logs = [];

    public function __construct(public readonly bool $dryRun) {}

    public function addPreviewRow(array $row): void
    {
        if (count($this->previewRows) < 20) {
            $this->previewRows[] = $row;
        }
    }

    public function addError(string $message): void
    {
        $this->errorCount++;
        $this->errors[] = $message;
        $this->addLog('error', $message);
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
        $this->addLog('warning', $message);
    }

    public function addLog(string $level, string $message, array $context = []): void
    {
        $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $this->mask($context)];
    }

    public function toSummaryArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'source_count' => $this->sourceCount,
            'transformed_count' => $this->transformedCount,
            'written_count' => $this->writtenCount,
            'skipped_count' => $this->skippedCount,
            'error_count' => $this->errorCount,
            'preview_rows' => $this->previewRows,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'logs' => $this->logs,
        ];
    }

    public function isSuccessful(): bool
    {
        return $this->errorCount === 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function logs(): array
    {
        return $this->logs;
    }

    private function mask(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match('/secret|password|token|api_key|app_key|client_secret|key/i', $key) === 1) {
                $context[$key] = '***';
            } elseif (is_array($value)) {
                $context[$key] = $this->mask($value);
            }
        }
        return $context;
    }
}
