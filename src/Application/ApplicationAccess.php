<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Http\Page;

/**
 * Whether the signed-in person may use the application that is open.
 */
final class ApplicationAccess
{
    public static function slug(): string
    {
        if (Page::current()->inApplication()) {
            return Page::current()->applicationSlug();
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

    /**
     * Post, edit, and delete notices. Opening the application includes this.
     */
    public static function canAnnounce(): bool
    {
        $slug = self::slug();
        if ($slug === '') {
            return Access::can(Permission::ADMIN_SETTINGS);
        }
        return Access::can(Permission::appUse($slug), Permission::ADMIN_SETTINGS);
    }

    public static function canSettings(): bool
    {
        return self::canDesign();
    }

    /**
     * People who design applications. Daily use of an app does not include this.
     */
    public static function canDesign(): bool
    {
        return Access::can(
            Permission::ADMIN_SETTINGS,
            Permission::ADMIN_ITEMS,
            Permission::ADMIN_ACTIONS,
            Permission::ADMIN_SYSTEM
        );
    }

    /**
     * Whether named permissions satisfy an older role threshold.
     * A lower allowed role number requires more privilege.
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
