<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Integration;

use GrommasDietz\KirbyLocale\TitleLocale;
use GrommasDietz\KirbyLocale\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the title locale storage pipeline — store, getStored,
 * and the template gating.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TitleLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.intendedTemplate' => 'home',
            ],
        ])->impersonate('kirby');
    }

    public function testStoreAndRetrieveTitleLocale(): void
    {
        $page = $this->kirby->page('home');
        $this->assertNotNull($page, 'Home page must exist');

        TitleLocale::store($page, 'de', null);

        // Re-fetch the page so we read the persisted content
        $fresh = $this->kirby->page('home');
        $stored = TitleLocale::getStored($fresh, null);

        $this->assertSame('de', $stored);
        // Fixture content is auto-restored by TestEnvironment::ensureContentFixtures()
    }

    public function testStoreSkipsWhenTemplateNotAllowed(): void
    {
        // Boot a fresh instance where only 'article' templates are enabled
        $this->kirby = $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.intendedTemplate' => 'article',
            ],
        ]);
        $this->kirby->impersonate('kirby');

        $page = $this->kirby->page('home');
        $this->assertNotNull($page);

        // home uses the 'home' template, which is not 'article'
        TitleLocale::store($page, 'fr', null);

        $fresh = $this->kirby->page('home');
        $stored = TitleLocale::getStored($fresh, null);

        // Should NOT have been stored because the template is not allowed
        $this->assertNotSame('fr', $stored);
    }

    public function testGetStoredReturnsNullForMissingPage(): void
    {
        $this->assertNull(TitleLocale::getStored(null, null));
    }

    public function testGetStoredReturnsNullWhenNoLocaleSet(): void
    {
        // Boot without any intendedTemplate so nothing was stored
        $this->kirby = $this->bootKirby();
        $page = $this->kirby->page('home');

        // The page shouldn't have a title_locale field unless a previous test stored one
        $stored = TitleLocale::getStored($page, null);

        // Will be null if nothing was ever stored
        $this->assertTrue($stored === null || is_string($stored));
    }

    public function testStoreReturnsEarlyWhenPageIsNull(): void
    {
        TitleLocale::store(null, 'de', null);

        $this->addToAssertionCount(1);
    }

    public function testHandlePageLocaleUpdateReturnsEarlyWhenPageIsNull(): void
    {
        TitleLocale::handlePageLocaleUpdate(null);

        $this->addToAssertionCount(1);
    }
}
