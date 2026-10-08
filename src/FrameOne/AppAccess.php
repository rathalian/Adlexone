<?php
declare(strict_types=1);

namespace Adlexone\FrameOne;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;

/**
 * Permission checks for the current application shell (APPLICATION_SLUG).
 */
final class AppAccess
{
    public static function slug(): string
    {
        if (defined('APPLICATION_SLUG')) {
            return (string) APPLICATION_SLUG;
        }
        return trim((string) ($_GET['app'] ?? $_SESSION['application_slug'] ?? ''));
    }

    public static function canUse(): bool
    {
        $slug = self::slug();
        if ($slug === '') {
            return Access::can(Permission::APP_ACCESS);
        }
        return Access::can(
            Permission::appUse($slug),
            Permission::APP_ACCESS,
            Permission::ADMIN_SETTINGS
        );
    }

    public static function canAnnounce(): bool
    {
        $slug = self::slug();
        if ($slug === '') {
            return Access::can(Permission::ADMIN_SETTINGS);
        }
        return Access::can(Permission::appAnnounce($slug), Permission::ADMIN_SETTINGS);
    }

    public static function canSettings(): bool
    {
        $slug = self::slug();
        if ($slug === '') {
            return Access::can(Permission::ADMIN_SETTINGS);
        }
        return Access::can(Permission::appSettings($slug), Permission::ADMIN_SETTINGS);
    }

    /**
     * Whether named permissions satisfy a legacy role threshold
     * (lower allowedRole = more privilege required).
     */
    public static function satisfiesLegacyRole(int $allowedRole): bool
    {
        if ($allowedRole >= 5) {
            return self::canUse() || Access::can(Permission::APP_ACCESS);
        }
        if ($allowedRole >= 4) {
            return self::canUse();
        }
        if ($allowedRole >= 3) {
            return self::canUse() || Access::can(Permission::ADMIN_ITEMS);
        }
        if ($allowedRole >= 2) {
            return Access::can(
                Permission::ADMIN_ITEMS,
                Permission::ADMIN_SETTINGS,
                Permission::ADMIN_SECURITY
            );
        }
        if ($allowedRole >= 1) {
            return Access::can(Permission::ADMIN_SETTINGS, Permission::ADMIN_SYSTEM);
        }
        return Access::can(Permission::ADMIN_SYSTEM);
    }

    public static function requireUse(): void
    {
        if (!self::canUse()) {
            Access::deny();
        }
    }
}
