<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Connections\ConnectionProfileData;
use Luna\Connections\ConnectionTester;
use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use PHPUnit\Framework\TestCase;

final class ConnectionProfileDataTest extends TestCase
{
    public function testMultipleConnectionsCanShareWorkspaceWithoutOverwritingRole(): void
    {
        $pimcore = ConnectionProfileData::normalize([
            'workspace_id' => 7,
            'name' => 'PIMCore',
            'type' => 'source',
            'driver' => 'mysql',
            'host' => 'pimcore-db.local',
            'database_name' => 'pimcore',
            'username' => 'pimcore_user',
        ]);
        $prices = ConnectionProfileData::normalize([
            'workspace_id' => 7,
            'name' => 'Preis DB',
            'type' => 'transfer',
            'driver' => 'mariadb',
            'host' => 'prices-db.local',
            'database_name' => 'prices',
            'username' => 'price_user',
        ]);

        self::assertSame(7, $pimcore['workspace_id']);
        self::assertSame(7, $prices['workspace_id']);
        self::assertSame('source', $pimcore['type']);
        self::assertSame('transfer', $prices['type']);
        self::assertSame('mysql', $pimcore['driver']);
        self::assertSame('mariadb', $prices['driver']);
    }

    public function testSourceConnectionsDefaultToReadOnly(): void
    {
        $values = ConnectionProfileData::normalize([
            'type' => 'source',
            'name' => 'Source',
            'driver' => 'mysql',
            'host' => 'source-db.local',
            'database_name' => 'source_db',
            'username' => 'source_user',
        ]);

        self::assertSame(1, $values['read_only']);
    }

    public function testTransferAndTargetConnectionsAreNotReadOnlyByDefault(): void
    {
        self::assertSame(0, ConnectionProfileData::normalize(['type' => 'transfer'])['read_only']);
        self::assertSame(0, ConnectionProfileData::normalize(['type' => 'target'])['read_only']);
    }

    public function testOnlySupportedRolesAndDriversValidate(): void
    {
        self::assertSame([], ConnectionProfileData::validate([
            'name' => 'Valid',
            'type' => 'target',
            'driver' => 'mariadb',
            'host' => 'db.local',
            'database_name' => 'app',
            'username' => 'user',
        ]));

        $errors = ConnectionProfileData::validate([
            'name' => 'Invalid',
            'type' => 'analytics',
            'driver' => 'pgsql',
            'host' => 'db.local',
            'database_name' => 'app',
            'username' => 'user',
        ]);

        self::assertContains('Connection-Rolle ist ungueltig.', $errors);
        self::assertContains('Connection-Driver ist ungueltig.', $errors);
    }

    public function testConnectionTesterDoesNotExposeSecretsOnFailure(): void
    {
        $tester = new ConnectionTester(new ExternalPdoConnectionFactory());
        $result = $tester->test(new ExternalDatabaseConfig(
            'unsupported',
            'secret-host.example',
            3306,
            'secret_database',
            'secret_user',
            'top-secret-password',
        ));

        self::assertFalse($result['success']);
        self::assertStringNotContainsString('top-secret-password', $result['message']);
        self::assertStringNotContainsString('secret_user', $result['message']);
        self::assertStringNotContainsString('secret_database', $result['message']);
        self::assertStringNotContainsString('secret-host.example', $result['message']);
        self::assertStringNotContainsString('mysql:', $result['message']);
    }
}
