<?php

/** @var array<string, mixed> $transfer */
/** @var array<int, array<string, mixed>> $fields */
/** @var array<int, array<string, mixed>> $groups */
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, array<string, mixed>> $connections */
/** @var array<int, array<string, mixed>> $datasets */
/** @var array<int, array<string, mixed>> $datasetFields */
/** @var array<string, mixed>|null $result */
/** @var array<int, string> $errors */

$previewOperations = is_array($result['preview_operations'] ?? null) ? $result['preview_operations'] : [];
$datasetFieldNames = array_map(static fn (array $datasetField): string => (string) ($datasetField['name'] ?? ''), $datasetFields ?? []);
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars((string) ($transfer['name'] ?? 'Transfer'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-body-secondary mb-0">Ein Dataset Row wird in genau eine Ziel-Zeile übersetzt.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="/admin/transfers">Zurück</a>
        <form method="post" action="/admin/transfers/<?= (int) ($transfer['id'] ?? 0) ?>/delete" onsubmit="return confirm('Diesen Transfer wirklich löschen?');">
            <input type="hidden" name="confirm_delete" value="1">
            <button class="btn btn-outline-danger" type="submit">Löschen</button>
        </form>
    </div>
</div>

<?php foreach ($errors ?? [] as $error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>

<form method="post" action="/admin/transfers/<?= (int) ($transfer['id'] ?? 0) ?>" class="card admin-card mb-4">
    <div class="card-header">Transfer-Konfiguration</div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars((string) ($transfer['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <?php foreach (['draft', 'active', 'archived'] as $status): ?>
                    <option value="<?= $status ?>" <?= (string) ($transfer['status'] ?? 'draft') === $status ? 'selected' : '' ?>><?= $status ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Operation</label>
            <select class="form-select" name="operation_type">
                <?php foreach (['upsert', 'insert', 'update'] as $operation): ?>
                    <option value="<?= $operation ?>" <?= (string) ($transfer['operation_type'] ?? 'upsert') === $operation ? 'selected' : '' ?>><?= $operation ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Workspace</label>
            <select class="form-select" name="workspace_id">
                <option value="">Bitte wählen</option>
                <?php foreach ($workspaces ?? [] as $workspace): ?>
                    <option value="<?= (int) $workspace['id'] ?>" <?= (int) ($transfer['workspace_id'] ?? 0) === (int) $workspace['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Source Dataset</label>
            <select class="form-select" name="source_dataset" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($datasets ?? [] as $dataset): ?>
                    <option value="<?= htmlspecialchars((string) $dataset['name'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($transfer['source_dataset'] ?? '') === (string) $dataset['name'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $dataset['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Target Connection</label>
            <select class="form-select" name="target_connection_id" data-role="target-connection" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($connections ?? [] as $connection): ?>
                    <option value="<?= (int) $connection['id'] ?>" <?= (int) ($transfer['target_connection_id'] ?? 0) === (int) $connection['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $connection['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Target Table</label>
            <select class="form-select" name="target_table" data-role="target-table" data-current="<?= htmlspecialchars((string) ($transfer['target_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                <?php if (! empty($transfer['target_table'])): ?>
                    <option value="<?= htmlspecialchars((string) $transfer['target_table'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $transfer['target_table'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php else: ?>
                    <option value="">Bitte wählen</option>
                <?php endif; ?>
            </select>
            <div class="form-text" data-role="target-table-status"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Upsert Key</label>
            <input class="form-control" name="upsert_key" value="<?= htmlspecialchars((string) ($transfer['upsert_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Beschreibung</label>
            <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars((string) ($transfer['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <div class="card-footer text-end">
        <button class="btn btn-primary" type="submit">Aktualisieren</button>
    </div>
</form>

<div class="card admin-card mb-4">
    <div class="card-header">Feldzuordnungen</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Sortierung</th>
                <th>Dataset-Feld</th>
                <th>Zielspalte</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($fields ?? [] as $field): ?>
                <?php $formId = 'transfer-field-' . (int) $field['id']; ?>
                <tr>
                    <td>
                        <input class="form-control form-control-sm" type="number" name="sort_order" value="<?= (int) ($field['sort_order'] ?? 0) ?>" form="<?= $formId ?>">
                    </td>
                    <td>
                        <select class="form-select form-select-sm" name="dataset_field" form="<?= $formId ?>" required>
                            <?php foreach ($datasetFields ?? [] as $datasetField): ?>
                                <option value="<?= htmlspecialchars((string) $datasetField['name'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($field['dataset_field'] ?? '') === (string) $datasetField['name'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $datasetField['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                            <?php if (! in_array((string) ($field['dataset_field'] ?? ''), $datasetFieldNames, true)): ?>
                                <option value="<?= htmlspecialchars((string) ($field['dataset_field'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) ($field['dataset_field'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (gespeichert)</option>
                            <?php endif; ?>
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm" name="target_column" data-role="transfer-target-column" data-current="<?= htmlspecialchars((string) ($field['target_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" form="<?= $formId ?>" required>
                            <?php if (! empty($field['target_column'])): ?>
                                <option value="<?= htmlspecialchars((string) $field['target_column'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $field['target_column'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php else: ?>
                                <option value="">Bitte wählen</option>
                            <?php endif; ?>
                        </select>
                    </td>
                    <td>
                        <form id="<?= $formId ?>" method="post" action="/admin/transfers/<?= (int) $transfer['id'] ?>/fields/<?= (int) $field['id'] ?>" class="d-inline">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Speichern</button>
                        </form>
                        <form method="post" action="/admin/transfers/<?= (int) $transfer['id'] ?>/fields/<?= (int) $field['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Diese Feldzuordnung wirklich löschen?');">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($fields ?? []) === []): ?>
                <tr><td colspan="4" class="text-body-secondary">Noch keine Feldzuordnungen vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body border-top">
        <form method="post" action="/admin/transfers/<?= (int) ($transfer['id'] ?? 0) ?>/fields" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Dataset-Feld</label>
                <select class="form-select" name="dataset_field" required>
                    <option value="">Bitte wählen</option>
                    <?php foreach ($datasetFields ?? [] as $datasetField): ?>
                        <option value="<?= htmlspecialchars((string) $datasetField['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $datasetField['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Zielspalte</label>
                <select class="form-select" name="target_column" data-role="transfer-target-column" data-current="" required>
                    <option value="">Bitte wählen</option>
                </select>
                <div class="form-text">Die Optionen werden aus der gewählten Target Table geladen.</div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sortierung</label>
                <input class="form-control" type="number" name="sort_order" value="<?= count($fields ?? []) + 1 ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100" type="submit">Hinzufügen</button>
            </div>
        </form>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Target Groups</div>
    <div class="card-body">
        <p class="text-body-secondary">Parent/Child-Transfers nutzen Target Groups. Bestehende Single-Table-Transfers funktionieren weiterhin ohne Target Groups.</p>
        <?php foreach ($groups ?? [] as $group): ?>
            <?php $groupFormId = 'transfer-group-' . (int) $group['id']; ?>
            <div class="border rounded p-3 mb-3" data-role="transfer-group">
                <form id="<?= $groupFormId ?>" method="post" action="/admin/transfers/<?= (int) $transfer['id'] ?>/groups/<?= (int) $group['id'] ?>"></form>
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-2">
                        <label class="form-label">Sortierung</label>
                        <input class="form-control form-control-sm" type="number" name="sort_order" value="<?= (int) ($group['sort_order'] ?? 0) ?>" form="<?= $groupFormId ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Name</label>
                        <input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string) ($group['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" form="<?= $groupFormId ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Typ</label>
                        <select class="form-select form-select-sm" name="group_type" form="<?= $groupFormId ?>">
                            <?php foreach (['root', 'child'] as $groupType): ?>
                                <option value="<?= $groupType ?>" <?= (string) ($group['group_type'] ?? 'root') === $groupType ? 'selected' : '' ?>><?= $groupType ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Source Path</label>
                        <input class="form-control form-control-sm" name="source_path" value="<?= htmlspecialchars((string) ($group['source_path'] ?? '$'), ENT_QUOTES, 'UTF-8') ?>" form="<?= $groupFormId ?>" placeholder="$ oder positions[]">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-outline-primary w-100" type="submit" form="<?= $groupFormId ?>">Speichern</button>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Target Table</label>
                        <select class="form-select form-select-sm" name="target_table" data-role="transfer-group-target-table" data-current="<?= htmlspecialchars((string) ($group['target_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" form="<?= $groupFormId ?>" required>
                            <?php if (! empty($group['target_table'])): ?>
                                <option value="<?= htmlspecialchars((string) $group['target_table'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $group['target_table'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php else: ?>
                                <option value="">Bitte wählen</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Operation</label>
                        <select class="form-select form-select-sm" name="operation_type" form="<?= $groupFormId ?>">
                            <?php foreach (['upsert', 'insert', 'update'] as $operation): ?>
                                <option value="<?= $operation ?>" <?= (string) ($group['operation_type'] ?? 'upsert') === $operation ? 'selected' : '' ?>><?= $operation ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Upsert Key</label>
                        <input class="form-control form-control-sm" name="upsert_key" value="<?= htmlspecialchars((string) ($group['upsert_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" form="<?= $groupFormId ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Parent Source</label>
                        <input class="form-control form-control-sm" name="parent_link_source" value="<?= htmlspecialchars((string) ($group['parent_link_source'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" form="<?= $groupFormId ?>" placeholder="root.order_number">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Parent Zielspalte</label>
                        <select class="form-select form-select-sm" name="parent_link_target" data-role="transfer-group-target-column" data-current="<?= htmlspecialchars((string) ($group['parent_link_target'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" form="<?= $groupFormId ?>">
                            <?php if (! empty($group['parent_link_target'])): ?>
                                <option value="<?= htmlspecialchars((string) $group['parent_link_target'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $group['parent_link_target'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php else: ?>
                                <option value="">Bitte wählen</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <form method="post" action="/admin/transfers/<?= (int) $transfer['id'] ?>/groups/<?= (int) $group['id'] ?>/delete" onsubmit="return confirm('Diese Target Group wirklich löschen?');">
                            <button class="btn btn-sm btn-outline-danger w-100" type="submit">Löschen</button>
                        </form>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Sortierung</th><th>Dataset-Feld</th><th>Zielspalte</th><th>Aktionen</th></tr></thead>
                        <tbody>
                        <?php foreach ((array) ($group['fields'] ?? []) as $groupField): ?>
                            <?php $groupFieldFormId = 'transfer-group-field-' . (int) $groupField['id']; ?>
                            <tr>
                                <td><input class="form-control form-control-sm" type="number" name="sort_order" value="<?= (int) ($groupField['sort_order'] ?? 0) ?>" form="<?= $groupFieldFormId ?>"></td>
                                <td><input class="form-control form-control-sm" name="dataset_field" value="<?= htmlspecialchars((string) ($groupField['dataset_field'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" form="<?= $groupFieldFormId ?>" required></td>
                                <td>
                                    <select class="form-select form-select-sm" name="target_column" data-role="transfer-group-target-column" data-current="<?= htmlspecialchars((string) ($groupField['target_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" form="<?= $groupFieldFormId ?>" required>
                                        <?php if (! empty($groupField['target_column'])): ?>
                                            <option value="<?= htmlspecialchars((string) $groupField['target_column'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $groupField['target_column'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php else: ?>
                                            <option value="">Bitte wählen</option>
                                        <?php endif; ?>
                                    </select>
                                </td>
                                <td>
                                    <form id="<?= $groupFieldFormId ?>" method="post" action="/admin/transfers/<?= (int) $transfer['id'] ?>/fields/<?= (int) $groupField['id'] ?>" class="d-inline">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Speichern</button>
                                    </form>
                                    <form method="post" action="/admin/transfers/<?= (int) $transfer['id'] ?>/fields/<?= (int) $groupField['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Diese Feldzuordnung wirklich löschen?');">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ((array) ($group['fields'] ?? []) === []): ?>
                            <tr><td colspan="4" class="text-body-secondary">Noch keine Feldzuordnungen vorhanden.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <form method="post" action="/admin/transfers/<?= (int) $transfer['id'] ?>/groups/<?= (int) $group['id'] ?>/fields" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Dataset-Feld</label>
                        <input class="form-control form-control-sm" name="dataset_field" list="dataset-field-suggestions" placeholder="order_number oder positions[].sku" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Zielspalte</label>
                        <select class="form-select form-select-sm" name="target_column" data-role="transfer-group-target-column" data-current="" required>
                            <option value="">Bitte wählen</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sortierung</label>
                        <input class="form-control form-control-sm" type="number" name="sort_order" value="<?= count((array) ($group['fields'] ?? [])) + 1 ?>">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-outline-primary w-100" type="submit">Hinzufügen</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>

        <form method="post" action="/admin/transfers/<?= (int) ($transfer['id'] ?? 0) ?>/groups" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Sortierung</label>
                <input class="form-control" type="number" name="sort_order" value="<?= count($groups ?? []) + 1 ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Name</label>
                <input class="form-control" name="name" placeholder="orders" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Typ</label>
                <select class="form-select" name="group_type">
                    <option value="root">root</option>
                    <option value="child">child</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Source Path</label>
                <input class="form-control" name="source_path" value="$" placeholder="$ oder positions[]">
            </div>
            <div class="col-md-2">
                <label class="form-label">Target Table</label>
                <input class="form-control" name="target_table" required>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100" type="submit">Group hinzufügen</button>
            </div>
        </form>
    </div>
</div>

<datalist id="dataset-field-suggestions">
    <?php foreach ($datasetFields ?? [] as $datasetField): ?>
        <option value="<?= htmlspecialchars((string) ($datasetField['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></option>
        <option value="root.<?= htmlspecialchars((string) ($datasetField['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></option>
    <?php endforeach; ?>
</datalist>

<div class="d-flex gap-2 mb-4">
    <form method="post" action="/admin/transfers/<?= (int) ($transfer['id'] ?? 0) ?>/dry-run">
        <button class="btn btn-outline-primary" type="submit">Dry-Run ausführen</button>
    </form>
    <form method="post" action="/admin/transfers/<?= (int) ($transfer['id'] ?? 0) ?>/run" onsubmit="return confirm('Diesen Transfer wirklich schreiben?');">
        <input type="hidden" name="confirm" value="run">
        <button class="btn btn-danger" type="submit">Echten Run starten</button>
    </form>
</div>

<?php if (is_array($result)): ?>
    <div class="card admin-card">
        <div class="card-header">Transfer-Ergebnis</div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-2"><div class="small text-body-secondary">Dry-Run</div><strong><?= ! empty($result['dry_run']) ? 'ja' : 'nein' ?></strong></div>
                <div class="col-md-2"><div class="small text-body-secondary">Source Rows</div><strong><?= (int) ($result['source_count'] ?? 0) ?></strong></div>
                <div class="col-md-2"><div class="small text-body-secondary">Geplant</div><strong><?= (int) ($result['planned_count'] ?? 0) ?></strong></div>
                <div class="col-md-2"><div class="small text-body-secondary">Geschrieben</div><strong><?= (int) ($result['written_count'] ?? 0) ?></strong></div>
                <div class="col-md-2"><div class="small text-body-secondary">Fehler</div><strong><?= (int) ($result['error_count'] ?? 0) ?></strong></div>
            </div>
            <?php if (! empty($result['errors']) && is_array($result['errors'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', array_map('strval', $result['errors'])), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($previewOperations !== []): ?>
                <pre class="mb-0"><code><?= htmlspecialchars(json_encode($previewOperations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8') ?></code></pre>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
