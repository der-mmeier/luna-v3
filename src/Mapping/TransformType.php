<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class TransformType
{
    private const LABELS = [
        'direct' => 'Direkt',
        'static' => 'Statischer Wert',
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
}
