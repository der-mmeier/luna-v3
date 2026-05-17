<?php

declare(strict_types=1);

namespace Luna\Transfer;

final class TransferMode
{
    public const INSERT = 'insert';
    public const UPSERT_DRAFT = 'upsert_draft';

    public static function all(): array
    {
        return [self::INSERT, self::UPSERT_DRAFT];
    }
}
