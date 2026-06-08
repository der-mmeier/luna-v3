<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Deployment\DeploymentTargetUrlBuilder;
use Luna\Http\Request;
use Luna\Process\ProcessRunner;
use Luna\Process\ProcessStepContextAwareRunnerInterface;
use Luna\Process\ProcessStepResult;
use Luna\Process\ProcessTriggerRunner;
use Luna\Process\TriggerUrlBuilder;
use Luna\Repository\DeploymentTargetRepository;
use Luna\Repository\ProcessRepository;
use Luna\Repository\ProcessRunRepository;
use Luna\Repository\ProcessTriggerRepository;
use Luna\Repository\WooCommerceRuntimeEventRepository;
use Luna\Security\EncryptionService;
use Luna\WooCommerce\WooCommerceRuntimeWebhookHandler;
use Luna\WooCommerce\WooCommerceWebhookEventNormalizer;
use Luna\WooCommerce\WooCommerceWebhookSignatureVerifier;
use PDO;
use PHPUnit\Framework\TestCase;

final class WooCommerceRuntimeModuleTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = 'unit-test-app-key';
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
    }

    public function testMigrationContainsWooCommerceRuntimeEventsAndEncryptedTriggerSecret(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_06_08_000020_create_woocommerce_runtime_events.sql');

        self::assertIsString($migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS luna_woocommerce_runtime_events', $migration);
        self::assertStringContainsString('process_trigger_id', $migration);
        self::assertStringContainsString('secret_encrypted', $migration);
    }

    public function testWooCommerceWebhookUrlUsesDeploymentTargetWebhookBaseUrl(): void
    {
        $pdo = $this->pdo();
        $deploymentTargets = new DeploymentTargetRepository($this->database(), new DeploymentTargetUrlBuilder(), $pdo);
        $targetId = $deploymentTargets->create([
            'workspace_id' => 1,
            'name' => 'Production',
            'environment' => 'production',
            'public_base_url' => 'https://toolbox.asf.gmbh/luna',
            'webhook_base_url' => 'https://toolbox.asf.gmbh/luna/api/webhooks',
            'is_default' => '1',
            'is_active' => '1',
        ]);

        $builder = new TriggerUrlBuilder($deploymentTargets, new DeploymentTargetUrlBuilder());

        self::assertSame(
            'https://toolbox.asf.gmbh/luna/api/webhooks/woocommerce/wc-order-updated',
            $builder->urlForTrigger([
                'trigger_type' => 'webhook',
                'trigger_key' => 'wc-order-updated',
                'config_json' => '{"provider":"woocommerce"}',
            ], $deploymentTargets->find($targetId)),
        );
    }

    public function testWooCommerceSignatureVerifierAcceptsValidSignature(): void
    {
        $verifier = new WooCommerceWebhookSignatureVerifier();
        $rawBody = '{"id":10001,"status":"processing"}';
        $signature = base64_encode(hash_hmac('sha256', $rawBody, 'webhook-secret', true));

        self::assertTrue($verifier->verify($rawBody, 'webhook-secret', $signature));
        self::assertFalse($verifier->verify($rawBody, 'webhook-secret', 'invalid-signature'));
    }

    public function testMissingWooCommerceTriggerReturnsNotFound(): void
    {
        [$pdo, $handler] = $this->fixture();

        $result = $handler->handle($this->request(), 'missing-trigger', '{}');

        self::assertSame(404, $result['status']);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_process_runs')->fetchColumn());
    }

    public function testInactiveWooCommerceTriggerDoesNotStartProcess(): void
    {
        [$pdo, $handler, $triggers] = $this->fixture();
        $processId = $this->activeProcessWithStep(new ProcessRepository($this->database(), $pdo));
        $triggers->create([
            'process_id' => $processId,
            'workspace_id' => 1,
            'name' => 'Inactive WooCommerce Trigger',
            'trigger_type' => 'webhook',
            'trigger_key' => 'inactive-wc',
            'is_active' => '',
            'config_json' => '{"provider":"woocommerce","topic":"order.updated","allow_unsigned":false}',
        ], 'webhook-secret');

        $result = $handler->handle($this->request(), 'inactive-wc', '{"id":10001}');

        self::assertSame(409, $result['status']);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_process_runs')->fetchColumn());
    }

    public function testInvalidWooCommerceSignatureIsRejectedWithoutProcessRun(): void
    {
        [$pdo, $handler, $triggers] = $this->fixture();
        $processId = $this->activeProcessWithStep(new ProcessRepository($this->database(), $pdo));
        $triggers->create([
            'process_id' => $processId,
            'workspace_id' => 1,
            'name' => 'WooCommerce Order Updated',
            'trigger_type' => 'webhook',
            'trigger_key' => 'wc-order-updated',
            'is_active' => '1',
            'config_json' => '{"provider":"woocommerce","topic":"order.updated","allow_unsigned":false}',
        ], 'webhook-secret');

        $result = $handler->handle($this->request(['x-wc-webhook-signature' => 'invalid']), 'wc-order-updated', '{"id":10001}');

        self::assertSame(401, $result['status']);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_process_runs')->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM luna_woocommerce_runtime_events WHERE processing_status = 'rejected' AND signature_valid = 0")->fetchColumn());
    }

    public function testValidWooCommerceWebhookCreatesProcessRunAndMetadata(): void
    {
        [$pdo, $handler, $triggers] = $this->fixture();
        $processId = $this->activeProcessWithStep(new ProcessRepository($this->database(), $pdo));
        $triggerId = $triggers->create([
            'process_id' => $processId,
            'workspace_id' => 1,
            'name' => 'WooCommerce Order Updated',
            'trigger_type' => 'webhook',
            'trigger_key' => 'wc-order-updated',
            'is_active' => '1',
            'config_json' => '{"provider":"woocommerce","topic":"order.updated","allow_unsigned":false,"payload_log_mode":"summary"}',
        ], 'webhook-secret');
        $rawBody = '{"id":10001,"status":"processing","order_key":"wc_order_secret","total":"99.95"}';
        $signature = base64_encode(hash_hmac('sha256', $rawBody, 'webhook-secret', true));

        $result = $handler->handle($this->request(['x-wc-webhook-signature' => $signature]), 'wc-order-updated', $rawBody);

        self::assertSame(201, $result['status']);
        self::assertTrue($result['payload']['success']);
        $runId = (int) $result['payload']['process_run_id'];
        $run = (new ProcessRunRepository($this->database(), $pdo))->findRun($runId);
        self::assertNotNull($run);
        self::assertSame('success', $run['status']);
        self::assertSame($triggerId, (int) $run['trigger_id']);
        self::assertSame('webhook', $run['trigger_source']);

        $meta = json_decode((string) $run['trigger_payload_meta'], true);
        self::assertSame('woocommerce', $meta['provider'] ?? null);
        self::assertSame('order.updated', $meta['topic'] ?? null);
        self::assertSame('delivery-123', $meta['delivery_id'] ?? null);
        self::assertTrue((bool) ($meta['signature_valid'] ?? false));

        $context = json_decode((string) $run['context_json'], true);
        self::assertSame('woocommerce', $context['woocommerce_event']['provider'] ?? null);
        self::assertSame('woocommerce', $context['step_results']['1']['result']['provider_seen'] ?? null);
        self::assertStringNotContainsString('webhook-secret', (string) $run['context_json']);
        self::assertStringNotContainsString('wc_order_secret', (string) $run['trigger_payload_meta']);

        $storedSummary = (string) $pdo->query('SELECT payload_summary_json FROM luna_woocommerce_runtime_events WHERE id = 1')->fetchColumn();
        self::assertStringContainsString('***', $storedSummary);
        self::assertStringNotContainsString('wc_order_secret', $storedSummary);
    }

    public function testExistingWooCommerceExportAndProcessCommandsStayRegistered(): void
    {
        $bin = file_get_contents(dirname(__DIR__, 2) . '/bin/luna');
        self::assertIsString($bin);

        foreach ([
            'endpoint:export',
            'integration:export',
            'export:woocommerce:list',
            'export:woocommerce:run',
            'process:run',
        ] as $command) {
            self::assertStringContainsString($command, $bin);
        }
    }

    /**
     * @return array{0: PDO, 1: WooCommerceRuntimeWebhookHandler, 2: ProcessTriggerRepository}
     */
    private function fixture(): array
    {
        $pdo = $this->pdo();
        $database = $this->database();
        $processes = new ProcessRepository($database, $pdo);
        $runs = new ProcessRunRepository($database, $pdo);
        $triggers = new ProcessTriggerRepository($database, $pdo, new EncryptionService(new Config()));
        $runner = new ProcessTriggerRunner(
            $triggers,
            $processes,
            new ProcessRunner($processes, $runs, [new WooCommerceRuntimeTestStepRunner()]),
        );

        return [$pdo, new WooCommerceRuntimeWebhookHandler(
            $triggers,
            $runner,
            $runs,
            new WooCommerceRuntimeEventRepository($database, $pdo),
            new WooCommerceWebhookSignatureVerifier(),
            new WooCommerceWebhookEventNormalizer(),
        ), $triggers];
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(array $headers = []): Request
    {
        return new Request('POST', '/api/webhooks/woocommerce/wc-order-updated', [], [], [
            'HTTP_HOST' => 'shop.example.test',
        ], $headers + [
            'x-wc-webhook-topic' => 'order.updated',
            'x-wc-webhook-resource' => 'order',
            'x-wc-webhook-event' => 'updated',
            'x-wc-webhook-delivery-id' => 'delivery-123',
            'x-wc-webhook-id' => 'webhook-456',
            'content-type' => 'application/json',
        ]);
    }

    private function activeProcessWithStep(ProcessRepository $processes): int
    {
        $processId = $processes->create([
            'workspace_id' => 1,
            'name' => 'WooCommerce Runtime Testprozess',
            'process_key' => 'woocommerce_runtime_test_process',
            'status' => 'active',
            'default_mode' => 'run',
        ]);
        $processes->addStep($processId, [
            'position' => 10,
            'name' => 'WooCommerce Event aufnehmen',
            'step_type' => 'mapping_run',
            'reference_id' => 1,
            'is_enabled' => '1',
        ]);

        return $processId;
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
            secret_encrypted TEXT NULL,
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
        $pdo->exec('CREATE TABLE luna_woocommerce_runtime_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            process_trigger_id INTEGER NULL,
            process_run_id INTEGER NULL,
            provider TEXT NOT NULL DEFAULT "woocommerce",
            topic TEXT NOT NULL,
            resource TEXT NULL,
            event_action TEXT NULL,
            delivery_id TEXT NULL,
            webhook_id TEXT NULL,
            source_domain TEXT NULL,
            source_order_id TEXT NULL,
            signature_valid INTEGER NOT NULL DEFAULT 0,
            payload_size INTEGER NOT NULL DEFAULT 0,
            payload_hash TEXT NULL,
            payload_summary_json TEXT NULL,
            payload_meta_json TEXT NULL,
            processing_status TEXT NOT NULL DEFAULT "received",
            processing_message TEXT NULL,
            received_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');

        return $pdo;
    }
}

final class WooCommerceRuntimeTestStepRunner implements ProcessStepContextAwareRunnerInterface
{
    public function supports(string $stepType): bool
    {
        return $stepType === 'mapping_run';
    }

    public function run(array $process, array $step, int $processRunId, string $mode): ProcessStepResult
    {
        return ProcessStepResult::success('WooCommerce runtime step completed.');
    }

    public function runWithContext(array $process, array $step, int $processRunId, string $mode, array $context): ProcessStepResult
    {
        return ProcessStepResult::success('WooCommerce runtime step completed.', [
            'result' => [
                'provider_seen' => (string) ($context['previous_result']['provider'] ?? ''),
                'delivery_id' => (string) ($context['previous_result']['delivery_id'] ?? ''),
            ],
        ]);
    }
}
