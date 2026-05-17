<?php

declare(strict_types=1);

namespace Luna\Reports;

use Luna\Config\Config;
use Luna\Repository\AuditLogRepository;
use Luna\Repository\ReportRepository;

final class ReportMailer
{
    public function __construct(
        private readonly ReportRepository $reports,
        private readonly Config $config,
        private readonly AuditLogRepository $audit,
    ) {}

    public function send(int $reportId): ReportResult
    {
        $report = $this->reports->find($reportId);
        if ($report === null) {
            throw new \RuntimeException('Report not found.');
        }
        $recipients = trim((string) ($report['recipients'] ?? ''));
        if ($recipients === '') {
            $this->reports->markSkipped($reportId, 'No recipients configured.');
            $this->audit->log($report['workspace_id'] ?? null, 'report.mail_skipped', 'report', (string) $reportId, 'Mailversand übersprungen.');
            return new ReportResult($reportId, 'mail_skipped', 'Keine Empfänger konfiguriert.');
        }
        $headers = sprintf('From: %s <%s>', $this->config->string('MAIL_FROM_NAME', 'Luna V3'), $this->config->string('MAIL_FROM_ADDRESS'));
        if (@mail($recipients, (string) $report['subject'], (string) $report['body'], $headers)) {
            $this->reports->markSent($reportId);
            $this->audit->log($report['workspace_id'] ?? null, 'report.mail_sent', 'report', (string) $reportId, 'Report-Mail versendet.');
            return new ReportResult($reportId, 'sent', 'Report-Mail versendet.');
        }
        $this->reports->markFailed($reportId, 'Mail transport unavailable.');
        $this->audit->log($report['workspace_id'] ?? null, 'report.mail_failed', 'report', (string) $reportId, 'Report-Mail fehlgeschlagen.');
        return new ReportResult($reportId, 'mail_failed', 'Mailversand nicht verfügbar.');
    }
}
