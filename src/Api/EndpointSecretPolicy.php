<?php

declare(strict_types=1);

namespace Luna\Api;

use Luna\Http\Request;

final class EndpointSecretPolicy
{
    /**
     * @return array{status: int, code: string, message: string}|null
     */
    public function check(array $endpoint, Request $request, callable $verifySecret): ?array
    {
        $mode = $this->mode($endpoint);

        if ($mode === 'none') {
            return null;
        }

        $providedSecret = $this->secretFromRequest($request);

        if ($mode === 'optional' && $providedSecret === '') {
            return null;
        }

        if ($providedSecret === '') {
            return ['status' => 401, 'code' => 'secret_missing', 'message' => 'Endpoint secret is required.'];
        }

        if ($mode === 'required' && ! $verifySecret($providedSecret)) {
            return ['status' => 403, 'code' => 'secret_invalid', 'message' => 'Endpoint secret is invalid.'];
        }

        return null;
    }

    public function mode(array $endpoint): string
    {
        $mode = (string) ($endpoint['secret_mode'] ?? '');

        if (in_array($mode, ['none', 'optional', 'required'], true)) {
            return $mode;
        }

        return (string) ($endpoint['visibility'] ?? 'private') === 'private' ? 'required' : 'none';
    }

    public function secretFromRequest(Request $request): string
    {
        $header = $request->header('X-Luna-Endpoint-Secret', '');
        if (is_scalar($header) && trim((string) $header) !== '') {
            return (string) $header;
        }

        $query = $request->query('secret', '');

        return is_scalar($query) ? (string) $query : '';
    }
}
