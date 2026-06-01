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
        $manifest = $this->deploymentManifest($module, []);
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

        $this->writeDeploymentFiles($targetPath, $module);
        $checksums = $this->checksums($targetPath);
        $this->writeChecksums($targetPath, $checksums);
        $moduleManifest = $this->deploymentManifest($module, $checksums);
        $moduleManifest['endpoint_export'] = $this->publicEndpointManifest($endpointManifest);
        $moduleManifest['status'] = 'exported';
        $this->writeRootManifest($targetPath, $moduleManifest);
        $this->writeModuleManifest($targetPath, $module, $moduleManifest);
        $validation = $this->validateExportDirectory($module, $targetPath);
        $moduleManifest['validation'] = $validation;
        $this->writeRootManifest($targetPath, $moduleManifest);
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
            'source_commit' => (string) ($manifest['source_commit'] ?? 'unknown'),
            'checksums_present' => is_array($manifest['checksums'] ?? null),
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
        $checksumsPath = $targetDirectory . DIRECTORY_SEPARATOR . 'CHECKSUMS.txt';
        $absolutePathFindings = [];
        $secretValueFindings = [];

        foreach ($files as $file) {
            $path = $targetDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            $contents = is_file($path) ? (string) file_get_contents($path) : '';

            if (preg_match('/[A-Za-z]:\\\\Users\\\\|\/home\/[^\/]+\/|\/Users\/[^\/]+\//', $contents) === 1) {
                $absolutePathFindings[] = $file;
            }

            if (preg_match('/^\s*[A-Z0-9_]*(?:APP_KEY|PASSWORD|PASSWD|SECRET|TOKEN|API_KEY|APIKEY|PRIVATE_KEY)[A-Z0-9_]*\s*=\s*[^\\s]+/m', $contents) === 1) {
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
            'source_commit' => is_array($manifest) ? (string) ($manifest['source_commit'] ?? 'unknown') : 'unknown',
            'checksums_present' => is_file($checksumsPath) && is_array($manifest) && is_array($manifest['checksums'] ?? null) && $manifest['checksums'] !== [],
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

            $path = str_replace('\\', '/', realpath($file->getPathname()) ?: $file->getPathname());
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
            || str_starts_with($normalized, 'cache/')
            || str_contains($normalized, '/.cache/')
            || str_contains($normalized, '/tmp/')
            || str_contains($normalized, '/temp/');
    }

    /**
     * @param array<string, string> $checksums
     *
     * @return array<string, mixed>
     */
    private function deploymentManifest(ExportModuleInterface $module, array $checksums): array
    {
        $manifest = $module->manifest()->toArray();
        $manifest['source_commit'] = $this->sourceCommit();
        $manifest['checksums'] = $checksums;
        $manifest['deployment'] = [
            'requires_config' => true,
            'entrypoint' => 'api/' . $module->endpointKey() . '.php',
            'healthcheck' => 'api/' . $module->endpointKey() . '.php?health=1',
            'config_example' => 'config/config.example.php',
            'env_example' => '.env.example',
        ];

        return $manifest;
    }

    private function writeDeploymentFiles(string $targetPath, ExportModuleInterface $module): void
    {
        $this->writeFile($targetPath, 'config/config.example.php', $this->configExample($module));
        $this->writeFile($targetPath, 'README_DEPLOY.md', $this->deploymentReadme($module));
    }

    private function configExample(ExportModuleInterface $module): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n"
            . "    'app_env' => 'production',\n"
            . "    'debug' => false,\n"
            . "    'module' => '" . addslashes($module->name()) . "',\n"
            . "    'endpoint' => '" . addslashes($module->endpointKey()) . "',\n"
            . "    'config_source' => '.env',\n"
            . "    'healthcheck' => 'api/" . addslashes($module->endpointKey()) . ".php?health=1',\n"
            . "    'notes' => 'Copy .env.example to .env on the target system and fill values there.',\n"
            . "];\n";
    }

    private function deploymentReadme(ExportModuleInterface $module): string
    {
        $endpoint = $module->endpointKey();

        return <<<MARKDOWN
# {$module->name()} Deployment

Dieses Paket enthaelt die exportierte Runtime fuer den Endpoint `{$endpoint}`.

## Schritte

1. ZIP auf dem Zielsystem entpacken.
2. Webserver so konfigurieren, dass `api/{$endpoint}.php` erreichbar ist.
3. `.env.example` nach `.env` kopieren und die Zielsystem-Werte eintragen.
4. Secrets nur auf dem Zielsystem in `.env` oder Server-Umgebungsvariablen setzen.
5. Healthcheck aufrufen: `api/{$endpoint}.php?health=1`.
6. Endpoint aufrufen: `api/{$endpoint}.php`.
7. Server-Logs pruefen, falls der Endpoint kein erfolgreiches JSON liefert.
8. Secret-Scan gegen das entpackte Paket ausfuehren.

## Gegenpruefung

- `manifest.json` pruefen.
- `CHECKSUMS.txt` pruefen.
- Sicherstellen, dass keine `.env`, `.git`, `.idea`, `.phpunit.cache`, Log- oder Cache-Dateien enthalten sind.
- Treffer in dokumentierten Secret-Policies sind erlaubt. Echte Secret-Werte duerfen nicht enthalten sein.

MARKDOWN;
    }

    /**
     * @return array<string, string>
     */
    private function checksums(string $targetDirectory): array
    {
        $checksums = [];

        foreach ($this->relativeFiles($targetDirectory) as $file) {
            if ($this->isForbiddenFile($file) || in_array($file, ['CHECKSUMS.txt', 'manifest.json'], true) || (str_starts_with($file, 'module.') && str_ends_with($file, '.manifest.json'))) {
                continue;
            }

            $path = $targetDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            $hash = is_file($path) ? hash_file('sha256', $path) : false;
            if ($hash === false) {
                continue;
            }
            $checksums[$file] = $hash;
        }

        ksort($checksums);

        return $checksums;
    }

    /**
     * @param array<string, string> $checksums
     */
    private function writeChecksums(string $targetPath, array $checksums): void
    {
        $lines = [];
        foreach ($checksums as $file => $hash) {
            $lines[] = $hash . '  ' . $file;
        }

        $this->writeFile($targetPath, 'CHECKSUMS.txt', implode("\n", $lines) . "\n");
    }

    private function sourceCommit(): string
    {
        $git = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.git';
        $headPath = $git . DIRECTORY_SEPARATOR . 'HEAD';
        if (! is_file($headPath)) {
            return 'unknown';
        }

        $head = trim((string) file_get_contents($headPath));
        if (preg_match('/^[a-f0-9]{40}$/i', $head) === 1) {
            return $head;
        }

        if (! str_starts_with($head, 'ref: ')) {
            return 'unknown';
        }

        $ref = trim(substr($head, 5));
        $refPath = $git . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ref);
        if (is_file($refPath)) {
            $commit = trim((string) file_get_contents($refPath));

            return preg_match('/^[a-f0-9]{40}$/i', $commit) === 1 ? $commit : 'unknown';
        }

        return 'unknown';
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

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeRootManifest(string $targetPath, array $manifest): void
    {
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents(rtrim($targetPath, "\\/") . DIRECTORY_SEPARATOR . 'manifest.json', $json . "\n") === false) {
            throw new RuntimeException('Deployment manifest could not be written.');
        }
    }

    private function writeFile(string $targetDirectory, string $relativePath, string $contents): string
    {
        $path = rtrim($targetDirectory, "\\/") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Export directory could not be created.');
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Export file could not be written.');
        }

        return $relativePath;
    }
}
