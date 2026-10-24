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

    /**
     * The default password is for a machine in front of the person setting it up: the command line,
     * localhost, a private address, or a .local, .localhost or .test name. Any other host must set a
     * real password. INLAY_ALLOW_DEFAULT_ACCOUNT=1 lifts the limit for a trusted network.
     */
    public static function allowed(): bool
    {
        if (getenv('INLAY_ALLOW_DEFAULT_ACCOUNT') === '1') {
            return true;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return true;
        }
        $host = strtolower(trim((string) preg_replace('/:\d+$/', '', trim($host, '[]'))));
        if ($host === 'localhost' || $host === '::1' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }

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

        return self::allowed() && $hash !== '' && password_verify(self::PASSWORD, $hash);
    }

    public static function postedUsername(?string $posted): string
    {
        $posted = trim((string) $posted);
        return $posted !== '' ? $posted : self::USERNAME;
    }

    public static function postedPassword(?string $posted): string
    {
        if ($posted !== null && $posted !== '') {
            return $posted;
        }

        return self::allowed() ? self::PASSWORD : '';
    }

    public static function assertPassword(string $password, string $what = 'password'): void
    {
        if (self::allowed()) {
            if ($password === self::PASSWORD) {
                return;
            }
            if (strlen($password) < 8) {
                throw new \RuntimeException('Use a ' . $what . ' of at least 8 characters, or use ' . self::PASSWORD . '.');
            }

            return;
        }
        if (strlen($password) < 12 || strtolower($password) === self::PASSWORD) {
            throw new \RuntimeException('Use a ' . $what . ' of at least 12 characters. The default is only for localhost and private addresses.');
        }
    }
}

