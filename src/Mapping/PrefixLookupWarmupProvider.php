<?php

declare(strict_types=1);

namespace Luna\Mapping;

interface PrefixLookupWarmupProvider
{
    /**
     * @param list<array{field: array<string, mixed>, prefixes: list<string>}> $requests
     *
     * @return array<string, int|float>
     */
    public function warmUpPrefixLookups(array $requests): array;
}
