# Changelog

All notable changes to this project will be documented in this file.

## [2.0.0] — 2026-03-12

### BREAKING CHANGES

- The plugin's registry ID has changed from `grommasdietz/kirby-locale` to `grommasdietz/locale` to align with the getkirby.com plugin directory convention (no `kirby-` prefix in the registry name). The Composer package name (`grommasdietz/kirby-locale`) is unchanged.
- All Kirby option keys have changed accordingly: `grommasdietz.kirby-locale.*` → `grommasdietz.locale.*`. Update your site config wherever you set options such as `grommasdietz.locale.locales`, `grommasdietz.locale.catalog`, or `grommasdietz.locale.intendedTemplate`.
- All Panel API routes have changed: `grommasdietz/kirby-locale/*` → `grommasdietz/locale/*`.
