<?php

declare(strict_types=1);

namespace Luna\Security;

use Luna\Config\Config;
use RuntimeException;

final class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';

    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function encrypt(string $plainText): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt(
            $plainText,
            self::CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($encrypted === false) {
            throw new RuntimeException('Could not encrypt secret.');
        }

        $json = json_encode([
            'version' => 'v1',
            'cipher' => self::CIPHER,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'value' => base64_encode($encrypted),
        ], JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Could not encode encrypted secret.');
        }

        return $json;
    }

    public function decrypt(string $encrypted): string
    {
        $payload = json_decode($encrypted, true);

        if (! is_array($payload)) {
            throw new RuntimeException('Encrypted secret payload is invalid.');
        }

        if (($payload['version'] ?? null) !== 'v1' || ($payload['cipher'] ?? null) !== self::CIPHER) {
            throw new RuntimeException('Encrypted secret payload version is unsupported.');
        }

        $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
        $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
        $value = base64_decode((string) ($payload['value'] ?? ''), true);

        if ($iv === false || $tag === false || $value === false) {
            throw new RuntimeException('Encrypted secret payload is malformed.');
        }

        $plainText = openssl_decrypt(
            $value,
            self::CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plainText === false) {
            throw new RuntimeException('Could not decrypt secret.');
        }

        return $plainText;
    }

    private function key(): string
    {
        $appKey = $this->config->appKey();

        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is required for encryption.');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            if ($decoded === false || $decoded === '') {
                throw new RuntimeException('APP_KEY base64 value is invalid.');
            }

            $appKey = $decoded;
        }

        return hash('sha256', $appKey, true);
    }
}
