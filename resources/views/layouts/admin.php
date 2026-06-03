<?php

/** @var string $active */
/** @var string $appName */
/** @var string $content */
/** @var string $title */

$active = $active ?? 'dashboard';
$appName = $appName ?? 'Luna V3';
$title = $title ?? 'Dashboard';
$theme = isset($_COOKIE['luna_theme']) && $_COOKIE['luna_theme'] === 'light' ? 'light' : 'dark';

$navItems = [
    'dashboard' => ['/admin', 'Dashboard'],
    'workspaces' => ['/admin/workspaces', 'Workspaces'],
    'connections' => ['/admin/connections', 'Connections'],
    'schema' => ['/admin/schema', 'Schema Explorer'],
    'mappings' => ['/admin/mappings', 'Mappings'],
    'datasets' => ['/admin/datasets', 'Datasets'],
    'transfers' => ['/admin/transfers', 'Transfers'],
    'woocommerce' => ['/admin/woocommerce', 'WooCommerce'],
    'jobs' => ['/admin/jobs', 'Jobs'],
    'reports' => ['/admin/reports', 'Reports'],
    'endpoints' => ['/admin/endpoints', 'Endpoints'],
    'audit' => ['/admin/audit', 'Audit'],
];
?>
<!doctype html>
<html lang="de" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg luna-navbar border-bottom">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="/admin"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavigation" aria-controls="adminNavigation" aria-expanded="false" aria-label="Navigation umschalten">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavigation">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php foreach ($navItems as $key => [$href, $label]): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="luna-theme-switch d-flex align-items-center gap-2">
                <span class="navbar-text small">Theme</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Theme umschalten">
                    <button class="btn btn-outline-light" type="button" data-theme-choice="dark">Dark</button>
                    <button class="btn btn-outline-light" type="button" data-theme-choice="light">Light</button>
                </div>
            </div>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">
    <div class="admin-shell mx-auto">
        <?= $content ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/theme.js"></script>
<script src="/assets/js/mapping-tables.js"></script>
</body>
</html>
