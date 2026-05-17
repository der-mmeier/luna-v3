<?php

/** @var array<string, mixed>|null $mapping */
/** @var array<string, mixed>|null $field */
/** @var array<int, array<string, mixed>> $rules */
?>
<?php if ($mapping === null || $field === null): ?>
    <div class="alert alert-warning">Mapping Field nicht gefunden.</div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Value Rules</h1>
            <p class="text-body-secondary mb-0"><?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/mappings/<?= (int) $mapping['id'] ?>/fields">Zurück</a>
    </div>

    <div class="card admin-card mb-4">
        <div class="card-body">
            <h2 class="h5">Mapping Field</h2>
            <dl class="row mb-0">
                <dt class="col-sm-3">Source Column</dt>
                <dd class="col-sm-9"><code><?= htmlspecialchars((string) ($field['source_column'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></dd>
                <dt class="col-sm-3">Target Column</dt>
                <dd class="col-sm-9"><code><?= htmlspecialchars((string) ($field['target_column'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></dd>
                <dt class="col-sm-3">Transform Type</dt>
                <dd class="col-sm-9"><code><?= htmlspecialchars((string) ($field['transform_type'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></dd>
            </dl>
        </div>
    </div>

    <?php if (($field['transform_type'] ?? '') !== 'enum_map'): ?>
        <div class="alert alert-info">Value Rules sind nur für Transform Type <code>enum_map</code> relevant.</div>
    <?php endif; ?>

    <form class="card admin-card mb-4" method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>/fields/<?= (int) $field['id'] ?>/value-rules">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Source Value</label>
                <input class="form-control" name="source_value" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Target Value</label>
                <input class="form-control" name="target_value" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Notizen</label>
                <input class="form-control" name="notes">
            </div>
        </div>
        <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit">Value Rule hinzufügen</button>
        </div>
    </form>

    <div class="card admin-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Source Value</th><th>Target Value</th><th>Notizen</th><th>Aktionen</th></tr></thead>
                <tbody>
                <?php foreach ($rules ?? [] as $rule): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $rule['source_value'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $rule['target_value'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($rule['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>/fields/<?= (int) $field['id'] ?>/value-rules/<?= (int) $rule['id'] ?>/delete">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($rules ?? []) === []): ?>
                    <tr><td colspan="4" class="text-body-secondary">Noch keine Value Rules angelegt.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
