<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Process\ProcessRunner;
use Luna\Process\ProcessStepResult;
use Luna\Process\ProcessStepRunnerInterface;
use Luna\Repository\ProcessRepository;
use Luna\Repository\ProcessRunRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProcessRuntimeTest extends TestCase
{
    public function testProcessCanBeCreatedWithStep(): void
    {
        [$processes] = $this->repositories();

        $processId = $processes->create([
            'workspace_id' => 1,
            'name' => 'ISR Export vorbereiten',
            'process_key' => '',
            'status' => 'active',
            'default_mode' => 'dry_run',
        ]);
        $stepId = $processes->addStep($processId, [
            'position' => 10,
            'name' => 'Mapping prüfen',
            'step_type' => 'mapping_run',
            'reference_id' => 33,
            'is_enabled' => '1',
        ]);

        $process = $processes->find($processId);
        self::assertNotNull($process);
        self::assertSame('isr_export_vorbereiten', $process['process_key']);
        self::assertSame(33, (int) ($processes->findStep($stepId)['reference_id'] ?? 0));
        self::assertCount(1, $processes->stepsForProcess($processId));
    }

    public function testSuccessfulRunCreatesRunAndLogs(): void
    {
        [$processes, $runs] = $this->repositories();
        $processId = $this->activeProcessWithStep($processes);
        $runner = new ProcessRunner($processes, $runs, [
            new SuccessfulTestStepRunner(),
        ]);

        $runId = $runner->run($processId, 'dry_run', 'manual');
        $run = $runs->findRun($runId);

        self::assertNotNull($run);
        self::assertSame('success', $run['status']);
        self::assertSame('dry_run', $run['mode']);
        self::assertSame('manual', $run['trigger_type']);
        self::assertNotNull($run['started_at']);
        self::assertNotNull($run['finished_at']);
        self::assertGreaterThanOrEqual(0, (int) $run['duration_ms']);
        self::assertNotEmpty($runs->logsForRun($runId));
    }

    public function testFailedRunStoresErrorAndLog(): void
    {
        [$processes, $runs] = $this->repositories();
        $processId = $this->activeProcessWithStep($processes);
        $runner = new ProcessRunner($processes, $runs, [
            new FailingTestStepRunner(),
        ]);

        $runId = $runner->run($processId, 'run', 'cli');
        $run = $runs->findRun($runId);
        $logs = $runs->logsForRun($runId);

        self::assertNotNull($run);
        self::assertSame('failed', $run['status']);
        self::assertStringContainsString('Step failed for test', (string) $run['error_message']);
        self::assertSame('error', $logs[array_key_last($logs)]['level']);
    }

    public function testCliUsageKeepsExistingCommandsAndAddsProcessRun(): void
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
     * @return array{0: ProcessRepository, 1: ProcessRunRepository}
     */
    private function repositories(): array
    {
        $pdo = $this->pdo();
        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());

        return [
            new ProcessRepository($database, $pdo),
            new ProcessRunRepository($database, $pdo),
        ];
    }

    private function activeProcessWithStep(ProcessRepository $processes): int
    {
        $processId = $processes->create([
            'workspace_id' => 1,
            'name' => 'Testprozess',
            'process_key' => 'test_process',
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
        $pdo->exec('CREATE TABLE luna_process_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            process_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT "queued",
            mode TEXT NOT NULL DEFAULT "run",
            trigger_type TEXT NOT NULL DEFAULT "manual",
            trigger_ref TEXT NULL,
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

final class SuccessfulTestStepRunner implements ProcessStepRunnerInterface
{
    public function supports(string $stepType): bool
    {
        return $stepType === 'mapping_run';
    }

    public function run(array $process, array $step, int $processRunId, string $mode): ProcessStepResult
    {
        return ProcessStepResult::success('Test step completed.', [
            'process_id' => (int) $process['id'],
            'step_id' => (int) $step['id'],
            'mode' => $mode,
            'run_id' => $processRunId,
        ]);
    }
}

final class FailingTestStepRunner implements ProcessStepRunnerInterface
{
    public function supports(string $stepType): bool
    {
        return $stepType === 'mapping_run';
    }

    public function run(array $process, array $step, int $processRunId, string $mode): ProcessStepResult
    {
        throw new RuntimeException('Step failed for test.');
    }
}
