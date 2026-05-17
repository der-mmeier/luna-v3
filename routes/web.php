<?php

declare(strict_types=1);

use Luna\Connections\ConnectionTester;
use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Core\Application;
use Luna\Http\Request;
use Luna\Http\Response;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\SchemaMetadataRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Routing\RouteCollection;
use Luna\Schema\SampleDataReader;
use Luna\Schema\SchemaInspector;
use Luna\View\ViewRenderer;

return static function (RouteCollection $routes, Application $app): void {
    $view = $app->services()->get('view');

    if (! $view instanceof ViewRenderer) {
        throw new RuntimeException('View service is not available.');
    }

    $admin = static fn (string $template, array $data = []): Response => Response::html($view->render(
        $template,
        array_merge(['appName' => $app->config()->string('APP_NAME', 'Luna V3')], $data),
        'layouts/admin',
    ));

    $connections = static fn (): ConnectionProfileRepository => $app->services()->get('repository.connections');
    $workspaces = static fn (): WorkspaceRepository => $app->services()->get('repository.workspaces');
    $metadata = static fn (): SchemaMetadataRepository => $app->services()->get('repository.schema_metadata');
    $pdoFactory = static fn (): ExternalPdoConnectionFactory => $app->services()->get('connections.pdo_factory');
    $safeAll = static function (Closure $repositoryFactory): array {
        try {
            return $repositoryFactory()->all();
        } catch (Throwable) {
            return [];
        }
    };

    $configFor = static function (array $profile) use ($connections): ExternalDatabaseConfig {
        return ExternalDatabaseConfig::fromProfile($profile, $connections()->secretsFor((int) $profile['id']));
    };

    $routes->get('/', static fn (): Response => Response::html($view->render(
        'admin/dashboard',
        [
            'appName' => $app->config()->string('APP_NAME', 'Luna V3'),
            'title' => 'Dashboard',
            'active' => 'dashboard',
        ],
        'layouts/admin',
    )), 'web.home', 'web');

    $routes->get('/admin', static fn (): Response => $admin('admin/dashboard', [
        'title' => 'Dashboard',
        'active' => 'dashboard',
    ]), 'admin.dashboard', 'web');

    $routes->get('/admin/workspaces', static function () use ($admin, $workspaces): Response {
        try {
            $items = array_map(static fn (array $workspace): array => [
                'name' => $workspace['name'],
                'status' => $workspace['status'],
                'updated' => $workspace['updated_at'],
            ], $workspaces()->all());
            $error = null;
        } catch (Throwable) {
            $items = [];
            $error = 'Luna-Systemdatenbank ist nicht erreichbar.';
        }

        return $admin('admin/workspaces', [
            'title' => 'Workspaces',
            'active' => 'workspaces',
            'workspaces' => $items,
            'error' => $error,
        ]);
    }, 'admin.workspaces', 'web');

    $routes->get('/admin/connections', static function () use ($admin, $connections): Response {
        try {
            $profiles = $connections()->all();
            $error = null;
        } catch (Throwable) {
            $profiles = [];
            $error = 'Luna-Systemdatenbank ist nicht erreichbar.';
        }

        return $admin('admin/connections/index', [
            'title' => 'Connections',
            'active' => 'connections',
            'connections' => $profiles,
            'error' => $error,
        ]);
    }, 'admin.connections', 'web');

    $routes->get('/admin/connections/create', static function () use ($admin, $workspaces): Response {
        try {
            $workspaceItems = $workspaces()->all();
            $error = null;
        } catch (Throwable) {
            $workspaceItems = [];
            $error = 'Luna-Systemdatenbank ist nicht erreichbar.';
        }

        return $admin('admin/connections/create', [
            'title' => 'Connection anlegen',
            'active' => 'connections',
            'workspaces' => $workspaceItems,
            'error' => $error,
            'values' => ['type' => 'source', 'driver' => 'mysql', 'port' => '3306', 'charset' => 'utf8mb4', 'read_only' => '1'],
            'errors' => [],
        ]);
    }, 'admin.connections.create', 'web');

    $routes->post('/admin/connections', static function (Request $request) use ($admin, $connections, $workspaces): Response {
        $values = [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'type' => (string) $request->post('type', 'source'),
            'driver' => (string) $request->post('driver', 'mysql'),
            'host' => (string) $request->post('host', ''),
            'port' => (string) $request->post('port', '3306'),
            'database_name' => (string) $request->post('database_name', ''),
            'username' => (string) $request->post('username', ''),
            'charset' => (string) $request->post('charset', 'utf8mb4'),
            'read_only' => $request->post('read_only') !== null ? '1' : '',
            'notes' => (string) $request->post('notes', ''),
        ];
        $errors = [];

        foreach (['name' => 'Name', 'driver' => 'Driver', 'host' => 'Host', 'database_name' => 'Datenbankname', 'username' => 'Benutzername'] as $key => $label) {
            if (trim((string) $values[$key]) === '') {
                $errors[] = $label . ' ist erforderlich.';
            }
        }

        if ($errors !== []) {
            return $admin('admin/connections/create', [
                'title' => 'Connection anlegen',
                'active' => 'connections',
                'workspaces' => $safeAll($workspaces),
                'values' => $values,
                'errors' => $errors,
                'error' => null,
            ]);
        }

        try {
            $id = $connections()->create($values, ['password' => (string) $request->post('password', '')]);

            return new Response('', 302, ['Location' => '/admin/connections/' . $id]);
        } catch (Throwable) {
            return $admin('admin/connections/create', [
                'title' => 'Connection anlegen',
                'active' => 'connections',
                'workspaces' => $safeAll($workspaces),
                'values' => $values,
                'errors' => ['Connection konnte nicht gespeichert werden. APP_KEY und Systemdatenbank prüfen.'],
                'error' => null,
            ]);
        }
    }, 'admin.connections.store', 'web');

    $routes->get('/admin/connections/{id}', static function (Request $request) use ($admin, $connections): Response {
        try {
            $profile = $connections()->find((int) $request->route('id'));
        } catch (Throwable) {
            return $admin('admin/connections/show', [
                'title' => 'Connection',
                'active' => 'connections',
                'connection' => null,
                'alert' => ['type' => 'danger', 'message' => 'Luna-Systemdatenbank ist nicht erreichbar.'],
            ]);
        }

        return $admin('admin/connections/show', [
            'title' => $profile['name'] ?? 'Connection',
            'active' => 'connections',
            'connection' => $profile,
            'alert' => null,
        ]);
    }, 'admin.connections.show', 'web');

    $routes->post('/admin/connections/{id}/test', static function (Request $request) use ($admin, $app, $connections, $configFor): Response {
        try {
            $profile = $connections()->find((int) $request->route('id'));

            if ($profile === null) {
                throw new RuntimeException('Connection nicht gefunden.');
            }

            /** @var ConnectionTester $tester */
            $tester = $app->services()->get('connections.tester');
            $result = $tester->test($configFor($profile));
            $alert = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
        } catch (Throwable) {
            $profile = $profile ?? null;
            $alert = ['type' => 'danger', 'message' => 'Verbindungstest konnte nicht ausgeführt werden.'];
        }

        return $admin('admin/connections/show', [
            'title' => $profile['name'] ?? 'Connection',
            'active' => 'connections',
            'connection' => $profile,
            'alert' => $alert,
        ]);
    }, 'admin.connections.test', 'web');

    $routes->get('/admin/schema', static function () use ($admin, $connections): Response {
        try {
            $profiles = array_filter($connections()->all(), static fn (array $profile): bool => (int) $profile['is_active'] === 1);
            $error = null;
        } catch (Throwable) {
            $profiles = [];
            $error = 'Luna-Systemdatenbank ist nicht erreichbar.';
        }

        return $admin('admin/schema/index', [
            'title' => 'Schema Explorer',
            'active' => 'schema',
            'connections' => $profiles,
            'error' => $error,
        ]);
    }, 'admin.schema', 'web');

    $routes->get('/admin/schema/{connectionId}', static function (Request $request) use ($admin, $connections, $configFor, $pdoFactory): Response {
        try {
            $profile = $connections()->find((int) $request->route('connectionId'));

            if ($profile === null) {
                throw new RuntimeException('Connection nicht gefunden.');
            }

            $tables = (new SchemaInspector($pdoFactory()->create($configFor($profile))))->tables();
            $alert = null;
        } catch (Throwable) {
            $profile = $profile ?? null;
            $tables = [];
            $alert = ['type' => 'danger', 'message' => 'Schema konnte nicht gelesen werden. Verbindung und Berechtigungen prüfen.'];
        }

        return $admin('admin/schema/index', [
            'title' => 'Schema Explorer',
            'active' => 'schema',
            'connections' => [],
            'connection' => $profile,
            'tables' => $tables,
            'alert' => $alert,
            'error' => null,
        ]);
    }, 'admin.schema.connection', 'web');

    $routes->get('/admin/schema/{connectionId}/table', static function (Request $request) use ($admin, $connections, $configFor, $pdoFactory, $metadata): Response {
        $tableName = (string) $request->query('table', '');

        try {
            $profile = $connections()->find((int) $request->route('connectionId'));

            if ($profile === null || $tableName === '') {
                throw new RuntimeException('Tabelle nicht gefunden.');
            }

            $pdo = $pdoFactory()->create($configFor($profile));
            $columns = (new SchemaInspector($pdo))->columns($tableName);
            $samples = (new SampleDataReader($pdo))->sampleRows($tableName);
            $tableNote = $metadata()->tableNote((int) $profile['id'], null, $tableName);
            $columnNotes = [];

            foreach ($columns as $column) {
                $columnNotes[$column['column_name']] = $metadata()->columnNote((int) $profile['id'], null, $tableName, $column['column_name']);
            }

            $alert = null;
        } catch (Throwable) {
            $profile = $profile ?? null;
            $columns = [];
            $samples = [];
            $tableNote = null;
            $columnNotes = [];
            $alert = ['type' => 'danger', 'message' => 'Tabellenanalyse konnte nicht geladen werden.'];
        }

        return $admin('admin/schema/table', [
            'title' => 'Tabellenanalyse',
            'active' => 'schema',
            'connection' => $profile,
            'tableName' => $tableName,
            'columns' => $columns,
            'samples' => $samples,
            'tableNote' => $tableNote,
            'columnNotes' => $columnNotes,
            'alert' => $alert,
        ]);
    }, 'admin.schema.table', 'web');

    $routes->post('/admin/schema/{connectionId}/table-note', static function (Request $request) use ($connections, $metadata): Response {
        $connectionId = (int) $request->route('connectionId');
        $tableName = (string) $request->post('table_name', '');
        $note = trim((string) $request->post('note', '')) ?: null;
        $profile = $connections()->find($connectionId);
        $metadata()->saveTableNote($connectionId, isset($profile['workspace_id']) ? (int) $profile['workspace_id'] : null, null, $tableName, $note);

        return new Response('', 302, ['Location' => '/admin/schema/' . $connectionId . '/table?table=' . rawurlencode($tableName)]);
    }, 'admin.schema.table_note', 'web');

    $routes->post('/admin/schema/{connectionId}/column-note', static function (Request $request) use ($connections, $metadata): Response {
        $connectionId = (int) $request->route('connectionId');
        $tableName = (string) $request->post('table_name', '');
        $columnName = (string) $request->post('column_name', '');
        $note = trim((string) $request->post('note', '')) ?: null;
        $profile = $connections()->find($connectionId);
        $metadata()->saveColumnNote($connectionId, isset($profile['workspace_id']) ? (int) $profile['workspace_id'] : null, null, $tableName, $columnName, $note);

        return new Response('', 302, ['Location' => '/admin/schema/' . $connectionId . '/table?table=' . rawurlencode($tableName)]);
    }, 'admin.schema.column_note', 'web');

    $routes->get('/admin/mappings', static fn (): Response => $admin('admin/mappings', [
        'title' => 'Mappings',
        'active' => 'mappings',
        'mappings' => [
            ['source' => 'customers.customer_id', 'target' => 'transfer_customer.external_id', 'rule' => 'Direkt'],
            ['source' => 'customers.email', 'target' => 'transfer_customer.email', 'rule' => 'Trim'],
        ],
    ]), 'admin.mappings', 'web');

    $routes->get('/admin/jobs', static fn (): Response => $admin('admin/jobs', [
        'title' => 'Jobs',
        'active' => 'jobs',
        'jobs' => [
            ['name' => 'Demo Transfer', 'status' => 'Bereit', 'lastRun' => '-'],
            ['name' => 'Report Versand', 'status' => 'Geplant', 'lastRun' => '-'],
        ],
    ]), 'admin.jobs', 'web');

    $routes->get('/admin/reports', static fn (): Response => $admin('admin/reports', [
        'title' => 'Reports',
        'active' => 'reports',
        'reports' => [
            ['name' => 'Transfer Status', 'type' => 'E-Mail', 'schedule' => 'Manuell'],
            ['name' => 'Fehlerübersicht', 'type' => 'E-Mail', 'schedule' => 'Täglich geplant'],
        ],
    ]), 'admin.reports', 'web');

    $routes->get('/health', static fn (): Response => Response::json([
        'status' => 'ok',
        'app' => $app->config()->string('APP_NAME', 'Luna V3'),
    ]), 'web.health', 'web');
};
