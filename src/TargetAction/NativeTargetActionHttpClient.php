<?php

declare(strict_types=1);

namespace Luna\TargetAction;

use RuntimeException;

final class NativeTargetActionHttpClient implements TargetActionHttpClientInterface
{
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 10): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => max(1, min($timeoutSeconds, 60)),
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException('HTTP Action konnte nicht ausgeführt werden.');
        }

        $statusCode = 0;
        $responseHeaders = [];
        foreach ($http_response_header as $line) {
            if (preg_match('/^HTTP\\/\\S+\\s+(\\d+)/', $line, $matches) === 1) {
                $statusCode = (int) $matches[1];
                continue;
            }
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
        }

        return [
            'status_code' => $statusCode,
            'body' => $responseBody,
            'headers' => $responseHeaders,
        ];
    }
}
