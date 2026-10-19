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

        $applications = [];
        foreach (\Adlexone\Application\ApplicationStore::menuItems() as $item) {
            $hint = trim((string) $item['hint']);
            $applications[] = self::tile(
                (string) $item['href'],
                (string) $item['label'],
                $hint !== '' ? $hint : (string) $item['label'],
                (string) $item['icon']
            );
        }

        $manage = [];
        if (Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $manage[] = self::tile(
                '?manage=builder',
                'Create application',
                'Guided builder for a new business app',
                'ic-itemtype-add'
            );
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $manage[] = self::tile(
                '?manage=applications',
                'Applications',
                'Arrange applications and navigation',
                'ic-launch'
            );
        }
        if (Access::can(Permission::ADMIN_ITEMS)) {
            $manage[] = self::tile(
                '?manage=fields',
                'Fields',
                'Fields used by your applications',
                'ic-manage-fields'
            );
            $manage[] = self::tile(
                '?manage=item-types',
                'Record types',
                'The records each application keeps',
                'ic-manage-item-types'
            );
        }
        if (Access::can(Permission::ADMIN_ACTIONS)) {
            $manage[] = self::tile(
                '?manage=rules',
                'Rules',
                'What happens when a record changes',
                'ic-manage-actions'
            );
        }
        if (Access::can(Permission::ADMIN_SECURITY)) {
            $manage[] = self::tile(
                '?manage=security&option=manage_users',
                'Security',
                'Users and groups',
                'ic-manage-users'
            );
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $manage[] = self::tile(
                '?manage=settings&option=adlexone_settings',
                'Settings',
                'Application setup',
                'ic-system-settings'
            );
        } elseif (Access::can(Permission::ADMIN_SYSTEM)) {
            $manage[] = self::tile(
                '?manage=settings&option=sign_in_settings',
                'Settings',
                'Sign-in and system setup',
                'ic-system-settings'
            );
        }

        $intro = '';
        if ($applications === [] && Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $intro = View::partialToString('launchpad_intro', [
                'copy' => defined('SET_HOME_LEAD') && trim((string) SET_HOME_LEAD) !== ''
                    ? (string) SET_HOME_LEAD
                    : 'The work, arranged the way your team actually does it.',
                'art' => Theme::illustrationHref('empty-apps'),
            ]);
        }

        $html = '<div class="launchpad">'
            . $intro
            . self::group('Applications', $applications)
            . self::group('Design', $manage)
            . '</div>';

        if ($applications === [] && $manage === [] && $intro === '') {
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
     * @return array{href: string, title: string, hint: string, icon: string}
     */
    private static function tile(string $href, string $title, string $hint, string $icon): array
    {
        return ['href' => $href, 'title' => $title, 'hint' => $hint, 'icon' => $icon];
    }

    /**
     * @param list<array{href: string, title: string, hint: string, icon: string}> $tiles
     */
    private static function group(string $label, array $tiles): string
    {
        if ($tiles === []) {
            return '';
        }

        return View::partialToString('launch_group', ['label' => $label, 'tiles' => $tiles]);
    }
}
