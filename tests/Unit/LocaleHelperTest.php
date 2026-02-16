<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Unit;

use GrommasDietz\KirbyLocale\LocaleHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LocaleHelper::normaliseLocaleDefinition
 */
final class LocaleHelperTest extends TestCase
{
    public function testNormaliseStringLocale(): void
    {
        $result = LocaleHelper::normaliseLocaleDefinition('en', 'Test Group', 'test');
        $this->assertEquals([
            'code' => 'en',
            'name' => 'en',
            'group' => 'Test Group',
            'source' => 'test',
            'nameProvided' => false,
        ], $result);
    }

    public function testNormaliseEmptyString(): void
    {
        $result = LocaleHelper::normaliseLocaleDefinition('', 'Test Group', 'test');
        $this->assertNull($result);
    }

    public function testNormaliseArrayLocale(): void
    {
        $result = LocaleHelper::normaliseLocaleDefinition([
            'code' => 'en-US',
            'name' => 'English (US)',
            'group' => 'Americas',
        ], 'Default', 'plugin');
        $this->assertEquals([
            'code' => 'en-US',
            'name' => 'English (US)',
            'group' => 'Americas',
            'source' => 'plugin',
            'nameProvided' => true,
        ], $result);
    }

    public function testNormaliseArrayWithoutName(): void
    {
        $result = LocaleHelper::normaliseLocaleDefinition([
            'code' => 'fr',
        ], 'Europe', 'catalog');
        $this->assertEquals([
            'code' => 'fr',
            'name' => 'fr',
            'group' => 'Europe',
            'source' => 'catalog',
            'nameProvided' => false,
        ], $result);
    }

    public function testNormaliseInvalidArray(): void
    {
        $result = LocaleHelper::normaliseLocaleDefinition(['name' => 'Test'], 'Group', 'test');
        $this->assertNull($result);
    }

    public function testNormaliseArrayRespectsExplicitNameProvidedFlag(): void
    {
        $result = LocaleHelper::normaliseLocaleDefinition([
            'code' => 'es',
            'name' => 'Spanish',
            'nameProvided' => 1,
        ], null, 'plugin');

        $this->assertSame(true, $result['nameProvided']);
    }
}
