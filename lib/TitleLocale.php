<?php

namespace GrommasDietz\KirbyLocale;

use Kirby\Cms\App;
use Kirby\Cms\Page;

final class TitleLocale
{
    public const FIELD_KEY = 'title_locale';

    public static function store(?Page $page, mixed $rawValue, ?string $languageCode): void
    {
        if (!$page) {
            return;
        }

        $kirby = App::instance();
        $allowed = LocaleHelper::getEnabledTitleLocaleTemplates($kirby);

        if (LocaleHelper::templateAllowsLocale($page->intendedTemplate()->name(), $allowed) === false) {
            return;
        }

        $value = LocaleHelper::normaliseTitleLocale($rawValue);
        $content = $languageCode ? $page->content($languageCode) : $page->content();
        $field = $content->get(self::FIELD_KEY);
        $existing = LocaleHelper::normaliseTitleLocale($field?->value());

        if ($existing === $value) {
            return;
        }

        $payload = [
            self::FIELD_KEY => $value,
        ];

        try {
            try {
                $page->update($payload, $languageCode, false);
                // @codeCoverageIgnoreStart
            } catch (\ArgumentCountError $argumentCountError) {
                $page->update($payload, $languageCode);
                // @codeCoverageIgnoreEnd
            }

            // @codeCoverageIgnoreStart
        } catch (\Throwable $exception) {
            if (method_exists($kirby, 'logger')) {
                /** @disregard P1013 Kirby 5 runtime method guarded by method_exists() */
                $kirby->logger('grommasdietz/kirby-locale')->error($exception->getMessage(), [
                    'exception' => $exception,
                    'page'      => $page->id(),
                    'language'  => $languageCode,
                ]);
            }

            return;
            // @codeCoverageIgnoreEnd
        }
    }

    public static function handlePageLocaleUpdate(mixed $page, mixed $explicitLanguage = null): void
    {
        if (!$page) {
            return;
        }

        $kirby = App::instance();
        $allowed = LocaleHelper::getEnabledTitleLocaleTemplates($kirby);

        if (LocaleHelper::templateAllowsLocale($page->intendedTemplate()->name(), $allowed) === false) {
            return;
        }

        $request = $kirby->request();
        [$hasValue, $rawValue] = LocaleHelper::extractLocaleFromRequest($request);

        if ($hasValue === false) {
            return;
        }

        $languageCode = LocaleHelper::resolveLanguageCode($kirby, $explicitLanguage);
        self::store($page, $rawValue, $languageCode);
    }

    public static function getStored(?Page $page, ?string $languageCode)
    {
        if (!$page) {
            return null;
        }

        $content = $languageCode ? $page->content($languageCode) : $page->content();
        $field = $content->get(self::FIELD_KEY);
        $value = $field?->value();

        return LocaleHelper::normaliseTitleLocale($value);
    }
}
