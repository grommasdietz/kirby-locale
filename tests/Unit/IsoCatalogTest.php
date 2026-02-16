<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Unit;

use GrommasDietz\KirbyLocale\IsoCatalog;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ISO 639‑1 catalog and translation datasets.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class IsoCatalogTest extends TestCase
{
    public function testCatalogReturnsNonEmptyArray(): void
    {
        $catalog = IsoCatalog::catalog();

        $this->assertIsArray($catalog);
        $this->assertGreaterThan(50, count($catalog), 'ISO catalog should contain many languages');
    }

    public function testCatalogEntriesHaveCodeAndName(): void
    {
        $entry = IsoCatalog::catalog()[0];

        $this->assertArrayHasKey('code', $entry);
        $this->assertArrayHasKey('name', $entry);
        $this->assertIsString($entry['code']);
        $this->assertIsString($entry['name']);
    }

    public function testTranslationsContainEnglishKey(): void
    {
        $translations = IsoCatalog::translations();

        $this->assertArrayHasKey('en', $translations);
        $this->assertIsArray($translations['en']);
        $this->assertGreaterThan(0, count($translations['en']));
    }

    public function testTranslationsEnglishIncludesCommonCodes(): void
    {
        $en = IsoCatalog::translations()['en'];

        $this->assertArrayHasKey('en', $en);
        $this->assertArrayHasKey('de', $en);
        $this->assertArrayHasKey('fr', $en);
    }

    public function testCatalogReturnsCachedResultOnSubsequentCalls(): void
    {
        $first = IsoCatalog::catalog();
        $second = IsoCatalog::catalog();

        $this->assertSame($first, $second);
    }

    public function testTranslationsReturnCachedResultOnSubsequentCalls(): void
    {
        $first = IsoCatalog::translations();
        $second = IsoCatalog::translations();

        $this->assertSame($first, $second);
    }
}
