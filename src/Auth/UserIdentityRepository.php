<?php
declare(strict_types=1);

namespace Adlexone\Auth;

use Adlexone\support\Database;

/**
 * Links an OAuth (OpenID Connect) sign-in to a local Adlexone user.
 */
final class UserIdentityRepository
{
    public static function ensureSchema(): void
    {
        Database::exec(
            'CREATE TABLE IF NOT EXISTS user_identities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                provider TEXT NOT NULL,
                subject TEXT NOT NULL,
                email TEXT,
                created_at INTEGER NOT NULL,
                UNIQUE(provider, subject)
            )'
        );
    }

    /**
     * @param array<string, mixed> $claims
     * @return array<string, mixed>
     */
    public static function resolveUser(OauthConnection $connection, array $claims): array
    {
        self::ensureSchema();

        $subject = self::subjectFromClaims($claims);
        $email = strtolower(trim((string) ($claims['email'] ?? $claims['preferred_username'] ?? '')));

        $identity = Database::first('user_identities', '*', 'provider = ? AND subject = ?', [$connection->id, $subject]);

        if ($identity !== null) {
            $user = Database::first('users', '*', 'user_id = ?', [(int) $identity['user_id']]);
            if ($user === null) {
                throw new AuthException('The linked Adlexone account no longer exists.');
            }
            if ($email !== '') {
                Database::update('user_identities', ['email' => $email], 'id = ?', [(int) $identity['id']]);
            }
            return $user;
        }

        $user = null;
        if ($email !== '') {
            $user = Database::first('users', '*', 'lower(email) = ?', [$email]);
        }

        if ($user === null) {
            if (!$connection->autoProvision) {
                throw new AuthException('No Adlexone account is linked for this OAuth sign-in. Ask an administrator for access.');
            }
            $user = self::provisionUser($claims, $email);
        }

        Database::insert('user_identities', [
            'user_id' => (int) $user['user_id'],
            'provider' => $connection->id,
            'subject' => $subject,
            'email' => $email !== '' ? $email : null,
            'created_at' => time(),
        ]);

        return $user;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private static function subjectFromClaims(array $claims): string
    {
        // Prefer oid when present (common on Microsoft directories); otherwise use sub.
        $subject = trim((string) ($claims['oid'] ?? $claims['sub'] ?? ''));
        if ($subject === '') {
            throw new AuthException('OAuth sign-in did not include a stable user id.');
        }
        return $subject;
    }

    /**
     * @param array<string, mixed> $claims
     * @return array<string, mixed>
     */
    private static function provisionUser(array $claims, string $email): array
    {
        $given = trim((string) ($claims['given_name'] ?? ''));
        $family = trim((string) ($claims['family_name'] ?? ''));
        $name = trim((string) ($claims['name'] ?? ''));
        if ($given === '' && $name !== '') {
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $given = $parts[0] ?? 'User';
            $family = $parts[1] ?? '';
        }
        if ($given === '') {
            $given = 'User';
        }

        $baseUsername = $email !== '' ? strtok($email, '@') : ('user' . substr(md5($claims['sub'] ?? uniqid('', true)), 0, 8));
        $username = self::uniqueUsername((string) $baseUsername);

        $defaultApplication = defined('SET_DEFAULT_APPLICATION') ? (string) SET_DEFAULT_APPLICATION : 'home}-{Home';
        $defaultApplicationArray = explode('}-{', $defaultApplication);

        $user = [
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'first_name' => $given,
            'last_name' => $family,
            'user_name' => $username,
            'email' => $email,
            'theme' => defined('SET_DEFAULT_THEME') ? SET_DEFAULT_THEME : 'inlay-stone',
            'language' => defined('SET_DEFAULT_LANGUAGE') ? SET_DEFAULT_LANGUAGE : 'English',
            'show_header' => 'Yes',
            'show_graphics' => 'Yes',
            'role' => 4,
            'home_controller' => $defaultApplicationArray[0] ?? 'home',
            'home_controller_name' => $defaultApplicationArray[1] ?? 'Home',
            'lastactive' => 'active',
        ];

        $userId = Database::insert('users', $user);
        $user['user_id'] = $userId;

        if (defined('USER_REG_ACTION') && USER_REG_ACTION !== '' && USER_REG_ACTION !== 'None') {
            $groupIds = \Adlexone\Data\GroupMembership::parseDelimited((string) USER_REG_ACTION);
            if ($groupIds === [] && ctype_digit((string) USER_REG_ACTION)) {
                $groupIds = [(int) USER_REG_ACTION];
            }
            \Adlexone\Data\GroupMembership::setUserGroups($userId, $groupIds);
        }

        $created = Database::first('users', '*', 'user_id = ?', [$userId]);
        if ($created === null) {
            throw new AuthException('Failed to create local account.');
        }
        return $created;
    }

    private static function uniqueUsername(string $base): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]/', '', $base) ?: 'user';
        $candidate = $base;
        $i = 1;
        while (Database::first('users', ['user_id'], 'user_name = ?', [$candidate]) !== null) {
            $candidate = $base . $i;
            $i++;
        }
        return $candidate;
    }
}
