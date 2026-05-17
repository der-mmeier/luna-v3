<?php

declare(strict_types=1);

namespace Luna\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $headers
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $server = [],
        private readonly array $headers = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $server = $_SERVER;
        $method = isset($server['REQUEST_METHOD']) ? (string) $server['REQUEST_METHOD'] : 'GET';
        $uri = isset($server['REQUEST_URI']) ? (string) $server['REQUEST_URI'] : '/';

        return new self(
            strtoupper($method),
            self::normalizePath($uri),
            $_GET,
            $_POST,
            $server,
            self::headersFromServer($server),
        );
    }

    public function method(): string
    {
        return strtoupper($this->method);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $normalized = self::normalizeHeaderName($key);

        return $this->headers[$normalized] ?? $default;
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    private static function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, mixed>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = substr($key, 5);
                $headers[self::normalizeHeaderName($name)] = $value;
            }
        }

        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $key) {
            if (array_key_exists($key, $server)) {
                $headers[self::normalizeHeaderName($key)] = $server[$key];
            }
        }

        return $headers;
    }

    private static function normalizeHeaderName(string $name): string
    {
        return strtolower(str_replace('_', '-', $name));
    }
}
