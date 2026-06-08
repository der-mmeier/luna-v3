<?php

declare(strict_types=1);

namespace Luna\Process;

use Luna\Repository\ProcessRunRepository;
use Luna\Repository\SchemaRegistryRepository;
use Luna\Schema\SchemaValidator;

final class SchemaValidationStepRunner implements ProcessStepContextAwareRunnerInterface
{
    public function __construct(
        private readonly SchemaRegistryRepository $schemas,
        private readonly ProcessRunRepository $runs,
        private readonly SchemaValidator $validator,
    ) {
    }

    public function supports(string $stepType): bool
    {
        return $stepType === 'schema_validation';
    }

    public function run(array $process, array $step, int $processRunId, string $mode): ProcessStepResult
    {
        return $this->runWithContext($process, $step, $processRunId, $mode, []);
    }

    public function runWithContext(array $process, array $step, int $processRunId, string $mode, array $context): ProcessStepResult
    {
        $schemaId = (int) ($step['reference_id'] ?? 0);
        if ($schemaId <= 0) {
            return ProcessStepResult::failure('Schema-Validation-Step hat keine gültige Schema-Referenz.');
        }

        $schema = $this->schemas->find($schemaId);
        if ($schema === null) {
            return ProcessStepResult::failure('Schema wurde nicht gefunden.');
        }

        $definition = json_decode((string) ($schema['definition_json'] ?? ''), true);
        if (! is_array($definition)) {
            return ProcessStepResult::failure('Schema-Definition ist ungültig.');
        }

        $data = $context['previous_result'] ?? $context;
        $result = $this->validator->validate($data, $definition);
        $summary = [
            'schema_id' => $schemaId,
            'schema_key' => (string) ($schema['schema_key'] ?? ''),
            'version' => (string) ($schema['version'] ?? ''),
            'valid' => (bool) $result['valid'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
            'result' => $data,
        ];

        $this->runs->addLog($processRunId, $result['valid'] ? 'info' : 'error', 'Schema-Validierung ausgeführt.', [
            'step_id' => (int) $step['id'],
            'schema_id' => $schemaId,
            'valid' => (bool) $result['valid'],
            'error_count' => count($result['errors']),
            'warning_count' => count($result['warnings']),
            'errors' => $result['errors'],
        ]);

        if ($result['valid']) {
            return ProcessStepResult::success('Schema-Validierung erfolgreich.', $summary);
        }

        return ProcessStepResult::failure('Schema-Validierung fehlgeschlagen.', $summary);
    }
}
