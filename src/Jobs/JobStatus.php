<?php

declare(strict_types=1);

namespace Luna\Jobs;

final class JobStatus
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const DISABLED = 'disabled';
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const PARTIAL = 'partial';
}
