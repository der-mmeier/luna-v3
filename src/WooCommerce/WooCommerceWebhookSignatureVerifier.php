<?php

declare(strict_types=1);

namespace Luna\WooCommerce;

final class WooCommerceWebhookSignatureVerifier
{
    public function expectedSignature(string $rawBody, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
    }

    public function verify(string $rawBody, string $secret, string $signature): bool
    {
        $signature = trim($signature);
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals($this->expectedSignature($rawBody, $secret), $signature);
    }
}
