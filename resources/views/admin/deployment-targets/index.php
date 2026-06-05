<?php
/** @var list<array<string, mixed>> $targets */
/** @var array<string, string>|null $alert */
$targets = $targets ?? [];
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">Deployment Targets</h1>
        <p class="text-body-secondary mb-0">Zielumgebungen verwalten und Endpoint-URLs für Staging oder Production nachvollziehbar machen.</p>
    </div>
    <a class="btn btn-primary" href="/admin/deployment-targets/create">Target anlegen</a>
</div>

<?php if ($alert !== null): ?>
    <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card admin-card">
    <div class="card-body">
        <p class="text-body-secondary">Deployment Targets enthalten keine Secrets. `license_server_url` ist in 2.2.0 nur ein vorbereitetes Metadatum und wird nicht kontaktiert.</p>
        <?php if ($targets === []): ?>
            <div class="text-body-secondary">Kein Deployment Target konfiguriert.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Environment</th>
                        <th>Workspace</th>
                        <th>Public Base URL</th>
                        <th>Default</th>
                        <th>Aktiv</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($targets as $target): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $target['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><code><?= htmlspecialchars((string) $target['environment'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><?= htmlspecialchars((string) ($target['workspace_name'] ?? 'Global'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><code class="luna-breakable"><?= htmlspecialchars((string) $target['public_base_url'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><?= ! empty($target['is_default']) ? 'ja' : 'nein' ?></td>
                            <td><?= ! empty($target['is_active']) ? 'ja' : 'nein' ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/deployment-targets/<?= (int) $target['id'] ?>/edit">Bearbeiten</a>
                                    <form method="post" action="/admin/deployment-targets/<?= (int) $target['id'] ?>/default"><button class="btn btn-sm btn-outline-secondary" type="submit">Default setzen</button></form>
                                    <form method="post" action="/admin/deployment-targets/<?= (int) $target['id'] ?>/toggle"><button class="btn btn-sm btn-outline-secondary" type="submit"><?= ! empty($target['is_active']) ? 'Deaktivieren' : 'Aktivieren' ?></button></form>
                                    <form method="post" action="/admin/deployment-targets/<?= (int) $target['id'] ?>/delete" onsubmit="return confirm('Deployment Target wirklich löschen?');"><button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
