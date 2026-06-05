<?php
/** @var list<string> $errors */
/** @var list<array<string, mixed>> $workspaces */
/** @var array<string, mixed> $values */
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Deployment Target anlegen</h1>
    <p class="text-body-secondary mb-0">Ein Target beschreibt öffentliche Ziel-URLs und enthält keine Zugangsdaten.</p>
</div>

<?php if (($errors ?? []) !== []): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form class="card admin-card" method="post" action="/admin/deployment-targets">
    <div class="card-body">
        <?php include __DIR__ . '/_form.php'; ?>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Speichern</button>
            <a class="btn btn-outline-secondary" href="/admin/deployment-targets">Zurück</a>
        </div>
    </div>
</form>
