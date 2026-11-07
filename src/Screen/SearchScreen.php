<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Database\RecordListQuery;
use Adlexone\Auth\Permission;
use Adlexone\Application\ApplicationAccess;
use Adlexone\Application\ApplicationSettings;
use Adlexone\support\Database;
use Adlexone\support\RssFeed;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Http\Router;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Text\Wording;
use Adlexone\Html\Html;

/**
 * Screen formerly loaded from legacy/.
 */
final class SearchScreen
{
    public static function open(): void
    {
        if (Page::current()->inApplication()) {
            ApplicationAccess::requireUse();
        } elseif (!Access::can(Permission::ADMIN_ITEMS)) {
            Access::deny();
        }
        if (!Page::current()->inApplication()) {
            Page::current()->setCrumbs(\Adlexone\Html\Breadcrumb::trail([['label' => 'Search']]));
        }














































        /**
         * Logic to render the appropriate template or call wrapper functions
         * Option is captured from the value selected via a hyperlink
         */
        $option = Request::query('option');
        switch ($option) {
            case 'show_item_search' :
            case 'show_search_results' :
                if (Page::current()->inApplication()) {
                    Access::permitUse();
                    ApplicationSearch::open();
                    break;
                }
                if ($option === 'show_search_results') {
                    Access::permitUse();
                    self::searchResults();
                    break;
                }
                Access::permitUse();
                $itemTypes = preg_replace('/[^0-9,]/', '', Request::query('item_types')) ?? '';
                $filter = (stristr($itemTypes, ',') !== false) ? explode(',', $itemTypes) : $itemTypes;
                self::showAdvancedItemSearch($filter);
                break;
            case 'show_saved_searches' :
                Access::permitUse();
                $application = Request::query('application');
                if ($application === '' && Page::current()->inApplication()) {
                    $application = Page::current()->applicationSlug();
                }
                self::showSavedSearches($_SESSION['access_user_id'], $application);
                break;
            case 'saved_search' :
                Access::permitUse();
                self::savedSearch(Request::queryValue('id'), $_SESSION['access_user_id'], Request::query('global'));
                break;
            case 'delete_saved_search' :
                Access::permitUse();
                self::deleteSavedSearch(Request::queryValue('id'), $_SESSION['access_user_id'], Request::query('scope'));
                break;
            case 'quick_search' :
                Access::permitUse();
                self::quickSearch();
                break;
            case 'show_quick_search' :
                Access::permitUse();
                self::showQuickSearch();
                break;
            case 'edit_saved_search' :
                Access::permitSystem();
                self::editSavedSearch(Request::queryValue('id'));
                break;
            case 'update_saved_search' :
                Access::permitSystem();
                self::updateSavedSearch(Request::queryValue('id'));
                break;
            default :
                Access::permitUse();
                self::showQuickSearch();
                break;
        }
    }
    /**
     * @return list<int>
     */
    public static function applicationSearchTypeIds(): array
    {
        if (!Page::current()->inApplication()) {
            return [];
        }
        return ApplicationSettings::allowedItemTypeIds(Page::current()->applicationSlug());
    }

    /**
     * Force a type filter for the current app when the caller did not supply one.
     *
     * @param mixed $filter
     * @return mixed
     */
    public static function constrainSearchTypeFilter($filter)
    {
        $allowed = self::applicationSearchTypeIds();
        if ($allowed === []) {
            if (is_array($filter)) {
                return array_values(array_map('intval', $filter));
            }

            return preg_replace('/[^0-9]/', '', (string) $filter) ?? '';
        }
        if (is_array($filter)) {
            $ids = array_values(array_filter(array_map('intval', $filter), static fn (int $id): bool => in_array($id, $allowed, true)));
            return $ids !== [] ? $ids : $allowed;
        }
        $single = trim((string) $filter);
        if ($single !== '' && $single !== '0' && ctype_digit($single) && in_array((int) $single, $allowed, true)) {
            return $single;
        }
        return count($allowed) === 1 ? (string) $allowed[0] : $allowed;
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

    /**
     * showQuickSearch()
     */
    public static function showQuickSearch()
    {
        $javascript = Form::fieldCheck(TXT_468, [''], ['search_value'], [''], [TXT_206], [true]);
        $inApplication = Page::current()->inApplication();
        $typeLabels = $inApplication ? ['Name', 'ID', 'Notes'] : [TXT_84, TXT_102, TXT_601];
        $fields = [
            TXT_682 => Form::select(
                'search_type',
                ['item_title', 'item_id', 'log_entries'],
                $typeLabels,
                'item_title'
            ),
            ($inApplication ? 'Name' : TXT_683) => Form::text('search_value', ''),
            '' => Form::hidden('search', 'Search'),
        ];
        Page::current()->setBody(Form::form(
            Wording::application('TXT_62', TXT_376),
            self::address('search', 'option=quick_search'),
            $fields,
            [Form::button('submit', 'search', TXT_3, $javascript)]
        ));
    }

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
            $again = self::address('search', Page::current()->inApplication() ? 'option=show_item_search' : 'option=show_quick_search');
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

        $base = self::address('records');
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

    public static function quickSearch()
    {
        // Takes criteria from quick search field and returns items
        Request::setPosted('disp_item_id', "disp_item_id");
        Request::setPosted('disp_item_title', "disp_item_title");
        Request::setPosted('disp_create_date', "disp_create_date");
        Request::setPosted('disp_item_type_id', "disp_item_type_id");
        $sql = "";
        $value = is_scalar(Request::postedValue('search_value') ?? null) ? (string) Request::postedValue('search_value') : '';
        switch ((string) (Request::postedValue('search_type') ?? '')) {
            case 'item_id':
                Request::setPosted('item_id', ctype_digit($value) ? $value : '0');
                Request::setPosted('id_operator', '=');
                break;
            case 'item_title':
                Request::setPosted('item_title', $value);
                Request::setPosted('item_title_operator', 'LIKE');
                break;
            case 'log_entries':
                $sql = 'SELECT items.item_id, item_title, items.create_date, item_type_id FROM '
                    . 'items JOIN core_log ON items.item_id = core_log.item_id WHERE log_text LIKE ? ESCAPE \'\\\'';
                self::searchResults($sql, ['%' . Database::escapeLike($value) . '%']);
                return;
            default:
                View::respond(TXT_548);
                return;
        }
        Request::setPosted('search', Request::postedValue('search') ?? 'quick_search');
        self::searchResults($sql);
    }

    /**
     * A comparison operator from a posted value, limited to the ones the search form offers.
     */
    private static function operator(mixed $posted, string $fallback = '='): string
    {
        $op = strtoupper(trim(html_entity_decode(is_scalar($posted) ? (string) $posted : '', ENT_COMPAT, 'UTF-8')));

        return in_array($op, ['=', '<>', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'], true) ? $op : $fallback;
    }

    /**
     * AND or OR between two search conditions.
     */
    private static function joiner(mixed $posted): string
    {
        return strtoupper(trim(is_scalar($posted) ? (string) $posted : '')) === 'OR' ? 'OR' : 'AND';
    }

    /**
     * A user id, or the signed-in user's id for the "me" choice in the search form.
     */
    private static function personId(string $posted): int
    {
        return $posted === 'session_user' ? (int) ($_SESSION['access_user_id'] ?? 0) : (int) $posted;
    }

    private static function dateColumn(mixed $posted): string
    {
        return in_array($posted, ['create_date', 'core_log_updated'], true) ? (string) $posted : 'create_date';
    }

    private static function postedText(string $key): string
    {
        return is_scalar(Request::postedValue($key) ?? null) ? (string) Request::postedValue($key) : '';
    }

    /**
     * Seconds since the epoch for a posted date, or null when it is not a date.
     */
    private static function postedTime(string $key): ?int
    {
        $text = trim(self::postedText($key));
        if ($text === '') {
            return null;
        }
        $parsed = date_parse($text);
        if (($parsed['error_count'] ?? 1) > 0 || !is_int($parsed['year'] ?? false) || !is_int($parsed['month'] ?? false) || !is_int($parsed['day'] ?? false)) {
            return null;
        }

        return mktime(
            (int) ($parsed['hour'] ?? 0),
            (int) ($parsed['minute'] ?? 0),
            (int) ($parsed['second'] ?? 0),
            $parsed['month'],
            $parsed['day'],
            $parsed['year']
        );
    }

    /**
     * An ORDER BY column from a posted value, or an empty string when it is not a plain column name.
     */
    private static function sortColumn(string $posted): string
    {
        $posted = trim($posted);

        return preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $posted) === 1 ? $posted : '';
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
                self::showAdvancedItemSearch(Request::postedValue('item_type_id'));
                return;
            }
        // Generate from form based values or pre defined sql
        $andOr = '';

        if ($sql == '') {
            $condition = 'WHERE';
            $allowedTypes = self::applicationSearchTypeIds();
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
                $itemIdText = self::postedText('item_id');
                if (self::operator(Request::postedValue('id_operator') ?? '=') === '=') {
                    $condition .= $andOr . " item_id = " . (ctype_digit($itemIdText) ? $itemIdText : '0') . " ";
                } else {
                    $condition .= $andOr . " item_id LIKE ? ESCAPE '\\' ";
                    $params[] = '%' . Database::escapeLike($itemIdText) . '%';
                }
                $andOr = self::joiner(Request::postedValue('id_andor') ?? '');
            }
            // Hour Range
            if (!Request::blankPosted('hour_range')) {
                $hours = (int) self::postedText('hour_range');
                $condition .= $andOr . " " . self::dateColumn(Request::postedValue('hour_type') ?? '') . " " . self::operator(Request::postedValue('hour_range_operator') ?? '', '<=') . " " . (time() - $hours * 60 * 60) . " ";
                $andOr = self::joiner(Request::postedValue('hour_range_andor') ?? '');
            }
            // Date Created 1 and 2
            foreach (['1', '2'] as $n) {
                $time = self::postedTime('date_' . $n);
                if ($time !== null) {
                    $condition .= $andOr . " " . self::dateColumn(Request::postedValue('date_type_' . $n) ?? '') . " " . self::operator(Request::postedValue('date_operator_' . $n) ?? '', '>=') . " " . $time . " ";
                    $andOr = self::joiner(Request::postedValue('date_andor_' . $n) ?? '');
                }
            }
            // Creator User
            if (!Request::blankPosted('creator_security')) {
                $condition .= $andOr . " creator_security " . self::operator(Request::postedValue('security_creators_operator') ?? '') . " " . self::personId(self::postedText('creator_security')) . " ";
                $andOr = self::joiner(Request::postedValue('security_creators_andor') ?? '');
            }
            // Security User
            if (!Request::blankPosted('user_security')) {
                $condition .= $andOr . " user_security " . self::operator(Request::postedValue('security_users_operator') ?? '') . " " . self::personId(self::postedText('user_security')) . " ";
                $andOr = self::joiner(Request::postedValue('security_users_andor') ?? '');
            }
            // Security Group
            if (!Request::blankPosted('security_groups')) {
                $groupId = (int) self::postedText('security_groups');
                if (self::operator(Request::postedValue('security_groups_operator') ?? '') === '=') {
                    $condition .= $andOr . " item_id IN (SELECT item_id FROM item_groups WHERE group_id = " . $groupId . ") ";
                } else {
                    $condition .= $andOr . " item_id NOT IN (SELECT item_id FROM item_groups WHERE group_id = " . $groupId . ") ";
                }
                $andOr = self::joiner(Request::postedValue('security_groups_andor') ?? '');
            }
            // Item Title
            if (!Request::blankPosted('item_title')) {
                $titleText = self::postedText('item_title');
                if (self::operator(Request::postedValue('item_title_operator') ?? 'LIKE', 'LIKE') === '=') {
                    $condition .= $andOr . ' item_title = ? ';
                    $params[] = $titleText;
                } else {
                    $condition .= $andOr . " item_title LIKE ? ESCAPE '\\' ";
                    $params[] = '%' . Database::escapeLike($titleText) . '%';
                }
                $andOr = self::joiner(Request::postedValue('title_andor') ?? '');
            }

            // Custom Fields
            $i = 0;
            $customFieldCount = min(50, max(0, (int) self::postedText('custom_field_count')));
            while ($i < $customFieldCount) {
                if (self::postedText('custom_field_value_' . $i) !== '') {
                    $fieldId = (int) self::postedText('custom_field_' . $i);
                    $op = self::operator(Request::postedValue('custom_field_operator_' . $i) ?? '');
                    $val = self::postedText('custom_field_value_' . $i);
                    $part = \Adlexone\Data\ItemFields::matchSql($fieldId, $op, $val);
                    $condition .= $andOr . ' ' . $part->sql . ' ';
                    foreach ($part->params as $param) {
                        $params[] = $param;
                    }
                    $andOr = self::joiner(Request::postedValue('custom_field_andor_' . $i) ?? '');
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

        $sortColumn = self::sortColumn(substr(self::postedText('sort_field'), 5));
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
            self::addSavedSearch(
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

    public static function showSavedSearches($userID, $application = '')
    {
        if (Access::allowsAdminister()) {
            $applicationClause = ($application != '') ? " WHERE application = ?" : "";
            $params = ($application != '') ? [$application] : [];
            $sql = "SELECT * FROM saved_searches $applicationClause ORDER BY search_name ASC";
        } else {
            $applicationClause = ($application != '') ? " AND application = ?" : "";
            $params = array_merge([(string) $userID], ($application != '') ? [$application] : []);
            $sql = "SELECT * FROM saved_searches WHERE (user = ? OR user = 'all') $applicationClause ORDER BY search_name ASC";
        }
        $result = Database::rows($sql, $params);
        $base = self::address('search');
        $rows = [];
        if (count($result) > 0) {
            foreach ($result as $row) {
                $href = $base . '&search=' . rawurlencode((string)$row['search_id']);
                $name = (string)$row['search_name'];
                if ($row['user'] == 'all') {
                    $href .= '&global=1';
                    $name .= ' (' . TXT_408 . ')';
                }
                $actions = [];
                $canDeleteGlobal = ($row['user'] == 'all' || $row['user'] == 'system') && Access::allowsAdminister();
                $canDeleteOwn = (string)$row['user'] === (string)$_SESSION['access_user_id'];
                if ($canDeleteGlobal || $canDeleteOwn) {
                    $deleteHref = $base . '&search=' . rawurlencode((string)$row['search_id']) . '&option=delete_saved_search';
                    if ($canDeleteGlobal) {
                        $deleteHref .= '&scope=global';
                    }
                    $actions[] = [
                        'href' => $deleteHref,
                        'label' => TXT_315,
                        'tone' => 'danger',
                        'confirm' => TXT_400,
                    ];
                }
                if (Access::allowsSystem()) {
                    $actions[] = [
                        'href' => $base . '&search=' . rawurlencode((string)$row['search_id']) . '&option=edit_saved_search',
                        'label' => TXT_626,
                    ];
                }
                $rows[] = [
                    'name' => $name,
                    'href' => $href,
                    'cells' => [
                        'description' => (string)($row['search_description'] ?? ''),
                    ],
                    'actions' => $actions,
                ];
            }
        }
        $toolbar = '';
        $emptyAction = '';
        if (Page::current()->inApplication()) {
            $searchButton = Blocks::link($base . '&option=show_item_search', 'Search', '', 'btn btn--primary');
            if ($rows === []) {
                $emptyAction = $searchButton;
            } else {
                $toolbar = '<div class="record-list__tools">'
                    . Blocks::link($base . '&option=show_item_search', 'Search', '', 'btn btn--primary btn--sm')
                    . '</div>';
            }
        }
        $list = Blocks::recordList([
            'column' => TXT_151,
            'columns' => [
                ['key' => 'description', 'label' => TXT_153, 'wrap' => true],
            ],
            'searchLabel' => TXT_3,
            'empty' => 'No saved searches',
            'emptyIllustration' => 'empty-saved',
            'emptyTitle' => 'No saved searches',
            'emptyCopy' => 'Run a search, then save it to this list.',
            'emptyAction' => $emptyAction,
            'toolbar' => $toolbar,
            'groups' => [['rows' => $rows]],
        ]);
        Page::current()->setBody(Blocks::vertical([
            [
                'title' => Wording::application('TXT_60', TXT_313),
                'html' => $list,
            ],
        ]));
    }

    public static function addSavedSearch($userID, $searchName, $searchDescription, $application, $security, array $criteria = [])
    {
        // Check for duplicate and respond with a return message if exists
        $searchName = trim((string) $searchName);
        $result = Database::rows('SELECT user FROM saved_searches WHERE search_name = ? AND user = ?', [$searchName, (string) $userID]);
        if (count($result) > 0) {
            View::respond(TXT_311, Form::historyBack(TXT_306));
            return;
        }
        if ($searchName === '') {
            View::respond(TXT_367, Form::historyBack(TXT_306));
            return;
        }
            if ($security == 'all') {
                $columnArray['user'] = 'all';
            } elseif ($security == 'mine') {
                $columnArray['user'] = $userID;
            } else {
                $columnArray['user'] = 'system';
            }
            $columnArray['search_name'] = $searchName;
            $columnArray['search_description'] = $searchDescription;
            $columnArray['application'] = $application;
            $columnArray['criteria_json'] = \Adlexone\Data\SavedSearch::encode(
                \Adlexone\Data\SavedSearch::withoutStoredSql($criteria)
            );
            Database::insert('saved_searches', $columnArray);
            // Success messagae
        View::respond(TXT_25, Form::historyBack(TXT_404));
    }

    public static function savedSearch($searchID, $userID, $global = false, $rss = false)
    {
        unset($_SESSION['item_search_sql'], $_SESSION['item_search_params']);

        // Show the records that match the saved filters.
        $searchID = (int) $searchID;
        if ($global == true) {
            $result = Database::rows("SELECT * FROM saved_searches WHERE search_id = ? AND user = 'all'", [$searchID]);
        } elseif (Access::allowsControl()) {
            $result = Database::rows('SELECT * FROM saved_searches WHERE search_id = ?', [$searchID]);
        } else {
            $result = Database::rows("SELECT * FROM saved_searches WHERE search_id = ? AND (user = 'system' OR user = 'all' OR user = ?)", [$searchID, (string) $userID]);
        }
        $row = $result[0] ?? null;
        if ($row === null) {
            View::respond(TXT_115, Blocks::link(self::address('search', 'option=show_saved_searches'), TXT_313));
            return;
        }

        if ($rss == false) {
            // Set title constant for language alias in showitems function
            define('SEARCH_NAME', $row['search_name']);

            $finder = json_decode((string) ($row['criteria_json'] ?? ''), true);
            if (is_array($finder) && Page::current()->inApplication()) {
                $target = ApplicationSearch::savedHref($finder);
                if ($target !== '') {
                    header('Location: ' . \Adlexone\Http\Router::absolute($target));
                    exit;
                }
            }

            $savedQuery = \Adlexone\Data\SavedSearch::resolve($row);
            $built = Database::rows($savedQuery->sql, $savedQuery->params);
            $itemIDs = [];
            if ($built !== []) {
                $itemIDs = array_values(array_filter(
                    array_column($built, 'item_id'),
                    static fn ($id): bool => is_scalar($id) && (string) $id !== ''
                ));
            }

            self::showItems($itemIDs, '', 'item_id DESC', $_SESSION['access_user_id']);
        } else {
            //Prep for output of XML RSS feed
            ob_clean();
            $search = $row;
            $savedQuery = \Adlexone\Data\SavedSearch::resolve($search);
            $itemArray = Database::rows($savedQuery->sql, $savedQuery->params);
            $linkArray = [];
            $titleArray = [];
            foreach ($itemArray as $id) {
                $result = Database::rows('SELECT item_id, item_title FROM items WHERE item_id = ?', [(int) ($id['item_id'] ?? $id)]);
                $item = $result[0] ?? null;
                $linkArray[] = Blocks::link(self::address('records', 'item=' . (int) ($item['item_id'] ?? 0) . '&option=show_item'), '', 'URL');
                $titleArray[] = (string) ($item['item_title'] ?? '');
            }
            RssFeed::send(SET_DATE_FORMAT, $search['search_name'], $search['search_description'], 'www.oneorzero.com', $titleArray, $linkArray);
        }
    }

    public static function deleteSavedSearch($searchID, $userID, $scope)
    {
        // Delete favourite search
        $searchID = (int) $searchID;
        if ($scope == 'global' and Access::allowsAdminister()) {
            Database::delete('saved_searches', 'search_id = ?', [$searchID]);
        } else {
            Database::delete('saved_searches', 'search_id = ? AND user = ?', [$searchID, (string) $userID]);
        }
        self::showSavedSearches($userID);
    }

    public static function showItemTypeMenu($filter = '')
    {
        $filter = self::constrainSearchTypeFilter($filter);
        $condition = '';
        if (is_array($filter)) {
            $i = 0;
            foreach ($filter as $id) {
                $id = (int) $id;
                // Remove special characters
                if ($i == 0) {
                    $condition = "WHERE item_type_id = '$id'";
                } else {
                    $condition .= " OR item_type_id = '$id'";
                }
                $i++;
            }
        } elseif ($filter != '' and $filter != '0') {
            $condition = 'WHERE item_type_id = ' . (int) $filter;
        } else {
            $condition = '';
        }
        $columnArray = array('item_type_id', 'item_type_name');
        $result = Database::select('item_types', $columnArray, $condition);
        if ($filter != '' and $filter != '0') {
            $i = 0;
            foreach ($result as $row) {
                $listValues[$i] = $row['item_type_id'];
                $listDisplayValues[$i] = $row['item_type_name'];
                $i++;
            }
            $listValues[$i] = '0';
            $listDisplayValues[$i] = TXT_100;
        } else {
            // Set the selected option to 'All item definitions'
            $i = 0;
            $listValues[0] = '';
            $listDisplayValues[0] = TXT_100;
            $i = 1;
            // Display all item types or only a single item type
            foreach ($result as $row) {
                $listValues[$i] = $row['item_type_id'];
                $listDisplayValues[$i] = $row['item_type_name'];
                $i++;
            }
        }
        // Create the list box
        return Form::select('item_type_id', $listValues, $listDisplayValues, '', 'onChange="document.itemSearch.submit();"');
    }

    public static function showSecurityGroups()
    {
        // Get security groups from database
        $columnArray = array('group_id', 'group_name');
        $condition = 'ORDER BY group_name';
        $result = Database::select('groups', $columnArray, $condition);
        // Set the selected option to 'All item definitions'
        $listValues[0] = '';
        $listDisplayValues[0] = TXT_104;
        $i = 1;
        foreach ($result as $row) {
            $listValues[$i] = $row['group_id'];
            $listDisplayValues[$i] = $row['group_name'];
            $i++;
        }
        // Create the list box
        return Form::select('security_groups', $listValues, $listDisplayValues, '');
    }

    public static function showCustomFieldsAsList($itemTypeID = '', $fieldData = '', $dispRows = '')
    {
        $condition = '';
        $fieldIds = [];
        if ($itemTypeID != '' and $itemTypeID != '0') {
            $columnArray = array('custom_field_id');
            if (is_array($itemTypeID)) {
                $condition = "WHERE ";
                foreach ($itemTypeID as $value) {
                    $condition .= "item_type_id = " . (int) $value . " OR ";
                }
                $condition = substr($condition, 0, -4);
            } else {
                $condition = "WHERE item_type_id = " . (int) $itemTypeID;
            }
            $result = Database::select('item_type_custom_fields', $columnArray, $condition);
            $fieldIds = [];
            foreach ($result as $row) {
                $id = (int) ($row['custom_field_id'] ?? 0);
                if ($id > 0) {
                    $fieldIds[] = $id;
                }
            }
        }
        $columnArray = array('custom_field_id', 'custom_field_name');
        $typeLimit = "field_type NOT LIKE ? AND field_type <> ?";
        $typeParams = ['worker%', 'fieldSeparator'];
        if (!empty($fieldIds)) {
            $marks = implode(', ', array_fill(0, count($fieldIds), '?'));
            $result = Database::select(
                'custom_fields',
                $columnArray,
                'custom_field_id IN (' . $marks . ') AND ' . $typeLimit,
                array_merge($fieldIds, $typeParams),
                'custom_field_name ASC'
            );
        } else {
            $result = Database::select('custom_fields', $columnArray, $typeLimit, $typeParams, 'custom_field_name ASC');
        }
        // Build list based on the defined amount of custom field lists to display
        $html = '';
        foreach ($result as $row) {
            $fieldId = (int) ($row['custom_field_id'] ?? 0);
            $fieldData[] = Html::e((string) ($row['custom_field_name'] ?? ''));
            $fieldData[] = Form::checkbox('disp_custom_field_' . $fieldId, 'true', '');
            $fieldData[] = Form::radio('sort_field', 'sort_custom_field_' . $fieldId, '');
            if (count($fieldData) == 9) {
                $dispRows .= '<div class="group-security-list">' . implode('', $fieldData) . '</div>';
                unset($fieldData);
            }
        }
        if (isset($fieldData) && is_array($fieldData)) {
            $dispRows .= '<div class="group-security-list">' . implode('', $fieldData) . '</div>';
        }

        return $dispRows;
    }

    /**
     * Renders the custom fields selection and input controls for advanced search.
     *
     * Uses the new content rendering methods for a modern, grid-based layout.
     *
     * @param string|array $itemTypeID Optional item type filter(s).
     * @return string HTML for the custom fields section.
     */
    public static function showCustomFields($itemTypeID = '')
    {
            $advanced = Database::rows("SELECT user_id FROM users WHERE user_id = '" . (int)$_SESSION['access_user_id'] . "' AND settings LIKE '%{SHOW-HIDE=TRUE}%'");
        if (count($advanced) === 0) {
            return '';
        }

        $ids = [];
        $idList = is_array($itemTypeID) ? $itemTypeID : [$itemTypeID];
        $idList = array_values(array_filter(array_map('intval', $idList)));
        if ($idList !== [] && !in_array(0, $idList, true)) {
            foreach (Database::select('item_type_custom_fields', ['custom_field_id'], 'WHERE item_type_id IN (' . implode(',', $idList) . ')') as $row) {
                $ids[] = (int)$row['custom_field_id'];
            }
        }

        $condition = "WHERE field_type NOT LIKE 'worker%' AND field_type <> 'fieldSeparator'";
        if ($ids !== []) {
            $condition .= ' AND custom_field_id IN (' . implode(',', array_unique($ids)) . ')';
        }
        $condition .= ' ORDER BY custom_field_name ASC';
        $result = Database::select('custom_fields', ['custom_field_id', 'custom_field_name'], $condition);
        $fieldRows = [];
        foreach ($result as $row) {
            $fieldRows[] = $row;
        }
        if ($fieldRows === []) {
            return '';
        }

        $html = '';
        $count = (int)SET_CUSTOM_FIELDS_IN_SEARCH;
        for ($a = 0; $a < $count; $a++) {
            $listValues = [''];
            $listDisplayValues = [TXT_108];
            foreach ($fieldRows as $row) {
                $listValues[] = $row['custom_field_id'];
                $listDisplayValues[] = $row['custom_field_name'];
            }
            $html .= self::searchControlRow(
                Form::select('custom_field_' . $a, $listValues, $listDisplayValues, ''),
                Form::select('custom_field_operator_' . $a, ['=', 'LIKE', '<>', '>', '<'], [TXT_418, TXT_112, TXT_318, TXT_364, TXT_365], '='),
                Form::text('custom_field_value_' . $a, ''),
                Form::select('custom_field_andor_' . $a, ['AND', 'OR'], [TXT_109, TXT_110], 'AND')
            );
        }
        $html .= Form::hidden('custom_field_count', (string)$count);

        return $html;
    }

    public static function searchControlRow(string ...$controls): string
    {
        return '<div class="criteria-row">' . implode('', $controls) . '</div>';
    }

    public static function searchApplicationMenu(string $selected): string
    {
        $names = [];
        $urls = [];
        foreach (\Adlexone\Application\ApplicationStore::searchScopes() as [$scope, $label]) {
            $names[] = $label;
            $urls[] = $scope;
        }

        return Form::select('application', $urls, $names, $selected);
    }

    /**
     * Displays the advanced item search form.
     *
     * This function generates an advanced search form for items, allowing users to filter
     * and sort items based on various criteria such as item types, system criteria, date ranges,
     * custom fields, and more. The form is divided into sections for better organization and
     * uses the `Form` builders for form elements.
     *
     * @param string|array $filter Optional filter criteria for item types.
     * @return void
     */
    public static function showAdvancedItemSearch($filter = '')
    {
        $filter = self::constrainSearchTypeFilter($filter);
        // Clear previous search SQL if not showing search results
        unset($_SESSION['item_search_sql'], $_SESSION['item_search_params']);

        // Define operator and logical values for dropdowns
        $operatorValues = array('=', 'LIKE', '<>');
        $operatorDisplayValues = array(TXT_81, TXT_112, TXT_318);
        $andOrValues = array('AND', 'OR');
        $andOrDisplayValues = array(TXT_109, TXT_110);
        $eventId = (Request::hasQueryKey('event_id') && is_scalar(Request::queryValue('event_id'))) ? '&event_id=' . rawurlencode((string) Request::queryValue('event_id')) : '';

        $fields = [];
        $allowedTypes = self::applicationSearchTypeIds();
        $inApplication = Page::current()->inApplication();
        if (!$inApplication || count($allowedTypes) > 1) {
            $fields[$inApplication ? 'Kind' : TXT_99] = self::showItemTypeMenu($filter);
        }
        $fields[Wording::get('LA_102', 'TXT_102')] = self::searchControlRow(
            Form::select('id_operator', $operatorValues, $operatorDisplayValues, ''),
            Form::text('item_id', ''),
            Form::select('id_andor', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[$inApplication ? 'Name' : Wording::get('LA_84', 'TXT_84')] = self::searchControlRow(
            Form::select('item_title_operator', $operatorValues, $operatorDisplayValues, ''),
            Form::text('item_title', ''),
            Form::select('title_andor', $andOrValues, $andOrDisplayValues, '')
        );

        // Populate users dropdown
        $sql = "select user_id, user_name FROM users ORDER BY user_name ASC";
            $result = Database::rows($sql);
        $listValues = ['' => TXT_105, 'session_user' => TXT_600];
        foreach ($result as $row) {
            $listValues[$row['user_id']] = $row['user_name'];
        }

        // Add user-related fields to system criteria
        $fields[Wording::get('LA_551', 'TXT_551')] = self::searchControlRow(
            Form::select('security_creators_operator', $operatorValues, $operatorDisplayValues, ''),
            Form::select('creator_security', array_keys($listValues), array_values($listValues), ''),
            Form::select('security_creators_andor', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[Wording::get('LA_269', 'TXT_269')] = self::searchControlRow(
            Form::select('security_users_operator', $operatorValues, $operatorDisplayValues, ''),
            Form::select('user_security', array_keys($listValues), array_values($listValues), ''),
            Form::select('security_users_andor', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[TXT_79] = self::searchControlRow(
            Form::select('security_groups_operator', array('=', '<>'), array(TXT_81, TXT_318), ''),
            self::showSecurityGroups(),
            Form::select('security_groups_andor', $andOrValues, $andOrDisplayValues, '')
        );

        $fields[TXT_406] = self::searchControlRow(
            Form::select('hour_type', array('create_date', 'core_log_updated'), array(TXT_103, TXT_420), ''),
            Form::select('hour_range_operator', array('<=', '=', '>='), array(TXT_405, TXT_81, TXT_111), '>='),
            Form::text('hour_range', '', '30'),
            Form::select('hour_range_andor', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[TXT_103 . ' 1'] = self::searchControlRow(
            Form::select('date_type_1', array('create_date', 'core_log_updated'), array(TXT_103, TXT_420), ''),
            Form::select('date_operator_1', array('<=', '=', '>='), array(TXT_405, TXT_81, TXT_111), '>='),
            Form::text('date_1', ''),
            Form::select('date_andor_1', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[TXT_103 . ' 2'] = self::searchControlRow(
            Form::select('date_type_2', array('create_date', 'core_log_updated'), array(TXT_103, TXT_420), ''),
            Form::select('date_operator_2', array('<=', '=', '>='), array(TXT_405, TXT_81, TXT_111), '<='),
            Form::text('date_2', ''),
            Form::select('date_andor_2', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[TXT_407] = '<p class="field-note">dd-mm-yyyy hh:mm:ss</p>';

        $postedItemTypeID = (Request::hasPostedKey('item_type_id')) ? Request::postedValue('item_type_id') : $filter;
        $customFields = self::showCustomFields($postedItemTypeID);
        if ($customFields !== '') {
            $fields[TXT_106] = $customFields;
        }

        $fields[TXT_258] = '<div class="choice-list__sort">'
            . '<label class="checkbox">' . Form::radio('sortOrder', 'ascending', 'ascending') . ' ' . Html::e(TXT_533) . '</label>'
            . '<label class="checkbox">' . Form::radio('sortOrder', 'descending', '') . ' ' . Html::e(TXT_534) . '</label>'
            . '</div>';

        $displayChoices = [
            [Wording::get('LA_102', 'TXT_102'), 'spec_item_id', 'disp_item_id', 'sort_item_id', true],
            [Wording::get('LA_84', 'TXT_84'), 'spec_item_title', 'disp_item_title', 'sort_item_title', false],
            [TXT_225, 'disp_create_date', '', 'sort_create_date', false],
        ];
        $choiceHtml = '<div class="choice-list">';
        foreach ($displayChoices as [$label, $checkName, $hiddenName, $sortValue, $sortChecked]) {
            $choiceHtml .= '<div class="choice-list__row">'
                . Form::checkbox($checkName, 'true', 'true', (string)$label)
                . ($hiddenName !== '' ? Form::hidden($hiddenName, $hiddenName) : '')
                . '<span class="choice-list__sort">' . Form::radio('sort_field', $sortValue, $sortChecked ? $sortValue : '', 'aria-label="' . Html::e(TXT_258) . '"')
                . Html::e(TXT_258) . '</span>'
                . '</div>';
        }
        $choiceHtml .= '</div>';
        $fields[TXT_527] = $choiceHtml;

        $fields[Wording::get('LA_310', 'TXT_310')] = Form::text('search_name', '');
        $fields[Wording::get('LA_312', 'TXT_312')] = Form::textarea('search_description', '', SET_FORM_FIELD_HEIGHT);
        if (Access::allowsAdminister()) {
            $fields[TXT_403] = Form::select('security', array('mine', 'all', 'system'), array(TXT_402, TXT_401, TXT_482), '1');
        } else {
            $fields[''] = Form::hidden('security', 'mine');
        }

        $searchScope = Request::query('application');
        if ($searchScope === '' && Page::current()->inApplication()) {
            $searchScope = Page::current()->applicationSlug();
        }
        $fields[TXT_350] = self::searchApplicationMenu($searchScope);

        $javascript = Form::fieldCheck(TXT_468, [''], ['search_name'], [''], [TXT_367], [true]);

        Page::current()->setBody(Form::form(
            Wording::application('TXT_61', (string)Wording::get('LA_96', 'TXT_96')),
            self::address('search', 'option=show_search_results' . $eventId),
            $fields,
            [
                Form::button('submit', 'search', TXT_3),
                Form::button('reset', 'reset', TXT_75),
                Form::button('submit', 'new_favourite', TXT_309, $javascript),
            ],
            ['name' => 'itemSearch', 'id' => 'itemSearch']
        ));
    }

    public static function editSavedSearch($id)
    {
        $id = (int) $id;
        $columnArray = ['search_name'];
        $row = Database::first('saved_searches', $columnArray, 'search_id = ?', [$id]);

        if (!$row) {
            // No record found — show a friendly message
           View::respond(TXT_628,TXT_115);
            return;
        }

        $action = self::address('search', 'search=' . $id . '&option=update_saved_search');
        $fields = [
            TXT_627 => Form::text('search_name', $row['search_name']),
        ];
        Page::current()->setBody(Form::form(
            TXT_628 . ' - ' . $row['search_name'],
            $action,
            $fields,
            [
                Form::button('submit', 'submit_button', TXT_74),
                Form::button('reset', 'reset', TXT_75),
            ]
        ));
    }

    public static function updateSavedSearch($id)
    {
        $id = (int) $id;
        if (!Database::exists('saved_searches', 'search_id = ?', [$id])) {
            View::respond(TXT_115);
            return;
        }
        $name = trim(html_entity_decode(self::postedText('search_name'), ENT_COMPAT, 'UTF-8'));
        if ($name === '') {
            View::respond(TXT_367);
            return;
        }
        Database::update('saved_searches', ['search_name' => $name], 'search_id = ?', [$id]);
        // Success messagae
        View::respond(TXT_628,TXT_629);
    }

    /**
     * The next address on this screen. Inside an application that is the application.
     * Outside, records and search are separate places.
     */
    private static function address(string $manage, string $query = ''): string
    {
        $page = Page::current();
        if ($page->inApplication()) {
            return Router::applicationUrl($page->applicationSlug(), $page->navId(), $query);
        }

        return Router::manageUrl($manage, $query);
    }
}
