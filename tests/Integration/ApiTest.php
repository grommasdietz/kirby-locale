<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Integration;

use GrommasDietz\KirbyLocale\DialogFactory;
use GrommasDietz\KirbyLocale\LocaleHelper;
use GrommasDietz\KirbyLocale\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for locale collection and dialog field generation.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby();
    }

    public function testCollectLocalesReturnsData(): void
    {
        $locales = LocaleHelper::collectLocales($this->kirby);

        $this->assertIsArray($locales);
        $this->assertGreaterThan(0, count($locales));
        $this->assertArrayHasKey('code', $locales[0]);
        $this->assertArrayHasKey('name', $locales[0]);
    }

    public function testBuildFieldReturnsOptions(): void
    {
        $field = DialogFactory::buildField();

        $this->assertIsArray($field);
        $this->assertArrayHasKey('options', $field);
        $this->assertIsArray($field['options']);
        $this->assertGreaterThan(0, count($field['options']));
    }
}
