<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Mapping\LookupKeyTemplateRenderer;
use Luna\Mapping\LookupResult;
use Luna\Mapping\LookupValueProvider;
use Luna\Mapping\MappingFieldResolver;
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
}

final class ArrayLookupProvider implements LookupValueProvider
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private readonly array $values)
    {
    }

    public function lookup(array $field, string $key): LookupResult
    {
        $lookupKey = (int) $field['lookup_connection_id'] . ':' . $key;

        return array_key_exists($lookupKey, $this->values)
            ? LookupResult::found($this->values[$lookupKey])
            : LookupResult::error('lookup_key_not_found');
    }
}
