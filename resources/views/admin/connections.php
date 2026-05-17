<?php

/** @var array<int, array{name: string, type: string, role: string, mode: string}> $connections */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Connections</h1>
        <p class="text-body-secondary mb-0">Vorbereitung für externe Datenquellen und Transferziele.</p>
    </div>
    <button class="btn btn-primary" type="button">Connection anlegen</button>
</div>

<div class="alert alert-info" role="alert">
    Zugangsdaten externer Verbindungen werden später verschlüsselt in der Luna-Systemdatenbank gespeichert.
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Typ</th>
                <th>Rolle</th>
                <th>Modus</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($connections ?? [] as $connection): ?>
                <tr>
                    <td><?= htmlspecialchars($connection['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($connection['type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($connection['role'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge text-bg-secondary"><?= htmlspecialchars($connection['mode'], ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
