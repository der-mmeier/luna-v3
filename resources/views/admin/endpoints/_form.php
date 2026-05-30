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
    <div class="col-md-3">
        <label class="form-label">HTTP-Methode</label>
        <select class="form-select" name="method"><option value="GET"<?= $selected('method', 'GET') ?>>GET</option></select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select class="form-select" name="status"><option value="inactive"<?= $selected('status', 'inactive') ?>>inaktiv</option><option value="active"<?= $selected('status', 'active') ?>>aktiv</option></select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Secret-Modus</label>
        <select class="form-select" name="secret_mode">
            <option value="none"<?= $selected('secret_mode', 'none') ?>>none</option>
            <option value="optional"<?= $selected('secret_mode', 'optional') ?>>optional</option>
            <option value="required"<?= $selected('secret_mode', 'required') ?>>required</option>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Response Type</label><input class="form-control" value="json" disabled></div>
    <div class="col-md-6">
        <label class="form-label">Mapping</label>
        <select class="form-select" name="mapping_set_id" data-role="endpoint-mapping-select">
            <option value="">Bitte wählen</option>
            <?php foreach ($mappings ?? [] as $mapping): ?>
                <option data-workspace-id="<?= (int) ($mapping['workspace_id'] ?? 0) ?>" value="<?= (int) $mapping['id'] ?>"<?= (string) ($values['mapping_set_id'] ?? '') === (string) $mapping['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Es werden nur Mappings aus dem gewählten Workspace akzeptiert.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Cache aktiv</label>
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="cache_enabled" value="1" id="cache_enabled"<?= ! empty($values['cache_enabled']) ? ' checked' : '' ?>>
            <label class="form-check-label" for="cache_enabled">vorbereitet</label>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Cache TTL Sekunden</label>
        <input class="form-control" type="number" min="1" name="cache_ttl_seconds" value="<?= $field('cache_ttl_seconds') ?>">
    </div>
    <div class="col-md-8">
        <label class="form-label">Secret setzen/ersetzen</label>
        <input class="form-control" type="password" name="secret" value="" autocomplete="new-password">
        <div class="form-text">Das Secret wird nicht angezeigt. Bei required muss ein Secret gesetzt sein.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Runtime-URL</label>
        <input class="form-control font-monospace" value="/api/endpoints/<?= $field('endpoint_key') ?>" disabled>
    </div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea class="form-control" rows="3" name="notes"><?= $field('notes') ?></textarea>
    </div>
</div>
<hr>
