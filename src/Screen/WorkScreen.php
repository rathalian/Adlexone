<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\RecordQuery;
use Adlexone\Application\ScreenCatalog;
use Adlexone\Application\WorkflowFields;
use Adlexone\Auth\Csrf;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\Http\Page;
use Adlexone\Http\Router;
use Adlexone\Html\Blocks;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;

/**
 * Screen formerly loaded from legacy/.
 */
final class WorkScreen
{
    /**
     * Items this person can open: owned, created, or shared through a group.
     * Optional item_type_id scopes the list to one record type.
     *
     * @return list<int>
     */
    public static function applicationAccessibleItemIds(?int $itemTypeId = null): array
    {
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $ids = [];
        $owned = Database::select('items', ['item_id', 'item_type_id'], 'user_security = ? OR creator_security = ?', [$userId, $userId]);
        foreach ($owned as $row) {
            if ($itemTypeId !== null && (int) $row['item_type_id'] !== $itemTypeId) {
                continue;
            }
            $ids[] = (int) $row['item_id'];
        }
        foreach (GroupMembership::itemIdsForUser($userId) as $itemId) {
            if ($itemTypeId !== null) {
                $item = Database::first('items', ['item_type_id'], 'item_id = ?', [$itemId]);
                if ($item === null || (int) $item['item_type_id'] !== $itemTypeId) {
                    continue;
                }
            }
            $ids[] = $itemId;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        rsort($ids);
        return $ids;
    }

    /**
     * Work list for an application (or the legacy Contact Centre pack).
     */
    public static function showApplicationWork(?int $itemTypeId = null, string $title = 'Work'): void
    {
        if ((string) ($_GET['option'] ?? '') === 'move_card' && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            self::moveWorkCard($itemTypeId);
            return;
        }
        if ((string) ($_GET['option'] ?? '') === 'move_cards' && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            self::moveWorkCards($itemTypeId);
            return;
        }

        $ids = self::applicationAccessibleItemIds($itemTypeId);
        $base = defined('MAN_BASE_URL') ? (string) MAN_BASE_URL : './';
        $stand = self::workStand($ids, $itemTypeId, $base);
        $shown = self::workFocusIds($ids, $stand['field_id'], $stand['values']);
        $status = self::workStatusField($itemTypeId);
        $canBoard = $status !== null && $status['values'] !== [];
        $view = $canBoard && self::workView() === 'board' ? 'board' : 'list';
        $createHref = self::createHref();
        if ($createHref === '') {
            $createHref = $base . '&option=show_item_types';
        }
        $new = '<a class="btn btn--primary" href="' . Html::e($createHref) . '">New</a>';
        $switch = $canBoard ? self::workViewSwitch($base, $view) : '';
        $toolbar = '<div class="work-toolbar">' . $switch . $stand['html'] . '</div>';
        if ($ids === []) {
            $body = Blocks::emptyState([
                'illustration' => 'empty-work',
                'title' => 'No records yet',
                'copy' => 'Create the first record.',
                'action' => $new,
            ]);
        } elseif ($shown === []) {
            $focus = (string) ($_GET['focus'] ?? '');
            if ($focus === 'mine') {
                $body = Blocks::emptyState([
                    'illustration' => 'empty-assigned',
                    'title' => 'Nothing is assigned to you',
                    'copy' => 'Records assigned to you are listed here.',
                    'action' => '<a class="btn btn--sm" href="' . Html::e(self::workHref($base, $view)) . '">Show all</a>',
                ]);
            } else {
                $body = $toolbar . '<p class="work-empty">No records in this view. <a href="' . Html::e(self::workHref($base, $view)) . '">Show all</a></p>';
            }
        } elseif ($view === 'board') {
            $body = $toolbar . self::workBoard($shown, $itemTypeId, $base, $stand);
        } else {
            $body = $toolbar . self::workList($shown, $itemTypeId, $base, $stand);
        }
        Page::current()->setBody(Blocks::vertical([
            [
                'title' => $title,
                'html' => $body,
                'class' => 'card--work',
            ],
        ]));
    }

    /**
     * Whose work is on screen, plus the menu value stored on each record.
     *
     * @param list<int> $ids
     * @return array{html: string, field_id: int, values: array<int, string>}
     */
    public static function workStand(array $ids, ?int $itemTypeId, string $base): array
    {
        $status = self::workStatusField($itemTypeId);
        $fieldId = (int) ($status['id'] ?? 0);
        $valueByItem = [];
        if ($fieldId > 0 && $ids !== []) {
            $stored = Database::rows(
                'SELECT item_id, value FROM item_field_values WHERE custom_field_id = ? AND item_id IN (' . implode(',', $ids) . ')',
                [$fieldId]
            );
            foreach ($stored as $row) {
                $valueByItem[(int) $row['item_id']] = (string) ($row['value'] ?? '');
            }
        }

        $focus = (string) ($_GET['focus'] ?? '');
        if (!in_array($focus, ['', 'mine', 'status', 'empty'], true)) {
            $focus = '';
        }

        $label = 'Whose work';

        return [
            'html' => '<nav class="work-scope" aria-label="' . Html::e($label) . '">'
                . self::workScopeLink($base, '', 'All', $focus === '')
                . self::workScopeLink($base, 'mine', 'Assigned to me', $focus === 'mine')
                . '</nav>',
            'field_id' => $fieldId,
            'values' => $valueByItem,
        ];
    }

    /**
     * @param array<int, string> $values item id => status value
     * @param list<int> $ids
     * @return list<int>
     */
    public static function workFocusIds(array $ids, int $fieldId, array $values): array
    {
        $focus = (string) ($_GET['focus'] ?? '');
        if ($focus === 'mine') {
            $userId = (int) ($_SESSION['access_user_id'] ?? 0);
            if ($userId <= 0 || $ids === []) {
                return [];
            }
            $owned = Database::select(
                'items',
                ['item_id'],
                'user_security = ? AND item_id IN (' . implode(',', $ids) . ')',
                [$userId]
            );
            $slug = self::applicationSlug();
            $typeId = 0;
            $sample = Database::first('items', ['item_type_id'], 'item_id = ?', [$ids[0]]);
            if ($sample !== null) {
                $typeId = (int) $sample['item_type_id'];
            }
            $status = self::workStatusField($typeId > 0 ? $typeId : null, $slug);
            $open = WorkflowFields::openValues($slug, $typeId, $status);
            $keep = [];
            foreach ($owned as $row) {
                $id = (int) $row['item_id'];
                if (!RecordQuery::isOpen($open, (string) ($values[$id] ?? ''))) {
                    continue;
                }
                $keep[] = $id;
            }
            rsort($keep);
            return $keep;
        }
        if ($focus === 'empty' && $fieldId > 0) {
            $keep = [];
            foreach ($ids as $id) {
                if (($values[$id] ?? '') === '') {
                    $keep[] = $id;
                }
            }
            return $keep;
        }
        if ($focus === 'status' && $fieldId > 0) {
            $want = (string) ($_GET['status'] ?? '');
            $keep = [];
            foreach ($ids as $id) {
                if (($values[$id] ?? '') === $want) {
                    $keep[] = $id;
                }
            }
            return $keep;
        }

        return $ids;
    }

    /**
     * Menu field that says where a record stands. Prefers a field whose name is Status.
     *
     * @return array{id: int, name: string, values: list<string>}|null
     */
    public static function workStatusField(?int $itemTypeId, string $slug = ''): ?array
    {
        if ($itemTypeId === null || $itemTypeId <= 0) {
            return null;
        }
        $field = WorkflowFields::resolve($slug !== '' ? $slug : self::applicationSlug(), $itemTypeId, WorkflowFields::STATUS);

        return $field === null ? null : [
            'id' => $field['id'],
            'name' => $field['name'],
            'values' => $field['values'],
        ];
    }

    public static function workScopeLink(string $base, string $focus, string $label, bool $selected): string
    {
        $href = self::workHref($base, self::workView(), $focus);
        $class = 'work-scope__link' . ($selected ? ' is-selected' : '');
        $current = $selected ? ' aria-current="page"' : '';

        return '<a class="' . $class . '" href="' . Html::e($href) . '"' . $current . '>'
            . Html::e($label) . '</a>';
    }

    private static function workView(): string
    {
        return (string) ($_GET['view'] ?? '') === 'board' ? 'board' : 'list';
    }

    private static function currentFocus(): string
    {
        $focus = (string) ($_GET['focus'] ?? '');

        return in_array($focus, ['mine', 'status', 'empty'], true) ? $focus : '';
    }

    private static function workHref(string $base, string $view, string $focus = ''): string
    {
        $href = $base . '&view=' . rawurlencode($view);
        if ($focus === 'mine' || $focus === 'status' || $focus === 'empty') {
            $href .= '&focus=' . rawurlencode($focus);
            if ($focus === 'status') {
                $href .= '&status=' . rawurlencode((string) ($_GET['status'] ?? ''));
            }
        }

        return $href;
    }

    private static function workViewSwitch(string $base, string $view): string
    {
        $focus = self::currentFocus();
        $links = '';
        foreach (['list' => 'List', 'board' => 'Board'] as $key => $label) {
            $selected = $view === $key;
            $links .= '<a class="work-views__link' . ($selected ? ' is-selected' : '') . '" href="'
                . Html::e(self::workHref($base, $key, $focus)) . '"'
                . ($selected ? ' aria-current="page"' : '') . '>'
                . Html::e($label) . '</a>';
        }

        return '<div class="work-views" role="group" aria-label="View">' . $links . '</div>';
    }

    /**
     * @param list<int> $ids
     * @param array{html: string, field_id: int, values: array<int, string>} $stand
     */
    private static function workList(array $ids, ?int $itemTypeId, string $base, array $stand): string
    {
        $facts = self::workCardFacts($ids);
        $status = self::workStatusField($itemTypeId);
        $rows = [];
        if ($ids !== []) {
            $result = Database::select(
                'items',
                ['item_id', 'item_title', 'create_date', 'item_type_id'],
                'WHERE item_id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY item_id DESC'
            );
            foreach ($result as $row) {
                $itemId = (int) $row['item_id'];
                $fact = $facts[$itemId] ?? ['title' => '', 'owner' => '', 'log' => '', 'detail' => '', 'next' => ''];
                $record = Blocks::itemRecord($row, $base . '&item=' . $itemId, Blocks::itemActions($itemId, $base));
                $bits = array_values(array_filter([
                    trim((string) ($fact['detail'] ?? '')),
                    trim((string) ($fact['next'] ?? '')) !== '' ? trim((string) $fact['next']) : trim((string) ($fact['log'] ?? '')),
                ]));
                $record['meta'] = implode(' · ', $bits);
                $record['mark'] = trim((string) ($fact['owner'] ?? ''));
                $record['itemId'] = $itemId;
                $record['sortName'] = strtolower((string) $record['name']);
                $opened = (int) ($row['create_date'] ?? 0);
                $record['sortOpened'] = $opened;
                if ($opened > 0) {
                    $record['cells']['opened'] = Presence::ago($opened);
                    $record['titles']['opened'] = date(SET_DATE_FORMAT, $opened);
                }
                $standValue = trim((string) ($stand['values'][$itemId] ?? ''));
                $record['sortStand'] = strtolower($standValue);
                $record['cells']['stand'] = $standValue;
                if ($standValue !== '') {
                    $record['tones']['stand'] = Presence::tone($standValue);
                }
                $rows[] = $record;
            }
        }
        $sort = in_array((string) ($_GET['sort'] ?? ''), ['name', 'status', 'opened'], true)
            ? (string) $_GET['sort']
            : 'opened';
        $direction = (string) ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
        usort($rows, static function (array $left, array $right) use ($sort, $direction): int {
            $key = match ($sort) {
                'name' => 'sortName',
                'status' => 'sortStand',
                default => 'sortOpened',
            };
            $result = ($left[$key] ?? '') <=> ($right[$key] ?? '');

            return $direction === 'asc' ? $result : -$result;
        });
        $columns = [];
        if ($status !== null && $status['values'] !== []) {
            $columns[] = [
                'key' => 'stand',
                'label' => self::workFieldLabel((string) $status['name']),
                'sortHref' => self::workSortHref($base, 'status', $sort, $direction),
                'ariaSort' => $sort === 'status' ? ($direction === 'asc' ? 'ascending' : 'descending') : '',
            ];
        }
        $columns[] = [
            'key' => 'opened',
            'label' => 'Opened',
            'sortHref' => self::workSortHref($base, 'opened', $sort, $direction),
            'ariaSort' => $sort === 'opened' ? ($direction === 'asc' ? 'ascending' : 'descending') : '',
        ];

        $bulk = '';
        if ($status !== null && $status['values'] !== []) {
            $options = '<option value="">Set status to</option>';
            foreach ($status['values'] as $value) {
                $options .= '<option value="' . Html::e($value) . '">' . Html::e($value) . '</option>';
            }
            $bulk = '<div class="record-list__tools work-bulk__tools">'
                . '<span class="record-list__meta" data-selected-count>0 selected</span>'
                . '<select class="select work-bulk__status" name="status" aria-label="Status for selected records" required>' . $options . '</select>'
                . '<button class="btn btn--sm" type="submit" data-bulk-submit disabled>Apply</button></div>';
        }

        $list = Blocks::recordList([
            'column' => self::workColumnName($itemTypeId),
            'columnSortHref' => self::workSortHref($base, 'name', $sort, $direction),
            'columnAriaSort' => $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : '',
            'columns' => $columns,
            'searchLabel' => defined('TXT_3') ? TXT_3 : 'Search',
            'toolbar' => $bulk,
            'selectName' => $bulk !== '' ? 'item_ids' : '',
            'empty' => 'No records yet',
            'groups' => [['rows' => $rows]],
        ]);
        if ($bulk === '') {
            return $list;
        }

        return '<form class="work-bulk" method="post" action="' . Html::e($base . '&option=move_cards') . '">'
            . Csrf::field() . $list . '</form>';
    }

    private static function workSortHref(string $base, string $key, string $current, string $direction): string
    {
        $next = $current === $key && $direction === 'asc' ? 'desc' : 'asc';

        return self::workHref($base, 'list', self::currentFocus())
            . '&sort=' . rawurlencode($key) . '&dir=' . $next;
    }

    /**
     * The New tab is the one place to add a record. The list links there when it is empty.
     */
    private static function createHref(): string
    {
        if (!Page::current()->inApplication()) {
            return '';
        }
        $slug = Page::current()->applicationSlug();
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            return '';
        }
        foreach (ApplicationStore::navigation((int) $app['application_id']) as $link) {
            if (ScreenCatalog::resolve((string) ($link['capability'] ?? '')) !== ScreenCatalog::CREATE) {
                continue;
            }
            if (!\Adlexone\Application\Capabilities::linkVisible($link)) {
                continue;
            }

            return Router::applicationUrl($slug, (int) $link['nav_id']);
        }

        return '';
    }

    private static function workColumnName(?int $itemTypeId): string
    {
        if ($itemTypeId === null || $itemTypeId <= 0) {
            return 'Name';
        }
        $row = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [$itemTypeId]);
        $name = trim((string) ($row['item_type_name'] ?? ''));

        return $name !== '' ? $name : 'Name';
    }

    private static function workFieldLabel(string $name): string
    {
        $plain = self::plainFieldName($name);

        return $plain === '' ? $name : ucfirst($plain);
    }

    /**
     * Status columns for the same records as the list. Dragging a card sets that status and runs rules.
     *
     * @param list<int> $ids
     * @param array{html: string, field_id: int, values: array<int, string>} $stand
     */
    public static function workBoard(array $ids, ?int $itemTypeId, string $base, array $stand): string
    {
        $status = self::workStatusField($itemTypeId);
        if ($status === null || $status['values'] === [] || $ids === []) {
            return '';
        }
        $fieldId = (int) $status['id'];
        $values = $stand['field_id'] === $fieldId ? $stand['values'] : self::workStatusValues($ids, $fieldId);
        $columns = [];
        foreach ($status['values'] as $value) {
            $columns[$value] = [];
        }
        $unset = [];
        $facts = self::workCardFacts($ids);
        foreach ($ids as $id) {
            $value = $values[$id] ?? '';
            $fact = $facts[$id] ?? ['title' => '', 'owner' => '', 'log' => '', 'detail' => '', 'next' => ''];
            $card = self::workCard($id, $fact, $base, $value, $status['values']);
            if (isset($columns[$value])) {
                $columns[$value][] = $card;
            } else {
                $unset[] = $card;
            }
        }

        $html = '';
        foreach ($columns as $value => $cards) {
            $html .= self::workColumn((string) $value, $cards, true);
        }
        if ($unset !== []) {
            $html .= self::workColumn('Not set', $unset, false);
        }

        $move = Html::e($base . '&option=move_card');
        $token = Html::e(Csrf::token());

        Page::current()->addScript('work-board');

        return '<section class="work-board" aria-label="Board" data-move="' . $move . '" data-csrf="' . $token . '">'
            . $html
            . '</section>';
    }

    /**
     * @param list<string> $cards
     */
    private static function workColumn(string $label, array $cards, bool $drop): string
    {
        $dropAttr = $drop ? ' data-status="' . Html::e($label) . '"' : '';

        $tone = Presence::tone($drop ? $label : '');
        $state = Presence::stateClass($label);

        return '<div class="work-board__col" data-tone="' . Html::e($tone) . '"'
            . ($state !== '' ? ' data-state="' . Html::e($state) . '"' : '') . $dropAttr . '>'
            . '<div class="work-board__head"><span class="work-board__label"><span class="work-board__dot" aria-hidden="true"></span>'
            . Html::e($label) . '</span>'
            . '<span class="work-board__count">' . count($cards) . '</span></div>'
            . '<div class="work-board__cards">' . implode('', $cards) . '</div>'
            . '</div>';
    }

    /**
     * @param array{title: string, owner: string, log: string, detail?: string, next?: string} $fact
     * @param list<string> $statuses
     */
    private static function workCard(int $itemId, array $fact, string $base, string $status, array $statuses): string
    {
        $title = $fact['title'] !== '' ? $fact['title'] : ('Record ' . $itemId);
        $href = Html::e($base . '&item=' . $itemId);
        $titleEsc = Html::e($title);
        $detail = trim((string) ($fact['detail'] ?? ''));
        $next = trim((string) ($fact['next'] ?? ''));
        if ($next === '') {
            $next = trim((string) $fact['log']);
        }
        $owner = trim((string) $fact['owner']);
        $detailHtml = $detail !== ''
            ? '<p class="work-card__detail">' . Html::e($detail) . '</p>'
            : '';
        $nextHtml = $next !== ''
            ? '<p class="work-card__log">' . Html::e($next) . '</p>'
            : '<p class="work-card__log" hidden></p>';
        $ownerHtml = '<p class="work-card__owner"' . ($owner === '' ? ' hidden' : '') . '>'
            . ($owner !== '' ? Presence::mark($owner) : '')
            . '<span class="work-card__owner-name">' . Html::e($owner) . '</span></p>';
        $options = '<option value="">Move to</option>';
        foreach ($statuses as $value) {
            if ($value === $status) {
                continue;
            }
            $options .= '<option value="' . Html::e($value) . '">' . Html::e($value) . '</option>';
        }

        return '<article class="work-card" draggable="true" data-item="' . $itemId . '" data-status="' . Html::e($status) . '">'
            . '<a class="work-card__title" draggable="false" href="' . $href . '">' . $titleEsc . '</a>'
            . $detailHtml
            . $nextHtml
            . $ownerHtml
            . '<label class="work-card__move">Move <select aria-label="Move ' . $titleEsc . '">' . $options . '</select></label>'
            . '</article>';
    }


    /**
     * @param list<int> $ids
     * @return array<int, array{title: string, owner: string, log: string, detail: string, next: string}>
     */
    public static function workCardFacts(array $ids): array
    {
        $facts = [];
        if ($ids === []) {
            return $facts;
        }
        $in = implode(',', $ids);
        $owners = [];
        foreach (Database::select('items', ['item_id', 'item_title', 'user_security', 'item_type_id'], 'item_id IN (' . $in . ')') as $row) {
            $facts[(int) $row['item_id']] = [
                'title' => trim((string) ($row['item_title'] ?? '')),
                'owner' => '',
                'log' => '',
                'detail' => '',
                'next' => '',
                'user_id' => (int) ($row['user_security'] ?? 0),
                'item_type_id' => (int) ($row['item_type_id'] ?? 0),
            ];
            $owners[(int) ($row['user_security'] ?? 0)] = true;
        }
        unset($owners[0]);
        if ($owners !== []) {
            $names = [];
            foreach (Database::select('users', ['user_id', 'user_name', 'first_name', 'last_name'], 'user_id IN (' . implode(',', array_keys($owners)) . ')') as $user) {
                $names[(int) $user['user_id']] = RecordQuery::displayName($user);
            }
            foreach ($facts as $id => $fact) {
                $facts[$id]['owner'] = $names[$fact['user_id']] ?? '';
            }
        }
        $logs = Database::rows(
            'SELECT c.item_id, c.log_text FROM core_log c
             INNER JOIN (
                SELECT item_id, MAX(log_item_sequence) AS seq FROM core_log WHERE item_id IN (' . $in . ') GROUP BY item_id
             ) latest ON latest.item_id = c.item_id AND latest.seq = c.log_item_sequence'
        );
        foreach ($logs as $row) {
            $id = (int) $row['item_id'];
            if (isset($facts[$id])) {
                $facts[$id]['log'] = trim((string) ($row['log_text'] ?? ''));
            }
        }
        self::attachCardText($facts);
        foreach ($facts as $id => $fact) {
            unset($facts[$id]['user_id'], $facts[$id]['item_type_id']);
        }

        return $facts;
    }

    /**
     * Field names are unique in the database, so a later app may be stored as
     * "Next step (Matters) 2". The work surface uses the name the builder asked for.
     */
    public static function plainFieldName(string $name): string
    {
        $name = strtolower(trim($name));
        $previous = null;
        while ($name !== $previous) {
            $previous = $name;
            $name = trim((string) preg_replace('/\s+\d+$/', '', $name));
            $name = trim((string) preg_replace('/\s*\([^)]*\)$/', '', $name));
        }

        return $name;
    }

    /**
     * Client, place, or the first short text field, plus a Next step when the record has one.
     *
     * @param array<int, array<string, mixed>> $facts
     */
    private static function attachCardText(array &$facts): void
    {
        $typeIds = [];
        foreach ($facts as $fact) {
            $typeId = (int) ($fact['item_type_id'] ?? 0);
            if ($typeId > 0) {
                $typeIds[$typeId] = true;
            }
        }
        $detailField = [];
        $nextField = [];
        foreach (array_keys($typeIds) as $typeId) {
            $firstText = 0;
            $links = Database::select(
                'item_type_custom_fields',
                ['custom_field_id'],
                'item_type_id = ?',
                [$typeId],
                'custom_field_order ASC'
            );
            foreach ($links as $link) {
                $field = Database::first(
                    'custom_fields',
                    ['custom_field_id', 'custom_field_name', 'field_type'],
                    'custom_field_id = ?',
                    [(int) $link['custom_field_id']]
                );
                if ($field === null || FieldTypes::normalise((string) ($field['field_type'] ?? '')) !== FieldTypes::TEXT_BOX) {
                    continue;
                }
                $name = self::plainFieldName((string) $field['custom_field_name']);
                $fieldId = (int) $field['custom_field_id'];
                if ($name === 'next step') {
                    continue;
                }
                if ($firstText === 0) {
                    $firstText = $fieldId;
                }
                if ($name === 'client' || $name === 'place') {
                    $detailField[$typeId] = $fieldId;
                }
            }
            if (!isset($detailField[$typeId]) && $firstText > 0) {
                $detailField[$typeId] = $firstText;
            }
            $step = WorkflowFields::resolve(self::applicationSlug(), (int) $typeId, WorkflowFields::STEP);
            if ($step !== null) {
                $nextField[$typeId] = $step['id'];
            }
        }
        $wanted = array_values(array_unique(array_filter(array_merge(array_values($detailField), array_values($nextField)))));
        $stored = [];
        if ($wanted !== [] && $facts !== []) {
            $rows = Database::rows(
                'SELECT item_id, custom_field_id, value FROM item_field_values WHERE item_id IN ('
                . implode(',', array_map('intval', array_keys($facts)))
                . ') AND custom_field_id IN (' . implode(',', array_map('intval', $wanted)) . ')'
            );
            foreach ($rows as $row) {
                $stored[(int) $row['item_id']][(int) $row['custom_field_id']] = trim((string) ($row['value'] ?? ''));
            }
        }
        foreach ($facts as $id => $fact) {
            $typeId = (int) ($fact['item_type_id'] ?? 0);
            $detailId = $detailField[$typeId] ?? 0;
            $nextId = $nextField[$typeId] ?? 0;
            $facts[$id]['detail'] = $detailId > 0 ? ($stored[$id][$detailId] ?? '') : '';
            $facts[$id]['next'] = $nextId > 0 ? ($stored[$id][$nextId] ?? '') : '';
        }
    }

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    public static function workStatusValues(array $ids, int $fieldId): array
    {
        $values = [];
        if ($fieldId <= 0 || $ids === []) {
            return $values;
        }
        $stored = Database::rows(
            'SELECT item_id, value FROM item_field_values WHERE custom_field_id = ? AND item_id IN (' . implode(',', $ids) . ')',
            [$fieldId]
        );
        foreach ($stored as $row) {
            $values[(int) $row['item_id']] = (string) ($row['value'] ?? '');
        }

        return $values;
    }

    public static function moveWorkCard(?int $itemTypeId): void
    {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        $ids = self::applicationAccessibleItemIds($itemTypeId);
        $field = self::workStatusField($itemTypeId);
        if ($itemId <= 0 || !in_array($itemId, $ids, true) || $field === null || !in_array($status, $field['values'], true)) {
            self::workJson(['ok' => false], 422);
        }

        $key = 'custom_field_' . (int) $field['id'];
        $_POST[$key] = $status;
        \Adlexone\Application\Rules::run($itemId, 'update_item');
        ItemFields::saveFromArray($itemId, [$key => $status]);

        $values = self::workStatusValues($ids, (int) $field['id']);
        $counts = [];
        foreach ($field['values'] as $value) {
            $counts[$value] = 0;
        }
        foreach ($ids as $id) {
            $value = $values[$id] ?? '';
            if ($value !== '' && array_key_exists($value, $counts)) {
                $counts[$value]++;
            }
        }
        $facts = self::workCardFacts([$itemId]);
        $fact = $facts[$itemId] ?? ['title' => '', 'owner' => '', 'log' => '', 'detail' => '', 'next' => ''];
        $line = trim((string) ($fact['next'] ?? ''));
        if ($line === '') {
            $line = (string) ($fact['log'] ?? '');
        }
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $mine = 0;
        if ($userId > 0) {
            $mine = count(Database::select('items', ['item_id'], 'user_security = ? AND item_id IN (' . implode(',', $ids) . ')', [$userId]));
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        self::workJson([
            'ok' => true,
            'item_id' => $itemId,
            'status' => $status,
            'owner' => $fact['owner'],
            'log' => $line,
            'counts' => $counts,
            'records' => count($ids),
            'mine' => $mine,
        ]);
    }

    public static function moveWorkCards(?int $itemTypeId): void
    {
        $posted = RecordQuery::ints($_POST['item_ids'] ?? []);
        $accessible = self::applicationAccessibleItemIds($itemTypeId);
        $ids = array_values(array_intersect($posted, $accessible));
        $status = trim((string) ($_POST['status'] ?? ''));
        $field = self::workStatusField($itemTypeId);
        if ($ids === [] || $field === null || !in_array($status, $field['values'], true)) {
            header('Location: ' . (defined('MAN_BASE_URL') ? (string) MAN_BASE_URL : './'));
            exit;
        }
        foreach ($ids as $itemId) {
            RecordQuery::saveField($itemId, (int) $field['id'], $status);
        }

        header('Location: ' . (defined('MAN_BASE_URL') ? (string) MAN_BASE_URL : './'));
        exit;
    }

    private static function applicationSlug(): string
    {
        return Page::current()->inApplication() ? Page::current()->applicationSlug() : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function workJson(array $payload, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
