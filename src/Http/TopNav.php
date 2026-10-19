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

    /** @return list<array{label: string, href: string, icon: string, current: bool}> */
    private static function designItems(): array
    {
        $items = [];
        $add = static function (string $label, string $href, string $icon, string $controller) use (&$items): void {
            $items[] = [
                'label' => $label,
                'href' => $href,
                'icon' => $icon,
                'current' => self::controllerMatches($controller),
            ];
        };

        if (Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $add('Builder', '?manage=builder', 'ic-itemtype-add', 'manage_builder');
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $add('Applications', '?manage=applications', 'ic-launch', 'manage_applications');
        }
        if (Access::can(Permission::ADMIN_ACTIONS)) {
            $add('Rules', '?manage=rules', 'ic-manage-actions', 'manage_actions');
        }

        return $items;
    }

    /** @return array{name: string, initials: string, links: list<array{label: string, href: string, icon: string}>}|null */
    private static function account(): ?array
    {
        $name = trim((string) ($_SESSION['access_user_name'] ?? ''));
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
            ['label' => 'My profile', 'href' => '?manage=security&option=modify_user', 'icon' => 'ic-manage-users'],
            ['label' => 'Theme', 'href' => '?manage=settings&option=my_theme', 'icon' => 'ic-system-settings'],
        ];
        if (Access::can(Permission::ADMIN_SECURITY)) {
            $links[] = ['label' => 'People', 'href' => '?manage=security&option=manage_users', 'icon' => 'ic-manage-users'];
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $links[] = ['label' => 'Settings', 'href' => '?manage=settings&option=adlexone_settings', 'icon' => 'ic-settings'];
        } elseif (Access::can(Permission::ADMIN_SYSTEM)) {
            $links[] = ['label' => 'Settings', 'href' => '?manage=settings&option=sign_in_settings', 'icon' => 'ic-settings'];
        }

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
