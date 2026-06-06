<?php
/** @var array<string, mixed> $action */
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, string> $types */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars((string) $action['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-body-secondary mb-0">Target Action bearbeiten. Fachliche Zielsystemlogik bleibt außerhalb von Triggern.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/target-actions">Zurück</a>
</div>

<?php if (($errors ?? []) !== []): ?>
    <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form class="card admin-card" method="post" action="/admin/target-actions/<?= (int) $action['id'] ?>">
    <div class="card-header">Target Action bearbeiten</div>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Key</label>
            <input class="form-control" name="action_key" value="<?= htmlspecialchars((string) ($values['action_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Workspace</label>
            <select class="form-select" name="workspace_id" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($workspaces ?? [] as $workspace): ?>
                    <option value="<?= (int) $workspace['id'] ?>" <?= (int) ($values['workspace_id'] ?? 0) === (int) $workspace['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Typ</label>
            <select class="form-select" name="action_type">
                <?php foreach ($types ?? [] as $type): ?>
                    <option value="<?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($values['action_type'] ?? '') === (string) $type ? 'selected' : '' ?>><?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Konfiguration</label>
            <textarea class="form-control" name="config_json" rows="12"><?= htmlspecialchars((string) ($values['config_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            <div class="form-text">Keine Secrets speichern. Retry-Konfiguration kann vorbereitet werden; v2.5.0 führt maximal einen Versuch aus.</div>
        </div>
        <div class="col-md-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="target_action_active" <?= ! empty($values['is_active']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="target_action_active">Aktiv</label>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-outline-secondary" href="/admin/target-actions">Abbrechen</a>
    </div>
</form>
