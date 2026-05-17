<?php

declare(strict_types=1);

namespace Luna\Routing;

use Luna\Http\Request;

final class Route
{
    /**
     * @var callable
     */
    private mixed $handler;

    public function __construct(
        string $method,
        string $path,
        callable $handler,
        private readonly ?string $name = null,
        private readonly string $group = 'web',
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalizePath($path);
        $this->handler = $handler;
    }

    private string $method;

    private string $path;

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function handler(): callable
    {
        return $this->handler;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function matches(Request $request): bool
    {
        return $request->isMethod($this->method) && $request->path() === $this->path;
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }
}
