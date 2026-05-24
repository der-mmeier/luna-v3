<?php

/** @var array<string, mixed> $connection */
$formAction = '/admin/connections/' . (int) $connection['id'] . '/edit';
$heading = 'Connection bearbeiten';
$lead = 'Passwort leer lassen, wenn das bestehende verschluesselte Secret unveraendert bleiben soll.';

include __DIR__ . '/create.php';
