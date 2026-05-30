<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class TransformType
{
    private const LABELS = [
        'direct' => 'Direkt',
        'static' => 'Statischer Wert',
        'source_column' => 'Source Column',
        'static_value' => 'Static Value',
        'lookup_value' => 'Lookup Value',
        'key_value_map_by_prefix' => 'Key-Value Map per Prefix',
        'enum_map' => 'Value Mapping',
        'json_path' => 'JSON Path',
        'concat' => 'Concat Entwurf',
    ];

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? $type;
    }

    public static function isValid(string $type): bool
    {
        return array_key_exists($type, self::LABELS);
    }

    public static function labels(): array
    {
        return self::LABELS;
    }

    public static function formLabels(): array
    {
        return [
            'source_column' => self::LABELS['source_column'],
            'static_value' => self::LABELS['static_value'],
            'lookup_value' => self::LABELS['lookup_value'],
            'key_value_map_by_prefix' => self::LABELS['key_value_map_by_prefix'],
            'enum_map' => self::LABELS['enum_map'],
            'json_path' => self::LABELS['json_path'],
            'concat' => self::LABELS['concat'],
            'direct' => 'Legacy: ' . self::LABELS['direct'],
            'static' => 'Legacy: ' . self::LABELS['static'],
        ];
    }
}
