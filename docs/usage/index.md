# Usage

Kirby Locale adds locale marks to writer fields and lets editors set a per‑page title locale via Panel dialogs.

## Writer mark

The `locale` mark wraps selected text in a `<span lang="…">` tag. Editors pick the language from a searchable dropdown populated with ISO 639‑1 codes.

### Enabling the mark

Add `locale` to the `marks` list of any writer or textarea field:

```yml
# site/blueprints/pages/default.yml
fields:
  text:
    type: writer
    marks:
      - bold
      - italic
      - locale
```

The mark then appears in the writer toolbar. Once applied, the selection gets wrapped in `<span lang="en">Example</span>`.

## Title locale

The optional select field in Kirby's page create and title rename dialogs can be activated individually based on `intendedTemplate` and saves `title_locale` to your content. In this way the `lang` attribute can be set flexible across the markup.

### Enabling title locale

Enable it per template in `site/config/config.php`:

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

When enabled, the page create and change‑title dialogs gain a locale dropdown. The chosen value is stored in the page's content file as `title_locale`. Retrieve it in templates with `$page->title_locale()`.

If your deployment runs cleanup commands such as `kirby clean:content`, declare `title_locale` as a hidden field in the affected blueprints:

```yml
fields:
  title_locale:
    type: hidden
    translate: false # optional
```

## Custom integrations

Need the locale list elsewhere? Reuse the shared dataset via the plugin's API endpoints:

- `GET grommasdietz/kirby-locale/locales` — returns the raw locale definitions (code, name, group, source)
- `GET grommasdietz/kirby-locale/options` — returns the grouped select options shown in the dialogs

Fetch the data inside custom Panel plugins or fields with Kirby's [`window.panel.api`](https://getkirby.com/docs/reference/panel/api#api-client) and wire it into your UI of choice. The API payloads stay in sync with the Writer mark and dialog pickers, so every consumer uses the same locale catalogue.

## Locale sources

The Writer dialog and the title selector share a single locale collector. Entries are deduplicated and gathered in this order:

1. `kirby()->option('grommasdietz.kirby-locale.locales')`
2. Kirby's configured site languages
3. The bundled ISO 639-1 catalog (unless disabled)

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
```

Each entry must provide a `code` and `name` (and optionally `group`). You can also supply a closure for the Kirby option if the list should be computed dynamically. The same dataset powers both the Writer mark and the title dialogs, so you only need to maintain it once.

## API routes

| Route                                    | Method | Description                           |
| ---------------------------------------- | ------ | ------------------------------------- |
| `grommasdietz/kirby-locale/locales`      | GET    | Locale list for the current site      |
| `grommasdietz/kirby-locale/options`      | GET    | Dropdown options for the locale field |
| `grommasdietz/kirby-locale/title-locale` | GET    | Stored title locale for a page        |

---

Next: Continue with [Architecture](./architecture.md)
