<?php
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<int, array<string, mixed>> $mappings */
/** @var array<int, array<string, mixed>> $jobs */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
$values = $values ?? [];
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Endpoint anlegen</h1>
    <p class="text-body-secondary mb-0">Private Endpoints speichern Secrets verschluesselt und zeigen sie nie an.</p>
</div>

<?php foreach ($errors ?? [] as $error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>

<form method="post" action="/admin/endpoints" class="card admin-card">
    <div class="card-body">
        <?php include __DIR__ . '/_form.php'; ?>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Speichern</button>
            <a class="btn btn-outline-secondary" href="/admin/endpoints">Abbrechen</a>
        </div>
    </div>
</form>
