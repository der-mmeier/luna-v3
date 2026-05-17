<?php

/** @var array<int, array{name: string, status: string, updated: string}> $workspaces */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Workspaces</h1>
        <p class="text-body-secondary mb-0">Projektbereiche für Integrationsvorhaben.</p>
    </div>
    <button class="btn btn-primary" type="button">Workspace anlegen</button>
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Aktualisiert</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($workspaces ?? [] as $workspace): ?>
                <tr>
                    <td><?= htmlspecialchars($workspace['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge text-bg-light"><?= htmlspecialchars($workspace['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($workspace['updated'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
