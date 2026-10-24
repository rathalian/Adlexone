# Adlexone Inlay

PHP 8.1+ / SQLite action and information management system. The database is a **shared platform schema** (users, items, fields, applications registry). Application packs consume shared data and may store an application slug on shared rows — they must not own private tables.

## Requirements

- PHP 8.1+ with `pdo_sqlite`, `dom`, `libxml`, `xml`
- Apache with `mod_rewrite` (or any server that routes requests to `index.php`)

## Setup

Point the domain’s document root at the `public/` folder. The SQLite file, `config/`, and `storage/attachments/` stay beside `public/`, not inside it. The account that runs PHP must own those folders (directories mode `700`, the database file mode `600`).

1. **Open the site.** If there is no database yet, the setup page names the exact paths and the `chown` / `chmod` commands. It asks for the organisation name and the first administrator, then creates `storage/database/adlexone.sqlite` and copies `config.example/*.json` into `config/`.

2. **Settings.** `bootstrap/bootstrap.php` loads every `*.json` in `config/` (and one level of subfolders) as constants, except `platform.json`, and the first definition wins. Do not put the templates themselves in `config/`.

   Public URL (redirects / OAuth): leave `SET_PUBLIC_BASE_URL` empty for automatic detection from the request Host. For production, set it to the site origin (e.g. `https://inlay.example.com`). Optionally set `SET_ALLOWED_HOSTS` (comma-separated) and `SET_TRUSTED_PROXIES` (IPs/CIDRs) when behind a reverse proxy — forwarded headers are ignored unless the client IP is trusted.

3. **Updates.** Replace the code and leave `config/` and `storage/` in place. On boot, `Adlexone\Database\SchemaMigrator` upgrades the live file. Reference DDL: `storage/database/shared_schema.sql`.

## Release zip for shared Apache hosting

`php bin/build-release.php` builds `dist/inlay-{version}-{commit}.zip` from the last commit. It needs PHP 8.1+ with `phar` and `zlib`, and git. The zip holds the code only. It never holds `config/*.json`, a SQLite file, or attachments.

1. Unzip it into the domain's document root. The file manager on most hosts can do this. Apache needs `mod_rewrite` and `AllowOverride All`.
2. Open the site and follow the setup page. It names the folders PHP must be able to write: `config/`, `storage/database/`, and `storage/attachments/`.
3. To update, unzip the next release over the same folder. `config/` and `storage/` are left as they are, and `SchemaMigrator` upgrades the database on the next request.

The root `.htaccess` lets only `index.php` run and refuses requests for `config/`, `storage/`, `src/`, `vendor/`, and other code or data paths. A host that lets you set the document root should use `php bin/build-release.php --with-public` and point the domain at `public/` instead. That zip adds the `public/` folder, and the code, settings, and data then sit outside the web folder.

## Several customers on one server

Set `INLAY_STORES` to a directory outside `public/` and `INLAY_OPERATOR_HOST` to the management hostname. The same keys can live in `config/platform.json` next to the code. Environment values win. Open the operator host, create the operator account, then add a customer. Each customer gets `{stores}/{slug}/adlexone.sqlite`, `config/`, and `attachments/`. Leave the admin password blank and their first visit is the same setup page, with their own paths. The operator login is stored in `platform.sqlite` beside the stores directory, not inside a customer database.

4. **Autoloader.** If you add classes under `src/`, regenerate the Composer classmap:

   ```powershell
   php composer.phar dump-autoload -o
   ```

   (`composer.phar` is not committed; download it from https://getcomposer.org/download/.)
