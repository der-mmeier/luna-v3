<?php

declare(strict_types=1);

namespace Luna\Core;

use Luna\Config\Config;
use Luna\Api\EndpointAccessGuard;
use Luna\Api\EndpointJsonResponseFactory;
use Luna\Api\EndpointResponseBuilder;
use Luna\Api\EndpointRuntime;
use Luna\Api\EndpointRunner;
use Luna\Api\EndpointSecretPolicy;
use Luna\Connections\ConnectionTester;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Database\DatabaseConfig;
use Luna\Database\MigrationRunner;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Dataset\DatasetRegistry;
use Luna\Deployment\DeploymentTargetUrlBuilder;
use Luna\Http\Response;
use Luna\Export\EndpointExportArchiveService;
use Luna\Export\EndpointExportContractService;
use Luna\Export\EndpointExportSanitizer;
use Luna\Export\EndpointRuntimeExporter;
use Luna\Export\EndpointSchemaBuilder;
use Luna\Export\WooCommerceExportService;
use Luna\Integration\ExportModuleRegistry;
use Luna\Integration\ExportRuntimeBuilder;
use Luna\Integration\Modules\IsrPricesExportModule;
use Luna\Jobs\JobRunner;
use Luna\Mapping\MappingValidator;
use Luna\Mapping\MappingFieldResolver;
use Luna\Mapping\PdoLookupValueProvider;
use Luna\Process\MappingRunStepRunner;
use Luna\Process\ProcessRunner;
use Luna\Process\ProcessTriggerRunner;
use Luna\Process\ProcessTriggerService;
use Luna\Process\SchemaValidationStepRunner;
use Luna\Process\TargetActionStepRunner;
use Luna\Process\TriggerConfigValidator;
use Luna\Process\TriggerUrlBuilder;
use Luna\Reports\ReportEngine;
use Luna\Reports\ReportMailer;
use Luna\Repository\AuditLogRepository;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\DatasetTransferRepository;
use Luna\Repository\DeploymentTargetRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\ExportProfileRepository;
use Luna\Repository\JobRepository;
use Luna\Repository\JobRunRepository;
use Luna\Repository\MappingRepository;
use Luna\Repository\ProcessRepository;
use Luna\Repository\ProcessRunRepository;
use Luna\Repository\ProcessTriggerRepository;
use Luna\Repository\ReportRepository;
use Luna\Repository\SchemaMetadataRepository;
use Luna\Repository\SchemaRegistryRepository;
use Luna\Repository\TargetActionRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Repository\WooCommerceIntegrationRepository;
use Luna\Security\EncryptionService;
use Luna\Schema\SchemaDefinitionValidator;
use Luna\Schema\SchemaValidator;
use Luna\TargetAction\NativeTargetActionHttpClient;
use Luna\TargetAction\TargetActionConfigValidator;
use Luna\TargetAction\TargetActionExecutor;
use Luna\TargetAction\TargetActionHttpClientInterface;
use Luna\Transfer\DatasetTransferRunner;
use Luna\Transfer\MappingExecutor;
use Luna\Transfer\MappingRowTransformer;
use Luna\Transfer\MappingSourceRowProvider;
use Luna\Transfer\SingleTableTransferWriter;
use Luna\Transfer\TargetWriter;
use Luna\View\ViewRenderer;
use Luna\WooCommerce\WooCommerceHposValidator;
use Luna\WooCommerce\WooCommerceHposOrderReader;
use Luna\WooCommerce\WooCommerceTransferRunner;
use Luna\WooCommerce\WooCommerceTransferWriter;
use Luna\WooCommerce\WooCommerceWebhookHandler;

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
        $this->services->set(DeploymentTargetUrlBuilder::class, new DeploymentTargetUrlBuilder());
        $this->services->set(WorkspaceRepository::class, new WorkspaceRepository($systemDatabase));
        $this->services->set(ConnectionProfileRepository::class, new ConnectionProfileRepository(
            $systemDatabase,
            $this->services->get(EncryptionService::class),
        ));
        $this->services->set(DeploymentTargetRepository::class, new DeploymentTargetRepository(
            $systemDatabase,
            $this->services->get(DeploymentTargetUrlBuilder::class),
        ));
        $this->services->set(MappingRepository::class, new MappingRepository($systemDatabase));
        $this->services->set(DatasetTransferRepository::class, new DatasetTransferRepository($systemDatabase));
        $this->services->set(AuditLogRepository::class, new AuditLogRepository($systemDatabase));
        $this->services->set(JobRepository::class, new JobRepository($systemDatabase));
        $this->services->set(JobRunRepository::class, new JobRunRepository($systemDatabase));
        $this->services->set(ProcessRepository::class, new ProcessRepository($systemDatabase));
        $this->services->set(ProcessRunRepository::class, new ProcessRunRepository($systemDatabase));
        $this->services->set(ProcessTriggerRepository::class, new ProcessTriggerRepository($systemDatabase));
        $this->services->set(TargetActionRepository::class, new TargetActionRepository($systemDatabase));
        $this->services->set(SchemaRegistryRepository::class, new SchemaRegistryRepository($systemDatabase));
        $this->services->set(ReportRepository::class, new ReportRepository($systemDatabase));
        $this->services->set(EndpointRepository::class, new EndpointRepository(
            $systemDatabase,
            $this->services->get(EncryptionService::class),
        ));
        $this->services->set(WooCommerceIntegrationRepository::class, new WooCommerceIntegrationRepository(
            $systemDatabase,
            $this->services->get(EncryptionService::class),
        ));
        $this->services->set(ExportProfileRepository::class, new ExportProfileRepository(
            $systemDatabase,
            $this->services->get(EncryptionService::class),
        ));
        $this->services->set(WooCommerceHposValidator::class, new WooCommerceHposValidator());
        $this->services->set(WooCommerceHposOrderReader::class, new WooCommerceHposOrderReader());
        $this->services->set(WooCommerceTransferWriter::class, new WooCommerceTransferWriter($systemDatabase));
        $this->services->set(WooCommerceTransferRunner::class, new WooCommerceTransferRunner(
            $this->services->get(WooCommerceIntegrationRepository::class),
            $this->services->get(ConnectionProfileRepository::class),
            $externalPdoFactory,
            $this->services->get(WooCommerceHposValidator::class),
            $this->services->get(WooCommerceHposOrderReader::class),
            $this->services->get(WooCommerceTransferWriter::class),
        ));
        $this->services->set(WooCommerceWebhookHandler::class, new WooCommerceWebhookHandler(
            $this->services->get(WooCommerceIntegrationRepository::class),
        ));
        $this->services->set(WooCommerceExportService::class, new WooCommerceExportService(
            $this->services->get(ExportProfileRepository::class),
            $systemDatabase,
        ));
        $this->services->set(SchemaMetadataRepository::class, new SchemaMetadataRepository($systemDatabase));
        $this->services->set(MappingValidator::class, new MappingValidator(
            $this->services->get(MappingRepository::class),
            $this->services->get(ConnectionProfileRepository::class),
            $externalPdoFactory,
        ));
        $lookupProvider = new PdoLookupValueProvider(
            $this->services->get(ConnectionProfileRepository::class),
            $externalPdoFactory,
        );
        $this->services->set(PdoLookupValueProvider::class, $lookupProvider);
        $this->services->set(MappingFieldResolver::class, new MappingFieldResolver($lookupProvider));
        $this->services->set(MappingRowTransformer::class, new MappingRowTransformer(
            $this->services->get(MappingRepository::class),
            $this->services->get(MappingFieldResolver::class),
        ));
        $this->services->set(TargetWriter::class, new TargetWriter());
        $this->services->set(MappingSourceRowProvider::class, new MappingSourceRowProvider());
        $this->services->set(MappingExecutor::class, new MappingExecutor(
            $this->services->get(MappingRepository::class),
            $this->services->get(MappingValidator::class),
            $this->services->get(ConnectionProfileRepository::class),
            $externalPdoFactory,
            $this->services->get(MappingRowTransformer::class),
            $this->services->get(TargetWriter::class),
            $this->services->get(MappingSourceRowProvider::class),
        ));
        $this->services->set(MappingRunStepRunner::class, new MappingRunStepRunner(
            $this->services->get(MappingExecutor::class),
            $this->services->get(ProcessRunRepository::class),
        ));
        $this->services->set(TargetActionHttpClientInterface::class, new NativeTargetActionHttpClient());
        $this->services->set(TargetActionExecutor::class, new TargetActionExecutor(
            $this->paths,
            $this->services->get(ConnectionProfileRepository::class),
            $externalPdoFactory,
            $this->services->get(TargetActionHttpClientInterface::class),
        ));
        $this->services->set(TargetActionStepRunner::class, new TargetActionStepRunner(
            $this->services->get(TargetActionRepository::class),
            $this->services->get(ProcessRunRepository::class),
            $this->services->get(TargetActionExecutor::class),
        ));
        $this->services->set(SchemaValidator::class, new SchemaValidator());
        $this->services->set(SchemaDefinitionValidator::class, new SchemaDefinitionValidator());
        $this->services->set(SchemaValidationStepRunner::class, new SchemaValidationStepRunner(
            $this->services->get(SchemaRegistryRepository::class),
            $this->services->get(ProcessRunRepository::class),
            $this->services->get(SchemaValidator::class),
        ));
        $this->services->set(ProcessRunner::class, new ProcessRunner(
            $this->services->get(ProcessRepository::class),
            $this->services->get(ProcessRunRepository::class),
            [
                $this->services->get(MappingRunStepRunner::class),
                $this->services->get(TargetActionStepRunner::class),
                $this->services->get(SchemaValidationStepRunner::class),
            ],
        ));
        $this->services->set(TargetActionConfigValidator::class, new TargetActionConfigValidator());
        $this->services->set(TriggerConfigValidator::class, new TriggerConfigValidator());
        $this->services->set(ProcessTriggerService::class, new ProcessTriggerService(
            $this->services->get(ProcessRepository::class),
            $this->services->get(ProcessTriggerRepository::class),
            $this->services->get(TriggerConfigValidator::class),
        ));
        $this->services->set(ProcessTriggerRunner::class, new ProcessTriggerRunner(
            $this->services->get(ProcessTriggerRepository::class),
            $this->services->get(ProcessRepository::class),
            $this->services->get(ProcessRunner::class),
        ));
        $this->services->set(TriggerUrlBuilder::class, new TriggerUrlBuilder(
            $this->services->get(DeploymentTargetRepository::class),
            $this->services->get(DeploymentTargetUrlBuilder::class),
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
        $this->services->set(EndpointJsonResponseFactory::class, new EndpointJsonResponseFactory());
        $this->services->set(EndpointSecretPolicy::class, new EndpointSecretPolicy());
        $this->services->set(EndpointRunner::class, new EndpointRunner(
            $this->services->get(EndpointJsonResponseFactory::class),
            fn (int $mappingSetId): ?array => $this->services->get(MappingRepository::class)->find($mappingSetId),
            fn (int $mappingSetId, ?int $limit): mixed => $this->services->get(MappingExecutor::class)->execute($mappingSetId, true, $limit),
        ));
        $this->services->set(EndpointAccessGuard::class, new EndpointAccessGuard(
            $this->services->get(EndpointRepository::class),
            $this->services->get(AuditLogRepository::class),
            $this->services->get(EndpointSecretPolicy::class),
            $this->services->get(EndpointJsonResponseFactory::class),
        ));
        $this->services->set(EndpointResponseBuilder::class, new EndpointResponseBuilder(
            $this->config,
            $this->services->get(JobRepository::class),
            $this->services->get(JobRunRepository::class),
            $this->services->get(ReportRepository::class),
            $this->services->get(JobRunner::class),
            $this->services->get(EndpointRunner::class),
            $this->services->get(EndpointJsonResponseFactory::class),
        ));
        $this->services->set(EndpointRuntime::class, new EndpointRuntime(
            $this->services->get(EndpointRepository::class),
            $this->services->get(EndpointAccessGuard::class),
            $this->services->get(EndpointResponseBuilder::class),
            $this->services->get(EndpointJsonResponseFactory::class),
            $this->services->get(AuditLogRepository::class),
        ));
        $this->services->set(EndpointRuntimeExporter::class, new EndpointRuntimeExporter(
            $this->services->get(EndpointRepository::class),
            $this->services->get(MappingRepository::class),
            $this->services->get(ConnectionProfileRepository::class),
            $this->services->get(WorkspaceRepository::class),
            $this->paths->basePath(),
        ));
        $this->services->set(EndpointSchemaBuilder::class, new EndpointSchemaBuilder());
        $this->services->set(EndpointExportSanitizer::class, new EndpointExportSanitizer());
        $this->services->set(EndpointExportContractService::class, new EndpointExportContractService(
            $this->services->get(EndpointRepository::class),
            $this->services->get(MappingRepository::class),
            $this->services->get(ConnectionProfileRepository::class),
            $this->services->get(WorkspaceRepository::class),
            $this->services->get(DeploymentTargetRepository::class),
            $this->services->get(DeploymentTargetUrlBuilder::class),
            $this->services->get(EndpointSchemaBuilder::class),
            $this->services->get(EndpointExportSanitizer::class),
            $this->paths->basePath(),
            $this->services->get(SchemaRegistryRepository::class),
        ));
        $this->services->set(EndpointExportArchiveService::class, new EndpointExportArchiveService());
        $this->services->set(IsrPricesExportModule::class, new IsrPricesExportModule());
        $this->services->set(ExportModuleRegistry::class, new ExportModuleRegistry([
            $this->services->get(IsrPricesExportModule::class),
        ]));
        $this->services->set(ExportRuntimeBuilder::class, new ExportRuntimeBuilder(
            $this->services->get(ExportModuleRegistry::class),
            $this->services->get(EndpointRuntimeExporter::class),
            $this->services->get(EndpointExportArchiveService::class),
        ));
        $this->services->set(DatasetRegistry::class, new DatasetRegistry(
            $this->services->get(EndpointRepository::class),
            $this->services->get(MappingRepository::class),
            fn (int $mappingSetId, bool $dryRun, ?int $limit): mixed => $this->services->get(MappingExecutor::class)->execute($mappingSetId, $dryRun, $limit),
        ));
        $this->services->set(SingleTableTransferWriter::class, new SingleTableTransferWriter());
        $this->services->set(DatasetTransferRunner::class, new DatasetTransferRunner(
            $this->services->get(DatasetTransferRepository::class),
            $this->services->get(DatasetRegistry::class),
            $this->services->get(ConnectionProfileRepository::class),
            $this->services->get(SingleTableTransferWriter::class),
            $externalPdoFactory,
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
        $this->services->set('repository.deployment_targets', $this->services->get(DeploymentTargetRepository::class));
        $this->services->set('repository.schema_metadata', $this->services->get(SchemaMetadataRepository::class));
        $this->services->set('repository.mappings', $this->services->get(MappingRepository::class));
        $this->services->set('repository.dataset_transfers', $this->services->get(DatasetTransferRepository::class));
        $this->services->set('repository.audit_log', $this->services->get(AuditLogRepository::class));
        $this->services->set('repository.jobs', $this->services->get(JobRepository::class));
        $this->services->set('repository.job_runs', $this->services->get(JobRunRepository::class));
        $this->services->set('repository.processes', $this->services->get(ProcessRepository::class));
        $this->services->set('repository.process_runs', $this->services->get(ProcessRunRepository::class));
        $this->services->set('repository.process_triggers', $this->services->get(ProcessTriggerRepository::class));
        $this->services->set('repository.target_actions', $this->services->get(TargetActionRepository::class));
        $this->services->set('repository.schemas', $this->services->get(SchemaRegistryRepository::class));
        $this->services->set('repository.reports', $this->services->get(ReportRepository::class));
        $this->services->set('repository.endpoints', $this->services->get(EndpointRepository::class));
        $this->services->set('repository.woocommerce_integrations', $this->services->get(WooCommerceIntegrationRepository::class));
        $this->services->set('repository.export_profiles', $this->services->get(ExportProfileRepository::class));
        $this->services->set('dataset.registry', $this->services->get(DatasetRegistry::class));
        $this->services->set('dataset.transfer_runner', $this->services->get(DatasetTransferRunner::class));
        $this->services->set('mapping.validator', $this->services->get(MappingValidator::class));
        $this->services->set('mapping.executor', $this->services->get(MappingExecutor::class));
        $this->services->set('transfer.mapping_executor', $this->services->get(MappingExecutor::class));
        $this->services->set('process.runner', $this->services->get(ProcessRunner::class));
        $this->services->set('process.target_action_runner', $this->services->get(TargetActionStepRunner::class));
        $this->services->set('process.schema_validation_runner', $this->services->get(SchemaValidationStepRunner::class));
        $this->services->set('target_actions.validator', $this->services->get(TargetActionConfigValidator::class));
        $this->services->set('target_actions.executor', $this->services->get(TargetActionExecutor::class));
        $this->services->set('schemas.definition_validator', $this->services->get(SchemaDefinitionValidator::class));
        $this->services->set('schemas.validator', $this->services->get(SchemaValidator::class));
        $this->services->set('process.trigger_service', $this->services->get(ProcessTriggerService::class));
        $this->services->set('process.trigger_runner', $this->services->get(ProcessTriggerRunner::class));
        $this->services->set('process.trigger_url_builder', $this->services->get(TriggerUrlBuilder::class));
        $this->services->set('jobs.runner', $this->services->get(JobRunner::class));
        $this->services->set('reports.engine', $this->services->get(ReportEngine::class));
        $this->services->set('reports.mailer', $this->services->get(ReportMailer::class));
        $this->services->set('api.endpoint_guard', $this->services->get(EndpointAccessGuard::class));
        $this->services->set('api.endpoint_response_factory', $this->services->get(EndpointJsonResponseFactory::class));
        $this->services->set('api.endpoint_runner', $this->services->get(EndpointRunner::class));
        $this->services->set('api.endpoint_response_builder', $this->services->get(EndpointResponseBuilder::class));
        $this->services->set('api.endpoint_runtime', $this->services->get(EndpointRuntime::class));
        $this->services->set('export.endpoint_runtime', $this->services->get(EndpointRuntimeExporter::class));
        $this->services->set('export.endpoint_contract', $this->services->get(EndpointExportContractService::class));
        $this->services->set('deployment.target_url_builder', $this->services->get(DeploymentTargetUrlBuilder::class));
        $this->services->set('export.endpoint_archive', $this->services->get(EndpointExportArchiveService::class));
        $this->services->set('integration.export_modules', $this->services->get(ExportModuleRegistry::class));
        $this->services->set('integration.export_runtime_builder', $this->services->get(ExportRuntimeBuilder::class));
        $this->services->set('woocommerce.hpos_validator', $this->services->get(WooCommerceHposValidator::class));
        $this->services->set('woocommerce.transfer_runner', $this->services->get(WooCommerceTransferRunner::class));
        $this->services->set('woocommerce.webhook_handler', $this->services->get(WooCommerceWebhookHandler::class));
        $this->services->set('woocommerce.export_service', $this->services->get(WooCommerceExportService::class));
    }
}
