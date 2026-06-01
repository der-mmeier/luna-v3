<?php

declare(strict_types=1);

namespace Luna\Integration;

use RuntimeException;

final class ExportModuleRegistry
{
    /** @var array<string, ExportModuleInterface> */
    private array $modules = [];

    /**
     * @param iterable<ExportModuleInterface> $modules
     */
    public function __construct(iterable $modules = [])
    {
        foreach ($modules as $module) {
            $this->register($module);
        }
    }

    public function register(ExportModuleInterface $module): void
    {
        $this->modules[$module->name()] = $module;
    }

    public function get(string $name): ExportModuleInterface
    {
        $name = trim($name);

        if (! isset($this->modules[$name])) {
            throw new RuntimeException('Export module not found.');
        }

        return $this->modules[$name];
    }

    /**
     * @return list<ExportModuleInterface>
     */
    public function all(): array
    {
        ksort($this->modules);

        return array_values($this->modules);
    }
}
