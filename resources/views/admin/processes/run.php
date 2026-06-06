<?php
/** @var array<string, mixed> $run */
/** @var array<int, array<string, mixed>> $logs */

$status = (string) $run['status'];
$badge = match ($status) {
    'success' => 'success',
    'failed' => 'danger',
    'running' => 'primary',
    'cancelled' => 'warning',
    default => 'secondary',
};
$context = json_decode((string) ($run['context_json'] ?? '{}'), true) ?: [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Prozesslauf #<?= (int) $run['id'] ?></h1>
        <p class="text-body-secondary mb-0"><?= htmlspecialchars((string) ($run['process_name'] ?? 'Prozess'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/processes/<?= (int) $run['process_id'] ?>">Zurück zum Prozess</a>
</div>

<?php if ($status === 'failed' && ! empty($run['error_message'])): ?>
    <div class="alert alert-danger">
        <strong>Lauf fehlgeschlagen.</strong>
        <?= htmlspecialchars((string) $run['error_message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Status</div><span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span></div></div>
    </div>
    <div class="col-md-2">
        <div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Modus</div><strong><?= htmlspecialchars((string) $run['mode'], ENT_QUOTES, 'UTF-8') ?></strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Trigger</div><strong><?= htmlspecialchars((string) $run['trigger_type'], ENT_QUOTES, 'UTF-8') ?></strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Start</div><strong><?= htmlspecialchars((string) ($run['started_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Ende</div><strong><?= htmlspecialchars((string) ($run['finished_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Dauer</div><strong><?= htmlspecialchars((string) ($run['duration_ms'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> ms</strong></div></div>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Kontext</div>
    <pre class="p-3 mb-0"><?= htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
</div>

<div class="card admin-card">
    <div class="card-header">Logs</div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Level</th>
                    <th>Nachricht</th>
                    <th>Kontext</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs ?? [] as $log): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $log['level'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $log['message'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) ($log['context_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($logs ?? []) === []): ?>
                <tr><td colspan="4" class="text-body-secondary">Keine Logs vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
