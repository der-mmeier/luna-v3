<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Api\EndpointJsonResponseFactory;
use Luna\Api\EndpointRunner;
use Luna\Api\EndpointSecretPolicy;
use Luna\Config\Config;
use Luna\Core\Application;
use Luna\Core\Paths;
use Luna\Http\Request;
use Luna\Http\Response;
use Luna\Routing\RouteCollection;
use Luna\Transfer\MappingExecutionResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EndpointBuilderV2Test extends TestCase
{
    public function testSuccessfulJsonResponseContainsStandardEnvelope(): void
    {
        $runner = $this->runner([7 => ['id' => 7, 'workspace_id' => 3]], $this->successfulExecution([
            ['model' => 'E001', 'price_group' => 2, 'price' => 499.00],
            ['model' => 'E002', 'price_group' => 3, 'price' => 699.00],
        ]));

        $response = $runner->run($this->endpoint());
        $body = $this->jsonBody($response);

        self::assertSame(200, $response->statusCode());
        self::assertSame(true, $body['success']);
        self::assertIsString($body['generated_at']);
        self::assertSame(2, $body['count']);
        self::assertSame('E001', $body['items'][0]['model']);
        self::assertEquals(499.00, $body['items'][0]['price']);
    }

    public function testEmptyMappingResultReturnsEmptyItems(): void
    {
        $response = $this->runner([7 => ['id' => 7, 'workspace_id' => 3]], $this->successfulExecution([]))->run($this->endpoint());
        $body = $this->jsonBody($response);

        self::assertSame(true, $body['success']);
        self::assertSame(0, $body['count']);
        self::assertSame([], $body['items']);
    }

    public function testNestedQuantitiesObjectIsSerializedInItems(): void
    {
        $response = $this->runner([7 => ['id' => 7, 'workspace_id' => 3]], $this->successfulExecution([
            ['model' => 'E001', 'quantities' => ['D48' => 17, 'D50' => 22]],
        ]))->run($this->endpoint());
        $body = $this->jsonBody($response);

        self::assertSame(17, $body['items'][0]['quantities']['D48']);
        self::assertSame(22, $body['items'][0]['quantities']['D50']);
    }

    public function testPublicRuntimeUsesUncappedOutputRows(): void
    {
        $items = [];
        for ($i = 1; $i <= 25; $i++) {
            $items[] = ['model' => 'E' . $i];
        }

        $response = $this->runner([7 => ['id' => 7, 'workspace_id' => 3]], $this->successfulExecution($items))->run($this->endpoint());
        $body = $this->jsonBody($response);

        self::assertSame(25, $body['count']);
        self::assertSame('E25', $body['items'][24]['model']);
    }

    public function testMappingNotFoundUsesStandardErrorFormat(): void
    {
        $response = $this->runner([], $this->successfulExecution([]))->run($this->endpoint());
        $body = $this->jsonBody($response);

        self::assertSame(404, $response->statusCode());
        self::assertSame(false, $body['success']);
        self::assertSame('mapping_not_found', $body['error']['code']);
    }

    public function testWorkspaceMismatchUsesStandardErrorFormat(): void
    {
        $response = $this->runner([7 => ['id' => 7, 'workspace_id' => 9]], $this->successfulExecution([]))->run($this->endpoint());
        $body = $this->jsonBody($response);

        self::assertSame(422, $response->statusCode());
        self::assertSame('mapping_workspace_mismatch', $body['error']['code']);
    }

    public function testMappingRunnerFailureDoesNotExposeSecretOrStacktrace(): void
    {
        $runner = new EndpointRunner(
            new EndpointJsonResponseFactory(),
            static fn (int $id): ?array => ['id' => $id, 'workspace_id' => 3],
            static fn (): MappingExecutionResult => throw new RuntimeException('password=secret stack trace SQL mysql://root:secret@db'),
        );

        $response = $runner->run($this->endpoint());
        $json = $response->body();
        $body = $this->jsonBody($response);

        self::assertSame(500, $response->statusCode());
        self::assertSame('mapping_execution_failed', $body['error']['code']);
        self::assertStringNotContainsString('password=secret', $json);
        self::assertStringNotContainsString('mysql://', $json);
        self::assertStringNotContainsString('stack trace', strtolower($json));
    }

    public function testSecretRequiredWithoutSecretIsUnauthorized(): void
    {
        $decision = (new EndpointSecretPolicy())->check(
            ['secret_mode' => 'required'],
            new Request('GET', '/api/endpoints/isr_prices'),
            static fn (string $secret): bool => $secret === 'expected',
        );

        self::assertSame(401, $decision['status'] ?? null);
        self::assertSame('secret_missing', $decision['code'] ?? null);
    }

    public function testSecretRequiredWithWrongSecretIsForbidden(): void
    {
        $decision = (new EndpointSecretPolicy())->check(
            ['secret_mode' => 'required'],
            new Request('GET', '/api/endpoints/isr_prices', ['secret' => 'wrong']),
            static fn (string $secret): bool => $secret === 'expected',
        );

        self::assertSame(403, $decision['status'] ?? null);
        self::assertSame('secret_invalid', $decision['code'] ?? null);
    }

    public function testSecretRequiredWithCorrectHeaderSecretPasses(): void
    {
        $decision = (new EndpointSecretPolicy())->check(
            ['secret_mode' => 'required'],
            new Request('GET', '/api/endpoints/isr_prices', [], [], [], ['x-luna-endpoint-secret' => 'expected']),
            static fn (string $secret): bool => hash_equals('expected', $secret),
        );

        self::assertNull($decision);
    }

    public function testPublicRuntimeRouteAndMethodNotAllowedRouteAreRegistered(): void
    {
        $routes = $this->loadApiRoutes();
        $get = new Request('GET', '/api/endpoints/isr_prices');
        $post = new Request('POST', '/api/endpoints/isr_prices');

        self::assertSame('api.endpoints.runtime', $routes->match($get)?->name());
        self::assertSame('api.endpoints.runtime_post', $routes->match($post)?->name());
    }

    public function testErrorFactoryMasksSensitiveKeys(): void
    {
        $response = (new EndpointJsonResponseFactory())->success([
            ['model' => 'E001', 'password' => 'secret-value', 'dsn' => 'mysql://root:secret@db'],
        ]);
        $json = $response->body();
        $body = $this->jsonBody($response);

        self::assertSame('***', $body['items'][0]['password']);
        self::assertSame('***', $body['items'][0]['dsn']);
        self::assertStringNotContainsString('secret-value', $json);
        self::assertStringNotContainsString('mysql://', $json);
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     */
    private function runner(array $mappings, MappingExecutionResult $execution): EndpointRunner
    {
        return new EndpointRunner(
            new EndpointJsonResponseFactory(),
            static fn (int $id): ?array => $mappings[$id] ?? null,
            static fn (): MappingExecutionResult => $execution,
        );
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function successfulExecution(array $items): MappingExecutionResult
    {
        $result = new MappingExecutionResult(true);
        foreach ($items as $item) {
            $result->addPreviewRow($item);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function endpoint(): array
    {
        return [
            'id' => 5,
            'workspace_id' => 3,
            'mapping_set_id' => 7,
            'source_type' => 'mapping',
            'config_json' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(Response $response): array
    {
        $decoded = json_decode($response->body(), true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function loadApiRoutes(): RouteCollection
    {
        $basePath = dirname(__DIR__, 2);
        $app = new Application(new Paths($basePath), new Config());
        $routes = new RouteCollection();
        $loader = require $basePath . '/routes/api.php';

        $loader($routes, $app);

        return $routes;
    }
}
