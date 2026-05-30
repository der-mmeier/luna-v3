<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Core\Application;
use Luna\Core\Paths;
use Luna\Routing\RouteCollection;
use Luna\Transfer\MappingExecutionResult;
use Luna\Transfer\MappingRowTransformer;
use Luna\Transfer\MappingSourceRowProvider;
use PDO;
use PHPUnit\Framework\TestCase;

final class MappingSourceRowProviderTest extends TestCase
{
    public function testMultipleSourceFiltersAreAppliedWithAnd(): void
    {
        $rows = $this->provider()->rows($this->pdo(), 'object_query_1', [
            'source_filters' => [
                ['source_column' => 'priceGroup', 'operator' => 'numeric_greater_than', 'filter_value' => '0'],
                ['source_column' => 'customfield_asf_model', 'operator' => 'not_ends_with', 'filter_value' => 'H'],
                ['source_column' => 'customfield_asf_model', 'operator' => 'not_ends_with', 'filter_value' => 'D'],
            ],
        ], null);

        self::assertSame(['E001', 'E002', 'PCT', 'WILD'], array_column($rows, 'model'));
    }

    public function testNumericGreaterThanExcludesNullEmptyAndZero(): void
    {
        $rows = $this->provider()->rows($this->pdo(), 'object_query_1', $this->numericFilter(), null);
        $models = array_column($rows, 'model');

        self::assertNotContains('TR001', $models);
        self::assertNotContains('EMPTY', $models);
        self::assertNotContains('ZERO', $models);
    }

    public function testNumericGreaterThanAllowsPositiveValues(): void
    {
        $rows = $this->provider()->rows($this->pdo(), 'object_query_1', $this->numericFilter(), null);

        self::assertContains('E001', array_column($rows, 'model'));
        self::assertContains('E002', array_column($rows, 'model'));
    }

    public function testNotEndsWithHExcludesValuesWithHSuffix(): void
    {
        $rows = $this->provider()->rows($this->pdo(), 'object_query_1', [
            'source_filters' => [
                ['source_column' => 'priceGroup', 'operator' => 'numeric_greater_than', 'filter_value' => '0'],
                ['source_column' => 'customfield_asf_model', 'operator' => 'not_ends_with', 'filter_value' => 'H'],
            ],
        ], null);

        self::assertNotContains('E001H', array_column($rows, 'model'));
    }

    public function testNotEndsWithDExcludesValuesWithDSuffix(): void
    {
        $rows = $this->provider()->rows($this->pdo(), 'object_query_1', [
            'source_filters' => [
                ['source_column' => 'priceGroup', 'operator' => 'numeric_greater_than', 'filter_value' => '0'],
                ['source_column' => 'customfield_asf_model', 'operator' => 'not_ends_with', 'filter_value' => 'D'],
            ],
        ], null);

        self::assertNotContains('E001D', array_column($rows, 'model'));
    }

    public function testContainsTreatsPercentAndUnderscoreAsLiteralCharacters(): void
    {
        $rows = $this->provider()->rows($this->pdo(), 'object_query_1', [
            'source_filters' => [
                ['source_column' => 'note', 'operator' => 'contains', 'filter_value' => '%_'],
            ],
        ], null);

        self::assertSame(['PCT'], array_column($rows, 'model'));
    }

    public function testInAndNotInUseCommaSeparatedValues(): void
    {
        $pdo = $this->pdo();
        $inRows = $this->provider()->rows($pdo, 'object_query_1', [
            'source_filters' => [
                ['source_column' => 'model', 'operator' => 'in', 'filter_value' => 'E001, E002'],
            ],
        ], null);
        $notInRows = $this->provider()->rows($pdo, 'object_query_1', [
            'source_filters' => [
                ['source_column' => 'model', 'operator' => 'not_in', 'filter_value' => 'E001, E002'],
            ],
        ], null);

        self::assertSame(['E001', 'E002'], array_column($inRows, 'model'));
        self::assertNotContains('E001', array_column($notInRows, 'model'));
        self::assertNotContains('E002', array_column($notInRows, 'model'));
    }

    public function testLegacySingleSourceFilterRemainsCompatible(): void
    {
        $rows = $this->provider()->rows($this->pdo(), 'object_query_1', [
            'source_filter_column' => 'priceGroup',
            'source_filter_operator' => 'numeric_gt',
            'source_filter_value' => '0',
        ], null);

        self::assertNotContains('TR001', array_column($rows, 'model'));
        self::assertContains('E001', array_column($rows, 'model'));
    }

    public function testUiPreviewAndDryRunUseSameSourceFilterProvider(): void
    {
        $this->loadWebRoutes();
        $pdo = $this->pdo();
        $values = [
            'source_filter_column' => 'priceGroup',
            'source_filter_operator' => 'numeric_gt',
            'source_filter_value' => '0',
        ];

        $uiRows = \mappingSampleRows($pdo, 'object_query_1', $values);
        $dryRunRows = $this->provider()->rows($pdo, 'object_query_1', $values, 10);

        self::assertSame(array_column($uiRows, 'model'), array_column($dryRunRows, 'model'));
        self::assertNotContains('TR001', array_column($dryRunRows, 'model'));
    }

    public function testReadExportDirectTransformUsesOnlyFilteredRows(): void
    {
        $rows = $this->provider()->rows($this->pdo(), 'object_query_1', [
            'source_filters' => [
                ['source_column' => 'priceGroup', 'operator' => 'numeric_greater_than', 'filter_value' => '0'],
                ['source_column' => 'customfield_asf_model', 'operator' => 'not_ends_with', 'filter_value' => 'H'],
                ['source_column' => 'customfield_asf_model', 'operator' => 'not_ends_with', 'filter_value' => 'D'],
            ],
        ], null);
        $transformer = new MappingRowTransformer(null);
        $result = new MappingExecutionResult(true);
        $output = [];

        foreach ($rows as $row) {
            $output[] = $transformer->transform($row, [
                ['target_column' => 'model', 'transform_type' => 'direct', 'source_column' => 'model'],
            ], $result);
        }

        self::assertSame([
            ['model' => 'E001'],
            ['model' => 'E002'],
            ['model' => 'PCT'],
            ['model' => 'WILD'],
        ], $output);
    }

    public function testEndpointRuntimeReceivesSameFilteredRowsAsDryRun(): void
    {
        $pdo = $this->pdo();
        $filter = [
            'source_filters' => [
                ['source_column' => 'priceGroup', 'operator' => 'numeric_greater_than', 'filter_value' => '0'],
                ['source_column' => 'customfield_asf_model', 'operator' => 'not_ends_with', 'filter_value' => 'H'],
                ['source_column' => 'customfield_asf_model', 'operator' => 'not_ends_with', 'filter_value' => 'D'],
            ],
        ];
        $dryRunRows = $this->provider()->rows($pdo, 'object_query_1', $filter, null);
        $endpointRows = $this->provider()->rows($pdo, 'object_query_1', $filter, null);

        self::assertSame(array_column($dryRunRows, 'model'), array_column($endpointRows, 'model'));
        self::assertNotContains('TR001', array_column($endpointRows, 'model'));
        self::assertNotContains('E001H', array_column($endpointRows, 'model'));
        self::assertNotContains('E001D', array_column($endpointRows, 'model'));
    }

    private function provider(): MappingSourceRowProvider
    {
        return new MappingSourceRowProvider();
    }

    /**
     * @return array<string, mixed>
     */
    private function numericFilter(): array
    {
        return [
            'source_filters' => [
                ['source_column' => 'priceGroup', 'operator' => 'numeric_greater_than', 'filter_value' => '0'],
            ],
        ];
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE object_query_1 (model TEXT, priceGroup TEXT NULL, customfield_asf_model TEXT NULL, note TEXT NULL)');
        $pdo->exec("INSERT INTO object_query_1 (model, priceGroup, customfield_asf_model, note) VALUES ('TR001', NULL, 'TR001', 'plain')");
        $pdo->exec("INSERT INTO object_query_1 (model, priceGroup, customfield_asf_model, note) VALUES ('EMPTY', '', 'EMPTY', 'plain')");
        $pdo->exec("INSERT INTO object_query_1 (model, priceGroup, customfield_asf_model, note) VALUES ('ZERO', '0', 'ZERO', 'plain')");
        $pdo->exec("INSERT INTO object_query_1 (model, priceGroup, customfield_asf_model, note) VALUES ('DASH', '-', 'DASH', 'plain')");
        $pdo->exec("INSERT INTO object_query_1 (model, priceGroup, customfield_asf_model, note) VALUES ('E001', '2', 'E001', 'plain')");
        $pdo->exec("INSERT INTO object_query_1 (model, priceGroup, customfield_asf_model, note) VALUES ('E001H', '2', 'E001H', 'plain')");
        $pdo->exec("INSERT INTO object_query_1 (model, priceGroup, customfield_asf_model, note) VALUES ('E001D', '2', 'E001D', 'plain')");
        $pdo->exec("INSERT INTO object_query_1 (model, priceGroup, customfield_asf_model, note) VALUES ('E002', '3', 'E002', 'plain')");
        $pdo->exec("INSERT INTO object_query_1 (model, priceGroup, customfield_asf_model, note) VALUES ('PCT', '4', 'E003', 'literal %_ token')");
        $pdo->exec("INSERT INTO object_query_1 (model, priceGroup, customfield_asf_model, note) VALUES ('WILD', '4', 'E004', 'literal ax token')");

        return $pdo;
    }

    private function loadWebRoutes(): RouteCollection
    {
        $basePath = dirname(__DIR__, 2);
        $app = new Application(new Paths($basePath), new Config());
        $routes = new RouteCollection();
        $loader = require $basePath . '/routes/web.php';
        $loader($routes, $app);

        return $routes;
    }
}
