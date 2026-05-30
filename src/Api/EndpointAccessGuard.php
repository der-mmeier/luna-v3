<?php

declare(strict_types=1);

namespace Luna\Api;

use Luna\Http\Request;
use Luna\Http\Response;
use Luna\Repository\AuditLogRepository;
use Luna\Repository\EndpointRepository;

final class EndpointAccessGuard
{
    public function __construct(
        private readonly EndpointRepository $endpoints,
        private readonly AuditLogRepository $audit,
        private readonly EndpointSecretPolicy $policy,
        private readonly EndpointJsonResponseFactory $responses,
    ) {
    }

    public function check(array $endpoint, Request $request): ?Response
    {
        $decision = $this->policy->check(
            $endpoint,
            $request,
            fn (string $secret): bool => $this->endpoints->verifySecret((int) $endpoint['id'], $secret),
        );

        if ($decision === null) {
            return null;
        }

        $this->auditDenied($endpoint, $decision['code']);

        return $this->responses->error($decision['code'], $decision['message'], $decision['status']);
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
