<?php

/** @var string $appName */
/** @var int $workspaceCount */
/** @var int $connectionCount */
/** @var int $mappingCount */
/** @var int $jobCount */
?>
<div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-body-secondary mb-0"><?= htmlspecialchars($appName ?? 'Luna V3', ENT_QUOTES, 'UTF-8') ?> Workbench fuer Integrations- und Mapping-Projekte.</p>
    </div>
    <span class="badge text-bg-secondary align-self-start align-self-lg-center">Workbench</span>
</div>

<div class="row g-3">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card admin-card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary">Workspaces</h2>
                <p class="display-6 mb-0"><?= (int) ($workspaceCount ?? 0) ?></p>
                <p class="small text-body-secondary mb-0">Verwaltete Projektbereiche</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card admin-card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary">Connections</h2>
                <p class="display-6 mb-0"><?= (int) ($connectionCount ?? 0) ?></p>
                <p class="small text-body-secondary mb-0">Gespeicherte Verbindungen</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card admin-card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary">Mappings</h2>
                <p class="display-6 mb-0"><?= (int) ($mappingCount ?? 0) ?></p>
                <p class="small text-body-secondary mb-0">Angelegte Mapping Sets</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card admin-card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary">Jobs</h2>
                <p class="display-6 mb-0"><?= (int) ($jobCount ?? 0) ?></p>
                <p class="small text-body-secondary mb-0">Konfigurierte Jobs</p>
            </div>
        </div>
    </div>
</div>
