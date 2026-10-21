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
     * Parameters that always hold a record number. Older screens place them straight into SQL,
     * so anything that is not a plain whole number is dropped before a screen reads it.
     *
     * @var list<string>
     */
    private const NUMBER_KEYS = [
        'item_id', 'item_type_id', 'user_id', 'group_id', 'custom_field_id', 'menu_value_id',
        'parent_menu_value_id', 'log_item_sequence', 'id', 'nav_id', 'rule_id', 'application_id',
        'search_id', 'pageset', 'default_item_type',
    ];

    public static function normaliseNumbers(): void
    {
        foreach ([&$_GET, &$_POST] as &$source) {
            foreach (self::NUMBER_KEYS as $key) {
                if (!array_key_exists($key, $source)) {
                    continue;
                }
                $value = $source[$key];
                $source[$key] = is_string($value) && preg_match('/^\d{1,18}$/', $value) === 1 ? $value : '';
            }
        }
        unset($source);
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
