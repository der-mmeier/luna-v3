<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Deployment\DeploymentTargetUrlBuilder;
use Luna\Repository\DeploymentTargetRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class DeploymentTargetRepositoryTest extends TestCase
{
    public function testTargetCanBeCreatedEditedDeactivatedAndSetAsDefault(): void
    {
        $repository = $this->repository($this->pdo());
        $id = $repository->create([
            'workspace_id' => null,
            'name' => 'Production',
            'environment' => 'production',
            'public_base_url' => 'https://toolbox.example.com/luna/',
            'is_default' => '1',
            'is_active' => '1',
        ]);

        $target = $repository->find($id);
        self::assertNotNull($target);
        self::assertSame('https://toolbox.example.com/luna', $target['public_base_url']);
        self::assertSame(1, (int) $target['is_default']);

        $repository->update($id, [
            'name' => 'Production Updated',
            'environment' => 'production',
            'public_base_url' => 'https://production.example.com/luna',
            'is_active' => '1',
        ]);
        self::assertSame('Production Updated', $repository->find($id)['name'] ?? null);

        $repository->setActive($id, false);
        self::assertSame(0, (int) ($repository->find($id)['is_active'] ?? 1));

        $repository->setActive($id, true);
        $repository->setDefault($id);
        self::assertSame(1, (int) ($repository->find($id)['is_default'] ?? 0));
    }

    public function testSecondDefaultClearsPreviousDefaultInSameEnvironment(): void
    {
        $repository = $this->repository($this->pdo());
        $firstId = $repository->create([
            'name' => 'Production A',
            'environment' => 'production',
            'public_base_url' => 'https://a.example.com',
            'is_default' => '1',
        ]);
        $secondId = $repository->create([
            'name' => 'Production B',
            'environment' => 'production',
            'public_base_url' => 'https://b.example.com',
            'is_default' => '1',
        ]);

        self::assertSame(0, (int) ($repository->find($firstId)['is_default'] ?? 1));
        self::assertSame(1, (int) ($repository->find($secondId)['is_default'] ?? 0));
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, name TEXT)');
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

        return $pdo;
    }

    private function repository(PDO $pdo): DeploymentTargetRepository
    {
        return new DeploymentTargetRepository(
            new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory()),
            new DeploymentTargetUrlBuilder(),
            $pdo,
        );
    }
}
