# Application function packs (optional)

**Prefer the built-in screens** configured in Manage → Applications → Navigation (or the Builder):

| Screen | Capability |
|--------|------------|
| Work / my items | `items.work` |
| Create item | `items.create` |
| Search | `search.*` |
| Announcements | `announcements.board` |
| App settings | `app.settings` |

Those live in `src/Screen/` — no pack folder required.

Add a pack under `site/apps/{pack}/` only for installation-specific screens that cannot be expressed as built-in screen config. Each `*.php` file (except `info.php`) may call `AppFunctions::register(...)`.

The former Contact Centre / Service Centre pack was retired into those built-in screens.
