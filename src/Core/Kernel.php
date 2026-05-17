<?php

declare(strict_types=1);

namespace Luna\Core;

use Luna\Http\Request;
use Luna\Http\Response;
use Luna\Routing\RouteCollection;
use Luna\Routing\Router;

final class Kernel
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function handle(): Response
    {
        $request = Request::fromGlobals();
        $routes = new RouteCollection();

        $this->loadRoutes($routes, $this->app->paths()->basePath('routes/web.php'));
        $this->loadRoutes($routes, $this->app->paths()->basePath('routes/api.php'));

        return (new Router($routes))->dispatch($request);
    }

    private function loadRoutes(RouteCollection $routes, string $file): void
    {
        if (! is_file($file)) {
            return;
        }

        $loader = require $file;

        if (is_callable($loader)) {
            $loader($routes, $this->app);
        }
    }
}
