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

## Several customers on one server

Set `INLAY_STORES` to a directory outside `public/` and `INLAY_OPERATOR_HOST` to the management hostname. The same keys can live in `config/platform.json` next to the code. Environment values win. Open the operator host, create the operator account, then add a customer. Each customer gets `{stores}/{slug}/adlexone.sqlite`, `config/`, and `attachments/`. Leave the admin password blank and their first visit is the same setup page, with their own paths. The operator login is stored in `platform.sqlite` beside the stores directory, not inside a customer database.

4. **Autoloader.** If you add classes under `src/`, regenerate the Composer classmap:

   ```powershell
   php composer.phar dump-autoload -o
   ```

   (`composer.phar` is not committed; download it from https://getcomposer.org/download/.)
