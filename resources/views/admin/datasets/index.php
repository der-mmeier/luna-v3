<?php

/** @var array<int, array<string, mixed>> $datasets */
/** @var string|null $error */
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">Datasets</h1>
        <p class="text-body-secondary mb-0">Ein Dataset ist ein Mapping-/Endpoint-Ergebnis und keine echte Datenbankverbindung.</p>
    </div>
</div>

<?php if (! empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="alert alert-info">
    Dataset Sources dienen als vorbereitete Quelle für spätere Transfers. In 1.8.0 wird noch nichts in Zielsysteme geschrieben.
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Quelle</th>
                <th>Mapping</th>
                <th>Status</th>
                <th>Felder</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($datasets ?? [] as $dataset): ?>
                <tr>
                    <td>
                        <code><?= htmlspecialchars((string) $dataset['name'], ENT_QUOTES, 'UTF-8') ?></code>
                        <div class="small text-body-secondary"><?= htmlspecialchars((string) ($dataset['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                    <td><?= htmlspecialchars((string) ($dataset['source_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($dataset['mapping_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($dataset['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= count((array) ($dataset['fields'] ?? [])) ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="/admin/datasets/<?= rawurlencode((string) $dataset['name']) ?>">Öffnen</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($datasets ?? []) === []): ?>
                <tr><td colspan="6" class="text-body-secondary">Noch keine Datasets verfügbar.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
