<?php

declare(strict_types=1);

namespace Luna\Jobs;

use Luna\Repository\AuditLogRepository;
use Luna\Repository\JobRepository;
use Luna\Repository\JobRunRepository;
use Luna\Reports\ReportEngine;
use Luna\Transfer\MappingExecutor;
use Throwable;

final class JobRunner
{
    public function __construct(
        private readonly JobRepository $jobs,
        private readonly JobRunRepository $runs,
        private readonly MappingExecutor $executor,
        private readonly ReportEngine $reports,
        private readonly AuditLogRepository $audit,
    ) {}

    public function runJob(int $jobId, ?bool $dryRunOverride = null): int
    {
        $job = $this->jobs->find($jobId);
        if ($job === null) {
            throw new \RuntimeException('Job not found.');
        }
        $dryRun = $dryRunOverride ?? ((int) $job['dry_run_default'] === 1);
        $runId = $this->runs->createRun($jobId, isset($job['workspace_id']) ? (int) $job['workspace_id'] : null, (int) $job['mapping_set_id'], $dryRun);
        if (! $dryRun && (string) $job['transfer_mode'] !== 'insert') {
            $this->runs->markRunning($runId);
            $this->runs->addLog($runId, 'error', 'Transfer mode is not executable in 0.9.0.');
            $this->runs->markFailed($runId, 'Transfer mode is not executable in 0.9.0.');
            return $runId;
        }
        $this->run($runId, (int) $job['mapping_set_id'], $dryRun, empty($job['row_limit']) ? null : (int) $job['row_limit'], $job);
        $this->jobs->touchLastRun($jobId);
        return $runId;
    }

    public function runMappingOnce(int $mappingSetId, bool $dryRun = true, ?int $limit = null): int
    {
        $runId = $this->runs->createRun(null, null, $mappingSetId, $dryRun);
        $this->run($runId, $mappingSetId, $dryRun, $limit, null);
        return $runId;
    }

    private function run(int $runId, int $mappingSetId, bool $dryRun, ?int $limit, ?array $job): void
    {
        $this->runs->markRunning($runId);
        $this->audit->log($job['workspace_id'] ?? null, $dryRun ? 'mapping.dry_run.started' : 'mapping.transfer.started', 'job_run', (string) $runId, 'Job Run gestartet.');

        try {
            $result = $this->executor->execute($mappingSetId, $dryRun, $limit);
            foreach ($result->logs() as $log) {
                $this->runs->addLog($runId, $log['level'], $log['message'], $log['context']);
            }
            $summary = $result->toSummaryArray();
            if ($result->isSuccessful()) {
                $this->runs->markFinished($runId, $summary);
                $this->audit->log($job['workspace_id'] ?? null, $dryRun ? 'mapping.dry_run.finished' : 'mapping.transfer.success', 'job_run', (string) $runId, 'Job Run erfolgreich.');
            } else {
                $message = $this->failureMessage($result->errors());
                $this->runs->markFailed($runId, $message, $summary);
                $this->audit->log($job['workspace_id'] ?? null, $dryRun ? 'mapping.dry_run.finished' : 'mapping.transfer.failed', 'job_run', (string) $runId, $message, [
                    'error_count' => $summary['error_count'] ?? 1,
                    'written_count' => $summary['written_count'] ?? 0,
                ]);
            }
            if ($job !== null && (int) $job['report_enabled'] === 1) {
                $this->reports->createJobRunReport($runId);
            }
        } catch (Throwable $exception) {
            $this->runs->addLog($runId, 'error', 'Job Run failed.');
            $this->runs->markFailed($runId, 'Job Run failed.');
            $this->audit->log($job['workspace_id'] ?? null, 'job.run.failed', 'job_run', (string) $runId, 'Job Run fehlgeschlagen.');
        }
    }

    private function failureMessage(array $errors): string
    {
        $message = $errors[0] ?? 'Mapping execution failed.';
        return is_string($message) && $message !== '' ? $message : 'Mapping execution failed.';
    }
}
