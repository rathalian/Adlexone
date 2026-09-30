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
        'new' => 'Inlay',
        'inlay-blue' => 'Inlay Blue',
    ];

    public static function name(): string
    {
        if (defined('SET_THEME') && (string) SET_THEME !== '') {
            return (string) SET_THEME;
        }
        if (defined('SET_DEFAULT_THEME') && (string) SET_DEFAULT_THEME !== '') {
            return (string) SET_DEFAULT_THEME;
        }

        return 'new';
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
        $theme = $theme !== '' ? $theme : self::name();
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
        $theme = $theme !== '' ? $theme : self::name();
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
        $theme = $theme !== '' ? $theme : self::name();
        $file = self::cssFile($theme);
        $fs = self::rootFs() . $theme . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
        $v = is_file($fs) ? (int) @filemtime($fs) : 0;

        return self::url($file, $theme) . ($v > 0 ? '?v=' . $v : '');
    }

    public static function jsHref(): string
    {
        $fs = self::layoutFs() . 'js' . DIRECTORY_SEPARATOR . 'app.js';
        $v = is_file($fs) ? (int) @filemtime($fs) : 0;

        return self::layoutUrl('js/app.js') . ($v > 0 ? '?v=' . $v : '');
    }

    public static function spriteHref(): string
    {
        return self::layoutUrl('assets/adlexone.sprite.svg');
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
        $theme = $theme !== '' ? $theme : self::name();
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
        $theme = $theme !== '' ? $theme : self::name();
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
}
