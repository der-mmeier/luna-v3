<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Core\Paths;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Process\ProcessRunner;
use Luna\Process\TargetActionStepRunner;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\ProcessRepository;
use Luna\Repository\ProcessRunRepository;
use Luna\Repository\TargetActionRepository;
use Luna\Security\EncryptionService;
use Luna\TargetAction\TargetActionConfigValidator;
use Luna\TargetAction\TargetActionExecutor;
use Luna\TargetAction\TargetActionHttpClientInterface;
use PDO;
use PHPUnit\Framework\TestCase;

final class TargetActionFoundationTest extends TestCase
{
    public function testMigrationDefinesTargetActionsTable(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_06_06_000018_create_target_actions.sql');

        self::assertIsString($migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS luna_target_actions', $migration);
        self::assertStringContainsString('action_type', $migration);
    }

    public function testTargetActionCanBeCreated(): void
    {
        [, , $actions] = $this->repositories($this->pdo());

        $id = $actions->create([
            'workspace_id' => 1,
            'name' => 'Status GET',
            'action_key' => '',
            'action_type' => 'http_get',
            'is_active' => '1',
            'config_json' => '{"url":"https://example.test/api/status"}',
        ]);
        $action = $actions->find($id);

        self::assertNotNull($action);
        self::assertSame('status_get', $action['action_key']);
        self::assertSame('http_get', $action['action_type']);
    }

    public function testInvalidJsonConfigurationIsRejected(): void
    {
        $errors = (new TargetActionConfigValidator())->validate([
            'workspace_id' => 1,
            'name' => 'Broken',
            'action_key' => 'broken',
            'action_type' => 'http_post',
            'config_json' => '{broken',
        ]);

        self::assertNotEmpty($errors);
    }

    public function testProcessCanReferenceTargetActionStepAndCreatesLogs(): void
    {
        [$processes, $runs, $actions] = $this->repositories($this->pdo());
        $actionId = $this->fileExportAction($actions);
        $processId = $this->activeProcessWithTargetActionStep($processes, $actionId);
        $runner = $this->runner($processes, $runs, $actions);

        $runId = $runner->run($processId, 'dry_run', 'manual');
        $run = $runs->findRun($runId);
        $logs = $runs->logsForRun($runId);

        self::assertNotNull($run);
        self::assertSame('success', $run['status']);
        self::assertNotEmpty($logs);
        self::assertStringContainsString('Target Action', json_encode($logs, JSON_THROW_ON_ERROR));
    }

    public function testDryRunDoesNotWriteFile(): void
    {
        [$processes, $runs, $actions] = $this->repositories($this->pdo());
        $actionId = $this->fileExportAction($actions, 'dry_run_no_write.json');
        $processId = $this->activeProcessWithTargetActionStep($processes, $actionId);
        $target = dirname(__DIR__, 2) . '/storage/runtime-exports/dry_run_no_write.json';
        if (is_file($target)) {
            unlink($target);
        }

        $this->runner($processes, $runs, $actions)->run($processId, 'dry_run', 'manual');

        self::assertFileDoesNotExist($target);
    }

    public function testFileExportRejectsPathTraversal(): void
    {
        [$processes, $runs, $actions] = $this->repositories($this->pdo());
        $actionId = $actions->create([
            'workspace_id' => 1,
            'name' => 'Bad file export',
            'action_key' => 'bad_file_export',
            'action_type' => 'file_export',
            'is_active' => '1',
            'config_json' => '{"directory":"../outside","filename_template":"run.json","format":"json"}',
        ]);
        $processId = $this->activeProcessWithTargetActionStep($processes, $actionId);

        $runId = $this->runner($processes, $runs, $actions)->run($processId, 'run', 'manual');
        $run = $runs->findRun($runId);

        self::assertNotNull($run);
        self::assertSame('failed', $run['status']);
        self::assertStringContainsString('Export-Verzeichnis ist nicht erlaubt', (string) $run['error_message']);
    }

    public function testHttpDryRunDoesNotCallHttpClient(): void
    {
        [$processes, $runs, $actions] = $this->repositories($this->pdo());
        $actionId = $actions->create([
            'workspace_id' => 1,
            'name' => 'HTTP POST',
            'action_key' => 'http_post',
            'action_type' => 'http_post',
            'is_active' => '1',
            'config_json' => '{"url":"https://example.test/api/items","headers":{"Content-Type":"application/json"},"body_template":"{{previous_result}}"}',
        ]);
        $processId = $this->activeProcessWithTargetActionStep($processes, $actionId);
        $client = new CountingHttpClient();

        $this->runner($processes, $runs, $actions, $client)->run($processId, 'dry_run', 'manual', null, [
            'previous_result' => ['items' => [['id' => 1]]],
        ]);

        self::assertSame(0, $client->calls);
    }

    public function testTargetActionFailureMarksProcessRunFailed(): void
    {
        [$processes, $runs, $actions] = $this->repositories($this->pdo());
        $actionId = $actions->create([
            'workspace_id' => 1,
            'name' => 'Invalid HTTP',
            'action_key' => 'invalid_http',
            'action_type' => 'http_get',
            'is_active' => '1',
            'config_json' => '{"url":"ftp://example.test/status"}',
        ]);
        $processId = $this->activeProcessWithTargetActionStep($processes, $actionId);

        $runId = $this->runner($processes, $runs, $actions)->run($processId, 'run', 'manual');
        $run = $runs->findRun($runId);

        self::assertNotNull($run);
        self::assertSame('failed', $run['status']);
        self::assertStringContainsString('HTTP URL muss', (string) $run['error_message']);
    }

    public function testDatabaseDryRunDoesNotWriteRows(): void
    {
        [$processes, $runs, $actions] = $this->repositories($this->pdo());
        $targetPdo = new PDO('sqlite::memory:');
        $targetPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $targetPdo->exec('CREATE TABLE target_table (external_id INTEGER PRIMARY KEY, name TEXT)');
        $actionId = $actions->create([
            'workspace_id' => 1,
            'name' => 'DB Insert',
            'action_key' => 'db_insert',
            'action_type' => 'database_insert',
            'is_active' => '1',
            'config_json' => '{"connection_id":1,"table":"target_table","columns":{"external_id":"id","name":"name"}}',
        ]);
        $processId = $this->activeProcessWithTargetActionStep($processes, $actionId);

        $this->runner($processes, $runs, $actions, null, $targetPdo)->run($processId, 'dry_run', 'manual', null, [
            'previous_result' => ['items' => [['id' => 1, 'name' => 'One']]],
        ]);

        self::assertSame(0, (int) $targetPdo->query('SELECT COUNT(*) FROM target_table')->fetchColumn());
    }

    public function testExistingCliCommandsRemainRegistered(): void
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
        ] as $command) {
            self::assertStringContainsString($command, $bin);
        }
    }

    /**
     * @return array{0: ProcessRepository, 1: ProcessRunRepository, 2: TargetActionRepository}
     */
    private function repositories(PDO $pdo): array
    {
        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());

        return [
            new ProcessRepository($database, $pdo),
            new ProcessRunRepository($database, $pdo),
            new TargetActionRepository($database, $pdo),
        ];
    }

    private function runner(
        ProcessRepository $processes,
        ProcessRunRepository $runs,
        TargetActionRepository $actions,
        ?CountingHttpClient $httpClient = null,
        ?PDO $targetPdo = null,
    ): ProcessRunner {
        $executor = new TargetActionExecutor(
            new Paths(dirname(__DIR__, 2)),
            $this->connectionRepository($this->pdo()),
            new ExternalPdoConnectionFactory(),
            $httpClient ?? new CountingHttpClient(),
            $targetPdo === null ? null : static fn (array $profile): PDO => $targetPdo,
        );

        return new ProcessRunner($processes, $runs, [
            new TargetActionStepRunner($actions, $runs, $executor),
        ]);
    }

    private function connectionRepository(PDO $pdo): ConnectionProfileRepository
    {
        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());

        return new ConnectionProfileRepository($database, new EncryptionService(new Config()), $pdo);
    }

    private function fileExportAction(TargetActionRepository $actions, string $filename = 'process_{{process_id}}_run_{{run_id}}.json'): int
    {
        return $actions->create([
            'workspace_id' => 1,
            'name' => 'File Export',
            'action_key' => 'file_export',
            'action_type' => 'file_export',
            'is_active' => '1',
            'config_json' => json_encode([
                'directory' => 'storage/runtime-exports',
                'filename_template' => $filename,
                'format' => 'json',
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    private function activeProcessWithTargetActionStep(ProcessRepository $processes, int $actionId): int
    {
        $processId = $processes->create([
            'workspace_id' => 1,
            'name' => 'Action Process',
            'process_key' => 'action_process',
            'status' => 'active',
            'default_mode' => 'dry_run',
        ]);
        $processes->addStep($processId, [
            'position' => 10,
            'name' => 'Run Action',
            'step_type' => 'target_action',
            'reference_id' => $actionId,
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
        $pdo->exec('CREATE TABLE luna_connection_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER,
            name TEXT,
            type TEXT,
            driver TEXT,
            host TEXT,
            port INTEGER,
            database_name TEXT,
            username TEXT,
            read_only INTEGER,
            config_json TEXT,
            created_at TEXT,
            updated_at TEXT
        )');
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, driver, host, port, database_name, username, read_only, config_json, created_at, updated_at) VALUES (1, 1, 'Target', 'target', 'mysql', 'localhost', 3306, 'test', 'user', 0, '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE luna_connection_secrets (connection_profile_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT)');
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
        $pdo->exec('CREATE TABLE luna_target_actions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            action_key TEXT NOT NULL,
            action_type TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            config_json TEXT NULL,
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

final class CountingHttpClient implements TargetActionHttpClientInterface
{
    public int $calls = 0;

    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 10): array
    {
        $this->calls++;

        return [
            'status_code' => 200,
            'body' => '{"ok":true}',
            'headers' => ['content-type' => 'application/json'],
        ];
    }
}
