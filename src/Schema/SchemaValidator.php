<?php

declare(strict_types=1);

namespace Luna\Schema;

final class SchemaValidator
{
    /**
     * @param array<string, mixed> $schema
     * @return array{valid: bool, errors: list<array<string, mixed>>, warnings: list<array<string, mixed>>}
     */
    public function validate(mixed $data, array $schema): array
    {
        $errors = [];
        $warnings = [];
        $this->validateNode($data, $schema, '$', $errors, $warnings);

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @param list<array<string, mixed>> $errors
     * @param list<array<string, mixed>> $warnings
     */
    private function validateNode(mixed $data, array $schema, string $path, array &$errors, array &$warnings): void
    {
        $expectedTypes = $this->expectedTypes($schema['type'] ?? 'mixed');
        if ($expectedTypes === ['mixed']) {
            return;
        }

        $actualType = $this->actualType($data);
        if ($actualType === 'integer' && in_array('number', $expectedTypes, true)) {
            $actualType = 'number';
        }
        if (! in_array($actualType, $expectedTypes, true)) {
            $errors[] = [
                'path' => $path,
                'message' => 'Expected ' . implode('|', $expectedTypes) . ', got ' . $actualType,
                'expected' => implode('|', $expectedTypes),
                'actual' => $actualType,
            ];

            return;
        }

        if ($actualType === 'object') {
            $this->validateObject(is_array($data) ? $data : [], $schema, $path, $errors, $warnings);
        }
        if ($actualType === 'array') {
            $this->validateArray(is_array($data) ? $data : [], $schema, $path, $errors, $warnings);
        }
        if ($actualType === 'string') {
            $this->validateFormat((string) $data, $schema, $path, $warnings);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $schema
     * @param list<array<string, mixed>> $errors
     * @param list<array<string, mixed>> $warnings
     */
    private function validateObject(array $data, array $schema, string $path, array &$errors, array &$warnings): void
    {
        $fields = $schema['fields'] ?? $schema['properties'] ?? [];
        if (! is_array($fields)) {
            return;
        }

        $required = $this->requiredFields($schema, $fields);
        foreach ($required as $fieldName) {
            if (! array_key_exists($fieldName, $data)) {
                $errors[] = [
                    'path' => $this->fieldPath($path, $fieldName),
                    'message' => 'Required field is missing',
                ];
            }
        }

        foreach ($fields as $fieldName => $fieldSchema) {
            if (! is_string($fieldName) || ! is_array($fieldSchema) || ! array_key_exists($fieldName, $data)) {
                continue;
            }
            $this->validateNode($data[$fieldName], $fieldSchema, $this->fieldPath($path, $fieldName), $errors, $warnings);
        }

        $additional = $schema['additional_properties'] ?? $schema['additionalProperties'] ?? null;
        if (is_array($additional)) {
            foreach ($data as $fieldName => $fieldValue) {
                if (is_string($fieldName) && array_key_exists($fieldName, $fields)) {
                    continue;
                }
                $this->validateNode($fieldValue, $additional, $this->fieldPath($path, (string) $fieldName), $errors, $warnings);
            }
        } elseif ($additional === false) {
            foreach (array_keys($data) as $fieldName) {
                if (is_string($fieldName) && ! array_key_exists($fieldName, $fields)) {
                    $errors[] = [
                        'path' => $this->fieldPath($path, $fieldName),
                        'message' => 'Additional property is not allowed',
                    ];
                }
            }
        }
    }

    /**
     * @param list<mixed> $data
     * @param array<string, mixed> $schema
     * @param list<array<string, mixed>> $errors
     * @param list<array<string, mixed>> $warnings
     */
    private function validateArray(array $data, array $schema, string $path, array &$errors, array &$warnings): void
    {
        $itemSchema = $schema['items'] ?? null;
        if (! is_array($itemSchema)) {
            return;
        }

        foreach ($data as $index => $item) {
            $this->validateNode($item, $itemSchema, $path . '[' . (int) $index . ']', $errors, $warnings);
        }
    }

    /**
     * @param list<array<string, mixed>> $warnings
     */
    private function validateFormat(string $value, array $schema, string $path, array &$warnings): void
    {
        $format = (string) ($schema['format'] ?? '');
        if ($format === '') {
            return;
        }

        $valid = match ($format) {
            'datetime', 'date-time' => strtotime($value) !== false,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => true,
        };

        if (! $valid) {
            $warnings[] = [
                'path' => $path,
                'message' => 'Format check failed: ' . $format,
                'format' => $format,
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function expectedTypes(mixed $type): array
    {
        if (is_array($type)) {
            return array_values(array_filter(array_map(
                static fn (mixed $entry): ?string => is_string($entry) ? self::normalizeType($entry) : null,
                $type,
            )));
        }

        return [self::normalizeType(is_string($type) ? $type : 'mixed')];
    }

    private static function normalizeType(string $type): string
    {
        return match ($type) {
            'bool' => 'boolean',
            'int' => 'integer',
            'float', 'double' => 'number',
            default => $type,
        };
    }

    private function actualType(mixed $data): string
    {
        if ($data === null) {
            return 'null';
        }
        if (is_bool($data)) {
            return 'boolean';
        }
        if (is_int($data)) {
            return 'integer';
        }
        if (is_float($data)) {
            return 'number';
        }
        if (is_string($data)) {
            return 'string';
        }
        if (is_array($data)) {
            return $this->isList($data) ? 'array' : 'object';
        }

        return get_debug_type($data);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $fields
     * @return list<string>
     */
    private function requiredFields(array $schema, array $fields): array
    {
        if (array_key_exists('required', $schema) && is_array($schema['required'])) {
            $required = $schema['required'];

            return array_values(array_filter($required, 'is_string'));
        }

        $fieldNames = [];
        foreach ($fields as $fieldName => $fieldSchema) {
            if (is_string($fieldName) && is_array($fieldSchema) && ! empty($fieldSchema['required'])) {
                $fieldNames[] = $fieldName;
            }
        }

        return $fieldNames;
    }

    private function fieldPath(string $path, string $field): string
    {
        return $path === '$' ? $field : $path . '.' . $field;
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
