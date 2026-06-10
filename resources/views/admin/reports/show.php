<?php
/** @var array<string, mixed>|null $report */
/** @var array<string, mixed> $values */
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, string> $errors */
/** @var array<string, mixed>|null $result */
$values = $values ?? ($report ?? []);
$isEdit = $report !== null;
$formAction = $isEdit ? '/admin/reports/' . (int) $report['id'] : '/admin/reports';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $isEdit ? 'Report bearbeiten' : 'Report anlegen' ?></h1>
        <p class="text-body-secondary mb-0">Reports sind verwaltbare Konfigurationen; komplexe BI-Auswertungen folgen später.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/reports">Zurück</a>
</div>

<?php if (($errors ?? []) !== []): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $message): ?>
                <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($result !== null): ?>
    <div class="alert alert-<?= ! empty($result['success']) ? 'success' : 'danger' ?>"><?= htmlspecialchars((string) ($result['message'] ?? 'Report-Aktion ausgeführt.'), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form class="card admin-card" method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label" for="workspace_id">Workspace optional</label>
            <select class="form-select" id="workspace_id" name="workspace_id">
                <option value="">Global</option>
                <?php foreach ($workspaces ?? [] as $workspace): ?>
                    <option value="<?= (int) $workspace['id'] ?>" <?= (string) ($values['workspace_id'] ?? '') === (string) $workspace['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="name">Name</label>
            <input class="form-control" id="name" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? $values['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="report_key">Key</label>
            <input class="form-control" id="report_key" name="report_key" value="<?= htmlspecialchars((string) ($values['report_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="type">Typ</label>
            <select class="form-select" id="type" name="type">
                <?php foreach (['process_runs', 'endpoint_snapshots', 'webhook_events', 'custom_sql_placeholder'] as $type): ?>
                    <option value="<?= $type ?>" <?= (string) ($values['type'] ?? 'process_runs') === $type ? 'selected' : '' ?>><?= $type ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <?php foreach (['draft', 'active', 'inactive'] as $status): ?>
                    <option value="<?= $status ?>" <?= (string) ($values['status'] ?? 'draft') === $status ? 'selected' : '' ?>><?= $status ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="config_json">Config JSON</label>
            <textarea class="form-control font-monospace" id="config_json" name="config_json" rows="8"><?= htmlspecialchars((string) ($values['config_json'] ?? '{}'), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="notes">Notizen</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars((string) ($values['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-outline-secondary" href="/admin/reports">Abbrechen</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="d-flex gap-2 mt-3">
        <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/send">
            <button class="btn btn-outline-primary" type="submit">Report senden</button>
        </form>
        <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/delete" onsubmit="return confirm('Diesen Report wirklich löschen?');">
            <input type="hidden" name="confirm_delete" value="1">
            <button class="btn btn-danger" type="submit">Löschen</button>
        </form>
    </div>
<?php endif; ?>
