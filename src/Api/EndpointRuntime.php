<?php

declare(strict_types=1);

namespace Luna\Api;

use Luna\Http\Request;
use Luna\Http\Response;
use Luna\Repository\AuditLogRepository;
use Luna\Repository\EndpointRepository;
use Throwable;

final class EndpointRuntime
{
    public function __construct(
        private readonly EndpointRepository $endpoints,
        private readonly EndpointAccessGuard $guard,
        private readonly EndpointResponseBuilder $builder,
        private readonly EndpointJsonResponseFactory $responses,
        private readonly AuditLogRepository $audit,
    ) {
    }

    public function handle(Request $request): Response
    {
        $endpointKey = EndpointRepository::normalizeEndpointKey((string) $request->route('endpointKey', ''));
        $endpoint = $this->endpoints->findBySlug($endpointKey);

        if ($endpoint === null) {
            return $this->responses->error('endpoint_not_found', 'Endpoint not found.', 404);
        }

        $this->audit->log(
            empty($endpoint['workspace_id']) ? null : (int) $endpoint['workspace_id'],
            'endpoint.request',
            'endpoint',
            (string) $endpoint['id'],
            'Endpoint aufgerufen.',
            ['endpoint_key' => $endpoint['endpoint_key'], 'method' => $request->method()],
        );

        if ((string) $endpoint['status'] !== 'active') {
            return $this->responses->error('endpoint_inactive', 'Endpoint is inactive.', 404);
        }

        if (strtoupper((string) $endpoint['method']) !== $request->method()) {
            return $this->responses->error('method_not_allowed', 'Method not allowed.', 405)->withHeader('Allow', strtoupper((string) $endpoint['method']));
        }

        $guardResponse = $this->guard->check($endpoint, $request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        try {
            $response = $this->builder->build($endpoint);
            $this->audit->log(
                empty($endpoint['workspace_id']) ? null : (int) $endpoint['workspace_id'],
                'endpoint.success',
                'endpoint',
                (string) $endpoint['id'],
                'Endpoint erfolgreich beantwortet.',
                ['endpoint_key' => $endpoint['endpoint_key'], 'status_code' => $response->statusCode()],
            );

            return $response;
        } catch (Throwable) {
            $this->audit->log(
                empty($endpoint['workspace_id']) ? null : (int) $endpoint['workspace_id'],
                'endpoint.failed',
                'endpoint',
                (string) $endpoint['id'],
                'Endpoint-Ausfuehrung fehlgeschlagen.',
                ['endpoint_key' => $endpoint['endpoint_key']],
            );

            return $this->responses->error('runtime_error', 'Internal runtime error.', 500);
        }
    }
}
