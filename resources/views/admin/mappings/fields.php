<?php

/** @var array<string, mixed>|null $mapping */
/** @var array<int, array<string, mixed>> $fields */
/** @var array<int, array<string, mixed>> $sourceColumns */
/** @var array<int, array<string, mixed>> $sourceSamples */
/** @var array<int, array<string, mixed>> $lookupColumns */
/** @var array<int, array<string, mixed>> $lookupSamples */
/** @var array<int, array<string, mixed>> $lookupTestResults */
/** @var array<string, mixed> $previewValues */
/** @var array<int, array<string, mixed>> $connections */
/** @var string|null $columnWarning */
/** @var string|null $lookupWarning */
$short = static function (mixed $value): string {
    $text = (string) $value;
    return strlen($text) > 32 ? substr($text, 0, 29) . '...' : $text;
};

$selected = static function (mixed $value, mixed $current): string {
    return (string) $value === (string) $current ? ' selected' : '';
};

$renderPreviewTable = static function (array $rows, array $columns = []) use ($short): void {
    $names = array_map(static fn (array $column): string => (string) ($column['column_name'] ?? ''), $columns);

    if ($names === [] && $rows !== []) {
        $names = array_keys($rows[0]);
    }
    ?>
    <?php if ($rows === []): ?>
        <div class="text-body-secondary">Keine Beispielwerte verfügbar.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <?php foreach ($names as $name): ?>
                        <th><code><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></code></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($names as $name): ?>
                            <?php $value = $row[$name] ?? ''; ?>
                            <td class="luna-truncate-cell" title="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($short($value), ENT_QUOTES, 'UTF-8') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php
};

$targetCounts = [];

foreach ($fields ?? [] as $field) {
    $targetColumn = (string) ($field['target_column'] ?? '');

    if ($targetColumn !== '') {
        $targetCounts[$targetColumn] = ($targetCounts[$targetColumn] ?? 0) + 1;
    }
}
?>
<?php if ($mapping === null): ?>
    <div class="alert alert-warning">Mapping nicht gefunden.</div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Lookup-Feldzuordnung</h1>
            <p class="text-body-secondary mb-0"><?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/mappings/<?= (int) $mapping['id'] ?>">Zurück</a>
    </div>

    <div class="alert alert-info">
        <strong>Lookup-Regel testen:</strong>
        Die Primary Source liefert den variablen Wert, das Lookup-Key-Template baut daraus den Suchschlüssel, und die Lookup-Tabelle liefert den Ergebniswert.
    </div>

    <?php if (! empty($columnWarning)): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($columnWarning, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form class="card admin-card mb-4" method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>/fields">
        <div class="card-body row g-3">
            <div class="col-12">
                <h2 class="h5 mb-1">Source-Filter und Lookup-Regel</h2>
                <p class="text-body-secondary mb-0">Wähle echte Spalten aus Primary Source und Lookup Source. Nur das Lookup-Key-Template wird frei editiert.</p>
            </div>
            <div class="col-md-4">
                <label class="form-label">Primary Source Column</label>
                <select class="form-select" name="source_column" <?= ($sourceColumns ?? []) === [] ? 'disabled' : '' ?>>
                    <option value="">Bitte wählen</option>
                    <?php foreach ($sourceColumns ?? [] as $column): ?>
                        <?php $columnName = (string) $column['column_name']; ?>
                        <option value="<?= htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') ?>"<?= $selected($columnName, $previewValues['source_column'] ?? '') ?>><?= htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Source-Filter Spalte</label>
                <select class="form-select" name="source_filter_column">
                    <option value="">Keine Filterung</option>
                    <?php foreach ($sourceColumns ?? [] as $column): ?>
                        <?php $columnName = (string) $column['column_name']; ?>
                        <option value="<?= htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') ?>"<?= $selected($columnName, $previewValues['source_filter_column'] ?? '') ?>><?= htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Source-Filter Operator</label>
                <select class="form-select" name="source_filter_operator">
                    <?php foreach (['is_numeric_gt_zero' => 'numerisch > 0', 'none' => 'Keine Filterung', 'gt' => '>', 'gte' => '>=', 'eq' => '='] as $operator => $label): ?>
                        <option value="<?= htmlspecialchars($operator, ENT_QUOTES, 'UTF-8') ?>"<?= $selected($operator, $previewValues['source_filter_operator'] ?? '') ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Source-Filter Wert</label>
                <input class="form-control" name="source_filter_value" value="<?= htmlspecialchars((string) ($previewValues['source_filter_value'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Lookup Connection</label>
                <select class="form-select" name="lookup_connection_id" data-role="lookup-connection">
                    <option value="">Bitte wählen</option>
                    <?php foreach ($connections ?? [] as $connection): ?>
                        <option value="<?= (int) $connection['id'] ?>"<?= $selected((int) $connection['id'], $previewValues['lookup_connection_id'] ?? 0) ?>><?= htmlspecialchars((string) $connection['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lookup Tabelle</label>
                <input class="form-control mb-2" type="search" data-role="lookup-table-filter" placeholder="Tabellen filtern">
                <select class="form-select" name="lookup_table" data-role="lookup-table" data-current="<?= htmlspecialchars((string) ($previewValues['lookup_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <option value="<?= htmlspecialchars((string) ($previewValues['lookup_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) (($previewValues['lookup_table'] ?? '') !== '' ? $previewValues['lookup_table'] : 'Bitte wählen'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
                <div class="form-text mapping-table-status" data-role="lookup-table-status"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lookup Key Column</label>
                <select class="form-select" name="lookup_key_column" data-role="lookup-key-column" data-current="<?= htmlspecialchars((string) ($previewValues['lookup_key_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <option value="<?= htmlspecialchars((string) ($previewValues['lookup_key_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) (($previewValues['lookup_key_column'] ?? '') !== '' ? $previewValues['lookup_key_column'] : 'Bitte wählen'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lookup Value Column</label>
                <select class="form-select" name="lookup_value_column" data-role="lookup-value-column" data-current="<?= htmlspecialchars((string) ($previewValues['lookup_value_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <option value="<?= htmlspecialchars((string) ($previewValues['lookup_value_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) (($previewValues['lookup_value_column'] ?? '') !== '' ? $previewValues['lookup_value_column'] : 'Bitte wählen'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lookup Key Template</label>
                <input class="form-control" name="lookup_key_template" value="<?= htmlspecialchars((string) ($previewValues['lookup_key_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="price_group_{{priceGroup}}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Lookup Match Mode</label>
                <select class="form-select" name="lookup_match_mode">
                    <?php foreach (['exact' => 'Exakt', 'prefix' => 'Prefix', 'suffix' => 'Suffix', 'contains' => 'Enthält', 'like' => 'LIKE-Pattern'] as $mode => $label): ?>
                        <option value="<?= $mode ?>"<?= $selected($mode, $previewValues['lookup_match_mode'] ?? 'exact') ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Result Handling</label>
                <select class="form-select" name="lookup_result_mode">
                    <?php foreach (['first' => 'Ersten Wert verwenden', 'list' => 'Liste aller Werte', 'count' => 'Treffer zählen', 'sum' => 'Werte summieren', 'min' => 'Kleinsten Wert verwenden', 'max' => 'Größten Wert verwenden', 'key_value_map' => 'Key-Value Objekt'] as $mode => $label): ?>
                        <option value="<?= $mode ?>"<?= $selected($mode, $previewValues['lookup_result_mode'] ?? 'first') ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Result Key Column</label>
                <select class="form-select" name="lookup_result_key_column" data-role="lookup-result-key-column" data-current="<?= htmlspecialchars((string) ($previewValues['lookup_result_key_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <option value="<?= htmlspecialchars((string) ($previewValues['lookup_result_key_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) (($previewValues['lookup_result_key_column'] ?? '') !== '' ? $previewValues['lookup_result_key_column'] : 'Bitte wählen'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
                <div class="form-text">Nur für Result Handling Key-Value Objekt: Spalte aus der Lookup-Tabelle für die Objekt-Keys.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Result Key Transform</label>
                <select class="form-select" name="lookup_result_key_transform">
                    <?php foreach (['none' => 'Keine Änderung', 'remove_prefix' => 'Prefix entfernen'] as $transform => $label): ?>
                        <option value="<?= $transform ?>"<?= $selected($transform, $previewValues['lookup_result_key_transform'] ?? 'none') ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Result Key Prefix Template</label>
                <input class="form-control" name="lookup_result_key_prefix_template" value="<?= htmlspecialchars((string) ($previewValues['lookup_result_key_prefix_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="{{customfield_asf_model}}">
                <div class="form-text">Nur bei Prefix entfernen: gerendertes Prefix, das vom Result Key entfernt wird.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Result Limit</label>
                <input class="form-control" name="lookup_result_limit" value="<?= htmlspecialchars((string) ($previewValues['lookup_result_limit'] ?? 100), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Missing Behavior</label>
                <select class="form-select" name="missing_behavior">
                    <?php foreach (['nullable', 'error', 'warning', 'fallback'] as $behavior): ?>
                        <option value="<?= $behavior ?>"<?= $selected($behavior, $previewValues['missing_behavior'] ?? 'nullable') ?>><?= $behavior ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Ausgabe-Alias</label>
                <input class="form-control" name="target_column" value="<?= htmlspecialchars((string) ($previewValues['target_column'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="resolved_value">
                <div class="form-text">Optionaler Name für das Ergebnis dieser Lookup-Regel in der Preview/Transfer-Struktur.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Fallback Value</label>
                <input class="form-control" name="fallback_value" value="<?= htmlspecialchars((string) ($previewValues['fallback_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sortierung</label>
                <input class="form-control" name="sort_order" value="<?= htmlspecialchars((string) ($previewValues['sort_order'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_required" value="1" id="is_required">
                    <label class="form-check-label" for="is_required">Required</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Notizen</label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
            </div>
            <input type="hidden" name="transform_type" value="lookup_value">
            <input type="hidden" name="source_json_path" value="">
            <input type="hidden" name="default_value" value="">
            <div class="col-12">
                <div class="alert alert-secondary mb-0">
                    <strong>Beispiel:</strong> <code>priceGroup = 2</code> und Template <code>price_group_{{priceGroup}}</code> ergeben den Lookup-Key <code>price_group_2</code>. Gesucht wird dann in <code>zweipunkt_setting.name</code>, gelesen wird aus <code>zweipunkt_setting.value</code>.
                    <hr>
                    <div><strong>Prefix-Beispiel:</strong> Source <code>customfield_asf_model = S001</code>, Template <code>{{customfield_asf_model}}</code>, Match Mode Prefix sucht <code>product_code LIKE S001%</code>.</div>
                    <div><strong>LIKE-Beispiel:</strong> Template <code>{{customfield_asf_model}}%</code>, Match Mode LIKE-Pattern sucht <code>product_code LIKE S001%</code>.</div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            <button class="btn btn-outline-secondary" type="submit" formmethod="get">Datensätze zeigen</button>
            <button class="btn btn-outline-primary" type="submit" name="lookup_test" value="1" formmethod="get">Lookup testen</button>
            <button class="btn btn-primary" type="submit">Feldzuordnung speichern</button>
        </div>
    </form>

    <div class="card admin-card mb-4">
        <div class="card-header">Primary Source Preview</div>
        <div class="card-body">
            <p class="text-body-secondary">Maximal 10 Beispielzeilen aus der Primary Source mit dem gewählten Source-Filter.</p>
            <?php $renderPreviewTable($sourceSamples ?? [], $sourceColumns ?? []); ?>
        </div>
    </div>

    <div class="card admin-card mb-4">
        <div class="card-header">Lookup Source Preview</div>
        <div class="card-body">
            <?php if (! empty($lookupWarning)): ?>
                <div class="alert alert-warning"><?= htmlspecialchars($lookupWarning, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <p class="text-body-secondary">Maximal 10 Beispielzeilen aus der gewählten Lookup-Tabelle.</p>
            <?php $renderPreviewTable($lookupSamples ?? [], $lookupColumns ?? []); ?>
        </div>
    </div>

    <div class="card admin-card mb-4">
        <div class="card-header">Lookup-Test</div>
        <div class="card-body">
            <?php if (empty($lookupTestResults)): ?>
                <div class="text-body-secondary">Noch kein Lookup-Test ausgeführt oder die Regel ist unvollständig.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Source-Wert</th>
                            <th>Template</th>
                            <th>Gerendertes Pattern</th>
                            <th>Match Mode</th>
                            <th>Suche</th>
                            <th>Wert aus</th>
                            <th>Treffer</th>
                            <th>Result Handling</th>
                            <th>Result Key</th>
                            <th>Status</th>
                            <th>Ergebnis</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lookupTestResults as $result): ?>
                            <tr>
                                <td><code><?= htmlspecialchars((string) $result['source_column'], ENT_QUOTES, 'UTF-8') ?> = <?= htmlspecialchars((string) $result['source_value'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><code><?= htmlspecialchars((string) $result['template'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><code><?= htmlspecialchars((string) ($result['rendered_pattern'] ?? $result['rendered_key']), ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= htmlspecialchars((string) ($result['lookup_match_mode'] ?? 'exact'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><code><?= htmlspecialchars((string) $result['lookup_table'], ENT_QUOTES, 'UTF-8') ?>.<?= htmlspecialchars((string) $result['lookup_key_column'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><code><?= htmlspecialchars((string) $result['lookup_table'], ENT_QUOTES, 'UTF-8') ?>.<?= htmlspecialchars((string) $result['lookup_value_column'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= (int) ($result['match_count'] ?? 0) ?></td>
                                <td><?= htmlspecialchars((string) ($result['lookup_result_mode'] ?? 'first'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if (($result['lookup_result_key_column'] ?? '') !== ''): ?>
                                        <code><?= htmlspecialchars((string) $result['lookup_table'], ENT_QUOTES, 'UTF-8') ?>.<?= htmlspecialchars((string) $result['lookup_result_key_column'], ENT_QUOTES, 'UTF-8') ?></code>
                                        <div class="text-body-secondary small"><?= htmlspecialchars((string) ($result['lookup_result_key_transform'] ?? 'none'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (($result['rendered_result_key_prefix'] ?? '') !== ''): ?>
                                        <div class="text-body-secondary small">Gerendertes Prefix: <code><?= htmlspecialchars((string) $result['rendered_result_key_prefix'], ENT_QUOTES, 'UTF-8') ?></code></div>
                                    <?php endif; ?>
                                    <?php if (($result['result_warnings'] ?? []) !== []): ?>
                                        <div class="text-body-secondary small"><?= htmlspecialchars(implode(', ', (array) $result['result_warnings']), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars((string) $result['status'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (($result['message'] ?? '') !== ''): ?>
                                        <div class="text-body-secondary small"><?= htmlspecialchars((string) $result['message'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars(is_array($result['found_value'] ?? null) ? json_encode($result['found_value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) ($result['found_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card admin-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Primary Source</th><th>Ausgabe-Alias</th><th>Type</th><th>Aktionen</th></tr></thead>
                <tbody>
                <?php foreach ($fields ?? [] as $field): ?>
                    <?php
                    $targetColumn = (string) ($field['target_column'] ?? '');
                    $isDuplicateTarget = $targetColumn !== '' && ($targetCounts[$targetColumn] ?? 0) > 1;
                    ?>
                    <tr class="<?= $isDuplicateTarget ? 'table-warning' : '' ?>">
                        <td><code><?= htmlspecialchars((string) ($field['source_column'] ?? $field['source_json_path'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td>
                            <code><?= htmlspecialchars($targetColumn, ENT_QUOTES, 'UTF-8') ?></code>
                            <?php if ($isDuplicateTarget): ?>
                                <span class="badge text-bg-warning ms-2">mehrfach</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string) $field['transform_type'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if (($field['transform_type'] ?? '') === 'enum_map'): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/mappings/<?= (int) $mapping['id'] ?>/fields/<?= (int) $field['id'] ?>/value-rules">Regeln</a>
                                <?php else: ?>
                                    <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true" title="Value Rules sind nur für enum_map relevant">Regeln</span>
                                <?php endif; ?>
                                <form method="post" action="/admin/mappings/<?= (int) $mapping['id'] ?>/fields/<?= (int) $field['id'] ?>/delete">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
