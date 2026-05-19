<?php

declare(strict_types=1);

use Luna\Connections\ConnectionTester;
use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Core\Application;
use Luna\Http\Request;
use Luna\Http\Response;
use Luna\Jobs\JobRunner;
use Luna\Mapping\MappingValidator;
use Luna\Mapping\TransformType;
use Luna\Reports\ReportMailer;
use Luna\Repository\AuditLogRepository;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\JobRepository;
use Luna\Repository\JobRunRepository;
use Luna\Repository\MappingRepository;
use Luna\Repository\ReportRepository;
use Luna\Repository\SchemaMetadataRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Routing\RouteCollection;
use Luna\Schema\SampleDataReader;
use Luna\Schema\SchemaInspector;
use Luna\View\ViewRenderer;

if (! function_exists('safeList')) {
    function safeList(Closure $repositoryFactory): array
    {
        try {
            return $repositoryFactory()->all();
        } catch (Throwable) {
            return [];
        }
    }
}

if (! function_exists('mappingSetValues')) {
    function mappingSetValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'description' => (string) $request->post('description', ''),
            'source_connection_id' => $request->post('source_connection_id'),
            'source_table' => (string) $request->post('source_table', ''),
            'target_connection_id' => $request->post('target_connection_id'),
            'target_table' => (string) $request->post('target_table', ''),
            'status' => (string) $request->post('status', 'draft'),
        ];
    }
}

if (! function_exists('mappingSetErrors')) {
    function mappingSetErrors(array $values): array
    {
        $errors = [];

        if (trim((string) $values['name']) === '') {
            $errors[] = 'Name ist erforderlich.';
        }

        if (! in_array((string) $values['status'], ['draft', 'active', 'archived'], true)) {
            $errors[] = 'Status ist ungültig.';
        }

        return $errors;
    }
}

if (! function_exists('mappingFieldValues')) {
    function mappingFieldValues(Request $request): array
    {
        return [
            'source_column' => (string) $request->post('source_column', ''),
            'source_json_path' => (string) $request->post('source_json_path', ''),
            'target_column' => (string) $request->post('target_column', ''),
            'transform_type' => (string) $request->post('transform_type', 'direct'),
            'default_value' => (string) $request->post('default_value', ''),
            'is_required' => $request->post('is_required') !== null ? '1' : '',
            'notes' => (string) $request->post('notes', ''),
            'sort_order' => (int) $request->post('sort_order', 0),
        ];
    }
}

if (! function_exists('mappingViewData')) {
    function mappingViewData(Closure $mappings, int $id, array $extra = []): array
    {
        try {
            $mapping = $mappings()->find($id);

            return $extra + [
                'mapping' => $mapping,
                'fields' => $mapping === null ? [] : $mappings()->fieldsForSet($id),
                'error' => null,
            ];
        } catch (Throwable) {
            return $extra + [
                'mapping' => null,
                'fields' => [],
                'error' => 'Mapping konnte nicht geladen werden.',
            ];
        }
    }
}

if (! function_exists('mappingFieldsData')) {
    function mappingFieldsData(Closure $mappings, Closure $connections, Closure $pdoFactory, int $id, array $extra = []): array
    {
        $data = mappingViewData($mappings, $id, $extra);
        $data['sourceColumns'] = [];
        $data['targetColumns'] = [];
        $data['columnWarning'] = null;

        if ($data['mapping'] === null) {
            return $data;
        }

        try {
            $source = $connections()->find((int) $data['mapping']['source_connection_id']);
            $target = $connections()->find((int) $data['mapping']['target_connection_id']);

            if ($source !== null && ! empty($data['mapping']['source_table'])) {
                $sourceConfig = ExternalDatabaseConfig::fromProfile($source, $connections()->secretsFor((int) $source['id']));
                $data['sourceColumns'] = (new SchemaInspector($pdoFactory()->create($sourceConfig)))->columns((string) $data['mapping']['source_table']);
            }

            if ($target !== null && ! empty($data['mapping']['target_table'])) {
                $targetConfig = ExternalDatabaseConfig::fromProfile($target, $connections()->secretsFor((int) $target['id']));
                $data['targetColumns'] = (new SchemaInspector($pdoFactory()->create($targetConfig)))->columns((string) $data['mapping']['target_table']);
            }
        } catch (Throwable) {
            $data['columnWarning'] = 'Spalten konnten nicht gelesen werden. Textfelder bleiben nutzbar.';
        }

        return $data;
    }
}

if (! function_exists('jobValues')) {
    function jobValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'mapping_set_id' => $request->post('mapping_set_id'),
            'name' => (string) $request->post('name', ''),
            'type' => 'mapping_transfer',
            'status' => (string) $request->post('status', 'draft'),
            'run_mode' => (string) $request->post('run_mode', 'manual'),
            'transfer_mode' => (string) $request->post('transfer_mode', 'insert'),
            'dry_run_default' => $request->post('dry_run_default') !== null ? '1' : '',
            'batch_size' => (int) $request->post('batch_size', 100),
            'row_limit' => $request->post('row_limit'),
            'report_enabled' => $request->post('report_enabled') !== null ? '1' : '',
            'report_recipients' => (string) $request->post('report_recipients', ''),
            'notes' => (string) $request->post('notes', ''),
        ];
    }
}

if (! function_exists('endpointValues')) {
    function endpointValues(Request $request): array
    {
        $staticResponse = trim((string) $request->post('static_response', ''));
        $config = [];
        if ($staticResponse !== '') {
            $decoded = json_decode($staticResponse, true);
            if (is_array($decoded)) {
                $config['static_response'] = $decoded;
            }
        }

        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'endpoint_key' => EndpointRepository::normalizeEndpointKey((string) $request->post('endpoint_key', '')),
            'description' => (string) $request->post('description', ''),
            'method' => (string) $request->post('method', 'GET'),
            'visibility' => (string) $request->post('visibility', 'private'),
            'status' => (string) $request->post('status', 'draft'),
            'response_type' => 'json',
            'source_type' => (string) $request->post('source_type', 'static'),
            'mapping_set_id' => $request->post('mapping_set_id'),
            'job_id' => $request->post('job_id'),
            'config_json' => $config === [] ? '' : (json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            'rate_limit_per_minute' => $request->post('rate_limit_per_minute'),
            'notes' => (string) $request->post('notes', ''),
            'static_response' => $staticResponse,
        ];
    }
}

if (! function_exists('endpointErrors')) {
    function endpointErrors(array $values, bool $requireSecret, string $staticResponse): array
    {
        $errors = [];
        if (trim((string) $values['name']) === '') {
            $errors[] = 'Name ist erforderlich.';
        }
        if (trim((string) $values['endpoint_key']) === '') {
            $errors[] = 'Endpoint Key ist erforderlich.';
        }
        if (! in_array((string) $values['method'], ['GET', 'POST'], true)) {
            $errors[] = 'Method ist ungueltig.';
        }
        if (! in_array((string) $values['visibility'], ['public', 'private'], true)) {
            $errors[] = 'Visibility ist ungueltig.';
        }
        if (! in_array((string) $values['status'], ['draft', 'active', 'disabled'], true)) {
            $errors[] = 'Status ist ungueltig.';
        }
        if (! in_array((string) $values['source_type'], ['static', 'version', 'mapping_dry_run', 'job_status', 'latest_report'], true)) {
            $errors[] = 'Source Type ist ungueltig.';
        }
        if ($requireSecret && (string) $values['visibility'] === 'private') {
            $errors[] = 'Private Endpoints sollten ein Secret erhalten.';
        }
        if ($staticResponse !== '' && json_decode($staticResponse, true) === null && json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'Static Response JSON ist ungueltig.';
        }

        return $errors;
    }
}

if (! function_exists('workspaceValues')) {
    function workspaceValues(Request $request): array
    {
        $name = trim((string) $request->post('name', ''));
        $slug = WorkspaceRepository::normalizeSlug((string) $request->post('slug', ''));

        return [
            'name' => $name,
            'slug' => $slug !== '' ? $slug : WorkspaceRepository::normalizeSlug($name),
            'description' => trim((string) $request->post('description', '')),
            'status' => (string) $request->post('status', 'active'),
        ];
    }
}

if (! function_exists('workspaceErrors')) {
    function workspaceErrors(array $values, WorkspaceRepository $workspaces, ?int $ignoreId = null): array
    {
        $errors = [];

        if ((string) $values['name'] === '') {
            $errors[] = 'Name ist erforderlich.';
        }

        if ((string) $values['slug'] === '') {
            $errors[] = 'Slug konnte nicht erzeugt werden.';
        }

        if (! in_array((string) $values['status'], ['active', 'archived', 'disabled'], true)) {
            $errors[] = 'Status ist ungueltig.';
        }

        if ((string) $values['slug'] !== '' && $workspaces->slugExists((string) $values['slug'], $ignoreId)) {
            $errors[] = 'Slug ist bereits vergeben.';
        }

        return $errors;
    }
}

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
    $mappings = static fn (): MappingRepository => $app->services()->get('repository.mappings');
    $audit = static fn (): AuditLogRepository => $app->services()->get('repository.audit_log');
    $jobs = static fn (): JobRepository => $app->services()->get('repository.jobs');
    $runs = static fn (): JobRunRepository => $app->services()->get('repository.job_runs');
    $reports = static fn (): ReportRepository => $app->services()->get('repository.reports');
    $endpoints = static fn (): EndpointRepository => $app->services()->get('repository.endpoints');
    $jobRunner = static fn (): JobRunner => $app->services()->get('jobs.runner');
    $reportMailer = static fn (): ReportMailer => $app->services()->get('reports.mailer');
    $validator = static fn (): MappingValidator => $app->services()->get('mapping.validator');
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
            $items = $workspaces()->all();
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

    $routes->get('/admin/workspaces/create', static fn (): Response => $admin('admin/workspaces/create', [
        'title' => 'Workspace anlegen',
        'active' => 'workspaces',
        'values' => ['status' => 'active'],
        'errors' => [],
    ]), 'admin.workspaces.create', 'web');

    $routes->post('/admin/workspaces', static function (Request $request) use ($admin, $workspaces, $audit): Response {
        $values = workspaceValues($request);
        $errors = workspaceErrors($values, $workspaces());

        if ($errors !== []) {
            return $admin('admin/workspaces/create', [
                'title' => 'Workspace anlegen',
                'active' => 'workspaces',
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        $id = $workspaces()->create((string) $values['slug'], (string) $values['name'], trim((string) $values['description']) ?: null);
        if ((string) $values['status'] !== 'active') {
            $workspaces()->update($id, (string) $values['slug'], (string) $values['name'], trim((string) $values['description']) ?: null, (string) $values['status']);
        }
        $audit()->log($id, 'workspace.created', 'workspace', (string) $id, 'Workspace erstellt.', [
            'slug' => $values['slug'],
            'name' => $values['name'],
            'status' => $values['status'],
        ]);

        return new Response('', 302, ['Location' => '/admin/workspaces/' . $id]);
    }, 'admin.workspaces.store', 'web');

    $routes->get('/admin/workspaces/{id}', static function (Request $request) use ($admin, $workspaces): Response {
        $workspace = $workspaces()->find((int) $request->route('id'));

        return $admin('admin/workspaces/show', [
            'title' => $workspace['name'] ?? 'Workspace',
            'active' => 'workspaces',
            'workspace' => $workspace,
            'values' => $workspace ?? ['status' => 'active'],
            'errors' => [],
        ]);
    }, 'admin.workspaces.show', 'web');

    $routes->post('/admin/workspaces/{id}', static function (Request $request) use ($admin, $workspaces, $audit): Response {
        $id = (int) $request->route('id');
        $workspace = $workspaces()->find($id);

        if ($workspace === null) {
            return Response::notFound();
        }

        $values = workspaceValues($request);
        $errors = workspaceErrors($values, $workspaces(), $id);

        if ($errors !== []) {
            return $admin('admin/workspaces/show', [
                'title' => 'Workspace bearbeiten',
                'active' => 'workspaces',
                'workspace' => $workspace,
                'values' => $values + ['id' => $id],
                'errors' => $errors,
            ]);
        }

        $workspaces()->update($id, (string) $values['slug'], (string) $values['name'], trim((string) $values['description']) ?: null, (string) $values['status']);
        $audit()->log($id, 'workspace.updated', 'workspace', (string) $id, 'Workspace aktualisiert.', [
            'slug' => $values['slug'],
            'name' => $values['name'],
            'status' => $values['status'],
        ]);

        return new Response('', 302, ['Location' => '/admin/workspaces/' . $id]);
    }, 'admin.workspaces.update', 'web');

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

    $routes->get('/admin/schema/{connectionId}/tables.json', static function (Request $request) use ($connections, $configFor, $pdoFactory): Response {
        try {
            $profile = $connections()->find((int) $request->route('connectionId'));

            if ($profile === null) {
                throw new RuntimeException('Connection nicht gefunden.');
            }

            $tables = (new SchemaInspector($pdoFactory()->create($configFor($profile))))->tables();

            return Response::json([
                'success' => true,
                'tables' => array_map(static fn (array $table): array => [
                    'name' => (string) $table['table_name'],
                    'label' => (string) $table['table_name'],
                ], $tables),
            ]);
        } catch (Throwable) {
            return Response::json([
                'success' => false,
                'message' => 'Tabellen konnten nicht geladen werden.',
            ], 500);
        }
    }, 'admin.schema.tables_json', 'web');

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

    $routes->get('/admin/mappings', static function () use ($admin, $mappings): Response {
        try {
            $items = $mappings()->all();
            $error = null;
        } catch (Throwable) {
            $items = [];
            $error = 'Luna-Systemdatenbank ist nicht erreichbar.';
        }

        return $admin('admin/mappings/index', [
            'title' => 'Mappings',
            'active' => 'mappings',
            'mappings' => $items,
            'error' => $error,
        ]);
    }, 'admin.mappings', 'web');

    $routes->get('/admin/mappings/create', static fn (): Response => $admin('admin/mappings/create', [
        'title' => 'Mapping anlegen',
        'active' => 'mappings',
        'workspaces' => $safeAll($workspaces),
        'connections' => $safeAll($connections),
        'values' => ['status' => 'draft'],
        'errors' => [],
    ]), 'admin.mappings.create', 'web');

    $routes->post('/admin/mappings', static function (Request $request) use ($admin, $mappings, $audit, $workspaces, $connections): Response {
        $values = mappingSetValues($request);
        $errors = mappingSetErrors($values);

        if ($errors !== []) {
            return $admin('admin/mappings/create', [
                'title' => 'Mapping anlegen',
                'active' => 'mappings',
                'workspaces' => safeList($workspaces),
                'connections' => safeList($connections),
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        try {
            $id = $mappings()->createSet($values);
            $audit()->log(
                empty($values['workspace_id']) ? null : (int) $values['workspace_id'],
                'mapping_set.created',
                'mapping_set',
                (string) $id,
                'Mapping Set erstellt.',
                ['name' => $values['name']],
            );

            return new Response('', 302, ['Location' => '/admin/mappings/' . $id]);
        } catch (Throwable) {
            return $admin('admin/mappings/create', [
                'title' => 'Mapping anlegen',
                'active' => 'mappings',
                'workspaces' => safeList($workspaces),
                'connections' => safeList($connections),
                'values' => $values,
                'errors' => ['Mapping Set konnte nicht gespeichert werden.'],
            ]);
        }
    }, 'admin.mappings.store', 'web');

    $routes->get('/admin/mappings/{id}', static function (Request $request) use ($admin, $mappings): Response {
        $id = (int) $request->route('id');

        return $admin('admin/mappings/show', mappingViewData($mappings, $id, [
            'title' => 'Mapping',
            'active' => 'mappings',
            'alert' => null,
            'validation' => null,
        ]));
    }, 'admin.mappings.show', 'web');

    $routes->post('/admin/mappings/{id}', static function (Request $request) use ($admin, $mappings, $audit, $workspaces, $connections): Response {
        $id = (int) $request->route('id');
        $values = mappingSetValues($request);
        $errors = mappingSetErrors($values);

        if ($errors !== []) {
            return $admin('admin/mappings/create', [
                'title' => 'Mapping bearbeiten',
                'active' => 'mappings',
                'workspaces' => safeList($workspaces),
                'connections' => safeList($connections),
                'values' => $values + ['id' => $id],
                'errors' => $errors,
            ]);
        }

        $mappings()->updateSet($id, $values);
        $audit()->log(
            empty($values['workspace_id']) ? null : (int) $values['workspace_id'],
            'mapping_set.updated',
            'mapping_set',
            (string) $id,
            'Mapping Set aktualisiert.',
            ['name' => $values['name']],
        );

        return $admin('admin/mappings/show', mappingViewData($mappings, $id, [
            'title' => 'Mapping',
            'active' => 'mappings',
            'alert' => ['type' => 'warning', 'message' => 'Mapping aktualisiert. Bitte erneut validieren.'],
            'validation' => null,
        ]));
    }, 'admin.mappings.update', 'web');

    $routes->get('/admin/mappings/{id}/fields', static function (Request $request) use ($admin, $mappings, $connections, $pdoFactory): Response {
        $id = (int) $request->route('id');

        return $admin('admin/mappings/fields', mappingFieldsData($mappings, $connections, $pdoFactory, $id, [
            'title' => 'Feldzuordnungen',
            'active' => 'mappings',
            'transformTypes' => TransformType::labels(),
        ]));
    }, 'admin.mappings.fields', 'web');

    $routes->post('/admin/mappings/{id}/fields', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $set = $mappings()->find($id);
        $fieldId = $mappings()->addField($id, mappingFieldValues($request));
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_field.created',
            'mapping_field',
            (string) $fieldId,
            'Mapping Field erstellt.',
            ['mapping_set_id' => $id],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields']);
    }, 'admin.mappings.fields.store', 'web');

    $routes->post('/admin/mappings/{id}/fields/{fieldId}', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');
        $set = $mappings()->find($id);
        $mappings()->updateField($fieldId, mappingFieldValues($request));
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_field.updated',
            'mapping_field',
            (string) $fieldId,
            'Mapping Field aktualisiert.',
            ['mapping_set_id' => $id],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields']);
    }, 'admin.mappings.fields.update', 'web');

    $routes->post('/admin/mappings/{id}/fields/{fieldId}/delete', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');
        $set = $mappings()->find($id);
        $mappings()->deleteField($fieldId);
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_field.deleted',
            'mapping_field',
            (string) $fieldId,
            'Mapping Field gelöscht.',
            ['mapping_set_id' => $id],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields']);
    }, 'admin.mappings.fields.delete', 'web');

    $routes->get('/admin/mappings/{id}/fields/{fieldId}/value-rules', static function (Request $request) use ($admin, $mappings): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');

        return $admin('admin/mappings/value-rules', [
            'title' => 'Value Rules',
            'active' => 'mappings',
            'mapping' => $mappings()->find($id),
            'field' => $mappings()->findField($fieldId),
            'rules' => $mappings()->valueRulesForField($fieldId),
        ]);
    }, 'admin.mappings.value_rules', 'web');

    $routes->post('/admin/mappings/{id}/fields/{fieldId}/value-rules', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');
        $set = $mappings()->find($id);
        $ruleId = $mappings()->addValueRule(
            $fieldId,
            (string) $request->post('source_value', ''),
            (string) $request->post('target_value', ''),
            trim((string) $request->post('notes', '')) ?: null,
        );
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_value_rule.created',
            'mapping_value_rule',
            (string) $ruleId,
            'Value Rule erstellt.',
            ['mapping_set_id' => $id, 'mapping_field_id' => $fieldId],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields/' . $fieldId . '/value-rules']);
    }, 'admin.mappings.value_rules.store', 'web');

    $routes->post('/admin/mappings/{id}/fields/{fieldId}/value-rules/{ruleId}/delete', static function (Request $request) use ($mappings, $audit): Response {
        $id = (int) $request->route('id');
        $fieldId = (int) $request->route('fieldId');
        $ruleId = (int) $request->route('ruleId');
        $set = $mappings()->find($id);
        $mappings()->deleteValueRule($ruleId);
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping_value_rule.deleted',
            'mapping_value_rule',
            (string) $ruleId,
            'Value Rule gelöscht.',
            ['mapping_set_id' => $id, 'mapping_field_id' => $fieldId],
        );

        return new Response('', 302, ['Location' => '/admin/mappings/' . $id . '/fields/' . $fieldId . '/value-rules']);
    }, 'admin.mappings.value_rules.delete', 'web');

    $routes->post('/admin/mappings/{id}/validate', static function (Request $request) use ($admin, $mappings, $audit, $validator): Response {
        $id = (int) $request->route('id');
        $set = $mappings()->find($id);
        $result = $validator()->validate($id);
        $audit()->log(
            isset($set['workspace_id']) ? (int) $set['workspace_id'] : null,
            'mapping.validation_run',
            'mapping_set',
            (string) $id,
            'Mapping-Validierung ausgeführt.',
            ['valid' => $result->isValid()],
        );

        return $admin('admin/mappings/show', mappingViewData($mappings, $id, [
            'title' => 'Mapping',
            'active' => 'mappings',
            'alert' => null,
            'validation' => $result,
        ]));
    }, 'admin.mappings.validate', 'web');

    $routes->post('/admin/mappings/{id}/dry-run', static function (Request $request) use ($jobRunner): Response {
        $runId = $jobRunner()->runMappingOnce((int) $request->route('id'), true, 25);
        return new Response('', 302, ['Location' => '/admin/jobs/runs/' . $runId]);
    }, 'admin.mappings.dry_run', 'web');

    $routes->post('/admin/mappings/{id}/run', static function (Request $request) use ($jobRunner): Response {
        if ($request->post('confirm') !== 'run') {
            return Response::text('Transfer confirmation missing.', 400);
        }
        $runId = $jobRunner()->runMappingOnce((int) $request->route('id'), false);
        return new Response('', 302, ['Location' => '/admin/jobs/runs/' . $runId]);
    }, 'admin.mappings.run', 'web');

    $routes->get('/admin/jobs', static fn (): Response => $admin('admin/jobs/index', [
        'title' => 'Jobs',
        'active' => 'jobs',
        'jobs' => safeList($jobs),
    ]), 'admin.jobs', 'web');

    $routes->get('/admin/jobs/create', static fn (): Response => $admin('admin/jobs/create', [
        'title' => 'Job anlegen',
        'active' => 'jobs',
        'workspaces' => safeList($workspaces),
        'mappings' => safeList($mappings),
        'values' => ['status' => 'draft', 'run_mode' => 'manual', 'transfer_mode' => 'insert', 'dry_run_default' => '1', 'batch_size' => 100],
        'errors' => [],
    ]), 'admin.jobs.create', 'web');

    $routes->post('/admin/jobs', static function (Request $request) use ($admin, $jobs, $audit, $workspaces, $mappings): Response {
        $values = jobValues($request);
        $errors = trim($values['name']) === '' ? ['Name ist erforderlich.'] : [];
        if ($errors !== []) {
            return $admin('admin/jobs/create', ['title' => 'Job anlegen', 'active' => 'jobs', 'workspaces' => safeList($workspaces), 'mappings' => safeList($mappings), 'values' => $values, 'errors' => $errors]);
        }
        $id = $jobs()->create($values);
        $audit()->log(empty($values['workspace_id']) ? null : (int) $values['workspace_id'], 'job.created', 'job', (string) $id, 'Job erstellt.');
        return new Response('', 302, ['Location' => '/admin/jobs/' . $id]);
    }, 'admin.jobs.store', 'web');

    $routes->get('/admin/jobs/{id}', static function (Request $request) use ($admin, $jobs, $runs): Response {
        $id = (int) $request->route('id');
        return $admin('admin/jobs/show', ['title' => 'Job', 'active' => 'jobs', 'job' => $jobs()->find($id), 'runs' => $runs()->runsForJob($id)]);
    }, 'admin.jobs.show', 'web');

    $routes->post('/admin/jobs/{id}', static function (Request $request) use ($jobs, $audit): Response {
        $id = (int) $request->route('id');
        $values = jobValues($request);
        $jobs()->update($id, $values);
        $audit()->log(empty($values['workspace_id']) ? null : (int) $values['workspace_id'], 'job.updated', 'job', (string) $id, 'Job aktualisiert.');
        return new Response('', 302, ['Location' => '/admin/jobs/' . $id]);
    }, 'admin.jobs.update', 'web');

    $routes->post('/admin/jobs/{id}/dry-run', static function (Request $request) use ($jobRunner): Response {
        $runId = $jobRunner()->runJob((int) $request->route('id'), true);
        return new Response('', 302, ['Location' => '/admin/jobs/runs/' . $runId]);
    }, 'admin.jobs.dry_run', 'web');

    $routes->post('/admin/jobs/{id}/run', static function (Request $request) use ($jobRunner): Response {
        if ($request->post('confirm') !== 'run') {
            return Response::text('Transfer confirmation missing.', 400);
        }
        $runId = $jobRunner()->runJob((int) $request->route('id'), false);
        return new Response('', 302, ['Location' => '/admin/jobs/runs/' . $runId]);
    }, 'admin.jobs.run', 'web');

    $routes->get('/admin/jobs/{id}/runs', static function (Request $request) use ($admin, $jobs, $runs): Response {
        $id = (int) $request->route('id');
        return $admin('admin/jobs/runs', ['title' => 'Job Runs', 'active' => 'jobs', 'job' => $jobs()->find($id), 'runs' => $runs()->runsForJob($id)]);
    }, 'admin.jobs.runs', 'web');

    $routes->get('/admin/jobs/runs/{runId}', static function (Request $request) use ($admin, $runs): Response {
        $runId = (int) $request->route('runId');
        return $admin('admin/jobs/run', ['title' => 'Job Run', 'active' => 'jobs', 'run' => $runs()->findRun($runId), 'logs' => $runs()->logsForRun($runId)]);
    }, 'admin.jobs.run_show', 'web');

    $routes->get('/admin/reports', static fn (): Response => $admin('admin/reports/index', [
        'title' => 'Reports',
        'active' => 'reports',
        'reports' => safeList($reports),
    ]), 'admin.reports', 'web');

    $routes->get('/admin/reports/{id}', static function (Request $request) use ($admin, $reports): Response {
        return $admin('admin/reports/show', ['title' => 'Report', 'active' => 'reports', 'report' => $reports()->find((int) $request->route('id')), 'result' => null]);
    }, 'admin.reports.show', 'web');

    $routes->post('/admin/reports/{id}/send', static function (Request $request) use ($admin, $reports, $reportMailer): Response {
        $id = (int) $request->route('id');
        $result = $reportMailer()->send($id);
        return $admin('admin/reports/show', ['title' => 'Report', 'active' => 'reports', 'report' => $reports()->find($id), 'result' => $result]);
    }, 'admin.reports.send', 'web');

    $routes->get('/admin/endpoints', static fn (): Response => $admin('admin/endpoints/index', [
        'title' => 'Endpoints',
        'active' => 'endpoints',
        'endpoints' => safeList($endpoints),
    ]), 'admin.endpoints', 'web');

    $routes->get('/admin/endpoints/create', static fn (): Response => $admin('admin/endpoints/create', [
        'title' => 'Endpoint anlegen',
        'active' => 'endpoints',
        'workspaces' => safeList($workspaces),
        'mappings' => safeList($mappings),
        'jobs' => safeList($jobs),
        'values' => ['method' => 'GET', 'visibility' => 'private', 'status' => 'draft', 'source_type' => 'static', 'response_type' => 'json'],
        'errors' => [],
    ]), 'admin.endpoints.create', 'web');

    $routes->post('/admin/endpoints', static function (Request $request) use ($admin, $endpoints, $audit, $workspaces, $mappings, $jobs): Response {
        $values = endpointValues($request);
        $secret = (string) $request->post('secret', '');
        $errors = endpointErrors($values, $secret === '', (string) $values['static_response']);

        if ($errors !== []) {
            return $admin('admin/endpoints/create', [
                'title' => 'Endpoint anlegen',
                'active' => 'endpoints',
                'workspaces' => safeList($workspaces),
                'mappings' => safeList($mappings),
                'jobs' => safeList($jobs),
                'values' => $values,
                'errors' => $errors,
            ]);
        }

        $id = $endpoints()->create($values, ['secret' => $secret]);
        $audit()->log(empty($values['workspace_id']) ? null : (int) $values['workspace_id'], 'endpoint.created', 'endpoint', (string) $id, 'Endpoint erstellt.', [
            'endpoint_key' => $values['endpoint_key'],
            'visibility' => $values['visibility'],
        ]);

        return new Response('', 302, ['Location' => '/admin/endpoints/' . $id]);
    }, 'admin.endpoints.store', 'web');

    $routes->get('/admin/endpoints/{id}', static function (Request $request) use ($admin, $endpoints, $workspaces, $mappings, $jobs): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);
        $config = $endpoint === null ? [] : (json_decode((string) ($endpoint['config_json'] ?? '{}'), true) ?: []);

        return $admin('admin/endpoints/show', [
            'title' => 'Endpoint',
            'active' => 'endpoints',
            'endpoint' => $endpoint,
            'workspaces' => safeList($workspaces),
            'mappings' => safeList($mappings),
            'jobs' => safeList($jobs),
            'staticResponse' => isset($config['static_response']) ? (json_encode($config['static_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') : '',
            'hasSecret' => $endpoint !== null && $endpoints()->hasSecret((int) $endpoint['id']),
            'alert' => null,
        ]);
    }, 'admin.endpoints.show', 'web');

    $routes->post('/admin/endpoints/{id}', static function (Request $request) use ($admin, $endpoints, $audit, $workspaces, $mappings, $jobs): Response {
        $id = (int) $request->route('id');
        $existing = $endpoints()->find($id);
        if ($existing === null) {
            return Response::notFound();
        }

        $values = endpointValues($request);
        $secret = (string) $request->post('secret', '');
        $errors = endpointErrors($values, false, (string) $values['static_response']);
        if ($errors !== []) {
            return $admin('admin/endpoints/show', [
                'title' => 'Endpoint',
                'active' => 'endpoints',
                'endpoint' => $values + ['id' => $id],
                'workspaces' => safeList($workspaces),
                'mappings' => safeList($mappings),
                'jobs' => safeList($jobs),
                'staticResponse' => (string) $values['static_response'],
                'hasSecret' => $endpoints()->hasSecret($id),
                'alert' => ['type' => 'danger', 'message' => implode(' ', $errors)],
            ]);
        }

        $endpoints()->update($id, $values, ['secret' => $secret]);
        $audit()->log(empty($values['workspace_id']) ? null : (int) $values['workspace_id'], 'endpoint.updated', 'endpoint', (string) $id, 'Endpoint aktualisiert.', [
            'endpoint_key' => $values['endpoint_key'],
            'visibility' => $values['visibility'],
        ]);

        return new Response('', 302, ['Location' => '/admin/endpoints/' . $id]);
    }, 'admin.endpoints.update', 'web');

    $routes->post('/admin/endpoints/{id}/delete', static function (Request $request) use ($endpoints, $audit): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);
        $endpoints()->delete($id);
        $audit()->log(isset($endpoint['workspace_id']) ? (int) $endpoint['workspace_id'] : null, 'endpoint.deleted', 'endpoint', (string) $id, 'Endpoint geloescht.');

        return new Response('', 302, ['Location' => '/admin/endpoints']);
    }, 'admin.endpoints.delete', 'web');

    $routes->post('/admin/endpoints/{id}/test', static function (Request $request) use ($admin, $endpoints, $app): Response {
        $id = (int) $request->route('id');
        $endpoint = $endpoints()->find($id);
        if ($endpoint === null) {
            return Response::notFound();
        }

        $result = ['notice' => 'Private Endpoints benoetigen ein Secret. Secrets werden hier nicht angezeigt.'];
        if ((string) $endpoint['visibility'] === 'public') {
            $result = json_decode($app->services()->get('api.endpoint_response_builder')->build($endpoint)->body(), true) ?: [];
        }

        return $admin('admin/endpoints/test', [
            'title' => 'Endpoint testen',
            'active' => 'endpoints',
            'endpoint' => $endpoint,
            'result' => $result,
        ]);
    }, 'admin.endpoints.test', 'web');

    $routes->get('/admin/audit', static fn (): Response => $admin('admin/audit/index', [
        'title' => 'Audit',
        'active' => 'audit',
        'entries' => $audit()->recent(100),
    ]), 'admin.audit', 'web');

    $routes->get('/health', static fn (): Response => Response::json([
        'status' => 'ok',
        'app' => $app->config()->string('APP_NAME', 'Luna V3'),
    ]), 'web.health', 'web');
};

if (! function_exists('safeList')) {
    function safeList(Closure $repositoryFactory): array
    {
        try {
            return $repositoryFactory()->all();
        } catch (Throwable) {
            return [];
        }
    }
}

if (! function_exists('mappingSetValues')) {
    function mappingSetValues(Request $request): array
    {
        return [
            'workspace_id' => $request->post('workspace_id'),
            'name' => (string) $request->post('name', ''),
            'description' => (string) $request->post('description', ''),
            'source_connection_id' => $request->post('source_connection_id'),
            'source_table' => (string) $request->post('source_table', ''),
            'target_connection_id' => $request->post('target_connection_id'),
            'target_table' => (string) $request->post('target_table', ''),
            'status' => (string) $request->post('status', 'draft'),
        ];
    }
}

if (! function_exists('mappingSetErrors')) {
    function mappingSetErrors(array $values): array
    {
        $errors = [];

        if (trim((string) $values['name']) === '') {
            $errors[] = 'Name ist erforderlich.';
        }

        if (! in_array((string) $values['status'], ['draft', 'active', 'archived'], true)) {
            $errors[] = 'Status ist ungültig.';
        }

        return $errors;
    }
}

if (! function_exists('mappingFieldValues')) {
    function mappingFieldValues(Request $request): array
    {
        return [
            'source_column' => (string) $request->post('source_column', ''),
            'source_json_path' => (string) $request->post('source_json_path', ''),
            'target_column' => (string) $request->post('target_column', ''),
            'transform_type' => (string) $request->post('transform_type', 'direct'),
            'default_value' => (string) $request->post('default_value', ''),
            'is_required' => $request->post('is_required') !== null ? '1' : '',
            'notes' => (string) $request->post('notes', ''),
            'sort_order' => (int) $request->post('sort_order', 0),
        ];
    }
}

if (! function_exists('mappingViewData')) {
    function mappingViewData(Closure $mappings, int $id, array $extra = []): array
    {
        try {
            $mapping = $mappings()->find($id);

            return $extra + [
                'mapping' => $mapping,
                'fields' => $mapping === null ? [] : $mappings()->fieldsForSet($id),
                'error' => null,
            ];
        } catch (Throwable) {
            return $extra + [
                'mapping' => null,
                'fields' => [],
                'error' => 'Mapping konnte nicht geladen werden.',
            ];
        }
    }
}

if (! function_exists('mappingFieldsData')) {
    function mappingFieldsData(Closure $mappings, Closure $connections, Closure $pdoFactory, int $id, array $extra = []): array
    {
        $data = mappingViewData($mappings, $id, $extra);
        $data['sourceColumns'] = [];
        $data['targetColumns'] = [];
        $data['columnWarning'] = null;

        if ($data['mapping'] === null) {
            return $data;
        }

        try {
            $source = $connections()->find((int) $data['mapping']['source_connection_id']);
            $target = $connections()->find((int) $data['mapping']['target_connection_id']);

            if ($source !== null && ! empty($data['mapping']['source_table'])) {
                $sourceConfig = ExternalDatabaseConfig::fromProfile($source, $connections()->secretsFor((int) $source['id']));
                $data['sourceColumns'] = (new SchemaInspector($pdoFactory()->create($sourceConfig)))->columns((string) $data['mapping']['source_table']);
            }

            if ($target !== null && ! empty($data['mapping']['target_table'])) {
                $targetConfig = ExternalDatabaseConfig::fromProfile($target, $connections()->secretsFor((int) $target['id']));
                $data['targetColumns'] = (new SchemaInspector($pdoFactory()->create($targetConfig)))->columns((string) $data['mapping']['target_table']);
            }
        } catch (Throwable) {
            $data['columnWarning'] = 'Spalten konnten nicht gelesen werden. Textfelder bleiben nutzbar.';
        }

        return $data;
    }
}
