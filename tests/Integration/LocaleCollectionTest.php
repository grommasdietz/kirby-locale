<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Integration;

use GrommasDietz\KirbyLocale\LocaleHelper;
use GrommasDietz\KirbyLocale\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the locale collection pipeline with different
 * configuration options (custom locales, catalog disabled, etc.).
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class LocaleCollectionTest extends TestCase
{
    public function testCollectLocalesWithCustomOption(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.locales' => [
                    ['code' => 'x-custom', 'name' => 'Custom Locale'],
                ],
            ],
        ]);

        $locales = LocaleHelper::collectLocales($this->kirby);

        $codes = array_column($locales, 'code');
        $this->assertContains('x-custom', $codes, 'Custom locale must appear in the collected list');
    }

    public function testCollectLocalesWithCatalogDisabled(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.locales' => [
                    ['code' => 'x-only', 'name' => 'Only Entry'],
                ],
                'grommasdietz.kirby-locale.catalog' => false,
            ],
        ]);

        $locales = LocaleHelper::collectLocales($this->kirby);
        $codes = array_column($locales, 'code');

        // With catalog disabled the list contains only the custom locale
        // plus site languages (en, de) from the multilang playground.
        $this->assertContains('x-only', $codes);
        $this->assertLessThanOrEqual(3, count($locales));
    }

    public function testCollectLocalesWithClosure(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.locales' => fn () => [
                    ['code' => 'x-dynamic', 'name' => 'Dynamic'],
                ],
                'grommasdietz.kirby-locale.catalog' => false,
            ],
        ]);

        $locales = LocaleHelper::collectLocales($this->kirby);
        $codes = array_column($locales, 'code');

        // Closure locale plus site languages from the multilang playground.
        $this->assertContains('x-dynamic', $codes);
        $this->assertLessThanOrEqual(3, count($locales));
    }

    public function testLocaleSourceIsTracked(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.locales' => [
                    ['code' => 'x-plugin', 'name' => 'Plugin Entry'],
                ],
            ],
        ]);

        $locales = LocaleHelper::collectLocales($this->kirby);

        $pluginEntry = null;

        foreach ($locales as $locale) {
            if ($locale['code'] === 'x-plugin') {
                $pluginEntry = $locale;
                break;
            }
        }

        $this->assertNotNull($pluginEntry);
        $this->assertSame('plugin', $pluginEntry['source']);
    }

    public function testCollectLocalesSupportsCallableCatalogAndSkipsInvalidPluginEntries(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.locales' => [
                    ['name' => 'Invalid entry without code'],
                    ['code' => 'x-plugin-valid', 'name' => 'Valid plugin locale'],
                ],
                'grommasdietz.kirby-locale.catalog' => static fn () => [
                    ['code' => 'x-catalog', 'name' => 'Catalog locale'],
                ],
            ],
        ]);

        $locales = LocaleHelper::collectLocales($this->kirby);
        $codes = array_column($locales, 'code');

        $this->assertContains('x-plugin-valid', $codes);
        $this->assertContains('x-catalog', $codes);
    }

    public function testFindPageReturnsExistingPageWhenImpersonated(): void
    {
        $this->bootKirby()->impersonate('kirby');

        $page = LocaleHelper::findPage('home');

        $this->assertSame('home', $page->id());
    }
}
