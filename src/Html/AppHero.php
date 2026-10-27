<?php
declare(strict_types=1);

namespace Adlexone\Html;

use Adlexone\Application\ApplicationSettings;
use Adlexone\support\Database;

/**
 * The band above an application's screens: its icon and name, the breadcrumb that says where the person is,
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

        $facts = '';
        if ($typeId > 0) {
            $facts .= '<li><strong>' . number_format($count) . '</strong><span>' . ($count === 1 ? 'record' : 'records') . '</span></li>'
                . '<li><strong>' . number_format($week) . '</strong><span>added this week</span></li>';
            if ($touched > 0) {
                $facts .= '<li><strong>' . Html::e(Presence::ago($touched)) . '</strong><span>last change</span></li>';
            }
        }

        $hint = trim((string) ($app['hint'] ?? ''));
        $guide = trim((string) ($app['guide'] ?? ''));
        $tools = '';
        if ($guide !== '') {
            $tools .= '<button type="button" class="btn btn--glass apphero__help" data-sheet-pop aria-haspopup="dialog" aria-controls="app-guide">'
                . Charts::icon('ic-info')
                . '<span class="apphero__help-label">How to use</span></button>';
        }
        if ($createHref !== '') {
            $tools .= '<a class="btn btn--glass apphero__action' . ($itemId === 0 && $section !== 'New' ? ' apphero__action--fab' : '') . '" href="' . Html::e($createHref) . '">' . Charts::icon('ic-plus') . 'New record</a>';
        }

        return '<header class="apphero" style="' . Html::e(Accent::style($accent)) . '">'
            . '<div class="apphero__inner">'
            . '<span class="apphero__icon">' . Charts::icon((string) ($app['icon'] ?? 'ic-grid')) . '</span>'
            . '<div class="apphero__copy">' . Breadcrumb::BAND_SLOT
            . '<p class="apphero__name">' . Html::e((string) $app['name']) . '</p>'
            . ($hint !== '' ? '<p class="apphero__hint">' . Html::e($hint) . '</p>' : '') . '</div>'
            . ($facts !== '' ? '<ul class="apphero__facts">' . $facts . '</ul>' : '')
            . ($tools !== '' ? '<div class="apphero__tools">' . $tools . '</div>' : '')
            . '</div></header>'
            . self::guideDialog($guide);
    }

    private static function guideDialog(string $guide): string
    {
        if ($guide === '') {
            return '';
        }
        $body = '';
        foreach (preg_split("/\n\s*\n/", $guide) ?: [] as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            $body .= '<p class="guide__p">' . nl2br(Html::e($block), false) . '</p>';
        }

        return '<dialog class="sheet-pop" id="app-guide" aria-labelledby="app-guide-title">'
            . '<div class="sheet-pop__panel">'
            . '<div class="sheet-pop__head"><h2 class="sheet-pop__title" id="app-guide-title">How to use</h2>'
            . '<button type="button" class="btn btn--quiet btn--sm sheet-pop__close" data-sheet-pop-close>Close</button></div>'
            . '<div class="sheet-pop__body">' . $body . '</div>'
            . '<div class="sheet-pop__actions"><button type="button" class="btn btn--primary" data-sheet-pop-close>Done</button></div>'
            . '</div></dialog>';
    }
}
