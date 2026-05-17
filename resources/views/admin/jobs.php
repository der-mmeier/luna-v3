<?php

/** @var array<int, array{name: string, status: string, lastRun: string}> $jobs */
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Jobs</h1>
    <p class="text-body-secondary mb-0">Platzhalter für den späteren Job Runner.</p>
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Jobname</th>
                <th>Status</th>
                <th>Letzte Ausführung</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($jobs ?? [] as $job): ?>
                <tr>
                    <td><?= htmlspecialchars($job['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge text-bg-light"><?= htmlspecialchars($job['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($job['lastRun'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
