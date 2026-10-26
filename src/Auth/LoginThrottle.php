<?php
declare(strict_types=1);

namespace Adlexone\Auth;

use Adlexone\support\Database;

/**
 * Slows down password guessing on the username-and-password sign-in form.
 *
 * Failures are counted per account name and per connecting address inside a
 * rolling window. Once the limit is reached that key is locked for a short
 * period, so a stolen password list cannot be tried quickly and one account is
 * not left open to an attacker who hammers a single name. A correct sign-in
 * clears the counters for the keys it used.
 */
final class LoginThrottle
{
    private const MAX_ATTEMPTS = 10;
    private const WINDOW_SECONDS = 900;
    private const LOCK_SECONDS = 900;

    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        Database::exec(
            'CREATE TABLE IF NOT EXISTS login_throttle (
                throttle_key TEXT PRIMARY KEY,
                attempts INTEGER NOT NULL DEFAULT 0,
                window_start INTEGER NOT NULL DEFAULT 0,
                locked_until INTEGER NOT NULL DEFAULT 0
            )'
        );
        self::$schemaReady = true;
    }

    /**
     * Keys to count a sign-in attempt against: the account name and the address.
     *
     * @return list<string>
     */
    public static function keysFor(string $username): array
    {
        $keys = [];
        $name = strtolower(trim($username));
        if ($name !== '') {
            $keys[] = 'user:' . $name;
        }
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip !== '') {
            $keys[] = 'ip:' . $ip;
        }
        return $keys;
    }

    /**
     * Seconds left on the longest active lock across the keys, or 0 when none is locked.
     *
     * @param list<string> $keys
     */
    public static function retryAfter(array $keys): int
    {
        if ($keys === []) {
            return 0;
        }
        self::ensureSchema();
        $now = time();
        $remaining = 0;
        foreach ($keys as $key) {
            $row = Database::first('login_throttle', ['locked_until'], 'throttle_key = ?', [$key]);
            $lockedUntil = (int) ($row['locked_until'] ?? 0);
            if ($lockedUntil > $now) {
                $remaining = max($remaining, $lockedUntil - $now);
            }
        }
        return $remaining;
    }

    /**
     * Record one failed sign-in against each key and lock a key that reaches the limit.
     *
     * @param list<string> $keys
     */
    public static function registerFailure(array $keys): void
    {
        if ($keys === []) {
            return;
        }
        self::ensureSchema();
        $now = time();
        foreach ($keys as $key) {
            Database::transaction(static function () use ($key, $now): void {
                $row = Database::first('login_throttle', ['attempts', 'window_start'], 'throttle_key = ?', [$key]);
                if ($row === null) {
                    Database::insert('login_throttle', [
                        'throttle_key' => $key,
                        'attempts' => 1,
                        'window_start' => $now,
                        'locked_until' => 0,
                    ]);
                    return;
                }
                $windowStart = (int) $row['window_start'];
                $attempts = (int) $row['attempts'];
                if ($now - $windowStart > self::WINDOW_SECONDS) {
                    $windowStart = $now;
                    $attempts = 0;
                }
                $attempts++;
                $lockedUntil = $attempts >= self::MAX_ATTEMPTS ? $now + self::LOCK_SECONDS : 0;
                Database::update(
                    'login_throttle',
                    ['attempts' => $attempts, 'window_start' => $windowStart, 'locked_until' => $lockedUntil],
                    'throttle_key = ?',
                    [$key]
                );
            });
        }
    }

    /**
     * Clear the counters after a correct sign-in.
     *
     * @param list<string> $keys
     */
    public static function clear(array $keys): void
    {
        if ($keys === []) {
            return;
        }
        self::ensureSchema();
        foreach ($keys as $key) {
            Database::delete('login_throttle', 'throttle_key = ?', [$key]);
        }
    }

    /**
     * A plain-language wait time for the locked message.
     */
    public static function waitMessage(int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);
        if ($minutes <= 1) {
            return 'Too many sign-in attempts. Please wait a minute and try again.';
        }
        return 'Too many sign-in attempts. Please wait about ' . $minutes . ' minutes and try again.';
    }
}
