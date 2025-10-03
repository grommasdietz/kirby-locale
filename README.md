# GD Locale

**GD Locale** is a Kirby Panel plugin that adds a single Writer mark to control the `lang` attribute of inline text. Authors can assign locales from a dropdown backed by the project configuration or fall back to manual entry when no presets exist.

In addition, the plugin augments Kirby’s page creation and rename dialogs with a locale selector so page titles can be tagged with the intended language right when they are entered.

## Installation

Copy the plugin folder to `site/plugins/gd-locale` or install it via Composer:

```shell
composer require grommasdietz/gd-locale
```

## Usage

Open any Writer field in the Panel. The toolbar gains a **Locale** button:

1. Select the text that should receive a language hint.
2. Click **Locale** to open Kirby’s native dialog.
3. Pick a locale from the dropdown (site languages appear first, followed by a separator and any additional locales). If no choices are configured you can still type a custom code.
4. Confirm to wrap the selection in `<span class="locale" lang="xx">…</span>`.

Selecting the empty option removes the mark again.

### Locale sources

The writer dialog and the page-title dropdown now share the same locale collector. It gathers entries in the following order and removes duplicates automatically:

1. `kirby()->option('grommasdietz.gd-locale.locales')`
2. `panel.config['grommasdietz/gd-locale'].locales` (or `...languages`)
3. `window.panel.config.locales` / `...languages`
4. Kirby’s configured site languages
5. The bundled ISO&nbsp;639‑1 catalog (unless disabled)

Values can be plain strings (`'en-GB'`) or associative arrays with `code`, `name`, and optional `group` keys. Closures are supported on both the Panel and Kirby options.

Set `kirby()->option('grommasdietz.gd-locale.catalog', false)` if you want to suppress the built-in ISO fallback entirely. You can also pass a custom array (or callback) to `grommasdietz.gd-locale.catalog` when you prefer to define your own fallback set.

### Optional configuration

Add the option below to `site/config/config.php` to provide custom labels:

```php
return [
    'grommasdietz.gd-locale.locales' => [
        ['code' => 'en', 'name' => 'English'],
        ['code' => 'de', 'name' => 'Deutsch'],
    ],
    // Optional: disable the ISO fallback if you only want the entries above
    // 'grommasdietz.gd-locale.catalog' => false,
];
```

Values may be simple strings (`'fr-CA'`) or associative arrays containing `code` and `name` keys. Closures are supported and will be executed at runtime.

The same data set is used for both the Writer mark and the page dialog fields, so you only need to configure the list once.

## Page title locales

The plugin adds a **Locale** select field to the Panel dialogs for creating a page and changing an existing page title. The dropdown mirrors the locale sources described above and always stays a select, so authors never have to type codes manually. It remembers the last stored value and, when none is saved yet, preselects the dialog language (the page language for edits or the Panel language for new pages).

### When the field appears

The selector is disabled by default. Opt in specific intended templates by setting the `grommasdietz.gd-locale.intendedTemplate` option in `site/config/config.php`:

```php
return [
    'grommasdietz.gd-locale.intendedTemplate' => [
        'project',
        'case-study',
    ],
];
```

The option accepts:

- A single template name as string
- An array of template names
- A callback returning one of the above (e.g. to derive the list dynamically)

Pass a single string for one template, an array for multiple templates, or a callback that returns either form. Set the option to `true`, `'*'`, or `'all'` if you want to enable the selector for every template, or leave it unset/empty to keep the feature off.

- The selection is stored per language in the page content as the `titleLocale` field.
- Clearing the field removes the stored value.
- The stored value can be accessed in templates and snippets via `$page->titleLocale()->value()` (and `$page->content('de')->titleLocale()->value()` in multi-language sites).

Rendering is intentionally left to the project so you can decide where a `<span lang="…">` wrapper makes sense. A simple helper could look like this:

```php
$title = $page->title()->value();
$locale = $page->titleLocale()->value();

if ($locale) {
    echo '<span lang="' . esc($locale) . '">' . esc($title) . '</span>';
} else {
    echo esc($title);
}
```

This makes it easy to persist the intended language of page titles for downstream features such as automated menus, sitemap generators, or custom SEO integrations.

> ℹ️ If your deployment runs cleanup commands such as `kirby content:clean`, declare the field in the affected blueprints to keep the stored value. A minimal example:

```yaml
fields:
    titleLocale:
        type: hidden
        translate: true
```

The plugin will still write the value even without the field, but defining it prevents automated tools from stripping the data.

## Development

```shell
npm install
npm run dev
npm run build
```

The project uses [kirbyup](https://github.com/getkirby/kirbyup) to bundle the Panel assets from `src/index.js` into `index.js`.

## License

MIT. See [LICENSE.md](LICENSE.md).
