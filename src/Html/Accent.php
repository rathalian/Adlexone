<?php
declare(strict_types=1);

namespace Adlexone\Html;

use Adlexone\Application\ApplicationStore;

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
    /** @var list<string> */
    private const PALETTE = [
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

    /** @var array<string, string> */
    private static array $bySlug = [];

    /**
     * @return list<string>
     */
    public static function palette(): array
    {
        return self::PALETTE;
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

        return self::PALETTE[($id - 1) % count(self::PALETTE)];
    }

    public static function forSlug(string $slug): string
    {
        if ($slug === '') {
            return self::PALETTE[0];
        }
        if (isset(self::$bySlug[$slug])) {
            return self::$bySlug[$slug];
        }
        $app = ApplicationStore::findBySlug($slug);

        return self::$bySlug[$slug] = is_array($app)
            ? self::forApp($app)
            : self::PALETTE[abs(crc32($slug)) % count(self::PALETTE)];
    }

    /** Inline style declaration that sets the accent for the element and its children. */
    public static function style(string $colour): string
    {
        return '--accent:' . $colour . ';';
    }

    public static function hue(string $name): int
    {
        return abs(crc32(strtolower(trim($name)))) % 360;
    }
}
