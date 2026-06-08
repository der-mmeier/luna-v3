<?php
/** @var list<array<string, mixed>> $schemas */
/** @var list<array<string, mixed>> $workspaces */
/** @var array<string, mixed> $values */
/** @var list<string> $errors */
$field = static fn (string $key, string $default = ''): string => htmlspecialchars((string) ($values[$key] ?? $default), ENT_QUOTES, 'UTF-8');
$selected = static fn (string $key, string $value): string => (string) ($values[$key] ?? '') === $value ? ' selected' : '';
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">Schemas</h1>
        <p class="text-body-secondary mb-0">Versionierte Datenstrukturen je Workspace verwalten und validieren.</p>
    </div>
</div>

<?php foreach ($errors ?? [] as $error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>

<div class="card admin-card mb-4">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
            <tr>
                <th>Name</th>
                <th>Schema Key</th>
                <th>Version</th>
                <th>Workspace</th>
                <th>Status</th>
                <th>Geändert</th>
                <th class="text-end">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($schemas ?? [] as $schema): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $schema['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) $schema['schema_key'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><code><?= htmlspecialchars((string) $schema['version'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) ($schema['workspace_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $schema['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($schema['updated_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/admin/schemas/<?= (int) $schema['id'] ?>">Ansehen</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($schemas ?? []) === []): ?>
                <tr><td colspan="7" class="text-body-secondary">Noch keine Schemas angelegt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<form class="card admin-card" method="post" action="/admin/schemas">
    <div class="card-header">Schema anlegen</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Workspace</label>
                <select class="form-select" name="workspace_id" required>
                    <option value="">Bitte wählen</option>
                    <?php foreach ($workspaces ?? [] as $workspace): ?>
                        <option value="<?= (int) $workspace['id'] ?>"<?= (string) ($values['workspace_id'] ?? '') === (string) $workspace['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= $field('name') ?>" required></div>
            <div class="col-md-2"><label class="form-label">Schema Key</label><input class="form-control" name="schema_key" value="<?= $field('schema_key') ?>" required></div>
            <div class="col-md-2"><label class="form-label">Version</label><input class="form-control" name="version" value="<?= $field('version', '1') ?>" required></div>
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
                <textarea class="form-control font-monospace" rows="10" name="definition_json" required><?= $field('definition_json') ?></textarea>
                <div class="form-text">Unterstützt werden string, integer, number, boolean, object, array, null und mixed.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Example JSON</label>
                <textarea class="form-control font-monospace" rows="6" name="example_json"><?= $field('example_json') ?></textarea>
                <div class="form-text">Keine Passwörter, Tokens oder echten Kundendaten eintragen.</div>
            </div>
            <div class="col-12"><label class="form-label">Change Summary</label><input class="form-control" name="change_summary" value="<?= $field('change_summary') ?>"></div>
        </div>
        <div class="mt-3"><button class="btn btn-primary" type="submit">Speichern</button></div>
    </div>
</form>
