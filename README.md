# Adlexone Inlay

PHP 8.1+ / SQLite action and information management system (helpdesk, knowledge base, report manager, time manager), ported from OneOrZero AIMS.

## Requirements

- PHP 8.1+ with `pdo_sqlite`, `dom`, `libxml`, `xml`
- Apache with `mod_rewrite` (or any server that routes requests to `index.php`)

## Setup

1. **Configuration.** The real files in `config/` are not committed because they hold credentials. Copy the templates and fill in the blanks:

   ```powershell
   Copy-Item -Recurse config.example\* config\
   ```

   Keep templates out of `config/` itself: `bootstrap/bootstrap.php` loads every `*.json` in `config/` (and one level of subfolders) as constants, and the first definition wins.

   Public URL (redirects / OAuth): leave `SET_PUBLIC_BASE_URL` empty for automatic detection from the request Host. For production, set it to the site origin (e.g. `https://inlay.example.com`). Optionally set `SET_ALLOWED_HOSTS` (comma-separated) and `SET_TRUSTED_PROXIES` (IPs/CIDRs) when behind a reverse proxy — forwarded headers are ignored unless the client IP is trusted.

2. **Database.** `storage/database/adlexone.sqlite` is not committed. Restore it from a backup or the server. Note that `storage/database/aims_sqlite_schema_and_seed.sql` still uses the old `aims_`-prefixed table names, while the code expects unprefixed names (`users`, `items`, ...).

3. **Attachments.** Make sure `storage/attachments/` exists and is writable by the web server.

4. **Autoloader.** If you add classes under `src/`, regenerate the Composer classmap:

   ```powershell
   php composer.phar dump-autoload -o
   ```

   (`composer.phar` is not committed; download it from https://getcomposer.org/download/.)
