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
        $design = self::designItems();
        if (Page::current()->hasSectionNav() && Page::current()->eyebrow() === 'Configure' && !Page::current()->keepsConfigureMenu()) {
            $design = [];
        }

        return [
            'apps' => ['mode' => $mode, 'items' => $appItems],
            'design' => $design,
            'account' => self::account(),
        ];
    }

    /** @return list<array{label: string, href: string, icon: string, current: bool, hint: string}> */
    public static function designItems(): array
    {
        $items = [];
        $add = static function (string $label, string $href, string $icon, string $controller, string $hint) use (&$items): void {
            $items[] = [
                'label' => $label,
                'href' => $href,
                'icon' => $icon,
                'current' => self::controllerMatches($controller),
                'hint' => $hint,
            ];
        };

        if (Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $add('Create an application', '?manage=builder', 'ic-plus', 'manage_builder', 'Add a record type, fields, and screens.');
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $add('Applications', '?manage=applications', 'ic-grid', 'manage_applications', 'Set the order of applications and their navigation.');
        }
        if (Access::can(Permission::ADMIN_ITEMS)) {
            $add('Fields', '?manage=fields', 'ic-fields', 'manage_fields', 'Fields used across the applications.');
            $add('Record types', '?manage=item-types', 'ic-layers', 'manage_item_types', 'The record type each application keeps.');
        }
        if (Access::can(Permission::ADMIN_ACTIONS)) {
            $add('Rules', '?manage=rules', 'ic-zap', 'manage_actions', 'What runs when a record is created or updated.');
        }
        if (Access::can(Permission::ADMIN_SECURITY)) {
            $add('People', '?manage=security&option=manage_users', 'ic-users', 'manage_security', 'People who can sign in.');
            $add('Groups', '?manage=security&option=manage_groups', 'ic-users-round', 'manage_security', 'Groups and their access.');
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $add('Settings', '?manage=settings&option=adlexone_settings', 'ic-sliders', 'manage_settings', 'Site, theme, sign-in, email, and advanced settings.');
        } elseif (Access::can(Permission::ADMIN_SYSTEM)) {
            $add('Settings', '?manage=settings&option=sign_in_settings', 'ic-sliders', 'manage_settings', 'Sign-in, email, and advanced settings.');
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
            ['label' => 'My profile', 'href' => '?manage=security&option=modify_user', 'icon' => 'ic-user'],
            ['label' => 'My theme', 'href' => '?manage=settings&option=my_theme', 'icon' => 'ic-palette'],
        ];

        return [
            'name' => $name,
            'initials' => $initials !== '' ? $initials : 'A',
            'links' => $links,
        ];
    }

    private static function applicationIsCurrent(string $slug): bool
    {
        return (string) ($_GET['controller'] ?? '') === 'application'
            && $slug !== ''
            && $slug === (string) ($_GET['app'] ?? '');
    }

    private static function controllerMatches(string $controller): bool
    {
        $current = (string) ($_GET['controller'] ?? '');
        if ($current === $controller) {
            return true;
        }
        $prefix = preg_replace('/_(main|manage)$/', '', $controller);

        return is_string($prefix) && $prefix !== $controller && str_starts_with($current, $prefix);
    }
}
