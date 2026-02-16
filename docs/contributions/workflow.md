# Workflow

This page covers the core developer workflow for Panel assets and tooling.

---

## Panel assets

Panel source lives in `src/index.js`, `src/dialogs`, `src/marks`, and `src/utils`.

Build production assets:

```bash
pnpm build
```

> [!NOTE]
> `pnpm build` runs kirbyup and writes `index.js` and `index.css` at the repo root. Keep both committed.

Run the dev server while iterating:

```bash
pnpm dev
```

PostCSS is configured in `postcss.config.cjs` (autoprefixer by default).

---

## ISO data generation

The ISO 639‑1 code list and translated names are generated from CLDR source data:

```bash
pnpm generate:iso
```

This runs `scripts/generate-iso-data.mjs` and writes the output to `resources/`.

---

## Dependency updates

Update PHP dependencies (repo and playground):

```bash
composer update
composer update -d playground
```

Update JS dev dependencies:

```bash
pnpm run update:dev
```

---

Next: Continue with [Tests](./tests.md)
