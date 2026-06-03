<?php

declare(strict_types=1);

namespace Luna\WooCommerce;

use Luna\Repository\WooCommerceIntegrationRepository;

final class WooCommerceWebhookHandler
{
    public function __construct(
        private readonly WooCommerceIntegrationRepository $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $headers
     * @return array{status: int, payload: array<string, mixed>}
     */
    public function handle(string $connectionToken, array $headers, string $rawBody): array
    {
        $connection = $this->repository->findConnectionByToken($connectionToken);
        if ($connection === null) {
            return [
                'status' => 404,
                'payload' => [
                    'success' => false,
                    'error' => 'woocommerce_connection_not_found',
                    'message' => 'WooCommerce-Anbindung wurde nicht gefunden.',
                ],
            ];
        }

        $normalizedHeaders = $this->normalizeHeaders($headers);
        $topic = (string) ($normalizedHeaders['x-wc-webhook-topic'] ?? '');
        $signature = (string) ($normalizedHeaders['x-wc-webhook-signature'] ?? '');
        $secret = $topic === '' ? null : $this->repository->secretForTopic((int) $connection['id'], $topic);
        $signatureValid = $secret !== null && $signature !== '' && $this->signatureIsValid($rawBody, $secret, $signature);
        $payload = json_decode($rawBody, true);
        $payloadArray = is_array($payload) ? $payload : [];
        $sourceOrderId = $this->sourceOrderId($payloadArray);

        $eventId = $this->repository->storeWebhookEvent([
            'workspace_id' => $connection['workspace_id'] ?? null,
            'woocommerce_connection_id' => (int) $connection['id'],
            'topic' => $topic,
            'resource' => (string) ($normalizedHeaders['x-wc-webhook-resource'] ?? ''),
            'event_action' => (string) ($normalizedHeaders['x-wc-webhook-event'] ?? ''),
            'source_order_id' => $sourceOrderId,
            'delivery_id' => (string) ($normalizedHeaders['x-wc-webhook-delivery-id'] ?? ''),
            'signature_valid' => $signatureValid,
            'raw_headers' => $this->safeHeadersForStorage($normalizedHeaders),
            'raw_payload' => $rawBody,
            'processing_status' => $signatureValid ? 'verified' : 'rejected',
            'processing_message' => $signatureValid ? 'Webhook-Signatur wurde geprüft.' : 'Webhook-Signatur ist ungültig oder Secret fehlt.',
        ]);

        if (! $signatureValid) {
            return [
                'status' => 401,
                'payload' => [
                    'success' => false,
                    'error' => 'invalid_signature',
                    'message' => 'Webhook-Signatur ist ungültig.',
                ],
            ];
        }

        if ($sourceOrderId !== null && $sourceOrderId !== '') {
            $this->repository->queueTransfer([
                'workspace_id' => $connection['workspace_id'] ?? null,
                'woocommerce_connection_id' => (int) $connection['id'],
                'webhook_event_id' => $eventId,
                'source_order_id' => $sourceOrderId,
                'topic' => $topic,
                'reason' => 'webhook ' . $topic,
                'status' => 'pending',
            ]);
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'status' => 'accepted',
            ],
        ];
    }

    private function signatureIsValid(string $rawBody, string $secret, string $signature): bool
    {
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($expected, $signature);
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower(str_replace('_', '-', (string) $name))] = is_scalar($value) ? (string) $value : '';
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function safeHeadersForStorage(array $headers): array
    {
        $safe = [];
        foreach ($headers as $name => $value) {
            $safe[$name] = $name === 'x-wc-webhook-signature' ? '[present]' : $value;
        }

        return $safe;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sourceOrderId(array $payload): ?string
    {
        foreach (['id', 'order_id', 'source_order_id'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key]) && (string) $payload[$key] !== '') {
                return (string) $payload[$key];
            }
        }

        return null;
    }
}
