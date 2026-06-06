<?php

declare(strict_types=1);

namespace Luna\Process;

use Luna\Repository\ProcessTriggerRepository;

final class TriggerConfigValidator
{
    /**
     * @return list<string>
     */
    public function validate(array $values): array
    {
        $errors = [];
        if ((int) ($values['process_id'] ?? 0) <= 0) {
            $errors[] = 'Prozess ist erforderlich.';
        }
        if (trim((string) ($values['name'] ?? '')) === '') {
            $errors[] = 'Name ist erforderlich.';
        }
        if (! in_array((string) ($values['trigger_type'] ?? ''), ProcessTriggerRepository::TYPES, true)) {
            $errors[] = 'Trigger-Typ ist ungültig.';
        }
        $key = trim((string) ($values['trigger_key'] ?? ''));
        if ($key !== '' && preg_match('/^[a-z0-9][a-z0-9\\-_]*$/', $key) !== 1) {
            $errors[] = 'Trigger Key darf nur Kleinbuchstaben, Zahlen, Bindestriche und Unterstriche enthalten.';
        }

        $configJson = trim((string) ($values['config_json'] ?? ''));
        if ($configJson !== '') {
            $decoded = json_decode($configJson, true);
            if (! is_array($decoded) && json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Konfiguration muss gültiges JSON sein.';
            }
        }

        return $errors;
    }
}
