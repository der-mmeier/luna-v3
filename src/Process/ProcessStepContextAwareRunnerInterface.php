<?php

declare(strict_types=1);

namespace Luna\Process;

interface ProcessStepContextAwareRunnerInterface extends ProcessStepRunnerInterface
{
    /**
     * @param array<string, mixed> $process
     * @param array<string, mixed> $step
     * @param array<string, mixed> $context
     */
    public function runWithContext(array $process, array $step, int $processRunId, string $mode, array $context): ProcessStepResult;
}
