<?php /** @var array<string, mixed> $endpoint */ /** @var array<string, mixed> $result */ ?>
<div class="mb-4">
    <h1 class="h3 mb-1">Endpoint Preview</h1>
    <p class="text-body-secondary mb-0"><code>/api/endpoints/<?= htmlspecialchars((string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?></code></p>
</div>

<div class="alert alert-info">Die Preview nutzt dasselbe JSON-Format wie die Public Runtime. Secrets werden nicht angezeigt.</div>

<div class="card admin-card">
    <div class="card-header">JSON-Ausgabe</div>
    <pre class="p-3 mb-0"><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
</div>
