<?php
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, array<string, mixed>> $mappings */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
$values = $values ?? [];
?>
<div class="mb-4"><h1 class="h3 mb-1">Job anlegen</h1><p class="text-body-secondary mb-0">Dry Run sollte aktiv bleiben, bis das Mapping geprüft wurde.</p></div>
<?php if (($errors ?? []) !== []): ?><div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form class="card admin-card" method="post" action="/admin/jobs">
<div class="card-body row g-3">
    <div class="col-md-6"><label class="form-label">Workspace</label><select class="form-select" name="workspace_id"><option value="">Kein Workspace</option><?php foreach ($workspaces ?? [] as $workspace): ?><option value="<?= (int) $workspace['id'] ?>"><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Mapping Set</label><select class="form-select" name="mapping_set_id"><?php foreach ($mappings ?? [] as $mapping): ?><option value="<?= (int) $mapping['id'] ?>"><?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
    <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option>draft</option><option>active</option><option>disabled</option></select></div>
    <div class="col-md-3"><label class="form-label">Run Mode</label><input class="form-control" name="run_mode" value="manual"></div>
    <div class="col-md-3"><label class="form-label">Transfer Mode</label><select class="form-select" name="transfer_mode"><option value="insert">insert</option><option value="upsert_draft">upsert_draft</option></select></div>
    <div class="col-md-3"><label class="form-label">Batch Size</label><input class="form-control" name="batch_size" value="<?= htmlspecialchars((string) ($values['batch_size'] ?? 100), ENT_QUOTES, 'UTF-8') ?>"></div>
    <div class="col-md-3"><label class="form-label">Row Limit</label><input class="form-control" name="row_limit"></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="dry_run_default" value="1" checked id="dry"><label class="form-check-label" for="dry">Dry Run Default</label></div></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="report_enabled" value="1" id="report"><label class="form-check-label" for="report">Report aktiv</label></div></div>
    <div class="col-md-9"><label class="form-label">Report Recipients</label><input class="form-control" name="report_recipients" placeholder="mail@example.test;team@example.test"></div>
    <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
</div>
<div class="card-footer bg-white"><button class="btn btn-primary" type="submit">Speichern</button></div>
</form>
