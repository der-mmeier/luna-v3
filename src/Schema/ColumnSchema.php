<?php

declare(strict_types=1);

namespace Luna\Schema;

final class ColumnSchema
{
    public function __construct(
        public readonly string $columnName,
        public readonly string $dataType,
        public readonly string $columnType,
        public readonly string $isNullable,
        public readonly mixed $columnDefault,
        public readonly string $columnKey,
        public readonly string $extra,
        public readonly string $columnComment,
    ) {
    }

    public function toArray(): array
    {
        return [
            'column_name' => $this->columnName,
            'data_type' => $this->dataType,
            'column_type' => $this->columnType,
            'is_nullable' => $this->isNullable,
            'column_default' => $this->columnDefault,
            'column_key' => $this->columnKey,
            'extra' => $this->extra,
            'column_comment' => $this->columnComment,
        ];
    }
}
