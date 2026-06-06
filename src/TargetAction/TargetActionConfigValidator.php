<?php

declare(strict_types=1);

namespace Luna\TargetAction;

use Luna\Repository\TargetActionRepository;

final class TargetActionConfigValidator
{
    /**
     * @return list<string>
     */
    public function validate(array $values): array
    {
        $errors = [];
        if ((int) ($values['workspace_id'] ?? 0) <= 0) {
            $errors[] = 'Workspace ist erforderlich.';
        }
        if (trim((string) ($values['name'] ?? '')) === '') {
            $errors[] = 'Name ist erforderlich.';
        }
        if (! in_array((string) ($values['action_type'] ?? ''), TargetActionRepository::ALL_TYPES, true)) {
            $errors[] = 'Action-Typ ist ungültig.';
        }

        $key = trim((string) ($values['action_key'] ?? ''));
        if ($key !== '' && preg_match('/^[a-z0-9][a-z0-9_\\-]*$/', $key) !== 1) {
            $errors[] = 'Key darf nur Kleinbuchstaben, Zahlen, Bindestriche und Unterstriche enthalten.';
        }

        $configJson = trim((string) ($values['config_json'] ?? ''));
        if ($configJson !== '') {
            $decoded = json_decode($configJson, true);
            if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Konfiguration muss gültiges JSON-Objekt sein.';
            }
        }

        return $errors;
    }
}
