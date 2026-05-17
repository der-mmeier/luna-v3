<?php

/** @var array<int, array{name: string, type: string, schedule: string}> $reports */
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Reports</h1>
    <p class="text-body-secondary mb-0">Platzhalter für die spätere Report Engine.</p>
</div>

<div class="row g-3">
    <?php foreach ($reports ?? [] as $report): ?>
        <div class="col-12 col-lg-6">
            <div class="card admin-card h-100">
                <div class="card-body">
                    <h2 class="h5"><?= htmlspecialchars($report['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="mb-1"><span class="text-body-secondary">Typ:</span> <?= htmlspecialchars($report['type'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-0"><span class="text-body-secondary">Zeitplan:</span> <?= htmlspecialchars($report['schedule'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
