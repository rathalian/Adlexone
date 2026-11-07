<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Http\Request;

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\RecordQuery;
use Adlexone\Application\ScreenCatalog;
use Adlexone\Application\WorkflowFields;
use Adlexone\Auth\Csrf;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Database\Database;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\FieldValidation;
use Adlexone\Http\Page;
use Adlexone\Http\Router;
use Adlexone\Html\Blocks;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;


final class WorkList
{


    private static function currentFocus(): string
    {
        $focus = (string) (Request::queryValue('focus') ?? '');

        return in_array($focus, ['mine', 'status', 'empty'], true) ? $focus : '';
    }


    public static function workViewSwitch(string $base, string $view): string
    {
        $focus = self::currentFocus();
        $links = '';
        foreach (['list' => 'List', 'board' => 'Board'] as $key => $label) {
            $selected = $view === $key;
            $links .= '<a class="work-views__link' . ($selected ? ' is-selected' : '') . '" href="'
                . Html::e(WorkScreen::workHref($base, $key, $focus)) . '"'
                . ($selected ? ' aria-current="page"' : '') . '>'
                . Html::e($label) . '</a>';
        }

        return '<div class="work-views" role="group" aria-label="View">' . $links . '</div>';
    }


    /**
     * @param list<int> $ids
     * @param array{html: string, field_id: int, values: array<int, string>} $stand
     */
    public static function workList(array $ids, ?int $itemTypeId, string $base, array $stand): string
    {
        $facts = WorkScreen::workCardFacts($ids);
        $status = WorkScreen::workStatusField($itemTypeId);
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
        $sort = in_array((string) (Request::queryValue('sort') ?? ''), ['name', 'status', 'opened'], true)
            ? (string) Request::queryValue('sort')
            : 'opened';
        $direction = (string) (Request::queryValue('dir') ?? '') === 'asc' ? 'asc' : 'desc';
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

        $wait = WorkScreen::waitContext($itemTypeId);
        $dueValues = $wait['field_id'] > 0 ? RecordQuery::values($ids, $wait['field_id']) : [];
        foreach ($rows as $index => $record) {
            $itemId = (int) $record['itemId'];
            $raw = trim((string) ($dueValues[$itemId] ?? ''));
            $rows[$index]['due'] = $raw === '' ? '' : FieldValidation::formatDate($raw);
        }
        $bulk = '';
        if ($status !== null && $status['values'] !== []) {
            $options = '<option value="">Set status to</option>';
            foreach ($status['values'] as $value) {
                $options .= '<option value="' . Html::e($value) . '">' . Html::e($value) . '</option>';
            }
            $due = '';
            if ($wait['waits'] !== []) {
                $due = '<label class="work-bulk__due"> ' . Html::e($wait['label'])
                    . ' <input class="input" type="text" name="due" inputmode="numeric" autocomplete="off" spellcheck="false" placeholder="'
                    . Html::e($wait['example']) . '" aria-label="' . Html::e($wait['label']) . '" data-due-input>'
                    . '</label>';
            }
            $bulk = '<div class="record-list__tools work-bulk__tools">'
                . '<span class="record-list__meta" data-selected-count>0 selected</span>'
                . '<select class="select work-bulk__status" name="status" aria-label="Status for selected records" required>' . $options . '</select>'
                . $due
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

        Page::current()->addScript('work-board');
        $waitAttr = $wait['waits'] === []
            ? ''
            : ' data-waits="' . Html::e(implode('|', $wait['waits'])) . '" data-due-label="' . Html::e($wait['label']) . '"';

        return '<form class="work-bulk" method="post" action="' . Html::e($base . '&option=move_cards') . '"' . $waitAttr . '>'
            . Csrf::field() . $list . '</form>';
    }


    private static function workSortHref(string $base, string $key, string $current, string $direction): string
    {
        $next = $current === $key && $direction === 'asc' ? 'desc' : 'asc';

        return WorkScreen::workHref($base, 'list', self::currentFocus())
            . '&sort=' . rawurlencode($key) . '&dir=' . $next;
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
        $plain = WorkScreen::plainFieldName($name);

        return $plain === '' ? $name : ucfirst($plain);
    }
}
