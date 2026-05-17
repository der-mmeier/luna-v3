<?php

declare(strict_types=1);

namespace Luna\Routing;

use Luna\Http\Request;
use Luna\Http\Response;
use Throwable;

final class Router
{
    public function __construct(
        private readonly RouteCollection $routes,
    ) {
    }

    public function dispatch(Request $request): Response
    {
        $route = $this->routes->match($request);

        if ($route === null) {
            return Response::notFound();
        }

        try {
            $result = ($route->handler())($request);
        } catch (Throwable) {
            return Response::text('Internal Server Error', 500);
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return Response::html((string) $result);
    }
}
