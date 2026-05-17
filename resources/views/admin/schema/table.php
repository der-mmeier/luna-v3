<?php

/** @var array<string, mixed>|null $connection */
/** @var string $tableName */
/** @var array<int, array<string, mixed>> $columns */
/** @var array<int, array<string, mixed>> $samples */
/** @var array<string, mixed>|null $tableNote */
/** @var array<string, array<string, mixed>|null> $columnNotes */
/** @var array{type: string, message: string}|null $alert */

$short = static function (mixed $value): string {
    $text = (string) $value;
    return strlen($text) > 80 ? substr($text, 0, 77) . '...' : $text;
};
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Tabelle: <code><?= htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') ?></code></h1>
        <p class="text-body-secondary mb-0">Spalten, Beispieldaten und Luna-Kommentare.</p>
    </div>
    <?php if ($connection !== null): ?>
        <a class="btn btn-outline-secondary" href="/admin/schema/<?= (int) $connection['id'] ?>">Zurück</a>
    <?php endif; ?>
</div>

<?php if (! empty($alert)): ?>
    <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($connection !== null): ?>
    <form class="card admin-card mb-4" method="post" action="/admin/schema/<?= (int) $connection['id'] ?>/table-note">
        <div class="card-body">
            <input type="hidden" name="table_name" value="<?= htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') ?>">
            <label class="form-label" for="table-note">Luna-Tabellenkommentar</label>
            <textarea class="form-control" id="table-note" name="note" rows="2"><?= htmlspecialchars((string) ($tableNote['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit">Tabellenkommentar speichern</button>
        </div>
    </form>
<?php endif; ?>

<div class="card admin-card mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Spalte</th>
                <th>Typ</th>
                <th>Nullable</th>
                <th>Key</th>
                <th>Extern</th>
                <th>Luna-Kommentar</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($columns ?? [] as $column): ?>
                <?php $name = (string) $column['column_name']; ?>
                <tr>
                    <td><code><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) $column['column_type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $column['is_nullable'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $column['column_key'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $column['column_comment'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if ($connection !== null): ?>
                            <form method="post" action="/admin/schema/<?= (int) $connection['id'] ?>/column-note">
                                <input type="hidden" name="table_name" value="<?= htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="column_name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="input-group input-group-sm">
                                    <input class="form-control" name="note" value="<?= htmlspecialchars((string) ($columnNotes[$name]['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="btn btn-outline-primary" type="submit">Speichern</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card admin-card">
    <div class="card-header bg-white">Beispieldaten</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <tbody>
            <?php foreach ($samples ?? [] as $row): ?>
                <tr>
                    <?php foreach ($row as $value): ?>
                        <td><?= htmlspecialchars($short($value), ENT_QUOTES, 'UTF-8') ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (($samples ?? []) === []): ?>
                <tr><td class="text-body-secondary">Keine Beispieldaten verfügbar.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
