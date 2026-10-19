# Cursor Cloud specific instructions

Commit and push directly to `main`. Do not create feature branches and do not open pull requests. Before committing, check out `main` and run `git pull --ff-only origin main`. See `.cursor/rules/commit-to-main.mdc`.

Inlay is PHP 8.1+ with SQLite. The cloud environment installs Composer dependencies, copies any missing files from `config.example/` into `config/`, and serves the app with `php -S 0.0.0.0:8000 -t public public/index.php` so the document root is `public/`.

Open the forwarded port **8000** to view the site. Routes use query strings such as `/?controller=home`.

Do not commit `config/*.json` or `storage/database/*.sqlite`. Those are created on the server. `config.example/` is the template. The first visit with an empty database shows setup. `SchemaMigrator` upgrades an existing sqlite file on boot. A SaaS host sets `INLAY_STORES` and `INLAY_OPERATOR_HOST` (environment or `config/platform.json`) and opens the operator host to add customer folders.

`Adlexone\Database\SchemaMigrator` runs on bootstrap and keeps the **shared platform schema** current (no application-private tables). Reference DDL: `storage/database/shared_schema.sql`.

## Site customizations

Installation-specific work belongs under **`site/`** (not the product core):

- `site/themes/{name}/` — skins (`theme.css` tokens, `theme.json`, `brand/`)
- `site/apps/{pack}/` — extra screen packs

Shared page chrome lives in `layouts/`. Screens live in `src/Screen/`. See `site/README.md`.
