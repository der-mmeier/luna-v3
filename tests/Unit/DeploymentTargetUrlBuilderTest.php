<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use InvalidArgumentException;
use Luna\Deployment\DeploymentTargetUrlBuilder;
use PHPUnit\Framework\TestCase;

final class DeploymentTargetUrlBuilderTest extends TestCase
{
    public function testTrailingSlashesAreRemoved(): void
    {
        self::assertSame('https://toolbox.example.com/luna', $this->builder()->normalizeBaseUrl('https://toolbox.example.com/luna///'));
    }

    public function testEndpointBaseUrlIsPreferred(): void
    {
        $url = $this->builder()->endpointUrl([
            'public_base_url' => 'https://toolbox.example.com/luna',
            'endpoint_base_url' => 'https://api.example.com/luna/endpoints/',
        ], 'isr_prices');

        self::assertSame('https://api.example.com/luna/endpoints/isr_prices', $url);
    }

    public function testPublicBaseUrlFallbackBuildsEndpointUrl(): void
    {
        $url = $this->builder()->endpointUrl([
            'public_base_url' => 'https://toolbox.example.com/luna/',
            'endpoint_base_url' => null,
        ], 'isr_prices');

        self::assertSame('https://toolbox.example.com/luna/api/endpoints/isr_prices', $url);
    }

    public function testLocalhostAndHttpAreAllowed(): void
    {
        self::assertSame('http://localhost:8080/luna', $this->builder()->normalizeBaseUrl('http://localhost:8080/luna/'));
    }

    public function testUnsupportedSchemesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->normalizeBaseUrl('ftp://example.com/luna');
    }

    public function testEmbeddedCredentialsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->normalizeBaseUrl('https://user:pass@example.com/luna');
    }

    public function testEndpointSlugIsNormalizedAndAppended(): void
    {
        $url = $this->builder()->endpointUrl([
            'public_base_url' => 'https://toolbox.example.com/luna',
        ], '/ISR Prices/');

        self::assertSame('https://toolbox.example.com/luna/api/endpoints/isr-prices', $url);
    }

    private function builder(): DeploymentTargetUrlBuilder
    {
        return new DeploymentTargetUrlBuilder();
    }
}
