<?php

declare(strict_types=1);

namespace Luna\WooCommerce;

final class WooCommerceValidationResult
{
    /**
     * @param list<string> $missingSchemaParts
     * @param list<string> $errors
     * @param list<string> $warnings
     * @param array<string, string> $hposOptions
     */
    public function __construct(
        public readonly ?string $tablePrefix,
        public readonly ?string $woocommerceVersion,
        public readonly bool $versionAccepted,
        public readonly bool $hposEnabled,
        public readonly bool $hposAuthoritative,
        public readonly bool $schemaComplete,
        public readonly bool $transferReady,
        public readonly int $orderCount,
        public readonly ?string $oldestOrderAt,
        public readonly ?string $newestOrderAt,
        public readonly array $missingSchemaParts = [],
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly array $hposOptions = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'table_prefix' => $this->tablePrefix,
            'woocommerce_version' => $this->woocommerceVersion,
            'version_accepted' => $this->versionAccepted,
            'hpos_enabled' => $this->hposEnabled,
            'hpos_authoritative' => $this->hposAuthoritative,
            'schema_complete' => $this->schemaComplete,
            'transfer_ready' => $this->transferReady,
            'order_count' => $this->orderCount,
            'oldest_order_at' => $this->oldestOrderAt,
            'newest_order_at' => $this->newestOrderAt,
            'missing_schema_parts' => $this->missingSchemaParts,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'hpos_options' => $this->hposOptions,
        ];
    }
}
