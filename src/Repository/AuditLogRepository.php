<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;

final class AuditLogRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
    ) {
    }

    public function log(
        ?int $workspaceId,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $message = null,
        array $context = [],
    ): void {
        $json = json_encode($this->maskSecrets($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO luna_audit_log
             (workspace_id, actor_type, actor_id, action, entity_type, entity_id, message, context_json, created_at)
             VALUES (:workspace_id, :actor_type, :actor_id, :action, :entity_type, :entity_id, :message, :context_json, NOW())',
        );
        $statement->execute([
            'workspace_id' => $workspaceId,
            'actor_type' => 'admin-dev',
            'actor_id' => null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'message' => $message,
            'context_json' => $json === false ? null : $json,
        ]);
    }

    private function maskSecrets(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match('/secret|password|token|key/i', $key) === 1) {
                $context[$key] = '[masked]';
                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->maskSecrets($value);
            }
        }

        return $context;
    }
}
