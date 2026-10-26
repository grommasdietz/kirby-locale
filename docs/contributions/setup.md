# Setup

This plugin uses the `playground` site for integration and browser tests.

---

## Composer

Install Composer dependencies for the repo and the playground:

```bash
composer run setup
```

The root dependency graph provides the PHP test and analysis tools. Use the playground graph for the runnable Kirby application.

---

## Node

Use Node 22.14 or newer within Node 22 and pnpm 12.15.0, as declared in
`.node-version` and `package.json`. CI installs Chromium system dependencies.

Install Node dependencies and Playwright Chromium:

```bash
pnpm run setup
```

---

Next: Continue with [Structure](./structure.md)
