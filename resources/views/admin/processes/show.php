<?php
/** @var array<string, mixed> $process */
/** @var array<int, array<string, mixed>> $steps */
/** @var array<int, array<string, mixed>> $runs */
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, array<string, mixed>> $mappings */
/** @var array<string, mixed> $values */
/** @var array<string, mixed> $stepValues */
/** @var array<int, string> $errors */
/** @var array<int, string> $stepErrors */
/** @var array<string, string>|null $alert */

$mappingNames = [];
foreach ($mappings ?? [] as $mapping) {
    $mappingNames[(int) $mapping['id']] = (string) $mapping['name'];
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars((string) $process['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-body-secondary mb-0">Process Runtime Foundation: manuelle und CLI-Ausführung ohne Scheduler oder Webhook-Runtime.</p>
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
                    <form method="post" action="/admin/processes/<?= (int) $process['id'] ?>/steps/<?= (int) $step['id'] ?>">
                        <td><input class="form-control form-control-sm" name="position" value="<?= (int) $step['position'] ?>"></td>
                        <td><input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string) $step['name'], ENT_QUOTES, 'UTF-8') ?>"></td>
                        <td>
                            <select class="form-select form-select-sm" name="step_type">
                                <option value="mapping_run" <?= (string) $step['step_type'] === 'mapping_run' ? 'selected' : '' ?>>Mapping ausführen</option>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="reference_id">
                                <?php foreach ($mappings ?? [] as $mapping): ?>
                                    <option value="<?= (int) $mapping['id'] ?>" <?= (int) ($step['reference_id'] ?? 0) === (int) $mapping['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <textarea class="form-control form-control-sm mt-2" name="config_json" rows="2" placeholder="Optionale JSON-Konfiguration"><?= htmlspecialchars((string) ($step['config_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </td>
                        <td class="text-center"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" <?= (int) $step['is_enabled'] === 1 ? 'checked' : '' ?>></td>
                        <td class="text-center"><input class="form-check-input" type="checkbox" name="continue_on_error" value="1" <?= (int) $step['continue_on_error'] === 1 ? 'checked' : '' ?>></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-sm btn-primary" type="submit">Speichern</button>
                    </form>
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
                <option value="mapping_run">Mapping ausführen</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Mapping</label>
            <select class="form-select" name="reference_id" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($mappings ?? [] as $mapping): ?>
                    <option value="<?= (int) $mapping['id'] ?>" <?= (int) ($stepValues['reference_id'] ?? 0) === (int) $mapping['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $mapping['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
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
                    <td><?= htmlspecialchars((string) $run['trigger_type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['started_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['finished_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['duration_ms'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> ms</td>
                    <td><?= htmlspecialchars((string) ($run['error_message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($runs ?? []) === []): ?>
                <tr><td colspan="8" class="text-body-secondary">Noch keine Läufe vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
