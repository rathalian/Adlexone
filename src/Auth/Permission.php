<?php
declare(strict_types=1);

namespace Adlexone\Auth;

use Adlexone\support\Database;

/**
 * Named permissions used for authorization.
 *
 * Application-scoped keys (app.{slug}.use|announce|settings) are generated
 * from the applications registry so Manage Security can assign them in the UI.
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

    public static function appAnnounce(string $slug): string
    {
        return 'app.' . self::safeSlug($slug) . '.announce';
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
                'label' => 'System configuration (sign-in, advanced settings, and outbound email)',
                'legacy_max_role' => 0,
            ],
            self::ADMIN_SETTINGS => [
                'label' => 'Application settings',
                'legacy_max_role' => 1,
            ],
            self::ADMIN_SECURITY => [
                'label' => 'Users and groups',
                'legacy_max_role' => 1,
            ],
            self::ADMIN_ACTIONS => [
                'label' => 'Workflow actions',
                'legacy_max_role' => 1,
            ],
            self::ADMIN_ITEMS => [
                'label' => 'Item types and fields',
                'legacy_max_role' => 1,
            ],
            self::APP_ACCESS => [
                'label' => 'Sign in and use applications',
                'legacy_max_role' => 5,
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, legacy_max_role: int}>
     */
    private static function applicationCatalog(): array
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
            $name = (string) $app['name'];
            // legacy_max_role 4 bridges shared item engines still gated by role numbers.
            $out[self::appUse($slug)] = [
                'label' => 'Use application: ' . $name,
                'legacy_max_role' => 4,
            ];
            $out[self::appAnnounce($slug)] = [
                'label' => 'Manage announcements: ' . $name,
                'legacy_max_role' => 2,
            ];
            $out[self::appSettings($slug)] = [
                'label' => 'Application settings: ' . $name,
                'legacy_max_role' => 1,
            ];
        }
        return $out;
    }

    /**
     * Grant selected app-scoped permissions to the given groups.
     * When $groupIds is empty, Administrator groups + the current user's groups are used.
     *
     * @param list<int> $groupIds
     * @param list<string> $levels subset of use|announce|settings (default all)
     */
    public static function grantAppToGroups(string $slug, array $groupIds = [], array $levels = []): void
    {
        Access::ensureReady();
        $levelMap = [
            'use' => self::appUse($slug),
            'announce' => self::appAnnounce($slug),
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
            return;
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
