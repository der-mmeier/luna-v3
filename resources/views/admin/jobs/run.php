<?php /** @var array<string, mixed>|null $run */ /** @var array<int, array<string, mixed>> $logs */ ?>
<?php if ($run === null): ?>
    <div class="alert alert-warning">Run nicht gefunden.</div>
<?php else: ?>
    <?php
    $summary = json_decode((string) ($run['summary_json'] ?? '{}'), true) ?: [];
    $status = (string) $run['status'];
    $badge = match ($status) {
        'success' => 'success',
        'failed' => 'danger',
        'partial' => 'warning',
        'running' => 'primary',
        default => 'secondary',
    };
    ?>
    <div class="mb-4">
        <h1 class="h3 mb-1">Job Run #<?= (int) $run['id'] ?></h1>
        <p class="text-body-secondary mb-0">Keine Secrets in Logs oder Summary.</p>
    </div>

    <?php if ($status === 'failed'): ?>
        <div class="alert alert-danger">
            <strong>Run fehlgeschlagen.</strong>
            <?php if (! empty($run['error_message'])): ?>
                <?= htmlspecialchars((string) $run['error_message'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Status</div><span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span></div></div>
        </div>
        <div class="col-md-2">
            <div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Dry Run</div><strong><?= (int) $run['dry_run'] === 1 ? 'Ja' : 'Nein' ?></strong></div></div>
        </div>
        <?php foreach (['source_count' => 'Source', 'transformed_count' => 'Transformiert', 'written_count' => 'Geschrieben', 'skipped_count' => 'Uebersprungen', 'error_count' => 'Fehler'] as $key => $label): ?>
            <div class="col-md-2">
                <div class="card admin-card"><div class="card-body"><div class="small text-body-secondary"><?= $label ?></div><strong><?= (int) $run[$key] ?></strong></div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (! empty($summary['preview_rows'])): ?>
        <div class="card admin-card mb-4">
            <div class="card-header">Dry Run Preview</div>
            <pre class="p-3 mb-0"><?= htmlspecialchars(json_encode($summary['preview_rows'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
    <?php endif; ?>

    <div class="card admin-card mb-4">
        <div class="card-header">Summary</div>
        <pre class="p-3 mb-0"><?= htmlspecialchars(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
    </div>

    <div class="card admin-card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Level</th><th>Message</th><th>Context</th></tr></thead>
                <tbody>
                    <?php foreach ($logs ?? [] as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $log['level'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $log['message'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><code><?= htmlspecialchars((string) ($log['context_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
