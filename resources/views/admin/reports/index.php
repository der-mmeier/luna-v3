<?php
/** @var array<int, array<string, mixed>> $reports */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Reports</h1>
        <p class="text-body-secondary mb-0">Report-Konfigurationen verwalten.</p>
    </div>
    <a class="btn btn-primary" href="/admin/reports/create">Report anlegen</a>
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Key</th>
                <th>Typ</th>
                <th>Workspace</th>
                <th>Status</th>
                <th>Aktualisiert</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($reports ?? [] as $report): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($report['name'] ?? $report['subject'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) ($report['report_key'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) ($report['type'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($report['workspace_name'] ?? 'Global'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($report['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($report['updated_at'] ?? $report['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/admin/reports/<?= (int) $report['id'] ?>">Öffnen</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($reports ?? []) === []): ?>
                <tr><td colspan="7" class="text-body-secondary">Keine Reports vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
