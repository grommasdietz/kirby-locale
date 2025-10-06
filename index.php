<?php

use Kirby\Cms\App;
use Kirby\Cms\Find;
use Kirby\Sane\Html;
use Kirby\Toolkit\I18n;

Html::$allowedTags['span'] = ['lang', 'class'];

$translationsDirectory = __DIR__ . '/translations';

$translationFiles = [
    'en' => $translationsDirectory . '/en.php',
    'de' => $translationsDirectory . '/de.php',
    'fr' => $translationsDirectory . '/fr.php',
    'es' => $translationsDirectory . '/es.php',
    'pl' => $translationsDirectory . '/pl.php',
    'nl' => $translationsDirectory . '/nl.php',
    'da' => $translationsDirectory . '/da.php',
    'sv' => $translationsDirectory . '/sv.php',
    'nb' => $translationsDirectory . '/nb.php',
    'cs' => $translationsDirectory . '/cs.php',
];

$translations = [];

$defaultEnglish = [
    'grommasdietz.kirby-locale.label'              => 'Locale',
    'grommasdietz.kirby-locale.dialog.headline'    => 'Choose locale for selection',
    'grommasdietz.kirby-locale.dialog.select.label' => 'Locale',
    'grommasdietz.kirby-locale.dialog.empty'       => 'No locale',
    'grommasdietz.kirby-locale.prompt'             => 'Enter the locale code (e.g. de, en-GB)',
];

foreach ($translationFiles as $locale => $path) {
    if (!is_file($path)) {
        $translations[$locale] = $locale === 'en' ? $defaultEnglish : [];
        continue;
    }

    $data = require $path;

    $translations[$locale] = is_array($data) ? $data : [];
}

$englishFallback = $translations['en'] ?? $defaultEnglish;
$translations['en'] = $englishFallback;

foreach ($translations as $locale => $strings) {
    if ($locale === 'en') {
        continue;
    }

    $translations[$locale] = $strings + $englishFallback;
}

$isoLanguageCatalog = require __DIR__ . '/resources/iso-639-1.php';

$normaliseLocaleDefinition = static function ($locale, ?string $defaultGroup = null) {
    if (is_string($locale) && $locale !== '') {
        return [
            'code'  => $locale,
            'name'  => $locale,
            'group' => $defaultGroup,
        ];
    }

    if (is_array($locale) && isset($locale['code'])) {
        $name = $locale['name'] ?? $locale['label'] ?? $locale['text'] ?? $locale['code'];
        $group = $locale['group'] ?? $locale['continent'] ?? $locale['region'] ?? $defaultGroup;

        if (is_string($group)) {
            $group = trim($group) ?: null;
        } else {
            $group = null;
        }

        return [
            'code'  => $locale['code'],
            'name'  => is_string($name) && $name !== '' ? $name : $locale['code'],
            'group' => $group,
        ];
    }

    return null;
};

$collectLocales = static function (App $kirby) use ($normaliseLocaleDefinition, $isoLanguageCatalog) {
    $collected = [];
    $seen      = [];

    $push = static function ($locale) use (&$collected, &$seen, $normaliseLocaleDefinition) {
        $normalised = $normaliseLocaleDefinition($locale);

        if ($normalised === null) {
            return;
        }

        $code = strtolower($normalised['code']);

        if ($code === '' || isset($seen[$code])) {
            return;
        }

        $seen[$code] = true;
        $collected[]  = [
            'code'  => $normalised['code'],
            'name'  => $normalised['name'],
            'group' => $normalised['group'] ?? null,
        ];
    };

    $languages = $kirby->languages();

    if ($languages) {
        foreach ($languages as $language) {
            $push([
                'code'  => $language->code(),
                'name'  => $language->name(),
            ]);
        }
    }

    $pluginLocales = $kirby->option('grommasdietz.kirby-locale.locales', []);

    if (is_callable($pluginLocales)) {
        $pluginLocales = $pluginLocales($kirby);
    }

    foreach ((array) $pluginLocales as $locale) {
        $push($locale);
    }

    $catalogPreference = $kirby->option('grommasdietz.kirby-locale.catalog');

    if ($catalogPreference !== false) {
        $fallback = $catalogPreference && $catalogPreference !== true ? $catalogPreference : $isoLanguageCatalog;

        foreach ((array) $fallback as $locale) {
            $push($locale);
        }
    }

    return $collected;
};

$normaliseTitleLocale = static function ($value) {
    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    return null;
};

$resolveLanguageCode = static function ($kirby, mixed $explicit = null): ?string {
    if (!$kirby) {
        return null;
    }

    if ($kirby->multilang() === false) {
        return null;
    }

    if (is_string($explicit) && $explicit !== '') {
        return $explicit;
    }

    $requestLanguage = $kirby->request()->get('language');

    if (is_string($requestLanguage) && $requestLanguage !== '') {
        return $requestLanguage;
    }

    return $kirby->language()?->code() ?? $kirby->defaultLanguage()?->code();
};

$extractLocaleFromRequest = static function ($request) {
    $value = $request->get('titleLocale');
    $provided = $value !== null;

    $data = $request->data();

    if ($provided === false && is_array($data)) {
        if (array_key_exists('titleLocale', $data)) {
            $value = $data['titleLocale'];
            $provided = true;
        } elseif (
            isset($data['content']) &&
            is_array($data['content']) &&
            array_key_exists('titleLocale', $data['content'])
        ) {
            $value = $data['content']['titleLocale'];
            $provided = true;
        }
    }

    return [$provided, $value];
};

$normaliseTemplateList = static function ($templates) {
    if (is_callable($templates)) {
        $templates = $templates();
    }

    if ($templates === null || $templates === '') {
        return [];
    }

    if ($templates === true) {
        return null;
    }

    if ($templates === false) {
        return [];
    }

    if (is_string($templates)) {
        $trimmed = trim($templates);

        if ($trimmed === '*' || strtolower($trimmed) === 'all') {
            return null;
        }

        if ($trimmed === '') {
            return [];
        }

        $templates = [$trimmed];
    }

    $list = [];

    foreach ((array) $templates as $template) {
        if (!is_string($template)) {
            continue;
        }

        $trimmed = trim($template);

        if ($trimmed === '') {
            continue;
        }

        $list[$trimmed] = true;
    }

    if ($list === []) {
        return [];
    }

    return array_keys($list);
};

$getEnabledTitleLocaleTemplates = static function (App $kirby) use ($normaliseTemplateList) {
    $option = $kirby->option('grommasdietz.kirby-locale.intendedTemplate');

    return $normaliseTemplateList($option);
};

$templateAllowsLocale = static function (?string $template, ?array $allowed) {
    if ($allowed === null) {
        return true;
    }

    if ($allowed === []) {
        return false;
    }

    if ($template === null) {
        return false;
    }

    return in_array($template, $allowed, true);
};

$storeTitleLocale = static function ($page, mixed $rawValue, ?string $languageCode) use ($normaliseTitleLocale, $templateAllowsLocale, $getEnabledTitleLocaleTemplates) {
    if (!$page) {
        return;
    }

    $kirby = App::instance();

    if (!$kirby) {
        return;
    }

    $allowed    = $getEnabledTitleLocaleTemplates($kirby);

    if ($templateAllowsLocale($page->intendedTemplate()->name(), $allowed) === false) {
        return;
    }

    $value      = $normaliseTitleLocale($rawValue);
    $content    = $languageCode ? $page->content($languageCode) : $page->content();
    $current    = $content->get('titleLocale');
    $existing   = $normaliseTitleLocale($current?->value());

    if ($existing === $value) {
        return;
    }

    try {
        try {
            $page->update([
                'titleLocale' => $value,
            ], $languageCode, false);
        } catch (\ArgumentCountError $argumentCountError) {
            $page->update([
                'titleLocale' => $value,
            ], $languageCode);
        }
    } catch (\Throwable $exception) {
        if (method_exists($kirby, 'logger')) {
            $kirby->logger('grommasdietz/kirby-locale')->error($exception->getMessage(), [
                'exception' => $exception,
                'page'      => $page->id(),
                'language'  => $languageCode,
            ]);
        }
    }
};

$handlePageLocaleUpdate = static function ($page, mixed $explicitLanguage = null) use ($resolveLanguageCode, $storeTitleLocale, $extractLocaleFromRequest, $templateAllowsLocale, $getEnabledTitleLocaleTemplates) {
    if (!$page) {
        return;
    }

    $kirby = App::instance();

    if (!$kirby) {
        return;
    }

    $allowed = $getEnabledTitleLocaleTemplates($kirby);

    if ($templateAllowsLocale($page->intendedTemplate()->name(), $allowed) === false) {
        return;
    }

    $request = $kirby->request();
    [$hasValue, $rawValue] = $extractLocaleFromRequest($request);

    if ($hasValue === false) {
        return;
    }

    $languageCode = $resolveLanguageCode($kirby, $explicitLanguage);
    $storeTitleLocale($page, $rawValue, $languageCode);
};

$resolvePageAfterTitleDialog = static function (string|null $rawId, string|null $newSlug) {
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
};

$handleRouteAfter = static function ($route, $path, $method, $result, $final) use ($storeTitleLocale, $resolveLanguageCode, $extractLocaleFromRequest, $resolvePageAfterTitleDialog) {
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
        [$hasValue, $rawValue] = $extractLocaleFromRequest($request);

        if ($hasValue === false) {
            return $result;
        }

        $arguments = $route->arguments();
        $pageId    = $arguments[0] ?? null;
        $newSlug   = $request->get('slug');
        $page      = $resolvePageAfterTitleDialog($pageId, is_string($newSlug) ? trim($newSlug) : null);

        if ($page === null) {
            return $result;
        }

        $languageCode = $resolveLanguageCode($kirby, $request->get('language'));
        $storeTitleLocale($page, $rawValue, $languageCode);
    }

    return $result;
};

$getStoredTitleLocale = static function ($page, ?string $languageCode) use ($normaliseTitleLocale) {
    if (!$page) {
        return null;
    }

    $content = $languageCode ? $page->content($languageCode) : $page->content();
    $field   = $content->get('titleLocale');
    $value   = $field?->value();

    return $normaliseTitleLocale($value);
};

$buildDialogLocaleField = static function (?string $currentValue = null, array $overrides = []) use ($collectLocales) {
    $kirby          = App::instance();
    $options        = [];
    $preferredCodes = [];

    if ($kirby) {
        $locales = $collectLocales($kirby);

        $options = array_map(static function ($locale) {
            $code = $locale['code'] ?? null;

            if (!is_string($code) || $code === '') {
                return null;
            }

            $name = $locale['name'] ?? $code;
            $text = $name === $code ? $code : sprintf('%s (%s)', $name, $code);

            return [
                'value' => $code,
                'text'  => $text,
            ];
        }, $locales);

        $options = array_values(array_filter($options));

        $languages = $kirby->languages();

        if ($languages) {
            foreach ($languages as $language) {
                $code = $language->code();

                if (is_string($code) && $code !== '') {
                    $preferredCodes[] = strtolower($code);
                }
            }
        }
    }

    $enabledOptions = array_values(array_filter($options, static function ($option) {
        return !isset($option['disabled']) || $option['disabled'] !== true;
    }));

    $value = $currentValue;

    if ($value === null && $enabledOptions !== []) {
        $preferred = null;

        if ($preferredCodes !== []) {
            foreach ($enabledOptions as $option) {
                $optionValue = $option['value'] ?? null;

                if (!is_string($optionValue) || $optionValue === '') {
                    continue;
                }

                if (in_array(strtolower($optionValue), $preferredCodes, true)) {
                    $preferred = $optionValue;
                    break;
                }
            }
        }

        if ($preferred === null) {
            $firstValue = $enabledOptions[0]['value'] ?? null;
            $preferred = is_string($firstValue) && $firstValue !== '' ? $firstValue : null;
        }

        $value = $preferred;
    }

    $field = [
        'label'     => I18n::translate('grommasdietz.kirby-locale.label', 'Locale'),
        'type'      => 'select',
        'icon'      => 'translate',
        'width'     => '1/3',
        'name'      => 'titleLocale',
        'options'   => $options,
        'empty'     => [
            'text' => I18n::translate('grommasdietz.kirby-locale.dialog.empty', 'No locale'),
        ],
        'search'    => count($options) > 7,
        'translate' => false,
        'value'     => $value,
    ];

    if ($overrides !== []) {
        $field = array_replace($field, $overrides);
    }

    $field['value'] = $value;

    return $field;
};

$extendDialogWithLocaleField = static function (array $dialog, ?string $value = null, array $fieldOverrides = []) use ($buildDialogLocaleField) {
    if (!isset($dialog['props']) || !is_array($dialog['props'])) {
        return $dialog;
    }

    $fields = $dialog['props']['fields'] ?? null;

    if (!is_array($fields)) {
        return $dialog;
    }

    $field = $buildDialogLocaleField($value, $fieldOverrides);

    if (isset($fields['titleLocale']) && is_array($fields['titleLocale'])) {
        $field = array_replace($field, $fields['titleLocale']);
    }

    $field['value'] = $value;

    $fields['titleLocale'] = $field;

    if (isset($fields['title']) && is_array($fields['title'])) {
        $fields['title'] = array_replace($fields['title'], [
            'width' => '2/3',
        ]);
    }

    $values = $dialog['props']['value'] ?? [];

    if (!is_array($values)) {
        $values = [];
    }

    $values['titleLocale'] = $value;

    $dialog['props']['fields'] = $fields;
    $dialog['props']['value']  = $values;

    return $dialog;
};

App::plugin('grommasdietz/kirby-locale', [
    'translations' => $translations,
    'areas' => [
        'site' => function (App $kirby) use (
            $extendDialogWithLocaleField,
            $extractLocaleFromRequest,
            $normaliseTitleLocale,
            $resolveLanguageCode,
            $getStoredTitleLocale,
            $getEnabledTitleLocaleTemplates,
            $templateAllowsLocale,
            $storeTitleLocale,
            $resolvePageAfterTitleDialog
        ) {
            $core       = $kirby->core()->area('site');
            $coreDialogs = $core['dialogs'] ?? [];
            $dialogs    = [];
            $allowedTemplates = $getEnabledTitleLocaleTemplates($kirby);

            $pageCreate = $coreDialogs['page.create'] ?? null;

            if ($allowedTemplates !== [] && is_array($pageCreate) && isset($pageCreate['load']) && is_callable($pageCreate['load'])) {
                $load = $pageCreate['load'];
                $fieldOverrides = [];

                if (is_array($allowedTemplates)) {
                    $fieldOverrides['when'] = [
                        'template' => count($allowedTemplates) === 1 ? $allowedTemplates[0] : $allowedTemplates,
                    ];
                }

                $dialogs['page.create'] = array_replace($pageCreate, [
                    'load' => function () use ($kirby, $load, $extendDialogWithLocaleField, $extractLocaleFromRequest, $normaliseTitleLocale, $fieldOverrides, $resolveLanguageCode) {
                        $dialog  = $load();
                        $request = $kirby->request();
                        [$hasValue, $rawValue] = $extractLocaleFromRequest($request);
                        $value = $hasValue ? $normaliseTitleLocale($rawValue) : null;

                        if ($value === null) {
                            $language = $resolveLanguageCode($kirby, $request->get('language'));

                            if (is_string($language) && $language !== '') {
                                $value = $language;
                            }
                        }

                        return $extendDialogWithLocaleField($dialog, $value, $fieldOverrides);
                    },
                ]);
            }

            $pageChangeTitle = $coreDialogs['page.changeTitle'] ?? null;

            if ($allowedTemplates !== [] && is_array($pageChangeTitle) && isset($pageChangeTitle['load']) && is_callable($pageChangeTitle['load'])) {
                $load = $pageChangeTitle['load'];
                $submit = $pageChangeTitle['submit'] ?? null;

                $dialogs['page.changeTitle'] = array_replace($pageChangeTitle, [
                    'load' => function (string $id) use (
                        $kirby,
                        $load,
                        $extendDialogWithLocaleField,
                        $extractLocaleFromRequest,
                        $normaliseTitleLocale,
                        $resolveLanguageCode,
                        $getStoredTitleLocale,
                        $templateAllowsLocale,
                        $allowedTemplates
                    ) {
                        $dialog  = $load($id);
                        $request = $kirby->request();
                        [$hasValue, $rawValue] = $extractLocaleFromRequest($request);
                        $languageCode = $resolveLanguageCode($kirby, $request->get('language'));
                        $value = $hasValue ? $normaliseTitleLocale($rawValue) : null;
                        $fieldOverrides = [];
                        $pageTemplate = null;
                        $page = Find::page($id);

                        if ($page) {
                            $pageTemplate = $page->intendedTemplate()->name();
                        }

                        if ($templateAllowsLocale($pageTemplate, $allowedTemplates) === false) {
                            return $dialog;
                        }

                        if ($value === null && $page) {
                            $value = $getStoredTitleLocale($page, $languageCode);
                        }

                        if ($value === null && is_string($languageCode) && $languageCode !== '') {
                            $value = $languageCode;
                        }

                        return $extendDialogWithLocaleField($dialog, $value, $fieldOverrides);
                    },
                    'submit' => is_callable($submit)
                        ? function (string $id) use (
                            $submit,
                            $kirby,
                            $extractLocaleFromRequest,
                            $resolveLanguageCode,
                            $storeTitleLocale,
                            $resolvePageAfterTitleDialog
                        ) {
                            $result  = $submit($id);
                            $request = $kirby->request();
                            [$hasValue, $rawValue] = $extractLocaleFromRequest($request);

                            if ($hasValue === true) {
                                $languageCode = $resolveLanguageCode($kirby, $request->get('language'));
                                $newSlug      = $request->get('slug');
                                $page         = $resolvePageAfterTitleDialog($id, is_string($newSlug) ? trim($newSlug) : null);

                                if ($page) {
                                    $storeTitleLocale($page, $rawValue, $languageCode);
                                }
                            }

                            return $result;
                        }
                        : ($pageChangeTitle['submit'] ?? null),
                ]);
            }

            if ($dialogs === []) {
                return [];
            }

            return [
                'dialogs' => $dialogs,
            ];
        },
    ],
    'api' => [
        'routes' => [
            [
                'pattern' => 'grommasdietz/kirby-locale/locales',
                'method'  => 'GET',
                'action'  => function () use ($collectLocales) {
                    $kirby = App::instance();

                    if (!$kirby) {
                        return [];
                    }

                    return $collectLocales($kirby);
                },
            ],
            [
                'pattern' => 'grommasdietz/kirby-locale/title-locale',
                'method'  => 'GET',
                'action'  => function () {
                    $kirby = App::instance();

                    if (!$kirby) {
                        return [
                            'titleLocale' => null,
                        ];
                    }

                    $request  = $kirby->request();
                    $id       = (string) $request->get('id', '');
                    $language = $request->get('language');

                    if ($id === '') {
                        return [
                            'titleLocale' => null,
                        ];
                    }

                    $page = $kirby->page($id);

                    if ($page === null) {
                        return [
                            'titleLocale' => null,
                        ];
                    }

                    $languageCode = null;

                    if ($kirby->multilang() === true) {
                        if (is_string($language) && $language !== '') {
                            $languageCode = $language;
                        } else {
                            $languageCode = $kirby->language()?->code();
                        }
                    }

                    $content = $languageCode ? $page->content($languageCode) : $page->content();
                    $field   = $content->get('titleLocale');
                    $value   = $field?->value();

                    if (is_string($value)) {
                        $value = trim($value);
                        $value = $value === '' ? null : $value;
                    }

                    return [
                        'titleLocale' => $value,
                    ];
                },
            ],
        ],
    ],
    'hooks' => [
        'page.create:after' => function ($page) use ($handlePageLocaleUpdate) {
            $handlePageLocaleUpdate($page);
        },
        'page.changeTitle:after' => function ($newPage) use ($handlePageLocaleUpdate) {
            $handlePageLocaleUpdate($newPage);
        },
        'page.changeSlug:after' => function ($newPage) use ($handlePageLocaleUpdate) {
            $handlePageLocaleUpdate($newPage);
        },
        'route:after' => function ($route, $path, $method, $result, $final) use ($handleRouteAfter) {
            return $handleRouteAfter($route, $path, $method, $result, $final);
        },
    ],
]);
