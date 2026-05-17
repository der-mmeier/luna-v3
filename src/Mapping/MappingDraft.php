<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class MappingDraft
{
    public function __construct(
        public readonly array $set,
        public readonly array $fields,
    ) {
    }
}
