<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Security\EncryptionService;
use PHPUnit\Framework\TestCase;

final class AdminWorkflowRouteTest extends TestCase
{
    public function testConnectionEditRouteIsRegistered(): void
    {
        $routes = $this->loadWebRoutes();
        $request = new \Luna\Http\Request('GET', '/admin/connections/5/edit');
        $route = $routes->match($request);

        self::assertNotNull($route);
        self::assertSame('admin.connections.edit', $route->name());
        self::assertSame(['id' => '5'], $route->parameters($request));
    }

    public function testTransferFieldUpdateRouteIsRegistered(): void
    {
        $routes = $this->loadWebRoutes();
        $request = new \Luna\Http\Request('POST', '/admin/transfers/7/fields/11');
        $route = $routes->match($request);

        self::assertNotNull($route);
        self::assertSame('admin.transfers.fields.update', $route->name());
        self::assertSame(['id' => '7', 'fieldId' => '11'], $route->parameters($request));
    }

    public function testConnectionEditValuesCanChangeWorkspaceWithoutChangingPassword(): void
    {
        $this->loadWebRoutes();
        $values = \connectionValues(new \Luna\Http\Request('POST', '/admin/connections/5/edit', [], [
            'workspace_id' => '9',
            'name' => 'PIMCore',
            'type' => 'source',
            'driver' => 'mysql',
            'host' => 'db.local',
            'port' => '3306',
            'database_name' => 'pimcore',
            'username' => 'pimcore_user',
            'read_only' => '1',
            'password' => '',
        ]));

        self::assertSame(9, $values['workspace_id']);
        self::assertSame([], \Luna\Connections\ConnectionProfileData::secretsFromPassword(''));
    }

    public function testConnectionEditStoresNewPasswordPayload(): void
    {
        self::assertSame([], \Luna\Connections\ConnectionProfileData::secretsFromPassword(''));
        self::assertSame(['password' => 'new-secret'], \Luna\Connections\ConnectionProfileData::secretsFromPassword('new-secret'));
    }

    public function testNewPasswordCanBeEncryptedWithoutPlainTextLeak(): void
    {
        $_ENV['APP_KEY'] = 'unit-test-key';
        $service = new EncryptionService(new Config());
        $encrypted = $service->encrypt('new-secret');
        $decrypted = $service->decrypt($encrypted);
        unset($_ENV['APP_KEY']);

        self::assertStringNotContainsString('new-secret', $encrypted);
        self::assertSame('new-secret', $decrypted);
    }

    public function testWorkspaceCreateRedirectsToWorkspaceIndexAfterSuccess(): void
    {
        $this->loadWebRoutes();
        $response = \workspaceCreateSuccessRedirect();

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/workspaces', $response->headers()['Location'] ?? null);
    }

    private function loadWebRoutes(): \Luna\Routing\RouteCollection
    {
        $app = new \Luna\Core\Application(new \Luna\Core\Paths(dirname(__DIR__, 2)), new Config());
        $routes = new \Luna\Routing\RouteCollection();
        $loader = require dirname(__DIR__, 2) . '/routes/web.php';
        $loader($routes, $app);

        return $routes;
    }
}
