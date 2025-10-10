# Kirby Locale

Kirby Locale adds a Writer mark and an optional dialog select field so editors can tag language-specific fragments and store per-page title locales.

The `locale` Writer mark wraps inline selections in a `span` element with `lang` attribute, e.g.:

```html
<span lang="en">Example</span>
```

The optional select field in Kirby’s page create and title rename dialogs can be activated individually based on `intendedTemplate` and saves `title_locale` to your content. This gives you full flexibility to set `lang` attribute yourself.

## Requirements

- Kirby 5+

## Installation

You can install the plugin via one of three methods:

1. ### Download

   Download and copy this repository to `site/plugins/kirby-locale`.

2. ### Git Submodule

   ```shell
   git submodule add https://github.com/grommasdietz/kirby-locale.git site/plugins/kirby-locale
   ```

3. ### Composer

   ```shell
   composer require grommasdietz/kirby-locale
   ```

## Core workflow

### Writer mark

Enable the locale mark on any Writer field by adding it to the field’s `marks` list:

```yaml
fields:
  text:
    type: writer
    marks:
      - locale
```

Once enabled, the Writer displays the locale mark in the toolbar and highlights tagged segments so language switches stay obvious. The selection gets wrapped in `<span lang="…">`.

### Title

Activate the title locale selector via `grommasdietz.kirby-locale.intendedTemplate` in `site/config/config.php`. For a single template:

```php
return [
    'grommasdietz.kirby-locale.intendedTemplate' => 'project',
];
```

For multiple templates:

```php
return [
    'grommasdietz.kirby-locale.intendedTemplate' => [
        'project',
        'note',
    ],
];
```

After configuration, Kirby’s create and rename dialogs automatically show the **Locale** dropdown and save the choice as `title_locale`. Retrieve it in templates with `$page->title_locale()`.

If your deployment runs cleanup commands such as `kirby clean:content`, declare `title_locale` as a hidden field in the affected blueprints:

```yaml
fields:
  title_locale:
    type: hidden
    translate: false
```

### Custom integrations

Need the locale list elsewhere? Reuse the shared dataset via the plugin’s API endpoints:

- `GET grommasdietz/kirby-locale/locales` &mdash; returns the raw locale definitions (code, name, group, source)
- `GET grommasdietz/kirby-locale/options` &mdash; returns the grouped select options shown in the dialogs

Fetch the data inside custom Panel plugins or fields with Kirby’s [`window.panel.api`](https://getkirby.com/docs/reference/panel/api#api-client) and wire it into your UI of choice. The API payloads stay in sync with the Writer mark and dialog pickers, so every consumer uses the same locale catalogue.

## Locale sources

The Writer dialog and the title selector share a single locale collector. Entries are deduplicated and gathered in this order:

1. `kirby()->option('grommasdietz.kirby-locale.locales')`
2. Kirby’s configured site languages
3. The bundled ISO 639-1 catalog (unless disabled)

> [!TIP]
> Catalog entries adopt the current Panel language when the browser supports `Intl.DisplayNames`, so editors always see familiar labels.

Values must be associative arrays that include at least a `code` and `name`, with an optional `group`. The kirby option `'grommasdietz.kirby-locale.locales'` may also be a closure that returns such an array at runtime.

Set the kirby option `'grommasdietz.kirby-locale.catalog'` to disable the ISO fallback entirely, or pass a custom array to replace it.

## Optional configuration

Define custom labels or additional locales in `site/config/config.php`:

```php
return [
    'grommasdietz.kirby-locale.locales' => [
        ['code' => 'en-GB', 'name' => 'English, United Kingdom'],
        ['code' => 'en-US', 'name' => 'English, United States'],
    ],
    // Optional: disable the ISO fallback if you only want the entries above
    // 'grommasdietz.kirby-locale.catalog' => false,
];

Passing an empty array (or a closure that returns one) keeps the plugin’s API active while skipping the bundled ISO catalog entirely.
```

Each entry must provide a `code` and `name` (and optionally `group`). You can also supply a closure for the Kirby option if the list should be computed dynamically. The same dataset powers both the Writer mark and the title dialogs, so you only need to maintain it once.

## Development

```shell
npm install
npm run generate:iso
npm run dev
npm run build
```

The project uses [kirbyup](https://github.com/johannschopplich/kirbyup) to bundle the Panel assets from `src/index.js` into `index.js`.

### ISO catalog maintenance

- Edit `resources/source/iso-639-1.json` to tweak the canonical ISO 639-1 dataset or add missing codes.
- Add a translation file to `translations/` (e.g. `it.php`) to expose a new Panel language.
- Run `npm run generate:iso` to rebuild:
  - `resources/iso-639-1.php` (PHP fallback catalog)
  - `src/utils/isoTranslations.json` (Panel bundle input)
- Re-run `npm run build` so the Panel bundle picks up the regenerated JSON.

The generator relies on CLDR data via FormatJS, so every supported Panel language automatically receives translations for each ISO code. Where CLDR does not supply a label the script falls back to the English entry from the canonical dataset.

## License

MIT. See [LICENSE.md](LICENSE.md).
