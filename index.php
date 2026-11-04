<?php

use GrommasDietz\KirbyLocale\DialogFactory;
use GrommasDietz\KirbyLocale\LocaleHelper;
use GrommasDietz\KirbyLocale\TitleLocale;
use GrommasDietz\KirbyLocale\Translations;
use Kirby\Cms\App;
use Kirby\Sane\Html;

$existingSpanAttributes = Html::$allowedTags['span'] ?? [];
Html::$allowedTags['span'] = array_values(array_unique(array_merge(
    is_array($existingSpanAttributes) ? $existingSpanAttributes : [],
    ['lang', 'class', 'translate']
)));

$translations = Translations::load();

App::plugin('grommasdietz/locale', [
    'translations' => $translations,
    'fields' => ['pagelist' => \GrommasDietz\KirbyLocale\Panel\PageListField::class],
    'areas' => [
        'site' => [
            'dialogs' => [
                'page.create' => [
                    'pattern' => 'pages/create',
                    'action' => \GrommasDietz\KirbyLocale\Panel\PageCreateDialogController::class,
                ],
                'page.changeTitle' => [
                    'pattern' => 'pages/(:any)/changeTitle',
                    'action' => \GrommasDietz\KirbyLocale\Panel\PageChangeTitleDialogController::class,
                ],
            ],
        ],
    ],
    'api' => [
        'routes' => [
            [
                'pattern' => 'grommasdietz/locale/locales',
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
                'pattern' => 'grommasdietz/locale/options',
                'method'  => 'GET',
                'action'  => function () {
                    $field = DialogFactory::buildField();

                    return $field['options'] ?? [];
                },
            ],
            [
                'pattern' => 'grommasdietz/locale/title-locale',
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
    ],
]);
