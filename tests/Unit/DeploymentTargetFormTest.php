<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DeploymentTargetFormTest extends TestCase
{
    public function testDeploymentTargetMainFormDoesNotShowModuleSupportOrOriginFieldsProminently(): void
    {
        $form = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/admin/deployment-targets/_form.php');

        self::assertStringNotContainsString('>Origin<', $form);
        self::assertStringNotContainsString('Support Status', $form);
        self::assertStringNotContainsString('Module Key', $form);
        self::assertStringContainsString('Erweiterte Metadaten', $form);
        self::assertStringContainsString('License Server URL optional', $form);
        self::assertStringContainsString('Nur vorbereitende Metadaten. Luna kontaktiert in 2.2.0 keinen Lizenzserver und erzwingt keine Entitlements.', $form);
    }
}
