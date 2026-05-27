<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class LookupResult
{
    private function __construct(
        public readonly bool $found,
        public readonly mixed $value,
        public readonly ?string $errorCode = null,
    ) {
    }

    public static function found(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function error(string $errorCode): self
    {
        return new self(false, null, $errorCode);
    }
}
