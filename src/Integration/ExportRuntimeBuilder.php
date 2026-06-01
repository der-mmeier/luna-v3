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
        $validation = $this->plannedValidation($module, $manifest);

        return [
            'status' => 'planned',
            'dry_run' => true,
            'module' => $module->name(),
            'endpoint_key' => $module->endpointKey(),
            'zip_requested' => $withZip,
            'included_files' => $module->runtimeFiles(),
            'excluded_files' => $module->excludedFiles(),
            'validation' => $validation,
            'manifest' => $manifest,
            'warnings' => $validation['warnings'],
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
        $validation = $this->validateExportDirectory($module, $targetPath);
        $moduleManifest['validation'] = $validation;
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
            'validation' => $validation,
            'manifest' => $moduleManifest,
            'warnings' => $validation['warnings'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(string $moduleName, string $targetDirectory): array
    {
        return $this->validateExportDirectory($this->modules->get($moduleName), $targetDirectory);
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array<string, mixed>
     */
    private function plannedValidation(ExportModuleInterface $module, array $manifest): array
    {
        return [
            'status' => 'planned',
            'module_name_valid' => ($manifest['module'] ?? '') === $module->name(),
            'manifest_present' => true,
            'runtime_files_complete' => true,
            'forbidden_files_present' => [],
            'secret_policy_active' => ($manifest['secret_policy']['exports_secrets'] ?? true) === false,
            'local_absolute_paths_found' => [],
            'secret_value_findings' => [],
            'payload_comparison' => [
                'automated_in_tests' => true,
                'manual_command' => 'php bin/luna integration:export ' . $module->name() . ' --dry-run --zip',
                'note' => 'Automatisierter Payload-Vergleich nutzt PHPUnit-Fixtures; externe Produktivdaten werden manuell per Endpoint-Abruf geprüft.',
            ],
            'included_files' => $module->runtimeFiles(),
            'excluded_files' => $module->excludedFiles(),
            'warnings' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateExportDirectory(ExportModuleInterface $module, string $targetDirectory): array
    {
        $targetDirectory = rtrim($targetDirectory, "\\/");
        $files = $this->relativeFiles($targetDirectory);
        $manifestPath = $targetDirectory . DIRECTORY_SEPARATOR . 'module.' . $module->name() . '.manifest.json';
        $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;
        $missingRuntimeFiles = array_values(array_diff($module->runtimeFiles(), $files));
        $forbiddenFiles = array_values(array_filter($files, fn (string $file): bool => $this->isForbiddenFile($file)));
        $absolutePathFindings = [];
        $secretValueFindings = [];

        foreach ($files as $file) {
            $path = $targetDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            $contents = is_file($path) ? (string) file_get_contents($path) : '';

            if (preg_match('/[A-Za-z]:\\\\Users\\\\|\/home\/[^\/]+\/|\/Users\/[^\/]+\//', $contents) === 1) {
                $absolutePathFindings[] = $file;
            }

            if (preg_match('/(?:APP_KEY|PASSWORD|SECRET|TOKEN|API_KEY|APIKEY|PRIVATE_KEY)\s*=\s*[^\\s]+/i', $contents) === 1) {
                if ($file !== '.env.example') {
                    $secretValueFindings[] = $file;
                }
            }
        }

        $warnings = [];
        if ($missingRuntimeFiles !== []) {
            $warnings[] = 'runtime_files_missing';
        }
        if ($forbiddenFiles !== []) {
            $warnings[] = 'forbidden_files_present';
        }
        if ($absolutePathFindings !== []) {
            $warnings[] = 'local_absolute_paths_found';
        }
        if ($secretValueFindings !== []) {
            $warnings[] = 'secret_value_findings';
        }

        return [
            'status' => $warnings === [] ? 'passed' : 'warning',
            'module_name_valid' => is_array($manifest) && ($manifest['module'] ?? '') === $module->name(),
            'manifest_present' => is_array($manifest),
            'runtime_files_complete' => $missingRuntimeFiles === [],
            'missing_runtime_files' => $missingRuntimeFiles,
            'forbidden_files_present' => $forbiddenFiles,
            'secret_policy_active' => is_array($manifest) && ($manifest['secret_policy']['exports_secrets'] ?? true) === false,
            'local_absolute_paths_found' => array_values(array_unique($absolutePathFindings)),
            'secret_value_findings' => array_values(array_unique($secretValueFindings)),
            'included_files' => $files,
            'excluded_files' => $module->excludedFiles(),
            'warnings' => $warnings,
        ];
    }

    /**
     * @return list<string>
     */
    private function relativeFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $base = str_replace('\\', '/', realpath($directory) ?: $directory);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $files[] = ltrim(substr($path, strlen($base)), '/');
        }

        sort($files);

        return $files;
    }

    private function isForbiddenFile(string $file): bool
    {
        $normalized = str_replace('\\', '/', $file);
        $basename = basename($normalized);

        if ($basename === '.env' || (str_starts_with($basename, '.env.') && $basename !== '.env.example')) {
            return true;
        }

        foreach (['.git', '.idea', '.phpunit.cache'] as $segment) {
            if ($normalized === $segment || str_starts_with($normalized, $segment . '/') || str_contains($normalized, '/' . $segment . '/')) {
                return true;
            }
        }

        return str_ends_with($normalized, '.log')
            || str_contains($normalized, '/logs/')
            || str_contains($normalized, '/cache/')
            || str_contains($normalized, '/tmp/')
            || str_contains($normalized, '/temp/');
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
