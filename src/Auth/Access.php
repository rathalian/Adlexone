<?php
declare(strict_types=1);

namespace Adlexone\Auth;

use Adlexone\Database\Database;

/**
 * Permission-based access control.
 *
 * A person's permissions are the union of the roles on every group they
 * belong to. One break-glass account receives every permission.
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
        Role::seedSystem();
        self::$ready = true;
    }

    public static function ensureSchema(): void
    {
        \Adlexone\Database\SchemaMigrator::migrate();
        Database::exec(
            'CREATE TABLE IF NOT EXISTS group_permissions (
                group_id INTEGER NOT NULL,
                permission TEXT NOT NULL,
                PRIMARY KEY (group_id, permission)
            )'
        );
    }

    /**
     * Load permissions into the session.
     */
    public static function hydrateSession(int $userId): void
    {
        self::ensureReady();
        $permissions = self::permissionsForUser($userId);
        $_SESSION['access_permissions'] = $permissions;
        unset($_SESSION['access_role_id']);
        $identity = Database::first('users', ['user_name', 'first_name', 'last_name', 'theme'], 'user_id = ?', [$userId]);
        if ($identity !== null) {
            $_SESSION['access_user_name'] = (string) ($identity['user_name'] ?? '');
            $display = trim(trim((string) ($identity['first_name'] ?? '')) . ' ' . trim((string) ($identity['last_name'] ?? '')));
            $_SESSION['access_display_name'] = $display !== '' ? $display : (string) ($identity['user_name'] ?? '');
            $theme = trim((string) ($identity['theme'] ?? ''));
            if ($theme !== '') {
                $_SESSION['access_theme'] = $theme;
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function permissionsForUser(int $userId): array
    {
        self::ensureReady();

        $user = Database::first('users', ['break_glass'], 'user_id = ?', [$userId]);
        if ($user === null) {
            return [];
        }

        if ((int) ($user['break_glass'] ?? 0) === 1) {
            return Permission::all();
        }

        $granted = [];

        foreach (self::groupIdsForUser($userId) as $groupId) {
            foreach (Role::permissionsForGroup($groupId) as $permission) {
                $granted[$permission] = true;
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
        return \Adlexone\Data\GroupMembership::userGroupIds($userId);
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

    /**
     * Return $value when the user has at least one permission, otherwise null.
     */
    public static function outputIfAllowed(mixed $value, string ...$permissions): mixed
    {
        return self::can(...$permissions) ? $value : null;
    }

    public static function deny(): never
    {
        $message = defined('TXT_356')
            ? TXT_356
            : 'You do not have access to this area. Please contact your administrator.';
        \Adlexone\Http\View::respond($message);
        \Adlexone\Http\Page::current()->send();
        exit;
    }

    public static function canEditRecords(): bool
    {
        return self::can(Permission::RECORDS_EDIT);
    }

    public static function canAssignRecords(): bool
    {
        return self::can(Permission::RECORDS_ASSIGN);
    }

    public static function canControlRecords(): bool
    {
        return self::can(Permission::RECORDS_CONTROL);
    }

    public static function canAdministerSite(): bool
    {
        return self::can(
            Permission::ADMIN_SYSTEM,
            Permission::ADMIN_SETTINGS,
            Permission::ADMIN_SECURITY,
            Permission::ADMIN_ACTIONS,
            Permission::ADMIN_ITEMS
        );
    }

    public static function requireEditRecords(): void
    {
        if (!self::canEditRecords()) {
            self::deny();
        }
    }

    public static function requireAssignRecords(): void
    {
        if (!self::canAssignRecords()) {
            self::deny();
        }
    }

    public static function requireControlRecords(): void
    {
        if (!self::canControlRecords()) {
            self::deny();
        }
    }

    /**
     * @return list<string>
     */
    public static function permissionsForGroup(int $groupId): array
    {
        self::ensureReady();

        return Role::permissionsForGroup($groupId);
    }

    /**
     * Replace all permissions for a group.
     *
     * @param list<string> $permissions
     */
    public static function setGroupPermissions(int $groupId, array $permissions): void
    {
        self::ensureReady();
        $group = Database::first('groups', ['group_name'], 'group_id = ?', [$groupId]);
        Role::assign($groupId, $permissions, (string) ($group['group_name'] ?? ''));
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
