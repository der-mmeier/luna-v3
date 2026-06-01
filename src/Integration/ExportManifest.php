<?php

declare(strict_types=1);

namespace Luna\Integration;

use DateTimeImmutable;

final class ExportManifest
{
    /**
     * @param list<string> $runtimeFiles
     * @param list<string> $excludedFiles
     * @param list<string> $neverExport
     * @param array<string, mixed> $secretPolicy
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $module,
        private readonly string $version,
        private readonly string $description,
        private readonly array $runtimeFiles,
        private readonly array $excludedFiles,
        private readonly array $neverExport,
        private readonly array $secretPolicy,
        private readonly array $metadata = [],
        private readonly ?DateTimeImmutable $generatedAt = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'version' => $this->version,
            'generated_at' => ($this->generatedAt ?? new DateTimeImmutable())->format(DATE_ATOM),
            'description' => $this->description,
            'runtime_files' => $this->runtimeFiles,
            'excluded_files' => $this->excludedFiles,
            'never_export' => $this->neverExport,
            'secret_policy' => $this->secretPolicy,
            'metadata' => $this->metadata,
        ];
    }
}
