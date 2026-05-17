<?php

/** @var array<string, mixed>|null $connection */
/** @var array{type: string, message: string}|null $alert */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Connection Details</h1>
        <p class="text-body-secondary mb-0">Secrets werden nicht angezeigt.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/connections">Zurück</a>
</div>

<?php if (! empty($alert)): ?>
    <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
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
                <dt class="col-sm-3">Read-only</dt>
                <dd class="col-sm-9"><?= (int) $connection['read_only'] === 1 ? 'Ja' : 'Nein' ?></dd>
                <dt class="col-sm-3">Aktiv</dt>
                <dd class="col-sm-9"><?= (int) $connection['is_active'] === 1 ? 'Ja' : 'Nein' ?></dd>
            </dl>
        </div>
    </div>
    <div class="d-flex gap-2">
        <form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/test">
            <button class="btn btn-primary" type="submit">Verbindung testen</button>
        </form>
        <a class="btn btn-outline-primary" href="/admin/schema/<?= (int) $connection['id'] ?>">Schema anzeigen</a>
    </div>
<?php endif; ?>
