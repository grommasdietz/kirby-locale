<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Integration;

use GrommasDietz\KirbyLocale\TitleLocale;
use GrommasDietz\KirbyLocale\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for multilang title locale storage — verifies that locale
 * values are stored and retrieved per language correctly.
 *
 * Fixture content is auto-restored by TestEnvironment::ensureContentFixtures()
 * at Kirby boot time, so tests don't need manual cleanup.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MultilangTitleLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.intendedTemplate' => ['article', 'home'],
            ],
        ])->impersonate('kirby');
    }

    public function testStoreLocalePerLanguage(): void
    {
        $page = $this->kirby->page('sample-article');
        $this->assertNotNull($page, 'Fixture page sample-article must exist');

        // Store for English, then re-fetch before storing for German
        // (avoids stale page object after update)
        TitleLocale::store($page, 'fr', 'en');

        $page = $this->kirby->page('sample-article');
        TitleLocale::store($page, 'ja', 'de');

        $fresh = $this->kirby->page('sample-article');

        $enStored = TitleLocale::getStored($fresh, 'en');
        $deStored = TitleLocale::getStored($fresh, 'de');

        $this->assertSame('fr', $enStored);
        $this->assertSame('ja', $deStored);
    }

    public function testStoreLocaleOnHomePageEnglish(): void
    {
        $page = $this->kirby->page('home');
        $this->assertNotNull($page);

        TitleLocale::store($page, 'es', 'en');

        $fresh = $this->kirby->page('home');
        $stored = TitleLocale::getStored($fresh, 'en');

        $this->assertSame('es', $stored);
    }

    public function testStoreLocaleOnHomePageGerman(): void
    {
        $page = $this->kirby->page('home');
        $this->assertNotNull($page);

        TitleLocale::store($page, 'it', 'de');

        $fresh = $this->kirby->page('home');
        $stored = TitleLocale::getStored($fresh, 'de');

        $this->assertSame('it', $stored);
    }

    public function testClearLocaleValuePerLanguage(): void
    {
        $page = $this->kirby->page('sample-article');
        $this->assertNotNull($page, 'Fixture page sample-article must exist');

        TitleLocale::store($page, 'pt', 'en');

        $fresh = $this->kirby->page('sample-article');
        $this->assertSame('pt', TitleLocale::getStored($fresh, 'en'));

        TitleLocale::store($fresh, null, 'en');

        $cleared = $this->kirby->page('sample-article');
        $stored = TitleLocale::getStored($cleared, 'en');

        $this->assertNull($stored);
    }

    public function testLanguageIsolation(): void
    {
        $page = $this->kirby->page('sample-article');
        $this->assertNotNull($page, 'Fixture page sample-article must exist');

        $originalDe = TitleLocale::getStored($page, 'de');

        TitleLocale::store($page, 'nl', 'en');

        $fresh = $this->kirby->page('sample-article');

        $this->assertSame('nl', TitleLocale::getStored($fresh, 'en'));
        $this->assertSame($originalDe, TitleLocale::getStored($fresh, 'de'));
    }

    public function testSeededArticleHasLocaleContent(): void
    {
        $page = $this->kirby->page('sample-article');
        $this->assertNotNull($page, 'Fixture page sample-article must exist');

        $enText = $page->content('en')->get('text')->value();
        $this->assertStringContainsString('lang="de"', $enText);
        $this->assertStringContainsString('class="notranslate"', $enText);
        $this->assertStringContainsString('translate="no"', $enText);

        $deText = $page->content('de')->get('text')->value();
        $this->assertStringContainsString('lang="en"', $deText);
        $this->assertStringContainsString('class="notranslate"', $deText);
        $this->assertStringContainsString('translate="no"', $deText);
    }
}
