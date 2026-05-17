<?php

/** @var string $appName */
?>
<div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-body-secondary mb-0"><?= htmlspecialchars($appName ?? 'Luna V3', ENT_QUOTES, 'UTF-8') ?> Workbench für Integrations- und Mapping-Projekte.</p>
    </div>
    <span class="badge text-bg-secondary align-self-start align-self-lg-center">0.5.0 UI-Grundlage</span>
</div>

<div class="row g-3">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card admin-card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary">Workspaces</h2>
                <p class="display-6 mb-0">2</p>
                <p class="small text-body-secondary mb-0">Statische Projektübersicht</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card admin-card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary">Connections</h2>
                <p class="display-6 mb-0">2</p>
                <p class="small text-body-secondary mb-0">Platzhalter-Verbindungen</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card admin-card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary">Mappings</h2>
                <p class="display-6 mb-0">2</p>
                <p class="small text-body-secondary mb-0">Beispiel-Zuordnungen</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card admin-card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary">Jobs</h2>
                <p class="display-6 mb-0">2</p>
                <p class="small text-body-secondary mb-0">Noch nicht ausführbar</p>
            </div>
        </div>
    </div>
</div>

<section class="mt-4">
    <div class="card admin-card">
        <div class="card-body">
            <h2 class="h5">Nächste Workbench-Bereiche</h2>
            <p class="mb-0 text-body-secondary">Connections, Schema Explorer, Mapping Designer, Job Runner und Report Engine sind als Navigation und statische UI vorbereitet.</p>
        </div>
    </div>
</section>
