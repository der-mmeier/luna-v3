<?php

declare(strict_types=1);

namespace Luna\Core;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\MigrationRunner;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Http\Response;
use Luna\Security\EncryptionService;
use Luna\View\ViewRenderer;

final class Application
{
    private ServiceRegistry $services;

    private Kernel $kernel;

    public function __construct(
        private readonly Paths $paths,
        private readonly Config $config,
        ?ServiceRegistry $services = null,
        ?Kernel $kernel = null,
    ) {
        $this->services = $services ?? new ServiceRegistry();
        $this->kernel = $kernel ?? new Kernel($this);

        $this->registerCoreServices();
    }

    public function paths(): Paths
    {
        return $this->paths;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function services(): ServiceRegistry
    {
        return $this->services;
    }

    public function kernel(): Kernel
    {
        return $this->kernel;
    }

    public function handle(): Response
    {
        return $this->kernel->handle();
    }

    public function run(): void
    {
        $this->handle()->send();
    }

    private function registerCoreServices(): void
    {
        $this->services->set(Paths::class, $this->paths);
        $this->services->set(Config::class, $this->config);
        $this->services->set(ServiceRegistry::class, $this->services);
        $this->services->set(Kernel::class, $this->kernel);
        $this->services->set(ViewRenderer::class, new ViewRenderer($this->paths->viewsPath()));
        $this->services->set(EncryptionService::class, new EncryptionService($this->config));

        $databaseConfig = new DatabaseConfig($this->config);
        $systemDatabase = new SystemDatabase($databaseConfig, new PdoConnectionFactory());
        $migrationRunner = new MigrationRunner($systemDatabase, $this->paths->basePath('database/migrations'));

        $this->services->set(DatabaseConfig::class, $databaseConfig);
        $this->services->set(SystemDatabase::class, $systemDatabase);
        $this->services->set(MigrationRunner::class, $migrationRunner);
        $this->services->set('paths', $this->paths);
        $this->services->set('config', $this->config);
        $this->services->set('kernel', $this->kernel);
        $this->services->set('view', $this->services->get(ViewRenderer::class));
        $this->services->set('security.encryption', $this->services->get(EncryptionService::class));
        $this->services->set('database.system', $systemDatabase);
        $this->services->set('database.migrations', $migrationRunner);
    }
}
