<?php

/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, array<string, mixed>> $connections */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">WooCommerce-Anbindung anlegen</h1>
        <p class="text-body-secondary mb-0">Die Anbindung nutzt eine vorhandene Luna-Connection und liest produktive Bestelldaten nur aus HPOS.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/woocommerce">Zurück</a>
</div>

<?php foreach ($errors ?? [] as $error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>

<form method="post" action="/admin/woocommerce" class="card admin-card">
    <div class="card-header">Anbindung</div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Workspace</label>
            <select class="form-select" name="workspace_id">
                <option value="">Bitte wählen</option>
                <?php foreach ($workspaces ?? [] as $workspace): ?>
                    <option value="<?= (int) $workspace['id'] ?>" <?= (int) ($values['workspace_id'] ?? 0) === (int) $workspace['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">WooCommerce-Connection</label>
            <select class="form-select" name="connection_id" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($connections ?? [] as $connection): ?>
                    <option value="<?= (int) $connection['id'] ?>" <?= (int) ($values['connection_id'] ?? 0) === (int) $connection['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $connection['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <div class="alert alert-warning mb-0">
                HPOS Data Caching wird in Luna v2.0.0 nicht als produktiver Datenpfad verwendet. Luna liest Bestellungen für Transfers direkt über die WooCommerce-Connection aus HPOS.
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <button class="btn btn-primary" type="submit">Anbindung speichern</button>
    </div>
</form>
