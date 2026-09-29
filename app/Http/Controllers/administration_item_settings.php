<?php
/**
 * Adlexone FlowIQ License Agreement 1.0
 *
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where
 *  Adlexone FlowIQ files contain the Adlexone FlowIQ license in the header of the file, the
 *  Adlexone FlowIQ License header must remain.
 * 3. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the data created or managed by your Adlexone FlowIQ installation.
 * 4. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for your Adlexone FlowIQ configuration or the hosting environment
 *  your Adlexone FlowIQ installation operates in.
 * 5. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the Adlexone FlowIQ where the software has been modified by
 *  third parties (i.e. other than Adlexone), unless an agreement has been
 *  reached with Adlexone.
 * 6. By using the Adlexone FlowIQ, you are indicating your acceptance of the
 *  stated Adlexone FlowIQ License terms and conditions.
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */

use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\MenuOptions;
use Adlexone\support\RenderViews;
use Adlexone\support\RenderNavigation;

MenuOptions::prepare();

/**
 * Items and Fields links sit in the top navigation card. There is no left sidebar.
 */
RenderNavigation::applySectionNav('Items and Fields', RenderNavigation::itemSettingsURLs());
/**
 * Controller specific constants
 */
define('ITEM_BASE_URL', 'index.php?controller=' . $_GET['controller'] . '&subcontroller=administration_item_settings');
/**
 * Shows secured item settings options
 */
//function showItemSettingsOptions ()
//{
//	// Security Options
//	$securityOption = RenderViews::buildURL(ITEM_BASE_URL . '&option=new_custom_field', TXT_88, 'URL') . '<br>';
//	$tableRows = RenderViews::outputIfRoleAllowed(RenderViews::tableData('', '', '', '', 'tdc1', array($securityOption), 'row'), $_SESSION['access_role_id'], 2);
//	$securityOption = RenderViews::buildURL(ITEM_BASE_URL . '&option=new_item_type', TXT_85, 'URL') . '<br>';
//	$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('', '', '', '', 'tdc1', array($securityOption), 'row'), $_SESSION['access_role_id'], 2);
//	$securityOption = RenderViews::buildURL(ITEM_BASE_URL . '&option=new_multilevel_menu_relationship', TXT_658, 'URL') . '<br>';
//	$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('', '', '', '', 'tdc1', array($securityOption), 'row'), $_SESSION['access_role_id'], 2);
//	$securityOption = RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_fields_types', TXT_52, 'URL') . '<br>';
//	$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('', '', '', '', 'tdc1', array($securityOption), 'row'), $_SESSION['access_role_id'], 2);
//	$html = RenderViews::table('100%', '0', '5', '0', 'tcNavigationBorder', $tableRows);
//	// Show page
//	define('HEADING', TXT_49);
//	define('BODY_CONTENT', $html);
//	RenderViews::renderThemePage('main_page_content', SET_THEME);
//}
/**
 * Shows add and edit custom field pages.  Controller logic: new_custom_field, edit_custom_field
 *
 * @param string $customFieldID Custom Fields ID
 * @param array $values Field values passes in via $_SESSION array for retaining form field values if error occurred during entry
 */
function showCustomField($customFieldID = '', $values = '')
{
    if ($customFieldID == '') {
        $formAction = 'index.php?controller=administration_item_settings&option=add_custom_field';
        $fieldValues = $values;
        $title = TXT_62;
    } else {
        $columnArray = array('*');
        $condition = "WHERE custom_field_id = '$customFieldID'";
        $fieldValues = Database::first('custom_fields', $columnArray, $condition);
        $formAction = 'index.php?controller=administration_item_settings&option=update_custom_field';
        $title = TXT_285;
    }

    $fields[TXT_86] = RenderViews::buildTextInput('custom_field_name', @$fieldValues['custom_field_name']);
    $fields[TXT_210] = RenderViews::buildTextInput('default_value', @$fieldValues['default_value']);
    $fieldTypes = array(FieldTypes::TEXT_BOX, 'password', FieldTypes::TEXT_AREA, FieldTypes::MENU, 'hidden', 'URL', 'dynamicURL', 'workerField', 'workerFieldMenu');
    $fieldNames = array(TXT_211, TXT_214, TXT_212, TXT_213, TXT_216, TXT_478, TXT_479, TXT_490, TXT_501);
    $fields[TXT_65] = RenderViews::buildSelectDropdown('field_type', $fieldTypes, $fieldNames, FieldTypes::normalise(@$fieldValues['field_type']));
    $fields[TXT_477] = RenderViews::buildTextInput('data', @$fieldValues['data']);
    // Javascript field validation setup
    $fields[TXT_505] = RenderViews::buildSelectDropdown('required', array('Yes', 'No'), array(TXT_93, TXT_94), @$fieldValues['required']);
    $validationTypes = array(NULL, 'numeric', 'string', 'alphanumeric', 'date', 'time', 'datetime', 'ip', 'email');
    $validationNames = array(TXT_525, TXT_507, TXT_508, TXT_509, TXT_510, TXT_511, TXT_512, TXT_513, TXT_514);
    $fields[TXT_506] = RenderViews::buildSelectDropdown('validation_type', $validationTypes, $validationNames, @$fieldValues['validation_type']);
    $fields[TXT_90] = RenderViews::buildSelectDropdown('enabled', array('Yes', 'No'), array(TXT_93, TXT_94), @$fieldValues['enabled']);
    $fields[''] = RenderViews::buildHiddenInput('custom_field_id', $customFieldID);
    $jsFieldNameArray = "['custom_field_name']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . TXT_217 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";

    define('BODY_CONTENT', RenderViews::buildForm(
        $title,
        $formAction,
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_74, $javascript),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Modify buildSelectDropdown and sub buildSelectDropdown values
 *
 * @param string $customFieldID Custom Field ID
 */
function modifyMenuValues($customFieldID)
{
    $fieldId = (int)$customFieldID;
    $fields = [];
    $fields[TXT_288] = '<div style="flex:1 1 100%"><p>' . htmlspecialchars(TXT_695, ENT_QUOTES, 'UTF-8') . '</p>'
        . MenuOptions::editorRows($fieldId) . '</div>';
    $fields[TXT_292] = RenderViews::buildTextInput('menu_value', '');
    $fields[TXT_693] = MenuOptions::parentSelect('parent_menu_value_id', $fieldId, 0);
    $fields[''] = RenderViews::buildHiddenInput('custom_field_id', $customFieldID);

    $buttons = [
        RenderViews::buildFormButton('submit', 'add_new', TXT_545),
        RenderViews::buildFormButton('submit', 'update_existing', TXT_542),
        RenderViews::buildFormButton('reset', 'reset', TXT_75),
    ];

    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_287,
        'index.php?controller=administration_item_settings&option=add_update_menu_value',
        $fields,
        $buttons
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function addUpdateMenuValue($customFieldID)
{
    $fieldId = (int)$customFieldID;
    if (isset($_POST['add_new']) and $_POST['add_new'] != '') {
        $value = trim((string)($_POST['menu_value'] ?? ''));
        $parentId = (int)($_POST['parent_menu_value_id'] ?? 0);
        if ($value !== '' && MenuOptions::valueExists($fieldId, $value, $parentId)) {
            $html = TXT_294;
            $html .= '<br><br><a href ="' . ITEM_BASE_URL . '&option=modify_menu_values&custom_field_id=' . $customFieldID . '" class="URL">' . TXT_31 . '</a>  ';
            define('BODY_CONTENT', $html);
            define('HEADING', TXT_139);
            RenderViews::renderThemePage('main_page_content', SET_THEME);
            return;
        }
        if ($value !== '') {
            MenuOptions::add($fieldId, $value, $parentId);
        }
        modifyMenuValues($customFieldID);
        return;
    }
    if (isset($_POST['update_existing']) and $_POST['update_existing'] != '') {
        MenuOptions::applyEdits($fieldId, $_POST);
        modifyMenuValues($customFieldID);
    }
}

/**
 * Delete a menu value and any values nested under it.
 *
 * @param string $menuValueID Menu Value ID
 * @param string $customFieldID Custom Field ID
 */
function deleteMenuValue($menuValueID, $customFieldID)
{
    MenuOptions::delete((int)$customFieldID, (int)$menuValueID);
    modifyMenuValues($customFieldID);
}

/**
 * showItemType()
 *
 * Shows add and edit item type pages.  Controller logic: new_item_type, edit_item_type
 *
 * @param string $itemTypeID Item type ID
 * @param array $values Field values passes in via $_SESSION array for retaining form field values if error occurred during entry
 * @return
 */
function showItemType($itemTypeID, $values = [])
{
    $isNew = ($itemTypeID == '');
    $action = $isNew ? 'index.php?controller=administration_item_settings&option=add_item_type' : 'index.php?controller=administration_item_settings&option=update_item_type';
    // Load field values
    if ($isNew) {
        $fieldValues = $values;
    } else {
        $columnArray = ['item_type_id', 'item_type_name', 'user_security', 'group_security', 'enabled'];
        $condition = "WHERE item_type_id = '$itemTypeID'";
        $fieldValues = Database::first('item_types', $columnArray, $condition);
    }

    $fields = [];
    $fields[TXT_87] = RenderViews::buildTextInput('item_type_name', $fieldValues['item_type_name'] ?? '');

    // User assignment select
    $columnArray = ['user_id', 'user_name'];
    $result = Database::select('users', $columnArray);
    $userIDArray = [''];
    $userArray = [TXT_267];
    foreach ($result as $row) {
        $userIDArray[] = $row['user_id'];
        $userArray[] = $row['user_name'];
    }
    $fields[TXT_269] = RenderViews::buildSelectDropdown('user_security', $userIDArray, $userArray, $fieldValues['user_security'] ?? '');

    $columnArray = ['group_id', 'group_name'];
    $condition = "ORDER BY group_name ASC";
    $result = Database::select('groups', $columnArray, $condition);

    $groupMembershipArray = explode('}-{', (string)($fieldValues['group_security'] ?? ''));
    $groupMembershipHtml = '<div class="group-security-list">';
    foreach ($result as $row) {
        $isChecked = in_array((string)$row['group_id'], $groupMembershipArray, true);
        $checkedValue = $isChecked ? (string)$row['group_id'] : '';
        $groupMembershipHtml .= RenderViews::buildCheckBox(
            'group_' . $row['group_id'],
            (string)$row['group_id'],
            $checkedValue,
            'checkbox',
            (string)$row['group_name']
        );
    }
    $groupMembershipHtml .= '</div>';

    $fields[TXT_90] = RenderViews::buildSelectDropdown('enabled', ['Yes', 'No'], [TXT_93, TXT_94], $fieldValues['enabled'] ?? '');
    $fields[TXT_71] = $groupMembershipHtml;

    // Custom fields selection (preserve ordering for existing item types)
    if ($isNew) {
        $customFieldArray = [];
        foreach ($values as $key => $value) {
            if (is_numeric($key)) {
                $customFieldArray[$key] = $value;
            }
        }
        if (empty($customFieldArray)) {
            $customFieldArray['dummy'] = '';
        }
        $customFieldSortArray = [];
    } else {
        $columnArray = ['custom_field_id', 'custom_field_order'];
        $condition = "WHERE item_type_id = '" . $itemTypeID . "'";
        $result = Database::select('item_type_custom_fields', $columnArray, $condition);
        $customFieldArray = [];
        $customFieldSortArray = [];
        if (count($result) != 0) {
            foreach ($result as $row) {
                $customFieldArray[$row['custom_field_id']] = $row['custom_field_id'];
                $customFieldSortArray['field_order_' . $row['custom_field_id']] = $row['custom_field_order'];
            }
        }
    }

    $fieldTypeLabels = [
        FieldTypes::TEXT_BOX => TXT_211,
        'password' => TXT_214,
        FieldTypes::TEXT_AREA => TXT_212,
        FieldTypes::MENU => TXT_213,
        'hidden' => TXT_216,
        'URL' => TXT_478,
        'dynamicURL' => TXT_479,
        'workerField' => TXT_490,
        'workerFieldMenu' => TXT_501,
    ];

    $columnArray = ['*'];
    $condition = "WHERE enabled = 'Yes' ORDER BY custom_field_name";
    $result = Database::select('custom_fields', $columnArray, $condition);

    $availableItems = '';
    $selectedItems = [];
    foreach ($result as $row) {
        $fieldId = (string)$row['custom_field_id'];
        $isSelected = isset($customFieldArray[$fieldId]) || in_array($fieldId, $customFieldArray, true);
        $order = (int)($customFieldSortArray['field_order_' . $fieldId] ?? 0);
        $typeKey = FieldTypes::normalise((string)$row['field_type']);
        $typeLabel = $fieldTypeLabels[$typeKey] ?? $typeKey;
        $itemHtml = '<li class="field-picker-item">'
            . '<label class="checkbox"><input type="checkbox" name="custom_field_id_' . $fieldId . '" value="' . $fieldId . '"' . ($isSelected ? ' checked' : '') . '> '
            . '<span>' . htmlspecialchars((string)$row['custom_field_name'], ENT_QUOTES, 'UTF-8') . '</span> '
            . '<span class="field-picker-type">' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</span></label>'
            . '<span class="field-picker-moves">'
            . '<button type="button" class="btn field-picker-move" data-move="up">' . htmlspecialchars(TXT_686, ENT_QUOTES, 'UTF-8') . '</button>'
            . '<button type="button" class="btn field-picker-move" data-move="down">' . htmlspecialchars(TXT_687, ENT_QUOTES, 'UTF-8') . '</button>'
            . '</span>'
            . '<input type="hidden" name="custom_field_sort_' . $fieldId . '" value="' . ($isSelected ? $order : 0) . '">'
            . '</li>';
        if ($isSelected) {
            $selectedItems[] = ['order' => $order > 0 ? $order : PHP_INT_MAX, 'name' => (string)$row['custom_field_name'], 'html' => $itemHtml];
        } else {
            $availableItems .= $itemHtml;
        }
    }
    usort($selectedItems, static function (array $a, array $b): int {
        return $a['order'] <=> $b['order'] ?: strcasecmp($a['name'], $b['name']);
    });
    $selectedHtml = implode('', array_column($selectedItems, 'html'));

    $pickerHtml = '<div class="field-picker">'
        . '<div><div class="label">' . htmlspecialchars(TXT_684, ENT_QUOTES, 'UTF-8') . '</div><ul id="availableFields" class="field-picker-list">' . $availableItems . '</ul></div>'
        . '<div><div class="label">' . htmlspecialchars(TXT_685, ENT_QUOTES, 'UTF-8') . '</div><ul id="selectedFields" class="field-picker-list">' . $selectedHtml . '</ul></div>'
        . '</div>'
        . '<script>
(function () {
  var available = document.getElementById("availableFields");
  var selected = document.getElementById("selectedFields");
  if (!available || !selected) return;
  function renumber() {
    selected.querySelectorAll(".field-picker-item").forEach(function (li, i) {
      var order = li.querySelector("input[type=hidden]");
      if (order) order.value = String(i + 1);
    });
    available.querySelectorAll("input[type=hidden]").forEach(function (order) { order.value = "0"; });
  }
  function place(event) {
    var box = event.target;
    if (!box || box.type !== "checkbox") return;
    var li = box.closest(".field-picker-item");
    (box.checked ? selected : available).appendChild(li);
    renumber();
  }
  available.addEventListener("change", place);
  selected.addEventListener("change", place);
  selected.addEventListener("click", function (event) {
    var button = event.target.closest("[data-move]");
    if (!button) return;
    var li = button.closest(".field-picker-item");
    if (button.getAttribute("data-move") === "up" && li.previousElementSibling) {
      selected.insertBefore(li, li.previousElementSibling);
    } else if (button.getAttribute("data-move") === "down" && li.nextElementSibling) {
      selected.insertBefore(li.nextElementSibling, li);
    }
    renumber();
  });
  renumber();
})();
</script>';

    $fields[TXT_222] = $pickerHtml;
    $fields[''] = RenderViews::buildHiddenInput('item_type_id', $itemTypeID);

    $jsFieldNameArray = "['item_type_name']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . TXT_223 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";

    define('BODY_CONTENT', RenderViews::buildForm(
        $isNew ? TXT_85 : TXT_286,
        $action,
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_74, $javascript),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * addCustomField()
 *
 * Adds custom field.  Controller logic: add_custom_field
 */
function addCustomField()
{
    // Remove unwanted POST variables
    unset ($_POST['submit_button'], $_POST['reset'], $_POST['custom_field_id']);
    // Check for duplicate and error handling
    $columnArray = array('custom_field_name');
    $condition = "WHERE custom_field_name = '" . $_POST['custom_field_name'] . "'";
    $result = Database::select('custom_fields', $columnArray, $condition);
    if (count($result) == 0) {
        // Set new id
        $id = Database::newID('custom_fields', 'custom_field_id');
        $array['custom_field_id'] = $id;
        // Setup a new field reference
        $array['field_reference'] = Database::newID('custom_fields', 'field_reference');
        // Build insert array
        $columnArray = array_merge($array, $_POST);
        // Handle data column special characters
        $columnArray['data'] = html_entity_decode($columnArray['data'], ENT_COMPAT, 'UTF-8');
        // Insert form field values into row
        Database::insert('custom_fields', $columnArray);
        if ($_POST['field_type'] != 'workerField' and $_POST['field_type'] != 'workerFieldMenu' and $_POST['field_type'] != 'multiLevelMenu') {
            // Create item table column
            $sql = "ALTER TABLE " . "items ADD custom_field_" . $id . " text";
            Database::run($sql);
        }
        RenderViews::buildResponse($_POST['custom_field_name'] . ' ' . TXT_162, RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_fields', TXT_362));
        return;
    }
    RenderViews::buildResponse($_POST['custom_field_name'] . ' ' . TXT_163, RenderViews::buildURL(ITEM_BASE_URL . '&option=new_custom_field', TXT_362));
}

/**
 * addItemType()
 *
 * Adds item type.  Controller logic: add_item_type
 */
function addItemType()
{
    // Remove unwanted POST variables
    unset ($_POST['submit_button'], $_POST['reset'], $_POST['item_type_id']);
    // Check for duplicate and error handling
    $columnArray = array('item_type_name');
    $condition = "WHERE item_type_name = '" . $_POST['item_type_name'] . "'";
    $result = Database::select('item_types', $columnArray, $condition);
    if (count($result) == 0) {
        // Set new id
        $array['item_type_id'] = Database::newID('item_types', 'item_type_id');
        // Setup the item type custom fields db update
        $selectedFields = selectedCustomFieldsFromPost();
        foreach ($selectedFields as $fieldId => $order) {
            $customFieldArray = [
                'item_type_id' => $array['item_type_id'],
                'custom_field_id' => $fieldId,
                'custom_field_order' => $order,
            ];
            Database::insert('item_type_custom_fields', $customFieldArray);
        }
        $array['group_security'] = groupSecurityFromPost();
        stripItemTypeFormFields();
        // Build insert array
        $columnArray = array_merge($array, $_POST);
        // Insert form field values into row
        Database::insert('item_types', $columnArray);
        RenderViews::buildResponse($_POST['item_type_name'] . ' ' . TXT_162, RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_item_types', TXT_362));
        return;
    }
    RenderViews::buildResponse($_POST['item_type_name'] . ' ' . TXT_163, RenderViews::buildURL(ITEM_BASE_URL . '&option=new_item_type', TXT_363));
}

/**
 * showSearchOptions
 *
 * Render a small search form for custom fields or item types using the
 * RenderViews::buildForm pattern. The page heading constant `HEADING` is
 * deprecated — the form title is passed directly to `buildForm`.
 *
 * Note: `buildFormSectionHeading` is intentionally not used in this pattern.
 *
 * @return void
 */
function showSearchOptions(): void
{
    $formFields = [];
    $formFields[TXT_682] = RenderViews::buildSelectDropdown(
        'type',
        ['custom_field_name', 'item_type_name'],
        [TXT_86, TXT_87],
        ''
    );
    $formFields[TXT_678] = RenderViews::buildSelectDropdown(
        'operator',
        ['LIKE', '='],
        [TXT_80, TXT_81],
        ''
    );
    $formFields[TXT_82] = RenderViews::buildTextInput('criteria', '');

    // Create form buttons
    $buttons = [];
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_74);
    $buttons[] = RenderViews::buildFormButton('reset', 'reset', TXT_75);

    // Render the form using the modern helper.
    // First parameter is the form title (replaces the deprecated HEADING constant).
    $html = RenderViews::buildForm(
        TXT_52,
        'index.php?controller=administration_item_settings&option=field_type_search',
        $formFields,
        $buttons
    );

    // Set the body content and include the main page layout
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * showFieldTypeResults()
 *
 * Returns search results from a custom field or item type search.  Controller logic: field_type_search
 */
/**
 * Custom fields, with edit and delete.
 */
function showFields(): void
{
    $fieldRows = [];
    foreach (Database::select('custom_fields', '*', 'ORDER BY custom_field_name ASC') as $row) {
        $fieldRows[] = customFieldRecord($row);
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        ['title' => TXT_53, 'html' => fieldRecordList($fieldRows)],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Item types, with edit and delete.
 */
function showItemTypes(): void
{
    $typeRows = [];
    foreach (Database::select('item_types', '*', 'ORDER BY item_type_name ASC') as $row) {
        $typeRows[] = itemTypeRecord($row);
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        ['title' => TXT_50, 'html' => typeRecordList($typeRows)],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function fieldRecordList(array $rows): string
{
    return RenderViews::buildRecordList([
        'column' => TXT_151,
        'searchLabel' => TXT_3,
        'empty' => TXT_115,
        'noMatch' => TXT_689,
        'groups' => [['rows' => $rows]],
    ]);
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function typeRecordList(array $rows): string
{
    return RenderViews::buildRecordList([
        'column' => TXT_151,
        'searchLabel' => TXT_3,
        'primary' => ['href' => 'index.php?controller=administration_item_settings&option=new_item_type', 'label' => TXT_692],
        'empty' => TXT_115,
        'noMatch' => TXT_689,
        'groups' => [['rows' => $rows]],
    ]);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function customFieldRecord(array $row): array
{
    $row['field_type'] = FieldTypes::normalise((string)($row['field_type'] ?? ''));
    $name = (string)$row['custom_field_name'];
    $id = rawurlencode((string)$row['custom_field_id']);
    $key = ($row['field_type'] == 'workerField' || $row['field_type'] == 'workerFieldMenu')
        ? 'worker_field_' . $row['custom_field_id']
        : 'custom_field_' . $row['custom_field_id'];
    $actions = [[
        'href' => ITEM_BASE_URL . '&option=modify_custom_field&custom_field_id=' . $id,
        'label' => TXT_626,
        'tone' => 'quiet',
    ]];
    if ((FieldTypes::normalise((string)$row['field_type']) === FieldTypes::MENU || $row['field_type'] == 'workerFieldMenu')
        && !MenuOptions::isLegacyChild((int)$row['custom_field_id'])) {
        $actions[] = [
            'href' => ITEM_BASE_URL . '&option=modify_menu_values&custom_field_id=' . $id,
            'label' => TXT_287,
            'tone' => 'quiet',
        ];
    }
    $actions[] = [
        'href' => ITEM_BASE_URL . '&option=delete_custom_field&custom_field_id=' . $id,
        'label' => TXT_47,
        'tone' => 'danger',
        'confirm' => $name . "\n" . TXT_400,
    ];

    return [
        'name' => $name,
        'href' => ITEM_BASE_URL . '&option=modify_custom_field&custom_field_id=' . $id,
        'meta' => $row['field_type'] . ' · ' . $key . ' · ' . TXT_451 . ' ' . $row['enabled'],
        'actions' => $actions,
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function itemTypeRecord(array $row): array
{
    $name = (string)$row['item_type_name'];
    $id = rawurlencode((string)$row['item_type_id']);

    return [
        'name' => $name,
        'href' => ITEM_BASE_URL . '&option=modify_item_type&item_type_id=' . $id,
        'meta' => TXT_451 . ': ' . $row['enabled'],
        'actions' => [
            [
                'href' => ITEM_BASE_URL . '&option=modify_item_type&item_type_id=' . $id,
                'label' => TXT_626,
                'tone' => 'quiet',
            ],
            [
                'href' => ITEM_BASE_URL . '&option=delete_item_type&item_type_id=' . $id,
                'label' => TXT_47,
                'tone' => 'danger',
                'confirm' => $name . "\n" . TXT_400,
            ],
        ],
    ];
}

function showFieldTypeResults()
{
    $html = '';
    $table = '';
    if (($_POST['type'] ?? '') == 'custom_field_name') {
        $table = 'custom_fields';
    } elseif (($_POST['type'] ?? '') == 'item_type_name') {
        $table = 'item_types';
    }
    if ($table === '') {
        showFields();
        return;
    }

    $columnArray = array('*');
    if ($_POST['operator'] == '=') {
        $condition = "WHERE " . $_POST['type'] . " = '" . $_POST['criteria'] . "' ORDER BY enabled DESC, " . $_POST['type'] . " ASC";
    } elseif ($_POST['operator'] == 'LIKE') {
        $condition = "WHERE " . $_POST['type'] . " LIKE '%" . $_POST['criteria'] . "%' ORDER BY enabled DESC, " . $_POST['type'] . " ASC";
    }
    $result = Database::select($table, $columnArray, $condition);
    $rows = [];
    $isFields = $table === 'custom_fields';
    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $rows[] = $isFields ? customFieldRecord($row) : itemTypeRecord($row);
        }
    }

    $html = $isFields ? fieldRecordList($rows) : typeRecordList($rows);

    $bodyBlock = [
        'title' => TXT_113,
        'html' => $html,
    ];
    $contentBody = RenderViews::buildVerticalCards([$bodyBlock]);
    define('BODY_CONTENT', $contentBody);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * updateItemType()
 *
 * Updates item type information.  Controller logic: update_item_type
 *
 * @param mixed $itemTypeID Item type id
 */
function updateItemType($itemTypeID)
{
    // Remove unwanted POST variables
    unset ($_POST['submit_button'], $_POST['reset']);
    $selectedFields = selectedCustomFieldsFromPost();
    $groupSecurity = groupSecurityFromPost();
    stripItemTypeFormFields();
    // Remove all existing custom field table entries and then re add changed selection
    $condition = "WHERE item_type_id = '" . $itemTypeID . "'";
    Database::delete('item_type_custom_fields', $condition);
    foreach ($selectedFields as $fieldId => $order) {
        $customFieldArray = [
            'item_type_id' => $itemTypeID,
            'custom_field_id' => $fieldId,
            'custom_field_order' => $order,
        ];
        Database::insert('item_type_custom_fields', $customFieldArray);
    }
    $columnArray = $_POST;
    if ($groupSecurity !== '') {
        $columnArray['group_security'] = $groupSecurity;
    }
    // Set condition
    $condition = "WHERE item_type_id = '$itemTypeID'";
    // Update form field values into row
    Database::update('item_types', $columnArray, $condition);
    RenderViews::buildResponse($_POST['item_type_name'] . ' ' . TXT_164, RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_item_types', TXT_362));
}

/**
 * updateCustomField()
 *
 * Updates custom field information.  Controller logic: update_custom_field
 *
 * @param mixed $customFieldID Custom field id
 */
function updateCustomField($customFieldID)
{
    // Remove unwanted POST variables
    unset ($_POST['submit_button'], $_POST['reset']);
    // Build insert array
    $columnArray = $_POST;
    // Handle data column special characters
    $columnArray['data'] = html_entity_decode($columnArray['data'], ENT_COMPAT, 'UTF-8');
    // Set condition
    $condition = "WHERE custom_field_id = '$customFieldID'";
    // Update form field values into row
    Database::update('custom_fields', $columnArray, $condition);
    RenderViews::buildResponse($_POST['custom_field_name'] . ' ' . TXT_164, RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_fields', TXT_362));
}

/**
 * Checked custom fields from the item-type form, read before any POST keys are removed.
 * Sort inputs can arrive before their checkbox, so the order is captured up front.
 *
 * @return array<string, string> custom field id => sort order
 */
function selectedCustomFieldsFromPost(): array
{
    $selected = [];
    foreach ($_POST as $key => $value) {
        if (str_starts_with((string)$key, 'custom_field_id_') && $value !== '' && $value !== '0') {
            $order = $_POST['custom_field_sort_' . $value] ?? '0';
            $selected[(string)$value] = ($order === '' ? '0' : (string)$order);
        }
    }
    return $selected;
}

/**
 * Group ids posted as group_{id}, in the }-{id}-{ storage format.
 */
function groupSecurityFromPost(): string
{
    $security = '';
    $i = 0;
    foreach ($_POST as $key => $value) {
        if (str_starts_with((string)$key, 'group_') && $value !== '') {
            $security .= ($i === 0 ? '}-{' : '') . $value . '}-{';
            $i++;
        }
    }
    return $security;
}

/**
 * Drop checkbox, sort and group inputs so they are not written onto item_types.
 */
function stripItemTypeFormFields(): void
{
    foreach (array_keys($_POST) as $key) {
        if (str_starts_with((string)$key, 'custom_field_id_')
            || str_starts_with((string)$key, 'custom_field_sort_')
            || str_starts_with((string)$key, 'group_')) {
            unset($_POST[$key]);
        }
    }
}

/**
 * Deletes a custom field, its menu values, item-type links, and the items column when one was added.
 */
function deleteCustomField(): void
{
    $customFieldID = (string)($_GET['custom_field_id'] ?? '');
    if ($customFieldID === '' || !ctype_digit($customFieldID)) {
        RenderViews::buildResponse(TXT_115, RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_fields', TXT_362));
        return;
    }

    $row = Database::first('custom_fields', '*', "WHERE custom_field_id = '" . $customFieldID . "'");
    if (!$row) {
        RenderViews::buildResponse(TXT_115, RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_fields', TXT_362));
        return;
    }

    $name = (string)$row['custom_field_name'];
    $type = (string)$row['field_type'];
    $condition = "WHERE custom_field_id = '" . $customFieldID . "'";
    Database::delete('custom_field_menu_values', $condition);
    Database::delete('item_type_custom_fields', $condition);
    Database::delete('custom_fields', $condition);

    $addsColumn = !in_array($type, ['workerField', 'workerFieldMenu', 'multiLevelMenu'], true);
    if ($addsColumn) {
        $column = 'custom_field_' . $customFieldID;
        if (Database::columnExists('items', $column)) {
            Database::exec('ALTER TABLE items DROP COLUMN ' . Database::escapeIdentifier($column));
        }
    }

    RenderViews::buildResponse($name . ' ' . TXT_47, RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_fields', TXT_362));
}

/**
 * Deletes an item type that has no items, and its custom-field links.
 */
function deleteItemType(): void
{
    $itemTypeID = (string)($_GET['item_type_id'] ?? '');
    $back = RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_item_types', TXT_362);
    if ($itemTypeID === '' || !ctype_digit($itemTypeID)) {
        RenderViews::buildResponse(TXT_115, $back);
        return;
    }

        $row = Database::first('item_types', '*', 'item_type_id = ?', [$itemTypeID]);
    if (!$row) {
        RenderViews::buildResponse(TXT_115, $back);
        return;
    }

    $name = (string)$row['item_type_name'];
    if (Database::exists('items', 'item_type_id = ?', [$itemTypeID])) {
        RenderViews::buildResponse($name . ' ' . TXT_691, $back);
        return;
    }

    $condition = "WHERE item_type_id = '" . $itemTypeID . "'";
    Database::delete('item_type_custom_fields', $condition);
    Database::delete('item_types', $condition);
    RenderViews::buildResponse($name . ' ' . TXT_47, $back);
}


/**
 * Logic to render the appropriate template or call wrapper functions
 */
switch (@$_GET['option']) {
    case 'new_custom_field' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        // Removes leading  from any session variables (used for form value persistence)
        showCustomField('', RenderViews::processVBLPrefixedKeys($_SESSION, 'remove'));
        // Unset session variables starting with
        $_SESSION = RenderViews::processVBLPrefixedKeys($_SESSION, 'unset');
        break;
    case 'add_custom_field' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        addCustomField();
        break;
    case 'modify_custom_field' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showCustomField($_GET['custom_field_id']);
        break;
    case 'modify_menu_values' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        modifyMenuValues($_GET['custom_field_id']);
        break;
    case 'add_update_menu_value' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        addUpdateMenuValue($_POST['custom_field_id']);
        break;
    case 'update_menu_value_filter' :
    case 'modify_menu_value_filters' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        modifyMenuValues((string)($_GET['custom_field_id'] ?? $_POST['custom_field_id'] ?? ''));
        break;
    case 'delete_menu_value' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        deleteMenuValue($_GET['menu_value_id'], $_GET['custom_field_id']);
        break;
    case 'update_custom_field' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        updateCustomField($_POST['custom_field_id']);
        break;
    case 'delete_custom_field' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        deleteCustomField();
        break;
    case 'new_item_type' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        // Removes leading  from any session variables (used for form value persistence)
        showItemType('', RenderViews::processVBLPrefixedKeys($_SESSION, 'remove'));
        // Unset session variables starting with
        $_SESSION = RenderViews::processVBLPrefixedKeys($_SESSION, 'unset');
        break;
    case 'add_item_type' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        addItemType();
        break;
    case 'modify_item_type' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showItemType($_GET['item_type_id']);
        break;
    case 'update_item_type' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        updateItemType($_POST['item_type_id']);
        break;
    case 'manage_fields' :
    case 'manage_fields_types' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showFields();
        break;
    case 'manage_item_types' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showItemTypes();
        break;
    case 'delete_item_type' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        deleteItemType();
        break;
    case 'field_type_search' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showFieldTypeResults();
        break;
    case 'new_multilevel_menu_relationship' :
    case 'add_multilevel_menu' :
    case 'update_multilevel_menu' :
    case 'show_multilevel_menu' :
    case 'show_multilevel_menu_items' :
    case 'update_multilevel_menu_items' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showFields();
        break;
    default :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showSearchOptions();
        break;
}
?>
