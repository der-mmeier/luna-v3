<?php

/** @var array<int, array<string, mixed>> $transfers */
/** @var string|null $error */
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">Transfers</h1>
        <p class="text-body-secondary mb-0">Transfers konsumieren Datasets und schreiben bewusst in eine Ziel-Tabelle.</p>
    </div>
    <a class="btn btn-primary" href="/admin/transfers/create">Transfer anlegen</a>
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
                <th>Dataset</th>
                <th>Ziel</th>
                <th>Operation</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($transfers ?? [] as $transfer): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($transfer['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) ($transfer['source_dataset'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td>
                        <?= htmlspecialchars((string) ($transfer['target_connection_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <div><code><?= htmlspecialchars((string) ($transfer['target_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></div>
                    </td>
                    <td><?= htmlspecialchars((string) ($transfer['operation_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($transfer['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="/admin/transfers/<?= (int) $transfer['id'] ?>">Öffnen</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($transfers ?? []) === []): ?>
                <tr><td colspan="6" class="text-body-secondary">Noch keine Transfers vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
