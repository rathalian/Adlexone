<?php
declare(strict_types=1);

namespace Adlexone\Html;

use Adlexone\Application\ApplicationSettings;
use Adlexone\support\Database;

/**
 * The band above an application's screens: its icon and name, where the person is,
 * a few figures, and the one action they most often want.
 */
final class AppHero
{
    /**
     * @param array<string, mixed> $app
     * @param string $createHref link to the screen that adds a record, or an empty string
     */
    public static function render(array $app, string $createHref, string $section = '', int $itemId = 0): string
    {
        $slug = (string) $app['slug'];
        $accent = Accent::forApp($app);
        $typeId = (int) ApplicationSettings::defaultItemTypeId($slug);
        $count = 0;
        $touched = 0;
        $week = 0;
        if ($typeId > 0) {
            $row = Database::rows(
                'SELECT COUNT(*) AS n, MAX(CASE WHEN core_log_updated > create_date THEN core_log_updated ELSE create_date END) AS touched, '
                . 'SUM(CASE WHEN create_date >= ? THEN 1 ELSE 0 END) AS fresh FROM items WHERE item_type_id = ?',
                [time() - 7 * 86400, $typeId]
            );
            $count = (int) ($row[0]['n'] ?? 0);
            $touched = (int) ($row[0]['touched'] ?? 0);
            $week = (int) ($row[0]['fresh'] ?? 0);
        }

        $crumbs = '<nav class="crumbs" aria-label="Breadcrumb"><a href="./">Home</a>'
            . '<span class="crumbs__sep">' . Charts::icon('ic-chevron-right') . '</span>';
        $base = \Adlexone\Http\Router::applicationUrl($slug);
        if ($itemId > 0 || $section !== '') {
            $crumbs .= '<a href="' . Html::e($base) . '">' . Html::e((string) $app['name']) . '</a>';
            if ($itemId > 0) {
                $title = \Adlexone\Screen\ItemScreen::userCanAccessItem($itemId)
                    ? Database::first('items', ['item_title'], 'item_id = ?', [$itemId])
                    : null;
                $name = trim((string) ($title['item_title'] ?? ''));
                $crumbs .= '<span class="crumbs__sep">' . Charts::icon('ic-chevron-right') . '</span>'
                    . '<span aria-current="page">' . Html::e($name !== '' ? $name : 'Record ' . $itemId) . '</span>';
            } else {
                $crumbs .= '<span class="crumbs__sep">' . Charts::icon('ic-chevron-right') . '</span>'
                    . '<span aria-current="page">' . Html::e($section) . '</span>';
            }
        } else {
            $crumbs .= '<span aria-current="page">' . Html::e((string) $app['name']) . '</span>';
        }
        $crumbs .= '</nav>';

        $facts = '';
        if ($typeId > 0) {
            $facts .= '<li><strong>' . number_format($count) . '</strong><span>' . ($count === 1 ? 'record' : 'records') . '</span></li>'
                . '<li><strong>' . number_format($week) . '</strong><span>added this week</span></li>';
            if ($touched > 0) {
                $facts .= '<li><strong>' . Html::e(Presence::ago($touched)) . '</strong><span>last change</span></li>';
            }
        }

        $hint = trim((string) ($app['hint'] ?? ''));

        return '<header class="apphero" style="' . Html::e(Accent::style($accent)) . '">'
            . '<div class="apphero__inner">'
            . '<span class="apphero__icon">' . Charts::icon((string) ($app['icon'] ?? 'ic-grid')) . '</span>'
            . '<div class="apphero__copy">' . $crumbs
            . '<p class="apphero__name">' . Html::e((string) $app['name']) . '</p>'
            . ($hint !== '' ? '<p class="apphero__hint">' . Html::e($hint) . '</p>' : '') . '</div>'
            . ($facts !== '' ? '<ul class="apphero__facts">' . $facts . '</ul>' : '')
            . ($createHref !== '' ? '<a class="btn btn--glass apphero__action" href="' . Html::e($createHref) . '">' . Charts::icon('ic-plus') . 'New record</a>' : '')
            . '</div></header>';
    }
}
