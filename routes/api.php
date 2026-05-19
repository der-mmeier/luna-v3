<?php

declare(strict_types=1);

use Luna\Core\Application;
use Luna\Core\AppVersion;
use Luna\Http\Response;
use Luna\Routing\RouteCollection;

return static function (RouteCollection $routes, Application $app): void {
    $routes->get('/api/version', static fn (): Response => Response::json([
        'app' => $app->config()->string('APP_NAME', 'Luna V3'),
        'version' => AppVersion::VERSION,
        'environment' => $app->config()->string('APP_ENV', 'local'),
        'status' => 'ok',
    ]), 'api.version', 'api');

    $endpointRuntime = static fn () => $app->services()->get('api.endpoint_runtime');

    $routes->get('/api/e/{endpointKey}', static fn ($request): Response => $endpointRuntime()->handle($request), 'api.endpoints.show', 'api');
    $routes->post('/api/e/{endpointKey}', static fn ($request): Response => $endpointRuntime()->handle($request), 'api.endpoints.post', 'api');
};
