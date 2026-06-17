<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Admin\DeletionGuard;
use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Security\EncryptionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class ConnectionWorkspaceSharingTest extends TestCase
{
    public function testAvailableConnectionsContainOwnerAndSharedButNotForeignConnections(): void
    {
        $pdo = $this->pdo();
        $this->seedWorkspacesAndConnections($pdo);

        $repository = $this->connections($pdo);
        $available = $repository->connectionsAvailableForWorkspace(2);
        $names = array_column($available, 'name');

        self::assertContains('Shared TransferDB', $names);
        self::assertContains('Asf Source', $names);
        self::assertNotContains('Foreign Source', $names);
        self::assertTrue($repository->connectionIsAvailableForWorkspace(1, 2));
        self::assertFalse($repository->connectionIsAvailableForWorkspace(3, 2));
    }

    public function testSyncPreventsDuplicatesAndDoesNotStoreOwnerAsShare(): void
    {
        $pdo = $this->pdo();
        $this->seedWorkspacesAndConnections($pdo);
        $repository = $this->connections($pdo);

        $repository->syncConnectionWorkspaceShares(1, [1, 2, 2, 3, 999]);

        self::assertSame([2, 3], $repository->sharedWorkspaceIdsForConnection(1));
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM luna_connection_workspaces WHERE connection_id = 1')->fetchColumn());

        $repository->syncConnectionWorkspaceShares(1, [3]);

        self::assertSame([3], $repository->sharedWorkspaceIdsForConnection(1));
    }

    public function testDeletionGuardBlocksConnectionWithWorkspaceShares(): void
    {
        $pdo = $this->pdo();
        $this->seedWorkspacesAndConnections($pdo);

        $guard = new DeletionGuard($this->systemDatabase(), $pdo);
        $check = $guard->canDelete('connection', 1);

        self::assertFalse($check['can_delete']);
        self::assertSame('workspace_share', $check['blockers'][0]['type']);
        self::assertSame('AsfInStocks', $check['blockers'][0]['name']);
        self::assertStringContainsString('für folgende Workspaces freigegeben ist', $guard->message($check));
        self::assertStringContainsString('- Workspace "AsfInStocks"', $guard->message($check));
    }

    public function testWorkspaceDeleteRemovesIncomingSharesButKeepsForeignConnections(): void
    {
        $pdo = $this->pdo();
        $this->seedWorkspacesAndConnections($pdo);

        (new WorkspaceRepository($this->systemDatabase(), $pdo))->delete(2);

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_connection_workspaces WHERE workspace_id = 2')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM luna_connection_profiles WHERE id = 1')->fetchColumn());
    }

    private function seedWorkspacesAndConnections(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'toolbox', 'Toolbox')");
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (2, 'asfinstocks', 'AsfInStocks')");
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (3, 'woocommerce', 'WooCommerce')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, driver, host, database_name, username, read_only, is_active) VALUES (1, 1, 'Shared TransferDB', 'transfer_db', 'mysql', 'db.local', 'transferdb', 'user', 0, 1)");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, driver, host, database_name, username, read_only, is_active) VALUES (2, 2, 'Asf Source', 'source', 'mysql', 'source.local', 'source', 'user', 1, 1)");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, driver, host, database_name, username, read_only, is_active) VALUES (3, 3, 'Foreign Source', 'source', 'mysql', 'foreign.local', 'foreign', 'user', 1, 1)");
        $pdo->exec("INSERT INTO luna_connection_workspaces (connection_id, workspace_id, created_at, updated_at) VALUES (1, 2, NOW(), NOW())");
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-06-16 12:00:00');
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, type TEXT, driver TEXT, host TEXT, port INTEGER NULL, database_name TEXT, username TEXT, read_only INTEGER, is_active INTEGER)');
        $pdo->exec('CREATE TABLE luna_connection_workspaces (id INTEGER PRIMARY KEY AUTOINCREMENT, connection_id INTEGER, workspace_id INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(connection_id, workspace_id))');
        $pdo->exec('CREATE TABLE luna_connection_secrets (id INTEGER PRIMARY KEY AUTOINCREMENT, connection_profile_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT, encryption_version TEXT, created_at TEXT, updated_at TEXT)');

        return $pdo;
    }

    private function connections(PDO $pdo): ConnectionProfileRepository
    {
        return new ConnectionProfileRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo);
    }

    private function systemDatabase(): SystemDatabase
    {
        return new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
    }
}
