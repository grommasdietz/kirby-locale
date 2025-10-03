# Kirby Locale

Kirby plugin to enable multilingual fragment definitions. If you have pages with titles, phrases or names which should stay in there original languages this plugin is for you.

The plugin ships two tools:

- A `locale` writer mark that wraps inline selections in a `<span lang="…">`
- A locale picker in Kirby’s page create and title rename dialogs to save `titlelocale`to your content so you can read and write it individually on templates and snippets

## Requirements

- Kirby 4+

## Installation

You can install the plugin via one of three methods:

1. **Download**

   Download and copy this repository to `site/plugins/kirby-locale`.

2. **Git Submodule**

   ```shell
   git submodule add https://github.com/grommasdietz/kirby-locale.git site/plugins/kirby-locale
   ```

3. **Composer**

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
      - bold
      - italic
      - locale
```

Once enabled, the Writer displays the locale mark in the toolbar and highlights tagged segments so language switches stay obvious. Published markup wraps the selection in `<span lang="…">` automatically.

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
        'case-study',
    ],
];
```

After configuration, Kirby’s create and rename dialogs automatically show the **Locale** dropdown and persist the choice as `titlelocale`. Retrieve it in templates with `$page->titlelocale()->value()` (or `$page->content('de')->titlelocale()->value()` in multi-language sites).

#### Keep the stored value

If your deployment runs cleanup commands such as `kirby content:clean`, declare `titlelocale` as a hidden field in the affected blueprints:

```yaml
fields:
  titlelocale:
    type: hidden
    translate: true # or false
```

## Locale sources

The Writer dialog and the title selector share a single locale collector. Entries are deduplicated and gathered in this order:

1. `kirby()->option('grommasdietz.kirby-locale.locales')`
2. `panel.config['grommasdietz/kirby-locale'].locales` (or `...languages`)
3. `window.panel.config.locales` / `...languages`
4. Kirby’s configured site languages
5. The bundled ISO\u00a0639-1 catalog (unless disabled)

Values can be plain strings (`'en-GB'`) or associative arrays with `code`, `name`, and optional `group` keys. Closures are supported on both the Panel and Kirby options.

Set `kirby()->option('grommasdietz.kirby-locale.catalog', false)` to disable the ISO fallback entirely, or provide your own array/callback to replace it.

## Optional configuration

Define custom labels or additional locales in `site/config/config.php`:

```php
return [
    'grommasdietz.kirby-locale.locales' => [
        ['code' => 'en', 'name' => 'English'],
        ['code' => 'de', 'name' => 'Deutsch'],
    ],
    // Optional: disable the ISO fallback if you only want the entries above
    // 'grommasdietz.kirby-locale.catalog' => false,
];
```

Values may be simple strings (`'fr-CA'`) or associative arrays containing `code` and `name` keys. Closures are supported and evaluated at runtime. The same dataset powers both the Writer mark and the title dialogs, so you only need to maintain it once.

## Development

```shell
npm install
npm run dev
npm run build
```

The project uses [kirbyup](https://github.com/getkirby/kirbyup) to bundle the Panel assets from `src/index.js` into `index.js`.

## License

MIT. See [LICENSE.md](LICENSE.md).
