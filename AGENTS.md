# Cursor Cloud specific instructions

Inlay is PHP 8.1+ with SQLite. The cloud environment installs Composer dependencies, copies any missing files from `config.example/` into `config/`, and serves the app with `php -S 0.0.0.0:8000` from the repository root.

Open the forwarded port **8000** to view the site. Routes use query strings such as `/?controller=login`.

Tracked live files **`config/adlexone_settings.json`** and **`storage/database/adlexone.sqlite`** belong in git. Always stage and push them with related work so environments stay in sync. `config.example/` remains the template for brand-new installs; SchemaMigrator upgrades the sqlite file on boot.

`Adlexone\Database\SchemaMigrator` runs on bootstrap and keeps the **shared platform schema** current (no application-private tables). Reference DDL: `storage/database/shared_schema.sql`.

## Site customizations

Installation-specific work belongs under **`site/`** (not the product core):

- `site/themes/{name}/` — skins (`theme.css` tokens, `theme.json`, `brand/`)
- `site/apps/{pack}/` — extra screen packs

Shared page chrome lives in `layouts/`. See `site/README.md`.
