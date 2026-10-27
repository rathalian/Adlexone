<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\RecordQuery;
use Adlexone\Application\WorkflowFields;
use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Html\Accent;
use Adlexone\Html\Charts;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;
use Adlexone\Html\Timeline;
use Adlexone\support\Database;
use Adlexone\support\FieldValidation;
use Adlexone\Theme\Theme;

/**
 * The signed-in home page: a greeting, the applications with a pulse for each, then
 * what is assigned to the person and what changed lately. Those two lists start closed.
 * Recent activity loads when the person first opens it.
 */
final class HomeDashboard
{
    private const WEEKS = 8;

    /** Controller name that returns the recent activity list as JSON. */
    public const ACTIVITY = 'home_activity';
    public const ASSIGNED = 'home_assigned';
    private const ASSIGNED_LIMIT = 500;
    private const QUIET_DAYS = 14;

    /** @var array<string, array{0: string, 1: string}> reason => tile label and tone */
    private const REASONS = [
        'overdue' => ['Overdue', 'danger'],
        'soon' => ['Due this week', 'warning'],
        'changed' => ['Changed by others', 'info'],
        'quiet' => ['No change for 14 days', 'neutral'],
    ];

    /**
     * @param list<array<string, mixed>> $menu items from ApplicationStore::menuItems()
     */
    public static function render(array $menu, string $intro): string
    {
        $apps = self::apps($menu);
        $typeIds = self::typeIds($apps);
        $typeToApp = self::typeToApp($apps);

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
        $attention = self::attention($userId, $typeToApp, $apps);

        $assignedPanel = self::assignedPanel($assigned);
        $activityPanel = self::activityPanel($typeIds);
        $workFirst = $assigned['total'] > 0;

        $html = '<div class="home' . ($workFirst ? ' home--work-first' : '') . '">'
            . self::hero($assigned['total'], $attention, self::resume($userId, $typeToApp, $apps))
            . self::notice($apps)
            . self::checklist()
            . $assignedPanel
            . $intro
            . self::applications($apps, $assigned['byApp'])
            . $activityPanel;

        return $html . '</div>';
    }

    /**
     * The recent activity list, sent when the person opens that panel.
     *
     * @param list<array<string, mixed>> $menu
     */
    public static function activityHtml(array $menu, string $scope): string
    {
        $apps = self::apps($menu);
        $scope = $scope === 'mine' ? 'mine' : 'all';
        $events = self::events(self::typeIds($apps), self::typeToApp($apps), $apps, $scope === 'mine');

        $link = static fn (string $key, string $label): string => '<a class="work-scope__link' . ($scope === $key ? ' is-selected' : '') . '" data-lazy-link href="?controller='
            . self::ACTIVITY . ($key === 'mine' ? '&scope=mine' : '') . '"' . ($scope === $key ? ' aria-current="true"' : '') . '>' . Html::e($label) . '</a>';

        return '<nav class="work-scope panel__scope" aria-label="Activity to show">' . $link('all', 'Everyone') . $link('mine', 'Mine') . '</nav>'
            . Timeline::render(
                $events,
                $scope === 'mine' ? 'None of your changes yet. Changes you make to records are listed here.' : 'No activity yet. Changes to records are listed here.'
            );
    }

    /**
     * @param list<array<string, mixed>> $menu
     * @return list<array<string, mixed>>
     */
    private static function apps(array $menu): array
    {
        $apps = [];
        $types = self::typeIdsBySlug(array_map(static fn (array $item): string => (string) ($item['app'] ?? ''), $menu));
        foreach ($menu as $item) {
            $slug = (string) ($item['app'] ?? '');
            $hint = trim((string) ($item['hint'] ?? ''));
            $apps[] = [
                'slug' => $slug,
                'name' => (string) $item['label'],
                'hint' => $hint !== '' ? $hint : '',
                'icon' => (string) $item['icon'],
                'href' => (string) $item['href'],
                'type' => $types[$slug] ?? 0,
                'accent' => Accent::forSlug($slug),
                'count' => 0,
                'touched' => 0,
                'series' => [],
                'owners' => [],
            ];
        }

        return $apps;
    }

    /**
     * @param list<array<string, mixed>> $apps
     * @return list<int>
     */
    private static function typeIds(array $apps): array
    {
        return array_values(array_filter(array_map(static fn (array $app): int => $app['type'], $apps)));
    }

    /**
     * @param list<array<string, mixed>> $apps
     * @return array<int, int> record type id => index of the first application that keeps it
     */
    private static function typeToApp(array $apps): array
    {
        $map = [];
        foreach ($apps as $index => $app) {
            if ($app['type'] > 0 && !isset($map[$app['type']])) {
                $map[$app['type']] = $index;
            }
        }

        return $map;
    }

    /**
     * @param array{groups: array<string, list<array<string, mixed>>>, next: array<string, mixed>|null} $attention
     * @param array<string, mixed>|null $resume
     */
    private static function hero(int $assigned, array $attention, ?array $resume): string
    {
        $hour = (int) date('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $name = trim((string) ($_SESSION['access_display_name'] ?? $_SESSION['access_user_name'] ?? ''));
        $first = $name !== '' ? (string) (preg_split('/\s+/', $name)[0] ?? '') : '';

        $need = array_sum(array_map('count', $attention['groups']));
        $assignedLink = '<a class="homehero__link" href="#assigned" data-open-panel="assigned">' . Html::e(self::plural($assigned, 'record') . ($assigned === 1 ? ' is' : ' are') . ' assigned to you') . '</a>';
        if ($assigned === 0) {
            $lead = 'Nothing is assigned to you right now.';
        } elseif ($need === 0) {
            $lead = $assignedLink . ' and none need attention today.';
        } else {
            $lead = $assignedLink . '. ' . Html::e(($need === 1 ? '1 needs' : $need . ' need') . ' attention today.');
        }

        $tiles = '';
        if ($assigned > 0) {
            foreach (self::REASONS as $key => [$label, $tone]) {
                $n = count($attention['groups'][$key] ?? []);
                $inner = '<strong class="homehero__n">' . $n . '</strong><span class="homehero__what">' . Html::e($label) . '</span>';
                $tiles .= $n > 0
                    ? '<li><a class="homehero__tile homehero__tile--' . $tone . '" href="#assigned" data-open-panel="assigned" data-panel-focus="?controller=' . self::ASSIGNED . '&focus=' . $key . '">' . $inner . '</a></li>'
                    : '<li><span class="homehero__tile homehero__tile--none">' . $inner . '</span></li>';
            }
            $tiles = '<ul class="homehero__tiles" aria-label="Your records that need attention">' . $tiles . '</ul>';
        }

        $card = '';
        $pick = $attention['next'] ?? null;
        $kicker = 'Up next';
        if ($pick === null && $resume !== null) {
            $pick = $resume;
            $kicker = 'Pick up where you left off';
        }
        if ($pick !== null) {
            $card = '<a class="homehero__next" href="' . Html::e((string) $pick['href']) . '">'
                . '<span class="homehero__kicker">' . Html::e($kicker) . '</span>'
                . '<span class="homehero__nexttitle">' . Html::e((string) $pick['title']) . '</span>'
                . '<span class="homehero__nextmeta">' . Html::e((string) $pick['app'] . ' · ' . (string) $pick['label']) . '</span>'
                . '<span class="homehero__nextgo">Open the record' . Charts::icon('ic-arrow-right') . '</span></a>';
        }

        $words = [
            'overdue' => 'overdue',
            'soon' => 'due this week',
            'changed' => 'changed',
            'quiet' => 'with no change',
        ];
        $facts = [];
        if ($assigned === 0) {
            $facts[] = '<span>Nothing assigned</span>';
        } else {
            $facts[] = '<a class="homehero__link" href="#assigned" data-open-panel="assigned">' . Html::e(self::plural($assigned, 'record') . ' assigned') . '</a>';
            $noted = false;
            foreach (array_keys(self::REASONS) as $key) {
                $n = count($attention['groups'][$key] ?? []);
                if ($n === 0) {
                    continue;
                }
                $noted = true;
                $facts[] = '<a class="homehero__link" href="#assigned" data-open-panel="assigned" data-panel-focus="?controller=' . self::ASSIGNED . '&focus=' . $key . '">'
                    . Html::e($n . ' ' . $words[$key]) . '</a>';
            }
            if (!$noted) {
                $facts[] = '<span>none need attention</span>';
            }
        }
        $brief = '<span class="homehero__hello">' . Html::e($greeting . ($first !== '' ? ', ' . $first : '')) . '</span>'
            . '<span class="homehero__when">' . Html::e(date('l j F')) . '</span>'
            . implode('', $facts);
        if ($pick !== null) {
            $brief .= '<a class="homehero__nextline" href="' . Html::e((string) $pick['href']) . '">'
                . Html::e(($kicker === 'Up next' ? 'Up next' : 'Continue') . ' · ' . (string) $pick['title']) . '</a>';
        }

        return '<details class="homehero" open data-minimise="home.welcome">'
            . '<summary class="homehero__summary">'
            . '<span class="homehero__dateline">' . Html::e(date('l j F')) . '</span>'
            . '<span class="homehero__brief">' . $brief . '</span>'
            . '<span class="homehero__switch"><span class="homehero__switch-hide">Minimise</span><span class="homehero__switch-show">Show</span></span>'
            . '</summary>'
            . '<div class="homehero__body">'
            . '<div class="homehero__copy">'
            . '<h1 class="homehero__title">' . Html::e($greeting . ($first !== '' ? ', ' . $first : '')) . '</h1>'
            . '<p class="homehero__lead">' . $lead . '</p>' . $tiles . '</div>'
            . $card
            . '</div></details>';
    }

    /**
     * Open records assigned to the person that need a look, each placed under the most urgent reason.
     *
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     * @return array{groups: array<string, list<array<string, mixed>>>, next: array<string, mixed>|null}
     */
    private static function attention(int $userId, array $typeToApp, array $apps): array
    {
        $groups = ['overdue' => [], 'soon' => [], 'changed' => [], 'quiet' => []];
        if ($userId <= 0 || $typeToApp === []) {
            return ['groups' => $groups, 'next' => null];
        }

        $now = time();
        $today = (int) strtotime('today');
        $rows = Database::rows(
            'SELECT item_id, item_title, item_type_id, create_date, core_log_updated FROM items WHERE user_security = ? AND item_type_id IN ('
            . implode(',', array_map('intval', array_keys($typeToApp))) . ') LIMIT 2000',
            [$userId]
        );
        $byType = [];
        foreach ($rows as $row) {
            $byType[(int) $row['item_type_id']][(int) $row['item_id']] = $row;
        }

        $role = (int) ($_SESSION['access_role_id'] ?? 5);
        $others = [];
        if ($rows !== []) {
            $ids = implode(',', array_map(static fn (array $row): int => (int) $row['item_id'], $rows));
            $found = Database::rows(
                'SELECT item_id, security_id, MAX(CAST(create_date AS INTEGER)) AS at FROM core_log WHERE item_id IN (' . $ids . ') '
                . 'AND role_id >= ? AND security_id IS NOT NULL AND security_id <> ? AND CAST(create_date AS INTEGER) >= ? GROUP BY item_id',
                [$role, $userId, $now - 7 * 86400]
            );
            $names = RecordQuery::userNames(array_map(static fn (array $row): int => (int) $row['security_id'], $found));
            foreach ($found as $row) {
                $others[(int) $row['item_id']] = ['at' => (int) $row['at'], 'who' => $names[(int) $row['security_id']] ?? ''];
            }
        }

        foreach ($byType as $typeId => $items) {
            $app = $apps[$typeToApp[$typeId] ?? -1] ?? null;
            if ($app === null) {
                continue;
            }
            $ids = array_keys($items);
            $slug = (string) $app['slug'];
            $status = WorkScreen::workStatusField($typeId, $slug);
            $stand = $status !== null ? RecordQuery::values($ids, $status['id']) : [];
            $open = WorkflowFields::openValues($slug, $typeId, $status);
            $due = WorkflowFields::resolve($slug, $typeId, WorkflowFields::DUE);
            $dates = $due !== null ? RecordQuery::values($ids, $due['id']) : [];

            foreach ($items as $id => $row) {
                if (!RecordQuery::isOpen($open, (string) ($stand[$id] ?? ''))) {
                    continue;
                }
                $title = trim((string) $row['item_title']);
                $last = max((int) $row['create_date'], (int) ($row['core_log_updated'] ?? 0));
                $entry = [
                    'item_id' => $id,
                    'title' => $title !== '' ? $title : 'Record ' . $id,
                    'app' => (string) $app['name'],
                    'accent' => (string) $app['accent'],
                    'href' => '?application=' . rawurlencode((string) $app['slug']) . '&item=' . $id,
                    'when' => $last,
                    'appIndex' => $typeToApp[$typeId],
                ];

                $offset = null;
                $day = FieldValidation::parseDate((string) ($dates[$id] ?? ''));
                if ($day !== null) {
                    $offset = (int) round(($day->setTime(0, 0)->getTimestamp() - $today) / 86400);
                }
                if ($offset !== null && $offset < 0) {
                    $groups['overdue'][] = $entry + ['rank' => $offset, 'label' => self::days(-$offset) . ' overdue'];
                } elseif ($offset !== null && $offset <= 7) {
                    $groups['soon'][] = $entry + ['rank' => $offset, 'label' => $offset === 0 ? 'Due today' : ($offset === 1 ? 'Due tomorrow' : 'Due in ' . $offset . ' days')];
                } elseif (isset($others[$id])) {
                    $who = $others[$id]['who'];
                    $groups['changed'][] = $entry + ['rank' => -$others[$id]['at'], 'label' => ($who !== '' ? 'Changed by ' . $who : 'Changed by someone else') . ' ' . Presence::ago($others[$id]['at'])];
                } elseif ($now - $last >= self::QUIET_DAYS * 86400) {
                    $quiet = (int) floor(($now - $last) / 86400);
                    $groups['quiet'][] = $entry + ['rank' => -$quiet, 'label' => 'No change for ' . self::days($quiet)];
                }
            }
        }

        $next = null;
        foreach ($groups as $key => $list) {
            usort($list, static fn (array $left, array $right): int => $left['rank'] <=> $right['rank']);
            $groups[$key] = $list;
            $next ??= $list[0] ?? null;
        }

        return ['groups' => $groups, 'next' => $next];
    }

    /**
     * The record the person changed most recently, for the days when nothing needs attention.
     *
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     * @return array<string, mixed>|null
     */
    private static function resume(int $userId, array $typeToApp, array $apps): ?array
    {
        if ($userId <= 0 || $typeToApp === []) {
            return null;
        }
        $row = Database::rows(
            'SELECT l.item_id, CAST(l.create_date AS INTEGER) AS at, i.item_title, i.item_type_id FROM core_log l JOIN items i ON i.item_id = l.item_id '
            . 'WHERE l.security_id = ? AND i.item_type_id IN (' . implode(',', array_map('intval', array_keys($typeToApp))) . ') '
            . 'ORDER BY CAST(l.create_date AS INTEGER) DESC LIMIT 1',
            [$userId]
        )[0] ?? null;
        $app = $row !== null ? ($apps[$typeToApp[(int) $row['item_type_id']] ?? -1] ?? null) : null;
        if ($row === null || $app === null) {
            return null;
        }
        $title = trim((string) $row['item_title']);

        return [
            'title' => $title !== '' ? $title : 'Record ' . (int) $row['item_id'],
            'app' => (string) $app['name'],
            'href' => '?application=' . rawurlencode((string) $app['slug']) . '&item=' . (int) $row['item_id'],
            'label' => 'You changed it ' . Presence::ago((int) $row['at']),
        ];
    }

    private static function days(int $count): string
    {
        return $count . ($count === 1 ? ' day' : ' days');
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
        $href = self::announcementsHref($app, $slugs);
        $key = 'announcement-' . (int) ($rows[0]['time'] ?? 0) . '-' . substr(md5($subject), 0, 8);

        return '<aside class="banner" data-dismissible="' . Html::e($key) . '" aria-label="Announcement">'
            . '<span class="banner__icon">' . Charts::icon('ic-announce') . '</span>'
            . '<p class="banner__text"><strong>Announcement</strong> <a href="' . Html::e($href) . '">' . Html::e($subject) . '</a></p>'
            . '<button type="button" class="iconbtn" data-dismiss aria-label="Hide this announcement">' . Charts::icon('ic-x') . '</button>'
            . '</aside>';
    }

    /**
     * Address of the announcements screen for the application that posted the
     * notice. A notice for the whole site opens the first listed application
     * that keeps an announcements screen. An application without one opens at
     * its start screen.
     *
     * @param list<string> $slugs
     */
    private static function announcementsHref(string $app, array $slugs): string
    {
        $candidates = $app !== '' ? [$app] : $slugs;
        foreach ($candidates as $slug) {
            $application = \Adlexone\Application\ApplicationStore::findBySlug($slug);
            if ($application === null) {
                continue;
            }
            foreach (\Adlexone\Application\ApplicationStore::navigation((int) $application['application_id']) as $nav) {
                if (\Adlexone\Application\ScreenCatalog::resolve($nav['capability']) === \Adlexone\Application\ScreenCatalog::ANNOUNCEMENTS) {
                    return \Adlexone\Http\Router::applicationUrl($slug, (int) $nav['nav_id']);
                }
            }
        }

        return $app !== '' ? \Adlexone\Http\Router::applicationUrl($app) : './';
    }

    private static function checklist(): string
    {
        if (!Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            return '';
        }
        $seen = Database::rows(
            'SELECT EXISTS(SELECT 1 FROM applications) AS apps, '
            . (Database::tableExists('custom_fields') ? 'EXISTS(SELECT 1 FROM custom_fields)' : '0') . ' AS fields, '
            . 'EXISTS(SELECT 1 FROM items) AS records, (SELECT COUNT(*) FROM (SELECT 1 FROM users LIMIT 2)) AS people'
        )[0] ?? [];
        $steps = [
            ['Create an application', 'An application groups the screens for one kind of work.', '?manage=builder', (int) ($seen['apps'] ?? 0) > 0],
            ['Add fields to a record type', 'Fields hold the details a record keeps.', '?manage=fields', (int) ($seen['fields'] ?? 0) > 0],
            ['Create a record', 'Open an application and add the first record.', './', (int) ($seen['records'] ?? 0) > 0],
            ['Add people', 'Add the people who need to sign in, then place them in groups.', '?manage=security&option=manage_users', (int) ($seen['people'] ?? 0) > 1],
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
    /**
     * Weeks from the first week with any record to the latest week.
     *
     * @param list<int> $series
     */
    private static function weeksOfHistory(array $series): int
    {
        foreach (array_values($series) as $index => $count) {
            if ($count > 0) {
                return count($series) - $index;
            }
        }

        return 0;
    }

    private static function applications(array $apps, array $assignedByApp): string
    {
        if ($apps === []) {
            return '';
        }
        $assigned = [];
        foreach ($assignedByApp as $row) {
            $assigned[(string) ($row['name'] ?? '')] = (int) ($row['count'] ?? 0);
        }
        foreach ($apps as $index => $app) {
            $apps[$index]['assigned'] = $assigned[(string) $app['name']] ?? 0;
        }
        $cards = '';
        foreach ($apps as $app) {
            $stat = $app['type'] > 0 ? self::plural((int) $app['count'], 'record') : '';
            $hasSeries = self::weeksOfHistory($app['series']) >= 3;
            $when = $app['touched'] > 0 ? 'Updated ' . Presence::ago((int) $app['touched']) : ($app['type'] > 0 ? 'No records yet' : '');
            $assignedCount = (int) ($app['assigned'] ?? 0);
            $cards .= '<a class="appcard' . ($assignedCount > 0 ? ' appcard--assigned' : '') . '" href="' . Html::e((string) $app['href']) . '" style="' . Html::e(Accent::style((string) $app['accent'])) . '">'
                . '<span class="appcard__top"><span class="appcard__icon">' . Charts::icon((string) $app['icon']) . '</span>'
                . '<span class="appcard__go" aria-hidden="true">' . Charts::icon('ic-arrow-right') . '</span></span>'
                . '<span class="appcard__name">' . Html::e((string) $app['name']) . '</span>'
                . ($app['hint'] !== '' ? '<span class="appcard__hint">' . Html::e((string) $app['hint']) . '</span>' : '')
                . '<span class="appcard__figures">'
                . ($assignedCount > 0
                    ? '<span class="appcard__count">' . Html::e(self::plural($assignedCount, 'record') . ' assigned') . '</span>'
                    : ($stat !== '' ? '<span class="appcard__count">' . Html::e($stat) . '</span>' : ''))
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
     * @param array{item_id: int, title: string, app: string, accent: string, href: string, when: int} $item
     */
    private static function assignedRow(array $item, string $tone = ''): string
    {
        $when = $tone !== '' ? (string) $item['label'] : Presence::ago($item['when']);

        return '<li><a class="rows__link" href="' . Html::e($item['href']) . '" style="' . Html::e(Accent::style($item['accent'])) . '">'
            . '<span class="rows__dot"></span><span class="rows__main"><span class="rows__title">' . Html::e($item['title']) . '</span>'
            . '<span class="rows__meta">' . Html::e($item['app']) . '</span></span>'
            . '<span class="rows__when' . ($tone !== '' ? ' rows__when--' . Html::e($tone) : '') . '">' . Html::e($when) . '</span></a></li>';
    }

    /**
     * Records assigned to the person, most recently changed first.
     *
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     * @return list<array{item_id: int, title: string, app: string, accent: string, href: string, when: int, appIndex: int}>
     */
    private static function assignedItems(int $userId, string $typeList, array $typeToApp, array $apps, int $limit): array
    {
        $rows = Database::rows(
            'SELECT item_id, item_title, item_type_id, create_date, core_log_updated FROM items WHERE user_security = ? AND item_type_id IN (' . $typeList . ') '
            . 'ORDER BY CASE WHEN core_log_updated > create_date THEN core_log_updated ELSE create_date END DESC LIMIT ' . $limit,
            [$userId]
        );
        $items = [];
        foreach ($rows as $row) {
            $index = $typeToApp[(int) $row['item_type_id']] ?? -1;
            $app = $apps[$index] ?? null;
            if ($app === null) {
                continue;
            }
            $title = trim((string) $row['item_title']);
            $items[] = [
                'item_id' => (int) $row['item_id'],
                'title' => $title !== '' ? $title : 'Record ' . (int) $row['item_id'],
                'app' => (string) $app['name'],
                'accent' => (string) $app['accent'],
                'href' => '?application=' . rawurlencode((string) $app['slug']) . '&item=' . (int) $row['item_id'],
                'when' => max((int) $row['create_date'], (int) ($row['core_log_updated'] ?? 0)),
                'appIndex' => $index,
            ];
        }

        return $items;
    }

    /**
     * Every record assigned to the person, grouped by application. Sent when they choose Show all.
     *
     * @param list<array<string, mixed>> $menu
     */
    public static function assignedHtml(array $menu, string $focus = ''): string
    {
        $apps = self::apps($menu);
        $typeToApp = self::typeToApp($apps);
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $attention = self::attention($userId, $typeToApp, $apps);
        $scope = self::scope($attention, $focus);
        if (isset(self::REASONS[$focus]) && $attention['groups'][$focus] !== []) {
            $tone = self::REASONS[$focus][1];
            $html = $scope . '<ul class="rows">';
            foreach ($attention['groups'][$focus] as $item) {
                $html .= self::assignedRow($item, $tone);
            }

            return $html . '</ul>';
        }
        $items = $userId > 0 && $typeToApp !== []
            ? self::assignedItems($userId, implode(',', array_map('intval', array_keys($typeToApp))), $typeToApp, $apps, self::ASSIGNED_LIMIT + 1)
            : [];
        if ($items === []) {
            return '<p class="timeline__empty">Nothing is assigned to you. Records you own are listed here.</p>';
        }
        $capped = count($items) > self::ASSIGNED_LIMIT;
        $items = array_slice($items, 0, self::ASSIGNED_LIMIT);

        $groups = [];
        foreach ($items as $item) {
            $groups[$item['appIndex']][] = $item;
        }
        $html = $scope;
        foreach ($groups as $index => $group) {
            $href = (string) $apps[$index]['href'];
            $href .= (str_contains($href, '?') ? '&' : '?') . 'focus=mine';
            $html .= '<section class="assigned-group">'
                . '<h3 class="assigned-group__title"><a href="' . Html::e($href) . '">' . Html::e((string) $apps[$index]['name'])
                . '<span>' . count($group) . '</span></a></h3><ul class="rows">';
            foreach ($group as $item) {
                $html .= self::assignedRow($item);
            }
            $html .= '</ul></section>';
        }
        if ($capped) {
            $html .= '<p class="timeline__empty">The ' . self::ASSIGNED_LIMIT . ' most recent records are shown. Open an application to see the rest.</p>';
        }

        return $html;
    }

    /**
     * Links that switch the assigned list between every record and each reason that needs attention.
     *
     * @param array{groups: array<string, list<array<string, mixed>>>, next: array<string, mixed>|null} $attention
     */
    private static function scope(array $attention, string $focus): string
    {
        if (array_sum(array_map('count', $attention['groups'])) === 0) {
            return '';
        }
        $link = static fn (string $key, string $label): string => '<a class="work-scope__link' . ($focus === $key ? ' is-selected' : '') . '" data-lazy-link href="?controller='
            . self::ASSIGNED . ($key !== '' ? '&focus=' . $key : '') . '"' . ($focus === $key ? ' aria-current="true"' : '') . '>' . Html::e($label) . '</a>';
        $html = $link('', 'All');
        foreach (self::REASONS as $key => [$label]) {
            if ($attention['groups'][$key] !== []) {
                $html .= $link($key, $label . ' ' . count($attention['groups'][$key]));
            }
        }

        return '<nav class="work-scope panel__scope" aria-label="Assigned records to show">' . $html . '</nav>';
    }

    /**
     * @param array{total: int, items: list<array{item_id: int, title: string, app: string, accent: string, href: string, when: int}>, byApp: list<array{name: string, href: string, count: int}>} $assigned
     */
    private static function assignedPanel(array $assigned): string
    {
        $items = $assigned['items'];
        if ($items === []) {
            $art = Theme::illustrationHref('empty-assigned');
            $body = '<div class="panel__empty">'
                . ($art !== '' ? '<img src="' . Html::e($art) . '" alt="" width="88" height="66" loading="lazy">' : '')
                . '<p>Nothing is assigned to you. Records you own are listed here.</p></div>';
        } else {
            $body = '<ul class="rows">';
            foreach ($items as $item) {
                $body .= self::assignedRow($item);
            }
            $body .= '</ul>';
            if ($assigned['total'] > count($items) || count($assigned['byApp']) > 1) {
                $links = '';
                foreach (array_slice($assigned['byApp'], 0, 4) as $app) {
                    $links .= '<a href="' . Html::e($app['href']) . '">' . Html::e($app['name']) . '<span>' . $app['count'] . '</span></a>';
                }
                $rest = count($assigned['byApp']) - 4;
                if ($rest > 0) {
                    $links .= '<span>and ' . self::plural($rest, 'other application') . '</span>';
                }
                $body .= '<p class="panel__more">'
                    . '<a class="panel__all" data-lazy-link href="?controller=' . self::ASSIGNED . '">Show all ' . $assigned['total'] . '</a>'
                    . $links . '</p>';
            }
        }
        $count = $assigned['total'] > 0 ? '<span class="panel__count">' . $assigned['total'] . '</span>' : '';

        $lazy = $items === [] ? '' : '?controller=' . self::ASSIGNED;

        return self::panel('assigned', 'Assigned to you', $count, '<div class="panel__body" data-lazy-body aria-live="polite">' . $body . '</div>', $lazy, true, $assigned['total'] > 0);
    }

    /**
     * @param list<int> $typeIds
     */
    private static function activityPanel(array $typeIds): string
    {
        if ($typeIds === []) {
            return '';
        }

        return self::panel(
            'activity',
            'Recent activity',
            '',
            '<div class="panel__body" data-lazy-body aria-live="polite"><p class="timeline__empty">Loading recent activity.</p>'
            . '<noscript><p class="timeline__empty">Recent activity needs JavaScript.</p></noscript></div>',
            '?controller=' . self::ACTIVITY
        );
    }

    /**
     * A panel that starts closed. The browser remembers whether the person opened it.
     */
    private static function panel(string $id, string $title, string $count, string $body, string $lazy, bool $loaded = false, bool $open = false): string
    {
        return '<details class="panel panel--' . $id . '" id="' . $id . '" data-remember="home.' . $id . '"'
            . ($lazy !== '' ? ' data-lazy="' . Html::e($lazy) . '"' : '') . ($loaded ? ' data-loaded="1"' : '') . ($open ? ' open' : '') . '>'
            . '<summary class="panel__head"><h2>' . Html::e($title) . '</h2>' . $count . '<span class="panel__chev" aria-hidden="true"></span></summary>'
            . $body . '</details>';
    }

    /**
     * Log lines and new records for the applications, newest first.
     *
     * @param list<int> $typeIds
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     * @return list<array{text: string, when: int, who: string, href: string, icon: string, detail: string}>
     */
    private static function events(array $typeIds, array $typeToApp, array $apps, bool $mine): array
    {
        if ($typeIds === []) {
            return [];
        }
        $list = implode(',', array_map('intval', $typeIds));
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $role = (int) ($_SESSION['access_role_id'] ?? 5);
        $visible = '';
        $visibleLog = '';
        if ($role > 2) {
            $ids = WorkScreen::applicationAccessibleItemIds();
            if ($ids === []) {
                return [];
            }
            $visible = ' AND item_id IN (' . implode(',', array_map('intval', $ids)) . ')';
            $visibleLog = ' AND i.item_id IN (' . implode(',', array_map('intval', $ids)) . ')';
        }
        $logParams = [$role];
        $itemParams = [];
        $ownLog = '';
        $ownItem = '';
        if ($mine) {
            $ownLog = ' AND l.security_id = ?';
            $ownItem = ' AND creator_security = ?';
            $logParams[] = $userId;
            $itemParams[] = $userId;
        }

        $events = [];
        $lines = Database::rows(
            'SELECT l.item_id, l.log_text, CAST(l.create_date AS INTEGER) AS at, l.security_id, i.item_type_id FROM core_log l '
            . 'JOIN items i ON i.item_id = l.item_id WHERE i.item_type_id IN (' . $list . ')' . $visibleLog . ' AND l.role_id >= ?' . $ownLog . ' '
            . 'ORDER BY CAST(l.create_date AS INTEGER) DESC LIMIT 12',
            $logParams
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
            'SELECT item_id, item_title, item_type_id, create_date, creator_security FROM items WHERE item_type_id IN (' . $list . ')' . $visible . $ownItem
            . ' ORDER BY create_date DESC LIMIT 8',
            $itemParams
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

        return array_slice($events, 0, 7);
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
     * @return array{total: int, items: list<array{item_id: int, title: string, app: string, accent: string, href: string, when: int}>, byApp: list<array{name: string, href: string, count: int}>}
     */
    private static function assigned(int $userId, array $typeToApp, array $apps): array
    {
        if ($userId <= 0 || $typeToApp === []) {
            return ['total' => 0, 'items' => [], 'byApp' => []];
        }
        $list = implode(',', array_map('intval', array_keys($typeToApp)));
        $total = 0;
        $byApp = [];
        $counts = Database::rows(
            'SELECT item_type_id, COUNT(*) AS n FROM items WHERE user_security = ? AND item_type_id IN (' . $list . ') GROUP BY item_type_id',
            [$userId]
        );
        foreach ($counts as $row) {
            $app = $apps[$typeToApp[(int) $row['item_type_id']] ?? -1] ?? null;
            if ($app === null) {
                continue;
            }
            $n = (int) $row['n'];
            $total += $n;
            $href = (string) $app['href'];
            $byApp[] = [
                'name' => (string) $app['name'],
                'href' => $href . (str_contains($href, '?') ? '&' : '?') . 'focus=mine',
                'count' => $n,
            ];
        }
        usort($byApp, static fn (array $left, array $right): int => $right['count'] <=> $left['count']);
        if ($total === 0) {
            return ['total' => 0, 'items' => [], 'byApp' => []];
        }

        $items = self::assignedItems($userId, $list, $typeToApp, $apps, 6);

        return ['total' => $total, 'items' => $items, 'byApp' => $byApp];
    }

    /**
     * A control that only shows on a phone. It opens the rows marked is-extra.
     */
    private static function foldButton(string $more, string $less, int $hidden): string
    {
        if ($hidden <= 0) {
            return '';
        }

        return '<button type="button" class="foldbtn" data-fold-toggle aria-expanded="false"'
            . ' data-more="' . Html::e($more) . '" data-less="' . Html::e($less) . '">' . Html::e($more) . '</button>';
    }

    /**
     * @param list<string> $slugs
     * @return array<string, int> application slug => default record type id
     */
    private static function typeIdsBySlug(array $slugs): array
    {
        $slugs = array_values(array_unique(array_filter($slugs, static fn (string $slug): bool => $slug !== '')));
        if ($slugs === [] || !Database::tableExists('applications')) {
            return [];
        }
        $rows = Database::rows(
            'SELECT slug, settings_json FROM applications WHERE slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')',
            $slugs
        );
        $out = [];
        foreach ($rows as $row) {
            $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);
            $out[(string) $row['slug']] = is_array($settings) ? (int) ($settings['default_item_type_id'] ?? 0) : 0;
        }

        return $out;
    }

    private static function plural(int $count, string $noun): string
    {
        return $count . ' ' . $noun . ($count === 1 ? '' : 's');
    }
}
