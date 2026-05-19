<?php

/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, array<string, mixed>> $connections */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
$values = $values ?? [];
?>
<div class="mb-4">
    <h1 class="h3 mb-1"><?= isset($values['id']) ? 'Mapping bearbeiten' : 'Mapping anlegen' ?></h1>
    <p class="text-body-secondary mb-0">Dieses Mapping wird nur gespeichert und validiert, nicht ausgeführt.</p>
</div>

<?php if (($errors ?? []) !== []): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $message): ?>
                <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form class="card admin-card" method="post" action="<?= isset($values['id']) ? '/admin/mappings/' . (int) $values['id'] : '/admin/mappings' ?>">
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label" for="workspace_id">Workspace</label>
            <select class="form-select" id="workspace_id" name="workspace_id">
                <option value="">Kein Workspace</option>
                <?php foreach ($workspaces ?? [] as $workspace): ?>
                    <option value="<?= (int) $workspace['id'] ?>" <?= (string) ($values['workspace_id'] ?? '') === (string) $workspace['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <?php foreach (['draft', 'active', 'archived'] as $status): ?>
                    <option value="<?= $status ?>" <?= ($values['status'] ?? 'draft') === $status ? 'selected' : '' ?>><?= $status ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="name">Name</label>
            <input class="form-control" id="name" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Beschreibung</label>
            <textarea class="form-control" id="description" name="description" rows="2"><?= htmlspecialchars((string) ($values['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="source_connection_id">Source Connection</label>
            <select class="form-select" id="source_connection_id" name="source_connection_id" data-role="source-connection">
                <option value="">Bitte wählen</option>
                <?php foreach ($connections ?? [] as $connection): ?>
                    <option value="<?= (int) $connection['id'] ?>" <?= (string) ($values['source_connection_id'] ?? '') === (string) $connection['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $connection['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="source_table">Source Table</label>
            <input class="form-control mb-2" type="search" data-role="source-table-filter" placeholder="Tabellen filtern">
            <select class="form-select" id="source_table" name="source_table" data-role="source-table" data-current="<?= htmlspecialchars((string) ($values['source_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <?php if (! empty($values['source_table'])): ?>
                    <option value="<?= htmlspecialchars((string) $values['source_table'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $values['source_table'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php else: ?>
                    <option value="">Bitte wählen</option>
                <?php endif; ?>
            </select>
            <div class="form-text mapping-table-status" data-role="source-table-status"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="target_connection_id">Target Connection</label>
            <select class="form-select" id="target_connection_id" name="target_connection_id" data-role="target-connection">
                <option value="">Bitte wählen</option>
                <?php foreach ($connections ?? [] as $connection): ?>
                    <option value="<?= (int) $connection['id'] ?>" <?= (string) ($values['target_connection_id'] ?? '') === (string) $connection['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $connection['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="target_table">Target Table</label>
            <input class="form-control mb-2" type="search" data-role="target-table-filter" placeholder="Tabellen filtern">
            <select class="form-select" id="target_table" name="target_table" data-role="target-table" data-current="<?= htmlspecialchars((string) ($values['target_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <?php if (! empty($values['target_table'])): ?>
                    <option value="<?= htmlspecialchars((string) $values['target_table'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $values['target_table'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php else: ?>
                    <option value="">Bitte wählen</option>
                <?php endif; ?>
            </select>
            <div class="form-text mapping-table-status" data-role="target-table-status"></div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-outline-secondary" href="/admin/mappings">Abbrechen</a>
    </div>
</form>
