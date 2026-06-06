<?php

declare(strict_types=1);

namespace Luna\Process;

use Luna\Repository\ProcessRunRepository;
use Luna\Transfer\MappingExecutor;

final class MappingRunStepRunner implements ProcessStepRunnerInterface
{
    public function __construct(
        private readonly MappingExecutor $mappingExecutor,
        private readonly ProcessRunRepository $runs,
    ) {
    }

    public function supports(string $stepType): bool
    {
        return $stepType === 'mapping_run';
    }

    public function run(array $process, array $step, int $processRunId, string $mode): ProcessStepResult
    {
        $mappingSetId = (int) ($step['reference_id'] ?? 0);
        if ($mappingSetId <= 0) {
            return ProcessStepResult::failure('Mapping-Step hat keine gültige Mapping-Referenz.');
        }

        $dryRun = $mode === 'dry_run';
        $this->runs->addLog($processRunId, 'info', 'Mapping-Step gestartet.', [
            'step_id' => (int) $step['id'],
            'mapping_set_id' => $mappingSetId,
            'dry_run' => $dryRun,
        ]);

        $result = $this->mappingExecutor->execute($mappingSetId, $dryRun);
        foreach ($result->logs() as $log) {
            $this->runs->addLog(
                $processRunId,
                (string) ($log['level'] ?? 'info'),
                (string) ($log['message'] ?? ''),
                is_array($log['context'] ?? null) ? $log['context'] : [],
            );
        }

        $summary = $result->toSummaryArray();
        if ($result->isSuccessful()) {
            return ProcessStepResult::success('Mapping-Step erfolgreich beendet.', $summary);
        }

        $errors = $result->errors();
        $message = is_string($errors[0] ?? null) ? (string) $errors[0] : 'Mapping-Step fehlgeschlagen.';

        return ProcessStepResult::failure($message, $summary);
    }
}
