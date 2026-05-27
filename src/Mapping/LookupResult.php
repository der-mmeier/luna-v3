<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class LookupResult
{
    private function __construct(
        public readonly bool $found,
        public readonly mixed $value,
        public readonly ?string $errorCode = null,
        public readonly int $matchCount = 0,
        public readonly array $matchedValues = [],
    ) {
    }

    public static function found(mixed $value, int $matchCount = 1, array $matchedValues = []): self
    {
        return new self(true, $value, null, $matchCount, $matchedValues);
    }

    public static function error(string $errorCode, int $matchCount = 0, array $matchedValues = []): self
    {
        return new self(false, null, $errorCode, $matchCount, $matchedValues);
    }
}
