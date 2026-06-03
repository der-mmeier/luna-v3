<?php

/** @var array<int, array<string, mixed>> $items */
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">WooCommerce - Anbindung</h1>
        <p class="text-body-secondary mb-0">WooCommerce-Bestellungen werden über HPOS gelesen; Webhooks dienen nur als verifizierte Änderungstrigger.</p>
    </div>
    <a class="btn btn-primary" href="/admin/woocommerce/create">Anbindung anlegen</a>
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Workspace</th>
                <th>Connection</th>
                <th>WooCommerce</th>
                <th>HPOS</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items ?? [] as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($item['workspace_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($item['connection_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <code><?= htmlspecialchars((string) ($item['detected_woocommerce_version'] ?? 'unbekannt'), ENT_QUOTES, 'UTF-8') ?></code>
                        <div class="small text-body-secondary">Prefix: <?= htmlspecialchars((string) ($item['detected_table_prefix'] ?? 'unbekannt'), ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                    <td>
                        <?= ! empty($item['hpos_enabled']) && ! empty($item['hpos_authoritative']) ? 'gültig' : 'nicht validiert' ?>
                    </td>
                    <td><a class="btn btn-sm btn-outline-primary" href="/admin/woocommerce/<?= (int) $item['id'] ?>">Öffnen</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?>
                <tr><td colspan="6" class="text-body-secondary">Noch keine WooCommerce-Anbindungen vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
