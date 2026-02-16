<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Unit;

use GrommasDietz\KirbyLocale\LocaleHelper;
use GrommasDietz\KirbyLocale\TitleLocale;
use Kirby\Cms\App;
use Kirby\Cms\Language;
use Kirby\Http\Request;
use PHPUnit\Framework\TestCase;

final class LocaleHelperCoverageTest extends TestCase
{
    public function testCanonicaliseLocaleTagFallsBackWhenIntlReturnsEmptyString(): void
    {
        $this->assertSame('--', LocaleHelper::canonicaliseLocaleTag('--'));
    }

    public function testLocaleCandidateKeysIncludeRegionalAndNorwegianFallbacks(): void
    {
        $this->assertSame(['en'], LocaleHelper::localeCandidateKeys(null));
        $this->assertSame(['de-at', 'de', 'en'], LocaleHelper::localeCandidateKeys('de-AT'));
        $this->assertSame(['nb', 'no', 'en'], LocaleHelper::localeCandidateKeys('nb'));
    }

    public function testNormaliseLowercaseReturnsEmptyForNonString(): void
    {
        $this->assertSame('', LocaleHelper::normaliseLowercase(null));
    }

    public function testResolveLanguageCodeReturnsNullWhenMultilangIsDisabled(): void
    {
        $kirby = $this->createMock(App::class);
        $kirby->method('multilang')->willReturn(false);

        $this->assertNull(LocaleHelper::resolveLanguageCode($kirby));
    }

    public function testResolveLanguageCodeUsesExplicitLanguageWhenProvided(): void
    {
        $kirby = $this->createMock(App::class);
        $kirby->method('multilang')->willReturn(true);
        $kirby->method('request')->willReturn(new Request(['query' => ['language' => 'de']]));

        $this->assertSame('fr', LocaleHelper::resolveLanguageCode($kirby, 'fr'));
    }

    public function testResolveLanguageCodeUsesRequestLanguageWhenNoExplicitValueExists(): void
    {
        $kirby = $this->createMock(App::class);
        $kirby->method('multilang')->willReturn(true);
        $kirby->method('request')->willReturn(new Request(['query' => ['language' => 'de']]));
        $kirby->method('language')->willReturn($this->mockLanguage('en'));
        $kirby->method('defaultLanguage')->willReturn($this->mockLanguage('en'));

        $this->assertSame('de', LocaleHelper::resolveLanguageCode($kirby));
    }

    public function testResolveLanguageCodeFallsBackToCurrentOrDefaultLanguage(): void
    {
        $kirbyWithCurrent = $this->createMock(App::class);
        $kirbyWithCurrent->method('multilang')->willReturn(true);
        $kirbyWithCurrent->method('request')->willReturn(new Request(['query' => []]));
        $kirbyWithCurrent->method('language')->willReturn($this->mockLanguage('it'));
        $kirbyWithCurrent->method('defaultLanguage')->willReturn($this->mockLanguage('en'));

        $this->assertSame('it', LocaleHelper::resolveLanguageCode($kirbyWithCurrent));

        $kirbyWithDefault = $this->createMock(App::class);
        $kirbyWithDefault->method('multilang')->willReturn(true);
        $kirbyWithDefault->method('request')->willReturn(new Request(['query' => []]));
        $kirbyWithDefault->method('language')->willReturn(null);
        $kirbyWithDefault->method('defaultLanguage')->willReturn($this->mockLanguage('en'));

        $this->assertSame('en', LocaleHelper::resolveLanguageCode($kirbyWithDefault));
    }

    public function testExtractLocaleFromRequestReadsTopLevelNullValueFromData(): void
    {
        $request = new Request([
            'body' => [
                TitleLocale::FIELD_KEY => null,
            ],
        ]);

        [$provided, $value] = LocaleHelper::extractLocaleFromRequest($request);

        $this->assertTrue($provided);
        $this->assertNull($value);
    }

    public function testExtractLocaleFromRequestReadsNestedContentValue(): void
    {
        $request = new Request([
            'body' => [
                'content' => [
                    TitleLocale::FIELD_KEY => 'pt',
                ],
            ],
        ]);

        [$provided, $value] = LocaleHelper::extractLocaleFromRequest($request);

        $this->assertTrue($provided);
        $this->assertSame('pt', $value);
    }

    public function testNormaliseTemplateListHandlesWhitespaceAndInvalidEntries(): void
    {
        $this->assertSame([], LocaleHelper::normaliseTemplateList('   '));
        $this->assertSame([], LocaleHelper::normaliseTemplateList([null, 123, '  ']));
    }

    private function mockLanguage(string $code): Language
    {
        $language = $this->createMock(Language::class);
        $language->method('code')->willReturn($code);

        return $language;
    }
}
