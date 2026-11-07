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
     * Column and direction for a record list. The previous choice is a column name, not a statement.
     */
    private static function listOrder(string $fallback, bool $remember): string
    {
        unset($_SESSION['item_sql']);
        $posted = Database::orderBy(is_scalar(Request::postedValue('currsort') ?? null) ? (string) Request::postedValue('currsort') : '');
        $previous = Database::orderBy((string) ($_SESSION['item_sort'] ?? ''));
        if ($posted !== '') {
            $postedColumn = explode(' ', $posted)[0];
            $previousColumn = $previous === '' ? '' : explode(' ', $previous)[0];
            $order = ($postedColumn === $previousColumn && !str_contains($previous, 'DESC'))
                ? $postedColumn . ' DESC'
                : $postedColumn;
        } elseif ($remember && $previous !== '' && Request::hasPostedKey('search_type')) {
            $order = $previous;
        } else {
            $order = Database::orderBy($fallback);
            if ($order === '') {
                $order = 'item_id DESC';
            }
        }
        if ($remember) {
            $_SESSION['item_sort'] = $order;
        }

        return $order;
    }

/**
     * Shows a list of items defined by item id, filtered by specific item where applicable
     *
     * @param array<mixed>|int|string $itemIDArray Item ids, or one id
     * @param int|string $itemID Item id to show on its own, or an empty string for the whole list
     * @param string $orderSQL Order by clause
     */
    public static function showItems($itemIDArray, $itemID = '', $orderSQL = '')
    {
        // Get data from database
        global $html;
        $columnArray = array('item_id', 'create_date', 'item_type_id', 'item_title');
        if ((string) $itemID !== '') {
            $ids = [(int) $itemID];
        } else {
            $ids = RecordListQuery::ids(is_array($itemIDArray) ? $itemIDArray : []);
        }
        $countrecs = count((array)$itemIDArray);
        $countBoolean = (($countrecs / (int) SET_ITEMS_PAGE) > 1);

        $orderSQL = self::listOrder((string) $orderSQL, (string) $itemID === '');
        $pageSize = max(1, (int) SET_ITEMS_PAGE);
        $page = RecordListQuery::page(Request::postedValue('pageset') ?? 1);
        $result = RecordList::fetch($ids, $columnArray, $orderSQL, $pageSize, ($page - 1) * $pageSize);

        $listHtml = '';
        $sortLinks = '';
        if ($countrecs > 1) {
            $sortLinks = '<div>'
                . Access::outputIfAllowed(Form::sortButton('item_id', Wording::get('LA_102', 'TXT_102')), Permission::RECORDS_EDIT)
                . ' '
                . Access::outputIfAllowed(Form::sortButton('item_title', Wording::get('LA_84', 'TXT_84')), Permission::RECORDS_EDIT)
                . ' '
                . Access::outputIfAllowed(Form::sortButton('create_date', TXT_225), Permission::RECORDS_EDIT)
                . ' '
                . Access::outputIfAllowed(Form::sortButton('item_type_id', Wording::get('LA_226', 'TXT_226')), Permission::RECORDS_EDIT)
                . '</div>';
        }

        if (count($result) > 0) {
            $itemTypeID = '';
            $itemTypeName = '';
            foreach ($result as $row) {
                if ($row['item_title'] == '') {
                    $title = TXT_357;
                } else {
                    $title = $row['item_title'];
                }
                $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
                $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
                if ($itemTypeID != $row['item_type_id']) {
                    $itemTypeRow = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [(int) $row['item_type_id']]);
                    $itemTypeName = (string) ($itemTypeRow['item_type_name'] ?? '');
                    $itemTypeID = $row['item_type_id'];
                }
                $itemId = (int) $row['item_id'];
                $action = Access::outputIfAllowed('<a href="' . Html::e(ItemScreen::recordUrl('item=' . $itemId . '&option=show_attachments')) . '" class="URL">' . TXT_389 . '</a>', Permission::RECORDS_EDIT);
                $action .= Access::outputIfAllowed(' - <a href="' . Html::e(ItemScreen::recordUrl('item=' . $itemId . '&option=change_security')) . '" class="URL">' . TXT_28 . '</a>', Permission::RECORDS_ASSIGN);
                $action .= Access::outputIfAllowed(' - <a href="' . Html::e(ItemScreen::recordUrl('item=' . $itemId . '&option=log_entry')) . '" class="URL">' . TXT_246 . '</a>', Permission::RECORDS_EDIT);
                $action .= Access::outputIfAllowed(' - ' . Blocks::link(Router::manageUrl('print', 'item=' . $itemId), TXT_625, 'URL', '', '', '_blank'), Permission::APP_ACCESS);
                $action .= Access::outputIfAllowed(
                    ' - ' . Blocks::link(
                        ItemScreen::recordUrl('item=' . $itemId . '&option=delete_item'),
                        TXT_315,
                        '',
                        'URL',
                        '',
                        '_parent',
                        TXT_400
                    ),
                    Permission::RECORDS_CONTROL
                );
                $listHtml .= Form::fields([
                    Wording::get('LA_102', 'TXT_102') => (string)$row['item_id'],
                    Wording::get('LA_84', 'TXT_84') => '<a href="' . Html::e(ItemScreen::recordUrl('item=' . $itemId . '&option=show_item' . $logEntry . $attachments)) . '" class="URL">' . Html::e((string) $title) . '</a>',
                    TXT_225 => date(SET_DATE_FORMAT, $row['create_date']),
                    Wording::get('LA_226', 'TXT_226') => (string)$itemTypeName,
                    TXT_388 => $action,
                ]);
                $listHtml .= Blocks::separator();
                unset($action);
            }
        } else {
            $listHtml = Form::fields(['' => Html::e(TXT_115)]);
        }
        $paginationHTML = '';
        $hidden = '';
        $pageset = '';
        if ($countBoolean) {
            $pageset = (Request::hasPostedKey('pageset')) ? Request::postedValue('pageset') : 1;
            $pager = RecordList::pager($countrecs, (int) SET_ITEMS_PAGE, $pageset);
            $paginationHTML = $pager['html'];
            $pageset = $pager['page'];
            if ($itemID == '') {
                $paginationHTML .= '</ul></div>';
                $hidden .= Form::hidden('pageset', (string)$pageset);
            }
        }
        if ($itemID == '') {
            $hidden .= Form::hidden('currsort', '');
            $hidden .= Form::hidden('search', (string) $pageset);
            $hidden .= Form::hidden('countrecs', (string)$countrecs);
            $hidden .= Form::hidden('search_type', Request::query('option'));
        }

        $html = Form::form(
            Wording::get('LA_44', 'TXT_44'),
            ItemScreen::recordUrl('option=my_items'),
            ['' => $sortLinks . $listHtml . $paginationHTML . $hidden],
            [],
            ['name' => 'repform', 'id' => 'repform']
        );
        Page::current()->setBody($html);
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
