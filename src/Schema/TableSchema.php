<?php

declare(strict_types=1);

namespace Luna\Schema;

final class TableSchema
{
    public function __construct(
        public readonly string $tableName,
        public readonly string $tableType,
        public readonly ?int $tableRows,
        public readonly ?string $tableComment,
    ) {
    }

    public function toArray(): array
    {
        return [
            'table_name' => $this->tableName,
            'table_type' => $this->tableType,
            'table_rows' => $this->tableRows,
            'table_comment' => $this->tableComment,
        ];
    }
}
