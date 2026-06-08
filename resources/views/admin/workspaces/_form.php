<?php
/** @var array<string, mixed> $values */
/** @var array<int, array<string, mixed>> $transferDbConnections */
$values = $values ?? [];
$transferDbConnections = $transferDbConnections ?? [];
$field = static fn (string $key, string $default = ''): string => htmlspecialchars((string) ($values[$key] ?? $default), ENT_QUOTES, 'UTF-8');
$selected = static fn (string $status): string => (string) ($values['status'] ?? 'active') === $status ? ' selected' : '';
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Name</label>
        <input class="form-control" id="name" name="name" value="<?= $field('name') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="slug">Slug</label>
        <input class="form-control" id="slug" name="slug" value="<?= $field('slug') ?>">
        <div class="form-text">Leer lassen, um automatisch aus dem Namen zu erzeugen.</div>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Beschreibung</label>
        <textarea class="form-control" id="description" name="description" rows="4"><?= $field('description') ?></textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
            <option value="active"<?= $selected('active') ?>>active</option>
            <option value="archived"<?= $selected('archived') ?>>archived</option>
            <option value="disabled"<?= $selected('disabled') ?>>disabled</option>
        </select>
    </div>
    <div class="col-md-8">
        <label class="form-label" for="transfer_db_connection_id">Default TransferDB Connection</label>
        <select class="form-select" id="transfer_db_connection_id" name="transfer_db_connection_id">
            <option value="">Keine TransferDB konfiguriert</option>
            <?php foreach ($transferDbConnections as $connection): ?>
                <option value="<?= (int) $connection['id'] ?>" <?= (string) ($values['transfer_db_connection_id'] ?? '') === (string) $connection['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $connection['name'] . ' (' . (string) $connection['database_name'] . ')', ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Die TransferDB kann produktive Payloads und personenbezogene Daten enthalten. Der Betreiber ist für Absicherung, Zugriffsschutz, Backups und Datenschutz verantwortlich.</div>
    </div>
</div>
