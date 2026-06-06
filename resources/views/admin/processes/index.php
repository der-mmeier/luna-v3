<?php /** @var array<int, array<string, mixed>> $processes */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Prozesse</h1>
        <p class="text-body-secondary mb-0">Kontrollierte Ausführungseinheiten mit Schritten, Läufen und Logs.</p>
    </div>
    <a class="btn btn-primary" href="/admin/processes/create">Prozess anlegen</a>
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Workspace</th>
                    <th>Key</th>
                    <th>Status</th>
                    <th>Schritte</th>
                    <th>Letzter Lauf</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($processes ?? [] as $process): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $process['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($process['workspace_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) $process['process_key'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string) $process['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= (int) ($process['step_count'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($process['last_run_status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="/admin/processes/<?= (int) $process['id'] ?>">Details</a>
                            <?php if ((string) $process['status'] === 'active'): ?>
                                <form method="post" action="/admin/processes/<?= (int) $process['id'] ?>/run">
                                    <input type="hidden" name="mode" value="<?= htmlspecialchars((string) ($process['default_mode'] ?? 'run'), ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="btn btn-sm btn-success" type="submit">Starten</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($processes ?? []) === []): ?>
                <tr><td colspan="7" class="text-body-secondary">Noch keine Prozesse angelegt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
