<?php

declare(strict_types=1);

namespace Luna\Transfer;

use Luna\Repository\MappingRepository;
use Luna\Mapping\MappingFieldResolver;

final class MappingRowTransformer
{
    public function __construct(
        private readonly ?MappingRepository $mappings,
        private readonly ?MappingFieldResolver $fieldResolver = null,
    ) {}

    public function transform(array $sourceRow, array $fields, MappingExecutionResult $result): array
    {
        $target = [];

        foreach ($fields as $field) {
            $targetColumn = (string) $field['target_column'];
            $type = (string) $field['transform_type'];

            if ($this->fieldResolver !== null && in_array($type, ['source_column', 'static_value', 'lookup_value', 'key_value_map_by_prefix'], true)) {
                $target[$targetColumn] = $this->fieldResolver->resolve($sourceRow, $target, $field, $result);
                continue;
            }

            $target[$targetColumn] = match ($type) {
                'direct' => $sourceRow[(string) $field['source_column']] ?? null,
                'static' => $field['default_value'],
                'enum_map' => $this->enumMap($sourceRow, $field, $result),
                'json_path' => $this->jsonPath($sourceRow, $field),
                'concat' => $this->concat($sourceRow, $field, $result),
                default => null,
            };
        }

        return $target;
    }

    /**
     * @param list<array<string, mixed>> $sourceRows
     * @param list<array<string, mixed>> $fields
     */
    public function warmUpPrefixLookups(array $sourceRows, array $fields, MappingExecutionResult $result): void
    {
        $this->fieldResolver?->warmUpPrefixLookups($sourceRows, $fields, $result);
    }

    private function enumMap(array $sourceRow, array $field, MappingExecutionResult $result): mixed
    {
        $sourceValue = (string) ($sourceRow[(string) $field['source_column']] ?? '');

        foreach (($this->mappings?->valueRulesForField((int) $field['id']) ?? []) as $rule) {
            if ((string) $rule['source_value'] === $sourceValue) {
                return $rule['target_value'];
            }
        }

        $result->addWarning(sprintf('Keine Value Rule für "%s" gefunden.', $sourceValue));
        return null;
    }

    private function jsonPath(array $sourceRow, array $field): mixed
    {
        $source = $sourceRow[(string) ($field['source_column'] ?? '')] ?? json_encode($sourceRow);
        $data = is_string($source) ? json_decode($source, true) : null;

        if (! is_array($data)) {
            return null;
        }

        $value = $data;
        foreach (explode('.', (string) $field['source_json_path']) as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return is_scalar($value) || $value === null ? $value : json_encode($value);
    }

    private function concat(array $sourceRow, array $field, MappingExecutionResult $result): string
    {
        $result->addWarning('concat wird in 0.9.0 nur minimal als Entwurf unterstützt.');
        $sourceColumn = (string) ($field['source_column'] ?? '');
        return (string) ($sourceRow[$sourceColumn] ?? '');
    }
}
