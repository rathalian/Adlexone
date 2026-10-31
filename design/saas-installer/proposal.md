# SaaS installer and manager

This is the shape for the two tools that follow. It does not add the installer, the manager, or a schema. The self-hosted installer in `src/Platform/SetupScreen.php` stays as it is. A customer who still has no administrator finishes on their own hostname, on that same screen. That page is theirs.

The person who runs the server gets two pages.

The **SaaS installer** is the first visit that turns this copy of Adlexone into a host of customer sites. It chooses the stores directory and the operator hostname, checks that PHP can create, read, update, and delete files there, checks that a browser cannot read that directory, and creates the operator account.

The **site manager** is the signed-in operator page. It creates, reads, updates, and deletes hosted sites. It shows usage, and the cost of that usage, for one site and for the whole host.

## Where the installer sits

A new copy with `INLAY_STORES` empty still opens the self-hosted installer at `./`. That flow is introduction, document root, storage directory, account. The SaaS installer is a different page, `./?install=host`, on the same origin, handled by `Adlexone\Platform\SaasInstallScreen` before `SetupScreen`. The welcome page of the self-hosted installer does not link to it.

`./?install=host` runs only when all of these are true.

- `INLAY_STORES` is empty in the environment and in `config/platform.json` beside the code. `Runtime::define()` has not taken the platform path.
- `INLAY_OPERATOR_HOST` is empty in the environment. A value in the environment wins over the file, so the installer will not write a hostname the process will ignore.
- The self-hosted SQLite file from `InstallLayout::resolve()` has no administrator yet (`Runtime::needsSetup()` is true).

When that SQLite file already has an administrator, `./?install=host` stops. The page names the file and says this copy is already one site. The installer does not move that site into a customer folder.

When `INLAY_STORES` or `INLAY_OPERATOR_HOST` is set in the environment and the other is empty, the page says the environment already holds one of the two values and the other has to be set the same way. It does not write `config/platform.json`.

The installer keeps its own session key, `adlexone_host_install`. It does not read or write the self-hosted installer’s session.

## A server already in SaaS mode

SaaS mode is on when `INLAY_STORES` is set. `Runtime::define()` then returns on the platform path and bootstrap never calls `SaasInstallScreen`.

On the operator hostname the request is the operator screen. `?install=host` is ignored. With no operator account yet, the screen is “Create the operator account”. After that account exists, the screen is the manager. On any other hostname the request is that customer’s site, their setup, the suspended page, or “Address not recognised”. The installer is not among them.

The installer writes both values in one save. The existing notice for a stores directory with an empty operator host stays for a file edited by hand. The installer is not the repair for that file.

## Installer steps

The page uses the setup layout. There are four steps. Nothing is written to `config/platform.json` until the operator hostname is accepted. The account is created after that, on the operator hostname, because the session cookie has to belong to that host.

### 1. Document root

The page reads the document root with `InstallLayout::detect()` and offers the same two choices as the self-hosted installer: the package is in the document root, or the document root is the `public` folder. Continue accepts only the choice that matches. An unknown document root stops the installer with `InstallLayout::placementMessage()`. The stores check needs this answer so it knows which folder the website serves.

### 2. Stores directory

The field is a full path. The example is `/home/health/adlexone-stores`. The field is not prefilled with `storage/`. That path is the self-hosted storage directory, and it sits inside the website when the package is the document root.

Check this folder runs `InstallLayout::probe()`: create, read, update, delete, and create then remove a folder, as the account that runs PHP. The trial files are removed before the page answers. The probe also checks that PHP can write `config/platform.json` beside the code.

The stores directory has to sit outside the document root. The website then has no address for it. A directory inside the document root is refused, including when a trial request would not return a file. This folder holds every customer’s database, settings, and uploads. The self-hosted installer still allows its own storage directory inside the web folder when the trial file does not come back. That exception is not used here.

The directory is also refused when it is the package, when it contains the package, when it is inside `src`, `vendor`, `bootstrap`, `layouts`, `site`, `tests`, `translations`, `config.example`, `bin`, or `config`, or when it is inside the self-hosted storage directory `InstallLayout::resolve()` would use. A directory that already has `adlexone.sqlite` as a direct child is one site’s folder, and it is refused. Files already inside a stores directory are left in place. The installer does not add a `stores` row for them.

The operator database is `platform.sqlite` in the parent of the stores directory. For `/home/health/adlexone-stores` that file is `/home/health/platform.sqlite`. It sits beside the stores directory, outside `public/`, and outside every customer folder. The check requires that PHP can create that file and that the path is outside the document root and outside the stores directory. Directories are mode 700. The database file is mode 600.

When `INLAY_PLATFORM_DB` is already set in the environment, that path is used instead, after the same checks. The form does not ask for a database path.

### 3. Operator hostname

The field defaults to `Runtime::requestHost()`. The value is stored in lower case. It uses the same rule as a customer hostname: letters, numbers, dots, and hyphens, and it contains a dot. For example, `operate.example.com`.

Saving writes `config/platform.json` beside the code, the way `InstallLayout::remember()` merges one key into the existing JSON. The installer sets `INLAY_STORES`, `INLAY_OPERATOR_HOST`, and `INLAY_PLATFORM_DB`. It leaves `INLAY_STORAGE` in place when that key is already there. While `INLAY_STORES` is set, `Runtime` does not use `INLAY_STORAGE`. The installer does not delete a self-hosted folder. The file is mode 600. `config.example/platform.json` is not changed.

The page then sends the browser to `https://` plus the operator hostname. When this request is already on that hostname, the next page is step 4.

### 4. Operator account

This is the form `OperatorScreen` already shows when `PlatformDb::operatorCount()` is zero. The title stays “Create the operator account”. The lead stays: the account manages customer sites and is stored in the operator database, not in a customer SQLite file. Above the form, the page names the stores directory and the operator hostname just saved.

The username and password rules stay as they are, including `DefaultAccount`. The password is stored as a hash in `platform.sqlite`. When the account exists, this form is not shown again. This work does not add a second operator.

Submitting the account form signs that person in and opens the manager.

## The manager

The manager is the signed-in page of `OperatorScreen`. Sign-in and the first operator account stay on the installer layout. After sign-in the manager uses the same shell as a customer site: the top bar, the section bar, and the home dashboard.

`./?host=manage` opens the manager on any address this server answers. On the operator hostname the same screen is `./` with no query. `Runtime::operatorAddress()` keeps that difference in every link and form.

- `./` (or `./?host=manage`) is the dashboard. The lead is “Each row is one site. The totals are the last measurement of those sites.” The section bar is Host, with Sites, Rate, and Add a site.
- `./?view=rate` is the rate. `./?view=add` is the form that adds a site.
- `./?site=health` is one site. The title is the display name, for example “Health”. The lead names the hostname and the measurement, for example “health.example.com. These figures are the last measurement of this site.” The value is the slug. A slug that is not in `stores` says that customer site was not found.

`?site=` is read only in operator mode. Bootstrap already exits before the customer router on that hostname.

The dashboard shows the host totals, then one card for each site. The card shows the display name, the hostname, the status, the folder total from the last measurement, and the cost. The name opens the site page. The rate and the add form are their own screens. Suspend, backup, and delete stay on the site page.

The add form is unchanged: display name, slug, hostname, admin email, and the optional admin username and password. The same validation stays. Leave the username and password blank and the customer’s first visit to their hostname is still their setup page. After a site is created, the browser opens `?site=` for that slug, and the notice names the folder as it does now.

The site page shows the folder paths, the usage split, the cost, the update form, and the suspend, resume, backup, and delete actions.

## Update

Update is a save on the site page. It posts the store id and the slug. Both have to name the same row. A posted path is ignored. The folder is the stores directory plus the slug already stored, checked with the same inside-stores rule as delete.

Update changes three values.

- **Display name.** `stores.label`, and `SET_SITE_NAME` in that site’s `config/adlexone_settings.json`. The sign-in page reads the settings file. The name is 1 to 80 characters, the same limit as create. For example, Health.
- **Hostname.** `stores.host`, in lower case, unique, and not the operator hostname. The same pattern as create. When the hostname changes, that site’s `SET_PUBLIC_BASE_URL` becomes `https://` plus the new hostname, which is what create writes. When the hostname is unchanged, `SET_PUBLIC_BASE_URL` is left as it is. The old hostname then has no site. The new hostname opens the same folder.
- **Contact.** `stores.admin_email`. This is the address on the operator list. It may be blank. When it is not blank it has to be an email address. It is not written into the customer `users` table, and it does not change the administrator’s password or username.

The settings file is patched for those keys only. SMTP passwords and other keys in the file stay. The write is that site’s `config/adlexone_settings.json` and no other file. If that file is missing, the save stops and the `stores` row is not changed. Suspend, backup, and delete still work.

Update does not change the slug, the folder, another site’s files, the stores directory, the operator database, the site status, or anything inside the customer SQLite file.

## Usage

Usage is the last measurement of one site. The live folder size on the console today, `Provisioner::folderBytes()`, is replaced by this split. The parts are what the folder holds.

Read from disk, as file sizes, without opening SQLite:

| Figure | Where |
| --- | --- |
| Database | `{stores}/{slug}/adlexone.sqlite` |
| Write-ahead log | `adlexone.sqlite-wal` beside it, or 0 B when the file is absent |
| Shared memory | `adlexone.sqlite-shm` beside it, or 0 B when the file is absent |
| Settings | every file under `{stores}/{slug}/config/` |
| Uploads | every file under `{stores}/{slug}/attachments/` |
| Other | the rest of the folder, including `setup-token.txt` while that site has no administrator |
| Folder total | database + write-ahead log + shared memory + settings + uploads + other |

Other is omitted on the page when it is 0 B. The folder total is the figure the gigabyte rate uses. A file is counted only when its real path stays inside the site folder. A link that points out of the folder is not counted.

Sizes use the steps the console uses now: 1,024 bytes is 1 KB, 1,024 KB is 1 MB, and the manager adds 1,024 MB as 1 GB. A gigabyte in the rate is those same 1,073,741,824 bytes.

Read from that site’s SQLite file, and from nowhere else:

| Figure | Query |
| --- | --- |
| People | `COUNT(*)` from `users` |
| Applications | `COUNT(*)` from `applications` where `enabled` is 1 |
| Records | `COUNT(*)` from `items` |

People is every row in `users`. The `lastactive` column is a status word, not a time, and it is not a filter. `operators` is not in this file and is not a person on the site. Disabled applications are omitted so the number matches the applications the site offers. Records are rows in `items`. The product calls those rows records. Upload bytes come from the attachments directory on disk. `item_attachments.file_size` is not a usage figure.

A site that has not finished setup has no `users` table, or has no rows in it. `Runtime::needsSetup()` is the test. People, applications, and records are then unset, not zero. The page says the site has no administrator yet. The disk figures are still shown.

The operator database, the code, and `vendor/` are not usage. The host total is the sum of customer folders.

## Cost

There is no price list and no invoice. The operator types one rate for the whole host. The manager multiplies that rate by the last measurement. Cost is that product. It is not a bill, a balance, or a history. Saving a new measurement replaces the previous one.

The rate is stored in `platform.sqlite`, in a single `host_rate` row: a currency code, a monthly amount, an amount per person, and an amount per gigabyte, plus the time it was saved. Amounts are integer cents. The currency is three letters. The field starts as AUD. The form accepts 0.00 to 1,000,000.00 for each amount, with two decimal places. A negative amount is refused.

The rate section is on the host page. Its title is “Rate”. The lead is: “One rate for every site. There is no invoice. The cost is this rate applied to the last measurement.” The gigabyte help says a gigabyte is 1,024 MB and that the figure uses the folder total.

For one site the cost in cents is:

- the monthly amount, applied once to that site
- plus the number of people times the amount per person
- plus the folder total in bytes times the amount per gigabyte, divided by 1,073,741,824, rounded to the nearest cent (a half rounds up)

Applications and records are shown with the usage. They are not in the rate.

A site that has not finished setup contributes zero people to the cost. The counts stay unset on the page. The monthly amount and the storage amount still apply, because the folder is on disk. A suspended site is charged the same way. Suspend leaves the files in place. Delete is what removes the folder and drops the site from the total.

No rate row means cost is not calculated. The page says: “No rate is set. Set a rate to calculate a cost. Usage is already shown.” A saved rate of zero is a real rate and the cost is 0.00 AUD, or whatever currency was saved. “Clear the rate” deletes the row and clears the stored cost on every site. Clearing the rate does not open a customer database.

Saving the rate recomputes each site’s cost from the stored measurement and the new rate. It does not read a customer folder.

The host cost is the sum of the site costs for sites that have a measurement. A site that has never been measured is left out, and the page counts those sites in one sentence.

## Measuring

The host page and the site page read the measurement stored in `platform.sqlite`. They do not open a customer database on a normal view. One row per site, `store_usage`, holds the measured time as Unix time, the byte columns, the three counts, and the cost in cents. Counts are null when the site has no administrator yet. Cost is null when no rate is set. Deleting a site deletes this row with the `stores` row.

The operator database is the only database these pages open.

“Measure usage” on the host page walks the sites one at a time. For each site it reads the disk figures, then opens that site’s SQLite file read-only at `sqlite:file:{path}?mode=ro` with `PRAGMA query_only = ON` and a busy timeout of five seconds. It does not run `SchemaMigrator`, and it does not create a missing file. A missing `adlexone.sqlite` leaves the counts unset. A count whose table is missing stays unset as well, and the page names the site. If the file is locked or cannot be opened, the disk figures still update and the previous counts are kept. The connection is closed before the next site is opened.

“Measure this site” on the site page does that for one slug.

Creating a site measures that site before the browser is sent to its page, so a new row has a measured time. Update, suspend, resume, and backup do not measure. Backup still changes the file sizes, because it checkpoints the write-ahead log into the main file. The measured time on the page is how the operator sees that the figures are from before that backup.

The measured time is shown in the server’s default time zone, and the zone name is printed with it. For example, “29 Oct 2026, 15:41 (Australia/Sydney)”.

There is no scheduled measure in this work.

## Suspend, resume, backup, and delete

These stay the actions they are now.

**Suspend** sets `stores.status` to `suspended`. The folder stays. `Runtime::definePlatform()` answers that hostname with the suspended page, which stops sign-in. **Resume** sets the status back to `active`. The files are not opened.

**Backup** runs `Provisioner::checkpoint()` on that site’s `adlexone.sqlite` (`PRAGMA wal_checkpoint(TRUNCATE)`), skips the `-wal` and `-shm` files, and downloads `{slug}-backup.zip`. The operator database is not in the zip.

**Delete** requires the slug typed on the site page. The comparison stays `hash_equals` against the stored slug. The folder has to stay inside the stores directory. `Provisioner::removeTree()` removes that folder, then `PlatformDb::deleteStore()` removes the row, and the measurement row goes with it. Another site’s folder is not on that path. The operator database is the parent of the stores directory, so this delete does not remove it.

## Files the implementation changes

- `src/Platform/SaasInstallScreen.php` — the installer page at `./?install=host`.
- `bootstrap/bootstrap.php` — call that page only while SaaS mode is off and the query is `install=host`.
- `src/Platform/SiteUsage.php` — the disk split, the three counts, the snapshot, and the cost multiplication.
- `src/Platform/PlatformDb.php` — read and write `host_rate` and `store_usage`, and update `label`, `host`, and `admin_email` on one `stores` row. `ensureSchema()` creates the two tables when the operator database opens, the same way it creates `operators` and `stores` today.
- `src/Platform/OperatorScreen.php` — the host page, the site page, update, the rate, and measure. Create, sign-in, the operator account, suspend, resume, backup, and delete keep their current behaviour.
- `src/Platform/HelpText.php` — `operator()` describes the installer as the way the two values are written, and describes update, the measurement, and the rate. Environment values still win, and both values empty is still one self-hosted site. A new `hostInstall()` is the help on the installer. `customer()` is unchanged.
- `README.md` — the “Several customers on one server” paragraph names `./?install=host` for a copy that is not in SaaS mode yet, and keeps the environment as the way that still wins over the file.

`SetupScreen`, `Provisioner::install()`, `Provisioner::checkpoint()`, and `Provisioner::removeTree()` stay. `folderBytes()` can remain as the check that the named parts plus other equal a full walk of the folder.

## What the implementation has to prove

The installer route does not render when `INLAY_STORES` is set. Update of Health changes that site’s label, hostname, contact, `SET_SITE_NAME`, and, only when the hostname changed, `SET_PUBLIC_BASE_URL`. The slug directory is the same directory afterwards, and another site’s `config/` is untouched. The cost of a measured folder is the monthly amount, plus people times the per-person amount, plus the folder bytes times the per-gigabyte amount over 1,073,741,824. The host page reads the stored measurement and does not open a customer SQLite file. Delete still removes the folder only after the typed slug matches, and it removes only that folder.
