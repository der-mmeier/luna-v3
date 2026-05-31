<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Mapping\LookupKeyTemplateRenderer;
use Luna\Mapping\LookupResult;
use Luna\Mapping\LookupValueProvider;
use Luna\Mapping\MappingFieldResolver;
use Luna\Mapping\PrefixLookupWarmupProvider;
use Luna\Transfer\MappingExecutionResult;
use Luna\Transfer\MappingRowTransformer;
use PHPUnit\Framework\TestCase;

final class LookupMappingResolverTest extends TestCase
{
    public function testSourceColumnReadsValueFromPrimarySource(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver()->resolve(['name' => 'E001 Carbon Partnerringe'], [], [
            'target_column' => 'name',
            'transform_type' => 'source_column',
            'source_column' => 'name',
        ], $result);

        self::assertSame('E001 Carbon Partnerringe', $value);
    }

    public function testStaticValueSetsFixedValue(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver()->resolve([], [], [
            'target_column' => 'status',
            'transform_type' => 'static_value',
            'default_value' => 'active',
        ], $result);

        self::assertSame('active', $value);
    }

    public function testLookupValueRendersPriceGroupTemplate(): void
    {
        $rendered = (new LookupKeyTemplateRenderer())->render('price_group_{{price_group}}', ['price_group' => 2], []);

        self::assertTrue($rendered->isValid());
        self::assertSame('price_group_2', $rendered->value);
    }

    public function testLookupValueRendersPseudoPriceTemplate(): void
    {
        $rendered = (new LookupKeyTemplateRenderer())->render('price_group_{{price_group}}_pseudo', ['price_group' => 2], []);

        self::assertTrue($rendered->isValid());
        self::assertSame('price_group_2_pseudo', $rendered->value);
    }

    public function testLookupValueResolvesValueFromSecondConnection(): void
    {
        $provider = new ArrayLookupProvider([
            '11:price_group_2' => 499.00,
        ]);
        $result = new MappingExecutionResult(true);
        $value = $this->resolver($provider)->resolve(['price_group' => 2], ['price_group' => 2], $this->lookupField('price', 11, 'price_group_{{price_group}}'), $result);

        self::assertSame(499.00, $value);
        self::assertSame('lookup_resolved', $result->resolverEvents()[0]['code']);
    }

    public function testLookupValueReportsMissingLookupKey(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new ArrayLookupProvider([]))->resolve(['price_group' => 9], ['price_group' => 9], $this->lookupField('price', 11, 'price_group_{{price_group}}'), $result);

        self::assertNull($value);
        self::assertSame('lookup_key_not_found', $result->resolverEvents()[0]['code']);
        self::assertFalse($result->isSuccessful());
    }

    public function testLookupValueReportsMissingTemplatePlaceholder(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver()->resolve([], [], $this->lookupField('price', 11, 'price_group_{{price_group}}'), $result);

        self::assertNull($value);
        self::assertSame('template_placeholder_missing', $result->resolverEvents()[0]['code']);
    }

    public function testLookupValueUsesFallback(): void
    {
        $result = new MappingExecutionResult(true);
        $field = array_merge($this->lookupField('price', 11, 'price_group_{{price_group}}'), [
            'missing_behavior' => 'fallback',
            'fallback_value' => '0.00',
        ]);
        $value = $this->resolver(new ArrayLookupProvider([]))->resolve(['price_group' => 9], ['price_group' => 9], $field, $result);

        self::assertSame('0.00', $value);
        self::assertSame('fallback_used', $result->resolverEvents()[0]['code']);
    }

    public function testMultipleLookupFieldsCanUseSameLookupConnection(): void
    {
        $provider = new ArrayLookupProvider([
            '11:price_group_2' => 499.00,
            '11:price_group_2_pseudo' => 599.00,
        ]);
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver($provider));
        $row = $transformer->transform(['price_group' => 2], [
            ['target_column' => 'price_group', 'transform_type' => 'source_column', 'source_column' => 'price_group'],
            $this->lookupField('price', 11, 'price_group_{{price_group}}'),
            $this->lookupField('pseudo_price', 11, 'price_group_{{price_group}}_pseudo'),
        ], $result);

        self::assertSame(['price_group' => 2, 'price' => 499.00, 'pseudo_price' => 599.00], $row);
    }

    public function testMultipleLookupFieldsCanUseDifferentLookupConnections(): void
    {
        $provider = new ArrayLookupProvider([
            '11:price_group_2' => 499.00,
            '12:price_group_2_pseudo' => 599.00,
        ]);
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver($provider));
        $row = $transformer->transform(['price_group' => 2], [
            ['target_column' => 'price_group', 'transform_type' => 'source_column', 'source_column' => 'price_group'],
            $this->lookupField('price', 11, 'price_group_{{price_group}}'),
            $this->lookupField('pseudo_price', 12, 'price_group_{{price_group}}_pseudo'),
        ], $result);

        self::assertSame(['price_group' => 2, 'price' => 499.00, 'pseudo_price' => 599.00], $row);
    }

    public function testDirectTransformWritesValueUnderOutputField(): void
    {
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver());

        $row = $transformer->transform(['customfield_asf_model' => 'E001'], [
            ['target_column' => 'model', 'transform_type' => 'direct', 'source_column' => 'customfield_asf_model'],
        ], $result);

        self::assertSame(['model' => 'E001'], $row);
    }

    public function testLookupValueWritesValueUnderOutputField(): void
    {
        $provider = new ArrayLookupProvider(['11:price_2' => 499]);
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver($provider));

        $row = $transformer->transform(['priceGroup' => 2], [
            $this->lookupField('price', 11, 'price_{{priceGroup}}'),
        ], $result);

        self::assertSame(['price' => 499], $row);
    }

    public function testKeyValueMapByPrefixWritesObjectUnderOutputField(): void
    {
        $provider = new ArrayLookupProvider([], [
            '13:E001' => ['E001D48' => '17', 'E001D50' => '22'],
        ]);
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver($provider));

        $row = $transformer->transform(['customfield_asf_model' => 'E001'], [
            $this->prefixMapField('quantities', 13, '{{customfield_asf_model}}'),
        ], $result);

        self::assertSame(['quantities' => ['D48' => 17, 'D50' => 22]], $row);
    }

    public function testIsrExampleMappingProducesExpectedOutputKeys(): void
    {
        $provider = new ArrayLookupProvider([
            '11:price_2' => 499,
            '11:pseudo_price_2' => 599,
        ], [
            '13:E001' => ['E001D48' => '17', 'E001D50' => '22'],
        ]);
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver($provider));

        $row = $transformer->transform([
            'customfield_asf_model' => 'E001',
            'name' => 'Carbon Partnerringe E001',
            'priceGroup' => 2,
        ], [
            ['target_column' => 'model', 'transform_type' => 'direct', 'source_column' => 'customfield_asf_model'],
            ['target_column' => 'name', 'transform_type' => 'direct', 'source_column' => 'name'],
            ['target_column' => 'price_group', 'transform_type' => 'direct', 'source_column' => 'priceGroup'],
            $this->lookupField('price', 11, 'price_{{priceGroup}}'),
            $this->lookupField('pseudo_price', 11, 'pseudo_price_{{priceGroup}}'),
            $this->prefixMapField('quantities', 13, '{{customfield_asf_model}}'),
        ], $result);

        self::assertSame(['model', 'name', 'price_group', 'price', 'pseudo_price', 'quantities'], array_keys($row));
        self::assertSame([
            'model' => 'E001',
            'name' => 'Carbon Partnerringe E001',
            'price_group' => 2,
            'price' => 499,
            'pseudo_price' => 599,
            'quantities' => ['D48' => 17, 'D50' => 22],
        ], $row);
    }

    public function testKeyValueMapByPrefixRemovesPrefixFromOutputKeys(): void
    {
        $provider = new ArrayLookupProvider([], [
            '13:E001' => ['E001D48' => '17', 'E001D50' => '22', 'E001H60' => '4'],
        ]);
        $result = new MappingExecutionResult(true);
        $value = $this->resolver($provider)->resolve(
            ['customfield_asf_model' => 'E001'],
            [],
            $this->prefixMapField('quantities', 13, '{{customfield_asf_model}}'),
            $result,
        );

        self::assertSame(['D48' => 17, 'D50' => 22, 'H60' => 4], $value);
    }

    public function testKeyValueMapByPrefixReturnsEmptyObjectForNoMatches(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new ArrayLookupProvider([], []))->resolve(
            ['customfield_asf_model' => 'E999'],
            [],
            $this->prefixMapField('quantities', 13, '{{customfield_asf_model}}'),
            $result,
        );

        self::assertInstanceOf(\stdClass::class, $value);
        self::assertSame('{}', json_encode($value));
    }

    public function testFirstNonEmptyUsesOldNameWhenFilled(): void
    {
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver());

        $row = $transformer->transform([
            'old_name' => 'DR01',
            'customfield_asf_model' => 'DR001',
        ], [
            $this->firstNonEmptyField('stock_model'),
        ], $result);

        self::assertSame(['stock_model' => 'DR01'], $row);
    }

    public function testFirstNonEmptyUsesFallbackColumnWhenOldNameIsEmpty(): void
    {
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver());

        $row = $transformer->transform([
            'old_name' => '',
            'customfield_asf_model' => 'W001',
        ], [
            $this->firstNonEmptyField('stock_model'),
        ], $result);

        self::assertSame(['stock_model' => 'W001'], $row);
    }

    public function testFirstNonEmptyTreatsWhitespaceAsEmpty(): void
    {
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver());

        $row = $transformer->transform([
            'old_name' => '   ',
            'customfield_asf_model' => 'W001',
        ], [
            $this->firstNonEmptyField('stock_model'),
        ], $result);

        self::assertSame(['stock_model' => 'W001'], $row);
    }

    public function testPrefixTemplateCanUseComputedStockModel(): void
    {
        $provider = new ArrayLookupProvider([], [
            '13:DR01D' => ['DR01D48' => '17'],
        ]);
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver($provider));

        $row = $transformer->transform([
            'customfield_asf_model' => 'DR001',
            'old_name' => 'DR01',
        ], [
            $this->firstNonEmptyField('stock_model'),
            $this->prefixMapField('dr_quantities', 13, '{{stock_model}}D'),
        ], $result);

        self::assertSame('DR01', $row['stock_model']);
        self::assertSame(['48' => 17], $row['dr_quantities']);
    }

    public function testComputedStockModelFallsBackToModelForPrefixLookup(): void
    {
        $provider = new ArrayLookupProvider([], [
            '13:W001D' => ['W001D48' => '47'],
        ]);
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver($provider));

        $row = $transformer->transform([
            'customfield_asf_model' => 'W001',
            'old_name' => null,
        ], [
            $this->firstNonEmptyField('stock_model'),
            $this->prefixMapField('dr_quantities', 13, '{{stock_model}}D'),
        ], $result);

        self::assertSame('W001', $row['stock_model']);
        self::assertSame(['48' => 47], $row['dr_quantities']);
    }

    public function testPrefixWarmupCanUseComputedStockModel(): void
    {
        $provider = new WarmupRecordingLookupProvider();
        $result = new MappingExecutionResult(true);
        $resolver = $this->resolver($provider);

        $resolver->warmUpPrefixLookups([
            ['customfield_asf_model' => 'DR001', 'old_name' => 'DR01'],
            ['customfield_asf_model' => 'W001', 'old_name' => ' '],
        ], [
            $this->firstNonEmptyField('stock_model'),
            $this->prefixMapField('dr_quantities', 13, '{{stock_model}}D'),
        ], $result);

        self::assertSame(['DR01D', 'W001D'], $provider->prefixes);
    }

    public function testDryRunSummaryContainsJsonCompatibleTransferPreviewAndNoSecrets(): void
    {
        $provider = new ArrayLookupProvider(['11:price_group_2' => 499.00]);
        $result = new MappingExecutionResult(true);
        $transformer = new MappingRowTransformer(null, $this->resolver($provider));
        $row = $transformer->transform(['name' => 'E001', 'price_group' => 2, 'password' => 'secret'], [
            ['target_column' => 'name', 'transform_type' => 'source_column', 'source_column' => 'name'],
            ['target_column' => 'source_system', 'transform_type' => 'static_value', 'default_value' => 'pimcore'],
            $this->lookupField('price', 11, 'price_group_{{price_group}}'),
        ], $result);
        $result->addSourceRow(['name' => 'E001', 'price_group' => 2, 'password' => 'secret']);
        $result->addPreviewRow($row);
        $result->addPreviewRecord(['name' => 'E001', 'price_group' => 2, 'password' => 'secret'], $row, $result->resolverEvents());
        $summary = $result->toSummaryArray();
        $json = json_encode($summary, JSON_THROW_ON_ERROR);

        self::assertSame('E001', $summary['transfer_preview'][0]['name']);
        self::assertSame('E001', $summary['primary_source_preview'][0]['name']);
        self::assertSame('***', $summary['primary_source_preview'][0]['password']);
        self::assertSame(499.00, $summary['transfer_preview'][0]['price']);
        self::assertSame('price_group_2', $summary['records'][0]['lookups'][0]['rendered_key']);
        self::assertSame('found', $summary['records'][0]['lookups'][0]['status']);
        self::assertSame('E001', $summary['records'][0]['source']['name']);
        self::assertStringNotContainsString('secret', $json);
    }

    private function resolver(?LookupValueProvider $provider = null): MappingFieldResolver
    {
        return new MappingFieldResolver($provider ?? new ArrayLookupProvider([]));
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupField(string $targetColumn, int $connectionId, string $template): array
    {
        return [
            'target_column' => $targetColumn,
            'transform_type' => 'lookup_value',
            'lookup_connection_id' => $connectionId,
            'lookup_table' => 'price_settings',
            'lookup_key_column' => 'setting_key',
            'lookup_value_column' => 'setting_value',
            'lookup_key_template' => $template,
            'missing_behavior' => 'error',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prefixMapField(string $targetColumn, int $connectionId, string $template): array
    {
        return [
            'target_column' => $targetColumn,
            'transform_type' => 'key_value_map_by_prefix',
            'lookup_connection_id' => $connectionId,
            'lookup_table' => 'products',
            'lookup_key_column' => 'product_code',
            'lookup_value_column' => 'quantity',
            'lookup_key_template' => $template,
            'missing_behavior' => 'error',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function firstNonEmptyField(string $targetColumn): array
    {
        return [
            'target_column' => $targetColumn,
            'transform_type' => 'first_non_empty',
            'source_column' => 'old_name,customfield_asf_model',
        ];
    }
}

final class ArrayLookupProvider implements LookupValueProvider
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private readonly array $values,
        private readonly array $prefixValues = [],
    )
    {
    }

    public function lookup(array $field, string $key): LookupResult
    {
        $lookupKey = (int) $field['lookup_connection_id'] . ':' . $key;

        return array_key_exists($lookupKey, $this->values)
            ? LookupResult::found($this->values[$lookupKey])
            : LookupResult::error('lookup_key_not_found');
    }

    public function lookupByPrefix(array $field, string $prefix): LookupResult
    {
        $lookupKey = (int) $field['lookup_connection_id'] . ':' . $prefix;
        $rows = $this->prefixValues[$lookupKey] ?? [];

        if ($rows === []) {
            return LookupResult::found((object) []);
        }

        $map = [];
        foreach ($rows as $key => $value) {
            $outputKey = str_starts_with((string) $key, $prefix) ? substr((string) $key, strlen($prefix)) : (string) $key;
            $map[$outputKey] = is_numeric($value) ? (int) $value : $value;
        }

        return LookupResult::found($map);
    }
}

final class WarmupRecordingLookupProvider implements LookupValueProvider, PrefixLookupWarmupProvider
{
    /** @var list<string> */
    public array $prefixes = [];

    public function lookup(array $field, string $key): LookupResult
    {
        return LookupResult::error('lookup_key_not_found');
    }

    public function lookupByPrefix(array $field, string $prefix): LookupResult
    {
        return LookupResult::found((object) []);
    }

    public function warmUpPrefixLookups(array $requests): array
    {
        foreach ($requests as $request) {
            foreach ($request['prefixes'] as $prefix) {
                $this->prefixes[] = $prefix;
            }
        }

        return ['prefix_lookup_prefixes' => count($this->prefixes)];
    }
}
