<?php
declare(strict_types=1);

namespace Adlexone\Http;

use Adlexone\Application\ApplicationStore;
use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;

/**
 * Data for the top bar of the app shell: which menus exist, what each lists,
 * and which entry is current. The template only loops over the result.
 */
final class TopNav
{
    /**
     * @return array{
     *     apps: array{mode: string, items: list<array{label: string, href: string, icon: string, current: bool}>},
     *     design: list<array{label: string, href: string, icon: string, current: bool}>,
     *     account: array{name: string, initials: string, links: list<array{label: string, href: string, icon: string}>}|null
     * }
     */
    public static function build(): array
    {
        $applications = ApplicationStore::menuItems();
        $inApplication = Page::current()->inApplication();
        $showAppMenu = count($applications) > 1 || (!$inApplication && $applications !== []);

        $appItems = [];
        foreach ($applications as $item) {
            $appItems[] = [
                'label' => (string) $item['label'],
                'hint' => (string) ($item['hint'] ?? ''),
                'href' => (string) $item['href'],
                'icon' => (string) $item['icon'],
                'current' => self::applicationIsCurrent((string) ($item['app'] ?? '')),
            ];
        }

        $mode = 'none';
        if ($showAppMenu) {
            $mode = count($applications) === 1 ? 'link' : 'menu';
        }

        return [
            'apps' => ['mode' => $mode, 'items' => $appItems],
            'design' => self::designItems(),
            'account' => self::account(),
        ];
    }

    /** @return list<array{label: string, href: string, icon: string, current: bool, hint: string}> */
    public static function designItems(): array
    {
        $items = [];
        $add = static function (string $label, string $name, string $icon, string $hint, string $query = '') use (&$items): void {
            $items[] = [
                'label' => $label,
                'href' => Router::manageUrl($name, $query),
                'icon' => $icon,
                'current' => self::screenIsCurrent($name),
                'hint' => $hint,
            ];
        };

        if (Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $add('Create an application', 'builder', 'ic-plus', 'Add a record type, fields, and screens.');
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $add('Applications', 'applications', 'ic-grid', 'Set the order of applications and their navigation.');
        }
        if (Access::can(Permission::ADMIN_ITEMS)) {
            $add('Fields', 'fields', 'ic-fields', 'Fields used across the applications.');
            $add('Record types', 'item-types', 'ic-layers', 'The record type each application keeps.');
        }
        if (Access::can(Permission::ADMIN_ACTIONS)) {
            $add('Rules', 'rules', 'ic-zap', 'What runs when a record is created or updated.');
        }
        if (Access::can(Permission::ADMIN_SECURITY)) {
            $add('People', 'security', 'ic-users', 'People who can sign in.', 'option=manage_users');
            $add('Groups', 'security', 'ic-users-round', 'Groups and their access.', 'option=manage_groups');
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $add('Settings', 'settings', 'ic-sliders', 'Site, theme, sign-in, email, and advanced settings.', 'option=adlexone_settings');
        } elseif (Access::can(Permission::ADMIN_SYSTEM)) {
            $add('Settings', 'settings', 'ic-sliders', 'Sign-in, email, and advanced settings.', 'option=sign_in_settings');
        }

        return $items;
    }

    /** @return array{name: string, initials: string, links: list<array{label: string, href: string, icon: string}>}|null */
    private static function account(): ?array
    {
        $name = trim((string) ($_SESSION['access_display_name'] ?? $_SESSION['access_user_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $initials = '';
        foreach (array_slice(preg_split('/\s+/', $name) ?: [], 0, 2) as $part) {
            if ($part !== '') {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }

        $links = [
            ['label' => 'My profile', 'href' => Router::manageUrl('security', 'option=modify_user'), 'icon' => 'ic-user'],
            ['label' => 'My theme', 'href' => Router::manageUrl('settings', 'option=my_theme'), 'icon' => 'ic-palette'],
        ];

        return [
            'name' => $name,
            'initials' => $initials !== '' ? $initials : 'A',
            'links' => $links,
        ];
    }

    private static function applicationIsCurrent(string $slug): bool
    {
        return Page::current()->inApplication()
            && $slug !== ''
            && $slug === Page::current()->applicationSlug();
    }

    private static function screenIsCurrent(string $screen): bool
    {
        if (Request::query('manage') === '') {
            return false;
        }

        return Router::matches($screen);
    }
}
