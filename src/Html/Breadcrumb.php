<?php
declare(strict_types=1);

namespace Adlexone\Html;

use Adlexone\Http\Router;
use Adlexone\support\Database;

/**
 * The one breadcrumb used by every screen.
 *
 * A trail is a list of crumbs. Each crumb has a label and, when the person can go there, an href.
 * The last crumb is the current page and is never a link. A crumb without an href that is not
 * last is a group name, such as Configure, which has no page of its own.
 *
 * Screens do not print crumbs. They set the trail on the Page and the layout places it:
 * inside the application band, in the section bar, or above the page heading.
 *
 * @phpstan-type Crumb array{label: string, href?: string}
 */
final class Breadcrumb
{
    /** Stands in for the crumbs inside the application band until the page is sent. */
    public const BAND_SLOT = '<!--crumbs:band-->';

    /** Stands in for the crumbs inside the section bar until the page is sent. */
    public const BAR_SLOT = '<!--crumbs:bar-->';

    /**
     * A trail that starts at Home.
     *
     * @param list<array{label: string, href?: string}> $after
     * @return list<array{label: string, href?: string}>
     */
    public static function trail(array $after): array
    {
        return array_merge([['label' => 'Home', 'href' => './']], self::clean($after));
    }

    /**
     * Home, the application, and then the section or record the person is on.
     *
     * @param array<string, mixed> $app
     * @return list<array{label: string, href?: string}>
     */
    public static function forApplication(array $app, string $section = '', int $itemId = 0): array
    {
        $name = (string) $app['name'];
        $crumbs = [['label' => $name, 'href' => Router::applicationUrl((string) $app['slug'])]];
        if ($itemId > 0) {
            $title = \Adlexone\Screen\ItemScreen::userCanAccessItem($itemId)
                ? Database::first('items', ['item_title'], 'item_id = ?', [$itemId])
                : null;
            $record = trim((string) ($title['item_title'] ?? ''));
            $crumbs[] = ['label' => $record !== '' ? $record : 'Record ' . $itemId];
        } elseif ($section !== '') {
            $crumbs[] = ['label' => $section];
        }

        return self::trail($crumbs);
    }

    /**
     * @param list<array{label: string, href?: string}> $trail
     * @param string $surface where the trail is drawn: band (on the application colour), bar (section bar), or page
     */
    public static function render(array $trail, string $surface = 'page'): string
    {
        $trail = self::clean($trail);
        if ($trail === []) {
            return '';
        }
        $class = 'crumbs crumbs--' . ($surface === 'band' ? 'band' : 'page') . ($surface === 'bar' ? ' sectionbar__crumbs' : '');
        $last = count($trail) - 1;
        $html = '<nav class="' . $class . '" aria-label="Breadcrumb"><ol class="crumbs__list">';
        foreach ($trail as $i => $crumb) {
            $label = Html::e($crumb['label']);
            $href = (string) ($crumb['href'] ?? '');
            $html .= '<li class="crumbs__item">';
            if ($i > 0) {
                $html .= '<span class="crumbs__sep" aria-hidden="true">' . Charts::icon('ic-chevron-right') . '</span>';
            }
            if ($i === $last) {
                $html .= '<span aria-current="page">' . $label . '</span>';
            } elseif ($href !== '') {
                $html .= '<a href="' . Html::e($href) . '">' . $label . '</a>';
            } else {
                $html .= '<span>' . $label . '</span>';
            }
            $html .= '</li>';
        }

        return $html . '</ol></nav>';
    }

    /**
     * Swaps the slot markers in a block of page chrome for the rendered trail.
     *
     * @param list<array{label: string, href?: string}> $trail
     */
    public static function fill(string $html, array $trail): string
    {
        if ($html === '' || !str_contains($html, '<!--crumbs:')) {
            return $html;
        }

        return strtr($html, [
            self::BAND_SLOT => self::render($trail, 'band'),
            self::BAR_SLOT => self::render($trail, 'bar'),
        ]);
    }

    /**
     * @param list<array{label: string, href?: string}> $trail
     * @return list<array{label: string, href?: string}>
     */
    private static function clean(array $trail): array
    {
        $clean = [];
        foreach ($trail as $crumb) {
            $label = trim((string) ($crumb['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $item = ['label' => $label];
            $href = trim((string) ($crumb['href'] ?? ''));
            if ($href !== '') {
                $item['href'] = $href;
            }
            $clean[] = $item;
        }

        return $clean;
    }
}
