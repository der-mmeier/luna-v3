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
            'key_value_map_by_prefix' => $this->keyValueMapByPrefix($sourceRow, $transferRow, $field, $result),
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $sourceRows
     * @param list<array<string, mixed>> $fields
     */
    public function warmUpPrefixLookups(array $sourceRows, array $fields, MappingExecutionResult $result): void
    {
        if (! $this->lookupProvider instanceof PrefixLookupWarmupProvider) {
            return;
        }

        $requests = [];

        foreach ($fields as $field) {
            if (($field['transform_type'] ?? '') !== 'key_value_map_by_prefix') {
                continue;
            }

            $template = (string) ($field['lookup_key_template'] ?? '');
            $prefixes = [];

            foreach ($sourceRows as $sourceRow) {
                $rendered = $this->templateRenderer->render($template, $sourceRow, []);

                if (! $rendered->isValid() || $rendered->value === '') {
                    continue;
                }

                $prefixes[$rendered->value] = $rendered->value;
            }

            if ($prefixes === []) {
                continue;
            }

            $requests[] = [
                'field' => $field,
                'prefixes' => array_values($prefixes),
            ];
        }

        if ($requests === []) {
            return;
        }

        $diagnostics = $this->lookupProvider->warmUpPrefixLookups($requests);

        if ($diagnostics !== []) {
            $result->addDiagnostics($diagnostics);
            $result->addLog('info', 'Prefix-Lookups vorab geladen.', $diagnostics);
        }
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
        $rendered = $this->templateRenderer->render($template, $sourceRow, $transferRow);

        if (! $rendered->isValid()) {
            $result->addResolverError('template_placeholder_missing', $targetColumn, [
                'resolver' => 'lookup_value',
                'template' => $template,
                'lookup_connection' => (string) ($field['lookup_connection_name'] ?? $field['lookup_connection_id'] ?? ''),
                'lookup_table' => (string) ($field['lookup_table'] ?? ''),
                'lookup_key_column' => (string) ($field['lookup_key_column'] ?? ''),
                'lookup_value_column' => (string) ($field['lookup_value_column'] ?? ''),
                'status' => 'template_placeholder_missing',
                'missing_placeholders' => $rendered->missingPlaceholders,
            ]);

            if (($field['missing_behavior'] ?? 'error') === 'fallback') {
                return $this->fallbackOrNull($field, $result, $targetColumn, 'template_placeholder_missing');
            }

            return null;
        }

        $lookup = $this->lookupProvider->lookup($field, $rendered->value);

        if ($lookup->found) {
            $result->addResolverEvent('lookup_resolved', $targetColumn, [
                'resolver' => 'lookup_value',
                'template' => $template,
                'rendered_key' => $rendered->value,
                'lookup_connection' => (string) ($field['lookup_connection_name'] ?? $field['lookup_connection_id'] ?? ''),
                'lookup_table' => (string) ($field['lookup_table'] ?? ''),
                'lookup_key_column' => (string) ($field['lookup_key_column'] ?? ''),
                'lookup_value_column' => (string) ($field['lookup_value_column'] ?? ''),
                'value' => $lookup->value,
                'status' => 'found',
            ]);

            return $lookup->value;
        }

        $errorCode = $lookup->errorCode ?? 'lookup_key_not_found';

        return $this->handleMissingLookup($field, $result, $targetColumn, $errorCode, $rendered->value, $template);
    }

    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $transferRow
     * @param array<string, mixed> $field
     */
    private function keyValueMapByPrefix(array $sourceRow, array $transferRow, array $field, MappingExecutionResult $result): mixed
    {
        $targetColumn = (string) ($field['target_column'] ?? 'resolved_value');
        $template = (string) ($field['lookup_key_template'] ?? '');
        $rendered = $this->templateRenderer->render($template, $sourceRow, $transferRow);

        if (! $rendered->isValid()) {
            $result->addResolverError('template_placeholder_missing', $targetColumn, [
                'resolver' => 'key_value_map_by_prefix',
                'template' => $template,
                'missing_placeholders' => $rendered->missingPlaceholders,
                'status' => 'template_placeholder_missing',
            ]);

            return (object) [];
        }

        $lookup = $this->lookupProvider->lookupByPrefix($field, $rendered->value);

        if ($lookup->found) {
            $result->addResolverEvent('lookup_prefix_resolved', $targetColumn, [
                'resolver' => 'key_value_map_by_prefix',
                'template' => $template,
                'rendered_key' => $rendered->value,
                'lookup_connection' => (string) ($field['lookup_connection_name'] ?? $field['lookup_connection_id'] ?? ''),
                'lookup_table' => (string) ($field['lookup_table'] ?? ''),
                'lookup_key_column' => (string) ($field['lookup_key_column'] ?? ''),
                'lookup_value_column' => (string) ($field['lookup_value_column'] ?? ''),
                'value' => $lookup->value,
                'status' => 'found',
            ]);

            return $lookup->value;
        }

        $result->addResolverError($lookup->errorCode ?? 'lookup_key_not_found', $targetColumn, [
            'resolver' => 'key_value_map_by_prefix',
            'template' => $template,
            'rendered_key' => $rendered->value,
            'lookup_table' => (string) ($field['lookup_table'] ?? ''),
            'lookup_key_column' => (string) ($field['lookup_key_column'] ?? ''),
            'lookup_value_column' => (string) ($field['lookup_value_column'] ?? ''),
            'status' => $lookup->errorCode ?? 'lookup_key_not_found',
        ]);

        return (object) [];
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
