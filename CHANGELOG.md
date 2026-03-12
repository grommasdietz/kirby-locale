# [2.0.0](https://github.com/grommasdietz/kirby-locale/compare/v1.0.7...v2.0.0) (2026-03-12)


* feat!: rename plugin registry ID to grommasdietz/locale ([cc84e12](https://github.com/grommasdietz/kirby-locale/commit/cc84e12517e78da6daa7ee0ec0d0cd565b124fca))


### Bug Fixes

* **playground:** update option key to grommasdietz.locale.intendedTemplate ([6dae93c](https://github.com/grommasdietz/kirby-locale/commit/6dae93c31880647d75fa71e05376949447c1806f))


### BREAKING CHANGES

* The plugin's Kirby ID changes from
`grommasdietz/kirby-locale` to `grommasdietz/locale`. All option keys
change from `grommasdietz.kirby-locale.*` to `grommasdietz.locale.*`
(e.g. `grommasdietz.locale.locales`, `grommasdietz.locale.catalog`,
`grommasdietz.locale.intendedTemplate`). All Panel API routes change
from `grommasdietz/kirby-locale/*` to `grommasdietz/locale/*`. Update
your site config and any custom API consumers accordingly.

# Changelog

All notable changes to this project will be documented in this file.

## [2.0.0] — 2026-03-12

### BREAKING CHANGES

- The plugin's registry ID has changed from `grommasdietz/kirby-locale` to `grommasdietz/locale` to align with the getkirby.com plugin directory convention (no `kirby-` prefix in the registry name). The Composer package name (`grommasdietz/kirby-locale`) is unchanged.
- All Kirby option keys have changed accordingly: `grommasdietz.kirby-locale.*` → `grommasdietz.locale.*`. Update your site config wherever you set options such as `grommasdietz.locale.locales`, `grommasdietz.locale.catalog`, or `grommasdietz.locale.intendedTemplate`.
- All Panel API routes have changed: `grommasdietz/kirby-locale/*` → `grommasdietz/locale/*`.
