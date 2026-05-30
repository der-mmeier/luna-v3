<?php

declare(strict_types=1);

namespace Luna\Repository;

final class DeleteCheckResult
{
    /**
     * @param list<string> $blockingNames
     * @param array<string, int> $counts
     */
    private function __construct(
        public readonly bool $allowed,
        public readonly string $message,
        public readonly array $blockingNames = [],
        public readonly array $counts = [],
    ) {
    }

    public static function allowed(): self
    {
        return new self(true, '');
    }

    /**
     * @param list<string> $blockingNames
     * @param array<string, int> $counts
     */
    public static function blocked(string $message, array $blockingNames = [], array $counts = []): self
    {
        return new self(false, $message, $blockingNames, $counts);
    }
}
