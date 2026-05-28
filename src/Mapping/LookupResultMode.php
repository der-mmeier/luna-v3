<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class LookupResultMode
{
    private const MODES = ['first', 'list', 'count', 'sum', 'min', 'max', 'key_value_map'];
    private const KEY_TRANSFORMS = ['none', 'remove_prefix'];

    public static function normalize(?string $mode): string
    {
        $mode = (string) $mode;

        return in_array($mode, self::MODES, true) ? $mode : 'first';
    }

    public static function isValid(string $mode): bool
    {
        return in_array($mode, self::MODES, true);
    }

    public static function normalizeKeyTransform(?string $transform): string
    {
        $transform = (string) $transform;

        return in_array($transform, self::KEY_TRANSFORMS, true) ? $transform : 'none';
    }

    public static function isValidKeyTransform(string $transform): bool
    {
        return in_array($transform, self::KEY_TRANSFORMS, true);
    }

    /**
     * @param list<mixed> $values
     */
    public static function reduce(array $values, string $mode): LookupResult
    {
        $mode = self::normalize($mode);

        if ($mode === 'key_value_map') {
            return self::reduceRows(
                array_map(static fn (mixed $value): array => ['lookup_value' => $value], $values),
                $mode,
            );
        }

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
     * @param list<array<string, mixed>> $rows
     * @param array{key_transform?: string, rendered_prefix?: string} $options
     */
    public static function reduceRows(array $rows, string $mode, array $options = []): LookupResult
    {
        $mode = self::normalize($mode);

        if ($mode !== 'key_value_map') {
            return self::reduce(array_values(array_map(static fn (array $row): mixed => $row['lookup_value'] ?? null, $rows)), $mode);
        }

        $matchCount = count($rows);

        if ($matchCount === 0) {
            return LookupResult::error('lookup_key_not_found');
        }

        $values = [];
        $matchedValues = [];
        $warnings = [];
        $transform = self::normalizeKeyTransform($options['key_transform'] ?? null);
        $prefix = (string) ($options['rendered_prefix'] ?? '');

        foreach ($rows as $row) {
            if (! array_key_exists('result_key', $row)) {
                return LookupResult::error('missing_result_key_column', $matchCount, $matchedValues, $warnings);
            }

            $resultKey = trim((string) $row['result_key']);

            if ($resultKey === '') {
                return LookupResult::error('empty_result_key', $matchCount, $matchedValues, $warnings);
            }

            if ($transform === 'remove_prefix' && $prefix !== '') {
                if (str_starts_with($resultKey, $prefix)) {
                    $resultKey = substr($resultKey, strlen($prefix));
                } else {
                    $warnings['prefix_not_found_on_result_key'] = 'prefix_not_found_on_result_key';
                }
            }

            $lookupValue = $row['lookup_value'] ?? null;
            $matchedValues[] = ['key' => $resultKey, 'value' => $lookupValue];

            if (array_key_exists($resultKey, $values)) {
                $warnings['duplicate_result_key'] = 'duplicate_result_key';
                $values[$resultKey] = is_array($values[$resultKey]) ? array_merge($values[$resultKey], [$lookupValue]) : [$values[$resultKey], $lookupValue];
                continue;
            }

            $values[$resultKey] = $lookupValue;
        }

        return LookupResult::found($values, $matchCount, $matchedValues, array_values($warnings));
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
