<?php

namespace GrommasDietz\KirbyLocale;

final class Translations
{
    /**
     * Load all plugin translation files located in translations/.
     */
    public static function load(): array
    {
        $directory = __DIR__ . '/../translations';

        // @codeCoverageIgnoreStart
        if (!is_dir($directory)) {
            return [];
        }
        // @codeCoverageIgnoreEnd

        $files = glob($directory . '/*.php') ?: [];
        $translations = [];

        foreach ($files as $file) {
            // @codeCoverageIgnoreStart
            if (!is_file($file)) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $locale = basename($file, '.php');

            // @codeCoverageIgnoreStart
            if (!is_string($locale) || $locale === '') {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $data = require $file;

            if (is_array($data)) {
                $translations[$locale] = $data;
            }
        }

        return $translations;
    }
}
