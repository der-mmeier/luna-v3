<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Deployment\DeploymentTargetUrlBuilder;
use Luna\Export\EndpointExportContractService;
use Luna\Export\EndpointExportSanitizer;
use Luna\Export\EndpointSchemaBuilder;
use Luna\Process\ProcessRunner;
use Luna\Process\ProcessStepResult;
use Luna\Process\ProcessStepRunnerInterface;
use Luna\Process\SchemaValidationStepRunner;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\DeploymentTargetRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\MappingRepository;
use Luna\Repository\ProcessRepository;
use Luna\Repository\ProcessRunRepository;
use Luna\Repository\SchemaRegistryRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Schema\SchemaDefinitionValidator;
use Luna\Schema\SchemaValidator;
use Luna\Security\EncryptionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class SchemaRegistryValidationTest extends TestCase
{
    public function testMigrationDefinesSchemaRegistryTables(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_06_08_000019_create_schema_registry.sql');

        self::assertIsString($migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS luna_schemas', $migration);
        self::assertStringContainsString('luna_schema_revisions', $migration);
        self::assertStringContainsString('schema_id', $migration);
    }

    public function testSchemaCanBeCreatedAndDuplicateVersionIsDetected(): void
    {
        $schemas = new SchemaRegistryRepository($this->database(), $this->pdo());
        $id = $schemas->create($this->schemaData());

        $schema = $schemas->find($id);

        self::assertNotNull($schema);
        self::assertSame('isr_prices', $schema['schema_key']);
        self::assertTrue($schemas->existsVersion(1, 'isr_prices', '1'));
        self::assertCount(1, $schemas->revisionsForSchema($id));
    }

    public function testDefinitionValidatorRejectsInvalidJson(): void
    {
        $errors = (new SchemaDefinitionValidator())->validateForm([
            'workspace_id' => 1,
            'name' => 'Broken',
            'schema_key' => 'broken',
            'version' => '1',
            'status' => 'active',
            'definition_json' => '{broken',
            'example_json' => '',
        ], new SchemaRegistryRepository($this->database(), $this->pdo()));

        self::assertNotEmpty($errors);
    }

    public function testValidatorAcceptsValidNestedData(): void
    {
        $result = (new SchemaValidator())->validate($this->validPayload(), $this->definition());

        self::assertTrue($result['valid']);
        self::assertSame([], $result['errors']);
    }

    public function testValidatorFindsMissingRequiredFieldAndWrongNestedType(): void
    {
        $payload = $this->validPayload();
        unset($payload['items'][0]['model']);
        $payload['items'][0]['dr_quantities']['48'] = 'two';

        $result = (new SchemaValidator())->validate($payload, $this->definition());
        $encoded = json_encode($result['errors'], JSON_THROW_ON_ERROR);

        self::assertFalse($result['valid']);
        self::assertStringContainsString('items[0].model', $encoded);
        self::assertStringContainsString('items[0].dr_quantities.48', $encoded);
    }

    public function testSchemaValidationStepCanValidatePreviousResult(): void
    {
        $pdo = $this->pdo();
        $schemas = new SchemaRegistryRepository($this->database(), $pdo);
        $schemaId = $schemas->create($this->schemaData());
        $processes = new ProcessRepository($this->database(), $pdo);
        $runs = new ProcessRunRepository($this->database(), $pdo);
        $processId = $this->processWithSchemaValidation($processes, $schemaId);
        $runner = new ProcessRunner($processes, $runs, [
            new PayloadStepRunner($this->validPayload()),
            new SchemaValidationStepRunner($schemas, $runs, new SchemaValidator()),
        ]);

        $runId = $runner->run($processId, 'dry_run', 'manual');
        $run = $runs->findRun($runId);
        $logs = json_encode($runs->logsForRun($runId), JSON_THROW_ON_ERROR);

        self::assertNotNull($run);
        self::assertSame('success', $run['status']);
        self::assertStringContainsString('Schema-Validierung', $logs);
    }

    public function testEndpointContractExportReferencesRegisteredSchema(): void
    {
        $pdo = $this->pdo();
        $schemas = new SchemaRegistryRepository($this->database(), $pdo);
        $schemaId = $schemas->create($this->schemaData());
        $pdo->exec('UPDATE luna_endpoints SET schema_id = ' . $schemaId . ' WHERE id = 5');
        $target = $this->tempDirectory();

        $result = $this->exportService($pdo, $schemas)->exportEndpoint(5, null, $target);
        $path = (string) $result['absolute_target_path'];
        $manifest = json_decode((string) file_get_contents($path . '/manifest.json'), true);
        $schema = json_decode((string) file_get_contents($path . '/schema.json'), true);
        $endpoint = json_decode((string) file_get_contents($path . '/endpoint.json'), true);

        self::assertSame('isr_prices', $manifest['schema']['schema_key']);
        self::assertSame('1', $endpoint['schema']['version']);
        self::assertSame('isr_prices', $schema['schema_key']);
        self::assertSame('object', $schema['definition']['type']);
    }

    public function testExistingCliCommandsRemainRegisteredAndSchemaValidateIsAdded(): void
    {
        $bin = file_get_contents(dirname(__DIR__, 2) . '/bin/luna');
        self::assertIsString($bin);
        foreach ([
            'migrate',
            'db:test',
            'connection:test',
            'job:run',
            'mapping:dry-run',
            'mapping:run',
            'dataset:list',
            'dataset:preview',
            'transfer:dry-run',
            'transfer:run',
            'woocommerce:transfer:run',
            'export:woocommerce:list',
            'export:woocommerce:run',
            'endpoint:export',
            'integration:export',
            'process:run',
            'trigger:list',
            'trigger:run',
            'schema:validate',
        ] as $command) {
            self::assertStringContainsString($command, $bin);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaData(): array
    {
        return [
            'workspace_id' => 1,
            'name' => 'ISR Prices Schema',
            'schema_key' => 'isr_prices',
            'version' => '1',
            'status' => 'active',
            'description' => 'ISR-ähnlicher Preis-Endpunkt.',
            'definition_json' => json_encode($this->definition(), JSON_THROW_ON_ERROR),
            'example_json' => json_encode($this->validPayload(), JSON_THROW_ON_ERROR),
            'change_summary' => 'Initiale Version',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(): array
    {
        return [
            'type' => 'object',
            'fields' => [
                'success' => ['type' => 'boolean', 'required' => true],
                'generated_at' => ['type' => 'string', 'required' => true, 'format' => 'datetime'],
                'count' => ['type' => 'integer', 'required' => true],
                'items' => [
                    'type' => 'array',
                    'required' => true,
                    'items' => [
                        'type' => 'object',
                        'fields' => [
                            'model' => ['type' => 'string', 'required' => true],
                            'price_group' => ['type' => 'string', 'required' => true],
                            'price' => ['type' => 'number', 'required' => true],
                            'pseudo_price' => ['type' => 'number', 'required' => false],
                            'dr_quantities' => [
                                'type' => 'object',
                                'required' => false,
                                'additional_properties' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'success' => true,
            'generated_at' => '2026-06-06T20:05:55+00:00',
            'count' => 1,
            'items' => [[
                'model' => 'S001',
                'price_group' => '1',
                'price' => 85.0,
                'pseudo_price' => 170.0,
                'dr_quantities' => ['48' => 2],
            ]],
        ];
    }

    private function processWithSchemaValidation(ProcessRepository $processes, int $schemaId): int
    {
        $processId = $processes->create([
            'workspace_id' => 1,
            'name' => 'Validate Payload',
            'process_key' => 'validate_payload',
            'status' => 'active',
            'default_mode' => 'dry_run',
        ]);
        $processes->addStep($processId, [
            'position' => 10,
            'name' => 'Payload',
            'step_type' => 'mapping_run',
            'reference_id' => 33,
            'is_enabled' => '1',
        ]);
        $processes->addStep($processId, [
            'position' => 20,
            'name' => 'Schema prüfen',
            'step_type' => 'schema_validation',
            'reference_id' => $schemaId,
            'is_enabled' => '1',
        ]);

        return $processId;
    }

    private function exportService(PDO $pdo, SchemaRegistryRepository $schemas): EndpointExportContractService
    {
        $database = $this->database();
        $encryption = new EncryptionService(new Config());
        $urlBuilder = new DeploymentTargetUrlBuilder();

        return new EndpointExportContractService(
            new EndpointRepository($database, $encryption, $pdo),
            new MappingRepository($database, $pdo),
            new ConnectionProfileRepository($database, $encryption, $pdo),
            new WorkspaceRepository($database, $pdo),
            new DeploymentTargetRepository($database, $urlBuilder, $pdo),
            $urlBuilder,
            new EndpointSchemaBuilder(),
            new EndpointExportSanitizer(),
            $this->tempDirectory(),
            $schemas,
        );
    }

    private function database(): SystemDatabase
    {
        return new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT, name TEXT)');
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'asfinstocks', 'AsfInStocks')");
        $pdo->exec('CREATE TABLE luna_schemas (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER, schema_key TEXT, version TEXT, name TEXT, description TEXT, definition_json TEXT, example_json TEXT, status TEXT, created_at TEXT, updated_at TEXT, UNIQUE(workspace_id, schema_key, version))');
        $pdo->exec('CREATE TABLE luna_schema_revisions (id INTEGER PRIMARY KEY AUTOINCREMENT, schema_id INTEGER, version TEXT, definition_json TEXT, example_json TEXT, change_summary TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE luna_processes (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER, name TEXT, process_key TEXT, description TEXT, status TEXT, default_mode TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_process_steps (id INTEGER PRIMARY KEY AUTOINCREMENT, process_id INTEGER, position INTEGER, name TEXT, step_type TEXT, reference_type TEXT, reference_id INTEGER, config_json TEXT, is_enabled INTEGER, continue_on_error INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_process_triggers (id INTEGER PRIMARY KEY AUTOINCREMENT, process_id INTEGER, workspace_id INTEGER, name TEXT, trigger_type TEXT, trigger_key TEXT, is_active INTEGER, config_json TEXT, secret_hash TEXT, last_triggered_at TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_process_runs (id INTEGER PRIMARY KEY AUTOINCREMENT, process_id INTEGER, status TEXT, mode TEXT, trigger_type TEXT, trigger_ref TEXT, trigger_id INTEGER, trigger_source TEXT, trigger_payload_meta TEXT, started_at TEXT, finished_at TEXT, duration_ms INTEGER, error_message TEXT, context_json TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_process_run_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, process_run_id INTEGER, level TEXT, message TEXT, context_json TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, type TEXT, driver TEXT, host TEXT, port INTEGER, database_name TEXT, username TEXT, read_only INTEGER)');
        $pdo->exec('CREATE TABLE luna_connection_secrets (connection_profile_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT)');
        $pdo->exec('CREATE TABLE luna_jobs (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE luna_endpoints (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, endpoint_key TEXT, method TEXT, status TEXT, secret_mode TEXT, secret_hash TEXT, source_type TEXT, mapping_set_id INTEGER, schema_id INTEGER, job_id INTEGER, config_json TEXT, cache_enabled INTEGER, cache_ttl_seconds INTEGER)');
        $pdo->exec('CREATE TABLE luna_endpoint_secrets (endpoint_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_sets (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, mapping_mode TEXT, source_connection_id INTEGER, source_table TEXT, target_connection_id INTEGER, target_table TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_fields (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, target_column TEXT, transform_type TEXT, default_value TEXT, lookup_connection_id INTEGER, lookup_table TEXT, lookup_key_column TEXT, lookup_value_column TEXT, lookup_key_template TEXT, lookup_result_mode TEXT, fallback_value TEXT, missing_behavior TEXT, notes TEXT, schema_type TEXT, schema_required INTEGER, schema_description TEXT, schema_example TEXT, sort_order INTEGER)');
        $pdo->exec('CREATE TABLE luna_mapping_source_filters (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, operator TEXT, filter_value TEXT, sort_order INTEGER)');
        $pdo->exec('CREATE TABLE luna_deployment_targets (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER NULL, name TEXT, environment TEXT, public_base_url TEXT, endpoint_base_url TEXT NULL, webhook_base_url TEXT NULL, license_server_url TEXT NULL, is_default INTEGER, is_active INTEGER, origin TEXT, support_status TEXT, module_key TEXT NULL, requires_entitlement INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, driver, host, port, database_name, username, read_only) VALUES (1, 1, 'PIMCORE', 'database', 'mysql', 'localhost', 3306, 'db', 'user', 1)");
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, method, status, secret_mode, secret_hash, source_type, mapping_set_id, schema_id, job_id, config_json, cache_enabled, cache_ttl_seconds) VALUES (5, 1, 'ISR Prices', 'isr_prices', 'GET', 'active', 'none', NULL, 'mapping', 33, NULL, NULL, '', 0, NULL)");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name, mapping_mode, source_connection_id, source_table, target_connection_id, target_table) VALUES (33, 1, 'ISR Prices Export', 'json_endpoint', 1, 'objects', NULL, NULL)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, schema_type, schema_required, sort_order) VALUES (1, 33, 'model', 'model', 'direct', 'string', 1, 0)");

        return $pdo;
    }

    private function tempDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/luna_schema_' . bin2hex(random_bytes(6));
        mkdir($directory, 0775, true);

        return str_replace('\\', '/', $directory);
    }
}

final class PayloadStepRunner implements ProcessStepRunnerInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private readonly array $payload)
    {
    }

    public function supports(string $stepType): bool
    {
        return $stepType === 'mapping_run';
    }

    public function run(array $process, array $step, int $processRunId, string $mode): ProcessStepResult
    {
        return ProcessStepResult::success('Payload erzeugt.', ['result' => $this->payload]);
    }
}
