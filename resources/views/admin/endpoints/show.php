<?php
/** @var array<string, mixed>|null $endpoint */
/** @var bool $hasSecret */
/** @var string $staticResponse */
/** @var array<string, string>|null $alert */
?>
<?php if ($endpoint === null): ?>
    <div class="alert alert-warning">Endpoint nicht gefunden.</div>
<?php else: ?>
    <?php if ($alert !== null): ?>
        <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars((string) $endpoint['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-body-secondary mb-0"><code>/api/e/<?= htmlspecialchars((string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/test">
                <button class="btn btn-outline-primary" type="submit">Endpoint testen</button>
            </form>
            <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/delete" onsubmit="return confirm('Endpoint wirklich loeschen?');">
                <button class="btn btn-outline-danger" type="submit">Loeschen</button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Status</div><strong><?= htmlspecialchars((string) $endpoint['status'], ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
        <div class="col-md-3"><div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Visibility</div><strong><?= htmlspecialchars((string) $endpoint['visibility'], ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
        <div class="col-md-3"><div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Method</div><strong><?= htmlspecialchars((string) $endpoint['method'], ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
        <div class="col-md-3"><div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Secret</div><strong><?= $hasSecret ? 'gesetzt' : 'nicht gesetzt' ?></strong></div></div></div>
    </div>

    <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>" class="card admin-card mb-4">
        <div class="card-body">
            <?php
            $values = $endpoint + ['static_response' => $staticResponse];
            $workspaces = $workspaces ?? [];
            $mappings = $mappings ?? [];
            $jobs = $jobs ?? [];
            include __DIR__ . '/_form.php';
            ?>
            <button class="btn btn-primary" type="submit">Aktualisieren</button>
        </div>
    </form>

    <div class="card admin-card">
        <div class="card-header">curl Beispiele</div>
        <pre class="p-3 mb-0"><?php if ((string) $endpoint['visibility'] === 'public'): ?>curl -X <?= htmlspecialchars((string) $endpoint['method'], ENT_QUOTES, 'UTF-8') ?> "<?= htmlspecialchars('/api/e/' . (string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?>"
<?php else: ?>curl -X <?= htmlspecialchars((string) $endpoint['method'], ENT_QUOTES, 'UTF-8') ?> -H "X-Luna-Endpoint-Secret: <endpoint-secret>" "<?= htmlspecialchars('/api/e/' . (string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?>"
<?php endif; ?></pre>
    </div>
<?php endif; ?>
