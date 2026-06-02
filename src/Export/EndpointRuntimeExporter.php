<?php

declare(strict_types=1);

namespace Luna\Export;

use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\MappingRepository;
use Luna\Repository\WorkspaceRepository;
use RuntimeException;

final class EndpointRuntimeExporter
{
    public function __construct(
        private readonly EndpointRepository $endpoints,
        private readonly MappingRepository $mappings,
        private readonly ConnectionProfileRepository $connections,
        private readonly WorkspaceRepository $workspaces,
        private readonly string $basePath,
        private readonly string $lunaVersion = '1.7.0',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(string $endpointKey, string $targetDirectory, bool $force = false, bool $writeLocalEnv = false): array
    {
        $endpointKey = EndpointRepository::normalizeEndpointKey($endpointKey);
        $endpoint = $this->endpoints->findBySlug($endpointKey);

        if ($endpoint === null) {
            throw new RuntimeException('Endpoint not found.');
        }

        $mappingId = (int) ($endpoint['mapping_set_id'] ?? 0);
        $mapping = $mappingId > 0 ? $this->mappings->find($mappingId) : null;

        if ($mapping === null) {
            throw new RuntimeException('Mapping not found.');
        }

        if ((int) ($endpoint['workspace_id'] ?? 0) !== (int) ($mapping['workspace_id'] ?? 0)) {
            throw new RuntimeException('Endpoint mapping workspace mismatch.');
        }

        $fields = $this->mappings->fieldsForSet($mappingId);
        $sourceFilters = $this->mappings->sourceFiltersForSet($mappingId);
        $connections = $this->connectionProfilesForExport($mapping, $fields);
        $profile = $this->exportProfile($endpoint, $mapping, $fields, $sourceFilters, $connections);

        $targetDirectory = rtrim($targetDirectory, "\\/");
        $this->prepareTargetDirectory($targetDirectory, $force);

        $files = [];
        $files[] = $this->writeFile($targetDirectory, 'api/' . $endpointKey . '.php', $this->apiFile($endpointKey));
        $files[] = $this->writeFile($targetDirectory, 'runtime/bootstrap.php', $this->runtimeBootstrap());
        $files[] = $this->writeFile($targetDirectory, 'runtime/EnvLoader.php', $this->runtimeEnvLoader());
        $files[] = $this->writeFile($targetDirectory, 'runtime/JsonResponseFactory.php', $this->runtimeJsonResponseFactory());
        $files[] = $this->writeFile($targetDirectory, 'runtime/ConnectionFactory.php', $this->runtimeConnectionFactory());
        $files[] = $this->writeFile($targetDirectory, 'runtime/MappingExecutor.php', $this->runtimeMappingExecutor());
        $files[] = $this->writeFile($targetDirectory, 'runtime/EndpointRunner.php', $this->runtimeEndpointRunner());
        $files[] = $this->writeFile($targetDirectory, 'runtime/.htaccess', "Require all denied\n");
        $files[] = $this->writeFile($targetDirectory, 'config/endpoint.' . $endpointKey . '.php', $this->phpConfig($profile));
        $files[] = $this->writeFile($targetDirectory, 'config/.htaccess', "Require all denied\n");
        $files[] = $this->writeFile($targetDirectory, '.env.example', $this->envExample($profile));
        if ($writeLocalEnv) {
            $this->writeFile($targetDirectory, '.env', $this->localEnv($profile, $connections, $endpoint));
        }
        $files[] = $this->writeFile($targetDirectory, '.htaccess', $this->rootHtaccess());

        $manifest = [
            'endpoint' => $endpointKey,
            'endpoint_id' => (int) ($endpoint['id'] ?? 0),
            'exported_at' => date(DATE_ATOM),
            'luna_version' => $this->lunaVersion,
            'mapping_id' => $mappingId,
            'mapping_name' => (string) ($mapping['name'] ?? ''),
            'target_path' => $this->relativePath($targetDirectory),
            'archive_path' => $this->archiveManifestPath($targetDirectory, $this->archivePathForTargetDirectory($endpoint, $targetDirectory)),
            'local_env_written' => $writeLocalEnv,
            'files' => $files,
        ];
        $manifest['files'][] = 'manifest.json';
        $this->writeFile($targetDirectory, 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        $manifest['absolute_target_path'] = $targetDirectory;
        $manifest['absolute_archive_path'] = $this->archivePathForTargetDirectory($endpoint, $targetDirectory);

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    public function exportToWorkspaceStorage(string $endpointKey, bool $force = false, bool $writeLocalEnv = false): array
    {
        $endpointKey = EndpointRepository::normalizeEndpointKey($endpointKey);
        $endpoint = $this->endpoints->findBySlug($endpointKey);

        if ($endpoint === null) {
            throw new RuntimeException('Endpoint not found.');
        }

        return $this->export($endpointKey, $this->defaultTargetDirectory($endpoint), $force, $writeLocalEnv);
    }

    /**
     * @return array<string, mixed>
     */
    public function exportEndpointToWorkspaceStorage(int $endpointId, bool $force = true, bool $writeLocalEnv = false): array
    {
        $endpoint = $this->endpoints->find($endpointId);

        if ($endpoint === null) {
            throw new RuntimeException('Endpoint not found.');
        }

        return $this->export((string) $endpoint['endpoint_key'], $this->defaultTargetDirectory($endpoint), $force, $writeLocalEnv);
    }

    /**
     * @param array<string, mixed> $endpoint
     */
    public function defaultTargetDirectory(array $endpoint): string
    {
        $endpointKey = EndpointRepository::normalizeEndpointKey((string) ($endpoint['endpoint_key'] ?? ''));

        if ($endpointKey === '') {
            throw new RuntimeException('Endpoint key is invalid.');
        }

        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $this->workspaceSlug($endpoint) . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'endpoints' . DIRECTORY_SEPARATOR . $endpointKey;
    }

    /**
     * @param array<string, mixed> $endpoint
     */
    public function archivePathForEndpoint(array $endpoint): string
    {
        $endpointKey = EndpointRepository::normalizeEndpointKey((string) ($endpoint['endpoint_key'] ?? ''));

        if ($endpointKey === '') {
            throw new RuntimeException('Endpoint key is invalid.');
        }

        $workspaceSlug = $this->workspaceSlug($endpoint);

        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $workspaceSlug . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'endpoints' . DIRECTORY_SEPARATOR . $workspaceSlug . '-' . $endpointKey . '-runtime.zip';
    }

    /**
     * @param array<string, mixed> $endpoint
     */
    private function archivePathForTargetDirectory(array $endpoint, string $targetDirectory): string
    {
        $endpointKey = EndpointRepository::normalizeEndpointKey((string) ($endpoint['endpoint_key'] ?? ''));

        if ($endpointKey === '') {
            throw new RuntimeException('Endpoint key is invalid.');
        }

        return dirname($targetDirectory) . DIRECTORY_SEPARATOR . $this->workspaceSlug($endpoint) . '-' . $endpointKey . '-runtime.zip';
    }

    public function archivePathForEndpointId(int $endpointId): string
    {
        $endpoint = $this->endpoints->find($endpointId);

        if ($endpoint === null) {
            throw new RuntimeException('Endpoint not found.');
        }

        return $this->archivePathForEndpoint($endpoint);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function exportStatusForEndpoint(int $endpointId): ?array
    {
        $endpoint = $this->endpoints->find($endpointId);

        if ($endpoint === null) {
            return null;
        }

        $target = $this->defaultTargetDirectory($endpoint);
        $manifestPath = $target . DIRECTORY_SEPARATOR . 'manifest.json';
        $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;
        $archivePath = $this->archivePathForEndpoint($endpoint);

        return [
            'exists' => is_dir($target),
            'path' => $this->relativePath($target),
            'manifest_path' => $this->relativePath($manifestPath),
            'archive_exists' => is_file($archivePath),
            'archive_path' => $this->relativePath($archivePath),
            'archive_name' => basename($archivePath),
            'exported_at' => is_array($manifest) ? (string) ($manifest['exported_at'] ?? '') : '',
        ];
    }

    /**
     * @param array<string, mixed> $endpoint
     */
    private function workspaceSlug(array $endpoint): string
    {
        $workspaceSlug = trim((string) ($endpoint['workspace_slug'] ?? ''));

        if ($workspaceSlug === '' && ! empty($endpoint['workspace_id'])) {
            $workspace = $this->workspaces->find((int) $endpoint['workspace_id']);
            $workspaceSlug = trim((string) ($workspace['slug'] ?? ''));

            if ($workspaceSlug === '') {
                $workspaceSlug = WorkspaceRepository::normalizeSlug((string) ($workspace['name'] ?? ''));
            }
        }

        return $workspaceSlug === '' ? 'workspace' : $workspaceSlug;
    }

    /**
     * @param array<string, mixed> $mapping
     * @param list<array<string, mixed>> $fields
     *
     * @return array<int, array<string, mixed>>
     */
    private function connectionProfilesForExport(array $mapping, array $fields): array
    {
        $ids = [];
        $sourceConnectionId = (int) ($mapping['source_connection_id'] ?? 0);
        if ($sourceConnectionId > 0) {
            $ids[$sourceConnectionId] = $sourceConnectionId;
        }

        foreach ($fields as $field) {
            $lookupConnectionId = (int) ($field['lookup_connection_id'] ?? 0);
            if ($lookupConnectionId > 0) {
                $ids[$lookupConnectionId] = $lookupConnectionId;
            }
        }

        $profiles = [];
        foreach ($ids as $id) {
            $profile = $this->connections->find($id);
            if ($profile === null) {
                throw new RuntimeException('Connection profile not found.');
            }
            $profiles[$id] = $profile;
        }

        return $profiles;
    }

    /**
     * @param array<string, mixed> $endpoint
     * @param array<string, mixed> $mapping
     * @param list<array<string, mixed>> $fields
     * @param list<array<string, mixed>> $sourceFilters
     * @param array<int, array<string, mixed>> $connections
     *
     * @return array<string, mixed>
     */
    private function exportProfile(array $endpoint, array $mapping, array $fields, array $sourceFilters, array $connections): array
    {
        $endpointKey = EndpointRepository::normalizeEndpointKey((string) ($endpoint['endpoint_key'] ?? ''));
        $connectionProfiles = [];
        $usedEnvPrefixes = [];

        foreach ($connections as $id => $profile) {
            $envPrefix = $this->connectionEnvPrefix((string) ($profile['name'] ?? 'connection'), $usedEnvPrefixes);
            $usedEnvPrefixes[$envPrefix] = true;
            $connectionProfiles[$id] = [
                'id' => $id,
                'name' => (string) ($profile['name'] ?? ''),
                'driver' => (string) ($profile['driver'] ?? 'mysql'),
                'host_env' => $envPrefix . '_HOST',
                'database_env' => $envPrefix . '_DATABASE',
                'username_env' => $envPrefix . '_USERNAME',
                'password_env' => $envPrefix . '_PASSWORD',
                'port_env' => $envPrefix . '_PORT',
                'read_only' => ((int) ($profile['read_only'] ?? 1)) === 1,
            ];
        }

        return [
            'endpoint' => [
                'id' => (int) ($endpoint['id'] ?? 0),
                'name' => (string) ($endpoint['name'] ?? ''),
                'endpoint_key' => $endpointKey,
                'method' => strtoupper((string) ($endpoint['method'] ?? 'GET')),
                'status' => (string) ($endpoint['status'] ?? 'inactive'),
                'secret_mode' => (string) ($endpoint['secret_mode'] ?? 'none'),
                'secret_env' => 'LUNA_ENDPOINT_' . $this->envToken($endpointKey) . '_SECRET',
                'cache_enabled' => ((int) ($endpoint['cache_enabled'] ?? 0)) === 1,
                'cache_ttl_seconds' => empty($endpoint['cache_ttl_seconds']) ? null : (int) $endpoint['cache_ttl_seconds'],
            ],
            'runtime' => [
                'module' => $endpointKey,
                'version' => $this->lunaVersion,
            ],
            'mapping' => [
                'id' => (int) ($mapping['id'] ?? 0),
                'name' => (string) ($mapping['name'] ?? ''),
                'mapping_mode' => (string) ($mapping['mapping_mode'] ?? 'json_endpoint'),
                'source_connection_id' => (int) ($mapping['source_connection_id'] ?? 0),
                'source_table' => (string) ($mapping['source_table'] ?? ''),
            ],
            'source_filters' => array_values(array_map([$this, 'publicFilter'], $sourceFilters)),
            'fields' => array_values(array_map([$this, 'publicField'], $fields)),
            'connections' => $connectionProfiles,
        ];
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function publicField(array $field): array
    {
        return [
            'id' => (int) ($field['id'] ?? 0),
            'source_column' => $field['source_column'] ?? null,
            'target_column' => (string) ($field['target_column'] ?? ''),
            'transform_type' => (string) ($field['transform_type'] ?? 'direct'),
            'default_value' => $field['default_value'] ?? null,
            'lookup_connection_id' => empty($field['lookup_connection_id']) ? null : (int) $field['lookup_connection_id'],
            'lookup_table' => $field['lookup_table'] ?? null,
            'lookup_key_column' => $field['lookup_key_column'] ?? null,
            'lookup_value_column' => $field['lookup_value_column'] ?? null,
            'lookup_key_template' => $field['lookup_key_template'] ?? null,
            'fallback_value' => $field['fallback_value'] ?? null,
            'missing_behavior' => (string) ($field['missing_behavior'] ?? 'nullable'),
            'sort_order' => (int) ($field['sort_order'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return array<string, mixed>
     */
    private function publicFilter(array $filter): array
    {
        return [
            'source_column' => (string) ($filter['source_column'] ?? ''),
            'operator' => (string) ($filter['operator'] ?? 'none'),
            'filter_value' => (string) ($filter['filter_value'] ?? ''),
            'sort_order' => (int) ($filter['sort_order'] ?? 0),
        ];
    }

    private function prepareTargetDirectory(string $targetDirectory, bool $force): void
    {
        if (is_dir($targetDirectory)) {
            $files = array_diff(scandir($targetDirectory) ?: [], ['.', '..']);
            if ($files !== [] && ! $force) {
                throw new RuntimeException('Target directory is not empty. Use --force to overwrite.');
            }

            if ($files !== []) {
                $this->clearDirectory($targetDirectory);
            }
            return;
        }

        if (! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
            throw new RuntimeException('Target directory could not be created.');
        }
    }

    private function clearDirectory(string $directory): void
    {
        $real = realpath($directory);
        if ($real === false || strlen($real) < 10) {
            throw new RuntimeException('Refusing to clear unsafe target directory.');
        }

        foreach (array_diff(scandir($real) ?: [], ['.', '..']) as $entry) {
            $path = $real . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->clearDirectory($path);
                rmdir($path);
                continue;
            }
            unlink($path);
        }
    }

    private function writeFile(string $targetDirectory, string $relativePath, string $contents): string
    {
        $path = $targetDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Export directory could not be created.');
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Export file could not be written.');
        }

        return $relativePath;
    }

    private function relativePath(string $path): string
    {
        $normalizedBase = str_replace('\\', '/', rtrim($this->basePath, "\\/"));
        $normalizedPath = str_replace('\\', '/', $path);

        if (str_starts_with($normalizedPath, $normalizedBase . '/')) {
            return substr($normalizedPath, strlen($normalizedBase) + 1);
        }

        return $normalizedPath;
    }

    private function archiveManifestPath(string $targetDirectory, string $archivePath): string
    {
        $relativeArchivePath = $this->relativePath($archivePath);
        if (! preg_match('/^[A-Za-z]:\//', $relativeArchivePath) && ! str_starts_with($relativeArchivePath, '/')) {
            return $relativeArchivePath;
        }

        $targetParent = str_replace('\\', '/', dirname($targetDirectory));
        $archiveParent = str_replace('\\', '/', dirname($archivePath));
        if ($targetParent === $archiveParent) {
            return '../' . basename($archivePath);
        }

        return basename($archivePath);
    }

    private function phpConfig(array $profile): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($profile, true) . ";\n";
    }

    private function envExample(array $profile): string
    {
        $lines = [
            'APP_ENV=production',
            'APP_DEBUG=false',
            '',
        ];

        if (($profile['endpoint']['secret_mode'] ?? 'none') !== 'none') {
            $lines[] = (string) $profile['endpoint']['secret_env'] . '=';
            $lines[] = '';
        }

        foreach ($profile['connections'] as $connection) {
            $lines[] = '# ' . (string) $connection['name'];
            $lines[] = (string) $connection['host_env'] . '=';
            $lines[] = (string) $connection['database_env'] . '=';
            $lines[] = (string) $connection['username_env'] . '=';
            $lines[] = (string) $connection['password_env'] . '=';
            $lines[] = (string) $connection['port_env'] . '=3306';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<int, array<string, mixed>> $connections
     * @param array<string, mixed> $endpoint
     */
    private function localEnv(array $profile, array $connections, array $endpoint): string
    {
        $lines = [
            'APP_ENV=local',
            'APP_DEBUG=false',
            '',
        ];

        if (($profile['endpoint']['secret_mode'] ?? 'none') !== 'none') {
            $endpointSecrets = $this->endpoints->secretsFor((int) ($endpoint['id'] ?? 0));
            $lines[] = (string) $profile['endpoint']['secret_env'] . '=' . $this->envValue((string) ($endpointSecrets['secret'] ?? ''));
            $lines[] = '';
        }

        foreach ($profile['connections'] as $id => $connection) {
            $source = $connections[(int) $id] ?? [];
            $secrets = $this->connections->secretsFor((int) $id);

            $lines[] = '# ' . (string) $connection['name'];
            $lines[] = (string) $connection['host_env'] . '=' . $this->envValue((string) ($source['host'] ?? ''));
            $lines[] = (string) $connection['database_env'] . '=' . $this->envValue((string) ($source['database_name'] ?? ''));
            $lines[] = (string) $connection['username_env'] . '=' . $this->envValue((string) ($source['username'] ?? ''));
            $lines[] = (string) $connection['password_env'] . '=' . $this->envValue((string) ($secrets['password'] ?? ''));
            $lines[] = (string) $connection['port_env'] . '=' . $this->envValue((string) ($source['port'] ?? '3306'));
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function envValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_.:\\\\\/-]+$/', $value) === 1) {
            return $value;
        }

        return '"' . str_replace(["\\", "\"", "\r", "\n"], ["\\\\", "\\\"", '', '\n'], $value) . '"';
    }

    private function connectionEnvPrefix(string $name, array $used): string
    {
        $base = 'LUNA_CONN_' . $this->envToken($name);
        $candidate = $base;
        $suffix = 2;
        while (isset($used[$candidate])) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function envToken(string $value): string
    {
        $token = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $value));
        $token = trim($token, '_');

        return $token === '' ? 'ENDPOINT' : $token;
    }

    private function apiFile(string $endpointKey): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nrequire __DIR__ . '/../runtime/bootstrap.php';\n\nif ((string) (\$_GET['health'] ?? '') === '1') {\n    return \\LunaExportRuntime\\EndpointRunner::health('" . addslashes($endpointKey) . "');\n}\n\nreturn \\LunaExportRuntime\\EndpointRunner::handle('" . addslashes($endpointKey) . "');\n";
    }

    private function rootHtaccess(): string
    {
        return <<<'HTACCESS'
<Files ".env">
    Require all denied
</Files>
<Files ".env.example">
    Require all denied
</Files>
HTACCESS;
    }

    private function runtimeBootstrap(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

require __DIR__ . '/EnvLoader.php';
require __DIR__ . '/JsonResponseFactory.php';
require __DIR__ . '/ConnectionFactory.php';
require __DIR__ . '/MappingExecutor.php';
require __DIR__ . '/EndpointRunner.php';

\LunaExportRuntime\EnvLoader::load(dirname(__DIR__) . '/.env');

ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException('Runtime error.', 0, $severity, $file, $line);
});

set_exception_handler(static function (\Throwable $exception): void {
    \LunaExportRuntime\JsonResponseFactory::send(
        \LunaExportRuntime\JsonResponseFactory::error('runtime_error', 'Endpoint could not be executed.'),
        500,
    );
});
PHP;
    }

    private function runtimeEnvLoader(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace LunaExportRuntime;

final class EnvLoader
{
    public static function load(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if ($key === '') {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_scalar($value) ? (string) $value : $default;
    }
}
PHP;
    }

    private function runtimeJsonResponseFactory(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace LunaExportRuntime;

final class JsonResponseFactory
{
    public static function success(array $items): array
    {
        return [
            'success' => true,
            'generated_at' => date(DATE_ATOM),
            'count' => count($items),
            'items' => self::mask($items),
        ];
    }

    public static function error(string $code, string $message): array
    {
        return [
            'success' => false,
            'generated_at' => date(DATE_ATOM),
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    public static function send(array $payload, int $status = 200): array
    {
        http_response_code($status);
        if (! headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $payload;
    }

    private static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/(^|_)(secret|password|token|api_key|app_key|client_secret|dsn)(_|$)/i', $key) === 1) {
                $data[$key] = '***';
                continue;
            }
            if (is_array($value)) {
                $data[$key] = self::mask($value);
            }
        }

        return $data;
    }
}
PHP;
    }

    private function runtimeConnectionFactory(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace LunaExportRuntime;

use PDO;
use Throwable;

final class ConnectionFactory
{
    /** @var array<int, PDO> */
    private array $cache = [];

    public function __construct(private readonly array $config)
    {
    }

    public function pdo(int $connectionId): PDO
    {
        if (isset($this->cache[$connectionId])) {
            return $this->cache[$connectionId];
        }

        $connection = $this->config['connections'][$connectionId] ?? null;
        if (! is_array($connection)) {
            throw new \RuntimeException('connection_unavailable');
        }

        $driver = strtolower((string) ($connection['driver'] ?? 'mysql'));
        $database = EnvLoader::get((string) ($connection['database_env'] ?? ''));
        $username = EnvLoader::get((string) ($connection['username_env'] ?? ''));
        $password = EnvLoader::get((string) ($connection['password_env'] ?? ''));

        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . $database;
        } else {
            $host = EnvLoader::get((string) ($connection['host_env'] ?? ''));
            $port = EnvLoader::get((string) ($connection['port_env'] ?? ''), '3306');
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
        }

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        if ($driver !== 'sqlite' && ! empty($connection['read_only'])) {
            try {
                $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            } catch (Throwable) {
            }
        }

        return $this->cache[$connectionId] = $pdo;
    }
}
PHP;
    }

    private function runtimeEndpointRunner(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace LunaExportRuntime;

use Throwable;

final class EndpointRunner
{
    public static function health(string $endpointKey): array
    {
        $endpointKey = trim($endpointKey, '/');
        $configFile = dirname(__DIR__) . '/config/endpoint.' . $endpointKey . '.php';
        $requiredFiles = [
            dirname(__DIR__) . '/runtime/bootstrap.php',
            dirname(__DIR__) . '/runtime/EndpointRunner.php',
            dirname(__DIR__) . '/runtime/MappingExecutor.php',
            $configFile,
        ];

        foreach ($requiredFiles as $file) {
            if (! is_file($file)) {
                return JsonResponseFactory::send(JsonResponseFactory::error('healthcheck_failed', 'Runtime healthcheck failed.'), 500);
            }
        }

        try {
            $config = require $configFile;
            if (! is_array($config)) {
                return JsonResponseFactory::send(JsonResponseFactory::error('healthcheck_failed', 'Runtime healthcheck failed.'), 500);
            }

            return JsonResponseFactory::send([
                'success' => true,
                'generated_at' => date(DATE_ATOM),
                'module' => (string) ($config['runtime']['module'] ?? $endpointKey),
                'version' => (string) ($config['runtime']['version'] ?? 'unknown'),
                'status' => 'ok',
            ]);
        } catch (Throwable) {
            return JsonResponseFactory::send(JsonResponseFactory::error('healthcheck_failed', 'Runtime healthcheck failed.'), 500);
        }
    }

    public static function handle(string $endpointKey): array
    {
        $endpointKey = trim($endpointKey, '/');
        $configFile = dirname(__DIR__) . '/config/endpoint.' . $endpointKey . '.php';

        if (! is_file($configFile)) {
            return JsonResponseFactory::send(JsonResponseFactory::error('endpoint_not_found', 'Endpoint not found.'), 404);
        }

        $config = require $configFile;
        if (! is_array($config)) {
            return JsonResponseFactory::send(JsonResponseFactory::error('runtime_config_invalid', 'Endpoint could not be executed.'), 500);
        }

        $endpoint = $config['endpoint'] ?? [];
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if (($endpoint['status'] ?? 'inactive') !== 'active') {
            return JsonResponseFactory::send(JsonResponseFactory::error('endpoint_inactive', 'Endpoint is inactive.'), 404);
        }

        if (strtoupper((string) ($endpoint['method'] ?? 'GET')) !== $method) {
            return JsonResponseFactory::send(JsonResponseFactory::error('method_not_allowed', 'Method not allowed.'), 405);
        }

        $secretError = self::secretError($endpoint);
        if ($secretError !== null) {
            return JsonResponseFactory::send(JsonResponseFactory::error($secretError[0], $secretError[1]), $secretError[2]);
        }

        try {
            $items = (new MappingExecutor($config, new ConnectionFactory($config)))->execute();

            return JsonResponseFactory::send(JsonResponseFactory::success($items));
        } catch (Throwable) {
            return JsonResponseFactory::send(JsonResponseFactory::error('runtime_error', 'Endpoint could not be executed.'), 500);
        }
    }

    /**
     * @param array<string, mixed> $endpoint
     *
     * @return array{0: string, 1: string, 2: int}|null
     */
    private static function secretError(array $endpoint): ?array
    {
        $mode = (string) ($endpoint['secret_mode'] ?? 'none');
        if ($mode === 'none') {
            return null;
        }

        $provided = (string) ($_SERVER['HTTP_X_LUNA_ENDPOINT_SECRET'] ?? ($_GET['secret'] ?? ''));
        if ($mode === 'required' && $provided === '') {
            return ['secret_missing', 'Endpoint secret is required.', 401];
        }

        if ($provided === '') {
            return null;
        }

        $expected = EnvLoader::get((string) ($endpoint['secret_env'] ?? ''));
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return ['secret_invalid', 'Endpoint secret is invalid.', 403];
        }

        return null;
    }
}
PHP;
    }

    private function runtimeMappingExecutor(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace LunaExportRuntime;

use PDO;

final class MappingExecutor
{
    /** @var array<string, mixed> */
    private array $exactCache = [];

    /** @var array<string, mixed> */
    private array $prefixCache = [];

    public function __construct(
        private readonly array $config,
        private readonly ConnectionFactory $connections,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(): array
    {
        $mapping = $this->config['mapping'];
        $fields = $this->config['fields'];
        $sourceRows = $this->sourceRows($mapping);
        $this->warmUpPrefixMaps($sourceRows, $fields);

        $items = [];
        foreach ($sourceRows as $row) {
            $item = [];
            foreach ($fields as $field) {
                $target = (string) ($field['target_column'] ?? '');
                if ($target === '') {
                    continue;
                }
                $item[$target] = $this->resolveField($row, $item, $field);
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $mapping
     *
     * @return list<array<string, mixed>>
     */
    private function sourceRows(array $mapping): array
    {
        $connectionId = (int) ($mapping['source_connection_id'] ?? 0);
        $table = (string) ($mapping['source_table'] ?? '');
        $this->assertIdentifier($table);
        $pdo = $this->connections->pdo($connectionId);
        $driver = strtolower((string) ($this->config['connections'][$connectionId]['driver'] ?? 'mysql'));
        $filters = is_array($this->config['source_filters'] ?? null) ? $this->config['source_filters'] : [];

        if ($filters === []) {
            return $pdo->query(sprintf('SELECT * FROM `%s`', $table))->fetchAll();
        }

        if ($driver !== 'mysql') {
            return $this->filterRowsInPhp($pdo->query(sprintf('SELECT * FROM `%s`', $table))->fetchAll(), $filters);
        }

        $params = [];
        $where = [];
        foreach ($filters as $index => $filter) {
            if (! is_array($filter)) {
                continue;
            }
            $where[] = '(' . $this->filterSql($filter, $index, $params) . ')';
        }

        $statement = $pdo->prepare(sprintf('SELECT * FROM `%s` WHERE %s', $table, implode(' AND ', $where)));
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @param list<array<string, mixed>> $sourceRows
     * @param list<array<string, mixed>> $fields
     */
    private function warmUpPrefixMaps(array $sourceRows, array $fields): void
    {
        $groups = [];

        foreach ($sourceRows as $row) {
            $targetRow = [];

            foreach ($fields as $field) {
                $target = (string) ($field['target_column'] ?? '');
                $transformType = (string) ($field['transform_type'] ?? 'direct');

                if ($transformType === 'key_value_map_by_prefix') {
                    $prefix = $this->renderTemplate((string) ($field['lookup_key_template'] ?? ''), $row, $targetRow);
                    if ($prefix === null || $prefix === '') {
                        continue;
                    }

                    $groupKey = $this->lookupConfigKey($field) . '|' . $target . '|' . (string) ($field['lookup_key_template'] ?? '');
                    $groups[$groupKey]['field'] = $field;
                    $groups[$groupKey]['prefixes'][$prefix] = $prefix;
                    continue;
                }

                if ($target === '' || ! in_array($transformType, ['source_column', 'direct', 'static_value', 'static', 'first_non_empty', 'normalize_dr_model'], true)) {
                    continue;
                }

                $targetRow[$target] = $this->resolveField($row, $targetRow, $field);
            }
        }

        foreach ($groups as $group) {
            $field = $group['field'];
            foreach (array_chunk(array_values($group['prefixes']), 100) as $chunk) {
                $this->warmUpPrefixChunk($field, $chunk);
            }
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param list<string> $prefixes
     */
    private function warmUpPrefixChunk(array $field, array $prefixes): void
    {
        $connectionId = (int) ($field['lookup_connection_id'] ?? 0);
        $table = (string) ($field['lookup_table'] ?? '');
        $keyColumn = (string) ($field['lookup_key_column'] ?? '');
        $valueColumn = (string) ($field['lookup_value_column'] ?? '');
        $this->assertIdentifier($table);
        $this->assertIdentifier($keyColumn);
        $this->assertIdentifier($valueColumn);

        $where = [];
        $params = [];
        foreach ($prefixes as $index => $prefix) {
            $placeholder = 'prefix_' . $index;
            $where[] = sprintf('`%s` LIKE :%s', $keyColumn, $placeholder);
            $params[$placeholder] = $prefix . '%';
            $this->prefixCache[$this->prefixCacheKey($field, $prefix)] = (object) [];
        }

        $statement = $this->connections->pdo($connectionId)->prepare(sprintf(
            'SELECT `%s` AS lookup_key, `%s` AS lookup_value FROM `%s` WHERE %s ORDER BY `%s`',
            $keyColumn,
            $valueColumn,
            $table,
            implode(' OR ', $where),
            $keyColumn,
        ));
        $statement->execute($params);

        $maps = [];
        foreach ($prefixes as $prefix) {
            $maps[$prefix] = [];
        }

        foreach ($statement->fetchAll() as $row) {
            $rawKey = (string) ($row['lookup_key'] ?? '');
            foreach ($prefixes as $prefix) {
                if (! str_starts_with($rawKey, $prefix)) {
                    continue;
                }
                $outputKey = substr($rawKey, strlen($prefix));
                if ($outputKey === '') {
                    continue;
                }
                if (array_key_exists($outputKey, $maps[$prefix])) {
                    throw new \RuntimeException('duplicate_result_key');
                }
                $maps[$prefix][$outputKey] = $this->normalizeValue($row['lookup_value'] ?? null);
            }
        }

        foreach ($maps as $prefix => $map) {
            $this->prefixCache[$this->prefixCacheKey($field, $prefix)] = $map === [] ? (object) [] : $map;
        }
    }

    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $targetRow
     * @param array<string, mixed> $field
     */
    private function resolveField(array $sourceRow, array $targetRow, array $field): mixed
    {
        return match ((string) ($field['transform_type'] ?? 'direct')) {
            'source_column', 'direct' => $sourceRow[(string) ($field['source_column'] ?? '')] ?? null,
            'static_value', 'static' => $field['default_value'] ?? null,
            'first_non_empty' => $this->firstNonEmpty($sourceRow, $targetRow, $field),
            'normalize_dr_model' => $this->normalizeDrModel($sourceRow[(string) ($field['source_column'] ?? '')] ?? null),
            'lookup_value' => $this->lookupValue($sourceRow, $targetRow, $field),
            'key_value_map_by_prefix' => $this->prefixMap($sourceRow, $targetRow, $field),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $targetRow
     * @param array<string, mixed> $field
     */
    private function firstNonEmpty(array $sourceRow, array $targetRow, array $field): mixed
    {
        $columns = array_values(array_filter(array_map(
            static fn (string $column): string => trim($column),
            explode(',', (string) ($field['source_column'] ?? '')),
        ), static fn (string $column): bool => $column !== ''));

        foreach ($columns as $column) {
            $value = array_key_exists($column, $targetRow) ? $targetRow[$column] : ($sourceRow[$column] ?? null);

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function normalizeDrModel(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        return preg_replace('/^DR0([0-9]{2})(.*)$/', 'DR$1$2', $text) ?? $text;
    }

    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $targetRow
     * @param array<string, mixed> $field
     */
    private function lookupValue(array $sourceRow, array $targetRow, array $field): mixed
    {
        $key = $this->renderTemplate((string) ($field['lookup_key_template'] ?? ''), $sourceRow, $targetRow);
        if ($key === null || $key === '') {
            return null;
        }

        $cacheKey = $this->lookupConfigKey($field) . '|' . $key;
        if (array_key_exists($cacheKey, $this->exactCache)) {
            return $this->exactCache[$cacheKey];
        }

        $connectionId = (int) ($field['lookup_connection_id'] ?? 0);
        $table = (string) ($field['lookup_table'] ?? '');
        $keyColumn = (string) ($field['lookup_key_column'] ?? '');
        $valueColumn = (string) ($field['lookup_value_column'] ?? '');
        $this->assertIdentifier($table);
        $this->assertIdentifier($keyColumn);
        $this->assertIdentifier($valueColumn);

        $statement = $this->connections->pdo($connectionId)->prepare(sprintf(
            'SELECT `%s` AS lookup_value FROM `%s` WHERE `%s` = :lookup_key LIMIT 1',
            $valueColumn,
            $table,
            $keyColumn,
        ));
        $statement->execute(['lookup_key' => $key]);
        $row = $statement->fetch();

        return $this->exactCache[$cacheKey] = $row === false ? null : $this->normalizeValue($row['lookup_value'] ?? null);
    }

    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $targetRow
     * @param array<string, mixed> $field
     */
    private function prefixMap(array $sourceRow, array $targetRow, array $field): mixed
    {
        $prefix = $this->renderTemplate((string) ($field['lookup_key_template'] ?? ''), $sourceRow, $targetRow);
        if ($prefix === null || $prefix === '') {
            return (object) [];
        }

        $cacheKey = $this->prefixCacheKey($field, $prefix);
        if (array_key_exists($cacheKey, $this->prefixCache)) {
            return $this->prefixCache[$cacheKey];
        }

        $this->warmUpPrefixChunk($field, [$prefix]);

        return $this->prefixCache[$cacheKey] ?? (object) [];
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $params
     */
    private function filterSql(array $filter, int $index, array &$params): string
    {
        $column = (string) ($filter['source_column'] ?? '');
        $operator = (string) ($filter['operator'] ?? 'none');
        $value = (string) ($filter['filter_value'] ?? '');
        $this->assertIdentifier($column);
        $placeholder = 'filter_' . $index;

        if ($operator === 'is_empty') {
            return sprintf('`%s` IS NULL OR `%s` = \'\'', $column, $column);
        }
        if ($operator === 'is_not_empty') {
            return sprintf('`%s` IS NOT NULL AND `%s` <> \'\'', $column, $column);
        }
        if (str_starts_with($operator, 'numeric_')) {
            $params[$placeholder] = $value;
            $comparison = [
                'numeric_equals' => '=',
                'numeric_not_equals' => '<>',
                'numeric_greater_than' => '>',
                'numeric_greater_or_equal' => '>=',
                'numeric_less_than' => '<',
                'numeric_less_or_equal' => '<=',
            ][$operator] ?? '>';

            return sprintf("TRIM(CAST(`%s` AS CHAR)) REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$' AND CAST(`%s` AS DECIMAL(20,6)) %s :%s", $column, $column, $comparison, $placeholder);
        }
        if (in_array($operator, ['contains', 'not_contains', 'starts_with', 'not_starts_with', 'ends_with', 'not_ends_with', 'like', 'not_like'], true)) {
            $params[$placeholder] = $this->likeValue($operator, $value);
            $not = str_starts_with($operator, 'not_') ? 'NOT ' : '';
            $sql = sprintf('`%s` %sLIKE :%s ESCAPE \'\\\\\'', $column, $not, $placeholder);

            return str_starts_with($operator, 'not_') ? sprintf('(%s OR `%s` IS NULL)', $sql, $column) : $sql;
        }
        if (in_array($operator, ['in', 'not_in'], true)) {
            $parts = $this->listValues($value);
            if ($parts === []) {
                return $operator === 'in' ? '1 = 0' : '1 = 1';
            }
            $placeholders = [];
            foreach ($parts as $partIndex => $part) {
                $partPlaceholder = $placeholder . '_' . $partIndex;
                $params[$partPlaceholder] = $part;
                $placeholders[] = ':' . $partPlaceholder;
            }
            $sql = sprintf('`%s` %sIN (%s)', $column, $operator === 'not_in' ? 'NOT ' : '', implode(', ', $placeholders));

            return $operator === 'not_in' ? sprintf('(%s OR `%s` IS NULL)', $sql, $column) : $sql;
        }

        $params[$placeholder] = $value;

        return $operator === 'not_equals'
            ? sprintf('(`%s` <> :%s OR `%s` IS NULL)', $column, $placeholder, $column)
            : sprintf('`%s` = :%s', $column, $placeholder);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $filters
     *
     * @return list<array<string, mixed>>
     */
    private function filterRowsInPhp(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters): bool {
            foreach ($filters as $filter) {
                if (! $this->rowMatches($row[(string) ($filter['source_column'] ?? '')] ?? null, $filter)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param array<string, mixed> $filter
     */
    private function rowMatches(mixed $rawValue, array $filter): bool
    {
        $operator = (string) ($filter['operator'] ?? 'none');
        $rawText = $rawValue === null ? null : (string) $rawValue;
        $value = $rawText === null ? null : trim($rawText);
        $filterValue = (string) ($filter['filter_value'] ?? '');

        if ($operator === 'is_empty') {
            return $rawValue === null || $rawText === '';
        }
        if ($operator === 'is_not_empty') {
            return $rawValue !== null && $rawText !== '';
        }
        if ($value === null || $value === '') {
            return in_array($operator, ['not_equals', 'not_contains', 'not_starts_with', 'not_ends_with', 'not_like', 'not_in'], true);
        }
        if (str_starts_with($operator, 'numeric_')) {
            if (! is_numeric($value)) {
                return false;
            }
            $left = (float) $value;
            $right = (float) $filterValue;

            return match ($operator) {
                'numeric_equals' => $left === $right,
                'numeric_not_equals' => $left !== $right,
                'numeric_greater_than' => $left > $right,
                'numeric_greater_or_equal' => $left >= $right,
                'numeric_less_than' => $left < $right,
                'numeric_less_or_equal' => $left <= $right,
                default => false,
            };
        }

        return match ($operator) {
            'equals' => $value === $filterValue,
            'not_equals' => $value !== $filterValue,
            'contains' => str_contains($value, $filterValue),
            'not_contains' => ! str_contains($value, $filterValue),
            'starts_with' => str_starts_with($value, $filterValue),
            'not_starts_with' => ! str_starts_with($value, $filterValue),
            'ends_with' => str_ends_with($value, $filterValue),
            'not_ends_with' => ! str_ends_with($value, $filterValue),
            'like' => $this->likeMatches($value, $filterValue),
            'not_like' => ! $this->likeMatches($value, $filterValue),
            'in' => in_array($value, $this->listValues($filterValue), true),
            'not_in' => ! in_array($value, $this->listValues($filterValue), true),
            default => true,
        };
    }

    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $targetRow
     */
    private function renderTemplate(string $template, array $sourceRow, array $targetRow): ?string
    {
        return preg_replace_callback('/{{\s*([A-Za-z0-9_]+)\s*}}/', static function (array $matches) use ($sourceRow, $targetRow): string {
            $name = (string) $matches[1];
            if (array_key_exists($name, $targetRow)) {
                return (string) $targetRow[$name];
            }
            if (array_key_exists($name, $sourceRow)) {
                return (string) $sourceRow[$name];
            }

            return '';
        }, $template);
    }

    private function lookupConfigKey(array $field): string
    {
        return implode('|', [
            (string) ($field['lookup_connection_id'] ?? ''),
            (string) ($field['lookup_table'] ?? ''),
            (string) ($field['lookup_key_column'] ?? ''),
            (string) ($field['lookup_value_column'] ?? ''),
        ]);
    }

    private function prefixCacheKey(array $field, string $prefix): string
    {
        return $this->lookupConfigKey($field) . '|' . $prefix;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (! is_string($value) || ! is_numeric($value)) {
            return $value;
        }

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    private function likeValue(string $operator, string $value): string
    {
        $escaped = in_array($operator, ['like', 'not_like'], true)
            ? str_replace('\\', '\\\\', $value)
            : str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return match ($operator) {
            'contains', 'not_contains' => '%' . $escaped . '%',
            'starts_with', 'not_starts_with' => $escaped . '%',
            'ends_with', 'not_ends_with' => '%' . $escaped,
            default => $escaped,
        };
    }

    private function likeMatches(string $value, string $pattern): bool
    {
        return preg_match('/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote($pattern, '/')) . '$/u', $value) === 1;
    }

    /**
     * @return list<string>
     */
    private function listValues(string $value): array
    {
        return array_values(array_filter(array_map(static fn (string $part): string => trim($part), explode(',', $value)), static fn (string $part): bool => $part !== ''));
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new \RuntimeException('invalid_identifier');
        }
    }
}
PHP;
    }
}
