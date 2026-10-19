# Cursor Cloud specific instructions

Commit and push directly to `main`. Do not create feature branches and do not open pull requests. Before committing, check out `main` and run `git pull --ff-only origin main`. See `.cursor/rules/commit-to-main.mdc`.

Inlay is PHP 8.1+ with SQLite. The cloud environment installs Composer dependencies, copies any missing files from `config.example/` into `config/`, and serves the app from the repository root on port 8000.

Open the forwarded port **8000** to view the site. Routes use query strings such as `/?controller=login`. nginx listens on that port, compresses CSS and JavaScript, and keeps the connection open. Four PHP workers behind it render pages, so the forwarded port does not open a new round trip for every file.

Tracked live files **`config/adlexone_settings.json`** and **`storage/database/adlexone.sqlite`** belong in git. Always stage and push them with related work so environments stay in sync. `config.example/` remains the template for brand-new installs; SchemaMigrator upgrades the sqlite file on boot.

`Adlexone\Database\SchemaMigrator` runs on bootstrap and keeps the **shared platform schema** current (no application-private tables). Reference DDL: `storage/database/shared_schema.sql`.

## Site customizations

Installation-specific work belongs under **`site/`** (not the product core):

- `site/themes/{name}/` — skins (`theme.css` tokens, `theme.json`, `brand/`)
- `site/apps/{pack}/` — extra screen packs

Shared page chrome lives in `layouts/`. Screens live in `src/Screen/`. See `site/README.md`.
