<?php

declare(strict_types=1);

namespace Luna\Routing;

use Luna\Http\Request;

final class RouteCollection
{
    /**
     * @var list<Route>
     */
    private array $routes = [];

    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    public function get(string $path, callable $handler, ?string $name = null, string $group = 'web'): Route
    {
        $route = new Route('GET', $path, $handler, $name, $group);
        $this->add($route);

        return $route;
    }

    public function post(string $path, callable $handler, ?string $name = null, string $group = 'web'): Route
    {
        $route = new Route('POST', $path, $handler, $name, $group);
        $this->add($route);

        return $route;
    }

    /**
     * @return list<Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function match(Request $request): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $route;
            }
        }

        return null;
    }
}
