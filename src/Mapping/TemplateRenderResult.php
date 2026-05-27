<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class TemplateRenderResult
{
    /**
     * @param list<string> $missingPlaceholders
     */
    public function __construct(
        public readonly string $value,
        public readonly array $missingPlaceholders = [],
    ) {
    }

    public function isValid(): bool
    {
        return $this->missingPlaceholders === [];
    }
}
