<?php

declare(strict_types=1);

namespace Luna\Mapping;

use Luna\Transfer\MappingExecutionResult;

final class MappingFieldResolver
{
    public function __construct(
        private readonly LookupValueProvider $lookupProvider,
        private readonly LookupKeyTemplateRenderer $templateRenderer = new LookupKeyTemplateRenderer(),
    ) {
    }

    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $transferRow
     * @param array<string, mixed> $field
     */
    public function resolve(array $sourceRow, array $transferRow, array $field, MappingExecutionResult $result): mixed
    {
        return match ((string) ($field['transform_type'] ?? 'source_column')) {
            'source_column', 'direct' => $this->sourceColumn($sourceRow, $field),
            'static_value', 'static' => $field['default_value'] ?? null,
            'lookup_value' => $this->lookupValue($sourceRow, $transferRow, $field, $result),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $field
     */
    private function sourceColumn(array $sourceRow, array $field): mixed
    {
        return $sourceRow[(string) ($field['source_column'] ?? '')] ?? null;
    }

    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $transferRow
     * @param array<string, mixed> $field
     */
    private function lookupValue(array $sourceRow, array $transferRow, array $field, MappingExecutionResult $result): mixed
    {
        $targetColumn = (string) ($field['target_column'] ?? 'lookup_value');
        $template = (string) ($field['lookup_key_template'] ?? '');
        $matchMode = LookupMatchMode::normalize(isset($field['lookup_match_mode']) ? (string) $field['lookup_match_mode'] : null);
        $resultMode = LookupResultMode::normalize(isset($field['lookup_result_mode']) ? (string) $field['lookup_result_mode'] : null);
        $resultKeyTransform = LookupResultMode::normalizeKeyTransform(isset($field['lookup_result_key_transform']) ? (string) $field['lookup_result_key_transform'] : null);
        $rendered = $this->templateRenderer->render($template, $sourceRow, $transferRow);

        if (! $rendered->isValid()) {
            $result->addResolverError('template_placeholder_missing', $targetColumn, [
                'resolver' => 'lookup_value',
                'template' => $template,
                'lookup_connection' => (string) ($field['lookup_connection_name'] ?? $field['lookup_connection_id'] ?? ''),
                'lookup_table' => (string) ($field['lookup_table'] ?? ''),
                'lookup_key_column' => (string) ($field['lookup_key_column'] ?? ''),
                'lookup_value_column' => (string) ($field['lookup_value_column'] ?? ''),
                'lookup_match_mode' => $matchMode,
                'lookup_result_mode' => $resultMode,
                'lookup_result_key_column' => (string) ($field['lookup_result_key_column'] ?? ''),
                'lookup_result_key_transform' => $resultKeyTransform,
                'status' => 'template_placeholder_missing',
                'missing_placeholders' => $rendered->missingPlaceholders,
            ]);

            if (($field['missing_behavior'] ?? 'error') === 'fallback') {
                return $this->fallbackOrNull($field, $result, $targetColumn, 'template_placeholder_missing');
            }

            return null;
        }

        if (! LookupMatchMode::hasSearchValue($matchMode, $rendered->value)) {
            return $this->handleMissingLookup($field, $result, $targetColumn, 'lookup_key_empty', $rendered->value, $template);
        }

        $field['_lookup_result_key_prefix'] = '';

        if ($resultMode === 'key_value_map' && $resultKeyTransform === 'remove_prefix') {
            $prefixTemplate = (string) ($field['lookup_result_key_prefix_template'] ?? '');
            $renderedPrefix = $this->templateRenderer->render($prefixTemplate, $sourceRow, $transferRow);

            if (! $renderedPrefix->isValid()) {
                return $this->handleMissingLookup($field, $result, $targetColumn, 'template_placeholder_missing', $rendered->value, $prefixTemplate);
            }

            $field['_lookup_result_key_prefix'] = $renderedPrefix->value;
        }

        $lookup = $this->lookupProvider->lookup($field, $rendered->value);

        if ($lookup->found) {
            $context = [
                'resolver' => 'lookup_value',
                'template' => $template,
                'rendered_key' => $rendered->value,
                'lookup_connection' => (string) ($field['lookup_connection_name'] ?? $field['lookup_connection_id'] ?? ''),
                'lookup_table' => (string) ($field['lookup_table'] ?? ''),
                'lookup_key_column' => (string) ($field['lookup_key_column'] ?? ''),
                'lookup_value_column' => (string) ($field['lookup_value_column'] ?? ''),
                'lookup_match_mode' => $matchMode,
                'lookup_result_mode' => $resultMode,
                'lookup_result_key_column' => (string) ($field['lookup_result_key_column'] ?? ''),
                'lookup_result_key_transform' => $resultKeyTransform,
                'lookup_result_key_prefix_template' => (string) ($field['lookup_result_key_prefix_template'] ?? ''),
                'rendered_result_key_prefix' => (string) ($field['_lookup_result_key_prefix'] ?? ''),
                'rendered_pattern' => LookupMatchMode::parameter($matchMode, $rendered->value),
                'match_count' => $lookup->matchCount,
                'matched_values' => array_slice($lookup->matchedValues, 0, 10),
                'result_warnings' => $lookup->warnings,
                'value' => $lookup->value,
                'status' => 'found',
            ];
            $result->addResolverEvent('lookup_resolved', $targetColumn, $context);

            foreach ($lookup->warnings as $warning) {
                $result->addResolverWarning((string) $warning, $targetColumn, $context);
            }

            return $lookup->value;
        }

        $errorCode = $lookup->errorCode ?? 'lookup_key_not_found';

        return $this->handleMissingLookup($field, $result, $targetColumn, $errorCode, $rendered->value, $template);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function handleMissingLookup(array $field, MappingExecutionResult $result, string $targetColumn, string $errorCode, string $lookupKey, string $template): mixed
    {
        $behavior = (string) ($field['missing_behavior'] ?? 'error');
        $context = $this->lookupContext($field, $template, $lookupKey, $errorCode);

        if ($behavior === 'fallback') {
            return $this->fallbackOrNull($field, $result, $targetColumn, $errorCode, $lookupKey);
        }

        if ($behavior === 'nullable') {
            $result->addResolverWarning($errorCode, $targetColumn, $context);

            return null;
        }

        if ($behavior === 'warning') {
            $result->addResolverWarning($errorCode, $targetColumn, $context);

            return null;
        }

        $result->addResolverError($errorCode, $targetColumn, $context);

        return null;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function lookupContext(array $field, string $template, string $lookupKey, string $status): array
    {
        return [
            'resolver' => 'lookup_value',
            'template' => $template,
            'rendered_key' => $lookupKey,
            'lookup_connection' => (string) ($field['lookup_connection_name'] ?? $field['lookup_connection_id'] ?? ''),
            'lookup_table' => (string) ($field['lookup_table'] ?? ''),
            'lookup_key_column' => (string) ($field['lookup_key_column'] ?? ''),
            'lookup_value_column' => (string) ($field['lookup_value_column'] ?? ''),
            'lookup_match_mode' => LookupMatchMode::normalize(isset($field['lookup_match_mode']) ? (string) $field['lookup_match_mode'] : null),
            'lookup_result_mode' => LookupResultMode::normalize(isset($field['lookup_result_mode']) ? (string) $field['lookup_result_mode'] : null),
            'lookup_result_key_column' => (string) ($field['lookup_result_key_column'] ?? ''),
            'lookup_result_key_transform' => LookupResultMode::normalizeKeyTransform(isset($field['lookup_result_key_transform']) ? (string) $field['lookup_result_key_transform'] : null),
            'lookup_result_key_prefix_template' => (string) ($field['lookup_result_key_prefix_template'] ?? ''),
            'rendered_result_key_prefix' => (string) ($field['_lookup_result_key_prefix'] ?? ''),
            'rendered_pattern' => LookupMatchMode::parameter(LookupMatchMode::normalize(isset($field['lookup_match_mode']) ? (string) $field['lookup_match_mode'] : null), $lookupKey),
            'value' => null,
            'status' => $status,
        ];
    }

    /**
     * @param array<string, mixed> $field
     */
    private function fallbackOrNull(array $field, MappingExecutionResult $result, string $targetColumn, string $reason, ?string $lookupKey = null): mixed
    {
        $context = ['reason' => $reason];

        if ($lookupKey !== null) {
            $context['lookup_key'] = $lookupKey;
        }

        if (! array_key_exists('fallback_value', $field) || $field['fallback_value'] === null || (string) $field['fallback_value'] === '') {
            $result->addResolverError('invalid_fallback_value', $targetColumn, $context);

            return null;
        }

        $result->addResolverWarning('fallback_used', $targetColumn, $context);

        return $field['fallback_value'];
    }
}
