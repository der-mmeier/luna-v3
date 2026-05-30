<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Mapping\LookupResult;
use Luna\Mapping\LookupValueProvider;
use Luna\Mapping\MappingFieldResolver;
use Luna\Mapping\PdoLookupValueProvider;
use Luna\Mapping\PrefixLookupWarmupProvider;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Security\EncryptionService;
use Luna\Transfer\MappingExecutionResult;
use Luna\Transfer\MappingRowTransformer;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PrefixLookupWarmupTest extends TestCase
{
    public function testPdoProviderCanBatchLoadMultiplePrefixes(): void
    {
        $provider = $this->pdoProvider($this->productsPdo());
        $diagnostics = $provider->warmUpPrefixLookups([[
            'field' => $this->prefixField('quantities', '{{model}}D'),
            'prefixes' => ['W001D', 'W002D'],
        ]]);

        $w001 = $provider->lookupByPrefix($this->prefixField('quantities', '{{model}}D'), 'W001D');
        $w002 = $provider->lookupByPrefix($this->prefixField('quantities', '{{model}}D'), 'W002D');

        self::assertSame(1, $diagnostics['prefix_lookup_batch_queries']);
        self::assertSame(1, $diagnostics['prefix_lookup_queries']);
        self::assertEquals(['48' => 47, '50' => 34], $w001->value);
        self::assertEquals(['48' => 12], $w002->value);
    }

    public function testPdoProviderKeepsEmptyPrefixResultAsObject(): void
    {
        $provider = $this->pdoProvider($this->productsPdo());
        $provider->warmUpPrefixLookups([[
            'field' => $this->prefixField('quantities', '{{model}}D'),
            'prefixes' => ['W999D'],
        ]]);

        $result = $provider->lookupByPrefix($this->prefixField('quantities', '{{model}}D'), 'W999D');

        self::assertInstanceOf(\stdClass::class, $result->value);
        self::assertSame('{}', json_encode($result->value));
    }

    public function testPdoProviderFallbackSingleQueryStillWorksWithoutWarmup(): void
    {
        $provider = $this->pdoProvider($this->productsPdo());
        $result = $provider->lookupByPrefix($this->prefixField('quantities', '{{model}}D'), 'W001D');

        self::assertTrue($result->found);
        self::assertEquals(['48' => 47, '50' => 34], $result->value);
    }

    public function testTwoPrefixRulesWithOneHundredThirtyOneRowsDoNotCreateTwoHundredSixtyTwoQueries(): void
    {
        $prefixesD = [];
        $prefixesH = [];
        for ($index = 1; $index <= 131; $index++) {
            $model = sprintf('W%03d', $index);
            $prefixesD[] = $model . 'D';
            $prefixesH[] = $model . 'H';
        }

        $provider = $this->pdoProvider($this->productsPdo());
        $diagnostics = $provider->warmUpPrefixLookups([
            [
                'field' => $this->prefixField('dr_quantities', '{{model}}D'),
                'prefixes' => $prefixesD,
            ],
            [
                'field' => $this->prefixField('hr_quantities', '{{model}}H'),
                'prefixes' => $prefixesH,
            ],
        ]);

        self::assertSame(2, $diagnostics['prefix_lookup_rules']);
        self::assertSame(262, $diagnostics['prefix_lookup_prefixes']);
        self::assertLessThan(262, $diagnostics['prefix_lookup_batch_queries']);
        self::assertSame(3, $diagnostics['prefix_lookup_batch_queries']);
    }

    public function testTransformerWarmupMakesLookupByPrefixUseCache(): void
    {
        $provider = new RecordingPrefixLookupProvider([
            'W001D48' => 47,
            'W001D50' => 34,
            'W001H56' => 25,
            'W001H58' => 31,
        ]);
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, new MappingFieldResolver($provider));
        $rows = [['model' => 'W001']];
        $fields = [
            $this->prefixField('dr_quantities', '{{model}}D'),
            $this->prefixField('hr_quantities', '{{model}}H'),
        ];

        $transformer->warmUpPrefixLookups($rows, $fields, $result);
        $output = $transformer->transform($rows[0], $fields, $result);

        self::assertSame([
            'dr_quantities' => ['48' => 47, '50' => 34],
            'hr_quantities' => ['56' => 25, '58' => 31],
        ], $output);
        self::assertSame(1, $provider->warmupCalls);
        self::assertSame(0, $provider->fallbackQueries);
        self::assertSame(2, $provider->lookupByPrefixCalls);
        self::assertSame(2, $result->toSummaryArray()['diagnostics']['prefix_lookup_prefixes']);
    }

    private function productsPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE products (product_code TEXT, quantity TEXT)');
        $pdo->exec("INSERT INTO products (product_code, quantity) VALUES ('W001D48', '47')");
        $pdo->exec("INSERT INTO products (product_code, quantity) VALUES ('W001D50', '34')");
        $pdo->exec("INSERT INTO products (product_code, quantity) VALUES ('W002D48', '12')");
        $pdo->exec("INSERT INTO products (product_code, quantity) VALUES ('W001H56', '25')");
        $pdo->exec("INSERT INTO products (product_code, quantity) VALUES ('W001H58', '31')");

        return $pdo;
    }

    private function pdoProvider(PDO $pdo): PdoLookupValueProvider
    {
        $provider = new PdoLookupValueProvider(
            new ConnectionProfileRepository(
                new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory()),
                new EncryptionService(new Config()),
            ),
            new ExternalPdoConnectionFactory(),
        );

        $pdoCache = new ReflectionProperty(PdoLookupValueProvider::class, 'pdoCache');
        $pdoCache->setValue($provider, [13 => $pdo]);

        $schemaErrorCache = new ReflectionProperty(PdoLookupValueProvider::class, 'schemaErrorCache');
        $schemaErrorCache->setValue($provider, ['13|products|product_code|quantity' => null]);

        return $provider;
    }

    /**
     * @return array<string, mixed>
     */
    private function prefixField(string $targetColumn, string $template): array
    {
        return [
            'target_column' => $targetColumn,
            'transform_type' => 'key_value_map_by_prefix',
            'lookup_connection_id' => 13,
            'lookup_table' => 'products',
            'lookup_key_column' => 'product_code',
            'lookup_value_column' => 'quantity',
            'lookup_key_template' => $template,
        ];
    }
}

final class RecordingPrefixLookupProvider implements LookupValueProvider, PrefixLookupWarmupProvider
{
    public int $warmupCalls = 0;
    public int $lookupByPrefixCalls = 0;
    public int $fallbackQueries = 0;

    /**
     * @var array<string, LookupResult>
     */
    private array $cache = [];

    /**
     * @param array<string, int> $rows
     */
    public function __construct(private readonly array $rows)
    {
    }

    public function lookup(array $field, string $key): LookupResult
    {
        return LookupResult::error('lookup_key_not_found');
    }

    public function lookupByPrefix(array $field, string $prefix): LookupResult
    {
        $this->lookupByPrefixCalls++;
        $cacheKey = $this->cacheKey($field, $prefix);

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $this->fallbackQueries++;
        $result = $this->resultForPrefix($prefix);
        $this->cache[$cacheKey] = $result;

        return $result;
    }

    public function warmUpPrefixLookups(array $requests): array
    {
        $this->warmupCalls++;
        $prefixCount = 0;

        foreach ($requests as $request) {
            foreach ($request['prefixes'] as $prefix) {
                $prefixCount++;
                $this->cache[$this->cacheKey($request['field'], $prefix)] = $this->resultForPrefix($prefix);
            }
        }

        return [
            'prefix_lookup_queries' => $requests === [] ? 0 : 1,
            'prefix_lookup_prefixes' => $prefixCount,
            'prefix_lookup_rules' => count($requests),
            'prefix_lookup_batch_queries' => $requests === [] ? 0 : 1,
            'prefix_lookup_runtime_ms' => 0.1,
        ];
    }

    private function resultForPrefix(string $prefix): LookupResult
    {
        $map = [];

        foreach ($this->rows as $key => $value) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            $map[substr($key, strlen($prefix))] = $value;
        }

        return LookupResult::found($map === [] ? (object) [] : $map);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function cacheKey(array $field, string $prefix): string
    {
        return implode('|', [
            (string) ($field['lookup_connection_id'] ?? ''),
            (string) ($field['lookup_table'] ?? ''),
            (string) ($field['lookup_key_column'] ?? ''),
            (string) ($field['lookup_value_column'] ?? ''),
            $prefix,
        ]);
    }
}
