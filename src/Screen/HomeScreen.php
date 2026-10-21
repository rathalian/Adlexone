<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Html\Blocks;
use Adlexone\Http\Page;
use Adlexone\Http\View;
use Adlexone\Theme\Theme;

/**
 * Screen formerly loaded from legacy/.
 */
final class HomeScreen
{
    public static function open(): void
    {
        $landing = self::landingHref();
        if ($landing !== '') {
            header('Location: ' . \Adlexone\Http\Router::absolute($landing));
            exit;
        }

        $menu = \Adlexone\Application\ApplicationStore::menuItems();

        $manage = [];
        if (Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $manage[] = self::tile(
                '?manage=builder',
                'Create application',
                'Create an application, including its record type, fields, and screens.',
                'ic-plus'
            );
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $manage[] = self::tile(
                '?manage=applications',
                'Applications',
                'Set the order of applications and their navigation.',
                'ic-grid'
            );
        }
        if (Access::can(Permission::ADMIN_ITEMS)) {
            $manage[] = self::tile(
                '?manage=fields',
                'Fields',
                'Fields used across the applications.',
                'ic-fields'
            );
            $manage[] = self::tile(
                '?manage=item-types',
                'Record types',
                'The record type each application keeps.',
                'ic-layers'
            );
        }
        if (Access::can(Permission::ADMIN_ACTIONS)) {
            $manage[] = self::tile(
                '?manage=rules',
                'Rules',
                'What runs when a record is created or updated.',
                'ic-zap'
            );
        }
        if (Access::can(Permission::ADMIN_SECURITY)) {
            $manage[] = self::tile(
                '?manage=security&option=manage_users',
                'People',
                'People who can sign in.',
                'ic-users'
            );
            $manage[] = self::tile(
                '?manage=security&option=manage_groups',
                'Groups',
                'A group is the people who can open an application.',
                'ic-users-round'
            );
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $manage[] = self::tile(
                '?manage=settings&option=adlexone_settings',
                'Settings',
                'Site name, theme, and related settings.',
                'ic-sliders'
            );
        } elseif (Access::can(Permission::ADMIN_SYSTEM)) {
            $manage[] = self::tile(
                '?manage=settings&option=sign_in_settings',
                'Settings',
                'Sign-in and system settings.',
                'ic-sliders'
            );
        }

        $intro = '';
        if ($menu === [] && Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $intro = View::partialToString('launchpad_intro', [
                'copy' => defined('SET_HOME_LEAD') && trim((string) SET_HOME_LEAD) !== ''
                    ? (string) SET_HOME_LEAD
                    : 'This site keeps the applications, records, and rules for the work.',
                'art' => Theme::illustrationHref('empty-apps'),
            ]);
        }

        $html = HomeDashboard::render($menu, $manage, $intro);

        if ($menu === [] && $manage === [] && $intro === '') {
            $html = Blocks::emptyState([
                'illustration' => 'empty-apps',
                'title' => 'Nothing is available for this account',
                'copy' => 'Ask an administrator to grant access to an application.',
            ]);
        }

        Page::current()->setBody($html);
    }
    /**
     * One application opens straight into it. Several applications stay on
     * this page so the person can choose, unless their account names a home app.
     */
    private static function landingHref(): string
    {
        $items = \Adlexone\Application\ApplicationStore::menuItems();
        if ($items === []) {
            return '';
        }
        $bySlug = [];
        foreach ($items as $item) {
            $bySlug[(string) ($item['app'] ?? '')] = (string) ($item['href'] ?? '');
        }
        $stored = (string) ($_SESSION['access_home_controller'] ?? '');
        if (str_starts_with($stored, 'application:')) {
            $slug = substr($stored, strlen('application:'));
            if (($bySlug[$slug] ?? '') !== '') {
                return $bySlug[$slug];
            }
        }
        $default = defined('SET_DEFAULT_APPLICATION') ? (string) SET_DEFAULT_APPLICATION : '';
        if (str_contains($default, '}-{')) {
            [$controller] = explode('}-{', $default, 2);
            if (str_starts_with((string) $controller, 'application:')) {
                $slug = substr((string) $controller, strlen('application:'));
                if (($bySlug[$slug] ?? '') !== '') {
                    return $bySlug[$slug];
                }
            }
        }
        if (count($items) === 1) {
            return (string) ($items[0]['href'] ?? '');
        }

        return '';
    }

    /**
     * Homepage. One tile per destination. Section menus stay inside each app.
     *
     * @return array{href: string, title: string, hint: string, icon: string, stat: string, when: string}
     */
    private static function tile(string $href, string $title, string $hint, string $icon, string $stat = '', string $when = ''): array
    {
        return [
            'href' => $href,
            'title' => $title,
            'hint' => $hint,
            'icon' => $icon,
            'stat' => $stat,
            'when' => $when,
        ];
    }
}
