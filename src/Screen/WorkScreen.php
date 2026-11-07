<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Http\Request;

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\RecordQuery;
use Adlexone\Application\ScreenCatalog;
use Adlexone\Application\WorkflowFields;
use Adlexone\Data\GroupMembership;
use Adlexone\Database\Database;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\FieldValidation;
use Adlexone\Http\Page;
use Adlexone\Http\Router;
use Adlexone\Html\Blocks;
use Adlexone\Html\Html;

/**
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
        if ((string) (Request::queryValue('option') ?? '') === 'move_card' && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            WorkBoard::moveWorkCard($itemTypeId);
            return;
        }
        if ((string) (Request::queryValue('option') ?? '') === 'move_cards' && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            WorkBoard::moveWorkCards($itemTypeId);
            return;
        }

        $ids = self::applicationAccessibleItemIds($itemTypeId);
        $base = self::place();
        $stand = self::workStand($ids, $itemTypeId, $base);
        $shown = self::workFocusIds($ids, $stand['field_id'], $stand['values']);
        $status = self::workStatusField($itemTypeId);
        $canBoard = $status !== null && $status['values'] !== [];
        $view = $canBoard && self::workView() === 'board' ? 'board' : 'list';
        $createHref = self::createHref();
        if ($createHref === '') {
            $createHref = $base . '&option=choose_type';
        }
        $new = '<a class="btn btn--primary" href="' . Html::e($createHref) . '">New</a>';
        $switch = $canBoard ? WorkList::workViewSwitch($base, $view) : '';
        $toolbar = '<div class="work-toolbar">' . $switch . $stand['html'] . '</div>';
        if ($ids === []) {
            $body = Blocks::emptyState([
                'illustration' => 'empty-work',
                'title' => 'No records yet',
                'copy' => 'Create the first record.',
                'action' => $new,
            ]);
        } elseif ($shown === []) {
            $focus = (string) (Request::queryValue('focus') ?? '');
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
            $body = $toolbar . WorkBoard::workBoard($shown, $itemTypeId, $base, $stand);
        } else {
            $body = $toolbar . WorkList::workList($shown, $itemTypeId, $base, $stand);
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

        $focus = (string) (Request::queryValue('focus') ?? '');
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
        $focus = (string) (Request::queryValue('focus') ?? '');
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
            $want = (string) (Request::queryValue('status') ?? '');
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
        return (string) (Request::queryValue('view') ?? '') === 'board' ? 'board' : 'list';
    }

    public static function workHref(string $base, string $view, string $focus = ''): string
    {
        $query = [];
        if ($focus === 'mine' || $focus === 'status' || $focus === 'empty') {
            $query[] = 'focus=' . rawurlencode($focus);
            if ($focus === 'status') {
                $query[] = 'status=' . rawurlencode((string) (Request::queryValue('status') ?? ''));
            }
        }
        $query[] = 'view=' . rawurlencode($view);

        return $base . '&' . implode('&', $query);
    }

    public static function place(): string
    {
        $page = Page::current();
        if ($page->inApplication()) {
            return Router::applicationUrl($page->applicationSlug(), $page->navId());
        }

        return Router::page();
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
    public static function workBoard(array $ids, ?int $itemTypeId, string $base, array $stand): string
    {
        return WorkBoard::workBoard($ids, $itemTypeId, $base, $stand);
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
        WorkBoard::moveWorkCard($itemTypeId);
    }

    public static function moveWorkCards(?int $itemTypeId): void
    {
        WorkBoard::moveWorkCards($itemTypeId);
    }


    /**
     * @return array{label: string, waits: list<string>, field_id: int, example: string}
     */
    public static function waitContext(?int $itemTypeId): array
    {
        $empty = ['label' => '', 'waits' => [], 'field_id' => 0, 'example' => ''];
        $typeId = (int) $itemTypeId;
        if ($typeId <= 0) {
            return $empty;
        }
        $slug = self::applicationSlug();
        $due = WorkflowFields::resolve($slug, $typeId, WorkflowFields::DUE);
        $waits = WorkflowFields::waitStatuses($slug, $typeId);
        if ($due === null || $waits === []) {
            return $empty;
        }

        return [
            'label' => (string) $due['label'],
            'waits' => $waits,
            'field_id' => (int) $due['id'],
            'example' => FieldValidation::formatDate('2026-10-14'),
        ];
    }

    public static function applicationSlug(): string
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
