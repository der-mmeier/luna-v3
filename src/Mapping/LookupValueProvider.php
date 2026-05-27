<?php

declare(strict_types=1);

namespace Luna\Mapping;

interface LookupValueProvider
{
    /**
     * @param array<string, mixed> $field
     */
    public function lookup(array $field, string $key): LookupResult;
}
