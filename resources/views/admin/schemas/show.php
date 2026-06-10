<?php
/** @var array<string, mixed>|null $schema */
/** @var list<array<string, mixed>> $workspaces */
/** @var array<string, mixed> $values */
/** @var list<string> $errors */
/** @var string $validationInput */
/** @var array<string, mixed>|null $validationResult */
/** @var list<array<string, mixed>> $revisions */
/** @var array{type: string, message: string}|null $alert */
$values = $values ?? [];
$field = static fn (string $key, string $default = ''): string => htmlspecialchars((string) ($values[$key] ?? $default), ENT_QUOTES, 'UTF-8');
$selected = static fn (string $key, string $value): string => (string) ($values[$key] ?? '') === $value ? ' selected' : '';
?>
<?php if ($schema === null): ?>
    <div class="alert alert-warning">Schema nicht gefunden.</div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars((string) $schema['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-body-secondary mb-0"><code><?= htmlspecialchars((string) $schema['schema_key'], ENT_QUOTES, 'UTF-8') ?>.v<?= htmlspecialchars((string) $schema['version'], ENT_QUOTES, 'UTF-8') ?></code></p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/schemas">Zurück</a>
    </div>

    <?php foreach ($errors ?? [] as $error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <?php if (! empty($alert)): ?>
        <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= nl2br(htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8')) ?></div>
    <?php endif; ?>

    <?php if ($validationResult !== null): ?>
        <div class="alert alert-<?= ! empty($validationResult['valid']) ? 'success' : 'danger' ?>">
            <?= ! empty($validationResult['valid']) ? 'Validierung erfolgreich.' : 'Validierung fehlgeschlagen.' ?>
        </div>
        <?php if (($validationResult['errors'] ?? []) !== []): ?>
            <div class="card admin-card mb-4">
                <div class="card-header">Validierungsfehler</div>
                <div class="card-body">
                    <ul class="mb-0">
                        <?php foreach (($validationResult['errors'] ?? []) as $error): ?>
                            <?php if (is_array($error)): ?>
                                <li><code><?= htmlspecialchars((string) ($error['path'] ?? '$'), ENT_QUOTES, 'UTF-8') ?></code> <?= htmlspecialchars((string) ($error['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form class="card admin-card mb-4" method="post" action="/admin/schemas/<?= (int) $schema['id'] ?>">
        <div class="card-header">Schema bearbeiten</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Workspace</label>
                    <select class="form-select" name="workspace_id" required>
                        <?php foreach ($workspaces ?? [] as $workspace): ?>
                            <option value="<?= (int) $workspace['id'] ?>"<?= (string) ($values['workspace_id'] ?? '') === (string) $workspace['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= $field('name') ?>" required></div>
                <div class="col-md-2"><label class="form-label">Schema Key</label><input class="form-control" name="schema_key" value="<?= $field('schema_key') ?>" required></div>
                <div class="col-md-2"><label class="form-label">Version</label><input class="form-control" name="version" value="<?= $field('version') ?>" required></div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="draft"<?= $selected('status', 'draft') ?>>draft</option>
                        <option value="active"<?= $selected('status', 'active') ?>>active</option>
                        <option value="deprecated"<?= $selected('status', 'deprecated') ?>>deprecated</option>
                    </select>
                </div>
                <div class="col-md-9"><label class="form-label">Beschreibung</label><input class="form-control" name="description" value="<?= $field('description') ?>"></div>
                <div class="col-12">
                    <label class="form-label">Definition JSON</label>
                    <textarea class="form-control font-monospace" rows="14" name="definition_json" required><?= $field('definition_json') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Example JSON</label>
                    <textarea class="form-control font-monospace" rows="8" name="example_json"><?= $field('example_json') ?></textarea>
                    <div class="form-text">Keine Secrets oder echten Zugangsdaten speichern.</div>
                </div>
                <div class="col-12"><label class="form-label">Change Summary</label><input class="form-control" name="change_summary" value="<?= $field('change_summary') ?>"></div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Aktualisieren</button>
                <a class="btn btn-outline-secondary" href="/admin/schemas">Abbrechen</a>
            </div>
        </div>
    </form>

    <form class="card admin-card mb-4" method="post" action="/admin/schemas/<?= (int) $schema['id'] ?>/validate">
        <div class="card-header">JSON validieren</div>
        <div class="card-body">
            <label class="form-label">Payload JSON</label>
            <textarea class="form-control font-monospace" rows="10" name="validation_json"><?= htmlspecialchars((string) ($validationInput ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            <div class="form-text">Leer lassen, um das gespeicherte Example JSON zu validieren.</div>
            <button class="btn btn-outline-primary mt-3" type="submit">Validieren</button>
        </div>
    </form>

    <div class="card admin-card mb-4">
        <div class="card-header">Status und Löschen</div>
        <div class="card-body d-flex flex-wrap gap-2">
            <form method="post" action="/admin/schemas/<?= (int) $schema['id'] ?>/status">
                <input type="hidden" name="status" value="active">
                <button class="btn btn-outline-success" type="submit">Aktiv setzen</button>
            </form>
            <form method="post" action="/admin/schemas/<?= (int) $schema['id'] ?>/status">
                <input type="hidden" name="status" value="deprecated">
                <button class="btn btn-outline-warning" type="submit">Deprecate</button>
            </form>
            <form method="post" action="/admin/schemas/<?= (int) $schema['id'] ?>/delete" onsubmit="return confirm('Dieses Schema wirklich löschen? Referenzierte Schemas können nicht gelöscht werden.');">
                <input type="hidden" name="confirm_delete" value="1">
                <button class="btn btn-outline-danger" type="submit">Löschen</button>
            </form>
        </div>
    </div>

    <div class="card admin-card">
        <div class="card-header">Revisionen</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>ID</th><th>Version</th><th>Änderung</th><th>Erstellt</th></tr></thead>
                <tbody>
                <?php foreach ($revisions ?? [] as $revision): ?>
                    <tr>
                        <td><?= (int) $revision['id'] ?></td>
                        <td><code><?= htmlspecialchars((string) $revision['version'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><?= htmlspecialchars((string) ($revision['change_summary'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($revision['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($revisions ?? []) === []): ?>
                    <tr><td colspan="4" class="text-body-secondary">Noch keine Revisionen vorhanden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
