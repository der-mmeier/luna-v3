<?php

/** @var array<int, array<string, mixed>> $connections */
/** @var array<int, array<string, mixed>> $tables */
/** @var array<string, mixed>|null $connection */
/** @var array{type: string, message: string}|null $alert */
/** @var string|null $error */
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Schema Explorer</h1>
    <p class="text-body-secondary mb-0">Liest externe Schema-Metadaten. Externe Datenbanken werden nicht verändert.</p>
</div>

<?php if (! empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (! empty($alert)): ?>
    <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (! empty($connections)): ?>
    <div class="card admin-card mb-4">
        <div class="card-body">
            <h2 class="h5">Aktive Connections</h2>
            <div class="list-group">
                <?php foreach ($connections as $profile): ?>
                    <a class="list-group-item list-group-item-action" href="/admin/schema/<?= (int) $profile['id'] ?>">
                        <?= htmlspecialchars((string) $profile['name'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="text-body-secondary">· <?= htmlspecialchars((string) $profile['driver'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (! empty($connection)): ?>
    <h2 class="h5"><?= htmlspecialchars((string) $connection['name'], ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="card admin-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>Tabelle</th>
                    <th>Typ</th>
                    <th>Rows</th>
                    <th>Kommentar</th>
                    <th>Aktion</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tables ?? [] as $table): ?>
                    <tr>
                        <td><code><?= htmlspecialchars((string) $table['table_name'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><?= htmlspecialchars((string) $table['table_type'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($table['table_rows'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($table['table_comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="/admin/schema/<?= (int) $connection['id'] ?>/table?table=<?= rawurlencode((string) $table['table_name']) ?>">Analysieren</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($tables ?? []) === []): ?>
                    <tr><td colspan="5" class="text-body-secondary">Keine Tabellen verfügbar.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
