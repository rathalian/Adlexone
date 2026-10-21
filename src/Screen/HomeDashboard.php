<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\RecordQuery;
use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Html\Accent;
use Adlexone\Html\Charts;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;
use Adlexone\Html\Timeline;
use Adlexone\support\Database;
use Adlexone\Theme\Theme;

/**
 * The signed-in home page: a greeting, a few figures, the applications with a pulse
 * for each, what is assigned to the person, and what changed lately.
 */
final class HomeDashboard
{
    private const WEEKS = 8;

    /** Rows kept visible on a phone before a list folds. */
    private const PREVIEW = 3;

    /**
     * @param list<array<string, mixed>> $menu items from ApplicationStore::menuItems()
     * @param list<array{href: string, title: string, hint: string, icon: string}> $manage
     */
    public static function render(array $menu, array $manage, string $intro): string
    {
        $apps = self::apps($menu);
        $typeIds = array_values(array_filter(array_map(static fn (array $app): int => $app['type'], $apps)));
        $typeToApp = [];
        foreach ($apps as $index => $app) {
            if ($app['type'] > 0 && !isset($typeToApp[$app['type']])) {
                $typeToApp[$app['type']] = $index;
            }
        }

        $stats = self::stats($typeIds);
        $weekly = self::weekly($typeIds);
        $owners = self::owners($typeIds);
        foreach ($apps as $index => $app) {
            $type = $app['type'];
            $apps[$index]['count'] = (int) ($stats[$type]['n'] ?? 0);
            $apps[$index]['touched'] = (int) ($stats[$type]['touched'] ?? 0);
            $apps[$index]['series'] = $weekly[$type] ?? array_fill(0, self::WEEKS, 0);
            $apps[$index]['owners'] = $owners[$type] ?? [];
        }

        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $assigned = self::assigned($userId, $typeToApp, $apps);
        $records = array_sum(array_column($apps, 'count'));
        $series = array_fill(0, self::WEEKS, 0);
        foreach ($apps as $app) {
            foreach ($app['series'] as $week => $n) {
                $series[$week] += $n;
            }
        }

        $html = '<div class="home">'
            . self::hero(count($apps), $records, $assigned['total'])
            . self::status(count($apps), $records, $series)
            . self::kpis(count($apps), $records, $assigned['total'], $series)
            . self::notice($apps)
            . self::checklist()
            . $intro
            . self::applications($apps);

        $columns = self::assignedPanel($assigned['items'], $assigned['total']) . self::activityPanel($typeIds, $typeToApp, $apps);
        if ($columns !== '') {
            $html .= '<div class="home__split">' . $columns . '</div>';
        }

        return $html . self::design($manage) . '</div>';
    }

    /**
     * @param list<array<string, mixed>> $menu
     * @return list<array<string, mixed>>
     */
    private static function apps(array $menu): array
    {
        $apps = [];
        foreach ($menu as $item) {
            $slug = (string) ($item['app'] ?? '');
            $hint = trim((string) ($item['hint'] ?? ''));
            $apps[] = [
                'slug' => $slug,
                'name' => (string) $item['label'],
                'hint' => $hint !== '' ? $hint : '',
                'icon' => (string) $item['icon'],
                'href' => (string) $item['href'],
                'type' => self::typeId($slug),
                'accent' => Accent::forSlug($slug),
                'count' => 0,
                'touched' => 0,
                'series' => [],
                'owners' => [],
            ];
        }

        return $apps;
    }

    private static function hero(int $apps, int $records, int $assigned): string
    {
        $hour = (int) date('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $name = trim((string) ($_SESSION['access_display_name'] ?? $_SESSION['access_user_name'] ?? ''));
        $first = $name !== '' ? (string) (preg_split('/\s+/', $name)[0] ?? '') : '';
        $lead = $assigned > 0
            ? 'You have ' . self::plural($assigned, 'record') . ' assigned to you.'
            : 'Nothing is assigned to you right now.';
        $lead .= ' This site has ' . self::plural($apps, 'application') . ' and ' . self::plural($records, 'record') . '.';

        $actions = '<button type="button" class="btn btn--primary" data-palette-open>'
            . Charts::icon('ic-search') . 'Search<kbd class="kbd">Ctrl K</kbd></button>';
        if (Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $actions .= '<a class="btn btn--glass" href="?manage=builder">' . Charts::icon('ic-plus') . 'Create an application</a>';
        }

        return '<header class="homehero">'
            . '<div class="homehero__copy">'
            . '<p class="homehero__date">' . Html::e(date('l j F')) . '</p>'
            . '<h1 class="homehero__title">' . Html::e($greeting . ($first !== '' ? ', ' . $first : '')) . '</h1>'
            . '<p class="homehero__lead">' . Html::e($lead) . '</p></div>'
            . '<div class="homehero__actions">' . $actions . '</div>'
            . '</header>';
    }

    /**
     * One line that stands in for the figures on a phone.
     *
     * @param list<int> $series
     */
    private static function status(int $apps, int $records, array $series): string
    {
        $month = array_sum(array_slice($series, -4));

        return '<p class="home__status">' . Html::e(
            self::plural($apps, 'application') . ', ' . self::plural($records, 'record') . ', ' . $month . ' added in four weeks'
        ) . '</p>';
    }

    /**
     * @param list<int> $series
     */
    private static function kpis(int $apps, int $records, int $assigned, array $series): string
    {
        $recent = array_sum(array_slice($series, -1));
        $month = array_sum(array_slice($series, -4));

        return Charts::kpis([
            ['label' => 'Applications', 'value' => (string) $apps, 'note' => 'Open to you', 'icon' => 'ic-grid', 'tone' => 'neutral'],
            ['label' => 'Records', 'value' => (string) $records, 'note' => 'In your applications', 'icon' => 'ic-files', 'tone' => 'brand', 'trend' => $series],
            ['label' => 'Assigned to you', 'value' => (string) $assigned, 'note' => $assigned === 0 ? 'Nothing waiting' : 'Waiting on you', 'icon' => 'ic-user-check', 'tone' => $assigned > 0 ? 'warning' : 'success'],
            ['label' => 'Added in four weeks', 'value' => (string) $month, 'note' => self::plural($recent, 'record') . ' this week', 'icon' => 'ic-trend-up', 'tone' => 'success'],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $apps
     */
    private static function notice(array $apps): string
    {
        $slugs = array_values(array_filter(array_column($apps, 'slug')));
        if ($slugs === [] || !Database::tableExists('announcements')) {
            return '';
        }
        $marks = implode(',', array_fill(0, count($slugs), '?'));
        $rows = Database::rows(
            'SELECT subject, application, time FROM announcements WHERE application = \'\' OR application IN (' . $marks . ') ORDER BY time DESC LIMIT 1',
            $slugs
        );
        $subject = trim((string) ($rows[0]['subject'] ?? ''));
        if ($subject === '') {
            return '';
        }
        $app = (string) ($rows[0]['application'] ?? '');
        $href = $app !== '' ? '?application=' . rawurlencode($app) : './';

        return '<aside class="banner" aria-label="Announcement">'
            . '<span class="banner__icon">' . Charts::icon('ic-announce') . '</span>'
            . '<p class="banner__text"><strong>Announcement</strong> <a href="' . Html::e($href) . '">' . Html::e($subject) . '</a></p>'
            . '</aside>';
    }

    private static function checklist(): string
    {
        if (!Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            return '';
        }
        $steps = [
            ['Create an application', 'An application groups the screens for one kind of work.', '?manage=builder', Database::count('applications') > 0],
            ['Add fields to a record type', 'Fields hold the details a record keeps.', '?manage=fields', Database::tableExists('custom_fields') && Database::count('custom_fields') > 0],
            ['Create a record', 'Open an application and add the first record.', './', Database::count('items') > 0],
            ['Add people', 'Add the people who need to sign in, then place them in groups.', '?manage=security&option=manage_users', Database::count('users') > 1],
        ];
        $done = count(array_filter($steps, static fn (array $step): bool => $step[3]));
        if ($done === count($steps)) {
            return '';
        }
        $percent = (int) round($done / count($steps) * 100);
        $list = '';
        $next = false;
        $hidden = 0;
        foreach ($steps as [$title, $copy, $href, $complete]) {
            $extra = $complete || $next;
            if (!$complete) {
                $next = true;
            }
            $hidden += $extra ? 1 : 0;
            $list .= '<li class="checklist__step' . ($complete ? ' is-done' : '') . ($extra ? ' is-extra' : '') . '">'
                . '<span class="checklist__tick">' . Charts::icon('ic-check') . '</span>'
                . '<span class="checklist__text"><a href="' . Html::e($href) . '">' . Html::e($title) . '</a><small>' . Html::e($copy) . '</small></span></li>';
        }

        return '<section class="checklist" data-dismissible="setup" data-fold aria-label="Set up this site">'
            . '<header class="checklist__head"><div><h2 class="checklist__title">Set up this site</h2>'
            . '<p class="checklist__lead">' . $done . ' of ' . count($steps) . ' steps are done.</p></div>'
            . '<div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $percent . '"><span style="--w:' . $percent . '%"></span></div>'
            . '<button type="button" class="iconbtn" data-dismiss aria-label="Hide this checklist">' . Charts::icon('ic-x') . '</button></header>'
            . '<ol class="checklist__steps">' . $list . '</ol>'
            . self::foldButton('Show all steps', 'Show the next step only', $hidden)
            . '</section>';
    }

    /**
     * @param list<array<string, mixed>> $apps
     */
    private static function applications(array $apps): string
    {
        if ($apps === []) {
            return '';
        }
        $cards = '';
        foreach ($apps as $app) {
            $stat = $app['type'] > 0 ? self::plural((int) $app['count'], 'record') : '';
            $hasSeries = array_sum($app['series']) > 0;
            $when = $app['touched'] > 0 ? 'Updated ' . Presence::ago((int) $app['touched']) : ($app['type'] > 0 ? 'No records yet' : '');
            $cards .= '<a class="appcard" href="' . Html::e((string) $app['href']) . '" style="' . Html::e(Accent::style((string) $app['accent'])) . '">'
                . '<span class="appcard__top"><span class="appcard__icon">' . Charts::icon((string) $app['icon']) . '</span>'
                . '<span class="appcard__go" aria-hidden="true">' . Charts::icon('ic-arrow-right') . '</span></span>'
                . '<span class="appcard__name">' . Html::e((string) $app['name']) . '</span>'
                . ($app['hint'] !== '' ? '<span class="appcard__hint">' . Html::e((string) $app['hint']) . '</span>' : '')
                . '<span class="appcard__figures">'
                . ($stat !== '' ? '<span class="appcard__count">' . Html::e($stat) . '</span>' : '')
                . ($hasSeries ? '<span class="appcard__spark" title="Records added over the last ' . self::WEEKS . ' weeks">' . Charts::sparkline($app['series'], 96, 28) . '</span>' : '')
                . '</span>'
                . '<span class="appcard__foot">' . Presence::stack($app['owners'], 3)
                . ($when !== '' ? '<span class="appcard__when">' . Html::e($when) . '</span>' : '') . '</span>'
                . '</a>';
        }

        return '<section class="home__section home__section--apps" aria-label="Applications">'
            . '<div class="home__heading"><h2>Applications</h2></div>'
            . '<div class="appgrid">' . $cards . '</div></section>';
    }

    /**
     * @param list<array{item_id: int, title: string, app: int, when: int}> $items
     */
    private static function assignedPanel(array $items, int $total): string
    {
        $art = Theme::illustrationHref('empty-assigned');
        if ($items === []) {
            $body = '<div class="panel__empty">'
                . ($art !== '' ? '<img src="' . Html::e($art) . '" alt="" width="88" height="66" loading="lazy">' : '')
                . '<p>Nothing is assigned to you. Records you own are listed here.</p></div>';
        } else {
            $body = '<ul class="rows">';
            foreach ($items as $index => $item) {
                $body .= '<li' . ($index >= self::PREVIEW ? ' class="is-extra"' : '') . '><a class="rows__link" href="' . Html::e($item['href']) . '" style="' . Html::e(Accent::style($item['accent'])) . '">'
                    . '<span class="rows__dot"></span><span class="rows__main"><span class="rows__title">' . Html::e($item['title']) . '</span>'
                    . '<span class="rows__meta">' . Html::e($item['app']) . '</span></span>'
                    . '<span class="rows__when">' . Html::e(Presence::ago($item['when'])) . '</span></a></li>';
            }
            $body .= '</ul>' . self::foldButton('Show ' . (count($items) - self::PREVIEW) . ' more', 'Show fewer', count($items) - self::PREVIEW);
        }
        $count = $total > 0 ? '<span class="panel__count">' . $total . '</span>' : '';

        return '<section class="panel panel--assigned" data-fold aria-label="Assigned to you"><header class="panel__head"><h2>Assigned to you</h2>' . $count . '</header>' . $body . '</section>';
    }

    /**
     * @param list<int> $typeIds
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     */
    private static function activityPanel(array $typeIds, array $typeToApp, array $apps): string
    {
        if ($typeIds === []) {
            return '';
        }
        $list = implode(',', array_map('intval', $typeIds));
        $visible = '';
        $visibleLog = '';
        if ((int) ($_SESSION['access_role_id'] ?? 5) > 2) {
            $ids = \Adlexone\Screen\WorkScreen::applicationAccessibleItemIds();
            if ($ids === []) {
                return '';
            }
            $visible = ' AND item_id IN (' . implode(',', array_map('intval', $ids)) . ')';
            $visibleLog = ' AND i.item_id IN (' . implode(',', array_map('intval', $ids)) . ')';
        }
        $events = [];
        $lines = Database::rows(
            'SELECT l.item_id, l.log_text, CAST(l.create_date AS INTEGER) AS at, l.security_id, i.item_type_id FROM core_log l '
            . 'JOIN items i ON i.item_id = l.item_id WHERE i.item_type_id IN (' . $list . ')' . $visibleLog . ' '
            . 'ORDER BY CAST(l.create_date AS INTEGER) DESC LIMIT 12'
        );
        $names = RecordQuery::userNames(array_map(static fn (array $row): int => (int) ($row['security_id'] ?? 0), $lines));
        foreach ($lines as $line) {
            $app = $apps[$typeToApp[(int) $line['item_type_id']] ?? -1] ?? null;
            $text = trim((string) ($line['log_text'] ?? ''));
            if ($app === null || $text === '' || (int) $line['at'] <= 0) {
                continue;
            }
            $events[] = [
                'text' => $text,
                'when' => (int) $line['at'],
                'who' => $names[(int) ($line['security_id'] ?? 0)] ?? '',
                'href' => '?application=' . rawurlencode((string) $app['slug']) . '&item=' . (int) $line['item_id'],
                'icon' => 'ic-activity',
                'detail' => (string) $app['name'],
            ];
        }
        $created = Database::rows(
            'SELECT item_id, item_title, item_type_id, create_date, creator_security FROM items WHERE item_type_id IN (' . $list . ')' . $visible . ' ORDER BY create_date DESC LIMIT 8'
        );
        $creators = RecordQuery::userNames(array_map(static fn (array $row): int => (int) ($row['creator_security'] ?? 0), $created));
        foreach ($created as $row) {
            $app = $apps[$typeToApp[(int) $row['item_type_id']] ?? -1] ?? null;
            if ($app === null || (int) $row['create_date'] <= 0) {
                continue;
            }
            $title = trim((string) $row['item_title']);
            $events[] = [
                'text' => 'Created ' . ($title !== '' ? $title : 'record ' . (int) $row['item_id']),
                'when' => (int) $row['create_date'],
                'who' => $creators[(int) ($row['creator_security'] ?? 0)] ?? '',
                'href' => '?application=' . rawurlencode((string) $app['slug']) . '&item=' . (int) $row['item_id'],
                'icon' => 'ic-file-plus',
                'detail' => (string) $app['name'],
            ];
        }
        usort($events, static fn (array $left, array $right): int => $right['when'] <=> $left['when']);

        $shown = array_slice($events, 0, 7);
        $more = max(0, count($shown) - self::PREVIEW);

        return '<section class="panel panel--activity" data-fold aria-label="Recent activity"><header class="panel__head"><h2>Recent activity</h2></header>'
            . Timeline::render($shown, 'No activity yet. Changes to records are listed here.', self::PREVIEW)
            . ($more > 0 ? self::foldButton('Show ' . $more . ' more', 'Show fewer', $more) : '') . '</section>';
    }

    /**
     * @param list<array{href: string, title: string, hint: string, icon: string}> $manage
     */
    private static function design(array $manage): string
    {
        if ($manage === []) {
            return '';
        }
        $tiles = '';
        foreach ($manage as $tile) {
            $tiles .= '<a class="toolchip" href="' . Html::e($tile['href']) . '" title="' . Html::e($tile['hint']) . '">'
                . '<span class="toolchip__icon">' . Charts::icon($tile['icon']) . '</span>'
                . '<span class="toolchip__text"><strong>' . Html::e($tile['title']) . '</strong><small>' . Html::e($tile['hint']) . '</small></span></a>';
        }

        return '<section class="home__section home__section--design" data-fold aria-label="Design">'
            . '<div class="home__heading"><h2>Design</h2>' . self::foldButton('Show', 'Hide', 1, 'foldbtn--inline') . '</div>'
            . '<div class="toolgrid is-extra">' . $tiles . '</div></section>';
    }

    /**
     * @param list<int> $typeIds
     * @return array<int, array{n: int, touched: int}>
     */
    private static function stats(array $typeIds): array
    {
        if ($typeIds === []) {
            return [];
        }
        $out = [];
        $rows = Database::rows(
            'SELECT item_type_id, COUNT(*) AS n, MAX(CASE WHEN core_log_updated > create_date THEN core_log_updated ELSE create_date END) AS touched '
            . 'FROM items WHERE item_type_id IN (' . implode(',', array_map('intval', $typeIds)) . ') GROUP BY item_type_id'
        );
        foreach ($rows as $row) {
            $out[(int) $row['item_type_id']] = ['n' => (int) $row['n'], 'touched' => (int) $row['touched']];
        }

        return $out;
    }

    /**
     * Records added per week for the last eight weeks, oldest first.
     *
     * @param list<int> $typeIds
     * @return array<int, list<int>>
     */
    private static function weekly(array $typeIds): array
    {
        if ($typeIds === []) {
            return [];
        }
        $now = time();
        $out = [];
        $rows = Database::rows(
            'SELECT item_type_id, CAST((? - create_date) / 604800 AS INTEGER) AS wk, COUNT(*) AS n FROM items '
            . 'WHERE item_type_id IN (' . implode(',', array_map('intval', $typeIds)) . ') AND create_date >= ? GROUP BY item_type_id, wk',
            [$now, $now - self::WEEKS * 604800]
        );
        foreach ($rows as $row) {
            $type = (int) $row['item_type_id'];
            $out[$type] ??= array_fill(0, self::WEEKS, 0);
            $slot = self::WEEKS - 1 - (int) $row['wk'];
            if ($slot >= 0 && $slot < self::WEEKS) {
                $out[$type][$slot] += (int) $row['n'];
            }
        }

        return $out;
    }

    /**
     * @param list<int> $typeIds
     * @return array<int, list<string>>
     */
    private static function owners(array $typeIds): array
    {
        if ($typeIds === []) {
            return [];
        }
        $rows = Database::rows(
            'SELECT item_type_id, user_security, COUNT(*) AS n FROM items WHERE user_security > 0 AND item_type_id IN ('
            . implode(',', array_map('intval', $typeIds)) . ') GROUP BY item_type_id, user_security ORDER BY n DESC'
        );
        $names = RecordQuery::userNames(array_map(static fn (array $row): int => (int) $row['user_security'], $rows));
        $out = [];
        foreach ($rows as $row) {
            $name = $names[(int) $row['user_security']] ?? '';
            if ($name !== '') {
                $out[(int) $row['item_type_id']][] = $name;
            }
        }

        return $out;
    }

    /**
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     * @return array{total: int, items: list<array{item_id: int, title: string, app: string, accent: string, href: string, when: int}>}
     */
    private static function assigned(int $userId, array $typeToApp, array $apps): array
    {
        if ($userId <= 0 || $typeToApp === []) {
            return ['total' => 0, 'items' => []];
        }
        $list = implode(',', array_map('intval', array_keys($typeToApp)));
        $total = (int) (Database::rows('SELECT COUNT(*) AS n FROM items WHERE user_security = ? AND item_type_id IN (' . $list . ')', [$userId])[0]['n'] ?? 0);
        $rows = Database::rows(
            'SELECT item_id, item_title, item_type_id, create_date, core_log_updated FROM items WHERE user_security = ? AND item_type_id IN (' . $list . ') '
            . 'ORDER BY CASE WHEN core_log_updated > create_date THEN core_log_updated ELSE create_date END DESC LIMIT 6',
            [$userId]
        );
        $items = [];
        foreach ($rows as $row) {
            $app = $apps[$typeToApp[(int) $row['item_type_id']] ?? -1] ?? null;
            if ($app === null) {
                continue;
            }
            $title = trim((string) $row['item_title']);
            $when = max((int) $row['create_date'], (int) ($row['core_log_updated'] ?? 0));
            $items[] = [
                'item_id' => (int) $row['item_id'],
                'title' => $title !== '' ? $title : 'Record ' . (int) $row['item_id'],
                'app' => (string) $app['name'],
                'accent' => (string) $app['accent'],
                'href' => '?application=' . rawurlencode((string) $app['slug']) . '&item=' . (int) $row['item_id'],
                'when' => $when,
            ];
        }

        return ['total' => $total, 'items' => $items];
    }

    /**
     * A control that only shows on a phone. It opens the rows marked is-extra.
     */
    private static function foldButton(string $more, string $less, int $hidden, string $class = ''): string
    {
        if ($hidden <= 0) {
            return '';
        }

        return '<button type="button" class="foldbtn' . ($class !== '' ? ' ' . $class : '') . '" data-fold-toggle aria-expanded="false"'
            . ' data-more="' . Html::e($more) . '" data-less="' . Html::e($less) . '">' . Html::e($more) . '</button>';
    }

    private static function typeId(string $slug): int
    {
        if ($slug === '' || !Database::tableExists('applications')) {
            return 0;
        }
        $row = Database::first('applications', ['settings_json'], 'slug = ?', [$slug]);
        if ($row === null) {
            return 0;
        }
        $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);

        return is_array($settings) ? (int) ($settings['default_item_type_id'] ?? 0) : 0;
    }

    private static function plural(int $count, string $noun): string
    {
        return $count . ' ' . $noun . ($count === 1 ? '' : 's');
    }
}
