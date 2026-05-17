<?php

declare(strict_types=1);

namespace Luna\Reports;

use Luna\Repository\AuditLogRepository;
use Luna\Repository\JobRunRepository;
use Luna\Repository\ReportRepository;

final class ReportEngine
{
    public function __construct(
        private readonly JobRunRepository $runs,
        private readonly ReportRepository $reports,
        private readonly AuditLogRepository $audit,
    ) {}

    public function createJobRunReport(int $jobRunId): int
    {
        $run = $this->runs->findRun($jobRunId);
        if ($run === null) {
            throw new \RuntimeException('Job Run not found.');
        }
        $subject = sprintf('Luna Job Run #%d: %s', $jobRunId, $run['status']);
        $body = implode("\n", [
            'Luna V3 Job Report',
            'Job: ' . (string) ($run['job_name'] ?? '-'),
            'Mapping: ' . (string) ($run['mapping_name'] ?? '-'),
            'Dry Run: ' . ((int) $run['dry_run'] === 1 ? 'ja' : 'nein'),
            'Status: ' . (string) $run['status'],
            'Source Rows: ' . (string) $run['source_count'],
            'Transformed: ' . (string) $run['transformed_count'],
            'Written: ' . (string) $run['written_count'],
            'Skipped: ' . (string) $run['skipped_count'],
            'Errors: ' . (string) $run['error_count'],
        ]);
        $id = $this->reports->create([
            'job_run_id' => $jobRunId,
            'workspace_id' => $run['workspace_id'] ?? null,
            'subject' => $subject,
            'body' => $body,
            'recipients' => $run['report_recipients'] ?? null,
        ]);
        $this->audit->log($run['workspace_id'] ?? null, 'report.created', 'report', (string) $id, 'Report erzeugt.');
        return $id;
    }
}
