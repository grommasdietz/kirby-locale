# Setup

This plugin uses the `playground` site for integration and browser tests.

---

## Composer

Install Composer dependencies for the repo and the playground:

```bash
composer run setup
```

> [!NOTE]
> PHPUnit loads `vendor/autoload.php` by default. If you only install dependencies in `playground`, the bootstrap loads `playground/vendor/autoload.php` as well.
> If you install `vlucas/phpdotenv`, the bootstrap loads `playground/.env` for tests.

---

## Node

Use Node 22.14 or newer within Node 22 and pnpm 11.22.0, as declared in
`.node-version` and `package.json`. CI installs Chromium system dependencies.

Install Node dependencies and Playwright Chromium:

```bash
pnpm run setup
```

---

Next: Continue with [Structure](./structure.md)
