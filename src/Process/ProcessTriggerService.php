<?php

declare(strict_types=1);

namespace Luna\Process;

use Luna\Http\Request;
use Luna\Repository\ProcessRepository;
use Luna\Repository\ProcessTriggerRepository;

final class ProcessTriggerService
{
    public function __construct(
        private readonly ProcessRepository $processes,
        private readonly ProcessTriggerRepository $triggers,
        private readonly TriggerConfigValidator $validator,
    ) {
    }

    public function valuesForProcess(int $processId, array $process, Request $request): array
    {
        $name = (string) $request->post('name', '');

        return [
            'process_id' => $processId,
            'workspace_id' => empty($process['workspace_id']) ? null : (int) $process['workspace_id'],
            'name' => $name,
            'trigger_type' => (string) $request->post('trigger_type', 'manual'),
            'trigger_key' => (string) $request->post('trigger_key', ''),
            'is_active' => $request->post('is_active') !== null ? '1' : '',
            'config_json' => (string) $request->post('config_json', ''),
        ];
    }

    /**
     * @return list<string>
     */
    public function validate(array $values): array
    {
        $errors = $this->validator->validate($values);
        $process = $this->processes->find((int) ($values['process_id'] ?? 0));
        if ($process === null) {
            $errors[] = 'Prozess wurde nicht gefunden.';
        }

        return $errors;
    }

    public function create(array $values, ?string $secret): int
    {
        return $this->triggers->create($values, $secret);
    }

    public function update(int $id, array $values, ?string $secret, bool $replaceSecret): void
    {
        $this->triggers->update($id, $values, $secret, $replaceSecret);
    }

    public function safeRequestMetadata(Request $request, string $rawBody): array
    {
        $headerWhitelist = [
            'content-type' => (string) $request->header('Content-Type', ''),
            'x-wc-webhook-topic' => (string) $request->header('X-WC-Webhook-Topic', ''),
            'x-wc-webhook-resource' => (string) $request->header('X-WC-Webhook-Resource', ''),
            'x-wc-webhook-event' => (string) $request->header('X-WC-Webhook-Event', ''),
            'x-wc-webhook-delivery-id' => (string) $request->header('X-WC-Webhook-Delivery-ID', ''),
        ];

        return [
            'method' => $request->method(),
            'route' => $request->path(),
            'content_type' => (string) $request->header('Content-Type', ''),
            'payload_size' => strlen($rawBody),
            'payload_hash' => $rawBody === '' ? null : hash('sha256', $rawBody),
            'headers' => array_filter($headerWhitelist, static fn (string $value): bool => $value !== ''),
        ];
    }
}
