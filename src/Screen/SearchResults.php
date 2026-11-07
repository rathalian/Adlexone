<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Database\RecordListQuery;
use Adlexone\Database\Database;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Html\Blocks;
use Adlexone\Http\View;
use Adlexone\Text\Wording;
use Adlexone\Html\Html;

final class SearchResults
{
/**
     * Simplified item listing that renders items as vertical cards.
     *
     * Parameters mirror the original showItems for compatibility.
     *
     * - Accepts:
     *   - $itemIDArray: array of ids, or array of rows with item_id
     *   - $itemID: optional single id filter
     *   - $orderSQL: optional ORDER BY clause (pass column(s) only)
     *   - $userID, $savedSearch: kept for signature compatibility (not used here)
     *
     * Notes:
     * - IDs are coerced to integers before inclusion in the IN() list to prevent injection through IDs.
     * - This function focuses on readable output and simple, maintainable structure.
     */
    public static function showItems($itemIDArray, $itemID = '', $orderSQL = '', $userID = '', $savedSearch = ''): void
    {
        // Normalize input: if a SQL string was passed, build an array using Database helper
        $built = is_array($itemIDArray) ? $itemIDArray : [];

        // Normalize to a flat list of integer IDs
        $ids = [];
        if (!empty($built)) {
            $first = $built[0] ?? null;
            if (is_array($first) && array_key_exists('item_id', $first)) {
                // Array of rows
                foreach ($built as $row) {
                    $ids[] = (int)$row['item_id'];
                }
            } else {
                // Flat list
                foreach ($built as $v) {
                    if ((string)$v !== '') {
                        $ids[] = (int)$v;
                    }
                }
            }
        }

        // If a specific item ID was requested, restrict to it (only if present in the provided set)
        if ($itemID !== '') {
            $requested = (int)$itemID;
            if (in_array($requested, $ids, true)) {
                $ids = [$requested];
            } else {
                // Not permitted or not present -> no results
                $ids = [];
            }
        }

        $heading = defined('SEARCH_NAME')
            ? (string)SEARCH_NAME
            : (string)Wording::application('TXT_1', (string)Wording::get('LA_44', 'TXT_44'));

        if (empty($ids)) {
            $again = SearchScreen::address('search', Page::current()->inApplication() ? 'option=show_item_search' : 'option=show_quick_search');
            Page::current()->setBody(Blocks::vertical([
                [
                    'title' => $heading,
                    'html' => Blocks::emptyState([
                        'illustration' => 'empty-search',
                        'title' => 'No records matched',
                        'copy' => 'Change the search and run it again.',
                        'action' => $again !== ''
                            ? '<a class="btn btn--primary" href="' . Html::e($again) . '">Search again</a>'
                            : '',
                    ]),
                ],
            ]));
            return;
        }

        $ids = RecordListQuery::ids($ids);
        $columns = ['item_id', 'item_title', 'create_date', 'item_type_id'];
        $result = RecordList::fetch($ids, $columns, RecordListQuery::order((string) $orderSQL));

        $base = SearchScreen::address('records');
        $rows = [];
        foreach ($result as $row) {
            $itemId = (int)$row['item_id'];
            $rows[] = Blocks::itemRecord(
                $row,
                $base . '&item=' . $itemId,
                Blocks::itemActions($itemId, $base)
            );
        }

        Page::current()->setBody(Blocks::vertical([
            [
                'title' => $heading,
                'html' => Blocks::recordList([
                    'column' => Wording::get('LA_84', 'TXT_84'),
                    'columns' => Blocks::itemListColumns(),
                    'searchLabel' => TXT_3,
                    'empty' => TXT_115,
                    'groups' => [['rows' => $rows]],
                ]),
            ],
        ]));
    }

/**
     * Wrapper function for template: search_item_search.html
     *
     * Creates a table with results from a search
     */
    public static function searchResults($sql = '', array $params = [])
    {

        // Handling accessing the page from option=show_search_results with no POSTed data
        if (Request::blankPosted('search') and Request::blankPosted('new_favourite') and Request::posted('item_type_id') == '' and Request::blankPosted('search_type')) {
            View::respond(TXT_548);
            return;
        }

        // Handle item id filtering
    if (Request::hasPostedKey('item_type_id') && Request::postedValue('item_type_id') !== '' && Request::blankPosted('search') && Request::blankPosted('new_favourite')) {
                SearchForm::showAdvancedItemSearch(Request::postedValue('item_type_id'));
                return;
            }
        // Generate from form based values or pre defined sql
        $andOr = '';

        if ($sql == '') {
            $condition = 'WHERE';
            $allowedTypes = SearchForm::applicationSearchTypeIds();
            // Item types
            if (!Request::blankPosted('item_type_id')) {
                $postedType = (int) Request::postedValue('item_type_id');
                if ($allowedTypes !== [] && !in_array($postedType, $allowedTypes, true)) {
                    Access::deny();
                }
                $condition .= " item_type_id = '" . $postedType . "' ";
                $andOr = 'AND';
            } elseif ($allowedTypes !== []) {
                $condition .= ' item_type_id IN (' . implode(',', array_map('intval', $allowedTypes)) . ') ';
                $andOr = 'AND';
            }
            // Item ID
            if (!Request::blankPosted('item_id')) {
                $itemIdText = SearchForm::postedText('item_id');
                if (SearchForm::operator(Request::postedValue('id_operator') ?? '=') === '=') {
                    $condition .= $andOr . " item_id = " . (ctype_digit($itemIdText) ? $itemIdText : '0') . " ";
                } else {
                    $condition .= $andOr . " item_id LIKE ? ESCAPE '\\' ";
                    $params[] = '%' . Database::escapeLike($itemIdText) . '%';
                }
                $andOr = SearchForm::joiner(Request::postedValue('id_andor') ?? '');
            }
            // Hour Range
            if (!Request::blankPosted('hour_range')) {
                $hours = (int) SearchForm::postedText('hour_range');
                $condition .= $andOr . " " . SearchForm::dateColumn(Request::postedValue('hour_type') ?? '') . " " . SearchForm::operator(Request::postedValue('hour_range_operator') ?? '', '<=') . " " . (time() - $hours * 60 * 60) . " ";
                $andOr = SearchForm::joiner(Request::postedValue('hour_range_andor') ?? '');
            }
            // Date Created 1 and 2
            foreach (['1', '2'] as $n) {
                $time = SearchForm::postedTime('date_' . $n);
                if ($time !== null) {
                    $condition .= $andOr . " " . SearchForm::dateColumn(Request::postedValue('date_type_' . $n) ?? '') . " " . SearchForm::operator(Request::postedValue('date_operator_' . $n) ?? '', '>=') . " " . $time . " ";
                    $andOr = SearchForm::joiner(Request::postedValue('date_andor_' . $n) ?? '');
                }
            }
            // Creator User
            if (!Request::blankPosted('creator_security')) {
                $condition .= $andOr . " creator_security " . SearchForm::operator(Request::postedValue('security_creators_operator') ?? '') . " " . SearchForm::personId(SearchForm::postedText('creator_security')) . " ";
                $andOr = SearchForm::joiner(Request::postedValue('security_creators_andor') ?? '');
            }
            // Security User
            if (!Request::blankPosted('user_security')) {
                $condition .= $andOr . " user_security " . SearchForm::operator(Request::postedValue('security_users_operator') ?? '') . " " . SearchForm::personId(SearchForm::postedText('user_security')) . " ";
                $andOr = SearchForm::joiner(Request::postedValue('security_users_andor') ?? '');
            }
            // Security Group
            if (!Request::blankPosted('security_groups')) {
                $groupId = (int) SearchForm::postedText('security_groups');
                if (SearchForm::operator(Request::postedValue('security_groups_operator') ?? '') === '=') {
                    $condition .= $andOr . " item_id IN (SELECT item_id FROM item_groups WHERE group_id = " . $groupId . ") ";
                } else {
                    $condition .= $andOr . " item_id NOT IN (SELECT item_id FROM item_groups WHERE group_id = " . $groupId . ") ";
                }
                $andOr = SearchForm::joiner(Request::postedValue('security_groups_andor') ?? '');
            }
            // Item Title
            if (!Request::blankPosted('item_title')) {
                $titleText = SearchForm::postedText('item_title');
                if (SearchForm::operator(Request::postedValue('item_title_operator') ?? 'LIKE', 'LIKE') === '=') {
                    $condition .= $andOr . ' item_title = ? ';
                    $params[] = $titleText;
                } else {
                    $condition .= $andOr . " item_title LIKE ? ESCAPE '\\' ";
                    $params[] = '%' . Database::escapeLike($titleText) . '%';
                }
                $andOr = SearchForm::joiner(Request::postedValue('title_andor') ?? '');
            }

            // Custom Fields
            $i = 0;
            $customFieldCount = min(50, max(0, (int) SearchForm::postedText('custom_field_count')));
            while ($i < $customFieldCount) {
                if (SearchForm::postedText('custom_field_value_' . $i) !== '') {
                    $fieldId = (int) SearchForm::postedText('custom_field_' . $i);
                    $op = SearchForm::operator(Request::postedValue('custom_field_operator_' . $i) ?? '');
                    $val = SearchForm::postedText('custom_field_value_' . $i);
                    $part = \Adlexone\Data\ItemFields::matchSql($fieldId, $op, $val);
                    $condition .= $andOr . ' ' . $part->sql . ' ';
                    foreach ($part->params as $param) {
                        $params[] = $param;
                    }
                    $andOr = SearchForm::joiner(Request::postedValue('custom_field_andor_' . $i) ?? '');
                }
                $i++;
            }
            $columnArray = array('item_id', 'item_type_id', 'item_title');
            $itemColumns = Database::columns('items');
            foreach (Request::postedAll() as $key => $value) {
                if (substr((string) $key, 0, 4) == "disp" && $value !== '') {
                    $col = substr((string) $key, 5);
                    if ($col !== '' && in_array($col, $itemColumns, true) && !in_array($col, $columnArray, true)) {
                        $columnArray[] = $col;
                    }
                }
            }
            $condition = ($condition == 'WHERE') ? '' : $condition;  // If condition is equal to where it means we have set no field values
            $sql = Database::sqlSelect('items', $columnArray, $condition);
        }

        unset($_SESSION['item_search_sql'], $_SESSION['item_search_params']);

        $sortColumn = SearchForm::sortColumn(substr(SearchForm::postedText('sort_field'), 5));
        $newsort = '';
        if (Request::hasPostedKey('sortOrder') && $sortColumn !== '') {
            $direction = (Request::postedValue('sortOrder') == 'descending') ? 'DESC' : '';
            $newsort = RecordListQuery::order(trim($sortColumn . ' ' . $direction), '');
            if ($newsort !== '') {
                $sql .= ' ORDER BY ' . $newsort;
            }
        }
        // Show search results or move to save search page
        if (Request::hasPostedKey('search')) {
            self::showItems(Database::rows($sql, $params), '', $newsort, $_SESSION['access_user_id']);
        } elseif (Request::hasPostedKey('new_favourite')) {
            SearchLibrary::addSavedSearch(
                $_SESSION['access_user_id'],
                Request::postedValue('search_name'),
                Request::postedValue('search_description'),
                Request::postedValue('application'),
                Request::postedValue('security'),
                \Adlexone\Data\SavedSearch::criteriaFromPost(Request::postedAll())
            );
        } else {
    //        if ($buildImage != '') {
    //            $imagesFolder = THEME_PATH . $theme . '/images/';
    //            $imageHTML = '<img src="' . $imagesFolder . $buildImage . '"> ';
    //        }
            View::respond(TXT_354, TXT_375);
    //        if ($lineBreak == true) {
    //            $html .= '<br />';
    //        }

        }
    }

/**
     *
     * This routine will double check that the ID's are for knowledgebase items and if they are update
     * the database to say that they have been returned
     *
     * @param $knowledgeIds
     * @return void
     */
    public static function updateKnoweldgeCount($knowledgeIds)
    {

        foreach ($knowledgeIds as $values) {
            if (substr($values, -1) == '*') {
                $values = substr($values, 0, -1);
                $validId = false;
                if (defined('APP_COUNT_ITEM_TYPE') && Database::exists('items', 'item_id = ? AND item_type_id = ?', [(int) $values, (int) APP_COUNT_ITEM_TYPE])) {
                    $validId = true;
                }
            } else {
                $validId = True;
            }
            $values = (int) $values;
            $event_id = Request::hasQueryKey('event_id') && is_scalar(Request::queryValue('event_id')) ? (string) Request::queryValue('event_id') : '';
            if ($validId == True) {
                if (Database::exists('system_log', 'item_id = ? AND event_id = ?', [$values, $event_id])) {
                    Database::run('UPDATE system_log SET event_counter = event_counter + 1 WHERE item_id = ? AND event_id = ?', [$values, $event_id]);
                } else {
                    Database::insert('system_log', [
                        'item_id' => $values,
                        'create_date' => time(),
                        'event_counter' => 1,
                        'event_id' => $event_id,
                        'security_id' => $_SESSION['access_user_id'],
                    ]);
                }

            }
        }
    }
}
