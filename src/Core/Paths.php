<?php

declare(strict_types=1);

namespace Luna\Core;

final class Paths
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $this->normalize($basePath);
    }

    public function basePath(string $path = ''): string
    {
        return $this->join($this->basePath, $path);
    }

    public function publicPath(string $path = ''): string
    {
        return $this->join($this->basePath('public'), $path);
    }

    public function srcPath(string $path = ''): string
    {
        return $this->join($this->basePath('src'), $path);
    }

    public function storagePath(string $path = ''): string
    {
        return $this->join($this->basePath('storage'), $path);
    }

    public function resourcesPath(string $path = ''): string
    {
        return $this->join($this->basePath('resources'), $path);
    }

    public function viewsPath(string $path = ''): string
    {
        return $this->join($this->resourcesPath('views'), $path);
    }

    public function publicAssetPath(string $path = ''): string
    {
        return $this->join($this->publicPath('assets'), $path);
    }

    private function join(string $basePath, string $path): string
    {
        if ($path === '') {
            return $basePath;
        }

        return $this->normalize($basePath . DIRECTORY_SEPARATOR . $path);
    }

    private function normalize(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return rtrim($normalized, DIRECTORY_SEPARATOR);
    }
}
