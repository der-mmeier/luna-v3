<?php /** @var array<int, array<string, mixed>> $endpoints */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Endpoints</h1>
        <p class="text-body-secondary mb-0">Öffentliche JSON-Endpunkte für Mapping-Ergebnisse.</p>
    </div>
    <a class="btn btn-primary" href="/admin/endpoints/create">Endpoint anlegen</a>
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Runtime-URL</th>
                <th>Methode</th>
                <th>Secret-Modus</th>
                <th>Status</th>
                <th>Mapping</th>
                <th class="text-end">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($endpoints ?? [] as $endpoint): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $endpoint['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><code>/api/endpoints/<?= htmlspecialchars((string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) $endpoint['method'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($endpoint['secret_mode'] ?? 'none'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $endpoint['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($endpoint['mapping_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a class="btn btn-sm btn-outline-secondary" href="/admin/endpoints/<?= (int) $endpoint['id'] ?>">Öffnen</a>
                            <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/delete" onsubmit="return confirm('Diesen Eintrag wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
                                <input type="hidden" name="confirm_delete" value="1">
                                <button class="btn btn-sm btn-danger" type="submit">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($endpoints ?? []) === []): ?>
                <tr><td colspan="8" class="text-body-secondary">Noch keine Endpoints angelegt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
