# Site customizations

Put **only** installation-specific look-and-feel and app packs here.

| Path | What belongs here |
|------|-------------------|
| `site/themes/{name}/` | Skin: `theme.css` tokens, `theme.json`, `brand/` assets |
| `site/apps/{pack}/` | Extra screens (PHP packs discovered at runtime) |

Core product code lives outside this folder (`layouts/`, `src/`, `inlay_functions/`, …). To change branding or add a pack, work in `site/` — not by copying layout PHP.

## New theme (skin)

1. Copy `site/themes/inlay-stone/` to `site/themes/my-skin/`
2. Edit `theme.json` label and `theme.css` CSS variables
3. Replace `brand/favicon.svg` if needed
4. Pick it under Settings → Theme (or on a user account)

Shared chrome, icons, and base CSS are in `layouts/`.
