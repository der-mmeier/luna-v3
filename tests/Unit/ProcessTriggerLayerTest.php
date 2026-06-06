<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Core\Application;
use Luna\Core\Paths;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Deployment\DeploymentTargetUrlBuilder;
use Luna\Http\Request;
use Luna\Process\ProcessRunner;
use Luna\Process\ProcessStepResult;
use Luna\Process\ProcessStepRunnerInterface;
use Luna\Process\ProcessTriggerException;
use Luna\Process\ProcessTriggerRunner;
use Luna\Process\ProcessTriggerService;
use Luna\Process\TriggerConfigValidator;
use Luna\Process\TriggerUrlBuilder;
use Luna\Repository\DeploymentTargetRepository;
use Luna\Repository\ProcessRepository;
use Luna\Repository\ProcessRunRepository;
use Luna\Repository\ProcessTriggerRepository;
use Luna\Routing\RouteCollection;
use Luna\Routing\Router;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProcessTriggerLayerTest extends TestCase
{
    public function testMigrationContainsTriggerTableAndRunContextColumns(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_06_06_000017_create_process_triggers.sql');

        self::assertIsString($migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS luna_process_triggers', $migration);
        self::assertStringContainsString('trigger_id', $migration);
        self::assertStringContainsString('trigger_payload_meta', $migration);
    }

    public function testTriggerTypesAreValidated(): void
    {
        $validator = new TriggerConfigValidator();

        self::assertSame([], $validator->validate([
            'process_id' => 1,
            'name' => 'Webhook',
            'trigger_type' => 'webhook',
            'trigger_key' => 'isr-webhook-test',
            'config_json' => '{"mode":"daily"}',
        ]));

        self::assertNotEmpty($validator->validate([
            'process_id' => 1,
            'name' => 'Broken',
            'trigger_type' => 'woocommerce_special',
        ]));
    }

    public function testActiveTriggerStartsProcessAndStoresContext(): void
    {
        [$processes, $runs, $triggers] = $this->repositories($this->pdo());
        $processId = $this->activeProcessWithStep($processes);
        $triggerId = $triggers->create([
            'process_id' => $processId,
            'workspace_id' => 1,
            'name' => 'ISR Webhook',
            'trigger_type' => 'webhook',
            'trigger_key' => 'isr-webhook-test',
            'is_active' => '1',
        ], 'unit-secret');

        $runId = $this->triggerRunner($processes, $runs, $triggers, new TriggerSuccessStepRunner())->runByIdentifier(
            'isr-webhook-test',
            'run',
            'webhook',
            'unit-secret',
            ['method' => 'POST', 'payload_hash' => hash('sha256', '{}')],
            $processId,
            'webhook',
        );
        $run = $runs->findRun($runId);

        self::assertNotNull($run);
        self::assertSame('success', $run['status']);
        self::assertSame($triggerId, (int) $run['trigger_id']);
        self::assertSame('webhook', $run['trigger_type']);
        self::assertSame('webhook', $run['trigger_source']);
        self::assertStringContainsString('payload_hash', (string) $run['trigger_payload_meta']);
        self::assertNotNull($triggers->find($triggerId)['last_triggered_at'] ?? null);
    }

    public function testInactiveTriggerCannotStartProcess(): void
    {
        [$processes, $runs, $triggers] = $this->repositories($this->pdo());
        $processId = $this->activeProcessWithStep($processes);
        $triggers->create([
            'process_id' => $processId,
            'workspace_id' => 1,
            'name' => 'Inactive API',
            'trigger_type' => 'api',
            'trigger_key' => 'inactive-api',
            'is_active' => '',
        ]);

        $this->expectException(ProcessTriggerException::class);
        $this->expectExceptionMessage('Trigger ist inaktiv.');

        $this->triggerRunner($processes, $runs, $triggers, new TriggerSuccessStepRunner())->runByIdentifier('inactive-api', 'run', 'api');
    }

    public function testFailedProcessRunViaTriggerIsStoredAsFailed(): void
    {
        [$processes, $runs, $triggers] = $this->repositories($this->pdo());
        $processId = $this->activeProcessWithStep($processes);
        $triggers->create([
            'process_id' => $processId,
            'workspace_id' => 1,
            'name' => 'CLI Trigger',
            'trigger_type' => 'cli',
            'trigger_key' => 'cli-trigger',
            'is_active' => '1',
        ]);

        $runId = $this->triggerRunner($processes, $runs, $triggers, new TriggerFailingStepRunner())->runByIdentifier('cli-trigger', 'run', 'cli');
        $run = $runs->findRun($runId);

        self::assertNotNull($run);
        self::assertSame('failed', $run['status']);
        self::assertStringContainsString('Trigger test failure', (string) $run['error_message']);
    }

    public function testApiAndWebhookRoutesReturnJsonWithoutWooCommerceProcessing(): void
    {
        $pdo = $this->pdo();
        [$processes, $runs, $triggers] = $this->repositories($pdo);
        $processId = $this->activeProcessWithStep($processes);
        $triggers->create([
            'process_id' => $processId,
            'workspace_id' => 1,
            'name' => 'API Trigger',
            'trigger_type' => 'api',
            'trigger_key' => 'api-trigger',
            'is_active' => '1',
        ]);
        $triggers->create([
            'process_id' => $processId,
            'workspace_id' => 1,
            'name' => 'Webhook Trigger',
            'trigger_type' => 'webhook',
            'trigger_key' => 'webhook-trigger',
            'is_active' => '1',
        ]);

        $app = new Application(new Paths(dirname(__DIR__, 2)), new Config());
        $app->services()->set(ProcessRunRepository::class, $runs);
        $app->services()->set(ProcessTriggerService::class, new ProcessTriggerService($processes, $triggers, new TriggerConfigValidator()));
        $app->services()->set(ProcessTriggerRunner::class, $this->triggerRunner($processes, $runs, $triggers, new TriggerSuccessStepRunner()));

        $routes = new RouteCollection();
        $loader = require dirname(__DIR__, 2) . '/routes/api.php';
        $loader($routes, $app);
        $router = new Router($routes);

        $apiResponse = $router->dispatch(new Request('POST', '/api/process-triggers/api-trigger/run'));
        $webhookResponse = $router->dispatch(new Request('POST', '/api/webhooks/webhook-trigger', [], [], [], [
            'x-wc-webhook-topic' => 'order.created',
        ]));

        self::assertSame(201, $apiResponse->statusCode());
        self::assertSame(201, $webhookResponse->statusCode());
        self::assertStringContainsString('"success": true', $apiResponse->body());
        self::assertStringNotContainsString('WooCommerceWebhookHandler', $webhookResponse->body());
    }

    public function testWebhookUrlUsesWebhookBaseUrlAndFallback(): void
    {
        $pdo = $this->pdo();
        $deploymentTargets = new DeploymentTargetRepository(
            new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory()),
            new DeploymentTargetUrlBuilder(),
            $pdo,
        );
        $withWebhook = $deploymentTargets->create([
            'workspace_id' => 1,
            'name' => 'Production',
            'environment' => 'production',
            'public_base_url' => 'https://toolbox.asf.gmbh/luna',
            'webhook_base_url' => 'https://hooks.asf.gmbh/luna',
            'is_default' => '1',
            'is_active' => '1',
        ]);
        $builder = new TriggerUrlBuilder($deploymentTargets, new DeploymentTargetUrlBuilder());

        self::assertSame('https://hooks.asf.gmbh/luna/isr-webhook-test', $builder->webhookUrl($deploymentTargets->find($withWebhook), 'isr-webhook-test'));
        self::assertSame('https://toolbox.asf.gmbh/luna/api/process-triggers/api-trigger/run', $builder->apiUrl($deploymentTargets->find($withWebhook), 'api-trigger'));
    }

    public function testCliUsageKeepsExistingCommandsAndAddsTriggerCommands(): void
    {
        $bin = file_get_contents(dirname(__DIR__, 2) . '/bin/luna');
        self::assertIsString($bin);

        foreach ([
            'endpoint:export',
            'integration:export',
            'export:woocommerce:list',
            'export:woocommerce:run',
            'process:run',
            '--trigger=',
            'trigger:list',
            'trigger:run',
        ] as $command) {
            self::assertStringContainsString($command, $bin);
        }
    }

    /**
     * @return array{0: ProcessRepository, 1: ProcessRunRepository, 2: ProcessTriggerRepository}
     */
    private function repositories(PDO $pdo): array
    {
        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());

        return [
            new ProcessRepository($database, $pdo),
            new ProcessRunRepository($database, $pdo),
            new ProcessTriggerRepository($database, $pdo),
        ];
    }

    private function triggerRunner(
        ProcessRepository $processes,
        ProcessRunRepository $runs,
        ProcessTriggerRepository $triggers,
        ProcessStepRunnerInterface $stepRunner,
    ): ProcessTriggerRunner {
        return new ProcessTriggerRunner(
            $triggers,
            $processes,
            new ProcessRunner($processes, $runs, [$stepRunner]),
        );
    }

    private function activeProcessWithStep(ProcessRepository $processes): int
    {
        $processId = $processes->create([
            'workspace_id' => 1,
            'name' => 'Trigger Testprozess',
            'process_key' => 'trigger_test_process',
            'status' => 'active',
            'default_mode' => 'run',
        ]);
        $processes->addStep($processId, [
            'position' => 10,
            'name' => 'Testschritt',
            'step_type' => 'mapping_run',
            'reference_id' => 33,
            'is_enabled' => '1',
        ]);

        return $processId;
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT, name TEXT)');
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'test', 'Test')");
        $pdo->exec('CREATE TABLE luna_deployment_targets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            name TEXT NOT NULL,
            environment TEXT NOT NULL,
            public_base_url TEXT NOT NULL,
            endpoint_base_url TEXT NULL,
            webhook_base_url TEXT NULL,
            license_server_url TEXT NULL,
            is_default INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            origin TEXT NOT NULL DEFAULT "customer_created",
            support_status TEXT NOT NULL DEFAULT "unverified",
            module_key TEXT NULL,
            requires_entitlement INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_processes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            process_key TEXT NOT NULL,
            description TEXT NULL,
            status TEXT NOT NULL DEFAULT "draft",
            default_mode TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_process_steps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            process_id INTEGER NOT NULL,
            position INTEGER NOT NULL DEFAULT 10,
            name TEXT NOT NULL,
            step_type TEXT NOT NULL,
            reference_type TEXT NULL,
            reference_id INTEGER NULL,
            config_json TEXT NULL,
            is_enabled INTEGER NOT NULL DEFAULT 1,
            continue_on_error INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_process_triggers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            process_id INTEGER NOT NULL,
            workspace_id INTEGER NULL,
            name TEXT NOT NULL,
            trigger_type TEXT NOT NULL,
            trigger_key TEXT NOT NULL UNIQUE,
            is_active INTEGER NOT NULL DEFAULT 1,
            config_json TEXT NULL,
            secret_hash TEXT NULL,
            last_triggered_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_process_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            process_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT "queued",
            mode TEXT NOT NULL DEFAULT "run",
            trigger_type TEXT NOT NULL DEFAULT "manual",
            trigger_ref TEXT NULL,
            trigger_id INTEGER NULL,
            trigger_source TEXT NULL,
            trigger_payload_meta TEXT NULL,
            started_at TEXT NULL,
            finished_at TEXT NULL,
            duration_ms INTEGER NULL,
            error_message TEXT NULL,
            context_json TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_process_run_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            process_run_id INTEGER NOT NULL,
            level TEXT NOT NULL,
            message TEXT NOT NULL,
            context_json TEXT NULL,
            created_at TEXT NOT NULL
        )');

        return $pdo;
    }
}

final class TriggerSuccessStepRunner implements ProcessStepRunnerInterface
{
    public function supports(string $stepType): bool
    {
        return $stepType === 'mapping_run';
    }

    public function run(array $process, array $step, int $processRunId, string $mode): ProcessStepResult
    {
        return ProcessStepResult::success('Trigger step completed.', [
            'run_id' => $processRunId,
            'mode' => $mode,
        ]);
    }
}

final class TriggerFailingStepRunner implements ProcessStepRunnerInterface
{
    public function supports(string $stepType): bool
    {
        return $stepType === 'mapping_run';
    }

    public function run(array $process, array $step, int $processRunId, string $mode): ProcessStepResult
    {
        throw new RuntimeException('Trigger test failure.');
    }
}
