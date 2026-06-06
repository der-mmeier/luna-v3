<?php

declare(strict_types=1);

namespace Luna\Process;

use Luna\Repository\ProcessRepository;
use Luna\Repository\ProcessRunRepository;
use RuntimeException;
use Throwable;

final class ProcessRunner
{
    /**
     * @param list<ProcessStepRunnerInterface> $stepRunners
     */
    public function __construct(
        private readonly ProcessRepository $processes,
        private readonly ProcessRunRepository $runs,
        private readonly array $stepRunners,
    ) {
    }

    public function run(int $processId, string $mode = 'run', string $triggerType = 'manual', ?string $triggerRef = null): int
    {
        $process = $this->processes->find($processId);
        if ($process === null) {
            throw new RuntimeException('Process not found.');
        }

        if ((string) ($process['status'] ?? '') !== 'active') {
            throw new RuntimeException('Process is not active.');
        }

        $mode = in_array($mode, ['run', 'dry_run'], true) ? $mode : 'run';
        $triggerType = in_array($triggerType, ['manual', 'cli', 'api', 'schedule', 'webhook'], true) ? $triggerType : 'manual';
        $runId = $this->runs->createRun($processId, $mode, $triggerType, $triggerRef);
        $started = microtime(true);
        $this->runs->markRunning($runId);
        $this->runs->addLog($runId, 'info', 'Prozesslauf gestartet.', [
            'process_id' => $processId,
            'mode' => $mode,
            'trigger_type' => $triggerType,
        ]);

        $context = [
            'steps_total' => 0,
            'steps_executed' => 0,
            'steps_skipped' => 0,
        ];

        try {
            $steps = $this->processes->stepsForProcess($processId);
            $enabledSteps = array_values(array_filter($steps, static fn (array $step): bool => ! empty($step['is_enabled'])));
            $context['steps_total'] = count($enabledSteps);

            if ($enabledSteps === []) {
                throw new RuntimeException('Process has no enabled steps.');
            }

            foreach ($enabledSteps as $step) {
                $runner = $this->runnerFor((string) ($step['step_type'] ?? ''));
                if ($runner === null) {
                    $message = 'Kein Step Runner für diesen Step-Typ vorhanden.';
                    $this->runs->addLog($runId, 'error', $message, ['step_type' => (string) ($step['step_type'] ?? '')]);
                    if (empty($step['continue_on_error'])) {
                        throw new RuntimeException($message);
                    }
                    $context['steps_skipped']++;
                    continue;
                }

                $this->runs->addLog($runId, 'info', 'Step wird ausgeführt.', [
                    'step_id' => (int) $step['id'],
                    'step_type' => (string) $step['step_type'],
                    'position' => (int) ($step['position'] ?? 0),
                ]);
                $result = $runner->run($process, $step, $runId, $mode);
                $context['steps_executed']++;

                if (! $result->success) {
                    $this->runs->addLog($runId, 'error', $result->message, [
                        'step_id' => (int) $step['id'],
                        'summary' => $result->summary,
                    ]);
                    if (empty($step['continue_on_error'])) {
                        throw new RuntimeException($result->message);
                    }
                    continue;
                }

                $this->runs->addLog($runId, 'info', $result->message, [
                    'step_id' => (int) $step['id'],
                    'summary' => $result->summary,
                ]);
            }

            $this->runs->markSuccess($runId, $this->durationMs($started), $context);
            $this->runs->addLog($runId, 'info', 'Prozesslauf erfolgreich beendet.', $context);
        } catch (Throwable $exception) {
            $this->runs->markFailed($runId, $exception->getMessage(), $this->durationMs($started), $context);
            $this->runs->addLog($runId, 'error', 'Prozesslauf fehlgeschlagen.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return $runId;
    }

    private function runnerFor(string $stepType): ?ProcessStepRunnerInterface
    {
        foreach ($this->stepRunners as $runner) {
            if ($runner->supports($stepType)) {
                return $runner;
            }
        }

        return null;
    }

    private function durationMs(float $started): int
    {
        return max(0, (int) round((microtime(true) - $started) * 1000));
    }
}
