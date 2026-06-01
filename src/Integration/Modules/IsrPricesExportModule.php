<?php

declare(strict_types=1);

namespace Luna\Integration\Modules;

use DateTimeImmutable;
use Luna\Integration\ExportManifest;
use Luna\Integration\ExportModuleInterface;

final class IsrPricesExportModule implements ExportModuleInterface
{
    public function name(): string
    {
        return 'isr_prices';
    }

    public function endpointKey(): string
    {
        return 'isr_prices';
    }

    public function description(): string
    {
        return 'Export Runtime für den AsfInStockRings Preis- und Bestand-Endpunkt.';
    }

    public function version(): string
    {
        return '1.6.0';
    }

    public function runtimeFiles(): array
    {
        return [
            'api/isr_prices.php',
            'runtime/bootstrap.php',
            'runtime/EnvLoader.php',
            'runtime/JsonResponseFactory.php',
            'runtime/ConnectionFactory.php',
            'runtime/MappingExecutor.php',
            'runtime/EndpointRunner.php',
            'runtime/.htaccess',
            'config/endpoint.isr_prices.php',
            'config/.htaccess',
            '.env.example',
            '.htaccess',
            'manifest.json',
            'module.isr_prices.manifest.json',
        ];
    }

    public function excludedFiles(): array
    {
        return [
            '.env',
            '.env.local',
            '.env.*',
            '*.zip',
            '*.log',
            '*.tmp',
            '.git/',
            '.idea/',
            'node_modules/',
            'vendor/',
            'logs/',
            'cache/',
            'tmp/',
            'temp/',
        ];
    }

    public function neverExport(): array
    {
        return [
            '.env',
            '.env.*',
            'APP_KEY',
            'password',
            'secret',
            'token',
            'api_key',
            'apikey',
            'private_key',
            'connection_secrets',
            'luna_connection_secrets',
            'local_absolute_paths',
        ];
    }

    public function secretPolicy(): array
    {
        return [
            'exports_secrets' => false,
            'allows_env_example' => true,
            'forbidden_patterns' => [
                '.env',
                'APP_KEY',
                'password',
                'secret',
                'token',
                'api_key',
                'apikey',
                'private_key',
            ],
        ];
    }

    public function manifest(?DateTimeImmutable $generatedAt = null): ExportManifest
    {
        return new ExportManifest(
            $this->name(),
            $this->version(),
            $this->description(),
            $this->runtimeFiles(),
            $this->excludedFiles(),
            $this->neverExport(),
            $this->secretPolicy(),
            [
                'project' => 'AsfInStockRings',
                'endpoint_key' => $this->endpointKey(),
                'primary_goal' => 'isr_prices fachlich korrekt und reproduzierbar exportieren.',
                'dry_run_command' => 'php bin/luna integration:export isr_prices --dry-run',
                'zip_command' => 'php bin/luna integration:export isr_prices --zip --force',
                'expected_item_fields' => [
                    'model',
                    'name',
                    'price_group',
                    'price',
                    'pseudo_price',
                    'dr_quantities',
                    'hr_quantities',
                ],
            ],
            $generatedAt,
        );
    }
}
