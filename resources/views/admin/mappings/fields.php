<?php

/** @var array<string, mixed>|null $mapping */
/** @var array<int, array<string, mixed>> $fields */
/** @var array<int, array<string, mixed>> $sourceColumns */
/** @var array<int, array<string, mixed>> $targetColumns */
/** @var array<string, string> $transformTypes */
/** @var string|null $columnWarning */
$targetCounts = [];

foreach ($fields ?? [] as $field) {
    $targetColumn = (string) ($field['target_column'] ?? '');

    if ($targetColumn !== '') {
        $targetCounts[$targetColumn] = ($targetCounts[$targetColumn] ?? 0) + 1;
    }
}
?>
<?php if ($mapping === null): ?>
    <div class="alert alert-warning">Mapping nicht gefunden.</div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Feldzuordnungen</h1>
            <p class="text-body-secondary mb-0"><?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/mappings/<?= (int) $mapping['id'] ?>">Zurück</a>
    </div>

    <?php if (! empty($columnWarning)): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($columnWarning, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form class="card admin-card mb-4" method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>/fields">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Source Column</label>
                <?php if (($sourceColumns ?? []) !== []): ?>
                    <select class="form-select" name="source_column">
                        <option value="">Keine</option>
                        <?php foreach ($sourceColumns as $column): ?>
                            <option value="<?= htmlspecialchars((string) $column['column_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $column['column_name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input class="form-control" name="source_column">
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Target Column</label>
                <?php if (($targetColumns ?? []) !== []): ?>
                    <select class="form-select" name="target_column">
                        <?php foreach ($targetColumns as $column): ?>
                            <option value="<?= htmlspecialchars((string) $column['column_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $column['column_name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input class="form-control" name="target_column" required>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Transform Type</label>
                <select class="form-select" name="transform_type">
                    <?php foreach ($transformTypes as $type => $label): ?>
                        <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">JSON Path</label>
                <input class="form-control" name="source_json_path">
            </div>
            <div class="col-md-4">
                <label class="form-label">Default Value</label>
                <input class="form-control" name="default_value">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sortierung</label>
                <input class="form-control" name="sort_order" value="0">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_required" value="1" id="is_required">
                    <label class="form-check-label" for="is_required">Required</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Notizen</label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
            </div>
        </div>
        <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit">Feldzuordnung speichern</button>
        </div>
    </form>

    <div class="card admin-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Source</th><th>Target</th><th>Type</th><th>Aktionen</th></tr></thead>
                <tbody>
                <?php foreach ($fields ?? [] as $field): ?>
                    <?php
                    $targetColumn = (string) ($field['target_column'] ?? '');
                    $isDuplicateTarget = $targetColumn !== '' && ($targetCounts[$targetColumn] ?? 0) > 1;
                    ?>
                    <tr class="<?= $isDuplicateTarget ? 'table-warning' : '' ?>">
                        <td><code><?= htmlspecialchars((string) ($field['source_column'] ?? $field['source_json_path'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td>
                            <code><?= htmlspecialchars($targetColumn, ENT_QUOTES, 'UTF-8') ?></code>
                            <?php if ($isDuplicateTarget): ?>
                                <span class="badge text-bg-warning ms-2">mehrfach</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string) $field['transform_type'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if (($field['transform_type'] ?? '') === 'enum_map'): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/mappings/<?= (int) $mapping['id'] ?>/fields/<?= (int) $field['id'] ?>/value-rules">Regeln</a>
                                <?php else: ?>
                                    <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true" title="Value Rules sind nur für enum_map relevant">Regeln</span>
                                <?php endif; ?>
                            <form method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>/fields/<?= (int) $field['id'] ?>/delete">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
