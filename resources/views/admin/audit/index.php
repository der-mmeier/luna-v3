<?php /** @var array<int, array<string, mixed>> $entries */ ?>
<div class="mb-4">
    <h1 class="h3 mb-1">Audit</h1>
    <p class="text-body-secondary mb-0">Letzte 100 Audit-Eintraege ohne Secrets.</p>
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Zeit</th><th>Action</th><th>Entity</th><th>ID</th><th>Message</th><th>Context</th></tr></thead>
            <tbody>
            <?php foreach ($entries ?? [] as $entry): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $entry['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) $entry['action'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) ($entry['entity_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($entry['entity_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($entry['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars(substr((string) ($entry['context_json'] ?? ''), 0, 300), ENT_QUOTES, 'UTF-8') ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($entries ?? []) === []): ?>
                <tr><td colspan="6" class="text-body-secondary">Keine Audit-Eintraege gefunden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
