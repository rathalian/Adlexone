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

    /**
     * True while the default administrator still signs in with the default password.
     * The sign-in page pre-fills the form only in that state.
     */
    public static function stillInUse(): bool
    {
        try {
            $row = \Adlexone\support\Database::first('users', ['password'], 'user_name = ?', [self::USERNAME]);
        } catch (\Throwable) {
            return false;
        }
        $hash = (string) ($row['password'] ?? '');

        return $hash !== '' && password_verify(self::PASSWORD, $hash);
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
