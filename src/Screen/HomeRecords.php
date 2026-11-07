<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\RecordQuery;
use Adlexone\Application\WorkflowFields;
use Adlexone\Auth\Access;
use Adlexone\Database\Database;
use Adlexone\Data\FieldValidation;
use Adlexone\Data\GroupMembership;
use Adlexone\Html\Presence;
use Adlexone\Http\Router;

/**
 * The record lookups the home page draws: what is assigned, what needs a look,
 * and what changed. Every id list is bound.
 */
final class HomeRecords
{
    public const WEEKS = 8;

    private const QUIET_DAYS = 14;

    /**
     * @param array<int|string> $ids
     * @return array{0: string, 1: list<int>}
     */
    private static function marks(array $ids): array
    {
        $ids = array_values(array_map(static fn (int|string $id): int => (int) $id, $ids));
        if ($ids === []) {
            return ['', []];
        }

        return [implode(',', array_fill(0, count($ids), '?')), $ids];
    }

    /**
     * Open records assigned to the person that need a look, each placed under the most urgent reason.
     *
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     * @return array{groups: array<string, list<array<string, mixed>>>, next: array<string, mixed>|null}
     */
    public static function attention(int $userId, array $typeToApp, array $apps): array
    {
        $groups = ['overdue' => [], 'soon' => [], 'changed' => [], 'quiet' => []];
        if ($userId <= 0 || $typeToApp === []) {
            return ['groups' => $groups, 'next' => null];
        }

        $now = time();
        $today = (int) strtotime('today');
        [$typeMarks, $typeParams] = self::marks(array_keys($typeToApp));
        $rows = Database::rows(
            'SELECT item_id, item_title, item_type_id, create_date, core_log_updated FROM items WHERE user_security = ? AND item_type_id IN ('
            . $typeMarks . ') LIMIT 2000',
            array_merge([$userId], $typeParams)
        );
        $byType = [];
        foreach ($rows as $row) {
            $byType[(int) $row['item_type_id']][(int) $row['item_id']] = $row;
        }

        $notes = GroupMembership::visibleNoteFilter();
        $others = [];
        if ($rows !== []) {
            [$idMarks, $idParams] = self::marks(array_map(static fn (array $row): int => (int) $row['item_id'], $rows));
            $found = Database::rows(
                'SELECT item_id, security_id, MAX(CAST(create_date AS INTEGER)) AS at FROM core_log WHERE item_id IN (' . $idMarks . ') '
                . 'AND ' . $notes['sql'] . ' AND security_id IS NOT NULL AND security_id <> ? AND CAST(create_date AS INTEGER) >= ? GROUP BY item_id',
                array_merge($idParams, $notes['params'], [$userId, $now - 7 * 86400])
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
                    'href' => Router::applicationUrl((string) $app['slug'], 0, 'item=' . $id),
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
    public static function resume(int $userId, array $typeToApp, array $apps): ?array
    {
        if ($userId <= 0 || $typeToApp === []) {
            return null;
        }
        [$marks, $typeParams] = self::marks(array_keys($typeToApp));
        $row = Database::rows(
            'SELECT l.item_id, CAST(l.create_date AS INTEGER) AS at, i.item_title, i.item_type_id FROM core_log l JOIN items i ON i.item_id = l.item_id '
            . 'WHERE l.security_id = ? AND i.item_type_id IN (' . $marks . ') '
            . 'ORDER BY CAST(l.create_date AS INTEGER) DESC LIMIT 1',
            array_merge([$userId], $typeParams)
        )[0] ?? null;
        $app = $row !== null ? ($apps[$typeToApp[(int) $row['item_type_id']] ?? -1] ?? null) : null;
        if ($row === null || $app === null) {
            return null;
        }
        $title = trim((string) $row['item_title']);

        return [
            'title' => $title !== '' ? $title : 'Record ' . (int) $row['item_id'],
            'app' => (string) $app['name'],
            'href' => Router::applicationUrl((string) $app['slug'], 0, 'item=' . (int) $row['item_id']),
            'label' => 'You changed it ' . Presence::ago((int) $row['at']),
        ];
    }

    private static function days(int $count): string
    {
        return $count . ($count === 1 ? ' day' : ' days');
    }

    /**
     * Records assigned to the person, most recently changed first.
     *
     * @param list<int> $typeIds
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     * @return list<array{item_id: int, title: string, app: string, accent: string, href: string, when: int, appIndex: int}>
     */
    public static function assignedItems(int $userId, array $typeIds, array $typeToApp, array $apps, int $limit, int $fetch): array
    {
        [$marks, $typeParams] = self::marks($typeIds);
        if ($marks === '') {
            return [];
        }
        $rows = Database::rows(
            'SELECT item_id, item_title, item_type_id, create_date, core_log_updated FROM items WHERE user_security = ? AND item_type_id IN (' . $marks . ') '
            . 'ORDER BY CASE WHEN core_log_updated > create_date THEN core_log_updated ELSE create_date END DESC LIMIT ?',
            array_merge([$userId], $typeParams, [$fetch])
        );
        $rows = self::openRows($rows, $typeToApp, $apps);
        if (count($rows) > $limit) {
            $rows = array_slice($rows, 0, $limit);
        }
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
                'href' => Router::applicationUrl((string) $app['slug'], 0, 'item=' . (int) $row['item_id']),
                'when' => max((int) $row['create_date'], (int) ($row['core_log_updated'] ?? 0)),
                'appIndex' => $index,
            ];
        }

        return $items;
    }

    /**
     * Log lines and new records for the applications, newest first.
     *
     * @param list<int> $typeIds
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     * @return list<array{text: string, when: int, who: string, href: string, icon: string, detail: string}>
     */
    public static function events(array $typeIds, array $typeToApp, array $apps, bool $mine): array
    {
        if ($typeIds === []) {
            return [];
        }
        [$typeMarks, $typeParams] = self::marks($typeIds);
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $notes = GroupMembership::visibleNoteFilter('l');
        $itemMarks = '';
        $itemParams = [];
        if (!Access::canControlRecords()) {
            $ids = WorkScreen::applicationAccessibleItemIds();
            if ($ids === []) {
                return [];
            }
            [$itemMarks, $itemParams] = self::marks($ids);
        }
        $visible = $itemMarks === '' ? '' : ' AND item_id IN (' . $itemMarks . ')';
        $visibleLog = $itemMarks === '' ? '' : ' AND i.item_id IN (' . $itemMarks . ')';
        $ownLog = '';
        $ownItem = '';
        $own = [];
        if ($mine) {
            $ownLog = ' AND l.security_id = ?';
            $ownItem = ' AND creator_security = ?';
            $own = [$userId];
        }

        $events = [];
        $lines = Database::rows(
            'SELECT l.item_id, l.log_text, CAST(l.create_date AS INTEGER) AS at, l.security_id, i.item_type_id FROM core_log l '
            . 'JOIN items i ON i.item_id = l.item_id WHERE i.item_type_id IN (' . $typeMarks . ')' . $visibleLog . ' AND ' . $notes['sql'] . $ownLog . ' '
            . 'ORDER BY CAST(l.create_date AS INTEGER) DESC LIMIT 12',
            array_merge($typeParams, $itemParams, $notes['params'], $own)
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
                'href' => Router::applicationUrl((string) $app['slug'], 0, 'item=' . (int) $line['item_id']),
                'icon' => 'ic-activity',
                'detail' => (string) $app['name'],
            ];
        }
        $created = Database::rows(
            'SELECT item_id, item_title, item_type_id, create_date, creator_security FROM items WHERE item_type_id IN (' . $typeMarks . ')' . $visible . $ownItem
            . ' ORDER BY create_date DESC LIMIT 8',
            array_merge($typeParams, $itemParams, $own)
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
                'href' => Router::applicationUrl((string) $app['slug'], 0, 'item=' . (int) $row['item_id']),
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
    public static function stats(array $typeIds): array
    {
        if ($typeIds === []) {
            return [];
        }
        [$marks, $params] = self::marks($typeIds);
        $out = [];
        $rows = Database::rows(
            'SELECT item_type_id, COUNT(*) AS n, MAX(CASE WHEN core_log_updated > create_date THEN core_log_updated ELSE create_date END) AS touched '
            . 'FROM items WHERE item_type_id IN (' . $marks . ') GROUP BY item_type_id',
            $params
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
    public static function weekly(array $typeIds): array
    {
        if ($typeIds === []) {
            return [];
        }
        $now = time();
        [$marks, $typeParams] = self::marks($typeIds);
        $out = [];
        $rows = Database::rows(
            'SELECT item_type_id, CAST((? - create_date) / 604800 AS INTEGER) AS wk, COUNT(*) AS n FROM items '
            . 'WHERE item_type_id IN (' . $marks . ') AND create_date >= ? GROUP BY item_type_id, wk',
            array_merge([$now], $typeParams, [$now - self::WEEKS * 604800])
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
    public static function owners(array $typeIds): array
    {
        if ($typeIds === []) {
            return [];
        }
        [$marks, $params] = self::marks($typeIds);
        $rows = Database::rows(
            'SELECT item_type_id, user_security, COUNT(*) AS n FROM items WHERE user_security > 0 AND item_type_id IN ('
            . $marks . ') GROUP BY item_type_id, user_security ORDER BY n DESC',
            $params
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
     * Owned rows that are still open. A record with no status counts as open.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     * @return list<array<string, mixed>>
     */
    private static function openRows(array $rows, array $typeToApp, array $apps): array
    {
        $byType = [];
        foreach ($rows as $row) {
            $byType[(int) $row['item_type_id']][] = $row;
        }
        $open = [];
        foreach ($byType as $typeId => $typed) {
            $app = $apps[$typeToApp[$typeId] ?? -1] ?? null;
            if ($app === null) {
                continue;
            }
            $ids = array_map(static fn (array $row): int => (int) $row['item_id'], $typed);
            $status = WorkScreen::workStatusField($typeId, (string) $app['slug']);
            $allowed = WorkflowFields::openValues((string) $app['slug'], $typeId, $status);
            $stand = $status !== null ? RecordQuery::values($ids, $status['id']) : [];
            foreach ($typed as $row) {
                if (RecordQuery::isOpen($allowed, (string) ($stand[(int) $row['item_id']] ?? ''))) {
                    $open[] = $row;
                }
            }
        }

        return $open;
    }

    /**
     * @param array<int, int> $typeToApp
     * @param list<array<string, mixed>> $apps
     * @return array{total: int, items: list<array{item_id: int, title: string, app: string, accent: string, href: string, when: int}>, byApp: list<array{name: string, href: string, count: int}>}
     */
    public static function assigned(int $userId, array $typeToApp, array $apps, int $fetch): array
    {
        if ($userId <= 0 || $typeToApp === []) {
            return ['total' => 0, 'items' => [], 'byApp' => []];
        }
        [$marks, $typeParams] = self::marks(array_keys($typeToApp));
        $total = 0;
        $byApp = [];
        $owned = Database::rows(
            'SELECT item_id, item_type_id FROM items WHERE user_security = ? AND item_type_id IN (' . $marks . ')',
            array_merge([$userId], $typeParams)
        );
        $counts = [];
        foreach (self::openRows($owned, $typeToApp, $apps) as $row) {
            $typeId = (int) $row['item_type_id'];
            $counts[$typeId] = ($counts[$typeId] ?? 0) + 1;
        }
        foreach ($counts as $typeId => $n) {
            $app = $apps[$typeToApp[(int) $typeId] ?? -1] ?? null;
            if ($app === null) {
                continue;
            }
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

        $items = self::assignedItems($userId, array_map('intval', array_keys($typeToApp)), $typeToApp, $apps, 6, $fetch);

        return ['total' => $total, 'items' => $items, 'byApp' => $byApp];
    }
}
