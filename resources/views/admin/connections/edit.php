<?php

/** @var array<string, mixed> $connection */
$formAction = '/admin/connections/' . (int) $connection['id'] . '/edit';
$heading = 'Connection bearbeiten';
$lead = 'Passwort leer lassen, wenn das bestehende verschlüsselte Secret undertaker bleiben soll.';

include __DIR__ . '/create.php';
