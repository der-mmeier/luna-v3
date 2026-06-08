<?php

declare(strict_types=1);

namespace Luna\WooCommerce;

use Luna\Http\Request;
use Luna\Process\ProcessTriggerException;
use Luna\Process\ProcessTriggerRunner;
use Luna\Repository\ProcessRunRepository;
use Luna\Repository\ProcessTriggerRepository;
use Luna\Repository\WooCommerceRuntimeEventRepository;

final class WooCommerceRuntimeWebhookHandler
{
    public function __construct(
        private readonly ProcessTriggerRepository $triggers,
        private readonly ProcessTriggerRunner $triggerRunner,
        private readonly ProcessRunRepository $processRuns,
        private readonly WooCommerceRuntimeEventRepository $events,
        private readonly WooCommerceWebhookSignatureVerifier $signatureVerifier,
        private readonly WooCommerceWebhookEventNormalizer $normalizer,
    ) {
    }

    /**
     * @return array{status: int, payload: array<string, mixed>}
     */
    public function handle(Request $request, string $triggerKey, string $rawBody): array
    {
        $trigger = $this->triggers->findByKey($triggerKey);
        if ($trigger === null) {
            return $this->error(404, 'woocommerce_trigger_not_found', 'WooCommerce-Trigger wurde nicht gefunden.');
        }
        if ((string) ($trigger['trigger_type'] ?? '') !== 'webhook') {
            return $this->error(409, 'not_webhook_trigger', 'Trigger ist kein Webhook-Trigger.');
        }
        if (empty($trigger['is_active'])) {
            return $this->error(409, 'inactive_trigger', 'Trigger ist inaktiv.');
        }

        $config = $this->config($trigger);
        if ((string) ($config['provider'] ?? '') !== 'woocommerce') {
            return $this->error(409, 'provider_mismatch', 'Trigger ist nicht als WooCommerce-Webhook konfiguriert.');
        }

        $topic = (string) $request->header('X-WC-Webhook-Topic', '');
        $configuredTopic = (string) ($config['topic'] ?? 'generic');
        if ($configuredTopic !== '' && $configuredTopic !== 'generic' && $topic !== '' && $topic !== $configuredTopic) {
            return $this->error(400, 'topic_mismatch', 'WooCommerce-Topic passt nicht zur Trigger-Konfiguration.');
        }

        $secret = $this->triggers->secretForTrigger($trigger);
        $signature = (string) $request->header('X-WC-Webhook-Signature', '');
        $allowUnsigned = ! empty($config['allow_unsigned']);
        $signatureValid = $secret !== null && $this->signatureVerifier->verify($rawBody, $secret, $signature);

        $event = $this->normalizer->normalize($trigger, $config, $request, $rawBody, $signatureValid);
        if ($secret === null && ! $allowUnsigned) {
            $eventId = $this->events->create($event, 'rejected', 'WooCommerce Webhook Secret ist nicht konfiguriert.');

            return $this->error(401, 'signature_secret_missing', 'WooCommerce Webhook Secret ist nicht konfiguriert.', ['event_id' => $eventId]);
        }
        if (! $signatureValid && ! $allowUnsigned) {
            $eventId = $this->events->create($event, 'rejected', 'WooCommerce Webhook Signatur ist ungültig.');

            return $this->error(401, 'invalid_signature', 'WooCommerce Webhook Signatur ist ungültig.', ['event_id' => $eventId]);
        }

        $event['signature_valid'] = $signatureValid || $allowUnsigned;
        $eventId = $this->events->create($event, 'verified', $allowUnsigned && ! $signatureValid ? 'Unsigned Webhook explizit erlaubt.' : 'WooCommerce Webhook Signatur wurde geprüft.');
        $runtimeEvent = $this->runtimeEvent($event, $eventId);

        try {
            $runId = $this->triggerRunner->runTrigger(
                $trigger,
                'run',
                'webhook',
                $secret,
                $this->payloadMeta($runtimeEvent),
                null,
                'webhook',
                [
                    'woocommerce_event' => $runtimeEvent,
                    'previous_result' => $runtimeEvent,
                ],
            );
        } catch (ProcessTriggerException $exception) {
            $this->events->attachRun($eventId, null, 'failed', $exception->getMessage());

            return $this->error($exception->httpStatus(), 'process_trigger_failed', $exception->getMessage(), ['event_id' => $eventId]);
        }

        $run = $this->processRuns->findRun($runId);
        $status = (string) ($run['status'] ?? 'queued');
        $this->events->attachRun($eventId, $runId, $status, 'Prozesslauf wurde durch WooCommerce Webhook gestartet.');

        return [
            'status' => 201,
            'payload' => [
                'success' => true,
                'process_run_id' => $runId,
                'status' => $status,
                'event_id' => $eventId,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $trigger
     * @return array<string, mixed>
     */
    private function config(array $trigger): array
    {
        $decoded = json_decode((string) ($trigger['config_json'] ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function runtimeEvent(array $event, int $eventId): array
    {
        $runtimeEvent = [
            'provider' => 'woocommerce',
            'event_id' => $eventId,
            'topic' => (string) ($event['topic'] ?? ''),
            'resource' => (string) ($event['resource'] ?? ''),
            'event' => (string) ($event['event'] ?? ''),
            'delivery_id' => (string) ($event['delivery_id'] ?? ''),
            'webhook_id' => (string) ($event['webhook_id'] ?? ''),
            'source_domain' => (string) ($event['source_domain'] ?? ''),
            'source_order_id' => $event['source_order_id'] ?? null,
            'received_at' => (string) ($event['received_at'] ?? ''),
            'signature_valid' => ! empty($event['signature_valid']),
            'payload_ref' => [
                'type' => 'woocommerce_runtime_event',
                'id' => $eventId,
            ],
            'payload_summary' => is_array($event['payload_summary'] ?? null) ? $event['payload_summary'] : [],
        ];

        if (is_array($event['payload'] ?? null)) {
            $runtimeEvent['payload'] = $event['payload'];
        }

        return $runtimeEvent;
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function payloadMeta(array $event): array
    {
        return [
            'provider' => 'woocommerce',
            'topic' => (string) ($event['topic'] ?? ''),
            'resource' => (string) ($event['resource'] ?? ''),
            'event' => (string) ($event['event'] ?? ''),
            'delivery_id' => (string) ($event['delivery_id'] ?? ''),
            'source_domain' => (string) ($event['source_domain'] ?? ''),
            'received_at' => (string) ($event['received_at'] ?? ''),
            'signature_valid' => ! empty($event['signature_valid']),
            'payload_ref' => $event['payload_ref'] ?? null,
            'payload_summary' => $event['payload_summary'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array{status: int, payload: array<string, mixed>}
     */
    private function error(int $status, string $error, string $message, array $extra = []): array
    {
        return [
            'status' => $status,
            'payload' => $extra + [
                'success' => false,
                'error' => $error,
                'message' => $message,
            ],
        ];
    }
}
