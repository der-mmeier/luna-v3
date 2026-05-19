<?php

declare(strict_types=1);

use Luna\Bootstrap;

$requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$publicFile = __DIR__ . '/' . ltrim(is_string($requestedPath) ? $requestedPath : '', '/');

if (PHP_SAPI === 'cli-server' && is_file($publicFile)) {
    return false;
}

require dirname(__DIR__) . '/vendor/autoload.php';

$app = Bootstrap::init(dirname(__DIR__));

$app->run();
