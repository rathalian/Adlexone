<?php
declare(strict_types=1);

namespace Adlexone\Auth;

/**
 * Named permissions used for authorization.
 *
 * legacyMaxRole documents the old numeric threshold these replaced
 * (lower number = more privilege in the legacy model).
 */
final class Permission
{
    public const ADMIN_SYSTEM = 'admin.system';
    public const ADMIN_SETTINGS = 'admin.settings';
    public const ADMIN_SECURITY = 'admin.security';
    public const ADMIN_ACTIONS = 'admin.actions';
    public const ADMIN_ITEMS = 'admin.items';

    public const SERVICECENTRE_USE = 'servicecentre.use';
    public const SERVICECENTRE_SEARCH = 'servicecentre.search';
    public const SERVICECENTRE_ANNOUNCE = 'servicecentre.announce';
    public const SERVICECENTRE_SETTINGS = 'servicecentre.settings';

    public const KNOWLEDGEBASE_USE = 'knowledgebase.use';
    public const KNOWLEDGEBASE_MANAGE = 'knowledgebase.manage';
    public const KNOWLEDGEBASE_SETTINGS = 'knowledgebase.settings';

    public const REPORTS_USE = 'reports.use';
    public const REPORTS_MANAGE = 'reports.manage';

    public const APP_ACCESS = 'app.access';

    /**
     * @return array<string, array{label: string, legacy_max_role: int}>
     */
    public static function catalog(): array
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
            self::SERVICECENTRE_SETTINGS => [
                'label' => 'Service Centre settings',
                'legacy_max_role' => 1,
            ],
            self::KNOWLEDGEBASE_MANAGE => [
                'label' => 'Knowledgebase administration',
                'legacy_max_role' => 1,
            ],
            self::SERVICECENTRE_ANNOUNCE => [
                'label' => 'Manage Service Centre announcements',
                'legacy_max_role' => 2,
            ],
            self::REPORTS_MANAGE => [
                'label' => 'Create and manage reports',
                'legacy_max_role' => 3,
            ],
            self::SERVICECENTRE_USE => [
                'label' => 'Use Service Centre',
                'legacy_max_role' => 4,
            ],
            self::SERVICECENTRE_SEARCH => [
                'label' => 'Search Service Centre',
                'legacy_max_role' => 5,
            ],
            self::KNOWLEDGEBASE_USE => [
                'label' => 'Use the knowledgebase',
                'legacy_max_role' => 5,
            ],
            self::KNOWLEDGEBASE_SETTINGS => [
                'label' => 'Knowledgebase settings',
                'legacy_max_role' => 5,
            ],
            self::REPORTS_USE => [
                'label' => 'View reports',
                'legacy_max_role' => 5,
            ],
            self::APP_ACCESS => [
                'label' => 'Sign in and use Adlexone',
                'legacy_max_role' => 5,
            ],
        ];
    }

    /**
     * Permissions granted to a legacy role number (and everything less privileged).
     *
     * @return list<string>
     */
    public static function forLegacyRole(int $role): array
    {
        $granted = [];
        foreach (self::catalog() as $key => $meta) {
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
}
