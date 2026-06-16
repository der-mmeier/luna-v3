<?php

/** @var array<string, mixed>|null $connection */
/** @var array{type: string, message: string}|null $alert */
/** @var array<string, mixed>|null $transferDbStatus */
$connection = $connection ?? null;
$transferDbStatus = $transferDbStatus ?? null;
$isTransferDb = $connection !== null && in_array((string) ($connection['type'] ?? ''), ['transfer_db', 'mixed'], true);
$typeLabels = [
    'source' => 'Source',
    'transfer' => 'Transfer',
    'target' => 'Target',
    'transfer_db' => 'TransferDB',
    'mixed' => 'Mixed',
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Connection Details</h1>
        <p class="text-body-secondary mb-0">Secrets werden nicht angezeigt.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/connections">Zurück</a>
</div>

<?php if (! empty($alert)): ?>
    <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= nl2br(htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8')) ?></div>
<?php endif; ?>

<?php if ($connection === null): ?>
    <div class="alert alert-warning">Connection nicht gefunden.</div>
<?php else: ?>
    <div class="card admin-card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <?php foreach (['name' => 'Name', 'driver' => 'Driver', 'host' => 'Host', 'port' => 'Port', 'database_name' => 'Datenbank', 'username' => 'Username', 'notes' => 'Notizen'] as $key => $label): ?>
                    <dt class="col-sm-3"><?= $label ?></dt>
                    <dd class="col-sm-9"><?= htmlspecialchars((string) ($connection[$key] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                <?php endforeach; ?>
                <dt class="col-sm-3">Typ</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($typeLabels[(string) ($connection['type'] ?? '')] ?? (string) ($connection['type'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Owner Workspace</dt>
                <dd class="col-sm-9"><?= htmlspecialchars((string) ($connection['workspace_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Freigegeben für</dt>
                <dd class="col-sm-9">
                    <?php $sharedNames = (array) ($connection['shared_workspace_names'] ?? []); ?>
                    <?= $sharedNames === [] ? '<span class="text-body-secondary">-</span>' : htmlspecialchars(implode(', ', array_map('strval', $sharedNames)), ENT_QUOTES, 'UTF-8') ?>
                </dd>
                <dt class="col-sm-3">Read-only</dt>
                <dd class="col-sm-9"><?= (int) $connection['read_only'] === 1 ? 'Ja' : 'Nein' ?></dd>
                <dt class="col-sm-3">Aktiv</dt>
                <dd class="col-sm-9"><?= (int) $connection['is_active'] === 1 ? 'Ja' : 'Nein' ?></dd>
            </dl>
        </div>
    </div>

    <?php if ($isTransferDb): ?>
        <div class="card admin-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">TransferDB Management</h2>
                        <p class="text-body-secondary mb-0">Setup und Migration laufen gegen diese ausgewählte TransferDB-Connection, nicht gegen die Luna-Systemdatenbank.</p>
                    </div>
                    <?php if ($transferDbStatus !== null): ?>
                        <span class="badge <?= ! empty($transferDbStatus['schema_current']) ? 'text-bg-success' : 'text-bg-warning' ?>">
                            <?= ! empty($transferDbStatus['schema_current']) ? 'Schema aktuell' : 'Schema unvollständig' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="alert alert-info mb-3">Die TransferDB kann produktive Payloads und personenbezogene Daten enthalten. Der Betreiber ist für Absicherung, Zugriffsschutz, Backups und Datenschutz verantwortlich.</div>

                <?php if ($transferDbStatus !== null): ?>
                    <dl class="row small mb-3">
                        <dt class="col-sm-3">Erreichbar</dt>
                        <dd class="col-sm-9"><?= ! empty($transferDbStatus['reachable']) ? 'Ja' : 'Nein' ?></dd>
                        <dt class="col-sm-3">Migration</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars((string) ($transferDbStatus['migration_version'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                        <?php if (! empty($transferDbStatus['error'])): ?>
                            <dt class="col-sm-3">Fehler</dt>
                            <dd class="col-sm-9 text-danger"><?= htmlspecialchars((string) $transferDbStatus['error'], ENT_QUOTES, 'UTF-8') ?></dd>
                        <?php endif; ?>
                    </dl>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <h3 class="h6">Vorhandene Tabellen</h3>
                            <?php if (($transferDbStatus['existing_tables'] ?? []) === []): ?>
                                <p class="text-body-secondary mb-0">Keine TransferDB-Tabellen gefunden.</p>
                            <?php else: ?>
                                <ul class="mb-0">
                                    <?php foreach ((array) $transferDbStatus['existing_tables'] as $table): ?>
                                        <li><code><?= htmlspecialchars((string) $table, ENT_QUOTES, 'UTF-8') ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h3 class="h6">Fehlende Tabellen</h3>
                            <?php if (($transferDbStatus['missing_tables'] ?? []) === []): ?>
                                <p class="text-success mb-0">Keine Tabellen fehlen.</p>
                            <?php else: ?>
                                <ul class="mb-0">
                                    <?php foreach ((array) $transferDbStatus['missing_tables'] as $table): ?>
                                        <li><code><?= htmlspecialchars((string) $table, ENT_QUOTES, 'UTF-8') ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2">
        <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/test">
            <button class="btn btn-primary" type="submit">Test connection</button>
        </form>
        <?php if ($isTransferDb): ?>
            <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/transferdb/status">
                <button class="btn btn-outline-info" type="submit">Check TransferDB schema</button>
            </form>
            <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/transferdb/setup">
                <button class="btn btn-outline-warning" type="submit">Install/setup TransferDB schema</button>
            </form>
            <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/transferdb/migrate">
                <button class="btn btn-outline-warning" type="submit">Migrate TransferDB schema</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-outline-primary" href="/admin/schema/<?= (int) $connection['id'] ?>">Schema anzeigen</a>
        <a class="btn btn-outline-secondary" href="/admin/connections/<?= (int) $connection['id'] ?>/edit">Bearbeiten</a>
        <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/delete" onsubmit="return confirm('Diesen Eintrag wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
            <input type="hidden" name="confirm_delete" value="1">
            <button class="btn btn-danger" type="submit">Löschen</button>
        </form>
    </div>
<?php endif; ?>
