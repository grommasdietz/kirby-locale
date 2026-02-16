<?php

namespace GrommasDietz\KirbyLocale;

final class IsoCatalog
{
    public static function catalog(): array
    {
        static $catalog = null;

        if ($catalog !== null) {
            return $catalog;
        }

        $path = __DIR__ . '/../resources/iso-639-1.php';

        // @codeCoverageIgnoreStart
        if (!is_file($path)) {
            $catalog = [];
            return $catalog;
        }
        // @codeCoverageIgnoreEnd

        $data = require $path;
        $catalog = is_array($data) ? $data : [];

        return $catalog;
    }

    public static function translations(): array
    {
        static $translations = null;

        if ($translations !== null) {
            return $translations;
        }

        $path = __DIR__ . '/../resources/iso-639-1-translations.php';
        $translations = [];

        if (is_file($path)) {
            $data = require $path;

            if (is_array($data)) {
                $translations = $data;
            }
        }

        // @codeCoverageIgnoreStart
        if (!isset($translations['en']) || !is_array($translations['en'])) {
            $translations['en'] = [];
        }
        // @codeCoverageIgnoreEnd

        foreach (self::catalog() as $entry) {
            $code = $entry['code'] ?? null;
            $name = $entry['name'] ?? null;

            // @codeCoverageIgnoreStart
            if (!is_string($code) || $code === '') {
                continue;
            }
            // @codeCoverageIgnoreEnd

            // @codeCoverageIgnoreStart
            if (!isset($translations['en'][$code]) || !is_string($translations['en'][$code])) {
                $translations['en'][$code] = is_string($name) && $name !== '' ? $name : $code;
            }
            // @codeCoverageIgnoreEnd
        }

        return $translations;
    }
}
