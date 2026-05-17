<?php

declare(strict_types=1);

use Luna\Core\Application;
use Luna\Http\Response;
use Luna\Routing\RouteCollection;

return static function (RouteCollection $routes, Application $app): void {
    $routes->get('/', static fn (): string => '<h1>Luna V3 Workbench</h1>', 'web.home', 'web');

    $routes->get('/health', static fn (): Response => Response::json([
        'status' => 'ok',
        'app' => $app->config()->string('APP_NAME', 'Luna V3'),
    ]), 'web.health', 'web');
};
