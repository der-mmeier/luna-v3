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
    private array $sourcePreviewRows = [];
    private array $previewRecords = [];
    private array $errors = [];
    private array $warnings = [];
    private array $resolverEvents = [];
    private array $logs = [];

    public function __construct(public readonly bool $dryRun) {}

    public function addPreviewRow(array $row): void
    {
        if (count($this->previewRows) < 20) {
            $this->previewRows[] = $this->mask($row);
        }
    }

    public function addSourceRow(array $row): void
    {
        if (count($this->sourcePreviewRows) < 20) {
            $this->sourcePreviewRows[] = $this->mask($row);
        }
    }

    public function addPreviewRecord(array $source, array $transfer, array $events): void
    {
        if (count($this->previewRecords) >= 20) {
            return;
        }

        $lookups = array_values(array_filter(array_map(static function (array $event): ?array {
            $context = is_array($event['context'] ?? null) ? $event['context'] : [];

            if (($context['resolver'] ?? null) !== 'lookup_value') {
                return null;
            }

            return [
                'field' => (string) ($event['target_column'] ?? ''),
                'template' => (string) ($context['template'] ?? ''),
                'rendered_key' => (string) ($context['rendered_key'] ?? ''),
                'lookup_connection' => (string) ($context['lookup_connection'] ?? ''),
                'lookup_table' => (string) ($context['lookup_table'] ?? ''),
                'lookup_key_column' => (string) ($context['lookup_key_column'] ?? ''),
                'lookup_value_column' => (string) ($context['lookup_value_column'] ?? ''),
                'lookup_match_mode' => (string) ($context['lookup_match_mode'] ?? 'exact'),
                'lookup_result_mode' => (string) ($context['lookup_result_mode'] ?? 'first'),
                'lookup_result_key_column' => (string) ($context['lookup_result_key_column'] ?? ''),
                'lookup_result_key_transform' => (string) ($context['lookup_result_key_transform'] ?? 'none'),
                'rendered_result_key_prefix' => (string) ($context['rendered_result_key_prefix'] ?? ''),
                'rendered_pattern' => (string) ($context['rendered_pattern'] ?? ''),
                'match_count' => (int) ($context['match_count'] ?? 0),
                'matched_values' => $context['matched_values'] ?? [],
                'result_warnings' => $context['result_warnings'] ?? [],
                'value' => $context['value'] ?? null,
                'status' => (string) ($context['status'] ?? $event['code'] ?? ''),
            ];
        }, $events)));

        $errors = array_values(array_filter(array_map(static function (array $event): ?array {
            if (($event['level'] ?? null) !== 'error') {
                return null;
            }

            return [
                'field' => (string) ($event['target_column'] ?? ''),
                'code' => (string) ($event['code'] ?? ''),
                'context' => is_array($event['context'] ?? null) ? $event['context'] : [],
            ];
        }, $events)));

        $this->previewRecords[] = [
            'source' => $this->mask($source),
            'lookups' => $this->mask($lookups),
            'transfer' => $this->mask($transfer),
            'errors' => $this->mask($errors),
        ];
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

    public function addResolverError(string $code, string $targetColumn, array $context = []): void
    {
        $this->resolverEvents[] = [
            'level' => 'error',
            'code' => $code,
            'target_column' => $targetColumn,
            'context' => $this->mask($context),
        ];
        $this->addError($code . ':' . $targetColumn);
    }

    public function addResolverWarning(string $code, string $targetColumn, array $context = []): void
    {
        $this->resolverEvents[] = [
            'level' => 'warning',
            'code' => $code,
            'target_column' => $targetColumn,
            'context' => $this->mask($context),
        ];
        $this->addWarning($code . ':' . $targetColumn);
    }

    public function addResolverEvent(string $code, string $targetColumn, array $context = []): void
    {
        $this->resolverEvents[] = [
            'level' => 'info',
            'code' => $code,
            'target_column' => $targetColumn,
            'context' => $this->mask($context),
        ];
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
            'primary_source_preview' => $this->sourcePreviewRows,
            'transfer_preview' => $this->previewRows,
            'records' => $this->previewRecords,
            'resolver_events' => $this->resolverEvents,
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

    public function resolverEvents(): array
    {
        return $this->resolverEvents;
    }

    public function resolverEventCount(): int
    {
        return count($this->resolverEvents);
    }

    public function resolverEventsSince(int $offset): array
    {
        return array_slice($this->resolverEvents, $offset);
    }

    private function mask(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match('/(^|_)(secret|password|token|api_key|app_key|client_secret|dsn|secret_value_encrypted)(_|$)/i', $key) === 1) {
                $context[$key] = '***';
            } elseif (is_array($value)) {
                $context[$key] = $this->mask($value);
            }
        }
        return $context;
    }
}
