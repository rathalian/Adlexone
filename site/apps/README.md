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

## Rendering from a pack

A screen builds its content and hands it to the request's page. It does not echo or define constants.

```php
use Adlexone\Http\Page;
use Adlexone\Http\View;
use Adlexone\Html\Html;

$page = Page::current();
$page->suggestTitle('Reports');          // only applies when no title is set yet
$page->setBody('<p>' . Html::e($text) . '</p>');
$page->addScript('my-pack');             // loads layouts/js/my-pack.js, deferred
```

- `View::respond($message)` shows a short message in the page body.
- `View::partial('name', $vars)` renders `layouts/partials/name.php`.
- `Page::send()` runs once at the end of the request; screens never call it, except where they stop the request with `exit`.
- Read and rewrite query parameters through `Adlexone\Http\Request`, not `$_GET`.
- Put script in `layouts/js/`, and pass data to it with `data-*` attributes. Inline `<script>` blocks are not used.
