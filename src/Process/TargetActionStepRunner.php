<?php

declare(strict_types=1);

namespace Luna\Process;

use Luna\Repository\ProcessRunRepository;
use Luna\Repository\TargetActionRepository;
use Luna\TargetAction\TargetActionExecutor;
use RuntimeException;
use Throwable;

final class TargetActionStepRunner implements ProcessStepContextAwareRunnerInterface
{
    public function __construct(
        private readonly TargetActionRepository $targetActions,
        private readonly ProcessRunRepository $runs,
        private readonly TargetActionExecutor $executor,
    ) {
    }

    public function supports(string $stepType): bool
    {
        return $stepType === 'target_action';
    }

    public function run(array $process, array $step, int $processRunId, string $mode): ProcessStepResult
    {
        return $this->runWithContext($process, $step, $processRunId, $mode, []);
    }

    public function runWithContext(array $process, array $step, int $processRunId, string $mode, array $context): ProcessStepResult
    {
        $targetActionId = (int) ($step['reference_id'] ?? 0);
        if ($targetActionId <= 0) {
            return ProcessStepResult::failure('Target-Action-Step hat keine gültige Action-Referenz.');
        }

        $action = $this->targetActions->find($targetActionId);
        if ($action === null) {
            return ProcessStepResult::failure('Target Action wurde nicht gefunden.');
        }

        $started = microtime(true);
        $this->runs->addLog($processRunId, 'info', 'Target Action gestartet.', [
            'step_id' => (int) $step['id'],
            'target_action_id' => $targetActionId,
            'type' => (string) ($action['action_type'] ?? ''),
            'dry_run' => $mode === 'dry_run',
        ]);

        try {
            $result = $this->executor->execute($action, $step, $processRunId, $mode, $context);
            $summary = [
                'process_step_id' => (int) $step['id'],
                'target_action_id' => $targetActionId,
                'type' => (string) ($action['action_type'] ?? ''),
                'status' => (string) ($result['status'] ?? 'success'),
                'duration_ms' => $this->durationMs($started),
                'dry_run' => $mode === 'dry_run',
                'request_summary' => is_array($result['request'] ?? null) ? $result['request'] : [],
                'response_summary' => is_array($result['response'] ?? null) ? $result['response'] : [],
                'result' => is_array($result['result'] ?? null) ? $result['result'] : [],
            ];
            $this->runs->addLog($processRunId, 'info', (string) ($result['message'] ?? 'Target Action beendet.'), $summary);

            return ProcessStepResult::success((string) ($result['message'] ?? 'Target Action erfolgreich beendet.'), $summary);
        } catch (Throwable $exception) {
            $summary = [
                'process_step_id' => (int) $step['id'],
                'target_action_id' => $targetActionId,
                'type' => (string) ($action['action_type'] ?? ''),
                'status' => 'failed',
                'duration_ms' => $this->durationMs($started),
                'dry_run' => $mode === 'dry_run',
                'error_message' => $exception->getMessage(),
            ];
            $this->runs->addLog($processRunId, 'error', 'Target Action fehlgeschlagen.', $summary);

            return ProcessStepResult::failure($exception instanceof RuntimeException ? $exception->getMessage() : 'Target Action fehlgeschlagen.', $summary);
        }
    }

    private function durationMs(float $started): int
    {
        return max(0, (int) round((microtime(true) - $started) * 1000));
    }
}
