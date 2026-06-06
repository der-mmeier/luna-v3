<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Export\EndpointExportSanitizer;
use PHPUnit\Framework\TestCase;

final class EndpointExportSanitizerTest extends TestCase
{
    public function testSensitiveKeysAreRemovedRecursivelyAndCaseInsensitive(): void
    {
        $sanitized = (new EndpointExportSanitizer())->sanitize([
            'name' => 'Export',
            'password' => 'secret-password',
            'Api_Key' => 'api-secret',
            'nested' => [
                'token' => 'token-secret',
                'safe' => 'value',
            ],
        ]);

        self::assertIsArray($sanitized);
        self::assertSame('Export', $sanitized['name']);
        self::assertArrayNotHasKey('password', $sanitized);
        self::assertArrayNotHasKey('Api_Key', $sanitized);
        self::assertArrayNotHasKey('token', $sanitized['nested']);
        self::assertSame('value', $sanitized['nested']['safe']);
        self::assertStringNotContainsString('secret-password', json_encode($sanitized, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('api-secret', json_encode($sanitized, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('token-secret', json_encode($sanitized, JSON_THROW_ON_ERROR));
    }
}
