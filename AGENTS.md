# Cursor Cloud specific instructions

Inlay is PHP 8.1+ with SQLite. The cloud environment installs Composer dependencies, copies any missing files from `config.example/` into `config/`, and serves the app with `php -S 0.0.0.0:8000` from the repository root.

Open the forwarded port **8000** to view the site. Routes use query strings such as `/?controller=login`.

`config/*.json` and `storage/database/*.sqlite` are not in git. A new environment gets the example settings and an empty database file created by PHP on first connection. The live database and real settings stay on the machine that already has them; copy those in only when this environment should use that data.

## Site customizations

Installation-specific work belongs under **`site/`** (not the product core):

- `site/themes/{name}/` — skins (`theme.css` tokens, `theme.json`, `brand/`)
- `site/apps/{pack}/` — extra screen packs

Shared page chrome lives in `layouts/`. See `site/README.md`.
