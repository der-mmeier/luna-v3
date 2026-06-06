<?php

declare(strict_types=1);

namespace Luna\TargetAction;

use Closure;
use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Core\Paths;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\TargetActionRepository;
use PDO;
use RuntimeException;

final class TargetActionExecutor
{
    private Closure $pdoFactory;

    public function __construct(
        private readonly Paths $paths,
        private readonly ConnectionProfileRepository $connections,
        ExternalPdoConnectionFactory $externalPdoFactory,
        private readonly TargetActionHttpClientInterface $httpClient,
        ?callable $pdoFactory = null,
    ) {
        $this->pdoFactory = Closure::fromCallable($pdoFactory ?? function (array $profile) use ($externalPdoFactory): PDO {
            return $externalPdoFactory->create(ExternalDatabaseConfig::fromProfile($profile, $this->connections->secretsFor((int) $profile['id'])), false);
        });
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $step
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function execute(array $action, array $step, int $processRunId, string $mode, array $context): array
    {
        if (empty($action['is_active'])) {
            throw new RuntimeException('Target Action ist inaktiv.');
        }

        $type = (string) ($action['action_type'] ?? '');
        if (! in_array($type, TargetActionRepository::EXECUTABLE_TYPES, true)) {
            throw new RuntimeException('Target Action Typ ist in v2.5.0 nicht ausführbar.');
        }

        $config = $this->mergedConfig($action, $step);
        $dryRun = $mode === 'dry_run';

        return match ($type) {
            'http_get' => $this->executeHttp('GET', $config, $dryRun, $context),
            'http_post' => $this->executeHttp('POST', $config, $dryRun, $context),
            'http_put' => $this->executeHttp('PUT', $config, $dryRun, $context),
            'file_export' => $this->executeFileExport($config, $dryRun, $context, $action, $step, $processRunId),
            'database_insert' => $this->executeDatabaseWrite('insert', $config, $dryRun, $context),
            'database_upsert' => $this->executeDatabaseWrite('upsert', $config, $dryRun, $context),
            default => throw new RuntimeException('Target Action Typ ist unbekannt.'),
        };
    }

    private function executeHttp(string $method, array $config, bool $dryRun, array $context): array
    {
        $url = $this->urlWithQuery((string) ($config['url'] ?? ''), is_array($config['query'] ?? null) ? $config['query'] : []);
        $headers = $this->stringMap(is_array($config['headers'] ?? null) ? $config['headers'] : []);
        $timeout = max(1, min((int) ($config['timeout_seconds'] ?? 10), 60));
        $body = in_array($method, ['POST', 'PUT'], true)
            ? $this->renderTemplate((string) ($config['body_template'] ?? '{{previous_result}}'), $context)
            : null;

        $requestSummary = [
            'method' => $method,
            'url' => $url,
            'headers' => $this->safeHeaders($headers),
            'body_bytes' => $body === null ? 0 : strlen($body),
            'timeout_seconds' => $timeout,
        ];

        $this->assertHttpUrl($url);

        if ($dryRun) {
            return [
                'status' => 'dry_run',
                'message' => 'Dry-run: would execute HTTP ' . $method . ' ' . $url,
                'request' => $requestSummary,
                'response' => ['executed' => false],
                'result' => ['planned' => true, 'type' => 'http', 'method' => $method, 'url' => $url],
            ];
        }

        $response = $this->httpClient->request($method, $url, $headers, $body, $timeout);
        $statusCode = (int) ($response['status_code'] ?? 0);
        $responseSummary = [
            'status_code' => $statusCode,
            'body_bytes' => strlen((string) ($response['body'] ?? '')),
            'body_preview' => $this->preview((string) ($response['body'] ?? '')),
            'headers' => $this->safeHeaders(is_array($response['headers'] ?? null) ? $response['headers'] : []),
        ];

        if ($statusCode >= 400 || $statusCode === 0) {
            throw new RuntimeException('HTTP Action fehlgeschlagen mit Status ' . $statusCode . '.');
        }

        return [
            'status' => 'success',
            'message' => 'HTTP ' . $method . ' Action erfolgreich ausgeführt.',
            'request' => $requestSummary,
            'response' => $responseSummary,
            'result' => ['status_code' => $statusCode, 'body' => $this->preview((string) ($response['body'] ?? ''), 2000)],
        ];
    }

    private function executeFileExport(array $config, bool $dryRun, array $context, array $action, array $step, int $processRunId): array
    {
        $targetPath = $this->safeStoragePath(
            (string) ($config['directory'] ?? 'runtime-exports'),
            $this->renderFilename((string) ($config['filename_template'] ?? 'process_{{process_id}}_run_{{run_id}}.json'), $action, $step, $processRunId),
        );
        $format = (string) ($config['format'] ?? 'json');
        if ($format !== 'json') {
            throw new RuntimeException('File Export unterstützt in v2.5.0 nur JSON.');
        }

        $payload = $this->jsonPayload($context['previous_result'] ?? $context);
        $summary = [
            'path' => $this->relativePath($targetPath),
            'format' => $format,
            'bytes' => strlen($payload),
        ];

        if ($dryRun) {
            return [
                'status' => 'dry_run',
                'message' => 'Dry-run: would write JSON file ' . $summary['path'],
                'request' => $summary,
                'response' => ['executed' => false],
                'result' => ['planned' => true, 'path' => $summary['path']],
            ];
        }

        $directory = dirname($targetPath);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Export-Verzeichnis konnte nicht erstellt werden.');
        }

        if (file_put_contents($targetPath, $payload) === false) {
            throw new RuntimeException('Export-Datei konnte nicht geschrieben werden.');
        }

        return [
            'status' => 'success',
            'message' => 'File Export erfolgreich geschrieben.',
            'request' => $summary,
            'response' => ['written' => true],
            'result' => ['path' => $summary['path'], 'bytes' => strlen($payload)],
        ];
    }

    private function executeDatabaseWrite(string $operation, array $config, bool $dryRun, array $context): array
    {
        $connectionId = (int) ($config['connection_id'] ?? 0);
        $table = (string) ($config['table'] ?? '');
        $columns = is_array($config['columns'] ?? null) ? $config['columns'] : [];
        $keyColumns = is_array($config['key_columns'] ?? null) ? array_values(array_map('strval', $config['key_columns'])) : [];

        $this->assertIdentifier($table);
        foreach (array_keys($columns) as $column) {
            $this->assertIdentifier((string) $column);
        }
        foreach ($keyColumns as $column) {
            $this->assertIdentifier($column);
        }

        if ($connectionId <= 0) {
            throw new RuntimeException('Target Connection ist erforderlich.');
        }
        if ($columns === []) {
            throw new RuntimeException('Mindestens eine Spaltenzuordnung ist erforderlich.');
        }
        if ($operation === 'upsert' && $keyColumns === []) {
            throw new RuntimeException('Database Upsert benötigt mindestens eine Key-Spalte.');
        }

        $rows = $this->rowsFromContext($context);
        $plannedRows = [];
        foreach ($rows as $row) {
            $plannedRows[] = $this->mappedRow($row, $columns);
        }

        $summary = [
            'operation' => $operation,
            'connection_id' => $connectionId,
            'table' => $table,
            'columns' => array_keys($columns),
            'key_columns' => $keyColumns,
            'row_count' => count($plannedRows),
        ];

        if ($dryRun) {
            $message = $operation === 'upsert'
                ? 'Dry-run: would upsert ' . count($plannedRows) . ' rows into ' . $table . ' using key ' . implode(',', $keyColumns)
                : 'Dry-run: would insert ' . count($plannedRows) . ' rows into ' . $table;

            return [
                'status' => 'dry_run',
                'message' => $message,
                'request' => $summary,
                'response' => ['executed' => false],
                'result' => ['planned' => true, 'row_count' => count($plannedRows)],
            ];
        }

        $profile = $this->connections->find($connectionId);
        if ($profile === null) {
            throw new RuntimeException('Target Connection wurde nicht gefunden.');
        }
        if (! empty($profile['read_only'])) {
            throw new RuntimeException('Target Connection ist read-only.');
        }

        $pdo = ($this->pdoFactory)($profile);
        $written = 0;
        foreach ($plannedRows as $row) {
            if ($operation === 'insert') {
                $this->insert($pdo, $table, $row);
                $written++;
                continue;
            }

            if ($this->exists($pdo, $table, $this->keyValues($row, $keyColumns))) {
                $written += $this->update($pdo, $table, $this->keyValues($row, $keyColumns), $row);
            } else {
                $this->insert($pdo, $table, $row);
                $written++;
            }
        }

        return [
            'status' => 'success',
            'message' => 'Database ' . ucfirst($operation) . ' erfolgreich ausgeführt.',
            'request' => $summary,
            'response' => ['written_rows' => $written],
            'result' => ['written_rows' => $written],
        ];
    }

    private function mergedConfig(array $action, array $step): array
    {
        $actionConfig = $this->decodeConfig((string) ($action['config_json'] ?? ''));
        $stepConfig = $this->decodeConfig((string) ($step['config_json'] ?? ''));

        return array_replace_recursive($actionConfig, $stepConfig);
    }

    private function decodeConfig(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Target Action Konfiguration ist kein gültiges JSON-Objekt.');
        }

        return $decoded;
    }

    private function assertHttpUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            throw new RuntimeException('HTTP URL ist ungültig.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('HTTP URL muss mit http:// oder https:// beginnen.');
        }
        if ((string) ($parts['host'] ?? '') === '') {
            throw new RuntimeException('HTTP URL muss einen Host enthalten.');
        }
        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            throw new RuntimeException('HTTP URLs mit Zugangsdaten sind nicht erlaubt.');
        }
    }

    private function urlWithQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    private function renderTemplate(string $template, array $context): string
    {
        $previous = $context['previous_result'] ?? null;
        if ($template === '{{previous_result}}') {
            return $this->jsonPayload($previous);
        }

        return str_replace('{{previous_result}}', $this->jsonPayload($previous), $template);
    }

    private function renderFilename(string $template, array $action, array $step, int $processRunId): string
    {
        $filename = strtr($template, [
            '{{process_id}}' => (string) ($step['process_id'] ?? ''),
            '{{run_id}}' => (string) $processRunId,
            '{{step_id}}' => (string) ($step['id'] ?? ''),
            '{{action_id}}' => (string) ($action['id'] ?? ''),
        ]);
        if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains(strtolower($filename), '.env')) {
            throw new RuntimeException('Dateiname ist für File Export nicht erlaubt.');
        }

        return $filename;
    }

    private function safeStoragePath(string $directory, string $filename): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        if ($directory === '' || str_contains($directory, '..') || preg_match('/^[A-Za-z]:\\//', $directory) === 1 || str_starts_with($directory, '/')) {
            throw new RuntimeException('Export-Verzeichnis ist nicht erlaubt.');
        }
        if (str_starts_with($directory, 'storage/')) {
            $relative = substr($directory, strlen('storage/'));
        } else {
            $relative = $directory;
        }

        $storageRoot = $this->paths->storagePath();
        $target = $this->paths->storagePath($relative . DIRECTORY_SEPARATOR . $filename);
        $normalizedRoot = rtrim(str_replace('\\', '/', $storageRoot), '/') . '/';
        $normalizedTarget = str_replace('\\', '/', $target);
        if (! str_starts_with($normalizedTarget, $normalizedRoot)) {
            throw new RuntimeException('Export-Ziel liegt außerhalb von storage.');
        }

        return $target;
    }

    private function relativePath(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', $this->paths->basePath()), '/') . '/';
        $normalized = str_replace('\\', '/', $path);

        return str_starts_with($normalized, $base) ? substr($normalized, strlen($base)) : $normalized;
    }

    private function rowsFromContext(array $context): array
    {
        $source = $context['previous_result'] ?? [];
        if (is_array($source) && isset($source['items']) && is_array($source['items'])) {
            return array_values(array_filter($source['items'], 'is_array'));
        }
        if (is_array($source) && isset($source['rows']) && is_array($source['rows'])) {
            return array_values(array_filter($source['rows'], 'is_array'));
        }
        if (is_array($source) && $source !== [] && array_is_list($source)) {
            return array_values(array_filter($source, 'is_array'));
        }
        if (is_array($source) && $source !== []) {
            return [$source];
        }

        return [];
    }

    private function mappedRow(array $source, array $columns): array
    {
        $row = [];
        foreach ($columns as $targetColumn => $sourceField) {
            $row[(string) $targetColumn] = $source[(string) $sourceField] ?? null;
        }

        return $row;
    }

    private function insert(PDO $pdo, string $table, array $row): void
    {
        $columns = array_keys($row);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns)),
            implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns)),
        );
        $statement = $pdo->prepare($sql);
        $statement->execute($this->payload($row));
    }

    private function update(PDO $pdo, string $table, array $key, array $row): int
    {
        $data = array_diff_key($row, $key);
        if ($data === []) {
            return 0;
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $this->quoteIdentifier($column) . ' = :set_' . $column;
        }
        $where = [];
        foreach (array_keys($key) as $column) {
            $where[] = $this->quoteIdentifier($column) . ' = :key_' . $column;
        }

        $statement = $pdo->prepare(sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $assignments),
            implode(' AND ', $where),
        ));
        $statement->execute($this->prefixedPayload('set_', $data) + $this->prefixedPayload('key_', $key));

        return 1;
    }

    private function exists(PDO $pdo, string $table, array $key): bool
    {
        $where = [];
        foreach (array_keys($key) as $column) {
            $where[] = $this->quoteIdentifier($column) . ' = :key_' . $column;
        }
        $statement = $pdo->prepare(sprintf('SELECT 1 FROM %s WHERE %s LIMIT 1', $this->quoteIdentifier($table), implode(' AND ', $where)));
        $statement->execute($this->prefixedPayload('key_', $key));

        return $statement->fetchColumn() !== false;
    }

    private function keyValues(array $row, array $keyColumns): array
    {
        $key = [];
        foreach ($keyColumns as $column) {
            $key[$column] = $row[$column] ?? null;
        }

        return $key;
    }

    private function quoteIdentifier(string $identifier): string
    {
        $this->assertIdentifier($identifier);

        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Ungültiger Tabellen- oder Spaltenname.');
        }
    }

    private function payload(array $row): array
    {
        $payload = [];
        foreach ($row as $column => $value) {
            $payload[$column] = is_array($value) || is_object($value) ? $this->jsonPayload($value) : $value;
        }

        return $payload;
    }

    private function prefixedPayload(string $prefix, array $row): array
    {
        $payload = [];
        foreach ($this->payload($row) as $column => $value) {
            $payload[$prefix . $column] = $value;
        }

        return $payload;
    }

    private function jsonPayload(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function stringMap(array $values): array
    {
        $map = [];
        foreach ($values as $key => $value) {
            $map[(string) $key] = (string) $value;
        }

        return $map;
    }

    private function safeHeaders(array $headers): array
    {
        $safe = [];
        foreach ($headers as $name => $value) {
            if (preg_match('/authorization|cookie|secret|token|password|api[_-]?key/i', (string) $name) === 1) {
                $safe[(string) $name] = '***';
                continue;
            }
            $safe[(string) $name] = (string) $value;
        }

        return $safe;
    }

    private function preview(string $value, int $limit = 500): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit) . '...';
    }
}
