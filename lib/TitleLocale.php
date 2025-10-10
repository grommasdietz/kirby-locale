<?php

namespace Grommasdietz\KirbyLocale;

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

        if (!$kirby) {
            return;
        }

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

        $updateSucceeded = false;
        $payload = [
            self::FIELD_KEY => $value,
        ];

        try {
            try {
                $page->update($payload, $languageCode, false);
            } catch (\ArgumentCountError $argumentCountError) {
                $page->update($payload, $languageCode);
            }

            $updateSucceeded = true;
        } catch (\Throwable $exception) {
            if (method_exists($kirby, 'logger')) {
                $kirby->logger('grommasdietz/kirby-locale')->error($exception->getMessage(), [
                    'exception' => $exception,
                    'page'      => $page->id(),
                    'language'  => $languageCode,
                ]);
            }

            return;
        }

        if ($updateSucceeded === false) {
            return;
        }
    }

    public static function handlePageLocaleUpdate($page, mixed $explicitLanguage = null): void
    {
        if (!$page) {
            return;
        }

        $kirby = App::instance();

        if (!$kirby) {
            return;
        }

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

    public static function handleRouteAfter($route, $path, $method, $result, $final)
    {
        if ($route === null) {
            return $result;
        }

        $methodUpper = strtoupper($method);

        if ($methodUpper !== 'POST' && $methodUpper !== 'PATCH') {
            return $result;
        }

        $pattern = $route->pattern();

        if ($pattern === 'pages/(:any)/changeTitle') {
            $kirby = App::instance();

            if (!$kirby) {
                return $result;
            }

            $request = $kirby->request();
            [$hasValue, $rawValue] = LocaleHelper::extractLocaleFromRequest($request);

            if ($hasValue === false) {
                return $result;
            }

            $arguments = $route->arguments();
            $pageId = $arguments[0] ?? null;
            $newSlug = $request->get('slug');
            $page = self::resolvePageAfterTitleDialog($pageId, is_string($newSlug) ? trim($newSlug) : null);

            if ($page === null) {
                return $result;
            }

            $languageCode = LocaleHelper::resolveLanguageCode($kirby, $request->get('language'));
            self::store($page, $rawValue, $languageCode);
        }

        return $result;
    }

    public static function resolvePageAfterTitleDialog(?string $rawId, ?string $newSlug): ?Page
    {
        if ($rawId === null || $rawId === '') {
            return null;
        }

        $kirby = App::instance();

        if (!$kirby) {
            return null;
        }

        $decodedId = trim(str_replace(['+', ' '], '/', $rawId), '/');
        $candidates = [];

        if (is_string($newSlug) && trim($newSlug) !== '') {
            $slug = trim($newSlug);
            $parent = trim(dirname($decodedId), '/');

            if ($parent === '.' || $parent === '') {
                $candidates[] = $slug;
            } else {
                $candidates[] = trim($parent . '/' . $slug, '/');
            }
        }

        if ($decodedId !== '') {
            $candidates[] = $decodedId;
        }

        if ($rawId !== $decodedId) {
            $candidates[] = $rawId;
        }

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }

            $page = $kirby->page($candidate);

            if ($page) {
                return $page;
            }
        }

        return null;
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
