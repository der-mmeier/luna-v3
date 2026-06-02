<?php

/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, array<string, mixed>> $connections */
/** @var array<int, array<string, mixed>> $datasets */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">Transfer anlegen</h1>
        <p class="text-body-secondary mb-0">Single-Table-Transfer aus einem Dataset in eine Ziel-Tabelle.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/transfers">Zurück</a>
</div>

<?php foreach ($errors ?? [] as $error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>

<div class="alert alert-warning">
    Ein Transfer schreibt Daten in ein Zielsystem. Nutzen Sie zuerst den Dry-Run, bevor Sie einen echten Run starten.
</div>

<form method="post" action="/admin/transfers" class="card admin-card">
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <?php foreach (['draft', 'active', 'archived'] as $status): ?>
                    <option value="<?= $status ?>" <?= (string) ($values['status'] ?? 'draft') === $status ? 'selected' : '' ?>><?= $status ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Operation</label>
            <select class="form-select" name="operation_type">
                <?php foreach (['upsert', 'insert', 'update'] as $operation): ?>
                    <option value="<?= $operation ?>" <?= (string) ($values['operation_type'] ?? 'upsert') === $operation ? 'selected' : '' ?>><?= $operation ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Workspace</label>
            <select class="form-select" name="workspace_id">
                <option value="">Bitte wählen</option>
                <?php foreach ($workspaces ?? [] as $workspace): ?>
                    <option value="<?= (int) $workspace['id'] ?>" <?= (int) ($values['workspace_id'] ?? 0) === (int) $workspace['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Source Dataset</label>
            <select class="form-select" name="source_dataset" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($datasets ?? [] as $dataset): ?>
                    <option value="<?= htmlspecialchars((string) $dataset['name'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($values['source_dataset'] ?? '') === (string) $dataset['name'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $dataset['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Target Connection</label>
            <select class="form-select" name="target_connection_id" data-role="target-connection" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($connections ?? [] as $connection): ?>
                    <option value="<?= (int) $connection['id'] ?>" <?= (int) ($values['target_connection_id'] ?? 0) === (int) $connection['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $connection['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Target Table</label>
            <select class="form-select" name="target_table" data-role="target-table" data-current="<?= htmlspecialchars((string) ($values['target_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                <?php if (! empty($values['target_table'])): ?>
                    <option value="<?= htmlspecialchars((string) $values['target_table'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $values['target_table'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php else: ?>
                    <option value="">Bitte wählen</option>
                <?php endif; ?>
            </select>
            <div class="form-text" data-role="target-table-status"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Upsert Key</label>
            <input class="form-control" name="upsert_key" value="<?= htmlspecialchars((string) ($values['upsert_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="model">
            <div class="form-text">Mehrere Zielspalten kommagetrennt angeben.</div>
        </div>
        <div class="col-12">
            <label class="form-label">Beschreibung</label>
            <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars((string) ($values['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <div class="card-footer text-end">
        <button class="btn btn-primary" type="submit">Speichern</button>
    </div>
</form>
