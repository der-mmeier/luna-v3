<?php

/** @var array<int, array{source: string, target: string, rule: string}> $mappings */
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Mappings</h1>
    <p class="text-body-secondary mb-0">Platzhalter für den späteren Mapping-Designer.</p>
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Quelle</th>
                <th>Ziel</th>
                <th>Regel</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($mappings ?? [] as $mapping): ?>
                <tr>
                    <td><code><?= htmlspecialchars($mapping['source'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><code><?= htmlspecialchars($mapping['target'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars($mapping['rule'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
