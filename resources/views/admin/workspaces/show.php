<?php
/** @var array<string, mixed>|null $workspace */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
/** @var array<string, mixed>|null $transferDbStatus */
/** @var array<string, string>|null $alert */
?>
<?php if ($workspace === null): ?>
    <div class="alert alert-warning">Workspace nicht gefunden.</div>
<?php else: ?>
    <div class="mb-4">
        <h1 class="h3 mb-1"><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-body-secondary mb-0"><code><?= htmlspecialchars((string) $workspace['slug'], ENT_QUOTES, 'UTF-8') ?></code></p>
    </div>

    <?php foreach ($errors ?? [] as $error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>
    <?php if (($alert ?? null) !== null): ?>
        <div class="alert alert-<?= htmlspecialchars((string) $alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form class="card admin-card" method="post" action="/admin/workspaces/<?= (int) $workspace['id'] ?>">
        <div class="card-body">
            <?php include __DIR__ . '/_form.php'; ?>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary" type="submit">Aktualisieren</button>
            <a class="btn btn-outline-secondary" href="/admin/workspaces">Zurück</a>
            <button class="btn btn-danger" type="submit" form="delete-workspace-form">Löschen</button>
        </div>
    </form>
    <form id="delete-workspace-form" method="post" action="/admin/workspaces/<?= (int) $workspace['id'] ?>/delete" onsubmit="return confirm('Diesen Eintrag wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
        <input type="hidden" name="confirm_delete" value="1">
    </form>

    <?php $status = $transferDbStatus ?? null; ?>
    <div class="card admin-card mt-4">
        <div class="card-header">TransferDB</div>
        <div class="card-body">
            <p class="text-body-secondary">Die TransferDB ist eine separate Runtime-/Staging-Datenbank. Luna legt dort ausschließlich eigene Tabellen mit <code>luna_</code>-Prefix an.</p>
            <?php if ($status === null): ?>
                <div class="alert alert-secondary">TransferDB-Status wurde noch nicht geprüft.</div>
            <?php elseif (! empty($status['error'])): ?>
                <div class="alert alert-warning"><?= htmlspecialchars((string) $status['error'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>
                <dl class="row">
                    <dt class="col-md-3">Connection</dt>
                    <dd class="col-md-9"><?= htmlspecialchars((string) (($status['connection']['name'] ?? '') ?: '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-md-3">Erreichbar</dt>
                    <dd class="col-md-9"><?= ! empty($status['reachable']) ? 'Ja' : 'Nein' ?></dd>
                    <dt class="col-md-3">Schema</dt>
                    <dd class="col-md-9"><?= ! empty($status['schema_current']) ? 'Aktuell' : 'Tabellen fehlen oder Migration fehlt' ?></dd>
                    <dt class="col-md-3">Letzte Migration</dt>
                    <dd class="col-md-9"><?= htmlspecialchars((string) ($status['migration_version'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                </dl>
                <?php if (($status['missing_tables'] ?? []) !== []): ?>
                    <div class="small text-body-secondary">Fehlende Tabellen:</div>
                    <ul>
                        <?php foreach ($status['missing_tables'] as $table): ?>
                            <li><code><?= htmlspecialchars((string) $table, ENT_QUOTES, 'UTF-8') ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
            <div class="alert alert-info">Die TransferDB kann produktive Payloads und personenbezogene Daten enthalten. Der Betreiber ist für Absicherung, Zugriffsschutz, Backups und Datenschutz verantwortlich.</div>
            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="/admin/workspaces/<?= (int) $workspace['id'] ?>/transferdb/check">
                    <button class="btn btn-outline-primary" type="submit">TransferDB prüfen</button>
                </form>
                <form method="post" action="/admin/workspaces/<?= (int) $workspace['id'] ?>/transferdb/migrate">
                    <button class="btn btn-primary" type="submit">TransferDB Tabellen anlegen/aktualisieren</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
