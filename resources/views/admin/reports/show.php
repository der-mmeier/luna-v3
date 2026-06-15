<?php
/** @var array<string, mixed>|null $report */
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
/** @var object|null $result */
?>
<?php if ($report === null): ?>
    <div class="alert alert-warning">Report nicht gefunden.</div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="h3 mb-1"><?= htmlspecialchars((string) $report['subject'], ENT_QUOTES, 'UTF-8') ?></h1><p class="text-body-secondary mb-0">Report bearbeiten oder versenden.</p></div>
        <a class="btn btn-outline-secondary" href="/admin/reports">Zurück</a>
    </div>
    <?php foreach ($errors ?? [] as $error): ?><div class="alert alert-danger"><?= nl2br(htmlspecialchars($error, ENT_QUOTES, 'UTF-8')) ?></div><?php endforeach; ?>
    <?php if ($result !== null): ?><div class="alert alert-info"><?= htmlspecialchars($result->message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>" class="card admin-card mb-4">
        <div class="card-header">Report-Konfiguration</div>
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">Betreff</label><input class="form-control" name="subject" value="<?= htmlspecialchars((string) ($values['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
            <div class="col-md-3"><label class="form-label">Workspace</label><select class="form-select" name="workspace_id"><option value="">Kein Workspace</option><?php foreach ($workspaces ?? [] as $workspace): ?><option value="<?= (int) $workspace['id'] ?>" <?= (int) ($values['workspace_id'] ?? 0) === (int) $workspace['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['created', 'draft', 'sent', 'archived'] as $status): ?><option value="<?= $status ?>" <?= (string) ($values['status'] ?? 'created') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Typ</label><input class="form-control" name="type" value="<?= htmlspecialchars((string) ($values['type'] ?? 'manual'), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-md-8"><label class="form-label">Empfänger</label><input class="form-control" name="recipients" value="<?= htmlspecialchars((string) ($values['recipients'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-12"><label class="form-label">Inhalt</label><textarea class="form-control" name="body" rows="8" required><?= htmlspecialchars((string) ($values['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary" type="submit">Änderungen speichern</button></div>
    </form>
    <div class="d-flex gap-2">
        <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/send"><button class="btn btn-outline-primary" type="submit">E-Mail senden</button></form>
        <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/delete" onsubmit="return confirm('Diesen Report wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');"><input type="hidden" name="confirm_delete" value="1"><button class="btn btn-danger" type="submit">Report löschen</button></form>
    </div>
<?php endif; ?>
