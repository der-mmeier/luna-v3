<?php

/** @var array<string, mixed>|null $connection */
/** @var array<int, array<string, mixed>> $sharedWorkspaces */
/** @var array<string, mixed>|null $transferDbStatus */
/** @var array{type: string, message: string}|null $alert */
$sharedWorkspaces = $sharedWorkspaces ?? [];
$transferDbStatus = $transferDbStatus ?? null;
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
                <?php foreach (['name' => 'Name', 'type' => 'Typ', 'driver' => 'Driver', 'host' => 'Host', 'port' => 'Port', 'database_name' => 'Datenbank', 'username' => 'Username', 'notes' => 'Notizen'] as $key => $label): ?>
                    <dt class="col-sm-3"><?= $label ?></dt>
                    <dd class="col-sm-9"><?= htmlspecialchars((string) ($connection[$key] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                <?php endforeach; ?>
                <dt class="col-sm-3">Owner-Workspace</dt>
                <dd class="col-sm-9"><?= htmlspecialchars((string) ($connection['workspace_name'] ?? 'Kein Workspace'), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Freigegebene Workspaces</dt>
                <dd class="col-sm-9">
                    <?php if ($sharedWorkspaces === []): ?>
                        Keine zusätzlichen Freigaben.
                    <?php else: ?>
                        <?= htmlspecialchars(implode(', ', array_map(static fn (array $workspace): string => (string) $workspace['name'], $sharedWorkspaces)), ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </dd>
                <dt class="col-sm-3">Read-only</dt>
                <dd class="col-sm-9"><?= (int) $connection['read_only'] === 1 ? 'Ja' : 'Nein' ?></dd>
                <dt class="col-sm-3">Aktiv</dt>
                <dd class="col-sm-9"><?= (int) $connection['is_active'] === 1 ? 'Ja' : 'Nein' ?></dd>
            </dl>
        </div>
    </div>

    <?php if (in_array((string) ($connection['type'] ?? ''), ['transfer_db', 'mixed'], true)): ?>
        <div class="card admin-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">TransferDB Management</h2>
                        <p class="text-body-secondary mb-0">Die Aktionen laufen gegen diese ausgewählte TransferDB-Connection, nicht gegen die Luna-Systemdatenbank.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/transferdb/status"><button class="btn btn-outline-primary btn-sm" type="submit">Schema prüfen</button></form>
                        <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/transferdb/setup"><button class="btn btn-outline-success btn-sm" type="submit">Schema einrichten</button></form>
                        <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/transferdb/migrate"><button class="btn btn-outline-success btn-sm" type="submit">Schema migrieren</button></form>
                    </div>
                </div>
                <?php if ($transferDbStatus === null): ?>
                    <div class="alert alert-secondary mb-0">Status wurde noch nicht geprüft.</div>
                <?php else: ?>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Erreichbar</dt>
                        <dd class="col-sm-9"><?= ! empty($transferDbStatus['reachable']) ? 'Ja' : 'Nein' ?></dd>
                        <dt class="col-sm-3">Schema aktuell</dt>
                        <dd class="col-sm-9"><?= ! empty($transferDbStatus['schema_current']) ? 'Ja' : 'Nein' ?></dd>
                        <dt class="col-sm-3">Migration</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars((string) ($transferDbStatus['migration_version'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-3">Vorhandene Tabellen</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars(implode(', ', $transferDbStatus['existing_tables'] ?? []), ENT_QUOTES, 'UTF-8') ?: '-' ?></dd>
                        <dt class="col-sm-3">Fehlende Tabellen</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars(implode(', ', $transferDbStatus['missing_tables'] ?? []), ENT_QUOTES, 'UTF-8') ?: '-' ?></dd>
                        <?php if (! empty($transferDbStatus['error'])): ?>
                            <dt class="col-sm-3">Fehler</dt>
                            <dd class="col-sm-9 text-danger"><?= htmlspecialchars((string) $transferDbStatus['error'], ENT_QUOTES, 'UTF-8') ?></dd>
                        <?php endif; ?>
                    </dl>
                <?php endif; ?>
                <p class="text-body-secondary mt-3 mb-0">Die TransferDB kann produktive Payloads und personenbezogene Daten enthalten. Der Betreiber ist für Absicherung, Zugriffsschutz, Backups und Datenschutz verantwortlich.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2 flex-wrap">
        <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/test">
            <button class="btn btn-primary" type="submit">Verbindung testen</button>
        </form>
        <a class="btn btn-outline-primary" href="/admin/schema/<?= (int) $connection['id'] ?>">Schema anzeigen</a>
        <a class="btn btn-outline-secondary" href="/admin/connections/<?= (int) $connection['id'] ?>/edit">Bearbeiten</a>
        <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/delete" onsubmit="return confirm('Diesen Eintrag wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
            <input type="hidden" name="confirm_delete" value="1">
            <button class="btn btn-danger" type="submit">Löschen</button>
        </form>
    </div>
<?php endif; ?>
