<?php
declare(strict_types=1);

namespace Adlexone\Html;

use Adlexone\Application\ApplicationStore;
use Adlexone\Theme\Theme;

/**
 * Colour for an application and for a person's initials.
 *
 * An application keeps one accent so its tile, header, and charts read as one place.
 * The accent comes from the application's own `accent` setting when it is a hex colour.
 * Otherwise it follows the application's position in this palette, so the first ten
 * applications on a site never share a colour.
 */
final class Accent
{
    /**
     * Used when a theme does not set a usable --inlay colour.
     *
     * @var list<string>
     */
    private const FALLBACK = [
        '#2563eb',
        '#7c3aed',
        '#0d9488',
        '#d97706',
        '#e11d48',
        '#059669',
        '#0284c7',
        '#c026d3',
        '#ea580c',
        '#475569',
    ];

    private const SIZE = 10;

    /** @var array<string, list<string>> */
    private static array $palettes = [];

    /** @var array<string, string> */
    private static array $bySlug = [];

    /**
     * Ten accents for the active theme. The first is the theme colour. The rest turn
     * around the colour wheel at the same weight, so applications stay apart while
     * the whole set reads as part of the theme.
     *
     * @return list<string>
     */
    public static function palette(): array
    {
        $theme = Theme::name();
        if (isset(self::$palettes[$theme])) {
            return self::$palettes[$theme];
        }
        $base = self::themeColour($theme);

        return self::$palettes[$theme] = $base === null ? self::FALLBACK : self::derive($base);
    }

    /**
     * @return list<string>
     */
    private static function derive(string $base): array
    {
        [$hue, $sat, $light] = self::hsl($base);
        $sat = min(0.72, max(0.45, $sat));
        $light = min(0.42, max(0.34, $light));
        $palette = [$base];
        for ($i = 1; $i < self::SIZE; $i++) {
            $h = fmod($hue + 36 * $i, 360);
            $l = $h >= 45 && $h <= 170 ? $light - 0.04 : $light;
            $palette[] = self::hex($h, $sat, $l);
        }

        return $palette;
    }

    private static function themeColour(string $theme): ?string
    {
        $file = Theme::rootFs() . $theme . DIRECTORY_SEPARATOR . Theme::cssFile($theme);
        $css = is_file($file) ? @file_get_contents($file) : false;
        if (!is_string($css) || preg_match('/--inlay\s*:\s*(#[0-9a-fA-F]{6})\b/', $css, $match) !== 1) {
            return null;
        }

        return strtolower($match[1]);
    }

    /**
     * @return array{0: float, 1: float, 2: float} hue in degrees, saturation and lightness from 0 to 1
     */
    private static function hsl(string $hex): array
    {
        $r = hexdec(substr($hex, 1, 2)) / 255;
        $g = hexdec(substr($hex, 3, 2)) / 255;
        $b = hexdec(substr($hex, 5, 2)) / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $light = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $light];
        }
        $d = $max - $min;
        $sat = $light > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $hue = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$hue * 60, $sat, $light];
    }

    private static function hex(float $hue, float $sat, float $light): string
    {
        $c = (1 - abs(2 * $light - 1)) * $sat;
        $x = $c * (1 - abs(fmod($hue / 60, 2) - 1));
        $m = $light - $c / 2;
        [$r, $g, $b] = match ((int) floor($hue / 60) % 6) {
            0 => [$c, $x, 0.0],
            1 => [$x, $c, 0.0],
            2 => [0.0, $c, $x],
            3 => [0.0, $x, $c],
            4 => [$x, 0.0, $c],
            default => [$c, 0.0, $x],
        };

        return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }

    /**
     * @param array<string, mixed> $app a row from ApplicationStore
     */
    public static function forApp(array $app): string
    {
        $settings = $app['settings'] ?? null;
        if (!is_array($settings)) {
            $decoded = json_decode((string) ($app['settings_json'] ?? ''), true);
            $settings = is_array($decoded) ? $decoded : [];
        }
        $custom = strtolower(trim((string) ($settings['accent'] ?? '')));
        if (preg_match('/^#[0-9a-f]{6}$/', $custom) === 1) {
            return $custom;
        }
        $id = max(1, (int) ($app['application_id'] ?? 0));
        $palette = self::palette();

        return $palette[($id - 1) % count($palette)];
    }

    public static function forSlug(string $slug): string
    {
        if ($slug === '') {
            return self::palette()[0];
        }
        if (isset(self::$bySlug[$slug])) {
            return self::$bySlug[$slug];
        }
        $app = ApplicationStore::findBySlug($slug);

        $palette = self::palette();

        return self::$bySlug[$slug] = is_array($app)
            ? self::forApp($app)
            : $palette[abs(crc32($slug)) % count($palette)];
    }

    /**
     * Classes that set this colour as the accent for the element and its children.
     * An empty string means the colour is not a hex value the stylesheet can apply.
     */
    public static function classes(string $colour): string
    {
        $channels = self::channels($colour);

        return $channels === '' ? '' : 'has-accent ' . $channels;
    }

    /**
     * Classes that set the red, green, and blue channels of a hex colour.
     * The theme picker uses these for a swatch. An accent also adds has-accent.
     */
    public static function channels(string $colour): string
    {
        $rgb = self::rgb($colour);
        if ($rgb === null) {
            return '';
        }

        return 'cr-' . $rgb[0] . ' cg-' . $rgb[1] . ' cb-' . $rgb[2];
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function rgb(string $colour): ?array
    {
        $colour = strtolower(trim($colour));
        if (preg_match('/^#([0-9a-f]{3,8})$/', $colour, $match) !== 1) {
            return null;
        }
        $hex = $match[1];
        $length = strlen($hex);
        if ($length === 3 || $length === 4) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]
                . ($length === 4 ? $hex[3] . $hex[3] : '');
        }
        if (strlen($hex) !== 6 && strlen($hex) !== 8) {
            return null;
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    public static function hue(string $name): int
    {
        return abs(crc32(strtolower(trim($name)))) % 360;
    }
}
