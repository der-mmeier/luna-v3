<?php

declare(strict_types=1);

namespace Luna\Api;

use Luna\Http\Response;

final class EndpointJsonResponseFactory
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function success(array $items, int $statusCode = 200): Response
    {
        return Response::json([
            'success' => true,
            'generated_at' => date(DATE_ATOM),
            'count' => count($items),
            'items' => $this->sanitize($items),
        ], $statusCode);
    }

    public function error(string $code, string $message, int $statusCode): Response
    {
        return Response::json([
            'success' => false,
            'generated_at' => date(DATE_ATOM),
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $statusCode);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/(^|_)(secret|password|token|api_key|app_key|client_secret|dsn|secret_hash)(_|$)/i', $key) === 1) {
                $data[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}
