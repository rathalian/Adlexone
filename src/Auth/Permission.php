<?php
declare(strict_types=1);

namespace Adlexone\Auth;

use Adlexone\support\Database;

/**
 * Named permissions used for authorization.
 *
 * Application-scoped keys (app.{slug}.use|settings) are generated
 * from the applications registry so a group can be given them.
 */
final class Permission
{
    public const ADMIN_SYSTEM = 'admin.system';
    public const ADMIN_SETTINGS = 'admin.settings';
    public const ADMIN_SECURITY = 'admin.security';
    public const ADMIN_ACTIONS = 'admin.actions';
    public const ADMIN_ITEMS = 'admin.items';

    public const APP_ACCESS = 'app.access';

    public static function appUse(string $slug): string
    {
        return 'app.' . self::safeSlug($slug) . '.use';
    }

    public static function appSettings(string $slug): string
    {
        return 'app.' . self::safeSlug($slug) . '.settings';
    }

    /**
     * @return array<string, array{label: string, legacy_max_role: int}>
     */
    public static function catalog(): array
    {
        return self::baseCatalog() + self::applicationCatalog();
    }

    /**
     * @return array<string, array{label: string, legacy_max_role: int}>
     */
    public static function baseCatalog(): array
    {
        return [
            self::ADMIN_SYSTEM => [
                'label' => 'Sign-in, email, and advanced settings',
                'legacy_max_role' => 0,
            ],
            self::ADMIN_SETTINGS => [
                'label' => 'Applications and site settings',
                'legacy_max_role' => 1,
            ],
            self::ADMIN_SECURITY => [
                'label' => 'People and groups',
                'legacy_max_role' => 1,
            ],
            self::ADMIN_ACTIONS => [
                'label' => 'Rules',
                'legacy_max_role' => 1,
            ],
            self::ADMIN_ITEMS => [
                'label' => 'Fields and record types',
                'legacy_max_role' => 1,
            ],
            self::APP_ACCESS => [
                'label' => 'Sign in and use applications',
                'legacy_max_role' => 5,
            ],
        ];
    }

    /**
     * Site permissions a group can be given.
     * Signing in is granted to every person, so that permission is not listed.
     *
     * @return array<string, string>
     */
    public static function siteChoices(): array
    {
        $out = [];
        foreach (self::baseCatalog() as $key => $meta) {
            if ($key === self::APP_ACCESS) {
                continue;
            }
            $out[$key] = (string) $meta['label'];
        }

        return $out;
    }

    /**
     * One row per application, with the permission keys that belong to it.
     *
     * @return list<array{name: string, slug: string, open: string, settings: string}>
     */
    public static function applicationChoices(): array
    {
        $out = [];
        foreach (self::applicationRows() as $app) {
            $out[] = [
                'name' => $app['name'],
                'slug' => $app['slug'],
                'open' => self::appUse($app['slug']),
                'settings' => self::appSettings($app['slug']),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, legacy_max_role: int}>
     */
    private static function applicationCatalog(): array
    {
        $out = [];
        foreach (self::applicationRows() as $app) {
            // legacy_max_role 4 bridges shared item engines still gated by role numbers.
            $out[self::appUse($app['slug'])] = [
                'label' => 'Open: ' . $app['name'],
                'legacy_max_role' => 4,
            ];
            $out[self::appSettings($app['slug'])] = [
                'label' => 'Settings: ' . $app['name'],
                'legacy_max_role' => 1,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    private static function applicationRows(): array
    {
        if (!Database::tableExists('applications')) {
            return [];
        }
        $out = [];
        foreach (Database::select('applications', ['slug', 'name'], '', [], 'name ASC') as $app) {
            $slug = self::safeSlug((string) $app['slug']);
            if ($slug === '') {
                continue;
            }
            $name = trim((string) $app['name']);
            $out[] = ['slug' => $slug, 'name' => $name !== '' ? $name : $slug];
        }

        return $out;
    }

    /**
     * Grant selected app-scoped permissions to the given groups.
     * When $groupIds is empty, Administrator groups + the current user's groups are used.
     *
     * @param list<int> $groupIds
     * @param list<string> $levels subset of use|settings (default both). Older packages may still say announce.
     * @return list<int> group ids that received grants
     */
    public static function grantAppToGroups(string $slug, array $groupIds = [], array $levels = []): array
    {
        Access::ensureReady();
        $levelMap = [
            'use' => self::appUse($slug),
            'settings' => self::appSettings($slug),
        ];
        if ($levels === []) {
            $levels = array_keys($levelMap);
        }
        $perms = [];
        foreach ($levels as $level) {
            if (isset($levelMap[$level])) {
                $perms[] = $levelMap[$level];
            }
        }
        if ($perms === []) {
            return [];
        }

        if ($groupIds === []) {
            foreach (Database::select('groups', ['group_id', 'group_name']) as $group) {
                $gname = strtolower((string) ($group['group_name'] ?? ''));
                if ($gname === 'administrator' || (int) $group['group_id'] === 3) {
                    $groupIds[] = (int) $group['group_id'];
                }
            }
            $userId = (int) ($_SESSION['access_user_id'] ?? 0);
            if ($userId > 0 && Database::tableExists('user_groups')) {
                foreach (Database::select('user_groups', ['group_id'], 'user_id = ?', [$userId]) as $row) {
                    $groupIds[] = (int) $row['group_id'];
                }
            }
        }

        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
        foreach ($groupIds as $groupId) {
            $have = Access::permissionsForGroup($groupId);
            foreach ($perms as $permission) {
                if (!in_array($permission, $have, true)) {
                    $have[] = $permission;
                }
            }
            Access::setGroupPermissions($groupId, $have);
        }
        return $groupIds;
    }

    /**
     * Groups whose members can open this application.
     * Other permissions on those groups are left as they are.
     *
     * @param list<int> $groupIds
     */
    public static function setAppUseGroups(string $slug, array $groupIds): void
    {
        Access::ensureReady();
        $permission = self::appUse($slug);
        $known = [];
        foreach (Database::select('groups', ['group_id']) as $group) {
            $known[(int) $group['group_id']] = true;
        }
        $wanted = [];
        foreach ($groupIds as $groupId) {
            $groupId = (int) $groupId;
            if (isset($known[$groupId])) {
                $wanted[$groupId] = true;
            }
        }
        foreach (array_keys($known) as $groupId) {
            $have = Access::permissionsForGroup($groupId);
            $has = in_array($permission, $have, true);
            $should = isset($wanted[$groupId]);
            if ($has === $should) {
                continue;
            }
            if ($should) {
                $have[] = $permission;
            } else {
                $have = array_values(array_filter(
                    $have,
                    static fn (string $granted): bool => $granted !== $permission
                ));
            }
            Access::setGroupPermissions($groupId, $have);
        }
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        if ($userId > 0) {
            Access::hydrateSession($userId);
        }
    }

    public static function revokeApp(string $slug): void
    {
        Access::ensureReady();
        foreach ([self::appUse($slug), self::appSettings($slug)] as $permission) {
            Database::delete('group_permissions', 'permission = ?', [$permission]);
        }
    }

    public static function renameApp(string $oldSlug, string $newSlug): void
    {
        $oldSlug = self::safeSlug($oldSlug);
        $newSlug = self::safeSlug($newSlug);
        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) {
            return;
        }
        Access::ensureReady();
        $map = [
            self::appUse($oldSlug) => self::appUse($newSlug),
            self::appSettings($oldSlug) => self::appSettings($newSlug),
        ];
        foreach ($map as $from => $to) {
            Database::run('UPDATE group_permissions SET permission = ? WHERE permission = ?', [$to, $from]);
        }
    }

    /**
     * @return list<array{group: string, levels: list<string>}>
     */
    public static function exportAppGrants(string $slug): array
    {
        Access::ensureReady();
        $levelMap = [
            self::appUse($slug) => 'use',
            self::appSettings($slug) => 'settings',
        ];
        $byGroup = [];
        foreach ($levelMap as $permission => $level) {
            foreach (Database::select('group_permissions', ['group_id'], 'permission = ?', [$permission]) as $row) {
                $gid = (int) $row['group_id'];
                $group = Database::first('groups', ['group_name'], 'group_id = ?', [$gid]);
                if ($group === null) {
                    continue;
                }
                $name = (string) $group['group_name'];
                $byGroup[$name] ??= [];
                $byGroup[$name][] = $level;
            }
        }
        $out = [];
        foreach ($byGroup as $name => $levels) {
            $out[] = ['group' => $name, 'levels' => array_values(array_unique($levels))];
        }
        return $out;
    }

    /**
     * @param list<array{group?: string, levels?: list<string>}> $grants
     * @return list<int>
     */
    public static function importAppGrants(string $slug, array $grants): array
    {
        $allGroups = [];
        foreach ($grants as $grant) {
            if (!is_array($grant)) {
                continue;
            }
            $name = trim((string) ($grant['group'] ?? ''));
            if ($name === '') {
                continue;
            }
            $group = Database::first('groups', ['group_id'], 'group_name = ?', [$name]);
            if ($group === null) {
                continue;
            }
            $gid = (int) $group['group_id'];
            $levels = is_array($grant['levels'] ?? null) ? $grant['levels'] : ['use', 'settings'];
            self::grantAppToGroups($slug, [$gid], array_map('strval', $levels));
            $allGroups[] = $gid;
        }
        if ($allGroups === []) {
            return self::grantAppToGroups($slug);
        }
        return array_values(array_unique($allGroups));
    }

    /** @deprecated use grantAppToGroups */
    public static function grantAppToAdmins(string $slug, string $name = ''): void
    {
        unset($name);
        self::grantAppToGroups($slug);
    }

    /**
     * Permissions granted to a legacy role number (base catalog only).
     *
     * @return list<string>
     */
    public static function forLegacyRole(int $role): array
    {
        $granted = [];
        foreach (self::baseCatalog() as $key => $meta) {
            if ($role <= (int) $meta['legacy_max_role']) {
                $granted[] = $key;
            }
        }
        return $granted;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::catalog());
    }

    private static function safeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }
}
