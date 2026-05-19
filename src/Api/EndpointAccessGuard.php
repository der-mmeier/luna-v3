<?php

declare(strict_types=1);

namespace Luna\Api;

use Luna\Config\Config;
use Luna\Http\Request;
use Luna\Http\Response;
use Luna\Repository\AuditLogRepository;
use Luna\Repository\EndpointRepository;

final class EndpointAccessGuard
{
    public function __construct(
        private readonly EndpointRepository $endpoints,
        private readonly AuditLogRepository $audit,
        private readonly Config $config,
    ) {
    }

    public function check(array $endpoint, Request $request): ?Response
    {
        if ((string) $endpoint['visibility'] === 'public') {
            return null;
        }

        $providedSecret = $this->secretFromRequest($request);
        if ($providedSecret === '') {
            $this->auditDenied($endpoint, 'missing_secret');

            return Response::json(['error' => 'endpoint_secret_required'], 401);
        }

        if (! $this->endpoints->verifySecret((int) $endpoint['id'], $providedSecret)) {
            $this->auditDenied($endpoint, 'invalid_secret');

            return Response::json(['error' => 'endpoint_secret_invalid'], 403);
        }

        return null;
    }

    private function secretFromRequest(Request $request): string
    {
        $header = $request->header('X-Luna-Endpoint-Secret', '');
        if (is_scalar($header) && trim((string) $header) !== '') {
            return (string) $header;
        }

        if ($this->config->string('APP_ENV', 'local') !== 'production') {
            $query = $request->query('secret', '');

            return is_scalar($query) ? (string) $query : '';
        }

        return '';
    }

    private function auditDenied(array $endpoint, string $reason): void
    {
        $this->audit->log(
            empty($endpoint['workspace_id']) ? null : (int) $endpoint['workspace_id'],
            'endpoint.access_denied',
            'endpoint',
            (string) $endpoint['id'],
            'Endpoint-Zugriff abgelehnt.',
            ['reason' => $reason, 'endpoint_key' => $endpoint['endpoint_key'] ?? null],
        );
    }
}
