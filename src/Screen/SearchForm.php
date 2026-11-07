<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Application\ApplicationSettings;
use Adlexone\Database\Database;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Text\Wording;
use Adlexone\Html\Html;

final class SearchForm
{
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
            SearchScreen::address('search', 'option=quick_search'),
            $fields,
            [Form::button('submit', 'search', TXT_3, $javascript)]
        ));
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
                SearchResults::searchResults($sql, ['%' . Database::escapeLike($value) . '%']);
                return;
            default:
                View::respond(TXT_548);
                return;
        }
        Request::setPosted('search', Request::postedValue('search') ?? 'quick_search');
        SearchResults::searchResults($sql);
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
        if (Access::canAdministerSite()) {
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
            SearchScreen::address('search', 'option=show_search_results' . $eventId),
            $fields,
            [
                Form::button('submit', 'search', TXT_3),
                Form::button('reset', 'reset', TXT_75),
                Form::button('submit', 'new_favourite', TXT_309, $javascript),
            ],
            ['name' => 'itemSearch', 'id' => 'itemSearch']
        ));
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
     * A comparison operator from a posted value, limited to the ones the search form offers.
     */
    public static function operator(mixed $posted, string $fallback = '='): string
    {
        $op = strtoupper(trim(html_entity_decode(is_scalar($posted) ? (string) $posted : '', ENT_COMPAT, 'UTF-8')));

        return in_array($op, ['=', '<>', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'], true) ? $op : $fallback;
    }

/**
     * AND or OR between two search conditions.
     */
    public static function joiner(mixed $posted): string
    {
        return strtoupper(trim(is_scalar($posted) ? (string) $posted : '')) === 'OR' ? 'OR' : 'AND';
    }

/**
     * A user id, or the signed-in user's id for the "me" choice in the search form.
     */
    public static function personId(string $posted): int
    {
        return $posted === 'session_user' ? (int) ($_SESSION['access_user_id'] ?? 0) : (int) $posted;
    }

    public static function dateColumn(mixed $posted): string
    {
        return in_array($posted, ['create_date', 'core_log_updated'], true) ? (string) $posted : 'create_date';
    }

    public static function postedText(string $key): string
    {
        return is_scalar(Request::postedValue($key) ?? null) ? (string) Request::postedValue($key) : '';
    }

/**
     * Seconds since the epoch for a posted date, or null when it is not a date.
     */
    public static function postedTime(string $key): ?int
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
    public static function sortColumn(string $posted): string
    {
        $posted = trim($posted);

        return preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $posted) === 1 ? $posted : '';
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
}
