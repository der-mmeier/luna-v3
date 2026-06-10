<?php

declare(strict_types=1);

namespace Luna\TransferDb;

use Throwable;

final class TransferDbStatusService
{
    public function __construct(
        private readonly TransferDbConnectionResolver $resolver,
        private readonly TransferDbSchemaManager $schemaManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function check(string|int $workspaceIdentifier): array
    {
        try {
            $resolved = $this->resolver->resolve($workspaceIdentifier);
            $status = $this->schemaManager->status($resolved['pdo']);

            return $status + [
                'workspace' => $resolved['workspace'],
                'connection' => $resolved['connection'],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->failedStatus($exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function migrate(string|int $workspaceIdentifier): array
    {
        $resolved = $this->resolver->resolve($workspaceIdentifier);
        $status = $this->schemaManager->migrate($resolved['pdo']);

        return $status + [
            'workspace' => $resolved['workspace'],
            'connection' => $resolved['connection'],
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function testConnection(int $connectionId): array
    {
        try {
            $resolved = $this->resolver->resolveConnection($connectionId);
            $resolved['pdo']->query('SELECT 1');

            return [
                'configured' => true,
                'reachable' => true,
                'schema_current' => false,
                'missing_tables' => $this->schemaManager->tableNames(),
                'existing_tables' => [],
                'migration_version' => null,
                'workspace' => null,
                'connection' => $resolved['connection'],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->failedStatus('Verbindung fehlgeschlagen: ' . $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function statusConnection(int $connectionId): array
    {
        try {
            $resolved = $this->resolver->resolveConnection($connectionId);
            $status = $this->schemaManager->status($resolved['pdo']);

            return $status + [
                'workspace' => null,
                'connection' => $resolved['connection'],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->failedStatus($exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function migrateConnection(int $connectionId): array
    {
        try {
            $resolved = $this->resolver->resolveConnection($connectionId);
            $status = $this->schemaManager->migrate($resolved['pdo']);

            return $status + [
                'workspace' => null,
                'connection' => $resolved['connection'],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->failedStatus('Migration fehlgeschlagen: ' . $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function failedStatus(string $message): array
    {
        return [
            'configured' => false,
            'reachable' => false,
            'schema_current' => false,
            'missing_tables' => $this->schemaManager->tableNames(),
            'existing_tables' => [],
            'migration_version' => null,
            'workspace' => null,
            'connection' => null,
            'error' => $message,
        ];
    }
}