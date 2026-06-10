<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use Luna\Security\EncryptionService;
use PDO;

final class ExportProfileRepository
{
    /**
     * @var array<string, array{name: string, description: string, include_raw_meta: bool, include_item_raw_meta: bool}>
     */
    private const WOOCOMMERCE_DEFAULT_PROFILES = [
        'orders' => [
            'name' => 'WooCommerce Orders',
            'description' => 'Order Header aus der Luna-Staging-Schicht.',
            'include_raw_meta' => false,
            'include_item_raw_meta' => false,
        ],
        'orders_full' => [
            'name' => 'WooCommerce Orders Full',
            'description' => 'Order Header mit Adressen, Positionen und optionalen Raw-Meta-Daten.',
            'include_raw_meta' => false,
            'include_item_raw_meta' => false,
        ],
        'order_items' => [
            'name' => 'WooCommerce Order Items',
            'description' => 'Line Items aus der Luna-Staging-Schicht.',
            'include_raw_meta' => false,
            'include_item_raw_meta' => false,
        ],
        'order_addresses' => [
            'name' => 'WooCommerce Order Addresses',
            'description' => 'Billing- und Shipping-Adressen aus der Luna-Staging-Schicht.',
            'include_raw_meta' => false,
            'include_item_raw_meta' => false,
        ],
        'order_meta_raw' => [
            'name' => 'WooCommerce Order Meta Raw',
            'description' => 'Raw Order Meta aus der Luna-Staging-Schicht.',
            'include_raw_meta' => true,
            'include_item_raw_meta' => false,
        ],
        'order_itemmeta_raw' => [
            'name' => 'WooCommerce Order Item Meta Raw',
            'description' => 'Raw Item Meta aus der Luna-Staging-Schicht.',
            'include_raw_meta' => false,
            'include_item_raw_meta' => true,
        ],
    ];

    public function __construct(
        private readonly SystemDatabase $database,
        private readonly EncryptionService $encryption,
        private readonly ?PDO $pdo = null,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function wooCommerceProfilesForConnection(int $woocommerceConnectionId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT *
             FROM luna_export_profiles
             WHERE integration_type = 'woocommerce'
               AND connection_id = :connection_id
             ORDER BY profile_key",
        );
        $statement->execute(['connection_id' => $woocommerceConnectionId]);

        return $this->sanitizeProfiles($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function wooCommerceProfiles(): array
    {
        $statement = $this->pdo()->query(
            "SELECT *
             FROM luna_export_profiles
             WHERE integration_type = 'woocommerce'
             ORDER BY profile_key",
        );

        return $this->sanitizeProfiles($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_export_profiles WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->sanitizeProfile($row);
    }

    public function findEnabledWooCommerceProfile(string $profileKey): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT *
             FROM luna_export_profiles
             WHERE integration_type = 'woocommerce'
               AND profile_key = :profile_key
               AND is_enabled = 1
             ORDER BY id
             LIMIT 1",
        );
        $statement->execute(['profile_key' => $profileKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->sanitizeProfile($row);
    }

    /**
     * @param array<string, mixed> $woocommerceConnection
     * @return list<int>
     */
    public function createDefaultWooCommerceProfiles(array $woocommerceConnection): array
    {
        $ids = [];
        $workspaceId = empty($woocommerceConnection['workspace_id']) ? null : (int) $woocommerceConnection['workspace_id'];
        $connectionId = (int) $woocommerceConnection['id'];

        foreach (self::WOOCOMMERCE_DEFAULT_PROFILES as $profileKey => $defaults) {
            $existing = $this->findWooCommerceProfileForConnection($connectionId, $profileKey);
            if ($existing !== null) {
                $ids[] = (int) $existing['id'];
                continue;
            }

            $ids[] = $this->createProfile([
                'workspace_id' => $workspaceId,
                'connection_id' => $connectionId,
                'integration_type' => 'woocommerce',
                'profile_key' => $profileKey,
                'name' => $defaults['name'],
                'description' => $defaults['description'],
                'include_raw_meta' => $defaults['include_raw_meta'],
                'include_item_raw_meta' => $defaults['include_item_raw_meta'],
            ]);
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function createProfile(array $values): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_export_profiles
             (workspace_id, connection_id, integration_type, profile_key, name, description, is_enabled, export_format, auth_mode, include_raw_meta, include_item_raw_meta, batch_size, created_at, updated_at)
             VALUES (:workspace_id, :connection_id, :integration_type, :profile_key, :name, :description, :is_enabled, :export_format, :auth_mode, :include_raw_meta, :include_item_raw_meta, :batch_size, :created_at, :updated_at)',
        );
        $now = $this->now();
        $statement->execute([
            'workspace_id' => $values['workspace_id'] ?? null,
            'connection_id' => $values['connection_id'] ?? null,
            'integration_type' => (string) ($values['integration_type'] ?? 'woocommerce'),
            'profile_key' => (string) ($values['profile_key'] ?? ''),
            'name' => (string) ($values['name'] ?? ''),
            'description' => (string) ($values['description'] ?? ''),
            'is_enabled' => ! array_key_exists('is_enabled', $values) || ! empty($values['is_enabled']) ? 1 : 0,
            'export_format' => (string) ($values['export_format'] ?? 'json'),
            'auth_mode' => (string) ($values['auth_mode'] ?? 'token_hmac'),
            'include_raw_meta' => ! empty($values['include_raw_meta']) ? 1 : 0,
            'include_item_raw_meta' => ! empty($values['include_item_raw_meta']) ? 1 : 0,
            'batch_size' => max(1, (int) ($values['batch_size'] ?? 100)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function setToken(int $profileId, string $token): void
    {
        $statement = $this->pdo()->prepare(
            'UPDATE luna_export_profiles
             SET token_hash = :token_hash,
                 updated_at = :updated_at
             WHERE id = :id',
        );
        $statement->execute([
            'id' => $profileId,
            'token_hash' => $this->hashToken($token),
            'updated_at' => $this->now(),
        ]);
    }

    public function setSecret(int $profileId, string $secret): void
    {
        $statement = $this->pdo()->prepare(
            'UPDATE luna_export_profiles
             SET secret_encrypted = :secret_encrypted,
                 updated_at = :updated_at
             WHERE id = :id',
        );
        $statement->execute([
            'id' => $profileId,
            'secret_encrypted' => $this->encryption->encrypt($secret),
            'updated_at' => $this->now(),
        ]);
    }

    public function toggleEnabled(int $profileId): void
    {
        $profile = $this->find($profileId);
        if ($profile === null) {
            return;
        }

        $statement = $this->pdo()->prepare(
            'UPDATE luna_export_profiles
             SET is_enabled = :is_enabled,
                 updated_at = :updated_at
             WHERE id = :id',
        );
        $statement->execute([
            'id' => $profileId,
            'is_enabled' => empty($profile['is_enabled']) ? 1 : 0,
            'updated_at' => $this->now(),
        ]);
    }

    public function canDelete(int $profileId): DeleteCheckResult
    {
        $profile = $this->find($profileId);
        if ($profile === null) {
            return DeleteCheckResult::allowed();
        }

        return DeleteCheckResult::allowed();
    }

    public function delete(int $profileId): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_export_profiles WHERE id = :id');
        $statement->execute(['id' => $profileId]);
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function tokenMatches(array $profile, string $token): bool
    {
        $hash = (string) ($profile['token_hash'] ?? '');
        if ($hash === '' || trim($token) === '') {
            return false;
        }

        return hash_equals($hash, $this->hashToken($token));
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function secretForProfile(array $profile): ?string
    {
        $encrypted = (string) ($profile['secret_encrypted'] ?? '');
        if ($encrypted === '') {
            return null;
        }

        return $this->encryption->decrypt($encrypted);
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $params
     */
    public function createExportRun(array $profile, string $triggeredBy, array $params): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_export_runs
             (workspace_id, export_profile_id, integration_type, profile_key, status, triggered_by, started_at, requested_since, requested_until, watermark_before, created_at, updated_at)
             VALUES (:workspace_id, :export_profile_id, :integration_type, :profile_key, :status, :triggered_by, :started_at, :requested_since, :requested_until, :watermark_before, :created_at, :updated_at)',
        );
        $now = $this->now();
        $statement->execute([
            'workspace_id' => empty($profile['workspace_id']) ? null : (int) $profile['workspace_id'],
            'export_profile_id' => (int) $profile['id'],
            'integration_type' => (string) $profile['integration_type'],
            'profile_key' => (string) $profile['profile_key'],
            'status' => 'processing',
            'triggered_by' => $triggeredBy,
            'started_at' => $now,
            'requested_since' => $this->dateOrNull($params['since'] ?? null),
            'requested_until' => $this->dateOrNull($params['until'] ?? null),
            'watermark_before' => (string) ($profile['last_successful_watermark'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $summary
     */
    public function finishExportRun(
        int $runId,
        array $profile,
        string $status,
        int $recordsFound,
        int $recordsExported,
        ?string $watermarkAfter,
        array $summary = [],
        string $errorMessage = '',
    ): void {
        $statement = $this->pdo()->prepare(
            'UPDATE luna_export_runs
             SET status = :status,
                 finished_at = :finished_at,
                 watermark_after = :watermark_after,
                 records_found = :records_found,
                 records_exported = :records_exported,
                 error_count = :error_count,
                 summary_json = :summary_json,
                 error_message = :error_message,
                 updated_at = :updated_at
             WHERE id = :id',
        );
        $now = $this->now();
        $statement->execute([
            'id' => $runId,
            'status' => $status,
            'finished_at' => $now,
            'watermark_after' => $watermarkAfter,
            'records_found' => $recordsFound,
            'records_exported' => $recordsExported,
            'error_count' => $status === 'success' ? 0 : 1,
            'summary_json' => $this->json($summary),
            'error_message' => $errorMessage === '' ? null : $errorMessage,
            'updated_at' => $now,
        ]);

        if ($status === 'success') {
            $this->markProfileExportSuccess((int) $profile['id'], $watermarkAfter);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentRunsForWooCommerceConnection(int $woocommerceConnectionId, int $limit = 20): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT er.*
             FROM luna_export_runs er
             INNER JOIN luna_export_profiles ep ON ep.id = er.export_profile_id
             WHERE ep.integration_type = 'woocommerce'
               AND ep.connection_id = :connection_id
             ORDER BY er.id DESC
             LIMIT " . max(1, $limit),
        );
        $statement->execute(['connection_id' => $woocommerceConnectionId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function latestSuccessfulRunForWooCommerceConnection(int $woocommerceConnectionId): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT er.*
             FROM luna_export_runs er
             INNER JOIN luna_export_profiles ep ON ep.id = er.export_profile_id
             WHERE ep.integration_type = 'woocommerce'
               AND ep.connection_id = :connection_id
               AND er.status = 'success'
             ORDER BY er.finished_at DESC, er.id DESC
             LIMIT 1",
        );
        $statement->execute(['connection_id' => $woocommerceConnectionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function generateToken(): string
    {
        return 'luna_exp_' . bin2hex(random_bytes(24));
    }

    public function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function findWooCommerceProfileForConnection(int $woocommerceConnectionId, string $profileKey): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT *
             FROM luna_export_profiles
             WHERE integration_type = 'woocommerce'
               AND connection_id = :connection_id
               AND profile_key = :profile_key
             LIMIT 1",
        );
        $statement->execute(['connection_id' => $woocommerceConnectionId, 'profile_key' => $profileKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->sanitizeProfile($row);
    }

    private function markProfileExportSuccess(int $profileId, ?string $watermarkAfter): void
    {
        $statement = $this->pdo()->prepare(
            'UPDATE luna_export_profiles
             SET last_successful_export_at = :last_successful_export_at,
                 last_successful_watermark = :last_successful_watermark,
                 updated_at = :updated_at
             WHERE id = :id',
        );
        $now = $this->now();
        $statement->execute([
            'id' => $profileId,
            'last_successful_export_at' => $now,
            'last_successful_watermark' => $watermarkAfter,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $profiles
     * @return list<array<string, mixed>>
     */
    private function sanitizeProfiles(array $profiles): array
    {
        return array_map($this->sanitizeProfile(...), $profiles);
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function sanitizeProfile(array $profile): array
    {
        $profile['has_token'] = ! empty($profile['token_hash']);
        $profile['has_secret'] = ! empty($profile['secret_encrypted']);

        return $profile;
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : str_replace('T', ' ', rtrim($value, 'Z'));
    }

    private function json(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? '{}' : $json;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
