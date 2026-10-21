# Site customizations

Put **only** installation-specific look-and-feel and app packs here.

| Path | What belongs here |
|------|-------------------|
| `site/themes/{name}/` | Skin: `theme.css` tokens, `theme.json`, `brand/` assets |
| `site/apps/{pack}/` | Extra screens (PHP packs discovered at runtime) |

Core product code lives outside this folder (`layouts/`, `src/`). To change branding or add a pack, work in `site/` — not by copying layout PHP.

New business applications are created with **Design → Create an application** (`/?manage=builder`), which composes an app from built-in screens (Work, New, Counts, Owners, Next steps, Diary, Parties, Activity, Files, Ageing, Register, Intake, Saved, Announcements, Settings). Configure screens when creating the application or under Design → Applications → Navigation — pack PHP under `site/apps/` is optional and only for rare custom screens.

## New theme (skin)

1. Copy `site/themes/inlay-stone/` to `site/themes/my-skin/`
2. Edit `theme.json` label and `theme.css` CSS variables
3. Replace `brand/favicon.svg` if needed
4. Pick it under Settings → Theme (or on a user account)

Shared chrome, icons, and base CSS are in `layouts/`.

## Colour and headers

- A theme sets one colour, `--inlay`, in `theme.css`. Application accents follow it: the first application takes the theme colour and the next nine turn around the colour wheel at the same weight (`Adlexone\Html\Accent`). An application can still set its own `accent` hex colour.
- A new user follows the site theme until they choose one under their own settings.
- Three header patterns are used, each for one kind of page. The home page has a greeting. An application page has the accent header with its tabs. Every other screen has the area name and its tabs: Design (Create an application, Applications, Fields, Record types, Rules), People and groups, and Settings.
- On a phone, lists on those screens stack into rows. The home page leads with what is assigned to the person.
