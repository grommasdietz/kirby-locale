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

        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . '/*.php');
        $translations = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $locale = basename($file, '.php');

            if (!is_string($locale) || $locale === '') {
                continue;
            }

            $data = require $file;

            if (is_array($data)) {
                $translations[$locale] = $data;
            }
        }

        return $translations;
    }
}
