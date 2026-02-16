<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Unit;

use GrommasDietz\KirbyLocale\LocaleHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the template normalisation and filtering helpers used
 * by the title locale feature to decide which templates support locale dialogs.
 */
final class TemplateFilterTest extends TestCase
{
    // --- normaliseTemplateList ---

    public function testNullReturnsEmptyArray(): void
    {
        $this->assertSame([], LocaleHelper::normaliseTemplateList(null));
    }

    public function testEmptyStringReturnsEmptyArray(): void
    {
        $this->assertSame([], LocaleHelper::normaliseTemplateList(''));
    }

    public function testFalseReturnsEmptyArray(): void
    {
        $this->assertSame([], LocaleHelper::normaliseTemplateList(false));
    }

    public function testTrueReturnsNull(): void
    {
        $this->assertNull(LocaleHelper::normaliseTemplateList(true));
    }

    public function testWildcardStringReturnsNull(): void
    {
        $this->assertNull(LocaleHelper::normaliseTemplateList('*'));
        $this->assertNull(LocaleHelper::normaliseTemplateList('all'));
        $this->assertNull(LocaleHelper::normaliseTemplateList('All'));
    }

    public function testSingleStringReturnsArray(): void
    {
        $this->assertSame(['project'], LocaleHelper::normaliseTemplateList('project'));
    }

    public function testArrayOfStrings(): void
    {
        $result = LocaleHelper::normaliseTemplateList(['project', 'note']);
        $this->assertSame(['project', 'note'], $result);
    }

    public function testDeduplicatesTemplates(): void
    {
        $result = LocaleHelper::normaliseTemplateList(['project', 'project', 'note']);
        $this->assertSame(['project', 'note'], $result);
    }

    public function testFiltersEmptyStrings(): void
    {
        $result = LocaleHelper::normaliseTemplateList(['project', '', '  ']);
        $this->assertSame(['project'], $result);
    }

    public function testCallableIsInvoked(): void
    {
        $result = LocaleHelper::normaliseTemplateList(fn () => ['article']);
        $this->assertSame(['article'], $result);
    }

    // --- templateAllowsLocale ---

    public function testNullAllowedAllowsAll(): void
    {
        $this->assertTrue(LocaleHelper::templateAllowsLocale('anything', null));
    }

    public function testEmptyAllowedDeniesAll(): void
    {
        $this->assertFalse(LocaleHelper::templateAllowsLocale('project', []));
    }

    public function testMatchingTemplateIsAllowed(): void
    {
        $this->assertTrue(LocaleHelper::templateAllowsLocale('project', ['project', 'note']));
    }

    public function testNonMatchingTemplateIsDenied(): void
    {
        $this->assertFalse(LocaleHelper::templateAllowsLocale('blog', ['project', 'note']));
    }

    public function testNullTemplateIsDenied(): void
    {
        $this->assertFalse(LocaleHelper::templateAllowsLocale(null, ['project']));
    }
}
