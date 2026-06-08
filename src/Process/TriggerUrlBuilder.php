<?php

declare(strict_types=1);

namespace Luna\Process;

use Luna\Deployment\DeploymentTargetUrlBuilder;
use Luna\Repository\DeploymentTargetRepository;
use Luna\Repository\ProcessTriggerRepository;

final class TriggerUrlBuilder
{
    public function __construct(
        private readonly DeploymentTargetRepository $deploymentTargets,
        private readonly DeploymentTargetUrlBuilder $urlBuilder,
    ) {
    }

    public function defaultTargetForWorkspace(?int $workspaceId): ?array
    {
        $targets = $this->deploymentTargets->activeForWorkspace($workspaceId);
        if ($targets === []) {
            return null;
        }

        foreach ($targets as $target) {
            if (! empty($target['is_default'])) {
                return $target;
            }
        }

        return $targets[0];
    }

    public function apiUrl(array $target, string $triggerKey): string
    {
        $base = $this->urlBuilder->normalizeBaseUrl((string) ($target['public_base_url'] ?? ''));

        return rtrim($base, '/') . '/api/process-triggers/' . ProcessTriggerRepository::normalizeKey($triggerKey) . '/run';
    }

    public function webhookUrl(array $target, string $triggerKey): string
    {
        $base = trim((string) ($target['webhook_base_url'] ?? ''));
        if ($base === '') {
            $base = $this->urlBuilder->normalizeBaseUrl((string) ($target['public_base_url'] ?? '')) . '/api/webhooks';
        } else {
            $base = $this->urlBuilder->normalizeBaseUrl($base);
        }

        return rtrim($base, '/') . '/' . ProcessTriggerRepository::normalizeKey($triggerKey);
    }

    public function woocommerceWebhookUrl(array $target, string $triggerKey): string
    {
        $base = trim((string) ($target['webhook_base_url'] ?? ''));
        if ($base === '') {
            $base = $this->urlBuilder->normalizeBaseUrl((string) ($target['public_base_url'] ?? '')) . '/api/webhooks';
        } else {
            $base = $this->urlBuilder->normalizeBaseUrl($base);
        }

        return rtrim($base, '/') . '/woocommerce/' . ProcessTriggerRepository::normalizeKey($triggerKey);
    }

    public function urlForTrigger(array $trigger, ?array $target): ?string
    {
        if ($target === null) {
            return null;
        }

        $key = (string) ($trigger['trigger_key'] ?? '');

        $type = (string) ($trigger['trigger_type'] ?? '');
        if ($type === 'webhook' && $this->isWooCommerceTrigger($trigger)) {
            return $this->woocommerceWebhookUrl($target, $key);
        }

        return match ($type) {
            'api' => $this->apiUrl($target, $key),
            'webhook' => $this->webhookUrl($target, $key),
            default => null,
        };
    }

    private function isWooCommerceTrigger(array $trigger): bool
    {
        $config = json_decode((string) ($trigger['config_json'] ?? ''), true);

        return is_array($config) && (string) ($config['provider'] ?? '') === 'woocommerce';
    }
}
