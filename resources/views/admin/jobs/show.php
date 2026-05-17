<?php /** @var array<string, mixed>|null $job */ /** @var array<int, array<string, mixed>> $runs */ ?>
<?php if ($job === null): ?><div class="alert alert-warning">Job nicht gefunden.</div><?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1"><?= htmlspecialchars((string) $job['name'], ENT_QUOTES, 'UTF-8') ?></h1><p class="text-body-secondary mb-0">Echte Transfers schreiben in die konfigurierte Target Connection.</p></div><a class="btn btn-outline-secondary" href="/admin/jobs">Zurück</a></div>
<div class="card admin-card mb-3"><div class="card-body"><dl class="row mb-0">
<dt class="col-sm-3">Mapping</dt><dd class="col-sm-9"><?= htmlspecialchars((string) ($job['mapping_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
<dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= htmlspecialchars((string) $job['status'], ENT_QUOTES, 'UTF-8') ?></dd>
<dt class="col-sm-3">Dry Run Default</dt><dd class="col-sm-9"><?= (int) $job['dry_run_default'] === 1 ? 'Ja' : 'Nein' ?></dd>
<dt class="col-sm-3">Report</dt><dd class="col-sm-9"><?= (int) $job['report_enabled'] === 1 ? 'Aktiv' : 'Inaktiv' ?></dd>
</dl></div></div>
<div class="d-flex gap-2 mb-4"><form method="post" action="/admin/jobs/<?= (int) $job['id'] ?>/dry-run"><button class="btn btn-success">Dry Run starten</button></form><form method="post" action="/admin/jobs/<?= (int) $job['id'] ?>/run"><input type="hidden" name="confirm" value="run"><button class="btn btn-danger">Echten Transfer starten</button></form><a class="btn btn-outline-primary" href="/admin/jobs/<?= (int) $job['id'] ?>/runs">Runs anzeigen</a></div>
<div class="card admin-card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>ID</th><th>Status</th><th>Dry Run</th><th>Created</th></tr></thead><tbody><?php foreach ($runs ?? [] as $run): ?><tr><td><a href="/admin/jobs/runs/<?= (int) $run['id'] ?>">#<?= (int) $run['id'] ?></a></td><td><?= htmlspecialchars((string) $run['status'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $run['dry_run'] === 1 ? 'Ja' : 'Nein' ?></td><td><?= htmlspecialchars((string) $run['created_at'], ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
