<?php
declare(strict_types=1);

namespace Adlexone\Platform;

/**
 * First operator and first application administrator.
 */
final class DefaultAccount
{
    public const USERNAME = 'admin';
    public const PASSWORD = 'admin';

    public static function username(): string
    {
        return self::USERNAME;
    }

    public static function password(): string
    {
        return self::PASSWORD;
    }

    public static function postedUsername(?string $posted): string
    {
        $posted = trim((string) $posted);
        return $posted !== '' ? $posted : self::USERNAME;
    }

    public static function postedPassword(?string $posted): string
    {
        return $posted !== null && $posted !== '' ? $posted : self::PASSWORD;
    }

    public static function assertPassword(string $password, string $what = 'password'): void
    {
        if ($password === self::PASSWORD) {
            return;
        }
        if (strlen($password) < 8) {
            throw new \RuntimeException('Use a ' . $what . ' of at least 8 characters, or use ' . self::PASSWORD . '.');
        }
    }
}
