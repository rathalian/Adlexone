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

    /**
     * A posted scalar, trimmed. A missing key or a list is the default.
     */
    public static function posted(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * A query value encoded for a link.
     */
    public static function queryPart(string $key): string
    {
        return rawurlencode(self::query($key));
    }

    public static function has(string $key): bool
    {
        return self::query($key) !== '';
    }

    /**
     * The query value as sent. A missing key is null. A string is trimmed.
     * A list is returned unchanged.
     */
    public static function queryValue(string $key): mixed
    {
        if (!array_key_exists($key, $_GET)) {
            return null;
        }
        $value = $_GET[$key];

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * The posted value as sent. A missing key is null. A string is trimmed.
     * A list is returned unchanged.
     */
    public static function postedValue(string $key): mixed
    {
        if (!array_key_exists($key, $_POST)) {
            return null;
        }
        $value = $_POST[$key];

        return is_string($value) ? trim($value) : $value;
    }

    public static function hasQueryKey(string $key): bool
    {
        return array_key_exists($key, $_GET);
    }

    public static function hasPostedKey(string $key): bool
    {
        return array_key_exists($key, $_POST);
    }

    /**
     * @return array<string, mixed>
     */
    public static function queryAll(): array
    {
        return $_GET;
    }

    /**
     * @return array<string, mixed>
     */
    public static function postedAll(): array
    {
        return $_POST;
    }

    public static function blankQuery(string $key): bool
    {
        return empty($_GET[$key]);
    }

    public static function blankPosted(string $key): bool
    {
        return empty($_POST[$key]);
    }

    public static function setPosted(string $key, mixed $value): void
    {
        $_POST[$key] = $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fillPosted(array $values): void
    {
        $_POST = $values;
    }

    public static function forgetPosted(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($_POST[$key]);
        }
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
     * Copy application onto app for screens that still read that name. Safe to repeat.
     */
    public static function prepare(): void
    {
        if (self::has('application')) {
            self::fill('app', self::query('application'));
        }
    }
}
