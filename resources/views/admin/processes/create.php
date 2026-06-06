<?php
/** @var array<int, array<string, mixed>> $workspaces */
/** @var array<string, mixed> $values */
/** @var array<int, string> $errors */
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Prozess anlegen</h1>
    <p class="text-body-secondary mb-0">Ein Prozess bündelt ausführbare Schritte und protokolliert jeden Lauf.</p>
</div>

<?php if (($errors ?? []) !== []): ?>
    <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form class="card admin-card" method="post" action="/admin/processes">
    <?php include __DIR__ . '/_form.php'; ?>
</form>
