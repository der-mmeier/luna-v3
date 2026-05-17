<?php

declare(strict_types=1);

use Luna\Core\Application;
use Luna\Http\Response;
use Luna\Routing\RouteCollection;
use Luna\View\ViewRenderer;

return static function (RouteCollection $routes, Application $app): void {
    $view = $app->services()->get('view');

    if (! $view instanceof ViewRenderer) {
        throw new RuntimeException('View service is not available.');
    }

    $admin = static fn (string $template, array $data = []): Response => Response::html($view->render(
        $template,
        array_merge(['appName' => $app->config()->string('APP_NAME', 'Luna V3')], $data),
        'layouts/admin',
    ));

    $routes->get('/', static fn (): Response => Response::html($view->render(
        'admin/dashboard',
        [
            'appName' => $app->config()->string('APP_NAME', 'Luna V3'),
            'title' => 'Dashboard',
            'active' => 'dashboard',
        ],
        'layouts/admin',
    )), 'web.home', 'web');

    $routes->get('/admin', static fn (): Response => $admin('admin/dashboard', [
        'title' => 'Dashboard',
        'active' => 'dashboard',
    ]), 'admin.dashboard', 'web');

    $routes->get('/admin/workspaces', static fn (): Response => $admin('admin/workspaces', [
        'title' => 'Workspaces',
        'active' => 'workspaces',
        'workspaces' => [
            ['name' => 'Demo Integration', 'status' => 'Entwurf', 'updated' => '2026-05-17'],
            ['name' => 'ERP Export', 'status' => 'Geplant', 'updated' => '2026-05-17'],
        ],
    ]), 'admin.workspaces', 'web');

    $routes->get('/admin/connections', static fn (): Response => $admin('admin/connections', [
        'title' => 'Connections',
        'active' => 'connections',
        'connections' => [
            ['name' => 'Quellsystem Demo', 'type' => 'MySQL', 'role' => 'Quelle', 'mode' => 'read-only'],
            ['name' => 'Transfer Ziel', 'type' => 'MariaDB', 'role' => 'Transfer', 'mode' => 'write-enabled'],
        ],
    ]), 'admin.connections', 'web');

    $routes->get('/admin/schema', static fn (): Response => $admin('admin/schema', [
        'title' => 'Schema Explorer',
        'active' => 'schema',
        'columns' => [
            ['name' => 'customer_id', 'type' => 'INT', 'nullable' => 'Nein', 'comment' => 'Externe Kundennummer'],
            ['name' => 'email', 'type' => 'VARCHAR(255)', 'nullable' => 'Ja', 'comment' => 'Kontaktadresse'],
            ['name' => 'updated_at', 'type' => 'DATETIME', 'nullable' => 'Nein', 'comment' => 'Letzte Änderung'],
        ],
    ]), 'admin.schema', 'web');

    $routes->get('/admin/mappings', static fn (): Response => $admin('admin/mappings', [
        'title' => 'Mappings',
        'active' => 'mappings',
        'mappings' => [
            ['source' => 'customers.customer_id', 'target' => 'transfer_customer.external_id', 'rule' => 'Direkt'],
            ['source' => 'customers.email', 'target' => 'transfer_customer.email', 'rule' => 'Trim'],
        ],
    ]), 'admin.mappings', 'web');

    $routes->get('/admin/jobs', static fn (): Response => $admin('admin/jobs', [
        'title' => 'Jobs',
        'active' => 'jobs',
        'jobs' => [
            ['name' => 'Demo Transfer', 'status' => 'Bereit', 'lastRun' => '-'],
            ['name' => 'Report Versand', 'status' => 'Geplant', 'lastRun' => '-'],
        ],
    ]), 'admin.jobs', 'web');

    $routes->get('/admin/reports', static fn (): Response => $admin('admin/reports', [
        'title' => 'Reports',
        'active' => 'reports',
        'reports' => [
            ['name' => 'Transfer Status', 'type' => 'E-Mail', 'schedule' => 'Manuell'],
            ['name' => 'Fehlerübersicht', 'type' => 'E-Mail', 'schedule' => 'Täglich geplant'],
        ],
    ]), 'admin.reports', 'web');

    $routes->get('/health', static fn (): Response => Response::json([
        'status' => 'ok',
        'app' => $app->config()->string('APP_NAME', 'Luna V3'),
    ]), 'web.health', 'web');
};
