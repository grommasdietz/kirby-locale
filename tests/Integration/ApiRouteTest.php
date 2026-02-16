<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Integration;

use GrommasDietz\KirbyLocale\TitleLocale;
use GrommasDietz\KirbyLocale\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the plugin's API routes — verifies that the route
 * registrations return the expected response shapes.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ApiRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.intendedTemplate' => ['article', 'home'],
                'api' => ['allowImpersonation' => true],
            ],
        ])->impersonate('kirby');
    }

    public function testLocalesRouteReturnsArray(): void
    {
        $result = $this->kirby->api()->call(
            'grommasdietz/kirby-locale/locales',
            'GET'
        );

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result));
        $this->assertArrayHasKey('code', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
    }

    public function testOptionsRouteReturnsArray(): void
    {
        $result = $this->kirby->api()->call(
            'grommasdietz/kirby-locale/options',
            'GET'
        );

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result));
    }

    public function testTitleLocaleRouteReturnsNullForMissingId(): void
    {
        $result = $this->kirby->api()->call(
            'grommasdietz/kirby-locale/title-locale',
            'GET'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey(TitleLocale::FIELD_KEY, $result);
        $this->assertNull($result[TitleLocale::FIELD_KEY]);
    }

    public function testTitleLocaleRouteReturnsStoredValue(): void
    {
        // The title-locale route reads query params from $kirby->request(),
        // which cannot be easily simulated via $api->call().
        // Verify the underlying logic directly: store a value, then read it
        // through the same code path the route action uses.
        $page = $this->kirby->page('sample-article');
        $this->assertNotNull($page, 'Fixture page sample-article must exist');

        TitleLocale::store($page, 'fr', 'en');

        $fresh = $this->kirby->page('sample-article');
        $stored = TitleLocale::getStored($fresh, 'en');

        $this->assertSame('fr', $stored);
        // Fixture content is auto-restored by TestEnvironment::ensureContentFixtures()
    }
}
