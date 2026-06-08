<?php

/** @var array<string, mixed> $connection */
$formAction = '/admin/connections/' . (int) $connection['id'] . '/edit';
$heading = 'Connection bearbeiten';
$lead = 'Passwort leer lassen, wenn das bestehende verschlüsselte Secret erhalten bleiben soll.';

include __DIR__ . '/create.php';
?>
<form method="post" action="/admin/connections/<?= (int) $connection['id'] ?>/delete" class="mt-3" onsubmit="return confirm('Diesen Eintrag wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
    <input type="hidden" name="confirm_delete" value="1">
    <button class="btn btn-danger" type="submit">Connection löschen</button>
</form>
