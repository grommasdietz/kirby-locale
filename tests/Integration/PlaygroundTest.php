<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Integration;

use GrommasDietz\KirbyLocale\Tests\TestCase;

/**
 * Playground smoke tests — boots Kirby and verifies the plugin is loaded,
 * pages can be retrieved, and user lifecycle works.
 */
final class PlaygroundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby()->impersonate('kirby');
    }

    public function testPluginRegistersWithKirby(): void
    {
        $this->assertNotNull($this->kirby->plugin('grommasdietz/kirby-locale'));
    }

    public function testHomePageCanBeLoaded(): void
    {
        $page = $this->kirby->page('home');

        $this->assertSame('home', $page?->id());
        $this->assertSame('Home', $page?->title()->value());
    }

    public function testUsersCanBeCreatedAndDeleted(): void
    {
        $initialCount = $this->kirby->users()->count();

        $primaryEmail = 'primary-admin-' . uniqid() . '@kirby-locale.test';
        $secondaryEmail = 'secondary-admin-' . uniqid() . '@kirby-locale.test';

        $primaryAdmin = $this->kirby->users()->create([
            'email' => $primaryEmail,
            'name' => 'Primary Admin',
            'role' => 'admin',
            'password' => 'test-password',
        ]);

        $secondaryAdmin = $this->kirby->users()->create([
            'email' => $secondaryEmail,
            'name' => 'Secondary Admin',
            'role' => 'admin',
            'password' => 'test-password',
        ]);

        $this->assertSame('admin', $primaryAdmin->role()->name());
        $this->assertSame('admin', $secondaryAdmin->role()->name());
        $this->assertCount($initialCount + 2, $this->kirby->users());

        $secondaryAdmin->delete();
        $primaryAdmin->delete();

        $this->assertCount($initialCount, $this->kirby->users());
    }
}
