<?php

declare(strict_types=1);

namespace Luna\Process;

interface ProcessStepRunnerInterface
{
    public function supports(string $stepType): bool;

    /**
     * @param array<string, mixed> $process
     * @param array<string, mixed> $step
     */
    public function run(array $process, array $step, int $processRunId, string $mode): ProcessStepResult;
}
