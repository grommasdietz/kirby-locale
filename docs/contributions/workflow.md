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
composer run update:dev
composer run playground:update
composer run setup
```

Update JS dev dependencies:

```bash
pnpm run update:dev
```

---

## Dependency updates and CI

Use `composer run update:dev` for root PHP development dependencies,
`composer run playground:update` for the runtime fixture, and `pnpm run update:dev`
for Node tooling. Review and commit the resulting lockfiles.

Dependabot groups version updates into one PR per ecosystem and lockfile, including
related TypeScript, ESLint, PostCSS and build tools. Each update entry allows one
open version PR. Root and playground Composer locks remain separate. Weekly runs
are staggered across plugins; GitHub Actions updates run monthly.

Plugins using `typescript-eslint` keep TypeScript 6 while its parser depends on
the compiler API removed in TypeScript 7. Dependabot defers TypeScript 7 version
updates in those repositories. Remove that exception after the parser supports
the new API and the full verification suite passes.

Version updates wait three days after publication. pnpm also enforces a strict
24-hour minimum release age for installation and local updates. Dependabot security
updates use separate groups and bypass its version-update cooldown; pnpm's install
age still applies. Patch updates may auto-merge after required checks pass; minor
and major updates need review. Refresh the remaining grouped PR after each merge
to keep its lockfile based on current `main`. Grouping reduces overlapping lockfile
changes but cannot prevent conflicts with manual dependency edits.

CI runs PHP quality, Node, generated-asset, documentation and browser checks with
one shared PHP 8.3 setup where practical. Separate PHP and Kirby compatibility
jobs preserve each plugin's supported-runtime coverage. Stale runs are cancelled,
jobs have time limits, and failed browser artifacts are retained for three days.

Next: Continue with [Tests](./tests.md)
