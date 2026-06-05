<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Export\EndpointSchemaBuilder;
use PHPUnit\Framework\TestCase;

final class EndpointSchemaBuilderTest extends TestCase
{
    public function testWrapperAndMappingFieldsAreExported(): void
    {
        $schema = (new EndpointSchemaBuilder())->build(['endpoint_key' => 'isr_prices'], [
            ['target_column' => 'model', 'schema_type' => 'string', 'schema_required' => 1],
            ['target_column' => 'price', 'schema_type' => 'number'],
            ['target_column' => 'dr_quantities', 'schema_type' => 'object'],
        ]);

        self::assertSame('object', $schema['type']);
        self::assertSame(['success', 'generated_at', 'count', 'items'], $schema['required']);
        self::assertSame('string', $schema['properties']['items']['items']['properties']['model']['type']);
        self::assertSame('number', $schema['properties']['items']['items']['properties']['price']['type']);
        self::assertSame('object', $schema['properties']['items']['items']['properties']['dr_quantities']['type']);
        self::assertSame(['model'], $schema['properties']['items']['items']['required']);
    }

    public function testAutoTypeDoesNotBreakExportAndDetectsQuantities(): void
    {
        $schema = (new EndpointSchemaBuilder())->build(['endpoint_key' => 'isr_prices'], [
            ['target_column' => 'hr_quantities', 'transform_type' => 'key_value_map_by_prefix', 'schema_type' => 'auto'],
            ['target_column' => 'source_count', 'schema_type' => 'auto'],
        ]);

        self::assertSame('object', $schema['properties']['items']['items']['properties']['hr_quantities']['type']);
        self::assertSame('integer', $schema['properties']['items']['items']['properties']['hr_quantities']['additionalProperties']['type']);
        self::assertSame('integer', $schema['properties']['items']['items']['properties']['source_count']['type']);
    }
}
