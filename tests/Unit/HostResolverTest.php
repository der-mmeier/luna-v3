<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Connections\ExternalDatabaseConfig;
use Luna\Network\HostResolver;
use PHPUnit\Framework\TestCase;

final class HostResolverTest extends TestCase
{
    public function testIpv4AddressIsReturnedUnchanged(): void
    {
        self::assertSame('192.0.2.10', HostResolver::resolveForTcp('192.0.2.10'));
    }

    public function testIpv6AddressIsReturnedUnchanged(): void
    {
        self::assertSame('2001:db8::1', HostResolver::resolveForTcp('2001:db8::1'));
    }

    public function testLocalhostPrefersIpv4AddressWhenResolvable(): void
    {
        $resolved = HostResolver::resolveForTcp('localhost');

        self::assertNotSame('', $resolved);
        self::assertTrue($resolved === 'localhost' || filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false);
    }

    public function testUnknownHostnameFallsBackToOriginalHost(): void
    {
        $host = 'nonexistent-host.invalid';

        self::assertSame($host, HostResolver::resolveForTcp($host));
    }

    public function testDsnCanUseResolvedConnectHostWithoutChangingStoredHost(): void
    {
        $config = new ExternalDatabaseConfig(
            'mysql',
            'dedi7208.your-server.de',
            3306,
            'luna_test',
            'luna_user',
            'secret',
        );

        self::assertSame('dedi7208.your-server.de', $config->host());
        self::assertStringContainsString('host=203.0.113.10', $config->dsn('203.0.113.10'));
        self::assertSame('dedi7208.your-server.de', $config->host());
    }
}
