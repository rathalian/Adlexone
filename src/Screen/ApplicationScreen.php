<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\ApplicationSettings;
use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Capabilities;
use Adlexone\Application\ScreenCatalog;
use Adlexone\Auth\Access;
use Adlexone\Html\Blocks;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Http\Router;
use Adlexone\Http\View;
use Adlexone\support\Database;

/**
 * Screen formerly loaded from legacy/.
 */
final class ApplicationScreen
{
    public static function open(): void
    {
        $slug = Request::query('app');
        if ($slug === '') {
            $slug = trim((string) ($_SESSION['application_slug'] ?? ''));
        }

        $app = $slug === '' ? null : ApplicationStore::findBySlug($slug);
        if ($app === null || !$app['enabled']) {
            View::respond('This application is not available.');
            return;
        }
        if (!Access::can((string) $app['permission'])) {
            Access::deny();
        }

        $visible = [];
        foreach (ApplicationStore::navigation((int) $app['application_id']) as $link) {
            if (Capabilities::linkVisible($link)) {
                $visible[] = $link;
            }
        }
        if ($visible === []) {
            View::respond('This application has no navigation yet. Add links under Configure, Applications.');
            return;
        }

        if (Request::has('item')) {
            $itemId = (int) Request::query('item');
            $item = $itemId > 0
                ? Database::first('items', ['item_id', 'item_type_id'], 'item_id = ?', [$itemId])
                : null;
            $slug = (string) $app['slug'];
            if ($item === null || !ApplicationSettings::allowsItemType($slug, (int) ($item['item_type_id'] ?? 0))) {
                $name = (string) $app['name'];
                View::respond(
                    'This record is not in ' . $name . '.',
                    Blocks::link(Router::applicationUrl($slug), 'Back to ' . $name, '', 'btn btn--primary btn--sm')
                );
                return;
            }
        }

        $requested = (int) Request::query('nav');
        $current = $visible[0];
        if ($requested > 0) {
            $current = null;
            foreach ($visible as $link) {
                if ((int) $link['nav_id'] === $requested) {
                    $current = $link;
                    break;
                }
            }
            if ($current === null) {
                Access::deny();
            }
        } else {
            $sessionNav = (int) ($_SESSION['application_nav_id'] ?? 0);
            foreach ($visible as $link) {
                if ((int) $link['nav_id'] === $sessionNav) {
                    $current = $link;
                    break;
                }
            }
        }
        if (Request::has('item')) {
            foreach ($visible as $link) {
                if (ScreenCatalog::resolve((string) $link['capability']) === ScreenCatalog::WORK) {
                    $current = $link;
                    break;
                }
            }
        }
        $_SESSION['application_nav_id'] = (int) $current['nav_id'];
        Page::current()->suggestNavLabel((string) $current['label']);

        Request::set('nav', (string) $current['nav_id']);
        Page::current()->enterApplication((string) $app['slug'], (int) $current['nav_id']);
        Capabilities::sectionNavigation($app, $visible);

        $engine = \Adlexone\Http\Router::engine();
        if ($engine !== null) {
            Capabilities::forwardShared($app, (int) $current['nav_id'], $engine);
            return;
        }

        Capabilities::open($app, $current);
    }
}
