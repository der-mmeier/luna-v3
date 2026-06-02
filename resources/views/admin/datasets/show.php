<?php

/** @var array<string, mixed> $dataset */
/** @var array<int, array<string, mixed>> $fields */
/** @var array<int, array<string, mixed>> $sourceFilters */
/** @var array<string, mixed> $preview */
/** @var string|null $error */

$rows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
$summary = is_array($preview['summary'] ?? null) ? $preview['summary'] : [];
$columns = [];
foreach ($rows as $row) {
    if (is_array($row)) {
        $columns = array_values(array_unique(array_merge($columns, array_keys($row))));
    }
}
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars((string) ($dataset['label'] ?? $dataset['name'] ?? 'Dataset'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-body-secondary mb-0"><code><?= htmlspecialchars((string) ($dataset['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/datasets">Zurück</a>
</div>

<?php if (! empty($error)): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="alert alert-info">
    Source Type: <strong>Dataset</strong>. Dieses Dataset ist ein geprüftes Mapping-/Endpoint-Ergebnis, keine echte Connection Table. In 1.8.0 ist es nur als interne Quelle vorbereitet und schreibt keine Daten.
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card admin-card h-100"><div class="card-body"><div class="small text-body-secondary">Quelle</div><strong><?= htmlspecialchars((string) ($dataset['source_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
    <div class="col-md-3"><div class="card admin-card h-100"><div class="card-body"><div class="small text-body-secondary">Mapping</div><strong><?= htmlspecialchars((string) ($dataset['mapping_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
    <div class="col-md-3"><div class="card admin-card h-100"><div class="card-body"><div class="small text-body-secondary">Status</div><strong><?= htmlspecialchars((string) ($dataset['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
    <div class="col-md-3"><div class="card admin-card h-100"><div class="card-body"><div class="small text-body-secondary">Source Table</div><code><?= htmlspecialchars((string) ($dataset['source_table'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></div></div></div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Source Filter</div>
    <div class="card-body">
        <?php if (($sourceFilters ?? []) === []): ?>
            <div class="text-body-secondary">Keine Source Filter hinterlegt.</div>
        <?php else: ?>
            <ul class="mb-0">
                <?php foreach ($sourceFilters as $filter): ?>
                    <li>
                        <code><?= htmlspecialchars((string) ($filter['source_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                        <?= htmlspecialchars((string) ($filter['operator'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (($filter['filter_value'] ?? '') !== ''): ?>
                            <code><?= htmlspecialchars((string) $filter['filter_value'], ENT_QUOTES, 'UTF-8') ?></code>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Output-Felder</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Sortierung</th>
                <th>Feld</th>
                <th>Source Column</th>
                <th>Transform</th>
                <th>Template</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($fields ?? [] as $field): ?>
                <tr>
                    <td><code><?= (int) ($field['sort_order'] ?? 0) ?></code></td>
                    <td><code><?= htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><code><?= htmlspecialchars((string) ($field['source_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) ($field['transform_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) ($field['lookup_key_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($fields ?? []) === []): ?>
                <tr><td colspan="5" class="text-body-secondary">Keine Output-Felder gefunden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card admin-card">
    <div class="card-header">Dataset Preview</div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="small text-body-secondary">Source Rows</div><strong><?= (int) ($summary['source_count'] ?? 0) ?></strong></div>
            <div class="col-md-3"><div class="small text-body-secondary">Transformed Rows</div><strong><?= (int) ($summary['transformed_count'] ?? 0) ?></strong></div>
            <div class="col-md-3"><div class="small text-body-secondary">Errors</div><strong><?= (int) ($summary['error_count'] ?? 0) ?></strong></div>
            <div class="col-md-3"><div class="small text-body-secondary">Written Rows</div><strong><?= (int) ($summary['written_count'] ?? 0) ?></strong></div>
        </div>
        <?php if (! empty($summary['errors']) && is_array($summary['errors'])): ?>
            <div class="alert alert-warning"><?= htmlspecialchars(implode(' ', array_map('strval', $summary['errors'])), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($rows === []): ?>
            <div class="text-body-secondary">Keine Preview-Zeilen verfügbar.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th><?= htmlspecialchars((string) $column, ENT_QUOTES, 'UTF-8') ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <?php $value = is_array($row[$column] ?? null) ? json_encode($row[$column], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) ($row[$column] ?? ''); ?>
                                <td><code title="<?= htmlspecialchars($value ?: '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(strlen($value ?: '') > 80 ? substr($value, 0, 77) . '...' : $value, ENT_QUOTES, 'UTF-8') ?></code></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
