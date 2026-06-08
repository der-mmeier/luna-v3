<?php

declare(strict_types=1);

namespace Luna\Process;

use Luna\Repository\ProcessRepository;
use Luna\Repository\ProcessTriggerRepository;

final class ProcessTriggerRunner
{
    public function __construct(
        private readonly ProcessTriggerRepository $triggers,
        private readonly ProcessRepository $processes,
        private readonly ProcessRunner $runner,
    ) {
    }

    public function runByIdentifier(
        string $identifier,
        string $mode = 'run',
        string $source = 'api',
        ?string $secret = null,
        array $payloadMeta = [],
        ?int $expectedProcessId = null,
        ?string $expectedType = null,
        array $initialContext = [],
    ): int {
        $trigger = $this->triggers->findByIdentifier($identifier);
        if ($trigger === null) {
            throw ProcessTriggerException::notFound();
        }

        return $this->runTrigger($trigger, $mode, $source, $secret, $payloadMeta, $expectedProcessId, $expectedType, $initialContext);
    }

    public function runTrigger(
        array $trigger,
        string $mode = 'run',
        string $source = 'api',
        ?string $secret = null,
        array $payloadMeta = [],
        ?int $expectedProcessId = null,
        ?string $expectedType = null,
        array $initialContext = [],
    ): int {
        if (empty($trigger['is_active'])) {
            throw ProcessTriggerException::inactive();
        }

        if ($expectedProcessId !== null && (int) ($trigger['process_id'] ?? 0) !== $expectedProcessId) {
            throw ProcessTriggerException::notExecutable('Trigger gehört nicht zum Prozess.');
        }

        if ($expectedType !== null && (string) ($trigger['trigger_type'] ?? '') !== $expectedType) {
            throw ProcessTriggerException::notExecutable('Trigger-Typ passt nicht zu diesem Auslöser.');
        }

        if (! $this->triggers->verifySecret($trigger, $secret)) {
            throw ProcessTriggerException::forbidden();
        }

        $processId = (int) ($trigger['process_id'] ?? 0);
        $process = $this->processes->find($processId);
        if ($process === null) {
            throw ProcessTriggerException::notExecutable('Zugehöriger Prozess wurde nicht gefunden.');
        }

        if ((string) ($process['status'] ?? '') !== 'active') {
            throw ProcessTriggerException::notExecutable('Zugehöriger Prozess ist nicht aktiv.');
        }

        $runId = $this->runner->run(
            $processId,
            $mode,
            (string) $trigger['trigger_type'],
            (string) $trigger['trigger_key'],
            $initialContext + [
                'trigger' => [
                    'id' => (int) $trigger['id'],
                    'key' => (string) $trigger['trigger_key'],
                    'type' => (string) $trigger['trigger_type'],
                    'source' => $source,
                ],
            ],
            (int) $trigger['id'],
            $source,
            $payloadMeta,
        );

        $this->triggers->markTriggered((int) $trigger['id']);

        return $runId;
    }
}
