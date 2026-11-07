<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Auth\Role;
use Adlexone\Database\RecordListQuery;
use Adlexone\Auth\Permission;
use Adlexone\Application\ApplicationAccess;
use Adlexone\Application\ApplicationSettings;
use Adlexone\Application\RecordQuery;
use Adlexone\Application\Rules;
use Adlexone\Application\WorkflowFields;
use Adlexone\Auth\Csrf;
use Adlexone\Database\Database;
use Adlexone\Application\Actions;
use Adlexone\Data\AttachmentUpload;
use Adlexone\Html\FieldControl;
use Adlexone\Data\FieldCheck;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\FieldValidation;
use Adlexone\Data\MenuOptions;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Http\Router;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Text\Wording;
use Adlexone\Html\Html;

final class ItemList
{
    /**
     * Shows a list of items defined by item id, filtered by a specific item where applicable.
     *
     * @param array<mixed>|int|string $itemIDArray Item ids, or one id
     * @param int|string $itemID Item id to show on its own, or an empty string for the whole list
     * @param string $orderSQL Order by clause, used when the address has no sort
     */
    public static function showItems($itemIDArray, $itemID = '', $orderSQL = '')
    {
        unset($_SESSION['item_sql']);
        if ((string) $itemID !== '') {
            $ids = [(int) $itemID];
        } else {
            $ids = RecordListQuery::ids(is_array($itemIDArray) ? $itemIDArray : []);
        }

        $base = ItemScreen::recordUrl('option=my_items');
        $sorts = ['name' => 'item_title', 'type' => 'item_type_id', 'opened' => 'create_date', 'id' => 'item_id'];
        $requested = (string) (Request::queryValue('sort') ?? '');
        $sort = isset($sorts[$requested]) ? $requested : '';
        $direction = (string) (Request::queryValue('dir') ?? '') === 'asc' ? 'asc' : 'desc';
        if ($sort === '') {
            $fallback = RecordListQuery::order((string) $orderSQL);
            $column = explode(' ', $fallback)[0];
            $sort = array_search($column, $sorts, true);
            $sort = is_string($sort) ? $sort : 'opened';
            $direction = str_contains($fallback, 'DESC') ? 'desc' : 'asc';
        }
        $order = RecordListQuery::order($sorts[$sort] . ' ' . ($direction === 'asc' ? 'ASC' : 'DESC'));
        $pageSize = max(1, (int) SET_ITEMS_PAGE);
        $pages = max(1, (int) ceil(count($ids) / $pageSize));
        $page = min($pages, RecordListQuery::page(Request::queryValue('page')));
        $result = RecordList::fetch($ids, ['item_id', 'create_date', 'item_type_id', 'item_title'], $order, $pageSize, ($page - 1) * $pageSize);

        $rows = [];
        foreach ($result as $row) {
            $itemId = (int) $row['item_id'];
            $rows[] = Blocks::itemRecord(
                $row,
                $base . '&item=' . $itemId . '&option=show_item',
                Blocks::itemActions($itemId, ItemScreen::recordUrl())
            );
        }

        $columns = Blocks::itemListColumns();
        foreach ($columns as $index => $column) {
            $key = $column['key'] === 'type' ? 'type' : 'opened';
            $columns[$index]['sortHref'] = self::listHref($base, $key, $sort, $direction, 1);
            $columns[$index]['ariaSort'] = $sort === $key ? ($direction === 'asc' ? 'ascending' : 'descending') : '';
        }

        $title = (string) Wording::get('LA_44', 'TXT_44');
        $list = $rows === [] && $ids === []
            ? Blocks::emptyState([
                'illustration' => 'empty-work',
                'title' => 'No records yet',
                'copy' => 'Records you own or share are listed here.',
            ])
            : Blocks::recordList([
                'column' => (string) Wording::get('LA_84', 'TXT_84'),
                'columnSortHref' => self::listHref($base, 'name', $sort, $direction, 1),
                'columnAriaSort' => $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : '',
                'columns' => $columns,
                'searchLabel' => defined('TXT_3') ? TXT_3 : 'Search',
                'toolbar' => self::listPager($base, $sort, $direction, $page, $pages, count($ids)),
                'empty' => defined('TXT_115') ? TXT_115 : 'No records',
                'groups' => [['rows' => $rows]],
            ]);

        Page::current()->setBody(Blocks::vertical([[
            'title' => $title,
            'html' => $list,
            'class' => 'card--work',
        ]]));
    }

    private static function listHref(string $base, string $key, string $current, string $direction, int $page): string
    {
        $next = $current === $key && $direction === 'asc' ? 'desc' : 'asc';
        if ($current !== $key) {
            $next = $key === 'name' ? 'asc' : 'desc';
        }
        $href = $base . '&sort=' . rawurlencode($key) . '&dir=' . $next;
        if ($page > 1) {
            $href .= '&page=' . $page;
        }

        return $href;
    }

    private static function listPager(string $base, string $sort, string $direction, int $page, int $pages, int $total): string
    {
        if ($pages < 2 || $total === 0) {
            return '';
        }
        $from = ($page - 1) * max(1, (int) SET_ITEMS_PAGE) + 1;
        $to = min($total, $page * max(1, (int) SET_ITEMS_PAGE));
        $href = static function (int $target) use ($base, $sort, $direction): string {
            $link = $base . '&sort=' . rawurlencode($sort) . '&dir=' . rawurlencode($direction);
            if ($target > 1) {
                $link .= '&page=' . $target;
            }

            return $link;
        };
        $html = '<span class="record-list__meta">Showing ' . $from . '–' . $to . ' of ' . $total . '</span>';
        if ($page > 1) {
            $html .= '<a class="btn btn--sm" href="' . Html::e($href($page - 1)) . '">Previous</a>';
        }
        if ($page < $pages) {
            $html .= '<a class="btn btn--sm" href="' . Html::e($href($page + 1)) . '">Next</a>';
        }

        return '<span class="record-list__tools">' . $html . '</span>';
    }

/**
     * Shows the available item types.
     *
     * This function retrieves and displays a list of available item types. It generates
     * a form with a dropdown for selecting an item type and a submit button. If no item
     * types are available, it displays an error message. The function also handles
     * security checks to ensure that only authorized users can view certain item types.
     *
     * @param string $defaultItemType (Optional) The item type to show by default.
     */

    public static function showItemTypes($defaultItemType = '')
    {
        $fields = [];

        // Get all item types
        $condition = "WHERE enabled = 'Yes' ORDER BY item_type_name ASC";
        $result = Database::select('item_types', ['item_type_id', 'item_type_name'], $condition);

        if (Page::current()->inApplication()) {
            $allowed = ApplicationSettings::allowedItemTypeIds(Page::current()->applicationSlug());
            $result = array_values(array_filter(
                $result,
                static fn (array $row): bool => in_array((int) $row['item_type_id'], $allowed, true)
            ));
            if ($defaultItemType === '' || $defaultItemType === null) {
                $defaultItemType = ApplicationSettings::defaultItemTypeId(Page::current()->applicationSlug());
            }
        }

        $noItemTypes = false;
        if (count($result) > 0) {
            if (SET_SECURE_TYPE != 'yes') {
                foreach ($result as $row) {
                    $valueArray[] = $row['item_type_id'];
                    $displayArray[] = $row['item_type_name'];
                }
            } else {
                $userGroupIds = GroupMembership::userGroupIds((int) ($_SESSION['access_user_id'] ?? 0));
                if ($userGroupIds === []) {
                    $noItemTypes = true;
                } else {
                    foreach ($result as $row) {
                        $typeFound = false;
                        $viewerGroupFound = false;
                        $typeGroupIds = GroupMembership::itemTypeGroupIds((int) $row['item_type_id']);
                        foreach ($userGroupIds as $group) {
                            if (in_array((int) $group, $typeGroupIds, true)) {
                                $typeFound = true;
                                if (!Access::canControlRecords() && Role::recordLevelForGroup((int) $group) === 'view') {
                                    $viewerGroupFound = true;
                                }
                            }
                        }
                        if ($typeFound && !$viewerGroupFound) {
                            $valueArray[] = $row['item_type_id'];
                            $displayArray[] = $row['item_type_name'];
                        }
                        if ($defaultItemType == '' && $row['item_type_id'] == SET_DEFAULT_ITEM_TYPE) {
                            $defaultItemType = $row['item_type_id'];
                        }
                    }
                }
                if (!isset($valueArray)) {
                    $noItemTypes = true;
                }
            }
        } else {
            $noItemTypes = true;
        }

        if (isset($valueArray) && count($valueArray) < 2) {
            ItemSheet::showItemAdd($valueArray[0], Request::postedAll());
            return;
        }

        if (!$noItemTypes && isset($valueArray, $displayArray)) {
            $inApplication = Page::current()->inApplication();
            $fields[$inApplication ? 'What is this' : Wording::get('LA_66', 'TXT_66')] = Form::select('item_type_id', $valueArray, $displayArray, $defaultItemType);
            Page::current()->setBody(Form::form(
                $inApplication ? 'New' : Wording::get('LA_46', 'TXT_46'),
                ItemScreen::recordUrl('option=new_item'),
                $fields,
                [Form::button('submit', 'submit_button', $inApplication ? 'Continue' : TXT_69)]
            ));
            return;
        }

        if (Page::current()->inApplication()) {
            Page::current()->setBody(Blocks::emptyState([
                'illustration' => 'empty-work',
                'title' => 'Nothing to add',
                'copy' => 'This application has no record type to open.',
            ]));
            return;
        }
        View::respond(TXT_341);
    }

    public static function showMyItems($userID, $itemID = '')
    {
        $uid = (int) ($_SESSION['access_user_id'] ?? 0);
        $userItemArray = Database::select('items', ['item_id'], 'user_security = ? OR creator_security = ?', [$uid, $uid]);
        $groupItemArray = array_map(
            static fn(int $id): array => ['item_id' => $id],
            \Adlexone\Data\GroupMembership::itemIdsForUser($uid)
        );
        $mergedArray = array_merge($groupItemArray, $userItemArray);
        $itemArray = array_unique($mergedArray, SORT_REGULAR);
        self::showItems($itemArray, $itemID, 'item_id DESC');
    }
}
