<?php /** @var array<string, mixed> $endpoint */ /** @var array<string, mixed> $result */ ?>
<div class="mb-4">
    <h1 class="h3 mb-1">Endpoint testen</h1>
    <p class="text-body-secondary mb-0"><code>/api/e/<?= htmlspecialchars((string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?></code></p>
</div>

<?php if ((string) $endpoint['visibility'] === 'private'): ?>
    <div class="alert alert-warning">Private Endpoints benötigen ein Secret. Das Secret wird nicht angezeigt und nicht in diesem Test offengelegt.</div>
<?php endif; ?>

<div class="card admin-card">
    <div class="card-header">Ergebnis</div>
    <pre class="p-3 mb-0"><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
</div>
