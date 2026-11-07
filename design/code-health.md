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
| `items` | redirected, see below |
| `rules` | `RulesScreen` |
| `security` | `SecurityScreen` |
| `settings` | `SiteSettingsScreen` |
| `palette` | `PaletteScreen` (allowed beside the alias map) |

`manage=items` is not a screen. `Router::open()` sends type options to `record-types` and everything else to `fields`. Record options stay on the old names (`show_item`, `add_item`, `my_items`, and the rest of `ITEM_OPTIONS`). Search options stay on `SEARCH_OPTIONS`.

An application link is `?application=`. `Capabilities::open()` resolves `ScreenCatalog::ALIASES`, then opens work, announcements, settings, a `RecordScreens` view, a `site/apps` pack, or the shared record and search engines.

`AuthRouter` handles signed-out `action` values: `logoff`, `local`, `oauth_start` / `oidc_start`, `oauth_callback` / `oidc_callback`.

`SchemaMigrator` is version 22. Steps that add or drop columns have to stay for databases that are still behind. Steps that rewrite old rows (group lists, Service Centre, saved-search SQL, showcase applications, home-controller names, role numbers, note audiences) are repair, not the shape of a new database. Do not squash either kind while an existing file might still be on an earlier version.

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

## Still safe, once a live database is searched

**Layout images. Tag: deprecate.** `layouts/images/` has 60 files. Product PHP, CSS, JS, JSON, SVG, and HTML name `hyperlink.png` only (`ItemSheet`, via `SET_IMAGE_PATH`). The sign-in icon is `Theme::faviconHref()`, an SVG. The other 59 names are listed in the check, which fails if a new unreferenced file appears. A stored record body could still point at an old path. This environment has no customer database, so the files stay.

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

These still have a stored value, a bookmark, or two jobs that are not the same job.

1. **Item and record vocabulary. Tag: deprecate, later.** The public engine is `?manage=records`. Options are still `show_item` and `add_item`. `manage=items` still redirects to fields or record types. Renaming an option breaks a bookmark and any saved link. Do not rename in a cleanup pass.
2. **`ScreenCatalog::ALIASES`. Tag: deprecate, later.** `contact_centre.*`, `servicecentre.*`, and `announcements` still map to current ids because navigation rows can store the old id. `ApplicationStore` still rewrites some of those labels. Drop the map only after a migration has rewritten every stored capability.
3. **Icons and themes. Tag: deprecate, later.** `Capabilities::renamedIcons()` draws an old stored icon id as a current sprite. `Theme::ALIASES` maps `new` to `inlay-stone` for a saved setting. `FieldTypes::ALIASES` accepts builder method names (`buildTextInput` and the rest) because `custom_fields.field_type` can still hold them.
4. **OAuth and OIDC names. Tag: standardise, later.** `AuthRouter` accepts `oidc_start` and `oidc_callback` beside the OAuth actions. `tests/retired_names.php` already removed `oidcEnabled()` and kept `AUTH_OIDC_*` because an existing settings file still signs in with those constants. Do not collapse the constants in this scan.
5. **`INLAY_DATA`. Tag: deprecate, later.** `InstallLayout` still honours the older storage layout. `INLAY_STORAGE` wins when both are set. Sites that were installed the old way still boot through `INLAY_DATA`.
6. **`site/apps` packs. Tag: deprecate, later.** The directory is empty and the built-in catalogue is the path the README prefers. `AppFunctions` is the extension point for a screen that cannot be expressed as navigation config. Removing it is a product decision, not a dead-code deletion.
7. **Record screens. Tag: consolidate, later.** `RecordScreens::open()` dispatches ten capabilities to `RecordCensus` (331 lines), `RecordPeople` (213), `RecordPlan` (241), and `RecordRegister` (523). They do not share one row shape. Keep the four classes.
8. **Search. Tag: consolidate, later.** `SearchScreen` opens the manage search (`SearchForm` 533 lines, `SearchResults` 326). `ApplicationSearch` (936 lines) is the in-application finder. Both read records through `RecordListQuery`. They are different pages.
9. **Application shape. Tag: simplify, later.** `BlueprintCatalog` is 1,044 lines of static blueprint data. `ApplicationBuilder` is 1,069 lines and installs that data. `ApplicationPackage` (529) imports and exports. `ApplicationStore` (832) reads and writes the application rows. `Rules` (955) stores a rule and `RuleRunner` (916) runs it. The overlap is real and the boundaries are already named. Move code only when one of those classes is being changed for a feature.
10. **`SchemaMigrator` repair steps. Tag: simplify, later.** Version 22 has to keep running on an old file. Squashing v1–v22 into `shared_schema.sql` would break an upgrade. A fresh install can already build from the migrator. Leave the steps in order.
11. **SaaS proposal. Tag: product gap.** `design/saas-installer/proposal.md` still describes cost, aggregated usage, and editing a site after it is created. `HostConsole`, `SiteUsage`, `OperatorScreen`, and `SaasInstallScreen` are the built part. That gap is scope, not unused code.
12. **Eight themes.** Customer-facing skins. Not duplicates to merge.

## Still open

Delete the 59 unreferenced files in `layouts/images/` after a search of a live database for those filenames. The other safe items are done. The product-call list above is unchanged.
