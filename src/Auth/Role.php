<?php
declare(strict_types=1);

namespace Adlexone\Auth;

use Adlexone\Database\Database;

/**
 * Named roles assigned to groups.
 *
 * A person has no role of their own. Their permissions are the union of the
 * roles on the groups they belong to. One user row can be the break-glass
 * administrator, which is a flag rather than a role.
 */
final class Role
{
    private static bool $seeding = false;

    public const SITE_ADMINISTRATOR = 'site-administrator';
    public const ADMINISTRATOR = 'administrator';
    public const MANAGER = 'manager';
    public const USER = 'user';
    public const VIEWER = 'viewer';

    /**
     * The five roles that replace the old 1–5 ladder.
     * Sort matches the old number: a lower sort is a stronger role.
     *
     * @return array<string, array{name: string, sort: int}>
     */
    public static function systemCatalog(): array
    {
        return [
            self::SITE_ADMINISTRATOR => ['name' => 'Site administrator', 'sort' => 1],
            self::ADMINISTRATOR => ['name' => 'Administrator', 'sort' => 2],
            self::MANAGER => ['name' => 'Manager', 'sort' => 3],
            self::USER => ['name' => 'User', 'sort' => 4],
            self::VIEWER => ['name' => 'Viewer', 'sort' => 5],
        ];
    }

    public static function seedSystem(): void
    {
        if (self::$seeding) {
            return;
        }
        self::$seeding = true;
        try {
            self::seedSystemRows();
        } finally {
            self::$seeding = false;
        }
    }

    private static function seedSystemRows(): void
    {
        self::ensureTables();
        foreach (self::systemCatalog() as $slug => $meta) {
            $sort = (int) $meta['sort'];
            Database::insertIgnore('roles', [
                'role_id' => $sort,
                'slug' => $slug,
                'name' => (string) $meta['name'],
                'system' => 1,
                'sort_order' => $sort,
            ]);
            $roleId = self::id($slug);
            foreach (Permission::forLegacyRole($sort) as $permission) {
                Database::insertIgnore('role_permissions', [
                    'role_id' => $roleId,
                    'permission' => $permission,
                ]);
            }
        }
    }

    /**
     * Copy each group's current grants onto a role, then mark one break-glass account.
     * The old number columns are still present when this runs.
     */
    public static function migrateLegacyGroups(): void
    {
        self::seedSystem();
        if (!Database::tableExists('groups')) {
            self::markBreakGlass();
            return;
        }
        $hasRole = Database::columnExists('groups', 'role');
        $hasName = Database::columnExists('groups', 'group_name');
        $columns = ['group_id'];
        if ($hasRole) {
            $columns[] = 'role';
        }
        if ($hasName) {
            $columns[] = 'group_name';
        }
        foreach (Database::select('groups', $columns) as $group) {
            $groupId = (int) $group['group_id'];
            $number = $hasRole ? (int) ($group['role'] ?? 5) : 5;
            $name = $hasName ? (string) ($group['group_name'] ?? '') : '';
            $stored = self::storedGroupPermissions($groupId);
            $implied = Permission::forLegacyRole($number);
            if (strtolower($name) === 'administrator' || $groupId === 3) {
                $implied = Permission::all();
            }
            $impliedRecords = array_values(array_filter(
                $implied,
                static fn (string $permission): bool => str_starts_with($permission, 'records.')
            ));
            $perms = ($stored === [] || self::sameList($stored, $impliedRecords)) ? $implied : $stored;
            self::assign($groupId, $perms, $name);
        }
        self::markBreakGlass();
    }

    public static function dropLegacyColumns(): void
    {
        if (Database::tableExists('users') && Database::columnExists('users', 'role')) {
            Database::exec('ALTER TABLE users DROP COLUMN role');
        }
        if (Database::tableExists('groups') && Database::columnExists('groups', 'role')) {
            Database::exec('ALTER TABLE groups DROP COLUMN role');
        }
    }

    public static function id(string $slug): int
    {
        self::seedSystem();
        if (!Database::tableExists('roles')) {
            return 0;
        }
        $row = Database::first('roles', ['role_id'], 'slug = ?', [$slug]);

        return $row === null ? 0 : (int) $row['role_id'];
    }

    /**
     * @return list<array{role_id: int, name: string}>
     */
    public static function formChoices(int $groupId): array
    {
        self::seedSystem();
        $rows = [];
        foreach (Database::select('roles', ['role_id', 'name'], 'system = 1', [], 'sort_order ASC') as $row) {
            $rows[] = ['role_id' => (int) $row['role_id'], 'name' => (string) $row['name']];
        }
        if ($groupId > 0) {
            $custom = Database::first('roles', ['role_id', 'name'], 'slug = ?', [self::customSlug($groupId)]);
            if ($custom !== null) {
                $rows[] = ['role_id' => (int) $custom['role_id'], 'name' => (string) $custom['name']];
            }
        }

        return $rows;
    }

    public static function nameForGroup(int $groupId): string
    {
        $group = Database::first('groups', ['role_id'], 'group_id = ?', [$groupId]);
        if ($group === null) {
            return '';
        }
        $role = Database::first('roles', ['name'], 'role_id = ?', [(int) $group['role_id']]);

        return $role === null ? '' : (string) $role['name'];
    }

    /**
     * Point the group at a system role when the ticks match one.
     * Otherwise keep a custom role that belongs to this group.
     *
     * @param list<string> $permissions
     */
    public static function assign(int $groupId, array $permissions, string $groupName): void
    {
        self::seedSystem();
        $wanted = self::known($permissions);
        foreach (Database::select('roles', ['role_id'], 'system = 1', [], 'sort_order ASC') as $row) {
            $roleId = (int) $row['role_id'];
            if (self::sameGrants($wanted, self::permissions($roleId))) {
                self::point($groupId, $roleId);
                self::dropCustom($groupId);
                return;
            }
        }
        $slug = self::customSlug($groupId);
        $name = trim($groupName) === '' ? 'Custom' : 'Custom — ' . trim($groupName);
        $sort = self::audience($wanted);
        $existing = Database::first('roles', ['role_id'], 'slug = ?', [$slug]);
        if ($existing === null) {
            $roleId = Database::insert('roles', [
                'slug' => $slug,
                'name' => $name,
                'system' => 0,
                'sort_order' => $sort,
            ]);
        } else {
            $roleId = (int) $existing['role_id'];
            Database::update('roles', ['name' => $name, 'sort_order' => $sort], 'role_id = ?', [$roleId]);
            Database::delete('role_permissions', 'role_id = ?', [$roleId]);
        }
        foreach ($wanted as $permission) {
            Database::insertIgnore('role_permissions', [
                'role_id' => $roleId,
                'permission' => $permission,
            ]);
        }
        self::point($groupId, $roleId);
    }

    /**
     * @return list<string>
     */
    public static function permissions(int $roleId): array
    {
        if ($roleId <= 0 || !Database::tableExists('role_permissions')) {
            return [];
        }
        $list = [];
        foreach (Database::select('role_permissions', ['permission'], 'role_id = ?', [$roleId], 'permission') as $row) {
            $list[] = (string) $row['permission'];
        }

        return $list;
    }

    /**
     * @return list<string>
     */
    public static function permissionsForGroup(int $groupId): array
    {
        if (!Database::tableExists('groups') || !Database::columnExists('groups', 'role_id')) {
            return [];
        }
        $group = Database::first('groups', ['role_id'], 'group_id = ?', [$groupId]);
        if ($group === null) {
            return [];
        }

        return self::permissions((int) $group['role_id']);
    }

    /**
     * Record power of a permission set. Administrator permissions do not
     * widen this. 2 deletes, 3 assigns, 4 edits, 5 views.
     *
     * @param list<string> $permissions
     */
    public static function recordRank(array $permissions): int
    {
        $held = array_fill_keys($permissions, true);
        if (isset($held[Permission::RECORDS_CONTROL])) {
            return 2;
        }
        if (isset($held[Permission::RECORDS_ASSIGN])) {
            return 3;
        }
        if (isset($held[Permission::RECORDS_EDIT])) {
            return 4;
        }

        return 5;
    }

    public static function recordRankForGroup(int $groupId): int
    {
        return self::recordRank(self::permissionsForGroup($groupId));
    }

    /**
     * Strongest legacy audience in a permission set. A lower number sees more old log lines.
     *
     * @param list<string> $permissions
     */
    public static function audience(array $permissions): int
    {
        if ($permissions === []) {
            return 5;
        }
        $held = array_fill_keys($permissions, true);
        $best = 5;
        foreach (Permission::baseCatalog() as $key => $meta) {
            if (!isset($held[$key])) {
                continue;
            }
            $best = min($best, (int) $meta['legacy_max_role']);
        }

        return $best;
    }

    public static function isBreakGlass(int $userId): bool
    {
        if ($userId <= 0 || !Database::tableExists('users') || !Database::columnExists('users', 'break_glass')) {
            return false;
        }
        $user = Database::first('users', ['break_glass'], 'user_id = ?', [$userId]);

        return $user !== null && (int) ($user['break_glass'] ?? 0) === 1;
    }

    /**
     * The weakest role that may be chosen as an owner.
     * The stored setting may still be the old number. Viewer includes every active person.
     */
    public static function ownerCeiling(): int
    {
        $raw = defined('SET_OWNER_MENU') ? trim((string) SET_OWNER_MENU) : 'viewer';
        $map = [
            '0' => 0,
            '1' => 1,
            '2' => 2,
            '3' => 3,
            '4' => 4,
            '5' => 5,
            self::SITE_ADMINISTRATOR => 1,
            self::ADMINISTRATOR => 2,
            self::MANAGER => 3,
            self::USER => 4,
            self::VIEWER => 5,
        ];

        return $map[$raw] ?? 5;
    }

    /**
     * People who may be chosen as an owner.
     *
     * @return array{ids: list<int|string>, names: list<string>}
     */
    public static function ownerMenu(bool $activeOnly): array
    {
        $ceiling = self::ownerCeiling();
        $where = $activeOnly ? "lastactive <> 'inactive' ORDER BY user_name ASC" : 'ORDER BY user_name ASC';
        $ids = [];
        $names = [];
        if (!Database::tableExists('users')) {
            return ['ids' => $ids, 'names' => $names];
        }
        foreach (Database::select('users', ['user_id', 'user_name'], $where) as $row) {
            if (self::strength((int) $row['user_id']) <= $ceiling) {
                $ids[] = $row['user_id'];
                $names[] = (string) ($row['user_name'] ?? '');
            }
        }

        return ['ids' => $ids, 'names' => $names];
    }

    /**
     * A lower number is a stronger person. No group counts as Viewer.
     */
    public static function strength(int $userId): int
    {
        if (self::isBreakGlass($userId)) {
            return 0;
        }
        $best = 5;
        if (!Database::tableExists('user_groups') || !Database::columnExists('groups', 'role_id')) {
            return $best;
        }
        foreach (Database::select('user_groups', ['group_id'], 'user_id = ?', [$userId]) as $link) {
            $group = Database::first('groups', ['role_id'], 'group_id = ?', [(int) $link['group_id']]);
            if ($group === null) {
                continue;
            }
            $role = Database::first('roles', ['sort_order'], 'role_id = ?', [(int) $group['role_id']]);
            if ($role !== null) {
                $best = min($best, (int) $role['sort_order']);
            }
        }

        return $best;
    }

    public static function ensureTables(): void
    {
        Database::exec(
            'CREATE TABLE IF NOT EXISTS roles (
                role_id INTEGER NOT NULL PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                system INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 5
            )'
        );
        Database::exec(
            'CREATE TABLE IF NOT EXISTS role_permissions (
                role_id INTEGER NOT NULL,
                permission TEXT NOT NULL,
                PRIMARY KEY (role_id, permission)
            )'
        );
        if (Database::tableExists('groups') && !Database::columnExists('groups', 'role_id')) {
            Database::exec('ALTER TABLE groups ADD COLUMN role_id INTEGER NOT NULL DEFAULT 5');
        }
        if (Database::tableExists('users') && !Database::columnExists('users', 'break_glass')) {
            Database::exec('ALTER TABLE users ADD COLUMN break_glass INTEGER NOT NULL DEFAULT 0');
        }
    }

    private static function markBreakGlass(): void
    {
        if (!Database::tableExists('users') || !Database::columnExists('users', 'break_glass')) {
            return;
        }
        $keep = 0;
        if (Database::columnExists('users', 'role')) {
            $zeros = Database::select('users', ['user_id'], 'role = 0', [], 'user_id ASC');
            if ($zeros !== []) {
                $keep = (int) $zeros[0]['user_id'];
                foreach (array_slice($zeros, 1) as $extra) {
                    self::giveSiteAdministrator((int) $extra['user_id']);
                }
            }
        }
        if ($keep === 0) {
            $keep = self::firstAdministratorId();
        }
        if ($keep === 0) {
            return;
        }
        Database::exec('UPDATE users SET break_glass = 0');
        Database::update('users', ['break_glass' => 1], 'user_id = ?', [$keep]);
        Database::exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS users_one_break_glass ON users (break_glass) WHERE break_glass = 1'
        );
    }

    private static function firstAdministratorId(): int
    {
        if (!Database::tableExists('users')) {
            return 0;
        }
        if (Database::tableExists('user_groups') && Database::columnExists('groups', 'role_id') && Database::tableExists('role_permissions')) {
            $rows = Database::rows(
                'SELECT u.user_id FROM users u
                 INNER JOIN user_groups ug ON ug.user_id = u.user_id
                 INNER JOIN groups g ON g.group_id = ug.group_id
                 INNER JOIN role_permissions rp ON rp.role_id = g.role_id
                 WHERE rp.permission = ? ORDER BY u.user_id ASC LIMIT 1',
                [Permission::ADMIN_SYSTEM]
            );
            if ($rows !== []) {
                return (int) $rows[0]['user_id'];
            }
        }
        $first = Database::first('users', ['user_id'], 'ORDER BY user_id ASC');

        return $first === null ? 0 : (int) $first['user_id'];
    }

    private static function giveSiteAdministrator(int $userId): void
    {
        if ($userId <= 0 || !Database::tableExists('user_groups')) {
            return;
        }
        $roleId = self::id(self::SITE_ADMINISTRATOR);
        if ($roleId <= 0) {
            return;
        }
        $group = Database::first('groups', ['group_id'], 'role_id = ?', [$roleId]);
        if ($group === null) {
            if (!Database::columnExists('groups', 'group_name')) {
                return;
            }
            $row = [
                'group_name' => 'Site administrators',
                'role_id' => $roleId,
            ];
            if (Database::columnExists('groups', 'description')) {
                $row['description'] = 'Site administrator';
            }
            if (Database::columnExists('groups', 'role')) {
                $row['role'] = 1;
            }
            $groupId = Database::insert('groups', $row);
        } else {
            $groupId = (int) $group['group_id'];
        }
        Database::insertIgnore('user_groups', [
            'user_id' => $userId,
            'group_id' => $groupId,
        ]);
    }

    /**
     * @return list<string>
     */
    private static function storedGroupPermissions(int $groupId): array
    {
        if (!Database::tableExists('group_permissions')) {
            return [];
        }
        $list = [];
        foreach (Database::select('group_permissions', ['permission'], 'group_id = ?', [$groupId], 'permission') as $row) {
            $list[] = (string) $row['permission'];
        }

        return $list;
    }

    /**
     * @param list<string> $permissions
     * @return list<string>
     */
    private static function known(array $permissions): array
    {
        $allowed = array_fill_keys(Permission::all(), true);
        $out = [];
        foreach ($permissions as $permission) {
            $permission = (string) $permission;
            if (isset($allowed[$permission])) {
                $out[$permission] = true;
            }
        }
        $list = array_keys($out);
        sort($list);

        return $list;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameGrants(array $left, array $right): bool
    {
        $strip = static function (array $list): array {
            $list = array_values(array_filter(
                $list,
                static fn (string $permission): bool => $permission !== Permission::APP_ACCESS
            ));
            sort($list);

            return $list;
        };

        return $strip($left) === $strip($right);
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameList(array $left, array $right): bool
    {
        $left = array_values(array_unique($left));
        $right = array_values(array_unique($right));
        sort($left);
        sort($right);

        return $left === $right;
    }

    private static function point(int $groupId, int $roleId): void
    {
        if ($groupId <= 0 || $roleId <= 0 || !Database::columnExists('groups', 'role_id')) {
            return;
        }
        Database::update('groups', ['role_id' => $roleId], 'group_id = ?', [$groupId]);
    }

    private static function dropCustom(int $groupId): void
    {
        $role = Database::first('roles', ['role_id'], 'slug = ? AND system = 0', [self::customSlug($groupId)]);
        if ($role === null) {
            return;
        }
        $roleId = (int) $role['role_id'];
        if (Database::exists('groups', 'role_id = ?', [$roleId])) {
            return;
        }
        Database::delete('role_permissions', 'role_id = ?', [$roleId]);
        Database::delete('roles', 'role_id = ?', [$roleId]);
    }

    private static function customSlug(int $groupId): string
    {
        return 'custom-group-' . $groupId;
    }
}
