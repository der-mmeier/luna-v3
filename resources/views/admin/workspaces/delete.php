<?php
/** @var array<string, mixed>|null $workspace */
/** @var Luna\Repository\DeleteCheckResult|null $check */
/** @var string|null $blockerMessage */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Workspace löschen</h1>
        <p class="text-body-secondary mb-0">Löschungen werden vorab auf abhängige Ressourcen geprüft.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= $workspace === null ? '/admin/workspaces' : '/admin/workspaces/' . (int) $workspace['id'] ?>">Zurück</a>
</div>

<?php if ($workspace === null): ?>
    <div class="alert alert-warning">Workspace nicht gefunden oder bereits gelöscht.</div>
<?php else: ?>
    <div class="card admin-card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9"><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Slug</dt>
                <dd class="col-sm-9"><code><?= htmlspecialchars((string) $workspace['slug'], ENT_QUOTES, 'UTF-8') ?></code></dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9"><?= htmlspecialchars((string) ($workspace['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
        </div>
    </div>

    <?php if ($check !== null && ! $check->allowed): ?>
        <div class="alert alert-danger">
            <?= nl2br(htmlspecialchars((string) $blockerMessage, ENT_QUOTES, 'UTF-8')) ?>
        </div>
        <p class="text-body-secondary">Bitte löschen, deaktivieren oder verschieben Sie die genannten Ressourcen zuerst.</p>
        <a class="btn btn-outline-secondary" href="/admin/workspaces/<?= (int) $workspace['id'] ?>">Zurück zum Workspace</a>
    <?php else: ?>
        <div class="alert alert-warning">Dieser Workspace kann gelöscht werden. Diese Aktion kann nicht rückgängig gemacht werden.</div>
        <form method="post" action="/admin/workspaces/<?= (int) $workspace['id'] ?>/delete" onsubmit="return confirm('Diesen Workspace wirklich löschen?');">
            <input type="hidden" name="confirm_delete" value="1">
            <button class="btn btn-danger" type="submit">Workspace endgültig löschen</button>
            <a class="btn btn-outline-secondary" href="/admin/workspaces/<?= (int) $workspace['id'] ?>">Abbrechen</a>
        </form>
    <?php endif; ?>
<?php endif; ?>
