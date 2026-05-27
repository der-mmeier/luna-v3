<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class LookupKeyTemplateRenderer
{
    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $transferRow
     */
    public function render(string $template, array $sourceRow, array $transferRow): TemplateRenderResult
    {
        $missing = [];
        $value = preg_replace_callback('/{{\s*([A-Za-z0-9_]+)\s*}}/', static function (array $matches) use ($sourceRow, $transferRow, &$missing): string {
            $key = (string) $matches[1];

            if (array_key_exists($key, $transferRow)) {
                return self::stringValue($transferRow[$key]);
            }

            if (array_key_exists($key, $sourceRow)) {
                return self::stringValue($sourceRow[$key]);
            }

            $missing[] = $key;

            return '';
        }, $template);

        return new TemplateRenderResult($value ?? '', array_values(array_unique($missing)));
    }

    private static function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
