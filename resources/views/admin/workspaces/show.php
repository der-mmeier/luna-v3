<?php
/** @var array<string, mixed>|null $workspace */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
?>
<?php if ($workspace === null): ?>
    <div class="alert alert-warning">Workspace nicht gefunden.</div>
<?php else: ?>
    <div class="mb-4">
        <h1 class="h3 mb-1"><?= htmlspecialchars((string) $workspace['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-body-secondary mb-0"><code><?= htmlspecialchars((string) $workspace['slug'], ENT_QUOTES, 'UTF-8') ?></code></p>
    </div>

    <?php foreach ($errors ?? [] as $error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <form class="card admin-card" method="post" action="/admin/workspaces/<?= (int) $workspace['id'] ?>">
        <div class="card-body">
            <?php include __DIR__ . '/_form.php'; ?>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary" type="submit">Aktualisieren</button>
            <a class="btn btn-outline-secondary" href="/admin/workspaces">Zurueck</a>
        </div>
    </form>
<?php endif; ?>
