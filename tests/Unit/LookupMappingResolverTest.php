<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Mapping\LookupKeyTemplateRenderer;
use Luna\Mapping\LookupMatchMode;
use Luna\Mapping\LookupResult;
use Luna\Mapping\LookupResultMode;
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

    public function testPrefixLookupCanReturnListOfMultipleMatches(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new PatternLookupProvider([
            ['product_code' => 'S001D48', 'quantity' => '1'],
            ['product_code' => 'S001D50', 'quantity' => '2'],
            ['product_code' => 'S001H60', 'quantity' => '1'],
        ]))->resolve(
            ['customfield_asf_model' => 'S001'],
            [],
            $this->patternField('stock_values', 'prefix', 'list', '{{customfield_asf_model}}'),
            $result,
        );

        self::assertSame(['1', '2', '1'], $value);
        self::assertSame(3, $result->resolverEvents()[0]['context']['match_count']);
        self::assertSame('S001%', $result->resolverEvents()[0]['context']['rendered_pattern']);
    }

    public function testSuffixLookupFindsMatchingValues(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new PatternLookupProvider([
            ['product_code' => 'S001D48', 'quantity' => '1'],
            ['product_code' => 'S002D48', 'quantity' => '3'],
        ]))->resolve(['size_code' => 'D48'], [], $this->patternField('stock_count', 'suffix', 'count', '{{size_code}}'), $result);

        self::assertSame(2, $value);
    }

    public function testContainsLookupFindsMatchingValues(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new PatternLookupProvider([
            ['product_code' => 'S001D48', 'quantity' => '1'],
            ['product_code' => 'S002H60', 'quantity' => '3'],
        ]))->resolve(['material_code' => 'H'], [], $this->patternField('stock_count', 'contains', 'count', '{{material_code}}'), $result);

        self::assertSame(1, $value);
    }

    public function testLikeLookupUsesRenderedTemplateAsPattern(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new PatternLookupProvider([
            ['product_code' => 'S001D48', 'quantity' => '1'],
            ['product_code' => 'S001H60', 'quantity' => '2'],
            ['product_code' => 'S002H60', 'quantity' => '5'],
        ]))->resolve(['customfield_asf_model' => 'S001'], [], $this->patternField('stock_count', 'like', 'count', '{{customfield_asf_model}}%'), $result);

        self::assertSame(2, $value);
        self::assertSame('S001%', $result->resolverEvents()[0]['context']['rendered_pattern']);
    }

    public function testResultModesReduceMultipleMatches(): void
    {
        $values = ['2', '5', '1'];

        self::assertSame('2', LookupResultMode::reduce($values, 'first')->value);
        self::assertSame($values, LookupResultMode::reduce($values, 'list')->value);
        self::assertSame(3, LookupResultMode::reduce($values, 'count')->value);
        self::assertSame(8.0, LookupResultMode::reduce($values, 'sum')->value);
        self::assertSame(1.0, LookupResultMode::reduce($values, 'min')->value);
        self::assertSame(5.0, LookupResultMode::reduce($values, 'max')->value);
    }

    public function testNonNumericSumReportsSafeError(): void
    {
        $result = LookupResultMode::reduce(['2', 'secret-value'], 'sum');

        self::assertFalse($result->found);
        self::assertSame('non_numeric_lookup_value', $result->errorCode);
        self::assertStringNotContainsString('password', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function testEmptyRenderedPatternIsNotLookedUp(): void
    {
        $provider = new PatternLookupProvider([['product_code' => 'S001D48', 'quantity' => '1']]);
        $result = new MappingExecutionResult(true);
        $value = $this->resolver($provider)->resolve(['customfield_asf_model' => ''], [], array_merge(
            $this->patternField('stock_count', 'prefix', 'count', '{{customfield_asf_model}}'),
            ['missing_behavior' => 'nullable'],
        ), $result);

        self::assertNull($value);
        self::assertSame(0, $provider->calls);
        self::assertSame('lookup_key_empty', $result->resolverEvents()[0]['code']);
    }

    public function testMultipleMatchesAreAllowedForListMode(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new PatternLookupProvider([
            ['product_code' => 'S001D48', 'quantity' => '1'],
            ['product_code' => 'S001D50', 'quantity' => '2'],
        ]))->resolve(['customfield_asf_model' => 'S001'], [], $this->patternField('stock_values', 'prefix', 'list', '{{customfield_asf_model}}'), $result);

        self::assertSame(['1', '2'], $value);
        self::assertTrue($result->isSuccessful());
    }

    public function testKeyValueMapReturnsLookupKeysWithoutTransformation(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new PatternLookupProvider([
            ['product_code' => 'S001D48', 'quantity' => 17],
            ['product_code' => 'S001D50', 'quantity' => 22],
            ['product_code' => 'S001H60', 'quantity' => 5],
        ]))->resolve(
            ['customfield_asf_model' => 'S001'],
            [],
            array_merge($this->patternField('quantities', 'prefix', 'key_value_map', '{{customfield_asf_model}}'), [
                'lookup_result_key_column' => 'product_code',
            ]),
            $result,
        );

        self::assertSame(['S001D48' => 17, 'S001D50' => 22, 'S001H60' => 5], $value);
        self::assertSame('key_value_map', $result->resolverEvents()[0]['context']['lookup_result_mode']);
        self::assertSame('product_code', $result->resolverEvents()[0]['context']['lookup_result_key_column']);
    }

    public function testKeyValueMapCanRemoveRenderedPrefix(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new PatternLookupProvider([
            ['product_code' => 'S001D48', 'quantity' => 17],
            ['product_code' => 'S001D50', 'quantity' => 22],
        ]))->resolve(
            ['customfield_asf_model' => 'S001'],
            [],
            array_merge($this->patternField('quantities', 'prefix', 'key_value_map', '{{customfield_asf_model}}'), [
                'lookup_result_key_column' => 'product_code',
                'lookup_result_key_transform' => 'remove_prefix',
                'lookup_result_key_prefix_template' => '{{customfield_asf_model}}',
            ]),
            $result,
        );

        self::assertSame(['D48' => 17, 'D50' => 22], $value);
        self::assertSame('S001', $result->resolverEvents()[0]['context']['rendered_result_key_prefix']);
    }

    public function testMissingResultKeyColumnIsReported(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new PatternLookupProvider([
            ['product_code' => 'S001D48', 'quantity' => 17],
        ]))->resolve(
            ['customfield_asf_model' => 'S001'],
            [],
            $this->patternField('quantities', 'prefix', 'key_value_map', '{{customfield_asf_model}}'),
            $result,
        );

        self::assertNull($value);
        self::assertSame('missing_result_key_column', $result->resolverEvents()[0]['code']);
    }

    public function testEmptyResultKeyIsReported(): void
    {
        $lookupResult = LookupResultMode::reduceRows([
            ['result_key' => '', 'lookup_value' => 17],
        ], 'key_value_map');

        self::assertFalse($lookupResult->found);
        self::assertSame('empty_result_key', $lookupResult->errorCode);
    }

    public function testDuplicateResultKeysAreKeptAsListAndWarned(): void
    {
        $lookupResult = LookupResultMode::reduceRows([
            ['result_key' => 'D48', 'lookup_value' => 17],
            ['result_key' => 'D48', 'lookup_value' => 22],
        ], 'key_value_map');

        self::assertTrue($lookupResult->found);
        self::assertSame(['D48' => [17, 22]], $lookupResult->value);
        self::assertSame(['duplicate_result_key'], $lookupResult->warnings);

        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new PatternLookupProvider([
            ['product_code' => 'S001D48', 'quantity' => 17],
            ['product_code' => 'S001D48', 'quantity' => 22],
        ]))->resolve(
            ['customfield_asf_model' => 'S001'],
            [],
            array_merge($this->patternField('quantities', 'prefix', 'key_value_map', '{{customfield_asf_model}}'), [
                'lookup_result_key_column' => 'product_code',
            ]),
            $result,
        );

        self::assertSame(['S001D48' => [17, 22]], $value);
        self::assertSame('duplicate_result_key', $result->resolverEvents()[1]['code']);
    }

    public function testMissingPrefixOnResultKeyKeepsKeyAndWarns(): void
    {
        $lookupResult = LookupResultMode::reduceRows([
            ['result_key' => 'S002D48', 'lookup_value' => 17],
        ], 'key_value_map', [
            'key_transform' => 'remove_prefix',
            'rendered_prefix' => 'S001',
        ]);

        self::assertTrue($lookupResult->found);
        self::assertSame(['S002D48' => 17], $lookupResult->value);
        self::assertSame(['prefix_not_found_on_result_key'], $lookupResult->warnings);
    }

    public function testMissingResultKeyPrefixPlaceholderIsReported(): void
    {
        $result = new MappingExecutionResult(true);
        $value = $this->resolver(new PatternLookupProvider([
            ['product_code' => 'S001D48', 'quantity' => 17],
        ]))->resolve(
            ['customfield_asf_model' => 'S001'],
            [],
            array_merge($this->patternField('quantities', 'prefix', 'key_value_map', '{{customfield_asf_model}}'), [
                'lookup_result_key_column' => 'product_code',
                'lookup_result_key_transform' => 'remove_prefix',
                'lookup_result_key_prefix_template' => '{{model_prefix}}',
                'missing_behavior' => 'nullable',
            ]),
            $result,
        );

        self::assertNull($value);
        self::assertSame('template_placeholder_missing', $result->resolverEvents()[0]['code']);
    }

    public function testKeyValueMapErrorsDoNotExposeSecrets(): void
    {
        $lookupResult = LookupResultMode::reduceRows([
            ['lookup_value' => 'secret-value'],
        ], 'key_value_map');
        $json = json_encode($lookupResult, JSON_THROW_ON_ERROR);

        self::assertFalse($lookupResult->found);
        self::assertSame('missing_result_key_column', $lookupResult->errorCode);
        self::assertStringNotContainsString('password', $json);
        self::assertStringNotContainsString('APP_KEY', $json);
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
    private function patternField(string $targetColumn, string $matchMode, string $resultMode, string $template): array
    {
        return [
            'target_column' => $targetColumn,
            'transform_type' => 'lookup_value',
            'lookup_connection_id' => 22,
            'lookup_table' => 'products',
            'lookup_key_column' => 'product_code',
            'lookup_value_column' => 'quantity',
            'lookup_key_template' => $template,
            'lookup_match_mode' => $matchMode,
            'lookup_result_mode' => $resultMode,
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

final class PatternLookupProvider implements LookupValueProvider
{
    public int $calls = 0;

    /**
     * @param list<array{product_code: string, quantity: mixed}> $rows
     */
    public function __construct(private readonly array $rows)
    {
    }

    public function lookup(array $field, string $key): LookupResult
    {
        $this->calls++;
        $mode = LookupMatchMode::normalize(isset($field['lookup_match_mode']) ? (string) $field['lookup_match_mode'] : null);
        $resultMode = LookupResultMode::normalize(isset($field['lookup_result_mode']) ? (string) $field['lookup_result_mode'] : null);

        if (! LookupMatchMode::hasSearchValue($mode, $key)) {
            return LookupResult::error('lookup_key_empty');
        }

        if ($resultMode === 'key_value_map' && empty($field['lookup_result_key_column'])) {
            return LookupResult::error('missing_result_key_column');
        }

        $pattern = LookupMatchMode::parameter($mode, $key);
        $rows = [];

        foreach ($this->rows as $row) {
            if ($this->matches($row['product_code'], $mode, $pattern)) {
                $rows[] = [
                    'result_key' => $row[(string) ($field['lookup_result_key_column'] ?? 'product_code')] ?? null,
                    'lookup_value' => $row['quantity'],
                ];
            }
        }

        return LookupResultMode::reduceRows($rows, $resultMode, [
            'key_transform' => isset($field['lookup_result_key_transform']) ? (string) $field['lookup_result_key_transform'] : 'none',
            'rendered_prefix' => (string) ($field['_lookup_result_key_prefix'] ?? ''),
        ]);
    }

    private function matches(string $code, string $mode, string $pattern): bool
    {
        return match ($mode) {
            'prefix' => str_starts_with($code, rtrim($pattern, '%')),
            'suffix' => str_ends_with($code, ltrim($pattern, '%')),
            'contains' => str_contains($code, trim($pattern, '%')),
            'like' => preg_match('/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote($pattern, '/')) . '$/', $code) === 1,
            default => $code === $pattern,
        };
    }
}
