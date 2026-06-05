<?php

declare(strict_types=1);

namespace Luna\Export;

use Luna\Repository\EndpointRepository;

final class EndpointSchemaBuilder
{
    /**
     * @param array<string, mixed> $endpoint
     * @param list<array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    public function build(array $endpoint, array $fields): array
    {
        $properties = [];
        $required = [];

        foreach ($fields as $field) {
            $targetField = trim((string) ($field['target_column'] ?? ''));
            if ($targetField === '') {
                continue;
            }

            $properties[$targetField] = $this->schemaForField($field);
            if (! empty($field['schema_required'])) {
                $required[] = $targetField;
            }
        }

        $itemSchema = [
            'type' => 'object',
            'properties' => $properties,
        ];
        if ($required !== []) {
            $itemSchema['required'] = array_values(array_unique($required));
        }

        return [
            'schema_key' => 'endpoint.' . EndpointRepository::normalizeEndpointKey((string) ($endpoint['endpoint_key'] ?? 'endpoint')) . '.v1',
            'type' => 'object',
            'required' => ['success', 'generated_at', 'count', 'items'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'generated_at' => ['type' => 'string', 'format' => 'date-time'],
                'count' => ['type' => 'integer'],
                'items' => [
                    'type' => 'array',
                    'items' => $itemSchema,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function schemaForField(array $field): array
    {
        $schemaType = strtolower(trim((string) ($field['schema_type'] ?? 'auto'))) ?: 'auto';
        if ($schemaType !== 'auto') {
            return $this->schemaForExplicitType($schemaType, $field);
        }

        $target = strtolower((string) ($field['target_column'] ?? ''));
        $transform = (string) ($field['transform_type'] ?? '');
        $lookupMode = (string) ($field['lookup_result_mode'] ?? '');

        if (in_array($transform, ['key_value_map_by_prefix'], true) || $lookupMode === 'key_value_map' || in_array($target, ['dr_quantities', 'hr_quantities'], true)) {
            return [
                'type' => 'object',
                'additionalProperties' => ['type' => 'integer'],
            ];
        }

        if (str_contains($target, 'count') || str_contains($target, 'quantity') || str_ends_with($target, '_qty')) {
            return ['type' => 'integer'];
        }

        if (str_contains($target, 'price') || str_contains($target, 'amount') || str_contains($target, 'total')) {
            return ['type' => 'number'];
        }

        return ['type' => 'string'];
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function schemaForExplicitType(string $schemaType, array $field): array
    {
        $allowed = ['string', 'integer', 'number', 'boolean', 'object', 'array', 'null'];
        $description = trim((string) ($field['schema_description'] ?? ''));
        $example = trim((string) ($field['schema_example'] ?? ''));

        if ($schemaType === 'mixed') {
            $schema = ['type' => ['string', 'integer', 'number', 'boolean', 'object', 'array', 'null']];
        } elseif (in_array($schemaType, $allowed, true)) {
            $schema = ['type' => $schemaType];
            if ($schemaType === 'object' && in_array((string) ($field['target_column'] ?? ''), ['dr_quantities', 'hr_quantities'], true)) {
                $schema['additionalProperties'] = ['type' => 'integer'];
            }
        } else {
            $schema = ['type' => 'string'];
        }

        if ($description !== '') {
            $schema['description'] = $description;
        }
        if ($example !== '') {
            $schema['examples'] = [$example];
        }

        return $schema;
    }
}
