<?php

namespace Grommasdietz\KirbyLocale;

use Kirby\Cms\App;
use Kirby\Cms\Find;
use Kirby\Http\Request;

final class LocaleHelper
{
  public static function normaliseLocaleDefinition($locale, ?string $defaultGroup = null, string $source = 'plugin'): ?array
  {
    if (is_string($locale)) {
      $trimmed = trim($locale);

      if ($trimmed === '') {
        return null;
      }

      return [
        'code'         => $trimmed,
        'name'         => $trimmed,
        'group'        => $defaultGroup,
        'source'       => $source,
        'nameProvided' => false,
      ];
    }

    if (!is_array($locale) || empty($locale['code'])) {
      return null;
    }

    $nameProvided = array_key_exists('name', $locale);
    $name = $locale['name'] ?? $locale['label'] ?? $locale['text'] ?? $locale['code'];
    $group = $locale['group'] ?? $locale['continent'] ?? $locale['region'] ?? $defaultGroup;

    if (is_string($group)) {
      $group = trim($group) ?: null;
    } else {
      $group = null;
    }

    return [
      'code'         => $locale['code'],
      'name'         => is_string($name) && $name !== '' ? $name : $locale['code'],
      'group'        => $group,
      'source'       => $locale['source'] ?? $source,
      'nameProvided' => array_key_exists('nameProvided', $locale)
        ? (bool) $locale['nameProvided']
        : ($source !== 'catalog' && $nameProvided),
    ];
  }

  public static function collectLocales(App $kirby): array
  {
    $collected = [];
    $seen = [];

    $push = function ($locale, string $source = 'plugin', ?string $defaultGroup = null) use (&$collected, &$seen) {
      $normalised = LocaleHelper::normaliseLocaleDefinition($locale, $defaultGroup, $source);

      if ($normalised === null) {
        return;
      }

      $code = strtolower($normalised['code']);

      if ($code === '' || isset($seen[$code])) {
        return;
      }

      $seen[$code] = true;

      $collected[] = [
        'code'         => $normalised['code'],
        'name'         => $normalised['name'],
        'group'        => $normalised['group'] ?? null,
        'source'       => $normalised['source'] ?? $source,
        'nameProvided' => $normalised['nameProvided'] ?? false,
      ];
    };

    $pluginLocales = $kirby->option('grommasdietz.kirby-locale.locales', []);

    if (is_callable($pluginLocales)) {
      $pluginLocales = $pluginLocales($kirby);
    }

    foreach ((array) $pluginLocales as $locale) {
      $push($locale, 'plugin');
    }

    $languages = $kirby->languages();

    if ($languages) {
      foreach ($languages as $language) {
        $code = $language->code();

        if (!is_string($code)) {
          continue;
        }

        $push([
          'code' => $code,
          'name' => $language->name(),
        ], 'site-language');
      }
    }

    $catalogPreference = $kirby->option('grommasdietz.kirby-locale.catalog');

    if ($catalogPreference !== false) {
      if (is_callable($catalogPreference)) {
        $catalogPreference = $catalogPreference($kirby);
      }

      $fallback = ($catalogPreference === null || $catalogPreference === true)
        ? IsoCatalog::catalog()
        : $catalogPreference;

      foreach ((array) $fallback as $locale) {
        $push($locale, 'catalog');
      }
    }

    return $collected;
  }

  public static function normaliseLocaleTag(?string $value): string
  {
    if (!is_string($value)) {
      return '';
    }

    $trimmed = trim($value);

    if ($trimmed === '') {
      return '';
    }

    $collapsed = preg_replace('/\s+/', '', $trimmed);

    return str_replace('_', '-', $collapsed ?? $trimmed);
  }

  public static function canonicaliseLocaleTag(?string $value): string
  {
    $tag = self::normaliseLocaleTag($value);

    if ($tag === '') {
      return '';
    }

    if (class_exists('\\Locale') && method_exists('\\Locale', 'canonicalize')) {
      try {
        $canonical = \Locale::canonicalize($tag);

        if (is_string($canonical) && $canonical !== '') {
          return str_replace('_', '-', $canonical);
        }
      } catch (\Throwable $exception) {
        // ignore and fall back to the normalised tag
      }
    }

    return $tag;
  }

  public static function localeCandidateKeys(?string $value): array
  {
    $canonical = self::canonicaliseLocaleTag($value);

    if ($canonical === '') {
      return ['en'];
    }

    $lowered = strtolower($canonical);
    $candidates = [$lowered];

    $parts = explode('-', $lowered, 2);
    $base = $parts[0] ?? '';

    if ($base !== '' && $base !== $lowered) {
      $candidates[] = $base;
    }

    if ($lowered === 'nb' && $base !== 'no') {
      $candidates[] = 'no';
    }

    if (!in_array('en', $candidates, true)) {
      $candidates[] = 'en';
    }

    return array_values(array_unique($candidates));
  }

  public static function normaliseLowercase(?string $value): string
  {
    if (!is_string($value)) {
      return '';
    }

    $trimmed = trim($value);

    if ($trimmed === '') {
      return '';
    }

    return function_exists('mb_strtolower')
      ? mb_strtolower($trimmed, 'UTF-8')
      : strtolower($trimmed);
  }

  public static function normaliseTitleLocale($value): ?string
  {
    if ($value === null) {
      return null;
    }

    if (is_string($value)) {
      $trimmed = trim($value);

      return $trimmed === '' ? null : $trimmed;
    }

    return null;
  }

  public static function resolveLanguageCode(App $kirby, mixed $explicit = null): ?string
  {
    if (!$kirby || $kirby->multilang() === false) {
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
  }

  /**
   * @return array{0: bool, 1: mixed}
   */
  public static function extractLocaleFromRequest(Request $request): array
  {
    $fieldKey = TitleLocale::FIELD_KEY;
    $value = $request->get($fieldKey);
    $provided = $value !== null;

    if ($provided === false) {
      $data = $request->data();

      if (is_array($data)) {
        if (array_key_exists($fieldKey, $data)) {
          $value = $data[$fieldKey];
          $provided = true;
        } elseif (
          isset($data['content']) &&
          is_array($data['content']) &&
          array_key_exists($fieldKey, $data['content'])
        ) {
          $value = $data['content'][$fieldKey];
          $provided = true;
        }
      }
    }

    return [$provided, $value];
  }

  public static function normaliseTemplateList($templates)
  {
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
  }

  public static function getEnabledTitleLocaleTemplates(App $kirby)
  {
    $option = $kirby->option('grommasdietz.kirby-locale.intendedTemplate');

    return self::normaliseTemplateList($option);
  }

  public static function templateAllowsLocale(?string $template, ?array $allowed): bool
  {
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
  }

  public static function findPage(string $id)
  {
    return Find::page($id);
  }
}
