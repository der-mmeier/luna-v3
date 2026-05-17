<?php

declare(strict_types=1);

namespace Luna\Core;

use RuntimeException;

final class ServiceRegistry
{
    /**
     * @var array<string, mixed>
     */
    private array $services = [];

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new RuntimeException(sprintf('Service "%s" is not registered.', $id));
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
