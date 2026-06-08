<?php

declare(strict_types=1);

namespace Luna\WooCommerce;

use Luna\Http\Request;

final class WooCommerceWebhookEventNormalizer
{
    /**
     * @param array<string, mixed> $trigger
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function normalize(array $trigger, array $config, Request $request, string $rawBody, bool $signatureValid): array
    {
        $headers = $this->headers($request);
        $payload = $this->payload($rawBody);
        $topic = $headers['x-wc-webhook-topic'] ?? (string) ($config['topic'] ?? 'generic');
        [$resource, $eventAction] = $this->resourceAndEvent($topic, $headers);
        $maxLength = max(200, min((int) ($config['max_payload_log_length'] ?? 4000), 20000));
        $summary = $this->payloadSummary($payload, $rawBody, $maxLength);
        $receivedAt = date(DATE_ATOM);

        return [
            'provider' => 'woocommerce',
            'workspace_id' => empty($trigger['workspace_id']) ? null : (int) $trigger['workspace_id'],
            'process_trigger_id' => (int) ($trigger['id'] ?? 0),
            'topic' => $topic,
            'resource' => $resource,
            'event' => $eventAction,
            'delivery_id' => $headers['x-wc-webhook-delivery-id'] ?? '',
            'webhook_id' => $headers['x-wc-webhook-id'] ?? '',
            'source_domain' => $this->sourceDomain($request, $headers),
            'source_order_id' => $this->sourceOrderId($payload),
            'received_at' => $receivedAt,
            'signature_valid' => $signatureValid,
            'payload_size' => strlen($rawBody),
            'payload_hash' => $rawBody === '' ? null : hash('sha256', $rawBody),
            'payload_summary' => $summary,
            'payload_meta' => [
                'top_level_keys' => is_array($payload) ? array_slice(array_keys($payload), 0, 50) : [],
                'content_type' => (string) $request->header('Content-Type', ''),
                'payload_log_mode' => (string) ($config['payload_log_mode'] ?? 'summary'),
            ],
            'payload' => ! empty($config['payload_context_mode']) && (string) $config['payload_context_mode'] === 'full'
                ? $this->sanitizePayload($payload)
                : null,
            'transfer_payload' => $this->sanitizePayload($payload),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $headers = [];
        foreach ([
            'X-WC-Webhook-Signature',
            'X-WC-Webhook-Topic',
            'X-WC-Webhook-Resource',
            'X-WC-Webhook-Event',
            'X-WC-Webhook-Delivery-ID',
            'X-WC-Webhook-ID',
            'X-WC-Webhook-Source',
        ] as $header) {
            $value = (string) $request->header($header, '');
            if ($value !== '') {
                $headers[strtolower($header)] = $value;
            }
        }

        return $headers;
    }

    private function payload(string $rawBody): mixed
    {
        if (trim($rawBody) === '') {
            return [];
        }

        try {
            return json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: string, 1: string}
     */
    private function resourceAndEvent(string $topic, array $headers): array
    {
        $resource = $headers['x-wc-webhook-resource'] ?? '';
        $event = $headers['x-wc-webhook-event'] ?? '';
        if (($resource === '' || $event === '') && str_contains($topic, '.')) {
            [$topicResource, $topicEvent] = explode('.', $topic, 2);
            $resource = $resource === '' ? $topicResource : $resource;
            $event = $event === '' ? $topicEvent : $event;
        }

        return [$resource, $event];
    }

    private function sourceDomain(Request $request, array $headers): string
    {
        $source = $headers['x-wc-webhook-source'] ?? (string) $request->header('Referer', '');
        if ($source !== '') {
            $host = parse_url($source, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }

            return $source;
        }

        return (string) $request->header('Host', $request->server('HTTP_HOST', ''));
    }

    private function sourceOrderId(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach (['id', 'order_id', 'source_order_id'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key]) && (string) $payload[$key] !== '') {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadSummary(mixed $payload, string $rawBody, int $maxLength): array
    {
        $summary = [
            'json_valid' => is_array($payload),
            'truncated' => strlen($rawBody) > $maxLength,
            'preview' => $this->payloadPreview($payload, $rawBody, $maxLength),
        ];

        if (is_array($payload)) {
            foreach (['id', 'order_id', 'status', 'currency', 'total', 'date_created', 'date_modified'] as $key) {
                if (isset($payload[$key]) && is_scalar($payload[$key])) {
                    $summary[$key] = (string) $payload[$key];
                }
            }
        }

        return $this->sanitizePayload($summary);
    }

    private function payloadPreview(mixed $payload, string $rawBody, int $maxLength): string
    {
        if (is_array($payload)) {
            $json = json_encode($this->sanitizePayload($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return substr($json === false ? '{}' : $json, 0, $maxLength);
        }

        $preview = substr($rawBody, 0, $maxLength);
        $preview = preg_replace(
            '/("?(?:password|passwd|secret|token|api_key|authorization|bearer|cookie|consumer_key|consumer_secret|order_key)"?\s*[:=]\s*)"[^"]*"/i',
            '$1"***"',
            $preview,
        );

        return $preview ?? '';
    }

    private function sanitizePayload(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        $sanitized = [];
        foreach ($payload as $key => $value) {
            $keyString = is_string($key) ? $key : (string) $key;
            if (preg_match('/password|passwd|secret|token|api_key|authorization|bearer|cookie|consumer_key|consumer_secret|order_key/i', $keyString) === 1) {
                $sanitized[$key] = '***';
                continue;
            }

            $sanitized[$key] = is_array($value) ? $this->sanitizePayload($value) : $value;
        }

        return $sanitized;
    }
}
