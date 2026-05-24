<?php

/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
/** @var string|null $error */
/** @var array<int, string> $roles */
/** @var array<int, string> $drivers */
/** @var string $formAction */
/** @var string $heading */
/** @var string $lead */
$values = $values ?? [];
$roles = $roles ?? ['source', 'transfer', 'target'];
$drivers = $drivers ?? ['mysql', 'mariadb'];
$formAction = $formAction ?? '/admin/connections';
$heading = $heading ?? 'Connection anlegen';
$lead = $lead ?? 'Fuer 1.2.0 werden mehrere MySQL/MariaDB-Verbindungen pro Workspace vorbereitet. Quellverbindungen sind standardmaessig read-only.';
?>
<div class="mb-4">
    <h1 class="h3 mb-1"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="text-body-secondary mb-0"><?= htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') ?></p>
</div>

<?php if (! empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (($errors ?? []) !== []): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $message): ?>
                <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form class="card admin-card" method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label" for="workspace_id">Workspace optional</label>
            <select class="form-select" id="workspace_id" name="workspace_id">
                <option value="">Kein Workspace</option>
                <?php foreach ($workspaces ?? [] as $workspace): ?>
                    <option value="<?= (int) $workspace['id'] ?>" <?= (string) ($values['workspace_id'] ?? '') === (string) $workspace['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="name">Name</label>
            <input class="form-control" id="name" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="type">Typ</label>
            <select class="form-select" id="type" name="type">
                <?php foreach ($roles as $type): ?>
                    <option value="<?= $type ?>" <?= ($values['type'] ?? 'source') === $type ? 'selected' : '' ?>><?= $type ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="driver">Driver</label>
            <select class="form-select" id="driver" name="driver">
                <?php foreach ($drivers as $driver): ?>
                    <option value="<?= $driver ?>" <?= ($values['driver'] ?? 'mysql') === $driver ? 'selected' : '' ?>><?= $driver ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="host">Host</label>
            <input class="form-control" id="host" name="host" value="<?= htmlspecialchars((string) ($values['host'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="port">Port</label>
            <input class="form-control" id="port" name="port" value="<?= htmlspecialchars((string) ($values['port'] ?? '3306'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="database_name">Datenbankname</label>
            <input class="form-control" id="database_name" name="database_name" value="<?= htmlspecialchars((string) ($values['database_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="username">Benutzername</label>
            <input class="form-control" id="username" name="username" value="<?= htmlspecialchars((string) ($values['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="password">Passwort</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="new-password">
            <div class="form-text">Leer lassen, um das bestehende Passwort unveraendert zu behalten.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="charset">Charset</label>
            <input class="form-control" id="charset" name="charset" value="<?= htmlspecialchars((string) ($values['charset'] ?? 'utf8mb4'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-8 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="read_only" name="read_only" value="1" <?= (int) ($values['read_only'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="read_only">Read-only verwenden</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="notes">Notizen</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars((string) ($values['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-outline-secondary" href="/admin/connections">Abbrechen</a>
    </div>
</form>


