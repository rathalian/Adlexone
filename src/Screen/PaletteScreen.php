<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\ApplicationSettings;
use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Capabilities;
use Adlexone\Http\Router;
use Adlexone\Http\TopNav;
use Adlexone\support\Database;

/**
 * The data behind the search and command box that opens with Ctrl K.
 *
 * Without a query it lists the applications, their screens, and the design pages the
 * person may use. With a query it lists records the person can open whose name matches.
 */
final class PaletteScreen
{
    public static function open(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        if ($query !== '') {
            WorkScreen::workJson(['records' => self::records($query)]);
        }

        WorkScreen::workJson(['commands' => self::commands()]);
    }

    /**
     * @return list<array{kind: string, label: string, group: string, href: string, icon: string}>
     */
    private static function commands(): array
    {
        $commands = [];
        foreach (ApplicationStore::menuItems() as $item) {
            $slug = (string) ($item['app'] ?? '');
            $app = ApplicationStore::findBySlug($slug);
            if ($app === null) {
                continue;
            }
            $commands[] = [
                'kind' => 'Applications',
                'label' => (string) $item['label'],
                'group' => (string) ($item['hint'] ?? ''),
                'href' => (string) $item['href'],
                'icon' => (string) $item['icon'],
            ];
            foreach (ApplicationStore::navigation((int) $app['application_id']) as $link) {
                if (!Capabilities::linkVisible($link)) {
                    continue;
                }
                $commands[] = [
                    'kind' => 'Screens',
                    'label' => (string) $link['label'],
                    'group' => (string) $app['name'],
                    'href' => Router::applicationUrl($slug, (int) $link['nav_id']),
                    'icon' => Capabilities::icon((string) $link['capability'], (string) $link['icon']),
                ];
            }
        }
        foreach (TopNav::build()['design'] as $item) {
            $commands[] = [
                'kind' => 'Design',
                'label' => (string) $item['label'],
                'group' => 'Design',
                'href' => (string) $item['href'],
                'icon' => (string) $item['icon'],
            ];
        }
        $account = TopNav::build()['account'];
        foreach ($account['links'] ?? [] as $item) {
            $commands[] = [
                'kind' => 'Account',
                'label' => (string) $item['label'],
                'group' => 'Account',
                'href' => (string) $item['href'],
                'icon' => (string) $item['icon'],
            ];
        }

        return $commands;
    }

    /**
     * @return list<array{kind: string, label: string, group: string, href: string, icon: string}>
     */
    private static function records(string $query): array
    {
        if (mb_strlen($query) < 2) {
            return [];
        }
        $typeToApp = [];
        foreach (ApplicationStore::menuItems() as $item) {
            $slug = (string) ($item['app'] ?? '');
            $type = ApplicationSettings::defaultItemTypeId($slug);
            if ($slug !== '' && ctype_digit($type) && !isset($typeToApp[(int) $type])) {
                $typeToApp[(int) $type] = $item;
            }
        }
        $ids = WorkScreen::applicationAccessibleItemIds();
        if ($typeToApp === [] || $ids === []) {
            return [];
        }
        $rows = Database::rows(
            'SELECT item_id, item_title, item_type_id FROM items WHERE item_id IN (' . implode(',', array_map('intval', $ids))
            . ') AND item_type_id IN (' . implode(',', array_map('intval', array_keys($typeToApp)))
            . ') AND item_title LIKE ? ESCAPE \'\\\' ORDER BY item_id DESC LIMIT 8',
            ['%' . Database::escapeLike($query) . '%']
        );
        $out = [];
        foreach ($rows as $row) {
            $app = $typeToApp[(int) $row['item_type_id']] ?? null;
            if ($app === null) {
                continue;
            }
            $title = trim((string) $row['item_title']);
            $out[] = [
                'kind' => 'Records',
                'label' => $title !== '' ? $title : 'Record ' . (int) $row['item_id'],
                'group' => (string) $app['label'],
                'href' => '?application=' . rawurlencode((string) $app['app']) . '&item=' . (int) $row['item_id'],
                'icon' => 'ic-file-search',
            ];
        }

        return $out;
    }
}
