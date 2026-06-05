<?php
/** @var array<string, mixed> $values */
/** @var list<array<string, mixed>> $workspaces */

$values = $values ?? [];
$workspaces = $workspaces ?? [];
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Name</label>
        <input class="form-control" id="name" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="environment">Environment</label>
        <select class="form-select" id="environment" name="environment">
            <?php foreach (['local' => 'Local', 'staging' => 'Staging', 'production' => 'Production', 'custom' => 'Custom'] as $value => $label): ?>
                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($values['environment'] ?? 'production') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="workspace_id">Workspace</label>
        <select class="form-select" id="workspace_id" name="workspace_id">
            <option value="">Global</option>
            <?php foreach ($workspaces as $workspace): ?>
                <option value="<?= (int) $workspace['id'] ?>" <?= (int) ($values['workspace_id'] ?? 0) === (int) $workspace['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label" for="public_base_url">Public Base URL</label>
        <input class="form-control" id="public_base_url" name="public_base_url" value="<?= htmlspecialchars((string) ($values['public_base_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="https://toolbox.example.com/luna" required>
        <div class="form-text">Deployment Targets enthalten keine Secrets.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="endpoint_base_url">Endpoint Base URL optional</label>
        <input class="form-control" id="endpoint_base_url" name="endpoint_base_url" value="<?= htmlspecialchars((string) ($values['endpoint_base_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="https://toolbox.example.com/luna/api/endpoints">
        <div class="form-text">Überschreibt die aus der Public Base URL abgeleitete Endpoint-URL.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="webhook_base_url">Webhook Base URL optional</label>
        <input class="form-control" id="webhook_base_url" name="webhook_base_url" value="<?= htmlspecialchars((string) ($values['webhook_base_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="license_server_url">License Server URL optional</label>
        <input class="form-control" id="license_server_url" name="license_server_url" value="<?= htmlspecialchars((string) ($values['license_server_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-text">Nur vorbereitetes Metadatum. Luna kontaktiert in 2.2.0 keinen Lizenzserver.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="origin">Origin</label>
        <input class="form-control" id="origin" name="origin" value="<?= htmlspecialchars((string) ($values['origin'] ?? 'customer_created'), ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="support_status">Support Status</label>
        <input class="form-control" id="support_status" name="support_status" value="<?= htmlspecialchars((string) ($values['support_status'] ?? 'unverified'), ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="module_key">Module Key optional</label>
        <input class="form-control" id="module_key" name="module_key" value="<?= htmlspecialchars((string) ($values['module_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-md-6 d-flex align-items-end gap-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" <?= ! empty($values['is_default']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_default">Default Target</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= array_key_exists('is_active', $values) ? (! empty($values['is_active']) ? 'checked' : '') : 'checked' ?>>
            <label class="form-check-label" for="is_active">Aktiv</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="requires_entitlement" name="requires_entitlement" value="1" <?= ! empty($values['requires_entitlement']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="requires_entitlement">Entitlement-Metadatum</label>
        </div>
    </div>
</div>
