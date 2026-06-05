<?php

/** @var array<string, mixed> $connection */
/** @var array<int, array<string, mixed>> $webhooks */
/** @var array<int, array<string, mixed>> $queue */
/** @var array<int, array<string, mixed>> $runs */
/** @var array<string, mixed>|null $lastSuccessfulRun */
/** @var array<int, array<string, mixed>> $exportProfiles */
/** @var array<int, array<string, mixed>> $exportRuns */
/** @var array<int, array<string, mixed>> $webhookEvents */
/** @var array<int, array<string, mixed>> $expectedWebhooks */
/** @var array<string, mixed> $deliveryUrlInfo */
/** @var array<string, string> $topicLabels */
/** @var array<string, string> $defaultNames */
/** @var array<string, mixed>|null $validation */
/** @var array{type: string, message: string}|null $alert */

$yesNo = static fn (bool $value): string => $value ? 'ja' : 'nein';
$apiVersion = 'WP REST API Integration v3';
$deliveryUrl = (string) ($deliveryUrlInfo['delivery_url'] ?? '');
$topicLabels = $topicLabels ?? [
    'order.created' => 'Bestellung erstellt (order.created)',
    'order.updated' => 'Bestellung aktualisiert (order.updated)',
    'order.deleted' => 'Bestellung gelöscht (order.deleted)',
];
$defaultNames = $defaultNames ?? [
    'order.created' => 'Luna Order Created',
    'order.updated' => 'Luna Order Updated',
    'order.deleted' => 'Luna Order Deleted',
];

$webhookByTopic = [];
foreach ($webhooks ?? [] as $webhook) {
    $webhookByTopic[(string) ($webhook['topic'] ?? '')] = $webhook;
}

$latestEventByTopic = [];
foreach ($webhookEvents ?? [] as $event) {
    $topic = (string) ($event['topic'] ?? '');
    if ($topic !== '' && ! isset($latestEventByTopic[$topic])) {
        $latestEventByTopic[$topic] = $event;
    }
}

$localStatus = static function (array $webhook) use ($topicLabels, $apiVersion): string {
    $complete = trim((string) ($webhook['webhook_name'] ?? '')) !== ''
        && isset($topicLabels[(string) ($webhook['topic'] ?? '')])
        && trim((string) ($webhook['delivery_url'] ?? '')) !== ''
        && (string) ($webhook['api_version'] ?? '') === $apiVersion
        && ! empty($webhook['has_secret']);

    return $complete ? 'vollständig' : 'unvollständig';
};

$lastEventLabel = static function (?array $event): string {
    if ($event === null) {
        return 'nie empfangen';
    }

    $prefix = ! empty($event['signature_valid']) ? 'gültig' : 'ungültig';
    $receivedAt = (string) ($event['received_at'] ?? '');

    return trim($prefix . ': ' . $receivedAt);
};
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars((string) ($connection['name'] ?? 'WooCommerce-Anbindung'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-body-secondary mb-0">HPOS-first, read-only Richtung WooCommerce. Webhooks erzeugen nur geprüfte Änderungsereignisse.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/woocommerce">Zurück</a>
</div>

<?php if ($alert !== null): ?>
    <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card admin-card h-100">
            <div class="card-header">Validierung</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">WooCommerce-Version</dt>
                    <dd class="col-sm-7"><?= htmlspecialchars((string) ($validation['woocommerce_version'] ?? $connection['detected_woocommerce_version'] ?? 'unbekannt'), ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-5">Tabellenpräfix</dt>
                    <dd class="col-sm-7"><?= htmlspecialchars((string) ($validation['table_prefix'] ?? $connection['detected_table_prefix'] ?? 'unbekannt'), ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-5">HPOS aktiv</dt>
                    <dd class="col-sm-7"><?= $yesNo(! empty($validation['hpos_enabled']) || ! empty($connection['hpos_enabled'])) ?></dd>
                    <dt class="col-sm-5">HPOS authoritative</dt>
                    <dd class="col-sm-7"><?= $yesNo(! empty($validation['hpos_authoritative']) || ! empty($connection['hpos_authoritative'])) ?></dd>
                    <dt class="col-sm-5">Bestellungen gefunden</dt>
                    <dd class="col-sm-7"><?= htmlspecialchars((string) ($validation['order_count'] ?? 'nicht geprüft'), ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-5">Älteste Bestellung</dt>
                    <dd class="col-sm-7"><?= htmlspecialchars((string) ($validation['oldest_order_at'] ?? 'nicht geprüft'), ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-5">Neueste Bestellung</dt>
                    <dd class="col-sm-7"><?= htmlspecialchars((string) ($validation['newest_order_at'] ?? 'nicht geprüft'), ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-5">Transfer bereit</dt>
                    <dd class="col-sm-7"><?= $yesNo(! empty($validation['transfer_ready'])) ?></dd>
                </dl>

                <?php foreach (($validation['errors'] ?? []) as $error): ?>
                    <div class="alert alert-danger mt-3 mb-0"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
                <?php foreach (($validation['warnings'] ?? []) as $warning): ?>
                    <div class="alert alert-warning mt-3 mb-0"><?= htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
                <?php if (! empty($validation['missing_schema_parts'])): ?>
                    <div class="mt-3">
                        <div class="fw-semibold">Fehlende HPOS-Struktur</div>
                        <ul class="mb-0">
                            <?php foreach ($validation['missing_schema_parts'] as $part): ?>
                                <li><code><?= htmlspecialchars((string) $part, ENT_QUOTES, 'UTF-8') ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-end">
                <form method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/validate">
                    <button class="btn btn-primary" type="submit">WooCommerce validieren</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card admin-card h-100">
            <div class="card-header">HPOS / Einstellungen</div>
            <div class="card-body">
                <div class="alert alert-warning">
                    Warnung: HPOS Data Caching ist für diese Anbindung initial deaktiviert.
                    Wenn diese Option aktiviert wird, können Cache-Invalidierungsprobleme entstehen. Luna liest Bestellungen für Transfers direkt über die WooCommerce-Connection aus HPOS. Aktivieren Sie diese Option nur, wenn die Invalidierung mit WooCommerce, Redis/Object Cache und Webhook-/Transferfluss nachweislich getestet wurde.
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-5">Storage Mode</dt>
                    <dd class="col-sm-7"><code><?= htmlspecialchars((string) ($connection['storage_mode'] ?? 'hpos'), ENT_QUOTES, 'UTF-8') ?></code></dd>
                    <dt class="col-sm-5">HPOS Data Caching genutzt</dt>
                    <dd class="col-sm-7">nein</dd>
                    <dt class="col-sm-5">Webhook-URL Token</dt>
                    <dd class="col-sm-7"><code><?= htmlspecialchars((string) ($connection['connection_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></dd>
                </dl>
            </div>
            <div class="card-footer text-end">
                <form method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/initial-transfer" onsubmit="return confirm('Initialen WooCommerce-Transfer wirklich vormerken?');">
                    <button class="btn btn-outline-primary" type="submit">Initialen WooCommerce-Transfer starten</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card admin-card mb-4" id="webhook-setup">
    <div class="card-header">So richtest du Webhooks ein</div>
    <div class="card-body">
        <div class="alert alert-info">
            Luna erstellt in v2.0.0 keine Webhooks automatisch im WooCommerce-Shop. Speichere hier nur die lokale Prüfkonfiguration und das Secret. Danach musst du denselben Webhook manuell in WooCommerce unter WooCommerce → Einstellungen → Erweitert → Webhooks anlegen.
        </div>
        <?php if (! empty($deliveryUrlInfo['is_localhost'])): ?>
            <div class="alert alert-warning">
                Warnung: Die aktuelle Delivery URL enthält localhost. Ein externer WooCommerce-Shop kann diese URL nicht erreichen. Trage in der Luna-Konfiguration eine öffentlich erreichbare HTTPS-Base-URL ein.
            </div>
        <?php endif; ?>
        <ol class="mb-4">
            <li>Wähle unten ein Topic aus, z. B. Bestellung aktualisiert (order.updated).</li>
            <li>Lass Name, Delivery URL und API-Version automatisch ausfüllen.</li>
            <li>Generiere ein Secret.</li>
            <li>Speichere die Konfiguration in Luna.</li>
            <li>Kopiere Delivery URL und Secret.</li>
            <li>Öffne WooCommerce → Einstellungen → Erweitert → Webhooks.</li>
            <li>Erstelle dort einen neuen Webhook mit demselben Namen, Topic, Status Active, derselben Delivery URL und demselben Secret.</li>
            <li>Ändere danach testweise den Status einer Bestellung in WooCommerce.</li>
            <li>Prüfe in Luna, ob ein Webhook-Event und ein Queue-Eintrag entstanden sind.</li>
        </ol>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>WooCommerce-Feld</th>
                    <th>Wert aus Luna</th>
                </tr>
                </thead>
                <tbody>
                <tr><td>Name</td><td><code>Luna Order Updated</code></td></tr>
                <tr><td>Status</td><td><code>Active</code></td></tr>
                <tr><td>Topic</td><td>Bestellung aktualisiert / <code>order.updated</code></td></tr>
                <tr><td>Delivery URL</td><td><code><?= htmlspecialchars($deliveryUrl, ENT_QUOTES, 'UTF-8') ?></code></td></tr>
                <tr><td>Secret</td><td>aus Luna vor dem Speichern kopieren</td></tr>
                <tr><td>API-Version</td><td><?= htmlspecialchars($apiVersion, ENT_QUOTES, 'UTF-8') ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="alert alert-secondary mt-4 mb-0">
            Test: Ändere nach dem Anlegen des WooCommerce-Webhooks den Status einer Testbestellung. WooCommerce sendet dann order.updated an Luna. Wenn die Signatur stimmt, erscheint unter Webhook-Events ein gültiges Event und in der Transfer Queue ein neuer Eintrag für diese Order-ID.
        </div>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Soll-Konfiguration: benötigte WooCommerce-Webhooks</div>
    <div class="card-body border-bottom">
        <p class="text-body-secondary mb-0">Diese Liste zeigt, welche Webhooks Luna erwartet. Sie beweist nicht, dass diese Webhooks bereits im WooCommerce-Shop existieren.</p>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Topic</th>
                <th>Erwarteter Status</th>
                <th>Delivery URL für WooCommerce</th>
                <th>API-Version</th>
                <th>Pflicht</th>
                <th>Lokale Luna-Konfiguration</th>
                <th>Shop-Prüfung</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($expectedWebhooks ?? [] as $webhook): ?>
                <?php $local = $webhookByTopic[(string) $webhook['topic']] ?? null; ?>
                <tr>
                    <td><?= htmlspecialchars((string) $webhook['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) $webhook['topic'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) $webhook['expected_status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <code data-copy-source="expected-url-<?= htmlspecialchars((string) $webhook['topic'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $webhook['delivery_url'], ENT_QUOTES, 'UTF-8') ?></code>
                        <button class="btn btn-sm btn-outline-secondary ms-1" type="button" data-copy-target="expected-url-<?= htmlspecialchars((string) $webhook['topic'], ENT_QUOTES, 'UTF-8') ?>">kopieren</button>
                    </td>
                    <td><?= htmlspecialchars((string) $webhook['api_version'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $yesNo(! empty($webhook['required'])) ?></td>
                    <td>
                        <?php if ($local === null): ?>
                            lokale Konfiguration fehlt
                        <?php else: ?>
                            lokale Konfiguration vorhanden, Secret vorhanden: <?= $yesNo(! empty($local['has_secret'])) ?>
                        <?php endif; ?>
                    </td>
                    <td>Nicht automatisch geprüft – keine WooCommerce REST-Credentials hinterlegt.</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Transfer Queue</span>
        <form method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/queue/run" class="mb-0" onsubmit="return confirm('Pending WooCommerce-Transfers jetzt ausführen?');">
            <button class="btn btn-sm btn-primary" type="submit">Pending WooCommerce-Transfers jetzt ausführen</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>ID</th>
                <th>Topic</th>
                <th>Source Order ID</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Created At</th>
                <th>Started At</th>
                <th>Finished At</th>
                <th>Last Error</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($queue ?? [] as $entry): ?>
                <tr>
                    <td><?= (int) $entry['id'] ?></td>
                    <td><code><?= htmlspecialchars((string) ($entry['topic'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><code><?= htmlspecialchars((string) ($entry['source_order_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) ($entry['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($entry['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) ($entry['attempts'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($entry['started_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($entry['finished_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($entry['last_error'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (in_array((string) ($entry['status'] ?? ''), ['pending', 'failed'], true)): ?>
                            <form method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/queue/<?= (int) $entry['id'] ?>/run" class="d-inline">
                                <?php if ((string) ($entry['status'] ?? '') === 'failed'): ?>
                                    <input type="hidden" name="retry" value="1">
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-primary" type="submit"><?= (string) ($entry['status'] ?? '') === 'failed' ? 'Retry' : 'Jetzt ausführen' ?></button>
                            </form>
                        <?php else: ?>
                            <span class="text-body-secondary">-</span>
                        <?php endif; ?>
                        <?php if (! empty($entry['last_run_id'])): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="#run-<?= (int) $entry['last_run_id'] ?>">Details</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($queue ?? []) === []): ?>
                <tr><td colspan="11" class="text-body-secondary">Keine Queue-Einträge vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body border-top">
        <p class="text-body-secondary small mb-0">Für größere Datenmengen sollte die Ausführung über den CLI-Befehl oder einen späteren Worker-Prozess erfolgen.</p>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Letzte Transfer Runs</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Run ID</th>
                <th>Queue ID</th>
                <th>Run Type</th>
                <th>Status</th>
                <th>Orders Found</th>
                <th>Orders Written</th>
                <th>Addresses Written</th>
                <th>Items Written</th>
                <th>Item Meta Written</th>
                <th>Order Meta Written</th>
                <th>Refunds Seen</th>
                <th>Error Count</th>
                <th>Started At</th>
                <th>Finished At</th>
                <th>Error Message</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($runs ?? [] as $run): ?>
                <tr id="run-<?= (int) $run['id'] ?>">
                    <td><?= (int) $run['id'] ?></td>
                    <td><?= (int) ($run['queue_id'] ?? 0) ?></td>
                    <td><code><?= htmlspecialchars((string) ($run['run_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) ($run['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) ($run['orders_found'] ?? 0) ?></td>
                    <td><?= (int) ($run['orders_written'] ?? 0) ?></td>
                    <td><?= (int) ($run['addresses_written'] ?? 0) ?></td>
                    <td><?= (int) ($run['items_written'] ?? 0) ?></td>
                    <td><?= (int) ($run['item_meta_written'] ?? 0) ?></td>
                    <td><?= (int) ($run['order_meta_written'] ?? 0) ?></td>
                    <td><?= (int) ($run['refunds_seen'] ?? 0) ?></td>
                    <td><?= (int) ($run['error_count'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($run['started_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['finished_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['error_message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($runs ?? []) === []): ?>
                <tr><td colspan="15" class="text-body-secondary">Noch keine Transfer Runs vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card admin-card mb-4" id="export-profiles">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Exportprofile</span>
        <form method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/exports/defaults" class="mb-0">
            <button class="btn btn-sm btn-primary" type="submit">Default-Profile anlegen</button>
        </form>
    </div>
    <div class="card-body border-bottom">
        <p class="text-body-secondary mb-2">Der Export liest aus Luna-Transferdaten, nicht direkt aus WooCommerce. Webhooks und HPOS-Transfers aktualisieren diese Daten. Externe Systeme greifen anschließend über geschützte Exportprofile auf den stabilisierten Stand zu.</p>
        <?php if (! empty($lastSuccessfulRun)): ?>
            <div class="small">Letzter erfolgreicher Exportlauf: #<?= (int) $lastSuccessfulRun['id'] ?> am <?= htmlspecialchars((string) ($lastSuccessfulRun['finished_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, Profil <code><?= htmlspecialchars((string) ($lastSuccessfulRun['profile_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></div>
        <?php else: ?>
            <div class="small text-body-secondary">Noch kein erfolgreicher Exportlauf vorhanden.</div>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Profil</th>
                <th>Name</th>
                <th>Aktiv</th>
                <th>Format</th>
                <th>Auth-Modus</th>
                <th>Token vorhanden</th>
                <th>Secret vorhanden</th>
                <th>Raw Meta</th>
                <th>Item Raw Meta</th>
                <th>Batch Size</th>
                <th>Letzter erfolgreicher Export</th>
                <th>Letztes Watermark</th>
                <th>Export-URL</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($exportProfiles ?? [] as $profile): ?>
                <?php $profileId = (int) $profile['id']; ?>
                <tr>
                    <td><code><?= htmlspecialchars((string) ($profile['profile_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) ($profile['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $yesNo(! empty($profile['is_enabled'])) ?></td>
                    <td><?= htmlspecialchars((string) ($profile['export_format'] ?? 'json'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars((string) ($profile['auth_mode'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= $yesNo(! empty($profile['has_token'])) ?></td>
                    <td><?= $yesNo(! empty($profile['has_secret'])) ?></td>
                    <td><?= $yesNo(! empty($profile['include_raw_meta'])) ?></td>
                    <td><?= $yesNo(! empty($profile['include_item_raw_meta'])) ?></td>
                    <td><?= (int) ($profile['batch_size'] ?? 100) ?></td>
                    <td><?= htmlspecialchars((string) ($profile['last_successful_export_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($profile['last_successful_watermark'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <code data-copy-source="export-url-<?= $profileId ?>"><?= htmlspecialchars((string) ($profile['export_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                        <button class="btn btn-sm btn-outline-secondary mt-1" type="button" data-copy-target="export-url-<?= $profileId ?>">Export-URL kopieren</button>
                    </td>
                    <td class="text-nowrap">
                        <form method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/exports/<?= $profileId ?>/token" class="d-inline">
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Token generieren</button>
                        </form>
                        <form method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/exports/<?= $profileId ?>/secret" class="d-inline">
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Secret generieren</button>
                        </form>
                        <form method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/exports/<?= $profileId ?>/test" class="d-inline">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Test-Export</button>
                        </form>
                        <form method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/exports/<?= $profileId ?>/toggle" class="d-inline">
                            <button class="btn btn-sm btn-outline-warning" type="submit"><?= ! empty($profile['is_enabled']) ? 'Deaktivieren' : 'Aktivieren' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($exportProfiles ?? []) === []): ?>
                <tr><td colspan="14" class="text-body-secondary">Noch keine Exportprofile vorhanden. Lege zuerst die Default-Profile an.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Exportläufe</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>ID</th>
                <th>Profil</th>
                <th>Status</th>
                <th>Ausgelöst durch</th>
                <th>Datensätze gefunden</th>
                <th>Datensätze exportiert</th>
                <th>Fehler</th>
                <th>Gestartet</th>
                <th>Beendet</th>
                <th>Watermark</th>
                <th>Fehlermeldung</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($exportRuns ?? [] as $run): ?>
                <tr>
                    <td><?= (int) $run['id'] ?></td>
                    <td><code><?= htmlspecialchars((string) ($run['profile_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) ($run['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['triggered_by'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) ($run['records_found'] ?? 0) ?></td>
                    <td><?= (int) ($run['records_exported'] ?? 0) ?></td>
                    <td><?= (int) ($run['error_count'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($run['started_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['finished_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['watermark_after'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($run['error_message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($exportRuns ?? []) === []): ?>
                <tr><td colspan="11" class="text-body-secondary">Noch keine Exportläufe vorhanden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Automatische Shop-Prüfung derzeit nicht aktiv</div>
    <div class="card-body">
        <div class="alert alert-secondary mb-0">
            Luna kann vorhandene Webhooks im WooCommerce-Shop erst automatisch auflisten, wenn WooCommerce REST-Credentials hinterlegt sind. Das ist für den Initialimport nicht erforderlich. Die manuelle Webhook-Konfiguration funktioniert trotzdem, wenn Delivery URL und Secret identisch in WooCommerce und Luna eingetragen werden.
        </div>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Webhook-Events</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>ID</th>
                <th>Topic</th>
                <th>Order ID</th>
                <th>Signatur</th>
                <th>Delivery ID</th>
                <th>Empfangen am</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($webhookEvents ?? [] as $event): ?>
                <tr>
                    <td><?= (int) $event['id'] ?></td>
                    <td><code><?= htmlspecialchars((string) ($event['topic'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><code><?= htmlspecialchars((string) ($event['source_order_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= ! empty($event['signature_valid']) ? 'gültig' : 'ungültig' ?></td>
                    <td><?= htmlspecialchars((string) ($event['delivery_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($event['received_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($event['processing_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($webhookEvents ?? []) === []): ?>
                <tr><td colspan="7" class="text-body-secondary">Noch keine Webhook-Events empfangen.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card admin-card mb-4">
    <div class="card-header">Lokale Webhook-Prüfkonfiguration in Luna</div>
    <div class="card-body border-bottom">
        <p class="mb-0">Luna erstellt keine Webhooks in WooCommerce. Speichere hier nur die lokale Prüfkonfiguration und das Secret. Danach musst du denselben Webhook manuell in WooCommerce anlegen.</p>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Topic</th>
                <th>Delivery URL</th>
                <th>API-Version</th>
                <th>Secret vorhanden</th>
                <th>Pflicht</th>
                <th>Lokaler Status</th>
                <th>Shop-Prüfung</th>
                <th>Letztes Event</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($webhooks ?? [] as $webhook): ?>
                <?php
                $formId = 'webhook-' . (int) $webhook['id'];
                $topic = (string) ($webhook['topic'] ?? '');
                ?>
                <tr>
                    <td><input class="form-control form-control-sm" name="webhook_name" form="<?= $formId ?>" value="<?= htmlspecialchars((string) ($webhook['webhook_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-webhook-name></td>
                    <td>
                        <select class="form-select form-select-sm" name="topic" form="<?= $formId ?>" data-webhook-topic>
                            <?php foreach ($topicLabels as $topicValue => $topicLabel): ?>
                                <option value="<?= htmlspecialchars($topicValue, ENT_QUOTES, 'UTF-8') ?>" <?= $topic === $topicValue ? 'selected' : '' ?>><?= htmlspecialchars($topicLabel, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input class="form-control form-control-sm" name="delivery_url" form="<?= $formId ?>" value="<?= htmlspecialchars((string) ($webhook['delivery_url'] ?? $deliveryUrl), ENT_QUOTES, 'UTF-8') ?>" data-copy-source="delivery-<?= (int) $webhook['id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary mt-1" type="button" data-copy-target="delivery-<?= (int) $webhook['id'] ?>">Delivery URL kopieren</button>
                    </td>
                    <td>
                        <select class="form-select form-select-sm" name="api_version" form="<?= $formId ?>">
                            <option value="<?= htmlspecialchars($apiVersion, ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars($apiVersion, ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </td>
                    <td>
                        Secret vorhanden: <?= $yesNo(! empty($webhook['has_secret'])) ?>
                        <input class="form-control form-control-sm mt-1" type="text" name="secret" form="<?= $formId ?>" placeholder="Neues Secret generieren" data-secret-input>
                        <div class="btn-group btn-group-sm mt-1">
                            <button class="btn btn-outline-secondary" type="button" data-generate-secret>Neues Secret generieren</button>
                            <button class="btn btn-outline-secondary" type="button" data-copy-secret>Secret kopieren</button>
                        </div>
                    </td>
                    <td><?= $yesNo(! empty($webhook['is_required'])) ?></td>
                    <td><?= htmlspecialchars($localStatus($webhook), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>REST-Credentials fehlen</td>
                    <td><?= htmlspecialchars($lastEventLabel($latestEventByTopic[$topic] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <form id="<?= $formId ?>" method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/webhooks/<?= (int) $webhook['id'] ?>" data-webhook-form>
                            <input type="hidden" name="expected_status" value="active">
                            <input type="hidden" name="is_required" value="<?= ! empty($webhook['is_required']) ? '1' : '' ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Bearbeiten</button>
                            <a class="btn btn-sm btn-outline-secondary" href="#webhook-setup">Anleitung anzeigen</a>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($webhooks ?? []) === []): ?>
                <tr><td colspan="10" class="text-body-secondary">Noch keine lokale Webhook-Prüfkonfiguration gespeichert.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body border-top">
        <form method="post" action="/admin/woocommerce/<?= (int) $connection['id'] ?>/webhooks" class="row g-2 align-items-end" data-webhook-form>
            <div class="col-md-3">
                <label class="form-label">Name</label>
                <div class="input-group">
                    <input class="form-control" name="webhook_name" value="Luna Order Updated" required data-webhook-name data-copy-source="new-webhook-name">
                    <button class="btn btn-outline-secondary" type="button" data-copy-target="new-webhook-name">kopieren</button>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Topic</label>
                <select class="form-select" name="topic" data-webhook-topic>
                    <?php foreach ($topicLabels as $topicValue => $topicLabel): ?>
                        <option value="<?= htmlspecialchars($topicValue, ENT_QUOTES, 'UTF-8') ?>" <?= $topicValue === 'order.updated' ? 'selected' : '' ?>><?= htmlspecialchars($topicLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Default: Bestellung aktualisiert, weil Status- und Zahlstatusänderungen darüber laufen.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Delivery URL</label>
                <div class="input-group">
                    <input class="form-control" name="delivery_url" value="<?= htmlspecialchars($deliveryUrl, ENT_QUOTES, 'UTF-8') ?>" required data-copy-source="new-delivery-url">
                    <button class="btn btn-outline-secondary" type="button" data-copy-target="new-delivery-url">kopieren</button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">API-Version</label>
                <select class="form-select" name="api_version">
                    <option value="<?= htmlspecialchars($apiVersion, ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars($apiVersion, ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Secret</label>
                <div class="input-group">
                    <input class="form-control" type="text" name="secret" required data-secret-input data-copy-source="new-secret" autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" data-generate-secret>Secret generieren</button>
                    <button class="btn btn-outline-secondary" type="button" data-copy-secret>Secret kopieren</button>
                </div>
                <div class="form-text">Vor dem Speichern sichtbar und kopierbar. Nach dem Speichern wird das Secret nicht mehr im Klartext angezeigt.</div>
            </div>
            <div class="col-md-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_required" value="1" checked id="webhookRequired">
                    <label class="form-check-label" for="webhookRequired">Pflicht</label>
                </div>
            </div>
            <div class="col-md-5 text-end">
                <button class="btn btn-outline-primary" type="submit">Konfiguration in Luna speichern</button>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const defaultNames = <?= json_encode($defaultNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function scopeFor(element) {
        return element.closest('form') || element.closest('tr') || document;
    }

    function randomSecret() {
        const bytes = new Uint8Array(32);
        window.crypto.getRandomValues(bytes);
        return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    function copyValue(value) {
        if (!value) {
            return;
        }
        navigator.clipboard?.writeText(value);
    }

    document.querySelectorAll('[data-webhook-topic]').forEach(select => {
        select.addEventListener('change', () => {
            const form = select.closest('form') || document.getElementById(select.getAttribute('form'));
            const nameInput = form?.querySelector('[data-webhook-name]');
            const suggested = defaultNames[select.value] || '';
            if (nameInput && (nameInput.value.trim() === '' || Object.values(defaultNames).includes(nameInput.value.trim()))) {
                nameInput.value = suggested;
            }
        });
    });

    document.querySelectorAll('[data-generate-secret]').forEach(button => {
        button.addEventListener('click', () => {
            const scope = scopeFor(button);
            const input = scope?.querySelector('[data-secret-input]');
            if (!input) {
                return;
            }
            if (input.value.trim() !== '' && !window.confirm('Neues Secret generieren? Das Secret muss danach auch im WooCommerce-Webhook aktualisiert werden.')) {
                return;
            }
            input.value = randomSecret();
        });
    });

    document.querySelectorAll('[data-copy-secret]').forEach(button => {
        button.addEventListener('click', () => {
            const scope = scopeFor(button);
            copyValue(scope?.querySelector('[data-secret-input]')?.value || '');
        });
    });

    document.querySelectorAll('[data-copy-target]').forEach(button => {
        button.addEventListener('click', () => {
            const key = button.getAttribute('data-copy-target');
            const source = document.querySelector(`[data-copy-source="${key}"]`);
            copyValue(source?.value || source?.textContent || '');
        });
    });
})();
</script>
