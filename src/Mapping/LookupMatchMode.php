<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class LookupMatchMode
{
    private const MODES = ['exact', 'prefix', 'suffix', 'contains', 'like'];

    public static function normalize(?string $mode): string
    {
        $mode = (string) $mode;

        return in_array($mode, self::MODES, true) ? $mode : 'exact';
    }

    public static function isValid(string $mode): bool
    {
        return in_array($mode, self::MODES, true);
    }

    public static function sqlOperator(string $mode): string
    {
        return self::normalize($mode) === 'exact' ? '=' : 'LIKE';
    }

    public static function parameter(string $mode, string $renderedKey): string
    {
        return match (self::normalize($mode)) {
            'prefix' => $renderedKey . '%',
            'suffix' => '%' . $renderedKey,
            'contains' => '%' . $renderedKey . '%',
            default => $renderedKey,
        };
    }

    public static function hasSearchValue(string $mode, string $renderedKey): bool
    {
        $value = trim($renderedKey);

        if ($value === '' || $value === '-') {
            return false;
        }

        if (self::normalize($mode) === 'like') {
            return trim(str_replace(['%', '_'], '', $value)) !== '';
        }

        return true;
    }
}
