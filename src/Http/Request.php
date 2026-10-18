<?php
declare(strict_types=1);

namespace Adlexone\Http;

/**
 * The query string of the current request.
 *
 * Router and the application layer read and rewrite parameters here only.
 * Screens that were written against the older engines still read the query
 * through $_GET, so every write goes through to it. Nothing else assigns
 * to $_GET.
 */
final class Request
{
    public static function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public static function has(string $key): bool
    {
        return self::query($key) !== '';
    }

    public static function set(string $key, string $value): void
    {
        $_GET[$key] = $value;
    }

    /**
     * Set a parameter only when the request left it empty.
     */
    public static function fill(string $key, string $value): void
    {
        if (!self::has($key)) {
            self::set($key, $value);
        }
    }

    /**
     * Fold the older parameter names into the ones the screens read. Safe to repeat.
     */
    public static function prepare(): void
    {
        if (self::has('application')) {
            self::fill('app', self::query('application'));
        }
    }
}
