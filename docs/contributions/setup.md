# Setup

This plugin uses the `playground` site for integration and browser tests.

---

## Composer

Install Composer dependencies for the repo and the playground:

```bash
composer run setup
```

Setup provisions `KIRBY_CONTENT_SALT` and `KIRBY_COOKIE_KEY` in the ignored repository-root `.env`, outside the playground document root. The playground also provisions missing or empty values on first boot. Values are independent, survive playground resets and are never rotated automatically. Existing comments and unrelated settings are preserved. The two keys accept literal quoted or unquoted values of at least 32 characters; process environment values take precedence. Invalid or duplicate assignments raise an error. The repository root must be writable for initial generation.

The root dependency graph provides the PHP test and analysis tools. Use the playground graph for the runnable Kirby application.

---

## Node

Use Node 22.22.2 or newer within Node 22 and pnpm 11.28.10, as declared in
`.node-version` and `package.json`. CI installs Chromium system dependencies.

Install Node dependencies and Playwright Chromium:

```bash
pnpm run setup
```

---

Next: Continue with [Structure](./structure.md)
