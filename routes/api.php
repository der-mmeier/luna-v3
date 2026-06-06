<?php

declare(strict_types=1);

use Luna\Core\Application;
use Luna\Core\AppVersion;
use Luna\Http\Response;
use Luna\Process\ProcessTriggerException;
use Luna\Process\ProcessTriggerRunner;
use Luna\Process\ProcessTriggerService;
use Luna\Repository\ProcessRunRepository;
use Luna\Routing\RouteCollection;

return static function (RouteCollection $routes, Application $app): void {
    $routes->get('/api/version', static fn (): Response => Response::json([
        'app' => $app->config()->string('APP_NAME', 'Luna V3'),
        'version' => AppVersion::VERSION,
        'environment' => $app->config()->string('APP_ENV', 'local'),
        'status' => 'ok',
    ]), 'api.version', 'api');

    $endpointRuntime = static fn () => $app->services()->get('api.endpoint_runtime');
    $triggerRunner = static fn (): ProcessTriggerRunner => $app->services()->get(ProcessTriggerRunner::class);
    $triggerService = static fn (): ProcessTriggerService => $app->services()->get(ProcessTriggerService::class);
    $processRuns = static fn (): ProcessRunRepository => $app->services()->get(ProcessRunRepository::class);

    $runTriggerResponse = static function ($request, string $expectedType) use ($triggerRunner, $triggerService, $processRuns): Response {
        $rawBody = file_get_contents('php://input');
        $rawBody = $rawBody === false ? '' : $rawBody;

        try {
            $runId = $triggerRunner()->runByIdentifier(
                (string) $request->route('trigger_key'),
                'run',
                $expectedType,
                (string) $request->header('X-Luna-Trigger-Secret', ''),
                $triggerService()->safeRequestMetadata($request, $rawBody),
                null,
                $expectedType,
            );
            $run = $processRuns()->findRun($runId);

            return Response::json([
                'success' => true,
                'process_id' => (int) ($run['process_id'] ?? 0),
                'trigger_id' => (int) ($run['trigger_id'] ?? 0),
                'run_id' => $runId,
                'status' => (string) ($run['status'] ?? 'queued'),
            ], 201);
        } catch (ProcessTriggerException $exception) {
            return Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus());
        } catch (Throwable) {
            return Response::json([
                'success' => false,
                'message' => 'Trigger konnte nicht ausgeführt werden.',
            ], 500);
        }
    };

    $routes->get('/api/endpoints/{endpointKey}', static fn ($request): Response => $endpointRuntime()->handle($request), 'api.endpoints.runtime', 'api');
    $routes->post('/api/endpoints/{endpointKey}', static fn ($request): Response => $endpointRuntime()->handle($request), 'api.endpoints.runtime_post', 'api');
    $routes->get('/api/e/{endpointKey}', static fn ($request): Response => $endpointRuntime()->handle($request), 'api.endpoints.show', 'api');
    $routes->post('/api/e/{endpointKey}', static fn ($request): Response => $endpointRuntime()->handle($request), 'api.endpoints.post', 'api');
    $routes->post('/api/process-triggers/{trigger_key}/run', static fn ($request): Response => $runTriggerResponse($request, 'api'), 'api.process_triggers.run', 'api');
    $routes->post('/api/webhooks/{trigger_key}', static fn ($request): Response => $runTriggerResponse($request, 'webhook'), 'api.process_triggers.webhook', 'api');
};
