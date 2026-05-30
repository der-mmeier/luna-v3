<?php

/** @var array<int, array<string, mixed>> $connections */
/** @var string|null $error */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Connections</h1>
        <p class="text-body-secondary mb-0">Externe Datenquellen werden verschlüsselt in der Luna-Systemdatenbank verwaltet.</p>
    </div>
    <a class="btn btn-primary" href="/admin/connections/create">Connection anlegen</a>
</div>

<?php if (! empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="alert alert-info">Secrets werden verschlüsselt gespeichert und hier nie im Klartext angezeigt.</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Workspace</th>
                <th>Typ</th>
                <th>Driver</th>
                <th>Host</th>
                <th>Database</th>
                <th>Read-only</th>
                <th>Aktiv</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($connections ?? [] as $connection): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $connection['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($connection['workspace_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $connection['type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $connection['driver'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $connection['host'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $connection['database_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $connection['read_only'] === 1 ? 'Ja' : 'Nein' ?></td>
                    <td><?= (int) $connection['is_active'] === 1 ? 'Ja' : 'Nein' ?></td>
                    <td>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-secondary" href="/admin/connections/<?= (int) $connection['id'] ?>">Details</a>
                            <a class="btn btn-sm btn-outline-primary" href="/admin/connections/<?= (int) $connection['id'] ?>/edit">Bearbeiten</a>
                            <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/delete" onsubmit="return confirm('Diesen Eintrag wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
                                <input type="hidden" name="confirm_delete" value="1">
                                <button class="btn btn-sm btn-danger" type="submit">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($connections ?? []) === []): ?>
                <tr><td colspan="9" class="text-body-secondary">Noch keine Connections angelegt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
