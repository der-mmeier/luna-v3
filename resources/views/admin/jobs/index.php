<?php /** @var array<int, array<string, mixed>> $jobs */ /** @var array{type: string, message: string}|null $alert */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Jobs</h1><p class="text-body-secondary mb-0">Manuelle Mapping-Transfers mit Dry-Run-Standard.</p></div>
    <a class="btn btn-primary" href="/admin/jobs/create">Job anlegen</a>
</div>
<?php if (($alert ?? null) !== null): ?>
    <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<div class="card admin-card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Name</th><th>Workspace</th><th>Mapping</th><th>Status</th><th>Dry Run</th><th>Report</th><th>Letzter Lauf</th><th>Aktionen</th></tr></thead>
    <tbody>
    <?php foreach ($jobs ?? [] as $job): ?>
        <tr>
            <td><?= htmlspecialchars((string) $job['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($job['workspace_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($job['mapping_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string) $job['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><?= (int) $job['dry_run_default'] === 1 ? 'Ja' : 'Nein' ?></td>
            <td><?= (int) $job['report_enabled'] === 1 ? 'Ja' : 'Nein' ?></td>
            <td><?= htmlspecialchars((string) ($job['last_run_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-primary" href="/admin/jobs/<?= (int) $job['id'] ?>">Details</a>
                    <form method="post" action="/admin/jobs/<?= (int) $job['id'] ?>/delete" onsubmit="return confirm('Diesen Job wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
                        <input type="hidden" name="confirm_delete" value="1">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (($jobs ?? []) === []): ?><tr><td colspan="8" class="text-body-secondary">Noch keine Jobs angelegt.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
