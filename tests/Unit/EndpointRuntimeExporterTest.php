<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Api\EndpointJsonResponseFactory;
use Luna\Export\EndpointExportArchiveService;
use Luna\Export\EndpointRuntimeExporter;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\MappingRepository;
use Luna\Security\EncryptionService;
use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class EndpointRuntimeExporterTest extends TestCase
{
    public function testEndpointExportCreatesDeployablePackageWithoutSecrets(): void
    {
        $target = $this->tempDirectory();
        $manifest = $this->exporter()->export('isr_prices', $target, true);

        self::assertSame('isr_prices', $manifest['endpoint']);
        self::assertFileExists($target . '/api/isr_prices.php');
        self::assertFileExists($target . '/runtime/bootstrap.php');
        self::assertFileExists($target . '/runtime/EndpointRunner.php');
        self::assertFileExists($target . '/runtime/ConnectionFactory.php');
        self::assertFileExists($target . '/runtime/MappingExecutor.php');
        self::assertFileExists($target . '/config/endpoint.isr_prices.php');
        self::assertFileExists($target . '/.env.example');
        self::assertFileDoesNotExist($target . '/.env');
        self::assertFileExists($target . '/manifest.json');

        $config = require $target . '/config/endpoint.isr_prices.php';
        self::assertSame('ISR Prices Export', $config['mapping']['name']);
        self::assertCount(5, $config['fields']);
        self::assertSame('first_non_empty', $config['fields'][3]['transform_type']);
        self::assertSame('key_value_map_by_prefix', $config['fields'][4]['transform_type']);
        self::assertSame('numeric_greater_than', $config['source_filters'][0]['operator']);
        self::assertArrayHasKey(1, $config['connections']);
        self::assertSame('LUNA_CONN_PIMCORE_OBJECTS_DATABASE', $config['connections'][1]['database_env']);

        $exported = $this->readExport($target);
        self::assertStringContainsString('LUNA_ENDPOINT_ISR_PRICES_SECRET=', $exported);
        self::assertStringContainsString('LUNA_CONN_PIMCORE_OBJECTS_DATABASE=', $exported);
        self::assertStringNotContainsString('plain-secret', $exported);
        self::assertStringNotContainsString('db-password', $exported);
        self::assertStringNotContainsString('secret_hash', $exported);
        self::assertStringNotContainsString('mysql://', $exported);
        self::assertStringNotContainsString('/admin/', $exported);

        $manifestBody = file_get_contents($target . '/manifest.json');
        self::assertIsString($manifestBody);
        self::assertStringContainsString('api/isr_prices.php', $manifestBody);
        self::assertStringNotContainsString('plain-secret', $manifestBody);
    }

    public function testEndpointExportWithLocalEnvWritesOnlyEnvFileSecrets(): void
    {
        $target = $this->tempDirectory();
        $this->exporter()->export('isr_prices', $target, true, true);

        self::assertFileExists($target . '/.env');
        self::assertFileExists($target . '/.env.example');

        $env = (string) file_get_contents($target . '/.env');
        $example = (string) file_get_contents($target . '/.env.example');
        $config = (string) file_get_contents($target . '/config/endpoint.isr_prices.php');
        $manifest = (string) file_get_contents($target . '/manifest.json');

        self::assertStringContainsString('LUNA_ENDPOINT_ISR_PRICES_SECRET=local-endpoint-secret', $env);
        self::assertStringContainsString('LUNA_CONN_PIMCORE_OBJECTS_HOST=objects.local', $env);
        self::assertStringContainsString('LUNA_CONN_PIMCORE_OBJECTS_DATABASE=objects_db', $env);
        self::assertStringContainsString('LUNA_CONN_PIMCORE_OBJECTS_USERNAME=objects_user', $env);
        self::assertStringContainsString('LUNA_CONN_PIMCORE_OBJECTS_PASSWORD=objects-password', $env);
        self::assertStringContainsString('LUNA_CONN_PIMCORE_OBJECTS_PORT=3307', $env);

        self::assertStringNotContainsString('local-endpoint-secret', $example);
        self::assertStringNotContainsString('objects-password', $example);
        self::assertStringNotContainsString('local-endpoint-secret', $config);
        self::assertStringNotContainsString('objects-password', $config);
        self::assertStringNotContainsString('local-endpoint-secret', $manifest);
        self::assertStringNotContainsString('objects-password', $manifest);
    }

    public function testWorkspaceStorageExportUsesWorkspaceSlugAndEndpointKey(): void
    {
        $basePath = $this->tempDirectory();
        $manifest = $this->exporter($basePath)->exportToWorkspaceStorage('isr_prices', true, false);
        $target = $basePath . '/storage/asfinstocks/exports/endpoints/isr_prices';

        self::assertSame('storage/asfinstocks/exports/endpoints/isr_prices', $manifest['target_path']);
        self::assertFileExists($target . '/api/isr_prices.php');
        self::assertFileExists($target . '/manifest.json');
        self::assertFileExists($target . '/.env.example');
        self::assertFileDoesNotExist($target . '/.env');
    }

    public function testComposerRequiresZipExtension(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

        self::assertIsArray($composer);
        self::assertSame('*', $composer['require']['ext-zip'] ?? null);
    }

    public function testArchiveServiceCreatesZipWithoutLocalEnvOrSecrets(): void
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP ZIP extension is not available.');
        }

        $target = $this->tempDirectory();
        $manifest = $this->exporter()->export('isr_prices', $target, true, true);
        file_put_contents($target . '/old-runtime.zip', 'old zip placeholder');
        $archivePath = dirname($target) . '/asfinstocks-isr_prices-runtime.zip';

        $files = (new EndpointExportArchiveService())->createArchive($target, $archivePath, true);

        self::assertFileExists($archivePath);
        self::assertContains('api/isr_prices.php', $files);
        self::assertContains('runtime/bootstrap.php', $files);
        self::assertContains('config/endpoint.isr_prices.php', $files);
        self::assertContains('.env.example', $files);
        self::assertContains('manifest.json', $files);
        self::assertNotContains('.env', $files);
        self::assertNotContains('old-runtime.zip', $files);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($archivePath));
        self::assertNotFalse($zip->locateName('api/isr_prices.php'));
        self::assertNotFalse($zip->locateName('runtime/bootstrap.php'));
        self::assertNotFalse($zip->locateName('config/endpoint.isr_prices.php'));
        self::assertNotFalse($zip->locateName('.env.example'));
        self::assertNotFalse($zip->locateName('manifest.json'));
        self::assertFalse($zip->locateName('.env'));
        self::assertFalse($zip->locateName('old-runtime.zip'));

        $contents = '';
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if ($name === false) {
                continue;
            }
            $contents .= (string) $zip->getFromName($name);
        }
        $zip->close();

        self::assertStringNotContainsString('local-endpoint-secret', $contents);
        self::assertStringNotContainsString('objects-password', $contents);
        self::assertSame('../asfinstocks-isr_prices-runtime.zip', $manifest['archive_path']);
    }

    public function testAdminExportCanCreateWorkspaceArchive(): void
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP ZIP extension is not available.');
        }

        $basePath = $this->tempDirectory();
        $exporter = $this->exporter($basePath);
        $manifest = $exporter->exportToWorkspaceStorage('isr_prices', true, true);
        $archivePath = $exporter->archivePathForEndpointId(5);

        (new EndpointExportArchiveService())->createArchive((string) $manifest['absolute_target_path'], $archivePath, true);

        self::assertFileExists($archivePath);
        self::assertSame($basePath . '/storage/asfinstocks/exports/endpoints/asfinstocks-isr_prices-runtime.zip', str_replace('\\', '/', $archivePath));
    }

    public function testAdminEndpointExportRouteIsPostOnly(): void
    {
        $routes = $this->loadWebRoutes();

        self::assertNull($routes->match(new \Luna\Http\Request('GET', '/admin/endpoints/5/export')));
        self::assertNotNull($routes->match(new \Luna\Http\Request('POST', '/admin/endpoints/5/export')));
    }

    public function testDownloadRouteReturnsZipResponseWithoutFreePath(): void
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP ZIP extension is not available.');
        }

        $basePath = $this->tempDirectory();
        $exporter = $this->exporter($basePath);
        $manifest = $exporter->exportToWorkspaceStorage('isr_prices', true, false);
        $archivePath = $exporter->archivePathForEndpointId(5);
        (new EndpointExportArchiveService())->createArchive((string) $manifest['absolute_target_path'], $archivePath, true);

        $routes = $this->loadWebRoutes($this->systemPdo(), $exporter);
        $request = new \Luna\Http\Request('GET', '/admin/endpoints/5/export/download');
        $route = $routes->match($request);
        self::assertNotNull($route);

        $request = $request->withRouteParams($route->parameters($request) ?? []);
        $handler = $route->handler();
        $response = $handler($request);
        self::assertSame(200, $response->statusCode());
        self::assertSame('application/zip', $response->headers()['Content-Type'] ?? null);
        self::assertSame('attachment; filename="asfinstocks-isr_prices-runtime.zip"', $response->headers()['Content-Disposition'] ?? null);
        self::assertStringStartsWith('PK', $response->body());
    }

    public function testCliSupportsZipFlag(): void
    {
        $cli = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/luna');

        self::assertStringContainsString('--zip', $cli);
        self::assertStringContainsString(EndpointExportArchiveService::class, $cli);
    }

    public function testGitignoreProtectsLocalExportEnvFiles(): void
    {
        $gitignore = (string) file_get_contents(dirname(__DIR__, 2) . '/.gitignore');

        self::assertStringContainsString('/storage/*/exports/', $gitignore);
        self::assertStringContainsString('/storage/*/exports/**', $gitignore);
        self::assertStringContainsString('/public/pim/', $gitignore);
        self::assertStringContainsString('/public/pim/**', $gitignore);
        self::assertStringContainsString('/public/pim/.env', $gitignore);
        self::assertStringContainsString('/storage/exports/*/.env', $gitignore);
        self::assertStringContainsString('/storage/exports/**/*.env', $gitignore);
        self::assertStringContainsString('/storage/*/exports/**/.env', $gitignore);
        self::assertStringContainsString('/public/pim/**/.env', $gitignore);
        self::assertStringContainsString('/storage/*/exports/**/*.zip', $gitignore);
        self::assertStringContainsString('/storage/**/exports/**/*.zip', $gitignore);
        self::assertStringContainsString('/public/pim/**/*.zip', $gitignore);
    }

    public function testDevRouterLetsExistingFilesPassThrough(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $probe = $public . '/__router_probe.php';
        file_put_contents($probe, '<?php echo "probe";');
        $_SERVER['REQUEST_URI'] = '/__router_probe.php';

        try {
            self::assertFalse(require $public . '/dev-router.php');
        } finally {
            unlink($probe);
        }
    }

    #[RunInSeparateProcess]
    public function testExportedRuntimeReturnsJsonAndChecksSecret(): void
    {
        $target = $this->tempDirectory();
        $database = $this->runtimeDatabase();
        $this->exporter()->export('isr_prices', $target, true);
        file_put_contents($target . '/.env', implode("\n", [
            'APP_ENV=production',
            'APP_DEBUG=false',
            'LUNA_ENDPOINT_ISR_PRICES_SECRET=expected-secret',
            'LUNA_CONN_PIMCORE_OBJECTS_DATABASE=' . $database,
            'LUNA_CONN_PIMCORE_SETTINGS_DATABASE=' . $database,
            'LUNA_CONN_SCHMUCKLAGER_DATABASE=' . $database,
            '',
        ]));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_LUNA_ENDPOINT_SECRET'] = 'expected-secret';
        ob_start();
        $payload = require $target . '/api/isr_prices.php';
        $json = (string) ob_get_clean();

        self::assertIsArray($payload);
        self::assertSame(true, $payload['success']);
        self::assertSame(1, $payload['count']);
        self::assertSame('W001', $payload['items'][0]['model']);
        self::assertSame(115, $payload['items'][0]['price']);
        self::assertSame('W001', $payload['items'][0]['stock_model']);
        self::assertSame(['48' => 47, '50' => 34], $payload['items'][0]['dr_quantities']);
        self::assertJson($json);

        unset($_SERVER['HTTP_X_LUNA_ENDPOINT_SECRET']);
        ob_start();
        $error = \LunaExportRuntime\EndpointRunner::handle('isr_prices');
        $errorJson = (string) ob_get_clean();

        self::assertSame(false, $error['success']);
        self::assertSame('secret_missing', $error['error']['code']);
        self::assertStringNotContainsString('expected-secret', $errorJson);
        self::assertStringNotContainsString($database, $errorJson);
        self::assertStringNotContainsString('Stack trace', $errorJson);

        ob_start();
        $missing = \LunaExportRuntime\EndpointRunner::handle('missing');
        ob_end_clean();
        self::assertSame('endpoint_not_found', $missing['error']['code']);
    }

    #[RunInSeparateProcess]
    public function testExportedIsrPricesRuntimeMatchesInternalEndpointPayloadShape(): void
    {
        $target = $this->tempDirectory();
        $database = $this->runtimeDatabase();
        $this->exporter()->export('isr_prices', $target, true);
        file_put_contents($target . '/.env', implode("\n", [
            'APP_ENV=production',
            'APP_DEBUG=false',
            'LUNA_ENDPOINT_ISR_PRICES_SECRET=expected-secret',
            'LUNA_CONN_PIMCORE_OBJECTS_DATABASE=' . $database,
            'LUNA_CONN_PIMCORE_SETTINGS_DATABASE=' . $database,
            'LUNA_CONN_SCHMUCKLAGER_DATABASE=' . $database,
            '',
        ]));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_LUNA_ENDPOINT_SECRET'] = 'expected-secret';
        ob_start();
        $exported = require $target . '/api/isr_prices.php';
        ob_end_clean();

        $expectedItems = [[
            'model' => 'W001',
            'price_group' => '6',
            'price' => 115,
            'stock_model' => 'W001',
            'dr_quantities' => ['48' => 47, '50' => 34],
        ]];
        $internal = json_decode((new EndpointJsonResponseFactory())->success($expectedItems)->body(), true);

        self::assertIsArray($internal);
        self::assertSame($internal['success'], $exported['success']);
        self::assertSame($internal['count'], $exported['count']);
        self::assertSame($internal['items'], $exported['items']);
    }

    private function exporter(?string $basePath = null): EndpointRuntimeExporter
    {
        $_ENV['APP_KEY'] = 'unit-test-app-key';
        $_SERVER['APP_KEY'] = 'unit-test-app-key';
        $pdo = $this->systemPdo();
        $systemDatabase = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
        $encryption = new EncryptionService(new Config());

        return new EndpointRuntimeExporter(
            new EndpointRepository($systemDatabase, $encryption, $pdo),
            new MappingRepository($systemDatabase, $pdo),
            new ConnectionProfileRepository($systemDatabase, $encryption, $pdo),
            new \Luna\Repository\WorkspaceRepository($systemDatabase, $pdo),
            $basePath ?? $this->tempDirectory(),
        );
    }

    private function systemPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, driver TEXT, host TEXT, port INTEGER, database_name TEXT, username TEXT, read_only INTEGER)');
        $pdo->exec('CREATE TABLE luna_connection_secrets (connection_profile_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT)');
        $pdo->exec('CREATE TABLE luna_jobs (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE luna_endpoints (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, endpoint_key TEXT, method TEXT, status TEXT, secret_mode TEXT, secret_hash TEXT, source_type TEXT, mapping_set_id INTEGER, job_id INTEGER, config_json TEXT, cache_enabled INTEGER, cache_ttl_seconds INTEGER)');
        $pdo->exec('CREATE TABLE luna_endpoint_secrets (endpoint_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_sets (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, mapping_mode TEXT, source_connection_id INTEGER, source_table TEXT, target_connection_id INTEGER, target_table TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_fields (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, target_column TEXT, transform_type TEXT, default_value TEXT, lookup_connection_id INTEGER, lookup_table TEXT, lookup_key_column TEXT, lookup_value_column TEXT, lookup_key_template TEXT, fallback_value TEXT, missing_behavior TEXT, sort_order INTEGER)');
        $pdo->exec('CREATE TABLE luna_mapping_source_filters (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, operator TEXT, filter_value TEXT, sort_order INTEGER)');

        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'asfinstocks', 'AsfInstocks')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, driver, host, port, database_name, username, read_only) VALUES (1, 1, 'PIMCORE-Objects', 'sqlite', 'objects.local', 3307, 'objects_db', 'objects_user', 1)");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, driver, host, port, database_name, username, read_only) VALUES (2, 1, 'PIMCORE-Settings', 'sqlite', 'settings.local', 3308, 'settings_db', 'settings_user', 1)");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, driver, host, port, database_name, username, read_only) VALUES (3, 1, 'Schmucklager', 'sqlite', 'stock.local', 3309, 'stock_db', 'stock_user', 1)");
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, method, status, secret_mode, secret_hash, source_type, mapping_set_id, job_id, config_json, cache_enabled, cache_ttl_seconds) VALUES (5, 1, 'ISR Prices', 'isr_prices', 'GET', 'active', 'required', 'hashed-secret', 'mapping', 33, NULL, '', 0, NULL)");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name, mapping_mode, source_connection_id, source_table, target_connection_id, target_table) VALUES (33, 1, 'ISR Prices Export', 'json_endpoint', 1, 'object_query_1', NULL, NULL)");
        $pdo->exec("INSERT INTO luna_mapping_source_filters (id, mapping_set_id, source_column, operator, filter_value, sort_order) VALUES (1, 33, 'priceGroup', 'numeric_greater_than', '0', 0)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, missing_behavior, sort_order) VALUES (1, 33, 'model', 'model', 'direct', NULL, NULL, NULL, NULL, NULL, 'nullable', 0)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, missing_behavior, sort_order) VALUES (2, 33, 'priceGroup', 'price_group', 'direct', NULL, NULL, NULL, NULL, NULL, 'nullable', 1)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, missing_behavior, sort_order) VALUES (3, 33, 'priceGroup', 'price', 'lookup_value', 2, 'zweipunkt_setting', 'name', 'value', 'price_{{priceGroup}}', 'nullable', 2)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, missing_behavior, sort_order) VALUES (4, 33, 'old_name,model', 'stock_model', 'first_non_empty', NULL, NULL, NULL, NULL, NULL, 'nullable', 3)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, missing_behavior, sort_order) VALUES (5, 33, 'stock_model', 'dr_quantities', 'key_value_map_by_prefix', 3, 'products', 'product_code', 'quantity', '{{stock_model}}D', 'nullable', 4)");

        $encryption = new EncryptionService(new Config());
        $secretStatement = $pdo->prepare('INSERT INTO luna_connection_secrets (connection_profile_id, secret_key, secret_value_encrypted) VALUES (:connection_profile_id, :secret_key, :secret_value_encrypted)');
        foreach ([1 => 'objects-password', 2 => 'settings-password', 3 => 'stock-password'] as $connectionId => $password) {
            $secretStatement->execute([
                'connection_profile_id' => $connectionId,
                'secret_key' => 'password',
                'secret_value_encrypted' => $encryption->encrypt($password),
            ]);
        }
        $endpointSecret = $pdo->prepare('INSERT INTO luna_endpoint_secrets (endpoint_id, secret_key, secret_value_encrypted) VALUES (:endpoint_id, :secret_key, :secret_value_encrypted)');
        $endpointSecret->execute([
            'endpoint_id' => 5,
            'secret_key' => 'secret',
            'secret_value_encrypted' => $encryption->encrypt('local-endpoint-secret'),
        ]);

        return $pdo;
    }

    private function runtimeDatabase(): string
    {
        $path = $this->tempDirectory() . '/runtime.sqlite';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE object_query_1 (model TEXT, old_name TEXT, priceGroup TEXT)');
        $pdo->exec('CREATE TABLE zweipunkt_setting (name TEXT, value TEXT)');
        $pdo->exec('CREATE TABLE products (product_code TEXT, quantity TEXT)');
        $pdo->exec("INSERT INTO object_query_1 (model, old_name, priceGroup) VALUES ('W001', '', '6')");
        $pdo->exec("INSERT INTO object_query_1 (model, old_name, priceGroup) VALUES ('TR001', '', NULL)");
        $pdo->exec("INSERT INTO zweipunkt_setting (name, value) VALUES ('price_6', '115')");
        $pdo->exec("INSERT INTO products (product_code, quantity) VALUES ('W001D48', '47')");
        $pdo->exec("INSERT INTO products (product_code, quantity) VALUES ('W001D50', '34')");

        return $path;
    }

    private function tempDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/luna_export_' . bin2hex(random_bytes(6));
        mkdir($directory, 0775, true);

        return str_replace('\\', '/', $directory);
    }

    private function readExport(string $target): string
    {
        $contents = '';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $contents .= (string) file_get_contents($file->getPathname());
            }
        }

        return $contents;
    }

    private function loadWebRoutes(?PDO $pdo = null, ?EndpointRuntimeExporter $exporter = null): \Luna\Routing\RouteCollection
    {
        $basePath = dirname(__DIR__, 2);
        $app = new \Luna\Core\Application(new \Luna\Core\Paths($basePath), new Config());
        if ($pdo !== null) {
            $systemDatabase = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
            $encryption = new EncryptionService(new Config());
            $app->services()->set('repository.endpoints', new EndpointRepository($systemDatabase, $encryption, $pdo));
        }
        if ($exporter !== null) {
            $app->services()->set(EndpointRuntimeExporter::class, $exporter);
        }
        $app->services()->set(EndpointExportArchiveService::class, new EndpointExportArchiveService());
        $routes = new \Luna\Routing\RouteCollection();
        $loader = require $basePath . '/routes/web.php';
        $loader($routes, $app);

        return $routes;
    }
}
