<?php

/** @var array<int, array<string, mixed>> $items */
/** @var array{type: string, message: string}|null $alert */
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">WooCommerce - Anbindung</h1>
        <p class="text-body-secondary mb-0">WooCommerce-Bestellungen werden über HPOS gelesen; Webhooks dienen nur als verifizierte Änderungstrigger.</p>
    </div>
    <a class="btn btn-primary" href="/admin/woocommerce/create">Anbindung anlegen</a>
</div>
<?php if (($alert ?? null) !== null): ?>
    <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

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
                    <td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="/admin/woocommerce/<?= (int) $item['id'] ?>">Öffnen</a><form method="post" action="/admin/woocommerce/<?= (int) $item['id'] ?>/delete" onsubmit="return confirm('Diese WooCommerce-Anbindung wirklich aus Luna löschen? Im Shop werden keine Daten geändert.');"><input type="hidden" name="confirm_delete" value="1"><button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button></form></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?>
                <tr><td colspan="6" class="text-body-secondary">Noch keine WooCommerce-Anbindungen vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
