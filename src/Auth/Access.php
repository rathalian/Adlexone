<?php
declare(strict_types=1);

namespace Adlexone\Auth;

use Adlexone\support\Database;

/**
 * Permission-based access control.
 *
 * Groups grant named permissions. A user's effective set is the union of
 * permissions from every group they belong to. A user record with role 0
 * remains a break-glass superuser and receives every permission.
 *
 * access_role_id in the session is derived from permissions only so older
 * role-number checks keep working during migration.
 */
final class Access
{
    private static bool $ready = false;

    public static function ensureReady(): void
    {
        if (self::$ready) {
            return;
        }
        self::ensureSchema();
        self::seedIfEmpty();
        self::$ready = true;
    }

    public static function ensureSchema(): void
    {
        Database::exec(
            'CREATE TABLE IF NOT EXISTS group_permissions (
                group_id INTEGER NOT NULL,
                permission TEXT NOT NULL,
                PRIMARY KEY (group_id, permission)
            )'
        );
        Database::exec(
            'CREATE TABLE IF NOT EXISTS user_permissions (
                user_id INTEGER NOT NULL,
                permission TEXT NOT NULL,
                PRIMARY KEY (user_id, permission)
            )'
        );
    }

    /**
     * First-time seed from legacy groups.role values.
     * Administrator group always receives the full admin set.
     */
    public static function seedIfEmpty(): void
    {
        $existing = Database::firstResultParams('SELECT COUNT(*) AS c FROM group_permissions', []);
        if ($existing !== null && (int) ($existing['c'] ?? 0) > 0) {
            return;
        }

        $groups = Database::buildArray('SELECT group_id, group_name, role FROM groups');
        foreach ($groups as $group) {
            $groupId = (int) $group['group_id'];
            $legacyRole = (int) ($group['role'] ?? 5);
            $name = strtolower((string) ($group['group_name'] ?? ''));

            $permissions = Permission::forLegacyRole($legacyRole);
            if ($name === 'administrator' || $groupId === 3) {
                $permissions = Permission::all();
            }

            foreach ($permissions as $permission) {
                Database::queryParams(
                    'INSERT OR IGNORE INTO group_permissions (group_id, permission) VALUES (?, ?)',
                    [$groupId, $permission]
                );
            }
        }
    }

    /**
     * Load permissions into the session and refresh derived legacy role.
     */
    public static function hydrateSession(int $userId): void
    {
        self::ensureReady();
        $permissions = self::permissionsForUser($userId);
        $_SESSION['access_permissions'] = $permissions;
        $_SESSION['access_role_id'] = self::legacyRoleFromPermissions($permissions);
        $identity = Database::firstResultParams(
            'SELECT user_name FROM users WHERE user_id = ?',
            [$userId]
        );
        if ($identity !== null) {
            $_SESSION['access_user_name'] = (string) ($identity['user_name'] ?? '');
        }
    }

    /**
     * @return list<string>
     */
    public static function permissionsForUser(int $userId): array
    {
        self::ensureReady();

        $user = Database::firstResultParams(
            'SELECT role FROM users WHERE user_id = ?',
            [$userId]
        );
        if ($user === null) {
            return [];
        }

        // Break-glass: legacy global administrator on the user record.
        if ((int) ($user['role'] ?? 5) === 0) {
            return Permission::all();
        }

        $granted = [];

        $directResult = Database::queryParams(
            'SELECT permission FROM user_permissions WHERE user_id = ?',
            [$userId]
        );
        while ($row = Database::fetchArray($directResult)) {
            $granted[$row['permission']] = true;
        }

        foreach (self::groupIdsForUser($userId) as $groupId) {
            $result = Database::queryParams(
                'SELECT permission FROM group_permissions WHERE group_id = ?',
                [$groupId]
            );
            while ($row = Database::fetchArray($result)) {
                $granted[$row['permission']] = true;
            }
        }

        // Everyone who can sign in gets baseline app access.
        $granted[Permission::APP_ACCESS] = true;

        $list = array_keys($granted);
        sort($list);
        return $list;
    }

    /**
     * @return list<int>
     */
    public static function groupIdsForUser(int $userId): array
    {
        $member = Database::firstResultParams(
            'SELECT groups FROM group_members WHERE user_id = ?',
            [$userId]
        );
        if ($member === null || empty($member['groups'])) {
            return [];
        }

        $ids = [];
        $parts = preg_split('/\}-\{/', (string) $member['groups']) ?: [];
        foreach ($parts as $part) {
            $id = trim($part, " \t\n\r\0\x0B{}-");
            if ($id !== '' && ctype_digit($id)) {
                $ids[] = (int) $id;
            }
        }
        return array_values(array_unique($ids));
    }

    public static function can(string ...$permissions): bool
    {
        if ($permissions === []) {
            return false;
        }
        $have = self::currentPermissions();
        if ($have === []) {
            return false;
        }
        foreach ($permissions as $permission) {
            if (in_array($permission, $have, true)) {
                return true;
            }
        }
        return false;
    }

    public static function canAll(string ...$permissions): bool
    {
        if ($permissions === []) {
            return false;
        }
        $have = self::currentPermissions();
        foreach ($permissions as $permission) {
            if (!in_array($permission, $have, true)) {
                return false;
            }
        }
        return true;
    }

    public static function require(string ...$permissions): void
    {
        if (!self::can(...$permissions)) {
            self::deny();
        }
    }

    public static function deny(): never
    {
        $message = defined('TXT_356')
            ? TXT_356
            : 'You do not have access to this area. Please contact your administrator.';
        \Adlexone\support\RenderViews::buildResponse($message);
        exit;
    }

    /**
     * Derived legacy role for older checks. Lower = more privilege.
     */
    public static function legacyRole(): int
    {
        if (isset($_SESSION['access_role_id'])) {
            return (int) $_SESSION['access_role_id'];
        }
        return self::legacyRoleFromPermissions(self::currentPermissions());
    }

    /**
     * @param list<string> $permissions
     */
    public static function legacyRoleFromPermissions(array $permissions): int
    {
        if ($permissions === []) {
            return 5;
        }
        $lookup = array_fill_keys($permissions, true);
        $best = 5;
        foreach (Permission::catalog() as $key => $meta) {
            if (!isset($lookup[$key])) {
                continue;
            }
            $role = (int) $meta['legacy_max_role'];
            if ($role < $best) {
                $best = $role;
            }
        }
        return $best;
    }

    /**
     * @return list<string>
     */
    public static function permissionsForGroup(int $groupId): array
    {
        self::ensureReady();
        $result = Database::queryParams(
            'SELECT permission FROM group_permissions WHERE group_id = ? ORDER BY permission',
            [$groupId]
        );
        $list = [];
        while ($row = Database::fetchArray($result)) {
            $list[] = (string) $row['permission'];
        }
        return $list;
    }

    /**
     * Replace all permissions for a group.
     *
     * @param list<string> $permissions
     */
    public static function setGroupPermissions(int $groupId, array $permissions): void
    {
        self::ensureReady();
        Database::queryParams('DELETE FROM group_permissions WHERE group_id = ?', [$groupId]);
        $allowed = array_fill_keys(Permission::all(), true);
        foreach ($permissions as $permission) {
            $permission = (string) $permission;
            if (!isset($allowed[$permission])) {
                continue;
            }
            Database::queryParams(
                'INSERT OR IGNORE INTO group_permissions (group_id, permission) VALUES (?, ?)',
                [$groupId, $permission]
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function currentPermissions(): array
    {
        if (!empty($_SESSION['access_permissions']) && is_array($_SESSION['access_permissions'])) {
            return array_values($_SESSION['access_permissions']);
        }
        if (!empty($_SESSION['access_user_id'])) {
            self::hydrateSession((int) $_SESSION['access_user_id']);
            return array_values($_SESSION['access_permissions'] ?? []);
        }
        return [];
    }
}
