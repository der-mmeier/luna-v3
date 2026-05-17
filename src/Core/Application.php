<?php

declare(strict_types=1);

namespace Luna\Core;

use Luna\Config\Config;
use Luna\Connections\ConnectionTester;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Database\DatabaseConfig;
use Luna\Database\MigrationRunner;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Http\Response;
use Luna\Jobs\JobRunner;
use Luna\Mapping\MappingValidator;
use Luna\Reports\ReportEngine;
use Luna\Reports\ReportMailer;
use Luna\Repository\AuditLogRepository;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\JobRepository;
use Luna\Repository\JobRunRepository;
use Luna\Repository\MappingRepository;
use Luna\Repository\ReportRepository;
use Luna\Repository\SchemaMetadataRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Security\EncryptionService;
use Luna\Transfer\MappingExecutor;
use Luna\Transfer\MappingRowTransformer;
use Luna\Transfer\TargetWriter;
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

        $externalPdoFactory = new ExternalPdoConnectionFactory();
        $this->services->set(ExternalPdoConnectionFactory::class, $externalPdoFactory);
        $this->services->set(ConnectionTester::class, new ConnectionTester($externalPdoFactory));
        $this->services->set(WorkspaceRepository::class, new WorkspaceRepository($systemDatabase));
        $this->services->set(ConnectionProfileRepository::class, new ConnectionProfileRepository(
            $systemDatabase,
            $this->services->get(EncryptionService::class),
        ));
        $this->services->set(MappingRepository::class, new MappingRepository($systemDatabase));
        $this->services->set(AuditLogRepository::class, new AuditLogRepository($systemDatabase));
        $this->services->set(JobRepository::class, new JobRepository($systemDatabase));
        $this->services->set(JobRunRepository::class, new JobRunRepository($systemDatabase));
        $this->services->set(ReportRepository::class, new ReportRepository($systemDatabase));
        $this->services->set(SchemaMetadataRepository::class, new SchemaMetadataRepository($systemDatabase));
        $this->services->set(MappingValidator::class, new MappingValidator(
            $this->services->get(MappingRepository::class),
            $this->services->get(ConnectionProfileRepository::class),
            $externalPdoFactory,
        ));
        $this->services->set(MappingRowTransformer::class, new MappingRowTransformer($this->services->get(MappingRepository::class)));
        $this->services->set(TargetWriter::class, new TargetWriter());
        $this->services->set(MappingExecutor::class, new MappingExecutor(
            $this->services->get(MappingRepository::class),
            $this->services->get(MappingValidator::class),
            $this->services->get(ConnectionProfileRepository::class),
            $externalPdoFactory,
            $this->services->get(MappingRowTransformer::class),
            $this->services->get(TargetWriter::class),
        ));
        $this->services->set(ReportEngine::class, new ReportEngine(
            $this->services->get(JobRunRepository::class),
            $this->services->get(ReportRepository::class),
            $this->services->get(AuditLogRepository::class),
        ));
        $this->services->set(ReportMailer::class, new ReportMailer(
            $this->services->get(ReportRepository::class),
            $this->config,
            $this->services->get(AuditLogRepository::class),
        ));
        $this->services->set(JobRunner::class, new JobRunner(
            $this->services->get(JobRepository::class),
            $this->services->get(JobRunRepository::class),
            $this->services->get(MappingExecutor::class),
            $this->services->get(ReportEngine::class),
            $this->services->get(AuditLogRepository::class),
        ));

        $this->services->set('paths', $this->paths);
        $this->services->set('config', $this->config);
        $this->services->set('kernel', $this->kernel);
        $this->services->set('view', $this->services->get(ViewRenderer::class));
        $this->services->set('security.encryption', $this->services->get(EncryptionService::class));
        $this->services->set('database.system', $systemDatabase);
        $this->services->set('database.migrations', $migrationRunner);
        $this->services->set('connections.pdo_factory', $externalPdoFactory);
        $this->services->set('connections.tester', $this->services->get(ConnectionTester::class));
        $this->services->set('repository.workspaces', $this->services->get(WorkspaceRepository::class));
        $this->services->set('repository.connections', $this->services->get(ConnectionProfileRepository::class));
        $this->services->set('repository.schema_metadata', $this->services->get(SchemaMetadataRepository::class));
        $this->services->set('repository.mappings', $this->services->get(MappingRepository::class));
        $this->services->set('repository.audit_log', $this->services->get(AuditLogRepository::class));
        $this->services->set('repository.jobs', $this->services->get(JobRepository::class));
        $this->services->set('repository.job_runs', $this->services->get(JobRunRepository::class));
        $this->services->set('repository.reports', $this->services->get(ReportRepository::class));
        $this->services->set('mapping.validator', $this->services->get(MappingValidator::class));
        $this->services->set('mapping.executor', $this->services->get(MappingExecutor::class));
        $this->services->set('jobs.runner', $this->services->get(JobRunner::class));
        $this->services->set('reports.engine', $this->services->get(ReportEngine::class));
        $this->services->set('reports.mailer', $this->services->get(ReportMailer::class));
    }
}
