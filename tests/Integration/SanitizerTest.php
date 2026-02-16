<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Integration;

use GrommasDietz\KirbyLocale\Tests\TestCase;
use Kirby\Sane\Html;

/**
 * Verifies that the plugin's bootstrap registers the expected
 * HTML sanitizer allowlist so <span lang="…" class="…"> survives.
 */
final class SanitizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby();
    }

    public function testSpanAllowsLangAttribute(): void
    {
        $allowed = Html::$allowedTags['span'] ?? [];

        $this->assertContains('lang', $allowed, 'span must allow the "lang" attribute');
    }

    public function testSpanAllowsClassAttribute(): void
    {
        $allowed = Html::$allowedTags['span'] ?? [];

        $this->assertContains('class', $allowed, 'span must allow the "class" attribute');
    }
}
