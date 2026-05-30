<?php

/** @var array<int, array<string, mixed>> $mappings */
/** @var string|null $error */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Mappings</h1>
        <p class="text-body-secondary mb-0">Mapping Sets werden entworfen und validiert, aber noch nicht ausgeführt.</p>
    </div>
    <a class="btn btn-primary" href="/admin/mappings/create">Mapping anlegen</a>
</div>

<?php if (! empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Workspace</th>
                <th>Source</th>
                <th>Source Table</th>
                <th>Target</th>
                <th>Target Table</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($mappings ?? [] as $mapping): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($mapping['workspace_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($mapping['source_connection_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) ($mapping['source_table'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) ($mapping['target_connection_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) ($mapping['target_table'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string) $mapping['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="/admin/mappings/<?= (int) $mapping['id'] ?>">Details</a>
                            <form method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>/delete" onsubmit="return confirm('Diesen Eintrag wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
                                <input type="hidden" name="confirm_delete" value="1">
                                <button class="btn btn-sm btn-danger" type="submit">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($mappings ?? []) === []): ?>
                <tr><td colspan="8" class="text-body-secondary">Noch keine Mapping-Sets angelegt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
