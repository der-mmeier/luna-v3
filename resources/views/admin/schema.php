<?php

/** @var array<int, array{name: string, type: string, nullable: string, comment: string}> $columns */
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Schema Explorer</h1>
    <p class="text-body-secondary mb-0">Platzhalter für Tabellenanalyse, Beispieldaten und Spaltenkommentare.</p>
</div>

<div class="card admin-card">
    <div class="card-header bg-white">
        Beispieltabelle: <strong>customers</strong>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Spaltenname</th>
                <th>Datentyp</th>
                <th>Nullable</th>
                <th>Kommentar</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($columns ?? [] as $column): ?>
                <tr>
                    <td><code><?= htmlspecialchars($column['name'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars($column['type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($column['nullable'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($column['comment'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
