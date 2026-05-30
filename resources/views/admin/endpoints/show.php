<?php
/** @var array<string, mixed>|null $endpoint */
/** @var bool $hasSecret */
/** @var string $staticResponse */
/** @var array<string, mixed>|null $exportStatus */
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
            <p class="text-body-secondary mb-0"><code>/api/endpoints/<?= htmlspecialchars((string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/export">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="local_env" value="1" id="local_env_export">
                    <label class="form-check-label small" for="local_env_export">Lokale Test-.env schreiben</label>
                </div>
                <button class="btn btn-outline-success" type="submit">Runtime exportieren</button>
            </form>
            <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/test">
                <button class="btn btn-outline-primary" type="submit">Preview ausführen</button>
            </form>
            <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/delete" onsubmit="return confirm('Diesen Eintrag wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
                <input type="hidden" name="confirm_delete" value="1">
                <button class="btn btn-outline-danger" type="submit">Löschen</button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Status</div><strong><?= htmlspecialchars((string) $endpoint['status'], ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
        <div class="col-md-3"><div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Secret-Modus</div><strong><?= htmlspecialchars((string) ($endpoint['secret_mode'] ?? 'none'), ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
        <div class="col-md-3"><div class="card admin-card"><div class="card-body"><div class="small text-body-secondary">Methode</div><strong><?= htmlspecialchars((string) $endpoint['method'], ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
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

    <div class="card admin-card mb-4">
        <div class="card-header">Letzter Runtime-Export</div>
        <div class="card-body">
            <?php if (! empty(($exportStatus ?? [])['exists'])): ?>
                <div class="mb-2">
                    <div class="small text-body-secondary">Exportpfad</div>
                    <code><?= htmlspecialchars((string) $exportStatus['path'], ENT_QUOTES, 'UTF-8') ?></code>
                </div>
                <div>
                    <div class="small text-body-secondary">exported_at</div>
                    <code><?= htmlspecialchars((string) (($exportStatus['exported_at'] ?? '') !== '' ? $exportStatus['exported_at'] : '-'), ENT_QUOTES, 'UTF-8') ?></code>
                </div>
                <?php if (! empty($exportStatus['archive_exists'])): ?>
                    <div class="mt-3">
                        <a class="btn btn-outline-primary" href="/admin/endpoints/<?= (int) $endpoint['id'] ?>/export/download">ZIP herunterladen</a>
                        <div class="form-text"><?= htmlspecialchars((string) ($exportStatus['archive_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-body-secondary">Noch kein Runtime-Export vorhanden.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card admin-card">
        <div class="card-header">curl Beispiel</div>
        <pre class="p-3 mb-0"><?php if (($endpoint['secret_mode'] ?? 'none') === 'required'): ?>curl -X GET -H "X-Luna-Endpoint-Secret: <endpoint-secret>" "<?= htmlspecialchars('/api/endpoints/' . (string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?>"
<?php else: ?>curl -X GET "<?= htmlspecialchars('/api/endpoints/' . (string) $endpoint['endpoint_key'], ENT_QUOTES, 'UTF-8') ?>"
<?php endif; ?></pre>
    </div>
<?php endif; ?>
