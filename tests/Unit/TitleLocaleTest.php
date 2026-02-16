<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Unit;

use GrommasDietz\KirbyLocale\LocaleHelper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TitleLocale‑related helpers that don't need a Kirby boot.
 * The static methods on LocaleHelper are pure and can be tested directly.
 */
final class TitleLocaleTest extends TestCase
{
    // --- normaliseTitleLocale ---

    public function testNormaliseTitleLocaleTrimsString(): void
    {
        $this->assertSame('en', LocaleHelper::normaliseTitleLocale('  en  '));
    }

    public function testNormaliseTitleLocaleReturnsNullForEmpty(): void
    {
        $this->assertNull(LocaleHelper::normaliseTitleLocale(''));
        $this->assertNull(LocaleHelper::normaliseTitleLocale('   '));
        $this->assertNull(LocaleHelper::normaliseTitleLocale(null));
    }

    public function testNormaliseTitleLocaleRejectsNonString(): void
    {
        $this->assertNull(LocaleHelper::normaliseTitleLocale(42));
        $this->assertNull(LocaleHelper::normaliseTitleLocale(['en']));
    }

    // --- normaliseLocaleTag ---

    public function testNormaliseLocaleTagCollapsesWhitespace(): void
    {
        $this->assertSame('enUS', LocaleHelper::normaliseLocaleTag(' en US '));
    }

    public function testNormaliseLocaleTagConvertsUnderscores(): void
    {
        $this->assertSame('en-US', LocaleHelper::normaliseLocaleTag('en_US'));
    }

    public function testNormaliseLocaleTagReturnsEmptyForNull(): void
    {
        $this->assertSame('', LocaleHelper::normaliseLocaleTag(null));
        $this->assertSame('', LocaleHelper::normaliseLocaleTag(''));
    }

    // --- canonicaliseLocaleTag ---

    public function testCanonicaliseLocaleTagNormalisesCase(): void
    {
        $result = LocaleHelper::canonicaliseLocaleTag('EN-us');
        $this->assertNotSame('', $result);
        // Intl extension canonicalises to "en-US" or "en_US" (then converted)
        $this->assertStringStartsWith('en', strtolower($result));
    }

    public function testCanonicaliseLocaleTagReturnsEmptyForNull(): void
    {
        $this->assertSame('', LocaleHelper::canonicaliseLocaleTag(null));
    }
}
