<?php
$field = static fn (string $key, string $default = ''): string => htmlspecialchars((string) ($values[$key] ?? $default), ENT_QUOTES, 'UTF-8');
$selected = static fn (string $key, string $value): string => (string) ($values[$key] ?? '') === $value ? ' selected' : '';
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Workspace</label>
        <select class="form-select" name="workspace_id">
            <option value="">Ohne Workspace</option>
            <?php foreach ($workspaces ?? [] as $workspace): ?>
                <option value="<?= (int) $workspace['id'] ?>"<?= (string) ($values['workspace_id'] ?? '') === (string) $workspace['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Name</label>
        <input class="form-control" name="name" value="<?= $field('name') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Endpoint Key</label>
        <input class="form-control" name="endpoint_key" value="<?= $field('endpoint_key') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Beschreibung</label>
        <input class="form-control" name="description" value="<?= $field('description') ?>">
    </div>
    <div class="col-md-3"><label class="form-label">Method</label><select class="form-select" name="method"><option value="GET"<?= $selected('method', 'GET') ?>>GET</option><option value="POST"<?= $selected('method', 'POST') ?>>POST</option></select></div>
    <div class="col-md-3"><label class="form-label">Visibility</label><select class="form-select" name="visibility"><option value="private"<?= $selected('visibility', 'private') ?>>private</option><option value="public"<?= $selected('visibility', 'public') ?>>public</option></select></div>
    <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="draft"<?= $selected('status', 'draft') ?>>draft</option><option value="active"<?= $selected('status', 'active') ?>>active</option><option value="disabled"<?= $selected('status', 'disabled') ?>>disabled</option></select></div>
    <div class="col-md-3"><label class="form-label">Response Type</label><input class="form-control" value="json" disabled></div>
    <div class="col-md-4">
        <label class="form-label">Source Type</label>
        <select class="form-select" name="source_type">
            <?php foreach (['static', 'version', 'mapping_dry_run', 'job_status', 'latest_report'] as $sourceType): ?>
                <option value="<?= $sourceType ?>"<?= $selected('source_type', $sourceType) ?>><?= $sourceType ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Mapping Set</label>
        <select class="form-select" name="mapping_set_id">
            <option value="">Optional</option>
            <?php foreach ($mappings ?? [] as $mapping): ?>
                <option value="<?= (int) $mapping['id'] ?>"<?= (string) ($values['mapping_set_id'] ?? '') === (string) $mapping['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Job</label>
        <select class="form-select" name="job_id">
            <option value="">Optional</option>
            <?php foreach ($jobs ?? [] as $job): ?>
                <option value="<?= (int) $job['id'] ?>"<?= (string) ($values['job_id'] ?? '') === (string) $job['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $job['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Rate Limit pro Minute</label>
        <input class="form-control" type="number" min="1" name="rate_limit_per_minute" value="<?= $field('rate_limit_per_minute') ?>">
    </div>
    <div class="col-md-8">
        <label class="form-label">Secret setzen/ersetzen</label>
        <input class="form-control" type="password" name="secret" value="" autocomplete="new-password">
    </div>
    <div class="col-12">
        <label class="form-label">Static Response JSON</label>
        <textarea class="form-control font-monospace" rows="5" name="static_response"><?= htmlspecialchars((string) ($values['static_response'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea class="form-control" rows="3" name="notes"><?= $field('notes') ?></textarea>
    </div>
</div>
<hr>
