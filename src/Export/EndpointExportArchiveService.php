<?php

declare(strict_types=1);

namespace Luna\Export;

use RuntimeException;
use ZipArchive;

final class EndpointExportArchiveService
{
    /**
     * @return list<string>
     */
    public function createArchive(string $exportDirectory, string $archivePath, bool $overwrite = true): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP extension is not available.');
        }

        if (! is_dir($exportDirectory)) {
            throw new RuntimeException('Export directory does not exist.');
        }

        if (is_file($archivePath)) {
            if (! $overwrite) {
                throw new RuntimeException('Archive already exists.');
            }
            unlink($archivePath);
        }

        $archiveDirectory = dirname($archivePath);
        if (! is_dir($archiveDirectory) && ! mkdir($archiveDirectory, 0775, true) && ! is_dir($archiveDirectory)) {
            throw new RuntimeException('Archive directory could not be created.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Archive could not be created.');
        }

        $files = [];
        $base = str_replace('\\', '/', realpath($exportDirectory) ?: $exportDirectory);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($exportDirectory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $relativePath = ltrim(substr($path, strlen($base)), '/');

            if ($this->excluded($relativePath)) {
                continue;
            }

            $files[$relativePath] = $file->getPathname();
        }

        ksort($files);

        foreach ($files as $relativePath => $path) {
            $zip->addFile($path, $relativePath);
        }

        $zip->close();

        return array_keys($files);
    }

    private function excluded(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $basename = basename($normalized);

        if (($basename === '.env' || (str_starts_with($basename, '.env.') && $basename !== '.env.example')) || str_ends_with($basename, '.zip')) {
            return true;
        }

        foreach (['.git', '.idea', 'node_modules', 'vendor'] as $segment) {
            if ($normalized === $segment || str_starts_with($normalized, $segment . '/')) {
                return true;
            }
        }

        if (str_contains($normalized, '/.git/') || str_contains($normalized, '/.idea/') || str_contains($normalized, '/node_modules/') || str_contains($normalized, '/vendor/')) {
            return true;
        }

        return str_contains($normalized, '/tmp/')
            || str_contains($normalized, '/temp/')
            || str_contains($normalized, '/logs/')
            || str_ends_with($normalized, '.tmp')
            || str_ends_with($normalized, '.log');
    }
}
