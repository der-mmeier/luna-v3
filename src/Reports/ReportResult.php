<?php

declare(strict_types=1);

namespace Luna\Reports;

final class ReportResult
{
    public function __construct(
        public readonly int $reportId,
        public readonly string $status,
        public readonly string $message,
    ) {}
}
