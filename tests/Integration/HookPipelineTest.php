<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Integration;

use GrommasDietz\KirbyLocale\TitleLocale;
use GrommasDietz\KirbyLocale\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the title-locale hook pipeline.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class HookPipelineTest extends TestCase
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

    protected function tearDown(): void
    {
        // Clean up any test-created pages
        if (isset($this->kirby)) {
            $this->kirby->impersonate('kirby');

            $testPage = $this->kirby->page('hook-test');

            if ($testPage) {
                $testPage->delete(true);
            }
        }

        parent::tearDown();
    }

    public function testPageCreateHookStoresLocale(): void
    {
        $page = $this->kirby->site()->createChild([
            'slug'     => 'hook-test',
            'template' => 'article',
            'content'  => [
                'title'                  => 'Hook Test',
                TitleLocale::FIELD_KEY   => 'fr',
            ],
        ]);

        $this->assertNotNull($page);

        // The hook fires after create; the locale should be persisted
        $stored = TitleLocale::getStored($page, 'en');
        $this->assertSame('fr', $stored);
    }

    public function testPageCreateHookRespectsTemplateGating(): void
    {
        // Boot with only 'article' allowed — 'default' template should be gated
        $this->kirby = $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.intendedTemplate' => 'article',
            ],
        ]);
        $this->kirby->impersonate('kirby');

        $home = $this->kirby->page('home');
        $this->assertNotNull($home);

        // Store locale on home (template 'home', not 'article') — should be blocked
        TitleLocale::store($home, 'es', 'en');
        $stored = TitleLocale::getStored($home, 'en');

        $this->assertNotSame('es', $stored, 'Locale should not be stored for non-allowed template');
    }

    public function testStoreAndRetrieveOnArticlePage(): void
    {
        $page = $this->kirby->page('sample-article');
        $this->assertNotNull($page, 'Fixture page sample-article must exist');

        TitleLocale::store($page, 'ja', 'en');

        $fresh = $this->kirby->page('sample-article');
        $stored = TitleLocale::getStored($fresh, 'en');

        $this->assertSame('ja', $stored);
        // Fixture content is auto-restored by TestEnvironment::ensureContentFixtures()
    }

    public function testHandlePageLocaleUpdateSkipsWhenTemplateNotAllowed(): void
    {
        // Only 'article' is allowed
        $this->kirby = $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.intendedTemplate' => 'article',
            ],
        ]);
        $this->kirby->impersonate('kirby');

        $home = $this->kirby->page('home');
        $this->assertNotNull($home);

        // handlePageLocaleUpdate reads from the request, so the locale
        // won't be in the request — it should bail early
        TitleLocale::handlePageLocaleUpdate($home);

        $stored = TitleLocale::getStored($home, null);
        $this->assertNotSame('fr', $stored);
    }

    public function testHandlePageLocaleUpdateStoresValueFromRequestWhenPresent(): void
    {
        $this->kirby = $this->bootKirby([
            'request' => [
                'method' => 'POST',
                'body' => [
                    TitleLocale::FIELD_KEY => 'sv',
                ],
            ],
            'options' => [
                'grommasdietz.kirby-locale.intendedTemplate' => ['article', 'home'],
            ],
        ]);
        $this->kirby->impersonate('kirby');

        $home = $this->kirby->page('home');
        $this->assertNotNull($home);

        TitleLocale::handlePageLocaleUpdate($home, 'en');

        $fresh = $this->kirby->page('home');
        $stored = TitleLocale::getStored($fresh, 'en');

        $this->assertSame('sv', $stored);
    }
}
