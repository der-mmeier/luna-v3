<?php

declare(strict_types=1);

use Luna\Core\Application;
use Luna\Http\Response;
use Luna\Routing\RouteCollection;

return static function (RouteCollection $routes, Application $app): void {
    // Private API-Security follows later through the Endpoint Builder and endpoint secrets.
    $routes->get('/api/version', static fn (): Response => Response::json([
        'app' => $app->config()->string('APP_NAME', 'Luna V3'),
        'version' => '0.4.0',
        'environment' => $app->config()->string('APP_ENV', 'local'),
    ]), 'api.version', 'api');
};
