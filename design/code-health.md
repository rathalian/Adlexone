# Code health scan

Scan of the product trees (`src/`, `layouts/`, `bootstrap/`, `public/`, `bin/`, `site/`). `website/` is out of scope. The follow-up removed dead imports, the unused checkbox helper, and the `ItemSheet` write wrappers, and it pointed two old anchors at `Blocks::link`. URLs and migrations are unchanged.

`tests/code_health.php` locks the facts below that can be checked without a product decision. A later cleanup may shrink an allowlist. It may not add a file to one.

## Baseline

Checked before the register was written:

- `php -l` on 133 PHP files under `src`, `layouts`, `bootstrap`, `public`, `bin`, and `site`. No syntax errors.
- `php tests/run.php`. 36 checks passed before `tests/code_health.php` was added. That check runs with them.
- PHPStan 2.1.22 at level 5, using `phpstan.neon.dist`. No errors.
- `phpstan-baseline.neon` ignored 55 errors in 23 files when the scan was written. The largest groups were `return.type` in `src/Application/RecordQuery.php` (10) and path and type noise in `src/Platform/InstallLayout.php` (6). The unused `ApplicationBuilder::check()` entry and those ten field-helper entries have since been removed, leaving 44.

`src/Text` and `src/Theme` are on the PHPStan paths. The check fails if any `src/` directory is left out.

## Surface

Requests are query-driven. `Router::requested()` reads `manage`, then `application`, then home.

Public `manage` names in `Router::ALIASES`:

| Public name | Screen |
| --- | --- |
| `print` | `PrintScreen` |
| `records` | `ItemScreen` (`Router::ITEMS`) |
| `search` | `SearchScreen` |
| `activity`, `assigned` | `HomeScreen` |
| `applications` | `ApplicationsScreen` |
| `builder` | `BuilderScreen` |
| `fields` | `FieldsScreen` |
| `record-types` | `ItemTypesScreen` |
| `items` | opens home |
| `rules` | `RulesScreen` |
| `security` | `SecurityScreen` |
| `settings` | `SiteSettingsScreen` |
| `palette` | `PaletteScreen` (allowed beside the alias map) |

`manage=items` is not a screen. `Router::requested()` opens home for a manage name that is not public. Record options are `view`, `add_record`, `mine`, `note`, and the rest of `ITEM_OPTIONS`. Search options are `find`, `results`, `saved`, `open_saved`, and the rest of `SEARCH_OPTIONS`.

An application link is `?application=`. `ScreenCatalog::resolve()` returns the capability it is given. `Capabilities::open()` then opens work, announcements, settings, a `RecordScreens` view, a `site/apps` pack, or the shared record and search engines.

`AuthRouter` handles signed-out `action` values: `logoff`, `local`, `oauth_start`, and `oauth_callback`.

A new database is created from `storage/database/shared_schema.sql`. `Database::ensure()` does that when the file has no `users` table, then records `schema_migrations` version `Database::SCHEMA_VERSION`. A file that already has `users` is left as it is. There is no upgrade of an older file.

`site/apps/` contains the README only. `AppFunctions::discover()` still loads a pack when a directory of PHP files appears. `site/themes/` has eight skins (`inlay-stone`, `inlay-ink`, `inlay-blue`, `inlay-indigo`, `inlay-slate`, `inlay-violet`, `inlay-rose`, `inlay-emerald`). Those are choices, listed in `Theme::KNOWN_LABELS`.

## Done

- The 229 unused `use` lines are gone. `tests/code_health.php` fails if `src/` grows another one.
- `src/Text` and `src/Theme` are analysed with the rest of `src/`.
- Private `ApplicationBuilder::check()` is deleted, and so is its PHPStan baseline entry.
- `ItemScreen` calls `ItemWrite` for add, update, delete, and transform. The four `ItemSheet` wrappers are gone.
- The record list and the duplicate menu-value message use `Blocks::link`. `class="URL"` remains on `Form::sortButton()` and `Form::pageButton()` only.
- `SharedMethods.php` is analysed with the rest of `src/`. PHPStan excludes no files. The settings loader refuses a file it cannot read, and the ini writer accepts only text and numbers.
- `RecordQuery::dueDate()`, `firstOf()`, `prefer()`, and `party()` take the full field shape. The ten baseline entries for those returns are gone.
- `SetupScreen`, `SaasInstallScreen`, and `OperatorScreen` call `Theme::defineInstallDefaults()`. `StatusScreen` calls it with `false`, so it still does not set `SET_DEFAULT_LANGUAGE`.
- Public addresses are the screen keys. `manage=items` opens home. Record and search options use the current names. `ScreenCatalog` no longer maps old capability ids. `ApplicationStore` no longer rewrites stored labels on boot. A new application that has a create link and no search tab still gains one.
- `SchemaMigrator` is deleted. A new database comes from `shared_schema.sql`. `Role` no longer copies a 0–5 group number onto a role.
- `INLAY_DATA`, field-type aliases, the theme alias `new`, `Capabilities::renamedIcons()`, and the `oidc_*` action and setting names are gone. An unpacked package still keeps `config/` and `storage/` beside the code.
- `layouts/images/` is empty. A web address on a record uses the `ic-external` sprite. The check fails if an unreferenced image appears.

Public methods that are only reached through `self::` from `open()` are not dead. A name search misses them. This scan did not treat that list as unused.

## Standardise in place

The behaviour stays. The local pattern moves to the one the rules already name.

1. **`class="URL"`. Tag: standardise.** `Form::sortButton()` and `Form::pageButton()` keep `class="URL"`. `Form::historyBack()` uses `class="link URL"`. The record list and `FieldsScreen::addUpdateMenuValue()` now go through `Blocks::link`. The check rejects `class="URL"` on any other file.
2. **Direct `$_GET` and `$_POST`. Tag: standardise.** Token scan, so a comment does not count. `$_GET` is read in `bootstrap/bootstrap.php`, `ApplicationAccess`, `Capabilities`, `AuthRouter`, `RenderNavigation`, `Request`, and `Theme::forLogin()`. `$_POST` is read in `bootstrap/bootstrap.php`, `RecordQuery`, `RuleRunner`, `AuthRouter`, `Csrf`, and `Request`. New files are rejected. `Request` remains the reader for screens. The writers in `RuleRunner` and `RecordQuery` stay until a rule can return the value without writing the superglobal.
3. **Integer `IN (...)` lists. Tag: standardise.** `RecordListQuery::idWhere()` binds the list. `RecordQuery::intList()`, `GroupMembership`, `HomeDashboard`, `ItemSheet`, `SearchForm`, `SearchResults`, `ApplicationSearch`, `WorkScreen`, `WorkList`, `WorkBoard`, `MenuOptions`, and `SavedSearch` still concatenate ids after `intval`. The values are integers, so this is consistency, not an open injection. New SQL should use `RecordListQuery` or `BoundQuery`.
4. **Hand-built `<form>` tags. Tag: standardise.** `Form::form()` is the rule for a manage form. Literal forms remain where the markup is not that card: install and operator (`SetupScreen`, `SaasInstallScreen`, `OperatorScreen`, `HostConsole`), the record composer and owner assigner (`ItemSheet`, `RecordScreens::editor()`), and the in-application finder (`ApplicationSearch`, `WorkBoard`, `WorkList`). Leave the install forms. A manage screen that grows a new card uses `Form::form()`.
5. **Manage record lists. Tag: consolidate.** `ItemTypesScreen::typeRecordList()` and `FieldsScreen::fieldRecordList()` share the unused-toggle toolbar. `SecurityScreen::userRecordList()` and `groupRecordList()` are the same `Blocks::recordList` call with different columns. The seam already exists. A further helper is only worth it for the toggle, not a new list type.

Copy in `translations/English.lang.php` and `src/` did not contain the brochure words or the template names named in `.cursor/rules/writing-style.mdc`.

The removed form helpers (`buildForm`, `buildStartForm`, `buildFormFieldsGrid`, `buildEndFormWithButtons`, `buildFormSectionHeading`) do not appear in product PHP. `tests/code_health.php` keeps them out. `tests/retired_names.php` still guards the old auth method names.

## Needs a product call

These still have two jobs that are not the same job.

1. **`site/apps` packs. Tag: deprecate, later.** The directory is empty and the built-in catalogue is the path the README prefers. `AppFunctions` is the extension point for a screen that cannot be expressed as navigation config. Removing it is a product decision, not a dead-code deletion.
2. **Record screens. Tag: consolidate, later.** `RecordScreens::open()` dispatches ten capabilities to `RecordCensus`, `RecordPeople`, `RecordPlan`, and `RecordRegister`. They do not share one row shape. Keep the four classes.
3. **Search. Tag: consolidate, later.** `SearchScreen` opens the manage search. `ApplicationSearch` is the in-application finder. Both read records through `RecordListQuery`. They are different pages.
4. **Application shape. Tag: simplify, later.** `BlueprintCatalog` holds the static blueprint data. `ApplicationBuilder` installs that data. `ApplicationPackage` imports and exports. `ApplicationStore` reads and writes the application rows. `Rules` stores a rule and `RuleRunner` runs it. The overlap is real and the boundaries are already named. Move code only when one of those classes is being changed for a feature.
5. **SaaS proposal. Tag: product gap.** `design/saas-installer/proposal.md` still describes cost, aggregated usage, and editing a site after it is created. `HostConsole`, `SiteUsage`, `OperatorScreen`, and `SaasInstallScreen` are the built part. That gap is scope, not unused code.
6. **Eight themes.** Customer-facing skins. Not duplicates to merge.

## Still open

The safe items in this pass are done. What remains is the product-call list above, the integer `IN (...)` lists, and the `$_POST` writers in `RuleRunner` and `RecordQuery`.
