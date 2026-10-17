<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\support\RenderViews;
use Adlexone\Http\Page;

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
            $applications[] = self::launchTile(
                (string) $item['href'],
                (string) $item['label'],
                $hint !== '' ? $hint : (string) $item['label'],
                (string) $item['icon']
            );
        }

        $manage = [];
        if (Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $manage[] = self::launchTile(
                '?manage=builder',
                'Create application',
                'Guided builder for a new business app',
                'ic-itemtype-add'
            );
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $manage[] = self::launchTile(
                '?manage=applications',
                'Applications',
                'Arrange applications and navigation',
                'ic-launch'
            );
        }
        if (Access::can(Permission::ADMIN_ITEMS)) {
            $manage[] = self::launchTile(
                '?manage=fields',
                'Fields',
                'Custom fields and menus',
                'ic-manage-fields'
            );
            $manage[] = self::launchTile(
                '?manage=item-types',
                'Item types',
                'Types and their fields',
                'ic-manage-item-types'
            );
        }
        if (Access::can(Permission::ADMIN_ACTIONS)) {
            $manage[] = self::launchTile(
                '?manage=rules',
                'Rules',
                'What happens when a record changes',
                'ic-manage-actions'
            );
        }
        if (Access::can(Permission::ADMIN_SECURITY)) {
            $manage[] = self::launchTile(
                '?manage=security&option=manage_users',
                'Security',
                'Users and groups',
                'ic-manage-users'
            );
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $manage[] = self::launchTile(
                '?manage=settings&option=adlexone_settings',
                'Settings',
                'Application setup',
                'ic-system-settings'
            );
        } elseif (Access::can(Permission::ADMIN_SYSTEM)) {
            $manage[] = self::launchTile(
                '?manage=settings&option=sign_in_settings',
                'Settings',
                'Sign-in and system setup',
                'ic-system-settings'
            );
        }

        $intro = '';
        if ($applications === [] && Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $introCopy = defined('SET_HOME_LEAD') && trim((string) SET_HOME_LEAD) !== ''
                ? (string) SET_HOME_LEAD
                : 'The work, arranged the way your team actually does it.';
            $art = \Adlexone\Theme\Theme::illustrationHref('empty-apps');
            $intro = '<section class="launchpad__intro launchpad__intro--illustrated">'
                . '<div class="empty-state empty-state--brand">'
                . ($art !== '' ? '<img class="empty-state__art" src="' . htmlspecialchars($art, ENT_QUOTES, 'UTF-8') . '" alt="" width="160" height="120" loading="lazy">' : '')
                . '<div class="brandwrap launchpad__brand">'
                . '<span class="logoA"><svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
                . '<rect width="32" height="32" rx="7" fill="#1c1917"/>'
                . '<rect x="4.5" y="4.5" width="23" height="7.5" rx="1.5" fill="#6f675e"/>'
                . '<rect x="4.5" y="13.5" width="14" height="14" rx="1.5" fill="#f3efe6"/>'
                . '<rect x="20" y="13.5" width="7.5" height="14" rx="1.5" fill="#b7b1a8"/>'
                . '</svg></span>'
                . '<span class="brandtext"><span class="title-static">Inlay</span><span class="brandmaker">Adlexone</span></span>'
                . '</div>'
                . '<p class="empty-state__copy">' . htmlspecialchars($introCopy, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<div class="empty-state__actions"><a class="btn btn--primary" href="?manage=builder">Design an app</a></div>'
                . '</div></section>';
        }

        $html = '<div class="launchpad">'
            . $intro
            . self::launchGroup('Applications', $applications)
            . self::launchGroup('Design', $manage)
            . '</div>';

        if ($applications === [] && $manage === [] && $intro === '') {
            $html = RenderViews::buildEmptyState([
                'illustration' => 'empty-apps',
                'title' => 'Nothing is available for this account',
                'copy' => 'Ask an administrator to grant access to an application.',
            ]);
        }

        Page::current()->setBody($html);
    }
    /**
     * Once an application exists, home opens it. The launchpad is only for a new install.
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

        return (string) ($items[0]['href'] ?? '');
    }

    /**
     * Homepage. One tile per destination. Section menus stay inside each app.
     */
    public static function launchTile(string $href, string $title, string $hint, string $icon): string
    {
        $hrefEsc = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $hintEsc = htmlspecialchars($hint, ENT_QUOTES, 'UTF-8');
        $iconEsc = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');

        return '<a class="launch-tile" href="' . $hrefEsc . '">'
            . '<span class="launch-tile__icon" aria-hidden="true"><svg class="icon"><use href="' . htmlspecialchars(\Adlexone\Theme\Theme::spriteHref(), ENT_QUOTES, 'UTF-8') . '#' . $iconEsc . '"></use></svg></span>'
            . '<span class="launch-tile__copy">'
            . '<span class="launch-tile__title">' . $titleEsc . '</span>'
            . '<span class="launch-tile__hint">' . $hintEsc . '</span>'
            . '</span></a>';
    }

    /**
     * @param list<string> $tiles
     */
    public static function launchGroup(string $label, array $tiles): string
    {
        if ($tiles === []) {
            return '';
        }
        $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return '<section class="launchpad__group" aria-label="' . $labelEsc . '">'
            . '<h2 class="launchpad__heading">' . $labelEsc . '</h2>'
            . '<div class="launchpad__grid">' . implode('', $tiles) . '</div>'
            . '</section>';
    }
}
