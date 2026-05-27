<?php

/** @var array<int, array<string, mixed>> $workspaces */
/** @var string|null $error */
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Workspaces</h1>
        <p class="text-body-secondary mb-0">Projektbereiche für Integrationsvorhaben.</p>
    </div>
    <a class="btn btn-primary" href="/admin/workspaces/create">Workspace anlegen</a>
</div>

<?php if (! empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (($workspaces ?? []) === []): ?>
    <div class="card admin-card">
        <div class="card-body">
            <h2 class="h5">Noch keine Workspaces</h2>
            <p class="text-body-secondary">Lege den ersten Workspace an, um Connections, Mappings, Jobs, Reports und Endpoints zu bündeln.</p>
            <a class="btn btn-primary" href="/admin/workspaces/create">Ersten Workspace anlegen</a>
        </div>
    </div>
<?php else: ?>
    <div class="card admin-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Beschreibung</th>
                    <th>Aktualisiert</th>
                    <th class="text-end">Aktionen</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($workspaces as $workspace): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><code><?= htmlspecialchars((string) $workspace['slug'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><span class="badge text-bg-light"><?= htmlspecialchars((string) $workspace['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= htmlspecialchars(substr((string) ($workspace['description'] ?? ''), 0, 100), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $workspace['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/admin/workspaces/<?= (int) $workspace['id'] ?>">Bearbeiten</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
