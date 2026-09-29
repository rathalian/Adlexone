<?php
declare(strict_types=1);

namespace Adlexone\Auth;

use Adlexone\support\Database;

final class LocalAuthenticator
{
    /**
     * @return array<string, mixed>
     */
    public static function authenticate(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new AuthException(defined('TXT_219') ? TXT_219 : 'Invalid username or password.');
        }

        $user = Database::first('users', '*', 'user_name = ?', [$username]);

        if ($user === null || !self::passwordMatches((string) ($user['password'] ?? ''), $password, (int) $user['user_id'])) {
            throw new AuthException(defined('TXT_219') ? TXT_219 : 'Invalid username or password.');
        }

        return $user;
    }

    private static function passwordMatches(string $stored, string $password, int $userId): bool
    {
        if ($stored !== '' && str_starts_with($stored, '$') && password_verify($password, $stored)) {
            if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                self::storeHash($userId, password_hash($password, PASSWORD_DEFAULT));
            }
            return true;
        }

        // Legacy MD5 hashes from older Adlexone installs.
        if (strlen($stored) === 32 && ctype_xdigit($stored) && hash_equals(strtolower($stored), md5($password))) {
            self::storeHash($userId, password_hash($password, PASSWORD_DEFAULT));
            return true;
        }

        return false;
    }

    private static function storeHash(int $userId, string $hash): void
    {
        Database::update('users', ['password' => $hash], 'user_id = ?', [$userId]);
    }
}
