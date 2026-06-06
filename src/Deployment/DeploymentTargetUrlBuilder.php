<?php

declare(strict_types=1);

namespace Luna\Deployment;

use InvalidArgumentException;
use Luna\Repository\EndpointRepository;

final class DeploymentTargetUrlBuilder
{
    public function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new InvalidArgumentException('Die URL darf nicht leer sein.');
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            throw new InvalidArgumentException('Die URL ist ungültig.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Die URL muss mit http:// oder https:// beginnen.');
        }

        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            throw new InvalidArgumentException('URLs mit eingebetteten Zugangsdaten sind nicht erlaubt.');
        }

        $host = (string) ($parts['host'] ?? '');
        if ($host === '') {
            throw new InvalidArgumentException('Die URL muss einen Host enthalten.');
        }

        $normalized = $scheme . '://' . $host;
        if (isset($parts['port'])) {
            $normalized .= ':' . (int) $parts['port'];
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($path !== '') {
            $normalized .= '/' . $path;
        }

        return $normalized;
    }

    public function assertProductionUrlAllowed(string $environment, string $url): void
    {
        if (strtolower(trim($environment)) !== 'production') {
            return;
        }

        if ($this->isLoopbackUrl($url)) {
            throw new InvalidArgumentException('Production Targets dürfen keine localhost- oder Loopback-URL verwenden.');
        }
    }

    public function isLoopbackUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts)) {
            return false;
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || str_starts_with($host, '127.')
            || $host === '::1'
            || $host === '0.0.0.0';
    }

    /**
     * @param array<string, mixed> $target
     */
    public function endpointUrl(array $target, string $endpointSlug): string
    {
        $base = trim((string) ($target['endpoint_base_url'] ?? ''));
        if ($base === '') {
            $base = $this->normalizeBaseUrl((string) ($target['public_base_url'] ?? '')) . '/api/endpoints';
        } else {
            $base = $this->normalizeBaseUrl($base);
        }

        return rtrim($base, '/') . '/' . EndpointRepository::normalizeEndpointKey($endpointSlug);
    }

    public function endpointPath(string $endpointSlug): string
    {
        return '/api/endpoints/' . EndpointRepository::normalizeEndpointKey($endpointSlug);
    }

    public function currentRequestBaseUrl(string $scheme, string $host, string $scriptName = ''): string
    {
        $basePath = trim(str_replace('\\', '/', dirname($scriptName)), '/.');
        $base = strtolower($scheme) . '://' . trim($host);

        return $this->normalizeBaseUrl($base . ($basePath === '' ? '' : '/' . $basePath));
    }
}
