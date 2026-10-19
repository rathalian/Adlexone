<?php
declare(strict_types=1);

namespace Adlexone\Theme;

/**
 * Skin themes under site/themes/{name}/ — CSS tokens + brand assets only.
 * Shared HTML/JS/base CSS live under layouts/.
 */
final class Theme
{
    private const KNOWN_LABELS = [
        'inlay-stone' => 'Inlay Stone',
        'inlay-ink' => 'Inlay Ink',
        'inlay-blue' => 'Inlay Blue',
        'inlay-indigo' => 'Inlay Indigo',
        'inlay-slate' => 'Inlay Slate',
        'inlay-violet' => 'Inlay Violet',
        'inlay-rose' => 'Inlay Rose',
        'inlay-emerald' => 'Inlay Emerald',
    ];

    /** Former slug kept so saved user/settings values still resolve. */
    private const ALIASES = [
        'new' => 'inlay-stone',
    ];

    public static function canonicalize(string $theme): string
    {
        $theme = trim($theme);
        return self::ALIASES[$theme] ?? $theme;
    }

    /**
     * Theme for the sign-in page. A valid ?theme= choice is kept for this session.
     */
    public static function forLogin(): string
    {
        $installed = self::installed();
        $asked = self::canonicalize((string) ($_GET['theme'] ?? ''));
        if ($asked !== '' && isset($installed[$asked])) {
            $_SESSION['preview_theme'] = $asked;
        }
        $stored = self::canonicalize((string) ($_SESSION['preview_theme'] ?? ''));
        if ($stored !== '' && isset($installed[$stored])) {
            return $stored;
        }

        $fallback = defined('SET_DEFAULT_THEME') ? (string) SET_DEFAULT_THEME : 'inlay-stone';

        return self::canonicalize($fallback);
    }

    public static function name(): string
    {
        if (defined('SET_THEME') && (string) SET_THEME !== '') {
            return self::canonicalize((string) SET_THEME);
        }
        if (defined('SET_DEFAULT_THEME') && (string) SET_DEFAULT_THEME !== '') {
            return self::canonicalize((string) SET_DEFAULT_THEME);
        }

        return 'inlay-stone';
    }

    public static function rootFs(): string
    {
        $base = defined('SET_INSTALL_PATH') ? (string) SET_INSTALL_PATH : '';
        $path = defined('THEME_PATH') ? (string) THEME_PATH : 'site/themes/';

        return rtrim($base . $path, '/\\') . DIRECTORY_SEPARATOR;
    }

    public static function layoutFs(): string
    {
        $base = defined('SET_INSTALL_PATH') ? (string) SET_INSTALL_PATH : '';
        $path = defined('LAYOUT_PATH') ? (string) LAYOUT_PATH : 'layouts/';

        return rtrim($base . $path, '/\\') . DIRECTORY_SEPARATOR;
    }

    public static function dirUrl(string $theme = ''): string
    {
        $theme = self::canonicalize($theme !== '' ? $theme : self::name());
        $path = defined('THEME_PATH') ? (string) THEME_PATH : 'site/themes/';

        return rtrim(str_replace('\\', '/', $path), '/') . '/' . rawurlencode($theme);
    }

    public static function layoutUrl(string $relative = ''): string
    {
        $path = defined('LAYOUT_PATH') ? (string) LAYOUT_PATH : 'layouts/';
        $base = rtrim(str_replace('\\', '/', $path), '/');
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        return $relative === '' ? $base : $base . '/' . $relative;
    }

    public static function url(string $relative, string $theme = ''): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        return self::dirUrl($theme) . '/' . $relative;
    }

    public static function cssFile(string $theme = ''): string
    {
        $theme = self::canonicalize($theme !== '' ? $theme : self::name());
        $meta = self::meta($theme);
        $css = (string) ($meta['css'] ?? 'theme.css');

        return $css !== '' ? $css : 'theme.css';
    }

    public static function baseCssHref(): string
    {
        $fs = self::layoutFs() . 'css' . DIRECTORY_SEPARATOR . 'base.css';
        $v = is_file($fs) ? (int) @filemtime($fs) : 0;

        return self::layoutUrl('css/base.css') . ($v > 0 ? '?v=' . $v : '');
    }

    public static function cssHref(string $theme = ''): string
    {
        $theme = self::canonicalize($theme !== '' ? $theme : self::name());
        $file = self::cssFile($theme);
        $fs = self::rootFs() . $theme . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
        $v = is_file($fs) ? (int) @filemtime($fs) : 0;

        return self::url($file, $theme) . ($v > 0 ? '?v=' . $v : '');
    }

    public static function jsHref(string $name = 'app'): string
    {
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '', $name) ?? '';
        $fs = self::layoutFs() . 'js' . DIRECTORY_SEPARATOR . $name . '.js';
        $v = is_file($fs) ? (int) @filemtime($fs) : 0;

        return self::layoutUrl('js/' . $name . '.js') . ($v > 0 ? '?v=' . $v : '');
    }

    public static function spriteHref(): string
    {
        return self::layoutUrl('assets/adlexone.sprite.svg');
    }

    /**
     * Cache-busted URL for a shared illustration under layouts/assets/illustrations/.
     */
    public static function illustrationHref(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '', $name) ?? '';
        if ($name === '') {
            return '';
        }
        $file = $name . '.svg';
        $fs = self::layoutFs() . 'assets' . DIRECTORY_SEPARATOR . 'illustrations' . DIRECTORY_SEPARATOR . $file;
        if (!is_file($fs)) {
            return '';
        }
        $v = (int) @filemtime($fs);

        return self::layoutUrl('assets/illustrations/' . $file) . ($v > 0 ? '?v=' . $v : '');
    }

    public static function faviconHref(string $theme = ''): string
    {
        return self::url('brand/favicon.svg', $theme);
    }

    public static function imagePath(): string
    {
        return self::layoutUrl('images') . '/';
    }

    public static function label(string $theme = ''): string
    {
        $theme = self::canonicalize($theme !== '' ? $theme : self::name());
        $meta = self::meta($theme);
        if (trim((string) ($meta['label'] ?? '')) !== '') {
            return (string) $meta['label'];
        }

        return self::KNOWN_LABELS[$theme] ?? $theme;
    }

    /**
     * @return array<string, string> slug => label
     */
    public static function installed(): array
    {
        $themes = [];
        $root = self::rootFs();
        if (!is_dir($root)) {
            return $themes;
        }

        foreach (scandir($root) ?: [] as $name) {
            if ($name === '.' || $name === '..' || !preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
                continue;
            }
            if (!self::exists($name)) {
                continue;
            }
            $themes[$name] = self::label($name);
        }
        asort($themes, SORT_NATURAL | SORT_FLAG_CASE);

        return $themes;
    }

    public static function exists(string $theme): bool
    {
        $theme = self::canonicalize($theme);
        $dir = self::rootFs() . $theme;
        if (!is_dir($dir)) {
            return false;
        }
        $css = $dir . DIRECTORY_SEPARATOR . self::cssFile($theme);

        return is_file($css);
    }

    /**
     * @return array<string, mixed>
     */
    public static function meta(string $theme = ''): array
    {
        $theme = self::canonicalize($theme !== '' ? $theme : self::name());
        $file = self::rootFs() . $theme . DIRECTORY_SEPARATOR . 'theme.json';
        if (!is_file($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function pagePath(string $page): string
    {
        return self::layoutFs() . 'pages' . DIRECTORY_SEPARATOR . $page . '.php';
    }

    public static function partialPath(string $name): string
    {
        return self::layoutFs() . 'partials' . DIRECTORY_SEPARATOR . $name . '.php';
    }

    /** Accent colour from a skin, used by the theme picker. */
    public static function accent(string $theme): string
    {
        $theme = self::canonicalize($theme);
        $file = self::rootFs() . $theme . DIRECTORY_SEPARATOR . self::cssFile($theme);
        $css = is_file($file) ? (string) file_get_contents($file) : '';
        if (preg_match('/--inlay:\s*(#[0-9a-fA-F]{3,8})/', $css, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/--brand:\s*(#[0-9a-fA-F]{3,8})/', $css, $match) === 1) {
            return $match[1];
        }

        return '#1c1917';
    }

    public static function picker(string $name, string $current, bool $submitOnChange = false): string
    {
        $current = self::canonicalize($current);
        $change = $submitOnChange ? ' onchange="this.form.submit()"' : '';
        $html = '<div class="theme-swatches" role="radiogroup">';
        foreach (self::installed() as $slug => $label) {
            $checked = $slug === $current ? ' checked' : '';
            $active = $slug === $current ? ' is-selected' : '';
            $html .= '<label class="theme-swatch' . $active . '">'
                . '<input type="radio" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="'
                . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '"' . $checked . $change . '>'
                . '<span class="theme-swatch__chip" style="background:' . htmlspecialchars(self::accent($slug), ENT_QUOTES, 'UTF-8') . '"></span>'
                . '<span class="theme-swatch__name">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
                . '</label>';
        }

        return $html . '</div>';
    }
}
