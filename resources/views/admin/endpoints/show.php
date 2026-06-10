<?php
/** @var array<string, mixed>|null $endpoint */
/** @var bool $hasSecret */
/** @var string $staticResponse */
/** @var array<string, mixed>|null $exportStatus */
/** @var array<string, mixed>|null $contractExportStatus */
/** @var list<array<string, mixed>> $contractTargets */
/** @var string|null $currentEndpointUrl */
/** @var array<string, mixed> $mappingSummary */
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
        <div class="d-flex flex-wrap gap-2">
            <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/export">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="local_env" value="1" id="local_env_export">
                    <label class="form-check-label small" for="local_env_export">Lokale Test-.env schreiben</label>
                </div>
                <button class="btn btn-outline-success" type="submit">Runtime exportieren</button>
            </form>
            <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/contract-export">
                <select class="form-select form-select-sm mb-1" name="target_environment" aria-label="Deployment Target für Exportpaket">
                    <option value="">Ohne Target</option>
                    <?php foreach (($contractTargets ?? []) as $target): ?>
                        <option value="<?= htmlspecialchars((string) $target['environment'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) $target['environment'] . ' - ' . (string) $target['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-info" type="submit">Exportpaket erzeugen</button>
            </form>
            <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/test">
                <button class="btn btn-outline-primary" type="submit">Preview ausführen</button>
            </form>
            <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/transferdb-snapshot">
                <button class="btn btn-outline-secondary" type="submit">Snapshot in TransferDB speichern</button>
            </form>
            <?php if (! empty($endpoint['schema_id'])): ?>
                <form method="post" action="/admin/endpoints/<?= (int) $endpoint['id'] ?>/validate-schema">
                    <button class="btn btn-outline-primary" type="submit">Schema validieren</button>
                </form>
            <?php endif; ?>
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

    <div class="card admin-card mb-4">
        <div class="card-header">Endpoint-URLs und Deployment Targets</div>
        <div class="card-body">
            <p class="text-body-secondary">Die aktuelle URL basiert auf dem laufenden Request. Für produktive Dokumentation und Exporte sollten Deployment Targets gepflegt werden.</p>
            <div class="mb-3">
                <div class="small text-body-secondary">Aktuelle URL</div>
                <code class="luna-breakable"><?= htmlspecialchars((string) ($currentEndpointUrl ?? ('/api/endpoints/' . (string) $endpoint['endpoint_key'])), ENT_QUOTES, 'UTF-8') ?></code>
            </div>

            <?php if (($contractTargets ?? []) === []): ?>
                <div class="alert alert-secondary mb-0">Kein Deployment Target konfiguriert.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Target</th>
                            <th>Environment</th>
                            <th>Endpoint-URL</th>
                            <th>Default</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($contractTargets as $target): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $target['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><code><?= htmlspecialchars((string) $target['environment'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><code class="luna-breakable"><?= htmlspecialchars((string) $target['endpoint_url'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= ! empty($target['is_default']) ? 'ja' : 'nein' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($contractExportStatus ?? null) !== null): ?>
        <div class="card admin-card mb-4">
            <div class="card-header">Letztes Exportpaket</div>
            <div class="card-body">
                <div class="small text-body-secondary">Exportpfad</div>
                <code class="luna-breakable"><?= htmlspecialchars((string) ($contractExportStatus['target_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                <div class="form-text">Das Exportpaket enthält keine Zugangsdaten. Connections werden nur als Referenzen exportiert.</div>
            </div>
        </div>
    <?php endif; ?>

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
        <div class="card-header">Mapping-Zusammenfassung für den Export</div>
        <div class="card-body">
            <?php $summary = $mappingSummary ?? ['mapping' => null, 'filters' => [], 'fields' => [], 'message' => 'Für diesen Endpoint ist kein Mapping ausgewählt.']; ?>
            <?php if (($summary['message'] ?? null) !== null): ?>
                <div class="text-body-secondary"><?= htmlspecialchars((string) $summary['message'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>
                <?php $summaryMapping = $summary['mapping'] ?? []; ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="small text-body-secondary">Mapping Set</div>
                        <strong><?= htmlspecialchars((string) ($summaryMapping['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="col-md-2">
                        <div class="small text-body-secondary">Status</div>
                        <code><?= htmlspecialchars((string) ($summaryMapping['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-body-secondary">Source Connection</div>
                        <?= htmlspecialchars((string) ($summaryMapping['source_connection_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-body-secondary">Source Table</div>
                        <code><?= htmlspecialchars((string) ($summaryMapping['source_table'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                </div>

                <h3 class="h6">Source Filter</h3>
                <?php if (($summary['filters'] ?? []) === []): ?>
                    <p class="text-body-secondary">Keine Source Filter konfiguriert.</p>
                <?php else: ?>
                    <ul class="mb-4">
                        <?php foreach ($summary['filters'] as $filter): ?>
                            <li>
                                <code><?= htmlspecialchars((string) ($filter['source_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                                <?= htmlspecialchars((string) ($filter['operator_label'] ?? $filter['operator'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (($filter['filter_value'] ?? '') !== ''): ?>
                                    <code><?= htmlspecialchars((string) $filter['filter_value'], ENT_QUOTES, 'UTF-8') ?></code>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h3 class="h6">Field Mappings</h3>
                <?php if (($summary['fields'] ?? []) === []): ?>
                    <p class="text-body-secondary">Keine Feldzuordnungen konfiguriert.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Order</th>
                                <th>Output Field</th>
                                <th>Source Column</th>
                                <th>Transform</th>
                                <th>Konfiguration</th>
                                <th>Value Rules</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($summary['fields'] as $field): ?>
                                <tr>
                                    <td><code><?= (int) ($field['sort_order'] ?? 0) ?></code></td>
                                    <td><code><?= htmlspecialchars((string) ($field['target_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td><code><?= htmlspecialchars((string) ($field['source_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td><?= htmlspecialchars((string) ($field['transform_label'] ?? $field['transform_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if (($field['lookup_key_template'] ?? '') !== ''): ?>
                                            <div>Template: <code><?= htmlspecialchars((string) $field['lookup_key_template'], ENT_QUOTES, 'UTF-8') ?></code></div>
                                        <?php endif; ?>
                                        <?php if (($field['lookup_connection'] ?? '') !== ''): ?>
                                            <div>Lookup: <?= htmlspecialchars((string) $field['lookup_connection'], ENT_QUOTES, 'UTF-8') ?> <code><?= htmlspecialchars((string) ($field['lookup_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></div>
                                        <?php endif; ?>
                                        <?php if (($field['lookup_key_column'] ?? '') !== '' || ($field['lookup_value_column'] ?? '') !== ''): ?>
                                            <div>Key/Value: <code><?= htmlspecialchars((string) ($field['lookup_key_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code> → <code><?= htmlspecialchars((string) ($field['lookup_value_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></div>
                                        <?php endif; ?>
                                        <?php if (($field['default_value'] ?? '') !== ''): ?>
                                            <div>Default: <code><?= htmlspecialchars((string) $field['default_value'], ENT_QUOTES, 'UTF-8') ?></code></div>
                                        <?php endif; ?>
                                        <?php if (($field['missing_behavior'] ?? '') !== ''): ?>
                                            <div class="text-body-secondary small">Missing Behavior: <?= htmlspecialchars((string) $field['missing_behavior'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($field['value_rules'] ?? []) === []): ?>
                                            <span class="text-body-secondary">-</span>
                                        <?php else: ?>
                                            <?php foreach ($field['value_rules'] as $rule): ?>
                                                <div><code><?= htmlspecialchars((string) ($rule['source_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code> → <code><?= htmlspecialchars((string) ($rule['target_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

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
