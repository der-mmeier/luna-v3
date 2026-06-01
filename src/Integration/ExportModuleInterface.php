<?php

declare(strict_types=1);

namespace Luna\Integration;

use DateTimeImmutable;

interface ExportModuleInterface
{
    public function name(): string;

    public function endpointKey(): string;

    public function description(): string;

    public function version(): string;

    /**
     * @return list<string>
     */
    public function runtimeFiles(): array;

    /**
     * @return list<string>
     */
    public function excludedFiles(): array;

    /**
     * @return list<string>
     */
    public function neverExport(): array;

    /**
     * @return array<string, mixed>
     */
    public function secretPolicy(): array;

    public function manifest(?DateTimeImmutable $generatedAt = null): ExportManifest;
}
