<?php

declare(strict_types=1);

use Luna\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::init(dirname(__DIR__));

echo 'Luna V3 läuft';