<?php
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<string, mixed> $values */
/** @var string $action */

$values = $values ?? [];
$action = $action ?? '/admin/processes';
?>
<div class="card-body row g-3">
    <div class="col-md-4">
        <label class="form-label" for="process_workspace_id">Workspace</label>
        <select class="form-select" id="process_workspace_id" name="workspace_id" required>
            <option value="">Bitte wählen</option>
            <?php foreach ($workspaces ?? [] as $workspace): ?>
                <option value="<?= (int) $workspace['id'] ?>" <?= (int) ($values['workspace_id'] ?? 0) === (int) $workspace['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="process_name">Name</label>
        <input class="form-control" id="process_name" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="process_key">Key</label>
        <input class="form-control" id="process_key" name="process_key" value="<?= htmlspecialchars((string) ($values['process_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="automatisch aus Name">
        <div class="form-text">Maschinenlesbarer Schlüssel pro Workspace. Leer lassen für automatische Vergabe.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="process_status">Status</label>
        <select class="form-select" id="process_status" name="status">
            <?php foreach (['draft' => 'Entwurf', 'active' => 'Aktiv', 'inactive' => 'Inaktiv'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= (string) ($values['status'] ?? 'draft') === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="process_default_mode">Standardmodus</label>
        <select class="form-select" id="process_default_mode" name="default_mode">
            <?php foreach (['run' => 'Ausführen', 'dry_run' => 'Dry-Run'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= (string) ($values['default_mode'] ?? 'run') === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="process_description">Beschreibung</label>
        <textarea class="form-control" id="process_description" name="description" rows="3"><?= htmlspecialchars((string) ($values['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>
</div>
<div class="card-footer d-flex gap-2">
    <button class="btn btn-primary" type="submit">Speichern</button>
    <a class="btn btn-outline-secondary" href="/admin/processes">Zurück</a>
</div>
