<?php

declare(strict_types=1);

namespace Luna\Integration;

use Luna\Export\EndpointExportArchiveService;
use Luna\Export\EndpointRuntimeExporter;
use RuntimeException;

final class ExportRuntimeBuilder
{
    public function __construct(
        private readonly ExportModuleRegistry $modules,
        private readonly EndpointRuntimeExporter $endpointExporter,
        private readonly EndpointExportArchiveService $archiveService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dryRun(string $moduleName, bool $withZip = false): array
    {
        $module = $this->modules->get($moduleName);
        $manifest = $module->manifest()->toArray();

        return [
            'status' => 'planned',
            'dry_run' => true,
            'module' => $module->name(),
            'endpoint_key' => $module->endpointKey(),
            'zip_requested' => $withZip,
            'manifest' => $manifest,
            'warnings' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function export(string $moduleName, ?string $targetDirectory = null, bool $force = false, bool $withZip = false): array
    {
        $module = $this->modules->get($moduleName);
        $endpointManifest = $targetDirectory === null || trim($targetDirectory) === ''
            ? $this->endpointExporter->exportToWorkspaceStorage($module->endpointKey(), $force, false)
            : $this->endpointExporter->export($module->endpointKey(), $targetDirectory, $force, false);

        $targetPath = (string) ($endpointManifest['absolute_target_path'] ?? '');
        if ($targetPath === '' || ! is_dir($targetPath)) {
            throw new RuntimeException('Endpoint export target was not created.');
        }

        $moduleManifest = $module->manifest()->toArray();
        $moduleManifest['endpoint_export'] = $this->publicEndpointManifest($endpointManifest);
        $moduleManifest['status'] = 'exported';
        $this->writeModuleManifest($targetPath, $module, $moduleManifest);

        $archivePath = null;
        $archiveFiles = [];
        if ($withZip) {
            $archivePath = (string) ($endpointManifest['absolute_archive_path'] ?? '');
            $archiveFiles = $this->archiveService->createArchive($targetPath, $archivePath, true);
        }

        return [
            'status' => 'exported',
            'module' => $module->name(),
            'endpoint_key' => $module->endpointKey(),
            'target_path' => (string) ($endpointManifest['target_path'] ?? $targetDirectory ?? ''),
            'archive_path' => $withZip ? (string) ($endpointManifest['archive_path'] ?? $archivePath) : null,
            'files' => $endpointManifest['files'] ?? [],
            'archive_files' => $archiveFiles,
            'manifest' => $moduleManifest,
            'warnings' => [],
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array<string, mixed>
     */
    private function publicEndpointManifest(array $manifest): array
    {
        unset($manifest['absolute_target_path'], $manifest['absolute_archive_path']);

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeModuleManifest(string $targetPath, ExportModuleInterface $module, array $manifest): void
    {
        $path = rtrim($targetPath, "\\/") . DIRECTORY_SEPARATOR . 'module.' . $module->name() . '.manifest.json';
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $json . "\n") === false) {
            throw new RuntimeException('Module manifest could not be written.');
        }
    }
}
