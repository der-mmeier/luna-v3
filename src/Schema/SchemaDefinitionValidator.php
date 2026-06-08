<?php

declare(strict_types=1);

namespace Luna\Schema;

use Luna\Repository\SchemaRegistryRepository;

final class SchemaDefinitionValidator
{
    public const TYPES = ['string', 'integer', 'number', 'boolean', 'object', 'array', 'null', 'mixed'];

    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    public function validateForm(array $values, SchemaRegistryRepository $schemas, ?int $ignoreId = null): array
    {
        $errors = [];
        $workspaceId = (int) ($values['workspace_id'] ?? 0);
        $schemaKey = SchemaRegistryRepository::normalizeKey((string) ($values['schema_key'] ?? ''));
        $version = trim((string) ($values['version'] ?? ''));

        if ($workspaceId <= 0) {
            $errors[] = 'Workspace ist erforderlich.';
        }
        if (trim((string) ($values['name'] ?? '')) === '') {
            $errors[] = 'Name ist erforderlich.';
        }
        if ($schemaKey === '') {
            $errors[] = 'Schema Key ist erforderlich.';
        }
        if ($version === '') {
            $errors[] = 'Version ist erforderlich.';
        }
        if (! in_array((string) ($values['status'] ?? 'draft'), SchemaRegistryRepository::STATUSES, true)) {
            $errors[] = 'Status ist ungültig.';
        }
        if ($workspaceId > 0 && $schemaKey !== '' && $version !== '' && $schemas->existsVersion($workspaceId, $schemaKey, $version, $ignoreId)) {
            $errors[] = 'Diese Schema-Version existiert in diesem Workspace bereits.';
        }

        $definition = $this->decodeJson((string) ($values['definition_json'] ?? ''), 'Definition JSON', $errors, true);
        if (is_array($definition)) {
            $errors = array_merge($errors, $this->validateDefinition($definition));
        }

        $example = trim((string) ($values['example_json'] ?? ''));
        if ($example !== '') {
            $this->decodeJson($example, 'Example JSON', $errors, false);
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<string>
     */
    public function validateDefinition(array $definition): array
    {
        $errors = [];
        $this->validateNode($definition, 'root', $errors);
        $this->scanForSecretValues($definition, 'definition', $errors);

        return $errors;
    }

    /**
     * @param list<string> $errors
     * @return array<string, mixed>|list<mixed>|null
     */
    private function decodeJson(string $json, string $label, array &$errors, bool $mustBeObject): ?array
    {
        $json = trim($json);
        if ($json === '') {
            $errors[] = $label . ' ist erforderlich.';

            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $errors[] = $label . ' ist ungültig: ' . $exception->getMessage();

            return null;
        }

        if (! is_array($decoded)) {
            $errors[] = $label . ' muss ein JSON-Objekt oder Array sein.';

            return null;
        }
        if ($mustBeObject && ! $this->isAssociative($decoded)) {
            $errors[] = $label . ' muss ein JSON-Objekt sein.';

            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $errors
     */
    private function validateNode(array $node, string $path, array &$errors): void
    {
        $type = $node['type'] ?? null;
        if (is_array($type)) {
            foreach ($type as $singleType) {
                if (! is_string($singleType) || ! in_array($singleType, self::TYPES, true)) {
                    $errors[] = $path . ': unbekannter Typ.';
                }
            }
        } elseif (! is_string($type) || ! in_array($type, self::TYPES, true)) {
            $errors[] = $path . ': type muss einer der unterstützten Typen sein.';
        }

        $normalizedType = is_string($type) ? $type : null;
        if ($normalizedType === 'object' || isset($node['fields']) || isset($node['properties'])) {
            $fields = $node['fields'] ?? $node['properties'] ?? [];
            if ($fields !== [] && ! is_array($fields)) {
                $errors[] = $path . ': fields/properties muss ein Objekt sein.';
            } elseif (is_array($fields)) {
                foreach ($fields as $fieldName => $fieldDefinition) {
                    if (! is_string($fieldName) || $fieldName === '') {
                        $errors[] = $path . ': Feldname ist ungültig.';
                        continue;
                    }
                    if (! is_array($fieldDefinition)) {
                        $errors[] = $path . '.' . $fieldName . ': Felddefinition muss ein Objekt sein.';
                        continue;
                    }
                    $this->validateNode($fieldDefinition, $path . '.' . $fieldName, $errors);
                }
            }

            $additional = $node['additional_properties'] ?? $node['additionalProperties'] ?? null;
            if ($additional !== null && ! is_array($additional) && ! is_bool($additional)) {
                $errors[] = $path . ': additional_properties muss ein Objekt oder boolean sein.';
            } elseif (is_array($additional)) {
                $this->validateNode($additional, $path . '.*', $errors);
            }
        }

        if ($normalizedType === 'array' || isset($node['items'])) {
            if (! isset($node['items']) || ! is_array($node['items'])) {
                $errors[] = $path . ': array benötigt ein items-Schema.';
            } else {
                $this->validateNode($node['items'], $path . '[]', $errors);
            }
        }
    }

    /**
     * @param array<string, mixed>|list<mixed> $value
     * @param list<string> $errors
     */
    private function scanForSecretValues(array $value, string $path, array &$errors): void
    {
        foreach ($value as $key => $item) {
            $childPath = $path . '.' . (string) $key;
            if (is_array($item)) {
                $this->scanForSecretValues($item, $childPath, $errors);
                continue;
            }
            if (! is_string($item) || $item === '') {
                continue;
            }
            if (preg_match('/(Bearer\\s+[A-Za-z0-9._-]+|DB_PASSWORD|LUNA_APP_KEY|Authorization\\s*:|api_key=|token=|password=|secret=)/i', $item) === 1) {
                $errors[] = $childPath . ': Schema darf keine Secrets oder Credential-Beispiele enthalten.';
            }
        }
    }

    /**
     * @param array<mixed> $value
     */
    private function isAssociative(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
