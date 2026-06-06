<?php

declare(strict_types=1);

namespace Luna\Process;

final class ProcessStepResult
{
    /**
     * @param array<string, mixed> $summary
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $summary = [],
    ) {
    }

    /**
     * @param array<string, mixed> $summary
     */
    public static function success(string $message, array $summary = []): self
    {
        return new self(true, $message, $summary);
    }

    /**
     * @param array<string, mixed> $summary
     */
    public static function failure(string $message, array $summary = []): self
    {
        return new self(false, $message, $summary);
    }
}
