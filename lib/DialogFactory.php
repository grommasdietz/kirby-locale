<?php

namespace GrommasDietz\KirbyLocale;

use Kirby\Cms\App;
use Kirby\Toolkit\I18n;

final class DialogFactory
{
    public static function buildField(?string $currentValue = null, array $overrides = []): array
    {
        $kirby = App::instance();
        $options = [];
        $seen = [];

        $siteGroupLabel = I18n::translate('grommasdietz.kirby-locale.dialog.group.site', 'Site locales');
        $otherGroupLabel = I18n::translate('grommasdietz.kirby-locale.dialog.group.other', 'Other locales');

        $currentValue = is_string($currentValue) ? trim($currentValue) : '';
        $currentGroupLabel = null;
        $locales = [];

        if ($kirby) {
            $translations = IsoCatalog::translations();
            $panelLocale = I18n::locale() ?: 'en';
            $candidateLocales = LocaleHelper::localeCandidateKeys($panelLocale);
            $preferredSet = [];
            $siteLocales = [];
            $remainingLocales = [];

            $registerPreferredCode = static function (string $rawCode) use (&$preferredSet): void {
                $trimmed = trim($rawCode);

                // Kirby language codes are validated as non-empty strings.
                // @codeCoverageIgnoreStart
                if ($trimmed === '') {
                    return;
                }
                // @codeCoverageIgnoreEnd

                $normalised = LocaleHelper::normaliseLowercase($trimmed);

                // Non-empty strings remain non-empty after lowercasing.
                // @codeCoverageIgnoreStart
                if ($normalised === '') {
                    return;
                }
                // @codeCoverageIgnoreEnd

                $preferredSet[$normalised] = true;

                $hyphenated = str_replace('_', '-', $normalised);

                if ($hyphenated !== '' && $hyphenated !== $normalised) {
                    $preferredSet[$hyphenated] = true;
                }

                $parts = explode('-', $hyphenated, 2);
                $base = $parts[0] ?? '';

                if ($base !== '') {
                    $preferredSet[$base] = true;

                    if ($base === 'nb') {
                        $preferredSet['no'] = true;
                    }
                }
            };

            $getIsoTranslation = static function (string $code) use ($translations, $candidateLocales): string|null {
                foreach ($candidateLocales as $candidate) {
                    if (!isset($translations[$candidate][$code])) {
                        continue;
                    }

                    $label = $translations[$candidate][$code];

                    if (is_string($label)) {
                        $label = trim($label);

                        if ($label !== '') {
                            return $label;
                        }
                    }
                }

                return null;
            };

            $getIntlDisplayName = static function (string $code) use ($candidateLocales): string|null {
                // intl is required in supported environments; guard remains defensive.
                // @codeCoverageIgnoreStart
                if (!class_exists(\Locale::class) || !method_exists(\Locale::class, 'getDisplayLanguage')) {
                    return null;
                }
                // @codeCoverageIgnoreEnd

                foreach ($candidateLocales as $candidate) {
                    try {
                        $value = \Locale::getDisplayLanguage($code, $candidate);
                        // @codeCoverageIgnoreStart
                    } catch (\Throwable $exception) {
                        $value = null;
                    }
                    // @codeCoverageIgnoreEnd

                    if (is_string($value)) {
                        $value = trim($value);

                        if ($value !== '' && $value !== $code) {
                            return $value;
                        }
                    }
                }

                return null;
            };

            $resolveLabel = static function (array $locale, string $code) use ($translations, $getIsoTranslation, $getIntlDisplayName): string {
                $rawName = $locale['name'] ?? null;
                $baseName = is_string($rawName) ? trim($rawName) : '';

                // collectLocales always provides a non-empty fallback name.
                // @codeCoverageIgnoreStart
                if ($baseName === '') {
                    $baseName = $code;
                }
                // @codeCoverageIgnoreEnd

                $englishName = $translations['en'][$code] ?? null;

                $normalisedBase = LocaleHelper::normaliseLowercase($baseName);
                $normalisedCode = LocaleHelper::normaliseLowercase($code);
                $normalisedEnglish = LocaleHelper::normaliseLowercase($englishName);
                // collectLocales always sets nameProvided for each locale record.
                // @codeCoverageIgnoreStart
                $nameProvided = array_key_exists('nameProvided', $locale)
                    ? (bool) $locale['nameProvided']
                    : false;
                // @codeCoverageIgnoreEnd

                $shouldLocalise = $nameProvided === false
                    || $normalisedBase === ''
                    || ($normalisedCode !== '' && $normalisedBase === $normalisedCode)
                    || ($normalisedEnglish !== '' && $normalisedBase === $normalisedEnglish);

                if ($shouldLocalise) {
                    $translated = $getIsoTranslation($code);

                    if (is_string($translated) && $translated !== '') {
                        return $translated;
                    }

                    $intlName = $getIntlDisplayName($code);

                    if (is_string($intlName) && $intlName !== '') {
                        return $intlName;
                    }
                }

                return $baseName;
            };

            $languages = $kirby->languages();

            if ($languages) {
                foreach ($languages as $language) {
                    $code = $language->code();

                    // Kirby Language::code() returns a string; guard is defensive.
                    // @codeCoverageIgnoreStart
                    if (!is_string($code)) {
                        continue;
                    }
                    // @codeCoverageIgnoreEnd

                    $registerPreferredCode($code);
                }
            }

            $locales = LocaleHelper::collectLocales($kirby);

            foreach ($locales as $locale) {
                $code = $locale['code'] ?? null;

                if (!is_string($code)) {
                    continue;
                }

                $trimmed = trim($code);

                if ($trimmed === '') {
                    continue;
                }

                $key = LocaleHelper::normaliseLowercase($trimmed);

                // trim() already excluded empty-string codes.
                // @codeCoverageIgnoreStart
                if ($key === '') {
                    continue;
                }
                // @codeCoverageIgnoreEnd

                $rawSource = $locale['source'] ?? null;
                $normalisedSource = is_string($rawSource)
                    ? strtolower(preg_replace('/[^a-z]/', '', $rawSource) ?? '')
                    : '';
                $isSiteSource = $normalisedSource === 'sitelanguage';

                if (isset($preferredSet[$key]) || $isSiteSource) {
                    $siteLocales[] = $locale;
                } else {
                    $remainingLocales[] = $locale;
                }
            }

            $pushOption = static function (array $locale, string $code, ?string $groupLabel) use (&$options, &$seen, $resolveLabel): void {
                $key = LocaleHelper::normaliseLowercase($code);

                // Duplicates and empty codes are filtered before this callback.
                // @codeCoverageIgnoreStart
                if ($key === '' || isset($seen[$key])) {
                    return;
                }
                // @codeCoverageIgnoreEnd

                $seen[$key] = true;

                $label = $resolveLabel($locale, $code);

                $option = [
                    'value' => $locale['code'],
                    'text'  => $label !== $code ? sprintf('%s (%s)', $label, $code) : $code,
                ];

                if (is_string($groupLabel) && $groupLabel !== '') {
                    $option['group'] = $groupLabel;
                }

                $options[] = $option;
            };

            if ($siteLocales !== []) {
                foreach ($siteLocales as $locale) {
                    $code = $locale['code'] ?? null;

                    // Locale records are normalised before grouping.
                    // @codeCoverageIgnoreStart
                    if (!is_string($code)) {
                        continue;
                    }

                    $trimmed = trim($code);

                    if ($trimmed === '') {
                        continue;
                    }
                    // @codeCoverageIgnoreEnd

                    $pushOption($locale, $trimmed, $siteGroupLabel);
                }
            }

            if ($remainingLocales !== []) {
                foreach ($remainingLocales as $locale) {
                    $code = $locale['code'] ?? null;

                    // Locale records are normalised before grouping.
                    // @codeCoverageIgnoreStart
                    if (!is_string($code)) {
                        continue;
                    }

                    $trimmed = trim($code);

                    if ($trimmed === '') {
                        continue;
                    }
                    // @codeCoverageIgnoreEnd

                    $rawGroup = $locale['group'] ?? null;
                    $groupLabel = is_string($rawGroup) && trim($rawGroup) !== ''
                        ? trim($rawGroup)
                        : $otherGroupLabel;

                    $pushOption($locale, $trimmed, $groupLabel);
                }
            }
        }

        $normalisedCurrent = LocaleHelper::normaliseLowercase($currentValue);

        if ($currentValue !== '' && $normalisedCurrent !== '' && !isset($seen[$normalisedCurrent])) {
            array_unshift($options, [
                'value' => $currentValue,
                'text'  => $currentValue,
            ]);
            $seen[$normalisedCurrent] = true;
        }

        $enabledCount = count($options);

        $field = [
            'label'     => I18n::translate('grommasdietz.kirby-locale.label', 'Locale'),
            'type'      => 'select',
            'icon'      => 'translate',
            'name'      => TitleLocale::FIELD_KEY,
            'options'   => $options,
            'locales'   => $locales,
            'empty'     => [
                'text'  => I18n::translate('grommasdietz.kirby-locale.dialog.empty', 'No locale'),
                'value' => '',
            ],
            'search'    => $enabledCount > 7,
            'reset'     => true,
            'default'   => '',
            'translate' => false,
            'value'     => $currentValue,
        ];

        if ($overrides !== []) {
            $field = array_replace($field, $overrides);
        }

        $field['value'] = $currentValue;

        return $field;
    }

    public static function extend(array $dialog, ?string $value = null, array $fieldOverrides = []): array
    {
        if (!isset($dialog['props']) || !is_array($dialog['props'])) {
            return $dialog;
        }

        $fields = $dialog['props']['fields'] ?? null;

        if (!is_array($fields)) {
            return $dialog;
        }

        $existingField = isset($fields[TitleLocale::FIELD_KEY]) && is_array($fields[TitleLocale::FIELD_KEY])
            ? $fields[TitleLocale::FIELD_KEY]
            : null;

        $field = self::buildField($value, $fieldOverrides);

        if (is_array($existingField)) {
            $field = array_replace($field, $existingField);
        }

        if ($fieldOverrides !== []) {
            $field = array_replace($field, $fieldOverrides);
        }

        $field['name'] = TitleLocale::FIELD_KEY;
        $field['value'] = $value;

        $fields[TitleLocale::FIELD_KEY] = $field;

        $values = $dialog['props']['value'] ?? [];

        if (!is_array($values)) {
            $values = [];
        }

        $values[TitleLocale::FIELD_KEY] = $value;

        $dialog['props']['fields'] = $fields;
        $dialog['props']['value'] = $values;

        return $dialog;
    }
}
