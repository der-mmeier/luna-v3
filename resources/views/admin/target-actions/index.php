<?php
/** @var array<int, array<string, mixed>> $actions */
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, string> $types */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */

$workspaceNames = [];
foreach ($workspaces ?? [] as $workspace) {
    $workspaceNames[(int) $workspace['id']] = (string) $workspace['name'];
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Target Actions</h1>
        <p class="text-body-secondary mb-0">Generische Aktionen für Prozess-Schritte. Keine Secrets in exportierbaren Konfigurationen speichern.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/processes">Zu den Prozessen</a>
</div>

<div class="card admin-card mb-4">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Typ</th>
                    <th>Aktiv</th>
                    <th>Workspace</th>
                    <th>Letzte Änderung</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($actions ?? [] as $action): ?>
                <tr>
                    <td><a href="/admin/target-actions/<?= (int) $action['id'] ?>"><?= htmlspecialchars((string) $action['name'], ENT_QUOTES, 'UTF-8') ?></a><br><code><?= htmlspecialchars((string) $action['action_key'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) $action['action_type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge text-bg-<?= ! empty($action['is_active']) ? 'success' : 'secondary' ?>"><?= ! empty($action['is_active']) ? 'Aktiv' : 'Inaktiv' ?></span></td>
                    <td><?= htmlspecialchars((string) ($action['workspace_name'] ?? $workspaceNames[(int) ($action['workspace_id'] ?? 0)] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($action['updated_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="/admin/target-actions/<?= (int) $action['id'] ?>">Bearbeiten</a>
                            <form method="post" action="/admin/target-actions/<?= (int) $action['id'] ?>/toggle">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= ! empty($action['is_active']) ? 'Deaktivieren' : 'Aktivieren' ?></button>
                            </form>
                            <form method="post" action="/admin/target-actions/<?= (int) $action['id'] ?>/delete">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($actions ?? []) === []): ?>
                <tr><td colspan="6" class="text-body-secondary">Noch keine Target Actions angelegt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($errors ?? []) !== []): ?>
    <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<form class="card admin-card" method="post" action="/admin/target-actions">
    <div class="card-header">Target Action anlegen</div>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Key</label>
            <input class="form-control" name="action_key" value="<?= htmlspecialchars((string) ($values['action_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="optional">
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
                    <option value="<?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($values['action_type'] ?? 'http_get') === (string) $type ? 'selected' : '' ?>><?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Konfiguration</label>
            <textarea class="form-control" name="config_json" rows="7" placeholder='{"url":"https://example.test/api/status","timeout_seconds":10}'><?= htmlspecialchars((string) ($values['config_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            <div class="form-text">JSON wird validiert. Keine geheimen Tokens, Passwörter oder API-Keys in exportierbaren Konfigurationen speichern. Dry-Run führt keine HTTP-, Datei- oder Datenbank-Schreibaktion aus.</div>
        </div>
        <div class="col-md-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="target_action_active" <?= ! empty($values['is_active']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="target_action_active">Aktiv</label>
            </div>
        </div>
    </div>
    <div class="card-footer"><button class="btn btn-primary" type="submit">Target Action anlegen</button></div>
</form>
