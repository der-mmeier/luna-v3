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
        return $request->isMethod($this->method) && $this->parameters($request) !== null;
    }

    /**
     * @return array<string, string>|null
     */
    public function parameters(Request $request): ?array
    {
        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)}#',
            static fn (array $matches): string => $matches[1] === 'endpointKey' ? '(?P<endpointKey>.+)' : '(?P<' . $matches[1] . '>[^/]+)',
            $this->path,
        );

        if ($pattern === null) {
            return null;
        }

        if (preg_match('#^' . $pattern . '$#', $request->path(), $matches) !== 1) {
            return null;
        }

        $params = [];

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = urldecode($value);
            }
        }

        return $params;
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
