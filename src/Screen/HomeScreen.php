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

        $intro = '';
        if ($menu === [] && Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $intro = View::partialToString('launchpad_intro', [
                'copy' => defined('SET_HOME_LEAD') && trim((string) SET_HOME_LEAD) !== ''
                    ? (string) SET_HOME_LEAD
                    : 'This site keeps the applications, records, and rules for the work.',
                'art' => Theme::illustrationHref('empty-apps'),
            ]);
        }

        $html = HomeDashboard::render($menu, $intro);

        if ($menu === [] && $intro === '') {
            $html = Blocks::emptyState([
                'illustration' => 'empty-apps',
                'title' => 'Nothing is available for this account',
                'copy' => 'Ask an administrator to grant access to an application.',
            ]);
        }

        Page::current()->setBody($html);
    }

    /**
     * The recent activity list for the home page, as JSON. The page asks for it when
     * the person first opens that panel.
     */
    public static function activity(): void
    {
        $menu = \Adlexone\Application\ApplicationStore::menuItems();
        $scope = (string) ($_GET['scope'] ?? '');

        WorkScreen::workJson(['html' => HomeDashboard::activityHtml($menu, $scope)]);
    }
    /**
     * Every record assigned to the person, as JSON, for the Show all link on the home page.
     */
    public static function assigned(): void
    {
        WorkScreen::workJson(['html' => HomeDashboard::assignedHtml(\Adlexone\Application\ApplicationStore::menuItems())]);
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
}
