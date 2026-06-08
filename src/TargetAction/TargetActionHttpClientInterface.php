<?php

declare(strict_types=1);

namespace Luna\TargetAction;

interface TargetActionHttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @return array{status_code: int, body: string, headers: array<string, string>}
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 10): array;
}
