<?php /** @var array<string, mixed>|null $report */ /** @var object|null $result */ ?>
<?php if ($report === null): ?><div class="alert alert-warning">Report nicht gefunden.</div><?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1"><?= htmlspecialchars((string) $report['subject'], ENT_QUOTES, 'UTF-8') ?></h1><p class="text-body-secondary mb-0">Status: <?= htmlspecialchars((string) $report['status'], ENT_QUOTES, 'UTF-8') ?></p></div><a class="btn btn-outline-secondary" href="/admin/reports">Zurück</a></div>
<?php if ($result !== null): ?><div class="alert alert-info"><?= htmlspecialchars($result->message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card admin-card mb-3"><pre class="p-3 mb-0"><?= htmlspecialchars((string) $report['body'], ENT_QUOTES, 'UTF-8') ?></pre></div>
<form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/send"><button class="btn btn-primary" type="submit">E-Mail senden</button></form>
<?php endif; ?>
