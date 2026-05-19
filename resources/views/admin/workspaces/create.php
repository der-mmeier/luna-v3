<?php /** @var array<string, mixed> $values */ /** @var array<int, string> $errors */ ?>
<div class="mb-4">
    <h1 class="h3 mb-1">Workspace anlegen</h1>
    <p class="text-body-secondary mb-0">Workspaces buendeln Integrationsprojekte.</p>
</div>

<?php foreach ($errors ?? [] as $error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>

<form class="card admin-card" method="post" action="/admin/workspaces">
    <div class="card-body">
        <?php include __DIR__ . '/_form.php'; ?>
    </div>
    <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <a class="btn btn-outline-secondary" href="/admin/workspaces">Abbrechen</a>
    </div>
</form>
