<?php

use Luna\Mapping\MappingValidationResult;

/** @var array<string, mixed>|null $mapping */
/** @var array<int, array<string, mixed>> $fields */
/** @var array{type: string, message: string}|null $alert */
/** @var MappingValidationResult|null $validation */
?>
<?php if (! empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($mapping === null): ?>
    <div class="alert alert-warning">Mapping nicht gefunden.</div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-body-secondary mb-0">Dieses Mapping wird in 0.8.0 nur entworfen und validiert, noch nicht ausgeführt.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="/admin/mappings/<?= (int) $mapping['id'] ?>/fields">Feldzuordnung hinzufügen</a>
            <form method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>/validate">
                <button class="btn btn-primary" type="submit">Validieren</button>
            </form>
            <form method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>/dry-run">
                <button class="btn btn-outline-success" type="submit">Dry Run</button>
            </form>
            <form method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>/run">
                <input type="hidden" name="confirm" value="run">
                <button class="btn btn-outline-danger" type="submit">Echten Transfer starten</button>
            </form>
        </div>
    </div>

    <?php if (! empty($alert)): ?>
        <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($validation !== null): ?>
        <?php require __DIR__ . '/validation.php'; ?>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card admin-card h-100">
                <div class="card-body">
                    <h2 class="h5">Source</h2>
                    <p class="mb-1"><?= htmlspecialchars((string) ($mapping['source_connection_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                    <code><?= htmlspecialchars((string) ($mapping['source_table'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card admin-card h-100">
                <div class="card-body">
                    <h2 class="h5">Target</h2>
                    <p class="mb-1"><?= htmlspecialchars((string) ($mapping['target_connection_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                    <code><?= htmlspecialchars((string) ($mapping['target_table'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code>
                </div>
            </div>
        </div>
    </div>

    <form class="card admin-card mb-4" method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>">
        <div class="card-body row g-3">
            <input type="hidden" name="workspace_id" value="<?= htmlspecialchars((string) ($mapping['workspace_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="source_connection_id" value="<?= htmlspecialchars((string) ($mapping['source_connection_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="target_connection_id" value="<?= htmlspecialchars((string) ($mapping['target_connection_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="col-md-4">
                <label class="form-label">Name</label>
                <input class="form-control" name="name" value="<?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Source Table</label>
                <input class="form-control" name="source_table" value="<?= htmlspecialchars((string) ($mapping['source_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Target Table</label>
                <input class="form-control" name="target_table" value="<?= htmlspecialchars((string) ($mapping['target_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label">Beschreibung</label>
                <input class="form-control" name="description" value="<?= htmlspecialchars((string) ($mapping['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <?php foreach (['draft', 'active', 'archived'] as $status): ?>
                        <option value="<?= $status ?>" <?= $mapping['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-footer bg-white">
            <button class="btn btn-outline-primary" type="submit">Mapping Set aktualisieren</button>
        </div>
    </form>

    <div class="card admin-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>Quelle</th>
                    <th>Ziel</th>
                    <th>Transform</th>
                    <th>Default</th>
                    <th>Aktionen</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($fields ?? [] as $field): ?>
                    <tr>
                        <td><code><?= htmlspecialchars((string) ($field['source_column'] ?? $field['source_json_path'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><code><?= htmlspecialchars((string) $field['target_column'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><?= htmlspecialchars((string) $field['transform_type'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($field['default_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><a class="btn btn-sm btn-outline-secondary" href="/admin/mappings/<?= (int) $mapping['id'] ?>/fields/<?= (int) $field['id'] ?>/value-rules">Value Rules</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($fields ?? []) === []): ?>
                    <tr><td colspan="5" class="text-body-secondary">Noch keine Feldzuordnungen angelegt.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
