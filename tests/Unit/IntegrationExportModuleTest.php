<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use DateTimeImmutable;
use Luna\Export\EndpointExportArchiveService;
use Luna\Integration\ExportModuleRegistry;
use Luna\Integration\ExportRuntimeBuilder;
use Luna\Integration\Modules\IsrPricesExportModule;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class IntegrationExportModuleTest extends TestCase
{
    public function testIsrPricesModuleManifestDocumentsRuntimeAndSecretPolicy(): void
    {
        $module = new IsrPricesExportModule();
        $manifest = $module->manifest(new DateTimeImmutable('2026-06-02T12:00:00+02:00'))->toArray();
        $json = json_encode($manifest, JSON_THROW_ON_ERROR);

        self::assertSame('isr_prices', $manifest['module']);
        self::assertSame('1.6.0', $manifest['version']);
        self::assertSame('2026-06-02T12:00:00+02:00', $manifest['generated_at']);
        self::assertContains('api/isr_prices.php', $manifest['runtime_files']);
        self::assertContains('runtime/MappingExecutor.php', $manifest['runtime_files']);
        self::assertContains('config/endpoint.isr_prices.php', $manifest['runtime_files']);
        self::assertContains('module.isr_prices.manifest.json', $manifest['runtime_files']);
        self::assertContains('.env', $manifest['excluded_files']);
        self::assertContains('.env.*', $manifest['excluded_files']);
        self::assertContains('APP_KEY', $manifest['never_export']);
        self::assertFalse($manifest['secret_policy']['exports_secrets']);
        self::assertContains('password', $manifest['secret_policy']['forbidden_patterns']);
        self::assertStringNotContainsString('mysql://', $json);
        self::assertStringNotContainsString('C:\\', $json);
    }

    public function testRegistryReturnsIsrPricesModule(): void
    {
        $registry = new ExportModuleRegistry([new IsrPricesExportModule()]);

        self::assertSame('isr_prices', $registry->get('isr_prices')->name());
        self::assertCount(1, $registry->all());
    }

    public function testBuilderDryRunReturnsManifestWithoutDatabaseExport(): void
    {
        $builder = new ExportRuntimeBuilder(
            new ExportModuleRegistry([new IsrPricesExportModule()]),
            (new \ReflectionClass(\Luna\Export\EndpointRuntimeExporter::class))->newInstanceWithoutConstructor(),
            new EndpointExportArchiveService(),
        );

        $result = $builder->dryRun('isr_prices', true);

        self::assertSame('planned', $result['status']);
        self::assertTrue($result['dry_run']);
        self::assertTrue($result['zip_requested']);
        self::assertSame('isr_prices', $result['module']);
        self::assertSame('isr_prices', $result['endpoint_key']);
        self::assertSame('isr_prices', $result['manifest']['module']);
        self::assertContains('.env', $result['manifest']['excluded_files']);
        self::assertContains('api/isr_prices.php', $result['included_files']);
        self::assertContains('.env.*', $result['excluded_files']);
        self::assertSame('planned', $result['validation']['status']);
        self::assertTrue($result['validation']['module_name_valid']);
        self::assertTrue($result['validation']['secret_policy_active']);
        self::assertTrue($result['validation']['payload_comparison']['automated_in_tests']);
        self::assertSame([], $result['warnings']);
    }

    public function testValidateExportDirectoryRejectsForbiddenFilesAndSecretValues(): void
    {
        $module = new IsrPricesExportModule();
        $directory = $this->tempDirectory();
        foreach (['api', 'runtime', 'config'] as $subdirectory) {
            mkdir($directory . '/' . $subdirectory, 0775, true);
        }
        foreach ($module->runtimeFiles() as $file) {
            $path = $directory . '/' . $file;
            $parent = dirname($path);
            if (! is_dir($parent)) {
                mkdir($parent, 0775, true);
            }
            file_put_contents($path, $file === 'module.isr_prices.manifest.json'
                ? json_encode($module->manifest(new DateTimeImmutable('2026-06-02T12:00:00+02:00'))->toArray(), JSON_THROW_ON_ERROR)
                : 'runtime-file');
        }
        file_put_contents($directory . '/.env', 'APP_KEY=test-placeholder');
        mkdir($directory . '/.phpunit.cache', 0775, true);
        file_put_contents($directory . '/.phpunit.cache/test-results', '{}');
        file_put_contents($directory . '/runtime/local-path.php', 'C:\\Users\\Saito\\PhpstormProjects\\luna-v3');

        $builder = new ExportRuntimeBuilder(
            new ExportModuleRegistry([$module]),
            (new \ReflectionClass(\Luna\Export\EndpointRuntimeExporter::class))->newInstanceWithoutConstructor(),
            new EndpointExportArchiveService(),
        );

        $validation = $builder->validate('isr_prices', $directory);

        self::assertSame('warning', $validation['status']);
        self::assertTrue($validation['module_name_valid']);
        self::assertTrue($validation['manifest_present']);
        self::assertTrue($validation['runtime_files_complete']);
        self::assertTrue($validation['secret_policy_active']);
        self::assertContains('.env', $validation['forbidden_files_present']);
        self::assertContains('.phpunit.cache/test-results', $validation['forbidden_files_present']);
        self::assertContains('runtime/local-path.php', $validation['local_absolute_paths_found']);
        self::assertContains('.env', $validation['secret_value_findings']);
        self::assertContains('forbidden_files_present', $validation['warnings']);
    }

    public function testArchiveServiceCreatesDeterministicZipWithoutLocalSecrets(): void
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP ZIP extension is not available.');
        }

        $directory = $this->tempDirectory();
        mkdir($directory . '/api', 0775, true);
        mkdir($directory . '/runtime', 0775, true);
        file_put_contents($directory . '/runtime/bootstrap.php', '<?php');
        file_put_contents($directory . '/api/isr_prices.php', '<?php');
        file_put_contents($directory . '/.env', 'APP_KEY=test-placeholder');
        file_put_contents($directory . '/.env.local', 'PASSWORD=test-placeholder');
        file_put_contents($directory . '/.env.example', 'APP_KEY=');
        file_put_contents($directory . '/debug.log', 'log');
        file_put_contents($directory . '/old.zip', 'zip');

        $archivePath = dirname($directory) . '/isr_prices-runtime.zip';
        $files = (new EndpointExportArchiveService())->createArchive($directory, $archivePath, true);

        self::assertSame([
            '.env.example',
            'api/isr_prices.php',
            'runtime/bootstrap.php',
        ], $files);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($archivePath));
        self::assertNotFalse($zip->locateName('.env.example'));
        self::assertFalse($zip->locateName('.env'));
        self::assertFalse($zip->locateName('.env.local'));
        self::assertFalse($zip->locateName('debug.log'));
        self::assertFalse($zip->locateName('old.zip'));
        $zip->close();
    }

    public function testCliExposesIntegrationExportDryRunCommand(): void
    {
        $cli = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/luna');

        self::assertStringContainsString('integration:export', $cli);
        self::assertStringContainsString('--dry-run', $cli);
    }

    private function tempDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/luna_module_' . bin2hex(random_bytes(6));
        mkdir($directory, 0775, true);

        return str_replace('\\', '/', $directory);
    }
}
