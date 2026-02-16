# Architecture

Kirby Locale is built around two features: inline locale marks and page title locale. This page explains how each works under the hood.

## Writer mark

The `locale` mark registers a Panel component that renders a `<span lang="…">` tag. The mark uses commands to open a dialog where editors pick the locale from an ISO 639‑1 code list.

The ISO data lives in `resources/iso-639-1.php` (codes) and `resources/iso-639-1-translations.php` (translated names). Both files are generated from CLDR source data via `scripts/generate-iso-data.mjs`.

## Title locale

Title locale hooks into the `page.create` and `page.changeTitle` Panel dialogs. The plugin extends the standard dialog by injecting an additional locale dropdown powered by `DialogFactory`.

### Storage

The locale value is stored as a content field (`title_locale`) in the page's content file. On multi‑language sites, each language version has its own title locale value.

### Hooks

The plugin registers three hooks to keep the stored locale in sync:

- `page.create:after` — Persists the locale chosen during page creation
- `page.changeTitle:after` — Updates the locale after a title change
- `page.changeSlug:after` — Keeps the locale after slug changes

## Sanitization

The plugin extends Kirby's HTML sanitizer to allow `lang` and `class` attributes on `<span>` elements. This ensures that locale‑marked content passes sanitization both on input and output.

---

Next: Continue with [Contributions](../contributions/index.md)
