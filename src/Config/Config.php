<?php

declare(strict_types=1);

namespace Luna\Config;

final class Config
{
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => $default,
            };
        }

        return $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    public function appKey(): string
    {
        return $this->string('APP_KEY');
    }
}
