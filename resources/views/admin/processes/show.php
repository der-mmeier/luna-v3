<?php
/** @var array<string, mixed> $process */
/** @var array<int, array<string, mixed>> $steps */
/** @var array<int, array<string, mixed>> $runs */
/** @var array<int, array<string, mixed>> $triggers */
/** @var array<int, array<string, mixed>> $targetActions */
/** @var array<int, array<string, mixed>> $schemas */
/** @var array<int, string> $triggerTypes */
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, array<string, mixed>> $mappings */
/** @var array<string, mixed> $values */
/** @var array<string, mixed> $stepValues */
/** @var array<string, mixed> $triggerValues */
/** @var array<int, string> $errors */
/** @var array<int, string> $stepErrors */
/** @var array<int, string> $triggerErrors */
/** @var array<int, string|null> $triggerUrls */
/** @var array<string, mixed>|null $triggerTarget */
/** @var array<string, string>|null $alert */

$mappingNames = [];
foreach ($mappings ?? [] as $mapping) {
    $mappingNames[(int) $mapping['id']] = (string) $mapping['name'];
}

$actionNames = [];
$actionTypes = [];
foreach ($targetActions ?? [] as $targetAction) {
    $actionNames[(int) $targetAction['id']] = (string) $targetAction['name'];
    $actionTypes[(int) $targetAction['id']] = (string) $targetAction['action_type'];
}
$schemaNames = [];
foreach ($schemas ?? [] as $schema) {
    $schemaNames[(int) $schema['id']] = (string) $schema['schema_key'] . ' v' . (string) $schema['version'];
}

$triggerLabels = [
    'manual' => 'Manual',
    'cli' => 'CLI',
    'api' => 'API',
    'schedule' => 'Schedule',
    'webhook' => 'Webhook',
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars((string) $process['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-body-secondary mb-0">Process Runtime mit manueller, CLI-, API-, Schedule- und Webhook-Trigger-Grundlage.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/processes">Zurück</a>
</div>

<?php if ($alert !== null): ?>
    <div class="alert alert-<?= htmlspecialchars((string) $alert['type'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars((string) $alert['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <?php if (($errors ?? []) !== []): ?>
            <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form class="card admin-card" method="post" action="/admin/processes/<?= (int) $process['id'] ?>">
            <div class="card-header">Prozess bearbeiten</div>
            <?php include __DIR__ . '/_form.php'; ?>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card admin-card mb-3">
            <div class="card-header">Ausführung</div>
            <div class="card-body">
                <p class="text-body-secondary">Nur aktive Prozesse können gestartet werden. Dry-Run nutzt vorhandene Preview-/Dry-Run-Mechaniken der Schritte.</p>
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="/admin/processes/<?= (int) $process['id'] ?>/run">
                        <input type="hidden" name="mode" value="run">
                        <button class="btn btn-success" type="submit" <?= (string) $process['status'] !== 'active' ? 'disabled' : '' ?>>Ausführen</button>
                    </form>
                    <form method="post" action="/admin/processes/<?= (int) $process['id'] ?>/run">
                        <input type="hidden" name="mode" value="dry_run">
                        <button class="btn btn-outline-success" type="submit" <?= (string) $process['status'] !== 'active' ? 'disabled' : '' ?>>Dry-Run starten</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card admin-card">
            <div class="card-header">Technische Daten</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">ID</dt><dd class="col-sm-8">#<?= (int) $process['id'] ?></dd>
                    <dt class="col-sm-4">Key</dt><dd class="col-sm-8"><code><?= htmlspecialchars((string) $process['process_key'], ENT_QUOTES, 'UTF-8') ?></code></dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= htmlspecialchars((string) $process['status'], ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-4">Modus</dt><dd class="col-sm-8"><?= htmlspecialchars((string) $process['default_mode'], ENT_QUOTES, 'UTF-8') ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Trigger</span>
        <?php if ($triggerTarget === null): ?>
            <span class="small text-warning">Kein Deployment Target für diesen Workspace gesetzt. URL-Vorschau nicht verfügbar.</span>
        <?php else: ?>
            <span class="small text-body-secondary">URL-Vorschau über <?= htmlspecialchars((string) ($triggerTarget['name'] ?? 'Deployment Target'), ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-body-secondary">Trigger starten bestehende Prozesse. Fachliche Verarbeitung bleibt im Prozess oder in späteren Adaptern.</p>
        <?php if (($triggerErrors ?? []) !== []): ?>
            <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $triggerErrors), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Typ</th>
                    <th>Aktiv</th>
                    <th>Trigger Key</th>
                    <th>Konfiguration</th>
                    <th>URL / Befehl</th>
                    <th>Letzter Start</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($triggers ?? [] as $trigger): ?>
                <?php $triggerType = (string) $trigger['trigger_type']; ?>
                <tr>
                    <td>
                        <form id="trigger-update-<?= (int) $trigger['id'] ?>" method="post" action="/admin/processes/<?= (int) $process['id'] ?>/triggers/<?= (int) $trigger['id'] ?>">
                            <input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string) $trigger['name'], ENT_QUOTES, 'UTF-8') ?>">
                    </td>
                    <td>
                            <select class="form-select form-select-sm" name="trigger_type">
                                <?php foreach ($triggerTypes ?? [] as $type): ?>
                                    <option value="<?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?>" <?= $triggerType === (string) $type ? 'selected' : '' ?>><?= htmlspecialchars($triggerLabels[(string) $type] ?? (string) $type, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                    </td>
                    <td class="text-center">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ! empty($trigger['is_active']) ? 'checked' : '' ?>>
                    </td>
                    <td>
                            <input class="form-control form-control-sm" name="trigger_key" value="<?= htmlspecialchars((string) $trigger['trigger_key'], ENT_QUOTES, 'UTF-8') ?>">
                    </td>
                    <td>
                            <textarea class="form-control form-control-sm" name="config_json" rows="2"><?= htmlspecialchars((string) ($trigger['config_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            <input class="form-control form-control-sm mt-2" name="secret" value="" placeholder="Neues Secret setzen (optional)">
                            <?php if (in_array($triggerType, ['api', 'webhook'], true)): ?>
                                <div class="small text-body-secondary mt-1">Secret wird nur als Hash gespeichert. Header: <code>X-Luna-Trigger-Secret</code></div>
                            <?php endif; ?>
                            <?php if ($triggerType === 'schedule'): ?>
                                <div class="small text-body-secondary mt-1">Zeitplan-Trigger werden in v2.4.0 nur konfiguriert. Eine produktive Scheduler-Runtime folgt später.</div>
                            <?php endif; ?>
                        </form>
                    </td>
                    <td class="small">
                        <?php if ($triggerType === 'cli'): ?>
                            <code>php bin/luna process:run <?= (int) $process['id'] ?> --trigger=<?= htmlspecialchars((string) $trigger['trigger_key'], ENT_QUOTES, 'UTF-8') ?></code>
                        <?php elseif (in_array($triggerType, ['api', 'webhook'], true)): ?>
                            <?php if (! empty($triggerUrls[(int) $trigger['id']])): ?>
                                <code><?= htmlspecialchars((string) $triggerUrls[(int) $trigger['id']], ENT_QUOTES, 'UTF-8') ?></code>
                            <?php else: ?>
                                <span class="text-body-secondary">URL-Vorschau nicht verfügbar.</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-body-secondary">Keine externe URL nötig.</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) ($trigger['last_triggered_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-primary" form="trigger-update-<?= (int) $trigger['id'] ?>" type="submit">Speichern</button>
                            <form method="post" action="/admin/processes/<?= (int) $process['id'] ?>/triggers/<?= (int) $trigger['id'] ?>/toggle">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= ! empty($trigger['is_active']) ? 'Deaktivieren' : 'Aktivieren' ?></button>
                            </form>
                            <?php if (in_array($triggerType, ['manual', 'cli'], true)): ?>
                                <form method="post" action="/admin/processes/<?= (int) $process['id'] ?>/triggers/<?= (int) $trigger['id'] ?>/run">
                                    <input type="hidden" name="mode" value="<?= htmlspecialchars((string) ($process['default_mode'] ?? 'run'), ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="btn btn-sm btn-success" type="submit" <?= empty($trigger['is_active']) || (string) $process['status'] !== 'active' ? 'disabled' : '' ?>>Über Trigger starten</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="/admin/processes/<?= (int) $process['id'] ?>/triggers/<?= (int) $trigger['id'] ?>/delete">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($triggers ?? []) === []): ?>
                <tr><td colspan="8" class="text-body-secondary">Noch keine Trigger angelegt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <form method="post" action="/admin/processes/<?= (int) $process['id'] ?>/triggers" class="card-footer row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars((string) ($triggerValues['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Typ</label>
            <select class="form-select" name="trigger_type">
                <?php foreach ($triggerTypes ?? [] as $type): ?>
                    <option value="<?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($triggerValues['trigger_type'] ?? 'manual') === (string) $type ? 'selected' : '' ?>><?= htmlspecialchars($triggerLabels[(string) $type] ?? (string) $type, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Trigger Key</label>
            <input class="form-control" name="trigger_key" value="<?= htmlspecialchars((string) ($triggerValues['trigger_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="optional">
        </div>
        <div class="col-md-3">
            <label class="form-label">Konfiguration</label>
            <textarea class="form-control" name="config_json" rows="1" placeholder='{"mode":"daily","time":"12:00","timezone":"Europe/Berlin"}'><?= htmlspecialchars((string) ($triggerValues['config_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="col-md-2">
            <label class="form-label">Secret</label>
            <input class="form-control" name="secret" value="" placeholder="optional">
        </div>
        <div class="col-md-2">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="new_trigger_active" <?= ! empty($triggerValues['is_active']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="new_trigger_active">Aktiv</label>
            </div>
        </div>
        <div class="col-md-10">
            <button class="btn btn-primary" type="submit">Trigger hinzufügen</button>
        </div>
    </form>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Schritte</div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Name</th>
                    <th>Typ</th>
                    <th>Referenz</th>
                    <th>Aktiv</th>
                    <th>Weiter bei Fehler</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($steps ?? [] as $step): ?>
                <tr>
                    <td><form id="step-update-<?= (int) $step['id'] ?>" method="post" action="/admin/processes/<?= (int) $process['id'] ?>/steps/<?= (int) $step['id'] ?>"><input class="form-control form-control-sm" name="position" value="<?= (int) $step['position'] ?>"></td>
                    <td><input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string) $step['name'], ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td>
                        <select class="form-select form-select-sm" name="step_type">
                            <option value="mapping_run" <?= (string) $step['step_type'] === 'mapping_run' ? 'selected' : '' ?>>Mapping ausführen</option>
                            <option value="target_action" <?= (string) $step['step_type'] === 'target_action' ? 'selected' : '' ?>>Target Action</option>
                            <option value="schema_validation" <?= (string) $step['step_type'] === 'schema_validation' ? 'selected' : '' ?>>Schema Validation</option>
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm" name="reference_id">
                            <optgroup label="Mappings">
                                <?php foreach ($mappings ?? [] as $mapping): ?>
                                    <option value="<?= (int) $mapping['id'] ?>" <?= (string) $step['step_type'] === 'mapping_run' && (int) ($step['reference_id'] ?? 0) === (int) $mapping['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Target Actions">
                                <?php foreach ($targetActions ?? [] as $targetAction): ?>
                                    <option value="<?= (int) $targetAction['id'] ?>" <?= (string) $step['step_type'] === 'target_action' && (int) ($step['reference_id'] ?? 0) === (int) $targetAction['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $targetAction['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $targetAction['action_type'], ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Schemas">
                                <?php foreach ($schemas ?? [] as $schema): ?>
                                    <option value="<?= (int) $schema['id'] ?>" <?= (string) $step['step_type'] === 'schema_validation' && (int) ($step['reference_id'] ?? 0) === (int) $schema['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $schema['schema_key'] . ' v' . (string) $schema['version'] . ' - ' . (string) $schema['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <?php if ((string) $step['step_type'] === 'target_action'): ?>
                            <div class="small text-body-secondary mt-1">Action-Typ: <?= htmlspecialchars($actionTypes[(int) ($step['reference_id'] ?? 0)] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ((string) $step['step_type'] === 'schema_validation'): ?>
                            <div class="small text-body-secondary mt-1">Schema: <?= htmlspecialchars($schemaNames[(int) ($step['reference_id'] ?? 0)] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <textarea class="form-control form-control-sm mt-2" name="config_json" rows="2" placeholder="Optionale JSON-Konfiguration"><?= htmlspecialchars((string) ($step['config_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" <?= (int) $step['is_enabled'] === 1 ? 'checked' : '' ?>></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" name="continue_on_error" value="1" <?= (int) $step['continue_on_error'] === 1 ? 'checked' : '' ?>></td>
                    <td>
                        </form>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-primary" form="step-update-<?= (int) $step['id'] ?>" type="submit">Speichern</button>
                            <form method="post" action="/admin/processes/<?= (int) $process['id'] ?>/steps/<?= (int) $step['id'] ?>/delete">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($steps ?? []) === []): ?>
                <tr><td colspan="7" class="text-body-secondary">Noch keine Schritte angelegt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($stepErrors ?? []) !== []): ?>
    <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $stepErrors), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<form class="card admin-card mb-4" method="post" action="/admin/processes/<?= (int) $process['id'] ?>/steps">
    <div class="card-header">Schritt hinzufügen</div>
    <div class="card-body row g-3">
        <div class="col-md-2">
            <label class="form-label">Position</label>
            <input class="form-control" name="position" value="<?= htmlspecialchars((string) ($stepValues['position'] ?? 10), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars((string) ($stepValues['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Step-Typ</label>
            <select class="form-select" name="step_type">
                <option value="mapping_run" <?= (string) ($stepValues['step_type'] ?? 'mapping_run') === 'mapping_run' ? 'selected' : '' ?>>Mapping ausführen</option>
                <option value="target_action" <?= (string) ($stepValues['step_type'] ?? '') === 'target_action' ? 'selected' : '' ?>>Target Action</option>
                <option value="schema_validation" <?= (string) ($stepValues['step_type'] ?? '') === 'schema_validation' ? 'selected' : '' ?>>Schema Validation</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Referenz</label>
            <select class="form-select" name="reference_id" required>
                <option value="">Bitte wählen</option>
                <optgroup label="Mappings">
                    <?php foreach ($mappings ?? [] as $mapping): ?>
                        <option value="<?= (int) $mapping['id'] ?>" <?= (string) ($stepValues['step_type'] ?? 'mapping_run') === 'mapping_run' && (int) ($stepValues['reference_id'] ?? 0) === (int) $mapping['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Target Actions">
                    <?php foreach ($targetActions ?? [] as $targetAction): ?>
                        <option value="<?= (int) $targetAction['id'] ?>" <?= (string) ($stepValues['step_type'] ?? '') === 'target_action' && (int) ($stepValues['reference_id'] ?? 0) === (int) $targetAction['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $targetAction['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $targetAction['action_type'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Schemas">
                    <?php foreach ($schemas ?? [] as $schema): ?>
                        <option value="<?= (int) $schema['id'] ?>" <?= (string) ($stepValues['step_type'] ?? '') === 'schema_validation' && (int) ($stepValues['reference_id'] ?? 0) === (int) $schema['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $schema['schema_key'] . ' v' . (string) $schema['version'] . ' - ' . (string) $schema['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Optionale JSON-Konfiguration</label>
            <textarea class="form-control" name="config_json" rows="2"><?= htmlspecialchars((string) ($stepValues['config_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="col-md-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="new_step_enabled" <?= ! empty($stepValues['is_enabled']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="new_step_enabled">Aktiv</label>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="continue_on_error" value="1" id="new_step_continue" <?= ! empty($stepValues['continue_on_error']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="new_step_continue">Weiter bei Fehler</label>
            </div>
        </div>
    </div>
    <div class="card-footer"><button class="btn btn-primary" type="submit">Schritt hinzufügen</button></div>
</form>

<div class="card admin-card">
    <div class="card-header">Run-Historie</div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Run-ID</th>
                    <th>Status</th>
                    <th>Modus</th>
                    <th>Trigger</th>
                    <th>Quelle</th>
                    <th>Start</th>
                    <th>Ende</th>
                    <th>Dauer</th>
                    <th>Fehler</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($runs ?? [] as $run): ?>
                <tr>
                    <td><a href="/admin/processes/runs/<?= (int) $run['id'] ?>">#<?= (int) $run['id'] ?></a></td>
                    <td><?= htmlspecialchars((string) $run['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $run['mode'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $run['trigger_type'], ENT_QUOTES, 'UTF-8') ?><?= ! empty($run['trigger_ref']) ? ' / ' . htmlspecialchars((string) $run['trigger_ref'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                    <td><?= htmlspecialchars((string) ($run['trigger_source'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['started_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['finished_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['duration_ms'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> ms</td>
                    <td><?= htmlspecialchars((string) ($run['error_message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($runs ?? []) === []): ?>
                <tr><td colspan="9" class="text-body-secondary">Noch keine Läufe vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
