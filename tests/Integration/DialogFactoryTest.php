<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Integration;

use GrommasDietz\KirbyLocale\DialogFactory;
use GrommasDietz\KirbyLocale\TitleLocale;
use GrommasDietz\KirbyLocale\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for DialogFactory::extend() — the core dialog-injection logic
 * that powers both the create and change-title dialogs.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DialogFactoryTest extends TestCase
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

    public function testExtendInjectsLocaleFieldIntoDialogProps(): void
    {
        $dialog = [
            'props' => [
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title'],
                ],
                'value' => [
                    'title' => 'Test',
                ],
            ],
        ];

        $result = DialogFactory::extend($dialog, 'de');

        $this->assertArrayHasKey(TitleLocale::FIELD_KEY, $result['props']['fields']);
        $this->assertSame('de', $result['props']['value'][TitleLocale::FIELD_KEY]);
    }

    public function testExtendedFieldHasCorrectStructure(): void
    {
        $dialog = [
            'props' => [
                'fields' => [
                    'title' => ['type' => 'text'],
                ],
                'value' => [],
            ],
        ];

        $result = DialogFactory::extend($dialog, 'fr');
        $field = $result['props']['fields'][TitleLocale::FIELD_KEY];

        $this->assertSame('select', $field['type']);
        $this->assertSame('translate', $field['icon']);
        $this->assertSame(TitleLocale::FIELD_KEY, $field['name']);
        $this->assertSame('fr', $field['value']);
        $this->assertIsArray($field['options']);
        $this->assertGreaterThan(0, count($field['options']));
    }

    public function testExtendAppliesFieldOverrides(): void
    {
        $dialog = [
            'props' => [
                'fields' => ['title' => ['type' => 'text']],
                'value' => [],
            ],
        ];

        $overrides = [
            'when' => ['template' => 'article'],
        ];

        $result = DialogFactory::extend($dialog, null, $overrides);
        $field = $result['props']['fields'][TitleLocale::FIELD_KEY];

        $this->assertArrayHasKey('when', $field);
        $this->assertSame(['template' => 'article'], $field['when']);
    }

    public function testExtendReturnsDialogUnchangedWithoutProps(): void
    {
        $dialog = ['component' => 'k-page-create-dialog'];

        $result = DialogFactory::extend($dialog, 'de');

        $this->assertSame($dialog, $result);
    }

    public function testExtendReturnsDialogUnchangedWithoutFields(): void
    {
        $dialog = [
            'props' => [
                'value' => ['title' => 'Test'],
            ],
        ];

        $result = DialogFactory::extend($dialog, 'de');

        $this->assertSame($dialog, $result);
    }

    public function testExtendPreservesExistingDialogFields(): void
    {
        $dialog = [
            'props' => [
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title'],
                    'slug'  => ['type' => 'slug', 'label' => 'Slug'],
                ],
                'value' => [
                    'title' => 'Hello',
                    'slug'  => 'hello',
                ],
            ],
        ];

        $result = DialogFactory::extend($dialog, 'en');

        // Original fields must still be present
        $this->assertArrayHasKey('title', $result['props']['fields']);
        $this->assertArrayHasKey('slug', $result['props']['fields']);
        $this->assertSame('Hello', $result['props']['value']['title']);
        $this->assertSame('hello', $result['props']['value']['slug']);

        // Locale field added alongside
        $this->assertArrayHasKey(TitleLocale::FIELD_KEY, $result['props']['fields']);
    }

    public function testExtendWithNullValueSetsEmptyLocale(): void
    {
        $dialog = [
            'props' => [
                'fields' => ['title' => ['type' => 'text']],
                'value' => ['title' => 'Test'],
            ],
        ];

        $result = DialogFactory::extend($dialog, null);

        $this->assertNull($result['props']['value'][TitleLocale::FIELD_KEY]);
    }

    public function testBuildFieldIncludesSiteLanguagesInOptions(): void
    {
        $field = DialogFactory::buildField();

        $optionValues = array_column($field['options'], 'value');

        // Multilang playground has en + de as site languages
        $this->assertContains('en', $optionValues);
        $this->assertContains('de', $optionValues);
    }

    public function testBuildFieldIncludesGroupLabels(): void
    {
        $field = DialogFactory::buildField();
        $groups = array_unique(array_filter(array_column($field['options'], 'group')));

        // Should have at least one group (site locales)
        $this->assertNotEmpty($groups);
    }

    public function testBuildFieldPrependsCurrentValueWhenMissingFromOptions(): void
    {
        $field = DialogFactory::buildField('x-current-locale');
        $firstOption = $field['options'][0] ?? null;

        $this->assertIsArray($firstOption);
        $this->assertSame('x-current-locale', $firstOption['value']);
        $this->assertSame('x-current-locale', $firstOption['text']);
    }

    public function testBuildFieldHandlesIntlFallbackAndCustomLocaleMetadata(): void
    {
        $this->kirby = $this->bootKirby([
            'options' => [
                'grommasdietz.kirby-locale.catalog' => false,
                'grommasdietz.kirby-locale.locales' => [
                    ['code' => 123, 'name' => 'Numeric'],
                    ['code' => '   ', 'name' => 'Blank'],
                    ['code' => 'de-DE'],
                    ['code' => 'zz', 'name' => 'zz', 'group' => 'Custom Group', 'source' => 123],
                ],
            ],
        ]);
        $this->kirby->impersonate('kirby');

        $field = DialogFactory::buildField();

        $deDeOption = null;
        $zzOption = null;

        foreach ($field['options'] as $option) {
            if (($option['value'] ?? null) === 'de-DE') {
                $deDeOption = $option;
            }

            if (($option['value'] ?? null) === 'zz') {
                $zzOption = $option;
            }
        }

        $this->assertNotNull($deDeOption);
        $this->assertStringContainsString('(de-DE)', $deDeOption['text']);
        $this->assertNotSame('de-DE', $deDeOption['text']);

        $this->assertNotNull($zzOption);
        $this->assertSame('Custom Group', $zzOption['group'] ?? null);
    }

    public function testBuildFieldTreatsNbUnderscoreLanguageAsPreferredLocaleFamily(): void
    {
        $this->kirby = $this->bootKirby([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'nb_NO', 'name' => 'Norwegian Bokmal'],
            ],
            'options' => [
                'grommasdietz.kirby-locale.catalog' => false,
                'grommasdietz.kirby-locale.locales' => [
                    ['code' => 'nb-NO'],
                    ['code' => 'no'],
                ],
            ],
        ]);
        $this->kirby->impersonate('kirby');

        $field = DialogFactory::buildField();
        $groupsByValue = [];

        foreach ($field['options'] as $option) {
            if (!isset($option['value'])) {
                continue;
            }

            $groupsByValue[$option['value']] = $option['group'] ?? null;
        }

        $this->assertArrayHasKey('nb-NO', $groupsByValue);
        $this->assertArrayHasKey('no', $groupsByValue);
        $this->assertSame($groupsByValue['en'], $groupsByValue['nb-NO']);
        $this->assertSame($groupsByValue['en'], $groupsByValue['no']);
    }

    public function testExtendMergesExistingLocaleFieldAndNormalisesNonArrayValues(): void
    {
        $dialog = [
            'props' => [
                'fields' => [
                    'title' => ['type' => 'text'],
                    TitleLocale::FIELD_KEY => ['type' => 'text', 'help' => 'existing'],
                ],
                'value' => 'invalid',
            ],
        ];

        $result = DialogFactory::extend($dialog, 'de', [
            'label' => 'Locale override',
        ]);
        $field = $result['props']['fields'][TitleLocale::FIELD_KEY];

        $this->assertSame('existing', $field['help']);
        $this->assertSame('Locale override', $field['label']);
        $this->assertIsArray($result['props']['value']);
        $this->assertSame('de', $result['props']['value'][TitleLocale::FIELD_KEY]);
    }
}
