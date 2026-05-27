<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class LookupResultMode
{
    private const MODES = ['first', 'list', 'count', 'sum', 'min', 'max'];

    public static function normalize(?string $mode): string
    {
        $mode = (string) $mode;

        return in_array($mode, self::MODES, true) ? $mode : 'first';
    }

    public static function isValid(string $mode): bool
    {
        return in_array($mode, self::MODES, true);
    }

    /**
     * @param list<mixed> $values
     */
    public static function reduce(array $values, string $mode): LookupResult
    {
        $mode = self::normalize($mode);
        $matchCount = count($values);

        if ($matchCount === 0) {
            return LookupResult::error('lookup_key_not_found');
        }

        return match ($mode) {
            'list' => LookupResult::found($values, $matchCount, $values),
            'count' => LookupResult::found($matchCount, $matchCount, $values),
            'sum' => self::numericResult($values, $matchCount, 'sum'),
            'min' => self::numericResult($values, $matchCount, 'min'),
            'max' => self::numericResult($values, $matchCount, 'max'),
            default => LookupResult::found($values[0], $matchCount, $values),
        };
    }

    /**
     * @param list<mixed> $values
     */
    private static function numericResult(array $values, int $matchCount, string $mode): LookupResult
    {
        $numbers = [];

        foreach ($values as $value) {
            if (! is_numeric($value)) {
                return LookupResult::error('non_numeric_lookup_value', $matchCount, $values);
            }

            $numbers[] = (float) $value;
        }

        $result = match ($mode) {
            'min' => min($numbers),
            'max' => max($numbers),
            default => array_sum($numbers),
        };

        return LookupResult::found($result, $matchCount, $values);
    }
}
