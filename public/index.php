<?php

declare(strict_types=1);

use Luna\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = Bootstrap::init(dirname(__DIR__));

echo $app->run();
