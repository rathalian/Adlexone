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
use Adlexone\support\RenderViews;
use Adlexone\support\RenderNavigation;

/**
 * Build the left navigation for Items and Fields using RenderNavigation.
 */
$controllers = RenderNavigation::build([
    'Items and Fields' => RenderNavigation::itemSettingsURLs(),
]);
define('LEFT_NAVIGATION', RenderNavigation::render($controllers, 1, 3, true));
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
        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $fieldValues = Database::fetchArray($result);
        $formAction = 'index.php?controller=administration_item_settings&option=update_custom_field';
        $title = TXT_285;
    }

    $fields[TXT_86] = RenderViews::buildTextInput('custom_field_name', @$fieldValues['custom_field_name']);
    $fields[TXT_210] = RenderViews::buildTextInput('default_value', @$fieldValues['default_value']);
    $fieldTypes = array(FieldTypes::TEXT_BOX, 'password', FieldTypes::TEXT_AREA, FieldTypes::MENU, 'subMenu', 'hidden', 'subMenuChild', 'URL', 'dynamicURL', 'workerField', 'workerFieldMenu', 'dataSourceMenu', 'multiLevelMenu');
    $fieldNames = array(TXT_211, TXT_214, TXT_212, TXT_213, TXT_280, TXT_216, TXT_413, TXT_478, TXT_479, TXT_490, TXT_501, TXT_637, TXT_659);
    $fields[TXT_65] = RenderViews::buildSelectDropdown('field_type', $fieldTypes, $fieldNames, FieldTypes::normalise(@$fieldValues['field_type']));
    $multiLevelURL = (@$fieldValues['field_type'] == 'multiLevelMenu') ? RenderViews::buildURL(ITEM_BASE_URL . '&option=show_multilevel_menu&multi_level_menu_id=' . $customFieldID, TXT_660, 'URL', '') . ' - ' . RenderViews::buildURL(ITEM_BASE_URL . '&option=show_multilevel_menu_items&multi_level_menu_id=' . $customFieldID, TXT_666, 'URL', '') : '';
    $fields[TXT_669] = RenderViews::buildTextInput('menu_levels', @$fieldValues['menu_levels']);
    //lookup data source names
    $i = 1;
    if (defined('SET_DS_DATA_SOURCE_COUNT')) {
        while ($i <= SET_DS_DATA_SOURCE_COUNT) {
            $dataSourceName[] = constant('SET_DS_NAME_' . $i);
            $i++;
        }
        $fields[TXT_636] = RenderViews::buildSelectDropdown('data_source_name', $dataSourceName, $dataSourceName, @$fieldValues['data_source_name']);
    }
    $fields[TXT_477] = RenderViews::buildTextInput('data', @$fieldValues['data']);
    // Javascript field validation setup
    $fields[TXT_505] = RenderViews::buildSelectDropdown('required', array('Yes', 'No'), array(TXT_93, TXT_94), @$fieldValues['required']);
    $validationTypes = array(NULL, 'numeric', 'string', 'alphanumeric', 'date', 'time', 'datetime', 'ip', 'email');
    $validationNames = array(TXT_525, TXT_507, TXT_508, TXT_509, TXT_510, TXT_511, TXT_512, TXT_513, TXT_514);
    $fields[TXT_506] = RenderViews::buildSelectDropdown('validation_type', $validationTypes, $validationNames, @$fieldValues['validation_type']);
    // Setup list of custom fields that can be sub menus
    $columnArray = array('custom_field_name', 'custom_field_id');
    $condition = "WHERE enabled = 'Yes' AND (field_type = 'subMenuChild') AND custom_field_id <> '$customFieldID'";
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    // Set default value
    $valueArray[] = '0';
    $nameArray[] = TXT_283;
    while ($row = Database::fetchArray($result)) {
        $valueArray[] = $row['custom_field_id'];
        $nameArray[] = $row['custom_field_name'];
    }
    $fields[TXT_281] = RenderViews::buildSelectDropdown('sub_menu', $valueArray, $nameArray, @$fieldValues['sub_menu']) . ' * ' . TXT_282;
    $fields[TXT_90] = RenderViews::buildSelectDropdown('enabled', array('Yes', 'No'), array(TXT_93, TXT_94), @$fieldValues['enabled']);
    $fields[''] = RenderViews::buildHiddenInput('custom_field_id', $customFieldID);
    $jsFieldNameArray = "['custom_field_name']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . TXT_217 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return subMenuCheck('" . TXT_541 . "','" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";

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
    $columnArray = array('*');
    $condition = "WHERE custom_field_id = '$customFieldID'";
    $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    $rows = '';
    while ($menuRow = Database::fetchArray($result)) {
        $input = RenderViews::buildTextInput((string)$menuRow['menu_value_id'], $menuRow['menu_value'])
            . RenderViews::buildHiddenInput('menu_value_old_id_' . $menuRow['menu_value_id'], $menuRow['menu_value']);
        $delete = RenderViews::buildURL(
            ITEM_BASE_URL . '&option=delete_menu_value&menu_value_id=' . $menuRow['menu_value_id'] . '&custom_field_id=' . $customFieldID,
            TXT_47,
            '',
            'URL',
            'onClick="javascript:return confirm(\'' . TXT_400 . '\')"'
        );
        $rows .= '<div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:0.75rem;align-items:center;margin-bottom:0.5em;">'
            . '<span>' . $input . '</span>' . $delete . '</div>';
    }
    if ($rows === '') {
        $rows = htmlspecialchars(TXT_366, ENT_QUOTES, 'UTF-8');
    }

    $fields = [];
    $fields[TXT_288] = '<div style="flex:1 1 100%">' . $rows . '</div>';
    $fields[TXT_292] = RenderViews::buildTextInput('menu_value', '');
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

/**
 * Sets sub buildSelectDropdown value filter
 *
 * @param string $customFieldID Custom Field ID
 */
function modifyMenuValueFilters($customFieldID)
{
    $columnArray = array('menu_value_id', 'menu_value', 'sub_menu_values');
    $condition = "WHERE custom_field_id = '" . $customFieldID . "'";
    $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    $fields = [];
    while ($row = Database::fetchArray($result)) {
        $label = (string)$row['menu_value'];
        if (array_key_exists($label, $fields)) {
            $label .= ' (' . $row['menu_value_id'] . ')';
        }
        $fields[$label] = RenderViews::buildTextArea(
            (string)$row['menu_value_id'],
            (string)$row['sub_menu_values'],
            (string)SET_FORM_FIELD_HEIGHT
        );
    }
    $fields[''] = '<p>' . htmlspecialchars(TXT_410, ENT_QUOTES, 'UTF-8') . '</p>'
        . RenderViews::buildHiddenInput('custom_field_id', $customFieldID);

    $buttons = [
        RenderViews::buildFormButton('submit', 'submit_button', TXT_56),
        RenderViews::buildFormButton('reset', 'reset', TXT_75),
    ];

    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_291,
        'index.php?controller=administration_item_settings&option=update_menu_value_filter',
        $fields,
        $buttons
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function addUpdateMenuValue($customFieldID)
{
    if (isset($_POST['add_new']) and $_POST['add_new'] != '') {
        // Check for duplicate and error handling
        $columnArray = array('menu_value');
        $condition = "WHERE custom_field_id = '" . $customFieldID . "' AND menu_value ='" . $_POST['menu_value'] . "'";
        $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        unset($columnArray);
        if (Database::numRows($result) > 0) {
            $html = TXT_294;
            $html .= '<br><br><a href ="' . ITEM_BASE_URL . '&option=modify_menu_values&custom_field_id=' . $customFieldID . '" class="URL">' . TXT_31 . '</a>  ';
            define('BODY_CONTENT', $html);
            define('HEADING', TXT_139);
            RenderViews::renderThemePage('main_page_content', SET_THEME);
        } else {
            // Remove unwanted POST variables
            $columnArray['menu_value_id'] = Database::newID('custom_field_menu_values', 'menu_value_id');
            $columnArray['custom_field_id'] = $customFieldID;
            $columnArray['menu_value'] = $_POST['menu_value'];
            $sql = Database::sqlInsert('custom_field_menu_values', $columnArray);
            Database::query($sql, DSN, SET_SHOW_SQL);
            modifyMenuValues($customFieldID);
        }
    } elseif (isset($_POST['update_existing']) and $_POST['update_existing'] != '') {

        //Setup an array containing the current field values
        foreach ($_POST as $key => $value) {
            if (stristr($key, 'menu_value_old_id_')) {
                $existingValueArray[$key] = $value;
            }
        }
        foreach ($_POST as $key => $value) {
            //Check each custom field for updates
            // Posted field names are strings; menu value ids are numeric
            if (ctype_digit((string)$key)) {
                if ($key != $_POST['menu_value_old_id_' . $key]) {
                    //Update buildSelectDropdown value table
                    $columnArray['menu_value'] = $value;
                    $condition = "WHERE menu_value_id = '" . $key . "'";
                    $sql = Database::sqlUpdate('custom_field_menu_values', $columnArray, $condition);
                    Database::query($sql, DSN, SET_SHOW_SQL);
                    unset($columnArray);
                    if (!array_search($value, $existingValueArray)) {
                        //Update item table only if we cannot find the changed value in any of the old buildSelectDropdown values
                        //For example if the order of fields is changed this will not occur.
                        $columnArray['custom_field_' . $customFieldID] = $value;
                        $condition = "WHERE custom_field_" . $customFieldID . " = '" . $_POST['menu_value_old_id_' . $key] . "'";
                        $sql = Database::sqlUpdate('items', $columnArray, $condition);
                        Database::query($sql, DSN, SET_SHOW_SQL);
                        unset($columnArray);
                    }
                }
            }
        }
        modifyMenuValues($customFieldID);
    }
}

function updateMenuValueFilter($customFieldID)
{
    // Check for duplicate and error handling
    foreach ($_POST as $key => $value) {
        // Update each buildSelectDropdown value's sub buildSelectDropdown values (numeric POST keys are the sub buildSelectDropdown ids)
        if (is_numeric($key)) {
            $columnArray['sub_menu_values'] = $value;
            $condition = "WHERE menu_value_id = '" . $key . "'";
            $sql = Database::sqlUpdate('custom_field_menu_values', $columnArray, $condition);
            Database::query($sql, DSN, SET_SHOW_SQL);
        }
    }
    modifyMenuValueFilters($customFieldID);
}

/**
 * Delete Menu Value
 *
 * @param string $menuValueID Menu Value ID
 * @param string $customFieldID Custom Field ID
 */
function deleteMenuValue($menuValueID, $customFieldID)
{
    // Delete buildSelectDropdown value
    $condition = "WHERE menu_value_id = '" . $menuValueID . "'";
    $sql = Database::sqlDelete('custom_field_menu_values', $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
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
        $sql = Database::sqlSelect('item_types', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $fieldValues = Database::fetchArray($result);
    }

    $fields = [];
    $fields[TXT_87] = RenderViews::buildTextInput('item_type_name', $fieldValues['item_type_name'] ?? '');

    // User assignment select
    $columnArray = ['user_id', 'user_name'];
    $sql = Database::sqlSelect('users', $columnArray);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $userIDArray = [''];
    $userArray = [TXT_267];
    while ($row = Database::fetchArray($result)) {
        $userIDArray[] = $row['user_id'];
        $userArray[] = $row['user_name'];
    }
    $fields[TXT_269] = RenderViews::buildSelectDropdown('user_security', $userIDArray, $userArray, $fieldValues['user_security'] ?? '');

    $columnArray = ['group_id', 'group_name'];
    $condition = "ORDER BY group_name ASC";
    $sql = Database::sqlSelect('groups', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    $groupMembershipArray = explode('}-{', (string)($fieldValues['group_security'] ?? ''));
    $groupMembershipHtml = '<div class="group-security-list">';
    while ($row = Database::fetchArray($result)) {
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
        $sql = Database::sqlSelect('item_type_custom_fields', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $customFieldArray = [];
        $customFieldSortArray = [];
        if (Database::numRows($result) != 0) {
            while ($row = Database::fetchArray($result)) {
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
        'subMenu' => TXT_280,
        'hidden' => TXT_216,
        'subMenuChild' => TXT_413,
        'URL' => TXT_478,
        'dynamicURL' => TXT_479,
        'workerField' => TXT_490,
        'workerFieldMenu' => TXT_501,
        'dataSourceMenu' => TXT_637,
        'multiLevelMenu' => TXT_659,
    ];

    $columnArray = ['*'];
    $condition = "WHERE enabled = 'Yes' ORDER BY custom_field_name";
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    $availableItems = '';
    $selectedItems = [];
    while ($row = Database::fetchArray($result)) {
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
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    if (Database::numRows($result) == 0) {
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
        $sql = Database::sqlInsert('custom_fields', $columnArray);
        Database::query($sql, DSN, SET_SHOW_SQL);
        if ($_POST['field_type'] != 'workerField' and $_POST['field_type'] != 'workerFieldMenu' and $_POST['field_type'] != 'multiLevelMenu') {
            // Create item table column
            $sql = "ALTER TABLE " . "items ADD custom_field_" . $id . " text";
            Database::query($sql, DSN, SET_SHOW_SQL);
        }
        RenderViews::buildResponse($_POST['custom_field_name'] . ' ' . TXT_162, RenderViews::buildURL(ITEM_BASE_URL, TXT_362));
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
    $sql = Database::sqlSelect('item_types', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    if (Database::numRows($result) == 0) {
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
            $sql = Database::sqlInsert('item_type_custom_fields', $customFieldArray);
            Database::query($sql, DSN, SET_SHOW_SQL);
        }
        $array['group_security'] = groupSecurityFromPost();
        stripItemTypeFormFields();
        // Build insert array
        $columnArray = array_merge($array, $_POST);
        // Insert form field values into row
        $sql = Database::sqlInsert('item_types', $columnArray);
        Database::query($sql, DSN, SET_SHOW_SQL);
        RenderViews::buildResponse($_POST['item_type_name'] . ' ' . TXT_162, RenderViews::buildURL(ITEM_BASE_URL, TXT_362));
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
 * Custom fields and item types, each with edit and delete.
 */
function showFieldsAndTypes(): void
{
    $fieldRows = [];
    foreach (Database::buildArray(Database::sqlSelect('custom_fields', '*', 'ORDER BY custom_field_name ASC')) as $row) {
        $fieldRows[] = customFieldRecord($row);
    }
    $typeRows = [];
    foreach (Database::buildArray(Database::sqlSelect('item_types', '*', 'ORDER BY item_type_name ASC')) as $row) {
        $typeRows[] = itemTypeRecord($row);
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        ['title' => TXT_53, 'html' => fieldRecordList($fieldRows)],
        ['title' => TXT_50, 'html' => typeRecordList($typeRows)],
        ['id' => 'add-multilevel-menu', 'title' => TXT_658, 'html' => showMultiLevelMenu('', '', true)],
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
        'primary' => ['href' => ITEM_BASE_URL . '&option=new_custom_field', 'label' => TXT_88],
        'buttons' => [['href' => ITEM_BASE_URL . '&option=manage_fields_types#add-multilevel-menu', 'label' => TXT_658]],
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
        'primary' => ['href' => ITEM_BASE_URL . '&option=new_item_type', 'label' => TXT_85],
        'empty' => TXT_115,
        'noMatch' => TXT_689,
        'groups' => [['rows' => $rows]],
    ]);
}

/**
 * Parent Menu-with-Sub-Menu fields whose sub menu is this child field.
 *
 * @return array<int, string>
 */
function submenuParentIds(string $childId): array
{
    if ($childId === '' || !ctype_digit($childId)) {
        return [];
    }
    $sql = Database::sqlSelect(
        'custom_fields',
        ['custom_field_id'],
        "WHERE field_type = 'subMenu' AND sub_menu = '" . $childId . "'"
    );
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $ids = [];
    while ($parent = Database::fetchArray($result)) {
        if (!empty($parent['custom_field_id'])) {
            $ids[] = (string)$parent['custom_field_id'];
        }
    }
    return $ids;
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
    if ($row['field_type'] == FieldTypes::MENU || $row['field_type'] == 'subMenu' || $row['field_type'] == 'workerFieldMenu') {
        $actions[] = [
            'href' => ITEM_BASE_URL . '&option=modify_menu_values&custom_field_id=' . $id,
            'label' => TXT_287,
            'tone' => 'quiet',
        ];
    }
    if ($row['field_type'] == 'subMenu') {
        $actions[] = [
            'href' => ITEM_BASE_URL . '&option=modify_menu_value_filters&custom_field_id=' . $id,
            'label' => TXT_291,
            'tone' => 'quiet',
        ];
    }
    if ($row['field_type'] == 'subMenuChild') {
        foreach (submenuParentIds((string)$row['custom_field_id']) as $parentId) {
            $actions[] = [
                'href' => ITEM_BASE_URL . '&option=modify_menu_value_filters&custom_field_id=' . rawurlencode($parentId),
                'label' => TXT_291,
                'tone' => 'quiet',
            ];
        }
    }
    if ($row['field_type'] == 'multiLevelMenu') {
        $actions[] = [
            'href' => ITEM_BASE_URL . '&option=show_multilevel_menu&multi_level_menu_id=' . $id,
            'label' => TXT_660,
            'tone' => 'quiet',
        ];
        $actions[] = [
            'href' => ITEM_BASE_URL . '&option=show_multilevel_menu_items&multi_level_menu_id=' . $id,
            'label' => TXT_666,
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
        showFieldsAndTypes();
        return;
    }

    $columnArray = array('*');
    if ($_POST['operator'] == '=') {
        $condition = "WHERE " . $_POST['type'] . " = '" . $_POST['criteria'] . "' ORDER BY enabled DESC, " . $_POST['type'] . " ASC";
    } elseif ($_POST['operator'] == 'LIKE') {
        $condition = "WHERE " . $_POST['type'] . " LIKE '%" . $_POST['criteria'] . "%' ORDER BY enabled DESC, " . $_POST['type'] . " ASC";
    }
    $sql = Database::sqlSelect($table, $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $rows = [];
    $isFields = $table === 'custom_fields';
    if ($result && Database::numRows($result) > 0) {
        while ($row = Database::fetchArray($result)) {
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
    $sql = Database::sqlDelete('item_type_custom_fields', $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
    foreach ($selectedFields as $fieldId => $order) {
        $customFieldArray = [
            'item_type_id' => $itemTypeID,
            'custom_field_id' => $fieldId,
            'custom_field_order' => $order,
        ];
        $sql = Database::sqlInsert('item_type_custom_fields', $customFieldArray);
        Database::query($sql, DSN, SET_SHOW_SQL);
    }
    $columnArray = $_POST;
    if ($groupSecurity !== '') {
        $columnArray['group_security'] = $groupSecurity;
    }
    // Set condition
    $condition = "WHERE item_type_id = '$itemTypeID'";
    // Update form field values into row
    $sql = Database::sqlUpdate('item_types', $columnArray, $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
    RenderViews::buildResponse($_POST['item_type_name'] . ' ' . TXT_164, RenderViews::buildURL(ITEM_BASE_URL, TXT_362));
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
    $sql = Database::sqlUpdate('custom_fields', $columnArray, $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
    RenderViews::buildResponse($_POST['custom_field_name'] . ' ' . TXT_164, RenderViews::buildURL(ITEM_BASE_URL, TXT_362));
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
        RenderViews::buildResponse(TXT_115, RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_fields_types', TXT_362));
        return;
    }

    $sql = Database::sqlSelect('custom_fields', '*', "WHERE custom_field_id = '" . $customFieldID . "'");
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);
    if (!$row) {
        RenderViews::buildResponse(TXT_115, RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_fields_types', TXT_362));
        return;
    }

    $name = (string)$row['custom_field_name'];
    $type = (string)$row['field_type'];
    $condition = "WHERE custom_field_id = '" . $customFieldID . "'";
    Database::query(Database::sqlDelete('custom_field_menu_values', $condition), DSN, SET_SHOW_SQL);
    Database::query(Database::sqlDelete('item_type_custom_fields', $condition), DSN, SET_SHOW_SQL);
    Database::query(Database::sqlDelete('custom_fields', $condition), DSN, SET_SHOW_SQL);

    $addsColumn = !in_array($type, ['workerField', 'workerFieldMenu', 'multiLevelMenu'], true);
    if ($addsColumn) {
        $column = 'custom_field_' . $customFieldID;
        $existing = Database::buildArray("SELECT name FROM pragma_table_info('items') WHERE name = '" . $column . "'");
        if ($existing !== []) {
            Database::query('ALTER TABLE items DROP COLUMN ' . Database::escapeIdentifier($column), DSN, SET_SHOW_SQL);
        }
    }

    RenderViews::buildResponse($name . ' ' . TXT_47, RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_fields_types', TXT_362));
}

/**
 * Deletes an item type that has no items, and its custom-field links.
 */
function deleteItemType(): void
{
    $itemTypeID = (string)($_GET['item_type_id'] ?? '');
    $back = RenderViews::buildURL(ITEM_BASE_URL . '&option=manage_fields_types', TXT_362);
    if ($itemTypeID === '' || !ctype_digit($itemTypeID)) {
        RenderViews::buildResponse(TXT_115, $back);
        return;
    }

    $result = Database::query(Database::sqlSelect('item_types', '*', "WHERE item_type_id = '" . $itemTypeID . "'"), DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);
    if (!$row) {
        RenderViews::buildResponse(TXT_115, $back);
        return;
    }

    $name = (string)$row['item_type_name'];
    $inUse = Database::buildArray("SELECT item_id FROM items WHERE item_type_id = '" . $itemTypeID . "' LIMIT 1");
    if ($inUse !== []) {
        RenderViews::buildResponse($name . ' ' . TXT_691, $back);
        return;
    }

    $condition = "WHERE item_type_id = '" . $itemTypeID . "'";
    Database::query(Database::sqlDelete('item_type_custom_fields', $condition), DSN, SET_SHOW_SQL);
    Database::query(Database::sqlDelete('item_types', $condition), DSN, SET_SHOW_SQL);
    RenderViews::buildResponse($name . ' ' . TXT_47, $back);
}


/**
 * Displays the form for adding or editing a multi-level menu.
 *
 * This function renders a form that allows users to create or update a multi-level menu.
 * It dynamically generates the form fields based on the provided `$multiLevelMenuID` and `$values`.
 * The form uses a modern, semantic HTML structure with div-based layout for accessibility and user-friendliness.
 *
 * Key Features:
 * - Dynamically determines if the form is for adding or editing based on `$multiLevelMenuID`.
 * - Fetches existing menu data from the database when editing.
 * - Parses and displays existing menu relationships.
 * - Builds a dropdown for selecting available multi-level menus.
 * - Generates a list of custom fields with checkboxes and sort dropdowns.
 * - Uses `RenderViews::buildForm` for rendering the form with a modern layout.
 *
 * @param string $multiLevelMenuID The ID of the multi-level menu to edit. If empty, a new menu is being created.
 * @param array|string $values Field values passed in for retaining form field values if an error occurred during entry.
 * @return void
 */
function showMultiLevelMenu($multiLevelMenuID = '', $values = '', bool $returnToList = false)
{
    $isNew = ($multiLevelMenuID === '');

    // Prepare form action and field values
    if ($isNew) {
        $formAction = 'index.php?controller=administration_item_settings&option=add_multilevel_menu';
        $fieldValues = is_array($values) ? $values : [];
    } else {
        $formAction = 'index.php?controller=administration_item_settings&option=update_multilevel_menu';
        $columnArray = ['*'];
        $condition = "WHERE custom_field_id = '" . (string)$multiLevelMenuID . "'";
        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $fieldValues = Database::fetchArray($result) ?: [];
    }

    // Parse existing menu_relationship safely
    $customFieldIDArray = [];
    $customFieldSortArray = [];
    $menuRelRaw = $fieldValues['menu_relationship'] ?? '';
    if (is_string($menuRelRaw) && $menuRelRaw !== '') {
        $customFieldArray = explode('}-{', $menuRelRaw);
        foreach ($customFieldArray as $value) {
            if ($value === '') {
                continue;
            }
            $fieldArray = explode(',', $value);
            $id = $fieldArray[0] ?? null;
            $sort = $fieldArray[1] ?? '0';
            if ($id !== null && $id !== '') {
                $customFieldIDArray[$id] = $id;
                $customFieldSortArray[$id] = $sort;
            }
        }
    }

    // Build select of available multi-level menus
    $menuIDArray = [];
    $menuNameArray = [];
    $columnArray = ['custom_field_id', 'custom_field_name'];
    $condition = "WHERE field_type = 'multiLevelMenu' and enabled = 'Yes'";
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    while ($row = Database::fetchArray($result)) {
        $menuIDArray[] = $row['custom_field_id'];
        $menuNameArray[] = $row['custom_field_name'];
    }
    $selectedMenu = $fieldValues['custom_field_id'] ?? '';
    if ($menuIDArray === []) {
        $fieldSelect = '<div class="mlm-note">' . htmlspecialchars(TXT_690, ENT_QUOTES, 'UTF-8') . ' '
            . RenderViews::buildURL('index.php?controller=administration_item_settings&option=new_custom_field', TXT_88)
            . '</div>';
    } else {
        $fieldSelect = RenderViews::buildSelectDropdown(
            'multi_level_menu_id',
            $menuIDArray,
            $menuNameArray,
            $selectedMenu
        );
    }

    $columnArray = ['*'];
    $condition = "WHERE enabled = 'Yes' AND field_type IN (" . FieldTypes::sqlInList(FieldTypes::MENU) . ") ORDER BY custom_field_name";
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $menuFields = [];
    while ($row = Database::fetchArray($result)) {
        $menuFields[] = $row;
    }

    $menuValues = [0];
    $displayValues = [TXT_661];
    $fieldCount = count($menuFields);
    for ($i = 1; $i <= $fieldCount; $i++) {
        $menuValues[$i] = $i;
        $displayValues[$i] = $i;
    }

    $rows = '';
    foreach ($menuFields as $row) {
        $id = (string)($row['custom_field_id'] ?? '');
        $name = (string)($row['custom_field_name'] ?? '');
        $isChecked = isset($customFieldIDArray[$id]);
        $checkbox = RenderViews::buildCheckBox(
            'custom_field_id_' . $id,
            $id,
            $isChecked ? $id : '',
            'checkbox',
            $name
        );
        $sortDropdown = RenderViews::buildSelectDropdown(
            'custom_field_sort_' . $id,
            $menuValues,
            $displayValues,
            $customFieldSortArray[$id] ?? '0'
        );
        $rows .= '<tr><td>' . $checkbox . '</td><td>' . $sortDropdown . '</td></tr>';
    }

    $customFieldsHtml = '<div class="mlm-fields"><table class="table">'
        . '<thead><tr><th>' . htmlspecialchars(TXT_665, ENT_QUOTES, 'UTF-8') . '</th>'
        . '<th>' . htmlspecialchars(TXT_668, ENT_QUOTES, 'UTF-8') . '</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table></div>';

    // Determine form title
    $title = $isNew ? TXT_663 : TXT_664;

    // Build form fields for RenderViews::buildForm
    $formFields = [];

    $formFields[TXT_659] = RenderViews::buildHiddenInput('multi_level_menu_id', $multiLevelMenuID) . $fieldSelect;
    if ($returnToList) {
        $formFields[TXT_659] .= RenderViews::buildHiddenInput('return_option', 'manage_fields_types');
    }
    $formFields[''] = $customFieldsHtml;

    // Create form buttons
    $buttons = [];
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_74);
    $buttons[] = RenderViews::buildFormButton('reset', 'reset', TXT_75);

    // Render the form using the modern helper; pass the card title as the form title
    $bodyContent = RenderViews::buildForm(
        $title,
        $formAction,
        $formFields,
        $buttons,
        ['card' => !$returnToList]
    );

    if ($returnToList) {
        return $bodyContent;
    }

    define('BODY_CONTENT', $bodyContent);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function addUpdateMultiLevelMenu($multiLevelMenuID)
{
    $returnToList = (($_POST['return_option'] ?? '') === 'manage_fields_types');
    unset($_POST['return_option']);

    if ((string)$multiLevelMenuID === '') {
        if ($returnToList) {
            showFieldsAndTypes();
            return;
        }
        showMultiLevelMenu();
        return;
    }

    $specialData = '}-{';
    foreach ($_POST as $key => $value) {
        //Check each custom field for updates
        if (stristr($key, 'custom_field_id_') and ($value != '0')) {
            $fieldID = str_replace('custom_field_id_', '', $key);
            $specialData .= $fieldID . ',' . $_POST['custom_field_sort_' . $fieldID] . "}-{";
            unset($_POST[$key]);

        }
    }
    $columnArray['menu_relationship'] = $specialData;
    $condition = "WHERE custom_field_id = '" . $multiLevelMenuID . "'";
    $sql = Database::sqlUpdate('custom_fields', $columnArray, $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
    if ($returnToList) {
        showFieldsAndTypes();
        return;
    }
    showMultiLevelMenu($multiLevelMenuID);

}

function showMultiLevelMenuItems($multiLevelMenuID = '', $fieldValues = '')
{
    $columnArray = array('menu_relationship', 'menu_value_links', 'menu_levels');
    $condition = "WHERE custom_field_id = '$multiLevelMenuID'";
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);

    if (!$row || ($row['menu_relationship'] ?? '') === '') {
        define('BODY_CONTENT', RenderViews::buildVerticalCards([[
            'title' => TXT_666,
            'html' => htmlspecialchars(TXT_667, ENT_QUOTES, 'UTF-8'),
        ]]));
        RenderViews::renderThemePage('main_page_content', SET_THEME);
        return;
    }

    $customFieldIDArray = [];
    foreach (array_filter(explode('}-{', $row['menu_relationship'])) as $value) {
        $parts = explode(',', $value);
        if (($parts[0] ?? '') !== '') {
            $customFieldIDArray[$parts[0]] = $parts[1] ?? '0';
        }
    }
    asort($customFieldIDArray);

    $links = $row['menu_value_links'];
    $savedLinks = (is_string($links) && $links !== '') ? @unserialize($links) : [];
    if (!is_array($savedLinks)) {
        $savedLinks = [];
    }
    $itemCount = (int)$row['menu_levels'];
    if ($itemCount < 1) {
        $edit = RenderViews::buildURL(
            ITEM_BASE_URL . '&option=modify_custom_field&custom_field_id=' . rawurlencode((string)$multiLevelMenuID),
            TXT_626
        );
        define('BODY_CONTENT', RenderViews::buildVerticalCards([[
            'title' => TXT_666,
            'html' => '<p>' . htmlspecialchars(TXT_669, ENT_QUOTES, 'UTF-8') . '</p><p>' . $edit . '</p>',
        ]]));
        RenderViews::renderThemePage('main_page_content', SET_THEME);
        return;
    }

    $fields = [];
    $fields[''] = RenderViews::buildHiddenInput('multi_level_menu_id', $multiLevelMenuID);
    $level = 1;
    foreach ($customFieldIDArray as $key => $value) {
        $idArray = [];
        $nameArray = [];
        $columnArray = array('menu_value_id', 'menu_value');
        $condition = "WHERE custom_field_id = '$key'";
        $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        while ($menuRow = Database::fetchArray($result)) {
            $idArray[] = $menuRow['menu_value_id'];
            $nameArray[] = $menuRow['menu_value'];
        }

        $menuHTML = '';
        for ($i = 1; $i <= $itemCount; $i++) {
            $fieldValue = $savedLinks[$i][$key] ?? '';
            $menuHTML .= RenderViews::buildSelectDropdown('custom_field_id_' . $key . '-' . $i, $idArray, $nameArray, $fieldValue)
                . ' <span>' . $i . '</span><br>';
        }

        $columnArray = array('custom_field_name');
        $condition = "WHERE custom_field_id = '" . $key . "'";
        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $nameRow = Database::fetchArray($result);
        $label = TXT_668 . ' ' . $level . ': ' . ($nameRow['custom_field_name'] ?? '');
        $fields[$label] = '<div style="flex:1 1 100%">' . $menuHTML . '</div>';
        $level++;
    }

    $buttons = [
        RenderViews::buildFormButton('submit', 'submit_button', TXT_74),
        RenderViews::buildFormButton('reset', 'reset', TXT_75),
    ];

    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_666,
        'index.php?controller=administration_item_settings&option=update_multilevel_menu_items',
        $fields,
        $buttons
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}


function updateMultiLevelMenuItems($multiLevelMenuID)
{
    unset($_POST['submit_button'], $_POST['menu_levels'], $_POST['multi_level_menu_id']);


    foreach ($_POST as $key => $value) {
        $key = str_replace("custom_field_id_", "", $key);
        $keyArray = explode("-", $key);
        $keySeries = $keyArray[1];
        $customFieldID = $keyArray[0];
        $fieldArray[$keySeries][$customFieldID] = $value; //i represents custom field buildSelectDropdown series, and keyNumber represents the custom field id
    }
    $columnArray['menu_value_links'] = serialize($fieldArray);
    $condition = "WHERE custom_field_id = '" . $multiLevelMenuID . "'";
    $sql = Database::sqlUpdate('custom_fields', $columnArray, $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
    showCustomField($multiLevelMenuID);

}

/**
 * Logic to render the appropriate template or call wrapper functions
 */
switch (@$_GET['option']) {
    case 'new_custom_field' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        // Removes leading  from any session variables (used for form value persistence)
        showCustomField('', RenderViews::processVBLPrefixedKeys($_SESSION, 'remove'));
        // Unset session variables starting with
        $_SESSION = RenderViews::processVBLPrefixedKeys($_SESSION, 'unset');
        break;
    case 'add_custom_field' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        addCustomField();
        break;
    case 'modify_custom_field' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        showCustomField($_GET['custom_field_id']);
        break;
    case 'modify_menu_values' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        modifyMenuValues($_GET['custom_field_id']);
        break;
    case 'add_update_menu_value' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        addUpdateMenuValue($_POST['custom_field_id']);
        break;
    case 'update_menu_value_filter' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        updateMenuValueFilter(@$_POST['custom_field_id']);
        break;
    case 'delete_menu_value' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        deleteMenuValue($_GET['menu_value_id'], $_GET['custom_field_id']);
        break;
    case 'modify_menu_value_filters' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        modifyMenuValueFilters($_GET['custom_field_id']);
        break;
    case 'update_custom_field' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        updateCustomField($_POST['custom_field_id']);
        break;
    case 'delete_custom_field' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        deleteCustomField();
        break;
    case 'new_item_type' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        // Removes leading  from any session variables (used for form value persistence)
        showItemType('', RenderViews::processVBLPrefixedKeys($_SESSION, 'remove'));
        // Unset session variables starting with
        $_SESSION = RenderViews::processVBLPrefixedKeys($_SESSION, 'unset');
        break;
    case 'add_item_type' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        addItemType();
        break;
    case 'modify_item_type' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        showItemType($_GET['item_type_id']);
        break;
    case 'update_item_type' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        updateItemType($_POST['item_type_id']);
        break;
    case 'manage_fields_types' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        showFieldsAndTypes();
        break;
    case 'delete_item_type' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        deleteItemType();
        break;
    case 'field_type_search' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        showFieldTypeResults();
        break;
    case 'new_multilevel_menu_relationship' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
        showMultiLevelMenu();
        break;
    case 'add_multilevel_menu' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        addUpdateMultiLevelMenu($_POST['multi_level_menu_id']);
        break;
    case 'update_multilevel_menu' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        addUpdateMultiLevelMenu($_POST['multi_level_menu_id']);
        break;
    case 'show_multilevel_menu' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        showMultiLevelMenu($_GET['multi_level_menu_id']);
        break;
    case 'show_multilevel_menu_items' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        showMultiLevelMenuItems($_GET['multi_level_menu_id']);
        break;
    case 'update_multilevel_menu_items' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        updateMultiLevelMenuItems($_POST['multi_level_menu_id']);
        break;
    default :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        showSearchOptions();
        break;
}
?>