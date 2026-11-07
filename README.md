# Adlexone

PHP 8.1+ / SQLite action and information management system. The database is a **shared platform schema** (users, items, fields, applications registry). Application packs consume shared data and may store an application slug on shared rows — they must not own private tables.

## Requirements

- PHP 8.1+ with `pdo_sqlite`, `dom`, `libxml`, `xml`
- Apache with `mod_rewrite` (or any server that routes requests to `index.php`)

## Setup

1. **Open the site.** If there is no administrator yet, the self-hosted installer opens with a short introduction, then three steps.

   - **Document root.** Shared hosting needs the Adlexone package in the document root. If the package is not in the document root, the document root must be the `public` folder. The page reads the document root from the server and continues only when the choice matches.
   - **Storage directory.** Choose a folder for the database, settings, and uploads. Adlexone creates `config/`, `database/adlexone.sqlite`, and `attachments/` inside it, and checks that the account running PHP can create, read, update, and delete files there. It also checks that a browser cannot fetch a file from that folder. A folder inside the web folder is strongly recommended against. If one is chosen anyway, setup requests a trial file from the website and continues only when that request does not return the file. The path is saved in `config/platform.json` next to the code (`INLAY_STORAGE`). The field suggests `storage-host` one folder higher than the detected document root. For a document root of `/home/health/public_html`, that is `/home/health/storage-host`. Create that folder before the check. The check does not create it. It does check that a file and a folder can be created inside it. The folders Adlexone then creates inside it are mode `700`. The database file and the settings files are mode `600`. The storage folder, the document root, and the home directory are left as they are.
   - **Account.** Set the name shown on the sign-in page. The default is Adlexone. It can be the organisation name or a system name. Then create the first administrator.

   An environment value wins over `config/platform.json`. When neither is set, an unpacked package keeps `config/` and `storage/` beside the code.

2. **Settings.** `bootstrap/bootstrap.php` loads every `*.json` in the settings folder (and one level of subfolders) as constants, except `platform.json`, and the first definition wins. Do not put the templates themselves in that folder. `platform.json` stays next to the code.

   Public URL (redirects / OAuth): leave `SET_PUBLIC_BASE_URL` empty for automatic detection from the request Host. For production, set it to the site origin (e.g. `https://health.example.com`). Optionally set `SET_ALLOWED_HOSTS` (comma-separated) and `SET_TRUSTED_PROXIES` (IPs/CIDRs) when behind a reverse proxy — forwarded headers are ignored unless the client IP is trusted.

3. **Updates.** Replace the code and leave the storage directory in place, including `config/platform.json` next to the code. A deploy ships the current database. `Database::ensure()` creates a missing file from `storage/database/shared_schema.sql` and leaves a file that already has `users` as it is.

## Release zip for shared Apache hosting

`php bin/build-release.php` builds `dist/adlexone-{version}-{commit}.zip` from the last commit. It needs PHP 8.1+ with `phar` and `zlib`, and git. The zip holds the code only. It never holds `config/*.json`, a SQLite file, or attachments. The `website/` folder is the sales site. It is left out of the zip, and the application does not serve it.

1. Unzip it into the domain's document root. The file manager on most hosts can do this. Apache needs `mod_rewrite` and `AllowOverride All`.
2. Open the site and follow the self-hosted installer. Confirm the document root, then choose the storage directory. The account that runs PHP must be able to create, read, update, and delete files there.
3. To update, unzip the next release over the same folder. `config/` and `storage/` are left as they are. The release includes the current database shape. An older database file is not upgraded.

The root `.htaccess` lets only `index.php` run and refuses requests for `config/`, `storage/`, `src/`, `vendor/`, `website/`, and other code or data paths. A host that lets you set the document root should use `php bin/build-release.php --with-public` and point the domain at `public/` instead. That zip adds the `public/` folder, and the code, settings, and data then sit outside the web folder.

## Several customers on one server

On a copy that is not in SaaS mode yet and has no administrator, open `./?host=install`. `./?install=host` is the same page. The page writes a setup token one folder above the document root and asks for that value. It does not show the value. The SaaS installer confirms the document root, chooses the stores directory, checks that PHP can use it and that a browser cannot read it, and saves the operator hostname. The operator account is created on that hostname, and it asks for a second setup token written beside the operator database. The same keys can live in the environment or in `config/platform.json` next to the code. Environment values win. Leave both `INLAY_STORES` and `INLAY_OPERATOR_HOST` empty for one self-hosted site.

The customer sites screen is the operator hostname. A customer hostname does not open it.

Open the operator host to add a customer, change the display name, own domain, or contact email, and read usage and cost. The address is the slug under the parent of the operator hostname. For an operator hostname of brospd.adlexone.com, Health is health.adlexone.com. One wildcard, *.adlexone.com, covers every slug. An optional own domain answers on the same site. Details on Add a site explains the DNS record. Each customer gets `{stores}/{slug}/adlexone.sqlite`, `config/`, and `attachments/`. Leave the admin password blank and their first visit writes a setup token beside that site’s database. Read the file and give the value to the customer. The page does not show it. The operator login, the site list, the rate, and the usage measurement are stored in `platform.sqlite` inside the stores directory, not inside a customer database. The field suggests `storage-saas` one folder higher than the detected document root. For a document root of `/home/health/public_html`, that is `/home/health/storage-saas`. Create that folder before the check. The installer sets mode `600` on `platform.sqlite` and mode `700` on each customer folder it creates inside the stores directory. It does not change the stores directory, the document root, or the home directory. A file already saved beside the stores directory is left there. The design notes are in `design/saas-installer/`.

4. **Autoloader.** `vendor/` is committed and a host never runs Composer. When a commit adds, moves, or deletes a class under `src/`, the Composer classmap must be rebuilt in the same commit:

   ```
   composer dump-autoload -o
   ```

   The `pre-commit` hook in `.githooks/` does this and stages the result. Turn it on once in each clone:

   ```
   git config core.hooksPath .githooks
   ```

   The hook uses `composer` if it is installed, and `composer.phar` in the project folder otherwise. `composer.phar` is development tooling only. The release zip leaves it out.
