<?php

declare(strict_types=1);

namespace Luna\Admin;

use Luna\Database\SystemDatabase;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\DatasetTransferRepository;
use Luna\Repository\DeleteCheckResult;
use Luna\Repository\EndpointRepository;
use Luna\Repository\ExportProfileRepository;
use Luna\Repository\JobRepository;
use Luna\Repository\ProcessRepository;
use Luna\Repository\ReportRepository;
use Luna\Repository\SchemaRegistryRepository;
use Luna\Repository\WooCommerceIntegrationRepository;
use Luna\Repository\WorkspaceRepository;
use PDO;

final class DeletionGuard
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly WorkspaceRepository $workspaces,
        private readonly ConnectionProfileRepository $connections,
        private readonly EndpointRepository $endpoints,
        private readonly JobRepository $jobs,
        private readonly ProcessRepository $processes,
        private readonly ReportRepository $reports,
        private readonly SchemaRegistryRepository $schemas,
        private readonly DatasetTransferRepository $transfers,
        private readonly WooCommerceIntegrationRepository $woocommerce,
        private readonly ExportProfileRepository $exportProfiles,
    ) {
    }

    public function canDelete(string $entityType, int $id): DeleteCheckResult
    {
        return match (strtolower(trim($entityType))) {
            'workspace' => $this->workspaces->canDelete($id),
            'connection', 'connection_profile' => $this->connections->canDelete($id),
            'schema' => $this->canDeleteSchema($id),
            'endpoint' => $this->endpoints->canDelete($id),
            'transfer', 'dataset_transfer' => $this->transfers->canDelete($id),
            'woocommerce', 'woocommerce_connection' => $this->woocommerce->canDeleteConnection($id),
            'woocommerce_webhook' => DeleteCheckResult::allowed(),
            'export_profile' => $this->exportProfiles->canDelete($id),
            'job', 'process', 'report' => DeleteCheckResult::allowed(),
            default => DeleteCheckResult::allowed(),
        };
    }

    public function delete(string $entityType, int $id): void
    {
        $check = $this->canDelete($entityType, $id);
        if (! $check->allowed) {
            throw new \RuntimeException($this->formatBlockedMessage($check));
        }

        match (strtolower(trim($entityType))) {
            'job' => $this->jobs->delete($id),
            'process' => $this->processes->delete($id),
            'report' => $this->reports->delete($id),
            'schema' => $this->schemas->delete($id),
            'endpoint' => $this->endpoints->delete($id),
            'transfer', 'dataset_transfer' => $this->transfers->delete($id),
            'woocommerce', 'woocommerce_connection' => $this->woocommerce->deleteConnection($id),
            'woocommerce_webhook' => $this->deleteWooCommerceWebhook($id),
            'export_profile' => $this->exportProfiles->delete($id),
            'connection', 'connection_profile' => $this->connections->delete($id),
            'workspace' => $this->workspaces->delete($id),
            default => throw new \InvalidArgumentException('Unbekannter Entitätstyp: ' . $entityType),
        };
    }

    public function formatBlockedMessage(DeleteCheckResult $check): string
    {
        if ($check->allowed) {
            return '';
        }

        $message = $check->message;
        if ($check->blockingNames !== []) {
            $message .= "\n- " . implode("\n- ", $check->blockingNames);
        }

        return $message;
    }

    private function canDeleteSchema(int $id): DeleteCheckResult
    {
        $schema = $this->schemas->find($id);
        if ($schema === null) {
            return DeleteCheckResult::allowed();
        }

        $blockingNames = [];
        $counts = [];

        if ($this->columnExists('luna_endpoints', 'schema_id')) {
            $statement = $this->pdo()->prepare('SELECT id, name, endpoint_key FROM luna_endpoints WHERE schema_id = :id ORDER BY name LIMIT 10');
            $statement->execute(['id' => $id]);
            $endpoints = $statement->fetchAll();
            if ($endpoints !== []) {
                $counts['Endpoints'] = count($endpoints);
                foreach ($endpoints as $endpoint) {
                    $label = trim((string) ($endpoint['name'] ?? '')) ?: (string) ($endpoint['endpoint_key'] ?? ('#' . $endpoint['id']));
                    $blockingNames[] = 'Endpoint "' . $label . '"';
                }
            }
        }

        if ($this->tableExists('luna_process_steps') && $this->columnExists('luna_process_steps', 'schema_id')) {
            $statement = $this->pdo()->prepare(
                'SELECT ps.id, ps.name, p.name AS process_name
                 FROM luna_process_steps ps
                 LEFT JOIN luna_processes p ON p.id = ps.process_id
                 WHERE ps.schema_id = :id
                 ORDER BY ps.id LIMIT 10',
            );
            $statement->execute(['id' => $id]);
            $steps = $statement->fetchAll();
            if ($steps !== []) {
                $counts['Process Steps'] = count($steps);
                foreach ($steps as $step) {
                    $stepName = trim((string) ($step['name'] ?? '')) ?: ('Step #' . $step['id']);
                    $processName = trim((string) ($step['process_name'] ?? ''));
                    $blockingNames[] = $processName === '' ? 'Process Step "' . $stepName . '"' : 'Process "' . $processName . '" / Step "' . $stepName . '"';
                }
            }
        }

        if ($blockingNames === []) {
            return DeleteCheckResult::allowed();
        }

        $label = trim((string) ($schema['name'] ?? '')) ?: (string) ($schema['schema_key'] ?? ('#' . $id));
        $version = trim((string) ($schema['version'] ?? ''));
        $message = 'Schema "' . $label . ($version === '' ? '' : ' v' . $version) . '" kann nicht gelöscht werden, weil abhängige Ressourcen existieren. Bitte entfernen Sie die Zuordnungen zuerst.';

        return DeleteCheckResult::blocked($message, $blockingNames, $counts);
    }

    private function deleteWooCommerceWebhook(int $id): void
    {
        $webhook = $this->woocommerce->findWebhookConfig($id);
        if ($webhook === null) {
            return;
        }

        $this->woocommerce->deleteWebhookConfig($id, (int) ($webhook['woocommerce_connection_id'] ?? 0));
    }

    private function tableExists(string $table): bool
    {
        try {
            $statement = $this->pdo()->prepare('SELECT 1 FROM ' . $table . ' LIMIT 1');
            $statement->execute();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $statement = $this->pdo()->prepare('SELECT ' . $column . ' FROM ' . $table . ' LIMIT 1');
            $statement->execute();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function pdo(): PDO
    {
        return $this->database->pdo();
    }
}
