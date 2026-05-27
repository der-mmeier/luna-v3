<?php /** @var array<int, array<string, mixed>> $endpoints */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Endpoints</h1>
        <p class="text-body-secondary mb-0">Einfache API-Endpunkte für Integrationsprojekte.</p>
    </div>
    <a class="btn btn-primary" href="/admin/endpoints/create">Endpoint anlegen</a>
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
            <tr>
                <th>Name</th>
                <th>Endpoint Key</th>
                <th>API-Pfad</th>
                <th>Method</th>
                <th>Visibility</th>
                <th>Status</th>
                <th>Source Type</th>
                <th class="text-end">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($endpoints ?? [] as $endpoint): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $endpoint['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><code>/api/e/<?= htmlspecialchars((string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) $endpoint['method'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $endpoint['visibility'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $endpoint['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $endpoint['source_type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/admin/endpoints/<?= (int) $endpoint['id'] ?>">Öffnen</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($endpoints ?? []) === []): ?>
                <tr><td colspan="8" class="text-body-secondary">Noch keine Endpoints angelegt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
