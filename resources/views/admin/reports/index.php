<?php
/** @var array<int, array<string, mixed>> $reports */
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
/** @var array{type: string, message: string}|null $alert */
?>
<div class="mb-4"><h1 class="h3 mb-1">Reports</h1><p class="text-body-secondary mb-0">Reports enthalten keine Secrets.</p></div>
<?php if (($alert ?? null) !== null): ?><div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php foreach ($errors ?? [] as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
<form method="post" action="/admin/reports" class="card admin-card mb-4">
    <div class="card-header">Report anlegen</div>
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">Betreff</label><input class="form-control" name="subject" value="<?= htmlspecialchars((string) ($values['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="col-md-3"><label class="form-label">Workspace</label><select class="form-select" name="workspace_id"><option value="">Kein Workspace</option><?php foreach ($workspaces ?? [] as $workspace): ?><option value="<?= (int) $workspace['id'] ?>" <?= (int) ($values['workspace_id'] ?? 0) === (int) $workspace['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['created', 'draft', 'archived'] as $status): ?><option value="<?= $status ?>" <?= (string) ($values['status'] ?? 'created') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Typ</label><input class="form-control" name="type" value="<?= htmlspecialchars((string) ($values['type'] ?? 'manual'), ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="col-md-8"><label class="form-label">Empfänger</label><input class="form-control" name="recipients" value="<?= htmlspecialchars((string) ($values['recipients'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="col-12"><label class="form-label">Inhalt</label><textarea class="form-control" name="body" rows="5" required><?= htmlspecialchars((string) ($values['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary" type="submit">Report anlegen</button></div>
</form>
<div class="card admin-card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Betreff</th><th>Status</th><th>Job Run</th><th>Erstellt</th><th>Gesendet</th><th>Aktionen</th></tr></thead><tbody><?php foreach ($reports ?? [] as $report): ?><tr><td><?= htmlspecialchars((string) $report['subject'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) $report['status'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($report['job_run_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) $report['created_at'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($report['sent_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="/admin/reports/<?= (int) $report['id'] ?>">Bearbeiten</a><form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/delete" onsubmit="return confirm('Diesen Report wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');"><input type="hidden" name="confirm_delete" value="1"><button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button></form></div></td></tr><?php endforeach; ?><?php if (($reports ?? []) === []): ?><tr><td colspan="6" class="text-body-secondary">Noch keine Reports vorhanden.</td></tr><?php endif; ?></tbody></table></div></div>
