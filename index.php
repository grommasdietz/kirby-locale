<?php

use Grommasdietz\KirbyLocale\DialogFactory;
use Grommasdietz\KirbyLocale\LocaleHelper;
use Grommasdietz\KirbyLocale\TitleLocale;
use Grommasdietz\KirbyLocale\Translations;
use Kirby\Cms\App;
use Kirby\Sane\Html;

require_once __DIR__ . '/lib/Translations.php';
require_once __DIR__ . '/lib/IsoCatalog.php';
require_once __DIR__ . '/lib/LocaleHelper.php';
require_once __DIR__ . '/lib/DialogFactory.php';
require_once __DIR__ . '/lib/TitleLocale.php';

$existingSpanAttributes = Html::$allowedTags['span'] ?? [];
Html::$allowedTags['span'] = array_values(array_unique(array_merge(
    is_array($existingSpanAttributes) ? $existingSpanAttributes : [],
    ['lang', 'class']
)));

$translations = Translations::load();

App::plugin('grommasdietz/kirby-locale', [
    'translations' => $translations,
    'areas' => [
        'site' => function (App $kirby) {
            $core = $kirby->core()->area('site');
            $coreDialogs = $core['dialogs'] ?? [];
            $dialogs = [];
            $allowedTemplates = LocaleHelper::getEnabledTitleLocaleTemplates($kirby);

            $pageCreate = $coreDialogs['page.create'] ?? null;

            if (
                $allowedTemplates !== [] &&
                is_array($pageCreate) &&
                isset($pageCreate['load']) &&
                is_callable($pageCreate['load'])
            ) {
                $load = $pageCreate['load'];
                $fieldOverrides = [];

                if (is_array($allowedTemplates)) {
                    $fieldOverrides['when'] = [
                        'template' => count($allowedTemplates) === 1 ? $allowedTemplates[0] : $allowedTemplates,
                    ];
                }

                $dialogs['page.create'] = array_replace($pageCreate, [
                    'load' => function () use ($kirby, $load, $fieldOverrides) {
                        $dialog = $load();
                        $request = $kirby->request();
                        [$hasValue, $rawValue] = LocaleHelper::extractLocaleFromRequest($request);
                        $value = $hasValue ? LocaleHelper::normaliseTitleLocale($rawValue) : null;

                        return DialogFactory::extend($dialog, $value, $fieldOverrides);
                    },
                ]);
            }

            $pageChangeTitle = $coreDialogs['page.changeTitle'] ?? null;

            if (
                $allowedTemplates !== [] &&
                is_array($pageChangeTitle) &&
                isset($pageChangeTitle['load']) &&
                is_callable($pageChangeTitle['load'])
            ) {
                $load = $pageChangeTitle['load'];
                $submit = $pageChangeTitle['submit'] ?? null;

                $dialogs['page.changeTitle'] = array_replace($pageChangeTitle, [
                    'load' => function (string $id) use ($kirby, $load, $allowedTemplates) {
                        $dialog = $load($id);
                        $request = $kirby->request();
                        [$hasValue, $rawValue] = LocaleHelper::extractLocaleFromRequest($request);
                        $languageCode = LocaleHelper::resolveLanguageCode($kirby, $request->get('language'));
                        $value = $hasValue ? LocaleHelper::normaliseTitleLocale($rawValue) : null;
                        $fieldOverrides = [];
                        $pageTemplate = null;
                        $page = LocaleHelper::findPage($id);

                        if ($page) {
                            $pageTemplate = $page->intendedTemplate()->name();
                        }

                        if (LocaleHelper::templateAllowsLocale($pageTemplate, $allowedTemplates) === false) {
                            return $dialog;
                        }

                        if ($value === null && $page) {
                            $value = TitleLocale::getStored($page, $languageCode);
                        }

                        return DialogFactory::extend($dialog, $value, $fieldOverrides);
                    },
                    'submit' => is_callable($submit)
                        ? function (string $id) use ($submit, $kirby) {
                            $result = $submit($id);
                            $request = $kirby->request();
                            [$hasValue, $rawValue] = LocaleHelper::extractLocaleFromRequest($request);

                            if ($hasValue === true) {
                                $languageCode = LocaleHelper::resolveLanguageCode($kirby, $request->get('language'));
                                $newSlug = $request->get('slug');
                                $page = TitleLocale::resolvePageAfterTitleDialog(
                                    $id,
                                    is_string($newSlug) ? trim($newSlug) : null
                                );

                                if ($page) {
                                    TitleLocale::store($page, $rawValue, $languageCode);
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
                'action'  => function () {
                    $kirby = App::instance();

                    if (!$kirby) {
                        return [];
                    }

                    return LocaleHelper::collectLocales($kirby);
                },
            ],
            [
                'pattern' => 'grommasdietz/kirby-locale/options',
                'method'  => 'GET',
                'action'  => function () {
                    $field = DialogFactory::buildField();

                    return $field['options'] ?? [];
                },
            ],
            [
                'pattern' => 'grommasdietz/kirby-locale/title-locale',
                'method'  => 'GET',
                'action'  => function () {
                    $kirby = App::instance();

                    if (!$kirby) {
                        return [
                            TitleLocale::FIELD_KEY => null,
                        ];
                    }

                    $request = $kirby->request();
                    $id = (string) $request->get('id', '');
                    $language = $request->get('language');

                    if ($id === '') {
                        return [
                            TitleLocale::FIELD_KEY => null,
                        ];
                    }

                    $page = $kirby->page($id);

                    if ($page === null) {
                        return [
                            TitleLocale::FIELD_KEY => null,
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

                    return [
                        TitleLocale::FIELD_KEY => TitleLocale::getStored($page, $languageCode),
                    ];
                },
            ],
        ],
    ],
    'hooks' => [
        'page.create:after' => function ($page) {
            TitleLocale::handlePageLocaleUpdate($page);
        },
        'page.changeTitle:after' => function ($newPage) {
            TitleLocale::handlePageLocaleUpdate($newPage);
        },
        'page.changeSlug:after' => function ($newPage) {
            TitleLocale::handlePageLocaleUpdate($newPage);
        },
        'route:after' => function ($route, $path, $method, $result, $final) {
            return TitleLocale::handleRouteAfter($route, $path, $method, $result, $final);
        },
    ],
]);
