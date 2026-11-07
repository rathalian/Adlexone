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
     * The five roles a group can hold. A lower sort is listed first.
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

    /**
     * Permissions each system role grants. Listed by name, not by a number.
     *
     * @return array<string, list<string>>
     */
    public static function systemGrants(): array
    {
        return [
            self::SITE_ADMINISTRATOR => [
                Permission::ADMIN_SETTINGS,
                Permission::ADMIN_SECURITY,
                Permission::ADMIN_ACTIONS,
                Permission::ADMIN_ITEMS,
                Permission::RECORDS_CONTROL,
                Permission::RECORDS_ASSIGN,
                Permission::RECORDS_EDIT,
                Permission::APP_ACCESS,
            ],
            self::ADMINISTRATOR => [
                Permission::RECORDS_CONTROL,
                Permission::RECORDS_ASSIGN,
                Permission::RECORDS_EDIT,
                Permission::APP_ACCESS,
            ],
            self::MANAGER => [
                Permission::RECORDS_ASSIGN,
                Permission::RECORDS_EDIT,
                Permission::APP_ACCESS,
            ],
            self::USER => [
                Permission::RECORDS_EDIT,
                Permission::APP_ACCESS,
            ],
            self::VIEWER => [
                Permission::APP_ACCESS,
            ],
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
            foreach (self::systemGrants()[$slug] as $permission) {
                Database::insertIgnore('role_permissions', [
                    'role_id' => $roleId,
                    'permission' => $permission,
                ]);
            }
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
        $sort = 6;
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
     * Record power of a permission set. Administrator permissions do not widen this.
     * control deletes, assign changes the owner, edit changes the record, view reads it.
     *
     * @param list<string> $permissions
     */
    public static function recordLevel(array $permissions): string
    {
        $held = array_fill_keys($permissions, true);
        if (isset($held[Permission::RECORDS_CONTROL])) {
            return 'control';
        }
        if (isset($held[Permission::RECORDS_ASSIGN])) {
            return 'assign';
        }
        if (isset($held[Permission::RECORDS_EDIT])) {
            return 'edit';
        }

        return 'view';
    }

    public static function recordLevelForGroup(int $groupId): string
    {
        return self::recordLevel(self::permissionsForGroup($groupId));
    }

    public static function strongerLevel(string $left, string $right): string
    {
        return self::levelRank($left) <= self::levelRank($right) ? $left : $right;
    }

    public static function levelAtLeast(string $have, string $need): bool
    {
        return self::levelRank($have) <= self::levelRank($need);
    }

    private static function levelRank(string $level): int
    {
        return match ($level) {
            'control' => 0,
            'assign' => 1,
            'edit' => 2,
            default => 3,
        };
    }

    /**
     * Strongest named standing in a permission set. Used for the owner list
     * and for notes that still name a minimum role.
     *
     * @param list<string> $permissions
     */
    public static function holderRank(array $permissions): string
    {
        $held = array_fill_keys($permissions, true);
        if (isset($held[Permission::ADMIN_SYSTEM])) {
            return 'break-glass';
        }
        $ladder = [
            self::SITE_ADMINISTRATOR => [
                Permission::ADMIN_SETTINGS,
                Permission::ADMIN_SECURITY,
                Permission::ADMIN_ACTIONS,
                Permission::ADMIN_ITEMS,
            ],
            self::ADMINISTRATOR => [Permission::RECORDS_CONTROL],
            self::MANAGER => [Permission::RECORDS_ASSIGN],
            self::USER => [Permission::RECORDS_EDIT],
        ];
        foreach ($ladder as $slug => $keys) {
            foreach ($keys as $key) {
                if (isset($held[$key])) {
                    return $slug;
                }
            }
        }

        return self::VIEWER;
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
     * A stored number is read as the role it used to mean. Viewer includes every active person.
     */
    public static function ownerLimit(): string
    {
        $raw = defined('SET_OWNER_MENU') ? trim((string) SET_OWNER_MENU) : self::VIEWER;

        return self::ownerSlug($raw);
    }

    public static function ownerSlug(string $raw): string
    {
        $raw = trim($raw);
        $map = [
            '0' => 'break-glass',
            '1' => self::SITE_ADMINISTRATOR,
            '2' => self::ADMINISTRATOR,
            '3' => self::MANAGER,
            '4' => self::USER,
            '5' => self::VIEWER,
            'break-glass' => 'break-glass',
            self::SITE_ADMINISTRATOR => self::SITE_ADMINISTRATOR,
            self::ADMINISTRATOR => self::ADMINISTRATOR,
            self::MANAGER => self::MANAGER,
            self::USER => self::USER,
            self::VIEWER => self::VIEWER,
        ];

        return $map[$raw] ?? self::VIEWER;
    }

    /**
     * True when $person is at least as strong as $limit.
     */
    public static function reaches(string $person, string $limit): bool
    {
        return self::power($person) <= self::power($limit);
    }

    /**
     * Note audiences this person can read. An empty string is a note for everyone.
     *
     * @return list<string>
     */
    public static function visibleAudiences(int $userId): array
    {
        $mine = self::power(self::strength($userId));
        $out = [''];
        foreach ([
            self::SITE_ADMINISTRATOR => 1,
            self::ADMINISTRATOR => 2,
            self::MANAGER => 3,
            self::USER => 4,
        ] as $slug => $rank) {
            if ($rank >= $mine) {
                $out[] = $slug;
            }
        }

        return $out;
    }

    private static function power(string $slug): int
    {
        return match ($slug) {
            'break-glass' => 0,
            self::SITE_ADMINISTRATOR => 1,
            self::ADMINISTRATOR => 2,
            self::MANAGER => 3,
            self::USER => 4,
            default => 5,
        };
    }

    /**
     * People who may be chosen as an owner.
     *
     * @return array{ids: list<int|string>, names: list<string>}
     */
    public static function ownerMenu(bool $activeOnly): array
    {
        $limit = self::ownerLimit();
        $where = $activeOnly ? "lastactive <> 'inactive' ORDER BY user_name ASC" : 'ORDER BY user_name ASC';
        $ids = [];
        $names = [];
        if (!Database::tableExists('users')) {
            return ['ids' => $ids, 'names' => $names];
        }
        foreach (Database::select('users', ['user_id', 'user_name'], $where) as $row) {
            if (self::reaches(self::strength((int) $row['user_id']), $limit)) {
                $ids[] = $row['user_id'];
                $names[] = (string) ($row['user_name'] ?? '');
            }
        }

        return ['ids' => $ids, 'names' => $names];
    }

    /**
     * The strongest standing a person holds. No group counts as Viewer.
     */
    public static function strength(int $userId): string
    {
        if (self::isBreakGlass($userId)) {
            return 'break-glass';
        }
        $best = self::VIEWER;
        if (!Database::tableExists('user_groups')) {
            return $best;
        }
        foreach (Database::select('user_groups', ['group_id'], 'user_id = ?', [$userId]) as $link) {
            $rank = self::holderRank(self::permissionsForGroup((int) $link['group_id']));
            if (self::reaches($rank, $best)) {
                $best = $rank;
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
