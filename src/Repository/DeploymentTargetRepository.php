<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use Luna\Deployment\DeploymentTargetUrlBuilder;
use PDO;

final class DeploymentTargetRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly DeploymentTargetUrlBuilder $urlBuilder,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function all(): array
    {
        $statement = $this->pdo()->query(
            'SELECT dt.*, w.name AS workspace_name
             FROM luna_deployment_targets dt
             LEFT JOIN luna_workspaces w ON w.id = dt.workspace_id
             ORDER BY dt.environment, dt.name',
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT dt.*, w.name AS workspace_name
             FROM luna_deployment_targets dt
             LEFT JOIN luna_workspaces w ON w.id = dt.workspace_id
             WHERE dt.id = :id',
        );
        $statement->execute(['id' => $id]);
        $target = $statement->fetch();

        return $target === false ? null : $target;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeForWorkspace(?int $workspaceId): array
    {
        if ($workspaceId === null || $workspaceId <= 0) {
            $statement = $this->pdo()->query(
                'SELECT * FROM luna_deployment_targets
                 WHERE is_active = 1 AND workspace_id IS NULL
                 ORDER BY is_default DESC, environment, name',
            );

            return $statement->fetchAll();
        }

        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_deployment_targets
             WHERE is_active = 1 AND (workspace_id = :workspace_id OR workspace_id IS NULL)
             ORDER BY workspace_id DESC, is_default DESC, environment, name',
        );
        $statement->execute(['workspace_id' => $workspaceId]);

        return $statement->fetchAll();
    }

    public function findActiveByEnvironment(?int $workspaceId, string $environment): ?array
    {
        $environment = $this->normalizeEnvironment($environment);
        if ($workspaceId !== null && $workspaceId > 0) {
            $statement = $this->pdo()->prepare(
                'SELECT * FROM luna_deployment_targets
                 WHERE is_active = 1 AND workspace_id = :workspace_id AND environment = :environment
                 ORDER BY is_default DESC, id DESC
                 LIMIT 1',
            );
            $statement->execute(['workspace_id' => $workspaceId, 'environment' => $environment]);
            $target = $statement->fetch();
            if ($target !== false) {
                return $target;
            }
        }

        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_deployment_targets
             WHERE is_active = 1 AND workspace_id IS NULL AND environment = :environment
             ORDER BY is_default DESC, id DESC
             LIMIT 1',
        );
        $statement->execute(['environment' => $environment]);
        $target = $statement->fetch();

        return $target === false ? null : $target;
    }

    public function create(array $data): int
    {
        $payload = $this->payload($data);
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            if ((int) $payload['is_default'] === 1) {
                $this->clearDefault((int) ($payload['workspace_id'] ?? 0) ?: null, (string) $payload['environment']);
            }

            $statement = $pdo->prepare(
                'INSERT INTO luna_deployment_targets
                 (workspace_id, name, environment, public_base_url, endpoint_base_url, webhook_base_url, license_server_url,
                  is_default, is_active, origin, support_status, module_key, requires_entitlement, created_at, updated_at)
                 VALUES
                 (:workspace_id, :name, :environment, :public_base_url, :endpoint_base_url, :webhook_base_url, :license_server_url,
                  :is_default, :is_active, :origin, :support_status, :module_key, :requires_entitlement, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            );
            $statement->execute($payload);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $id;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function update(int $id, array $data): void
    {
        $payload = $this->payload($data);
        $payload['id'] = $id;
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            if ((int) $payload['is_default'] === 1) {
                $this->clearDefault((int) ($payload['workspace_id'] ?? 0) ?: null, (string) $payload['environment'], $id);
            }

            $statement = $pdo->prepare(
                'UPDATE luna_deployment_targets
                 SET workspace_id = :workspace_id,
                     name = :name,
                     environment = :environment,
                     public_base_url = :public_base_url,
                     endpoint_base_url = :endpoint_base_url,
                     webhook_base_url = :webhook_base_url,
                     license_server_url = :license_server_url,
                     is_default = :is_default,
                     is_active = :is_active,
                     origin = :origin,
                     support_status = :support_status,
                     module_key = :module_key,
                     requires_entitlement = :requires_entitlement,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
            );
            $statement->execute($payload);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_deployment_targets WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function setDefault(int $id): void
    {
        $target = $this->find($id);
        if ($target === null) {
            return;
        }

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $this->clearDefault(empty($target['workspace_id']) ? null : (int) $target['workspace_id'], (string) $target['environment'], $id);
            $statement = $pdo->prepare('UPDATE luna_deployment_targets SET is_default = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $statement->execute(['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function setActive(int $id, bool $active): void
    {
        $statement = $this->pdo()->prepare('UPDATE luna_deployment_targets SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $id, 'is_active' => $active ? 1 : 0]);
    }

    private function payload(array $data): array
    {
        $environment = $this->normalizeEnvironment((string) ($data['environment'] ?? 'custom'));
        $publicBaseUrl = $this->urlBuilder->normalizeBaseUrl((string) ($data['public_base_url'] ?? ''));
        $endpointBaseUrl = trim((string) ($data['endpoint_base_url'] ?? ''));
        $webhookBaseUrl = trim((string) ($data['webhook_base_url'] ?? ''));
        $licenseServerUrl = trim((string) ($data['license_server_url'] ?? ''));
        $normalizedEndpointBaseUrl = $endpointBaseUrl === '' ? null : $this->urlBuilder->normalizeBaseUrl($endpointBaseUrl);
        $normalizedWebhookBaseUrl = $webhookBaseUrl === '' ? null : $this->urlBuilder->normalizeBaseUrl($webhookBaseUrl);
        $normalizedLicenseServerUrl = $licenseServerUrl === '' ? null : $this->urlBuilder->normalizeBaseUrl($licenseServerUrl);

        foreach ([$publicBaseUrl, $normalizedEndpointBaseUrl, $normalizedWebhookBaseUrl, $normalizedLicenseServerUrl] as $url) {
            if ($url !== null) {
                $this->urlBuilder->assertProductionUrlAllowed($environment, $url);
            }
        }

        return [
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'name' => trim((string) ($data['name'] ?? '')),
            'environment' => $environment,
            'public_base_url' => $publicBaseUrl,
            'endpoint_base_url' => $normalizedEndpointBaseUrl,
            'webhook_base_url' => $normalizedWebhookBaseUrl,
            'license_server_url' => $normalizedLicenseServerUrl,
            'is_default' => ! empty($data['is_default']) ? 1 : 0,
            'is_active' => array_key_exists('is_active', $data) ? (! empty($data['is_active']) ? 1 : 0) : 1,
            'origin' => trim((string) ($data['origin'] ?? 'customer_created')) ?: 'customer_created',
            'support_status' => trim((string) ($data['support_status'] ?? 'unverified')) ?: 'unverified',
            'module_key' => trim((string) ($data['module_key'] ?? '')) ?: null,
            'requires_entitlement' => ! empty($data['requires_entitlement']) ? 1 : 0,
        ];
    }

    private function normalizeEnvironment(string $environment): string
    {
        $environment = strtolower(trim($environment));

        return in_array($environment, ['local', 'staging', 'production', 'custom'], true) ? $environment : 'custom';
    }

    private function clearDefault(?int $workspaceId, string $environment, ?int $exceptId = null): void
    {
        if ($workspaceId === null) {
            $sql = 'UPDATE luna_deployment_targets SET is_default = 0 WHERE workspace_id IS NULL AND environment = :environment';
            $params = ['environment' => $environment];
        } else {
            $sql = 'UPDATE luna_deployment_targets SET is_default = 0 WHERE workspace_id = :workspace_id AND environment = :environment';
            $params = ['workspace_id' => $workspaceId, 'environment' => $environment];
        }

        if ($exceptId !== null) {
            $sql .= ' AND id != :except_id';
            $params['except_id'] = $exceptId;
        }

        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
