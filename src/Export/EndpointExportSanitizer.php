<?php

declare(strict_types=1);

namespace Luna\Export;

final class EndpointExportSanitizer
{
    private const SENSITIVE_KEYS = [
        'password',
        'passwd',
        'pwd',
        'secret',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'apikey',
        'app_key',
        'private_key',
        'client_secret',
        'dsn',
        'username',
    ];

    public function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];
        foreach ($value as $key => $child) {
            $stringKey = is_string($key) ? $key : (string) $key;
            if ($this->isSensitiveKey($stringKey)) {
                continue;
            }

            $sanitized[$key] = $this->sanitize($child);
        }

        return $sanitized;
    }

    public function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));
        if (in_array($normalized, ['secret_free', 'secret_exported', 'contains_secrets', 'connections_exported_as_references_only'], true)) {
            return false;
        }

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_contains($normalized, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
