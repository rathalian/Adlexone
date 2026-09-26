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
use Adlexone\support\RenderViews;
use Adlexone\support\RenderNavigation;

/**
 * Build the left navigation for the Helpdesk application using RenderNavigation.
 */
$controllers = RenderNavigation::build([
    'Helpdesk' => RenderNavigation::itemSettingsURLs(),
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
        $html = RenderViews::buildStartForm('index.php?controller=administration_item_settings&option=add_custom_field', 'POST', 'form-horizontal');
        $fieldValues = $values;
    } else {
        // Get group values from database
        $columnArray = array('*');
        $condition = "WHERE custom_field_id = '$customFieldID'";
        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $fieldValues = Database::fetchArray($result);
        $html = RenderViews::buildStartForm('index.php?controller=administration_item_settings&option=update_custom_field', 'POST', 'form-horizontal');
    }
    if ($customFieldID != '') {
        $title = TXT_285;
    } else {
        $title = TXT_62;
    }

    //$tableRows = RenderViews::tableData('2', '', array('center'), '', 'tdcHeading', array(TXT_208), 'row');
    $fields[TXT_86] = RenderViews::buildTextInput('custom_field_name', @$fieldValues['custom_field_name']);
    $fields[TXT_210] = RenderViews::buildTextInput('default_value', @$fieldValues['default_value']);
    $fieldTypes = array('buildTextInput', 'password', 'buildTextArea', 'buildSelectDropdown', 'subMenu', 'hidden', 'subMenuChild', 'URL', 'dynamicURL', 'workerField', 'workerFieldMenu', 'dataSourceMenu', 'multiLevelMenu');
    $fieldNames = array(TXT_211, TXT_214, TXT_212, TXT_213, TXT_280, TXT_216, TXT_413, TXT_478, TXT_479, TXT_490, TXT_501, TXT_637, TXT_659);
    $fields[TXT_65] = RenderViews::buildSelectDropdown('field_type', $fieldTypes, $fieldNames, @$fieldValues['field_type']);
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
    $fields[] = RenderViews::buildHiddenInput('custom_field_id', $customFieldID);
    //Javascript field validation
    $jsFieldNameArray = "['custom_field_name']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . TXT_217 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return subMenuCheck('" . TXT_541 . "','" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";
    //$tableRows .= RenderViews::tableData('2', '', '', '' , 'tdc1', array($endForm), 'row');
    $html .= RenderViews::buildFormFieldsGrid($fields);
    $html .= RenderViews::buildEndFormWithButtons([RenderViews::buildFormButton('submit', 'submit_button', TXT_74, $javascript), RenderViews::buildFormButton('reset', 'reset', TXT_75)]);

    $bodyBlock[] = [
        'title' => $title,
        'html' => $html,
    ];
    $body = RenderViews::buildVerticalCards($bodyBlock);
    define('BODY_CONTENT', $body);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Modify buildSelectDropdown and sub buildSelectDropdown values
 *
 * @param string $customFieldID Custom Field ID
 */
function modifyMenuValues($customFieldID)
{
    $html = RenderViews::buildStartForm('index.php?controller=administration_item_settings&option=add_update_menu_value', 'POST', 'form-horizontal');
    $tableRows = RenderViews::tableData('', array('50%', '50%'), '', '', 'tdcHeading', array(TXT_288, TXT_72), 'row');
    // Get custom field information
    $columnArray = array('*');
    $condition = "WHERE custom_field_id = '$customFieldID'";
    $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    while ($menuRow = Database::fetchArray($result)) {
        // Build buildSelectDropdown and sub buildSelectDropdown item array
        $cellData[] = RenderViews::buildTextInput($menuRow['menu_value_id'], $menuRow['menu_value'], '', 'form-control') . RenderViews::buildHiddenInput('menu_value_old_id_' . $menuRow['menu_value_id'], $menuRow['menu_value']);
        $cellData[] = RenderViews::buildURL(ITEM_BASE_URL . '&option=delete_menu_value&menu_value_id=' . $menuRow['menu_value_id'] . '&custom_field_id=' . $customFieldID, TXT_47, 'URL', '', 'onClick="javascript:return confirm(\'' . TXT_400 . '\')"');
        $tableRows .= RenderViews::tableData('', '', '', '', 'tdc1', $cellData, 'row');
        unset($cellData);
    }
    $html .= RenderViews::table('100%', '0', '5', '0', 'tcBorder', $tableRows);
    $html .= '<br>';
    $tableRows = RenderViews::tableData('2', '', 'left', '', 'tdcHeading', array(TXT_292), 'row');
    $cellData = array('<strong>' . TXT_293 . '</strong>: ' . RenderViews::buildTextInput('menu_value', ''));
    $tableRows .= RenderViews::tableData('', array('100%'), array('left'), '', 'tdc1', $cellData, 'row');
    $html .= RenderViews::buildHiddenInput('custom_field_id', $customFieldID);
    $buttonArray[] = RenderViews::buildFormButton('submit', 'add_new', TXT_545);
    $buttonArray[] = RenderViews::buildFormButton('submit', 'update_existing', TXT_542);
    $buttonArray[] = RenderViews::buildFormButton('reset', 'reset', TXT_75);
    $endForm = RenderViews::buildEndFormWithButtons($buttonArray, '1');
    $tableRows .= RenderViews::tableData('2', '', '', '', 'tdc1', array($endForm), 'row');
    $html .= RenderViews::table('95%', '0', '0', '0', 'tableIndent', $tableRows);
    define('HEADING', $heading);
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Sets sub buildSelectDropdown value filter
 *
 * @param string $customFieldID Custom Field ID
 */
function modifyMenuValueFilters($customFieldID)
{
    $html = RenderViews::buildStartForm('index.php?controller=administration_item_settings&option=update_menu_value_filter', 'POST', 'form-horizontal');
    $tableRows = RenderViews::tableData('', array('30%', '70%'), '', '', 'tdcHeading', array(TXT_288, TXT_295), 'row');
    // Get buildSelectDropdown and sub buildSelectDropdown values
    $columnArray = array('menu_value_id', 'menu_value', 'sub_menu_values');
    $condition = "WHERE custom_field_id = '" . $customFieldID . "'";
    $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    // Build table containing buildSelectDropdown values, and list of sub buildSelectDropdown values for each
    while ($row = Database::fetchArray($result)) {
        $cellData[] = '<strong>' . $row['menu_value'] . '</strong>';
        $cellData[] = render::textArea($row['menu_value_id'], $row['sub_menu_values'], SET_FORM_FIELD_HEIGHT);
        $tableRows .= RenderViews::tableData('', array('30%', '70%'), '', '', 'tdc1', $cellData, 'row');
        unset($cellData);
    }
    $tableRows .= RenderViews::tableData('', array('30%', '70%'), '', '', 'tdc1', array('', TXT_410), 'row');
    $endForm = RenderViews::buildHiddenInput('custom_field_id', $customFieldID);
    $buttonArray[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_56);
    $buttonArray[] = RenderViews::buildFormButton('reset', 'reset', TXT_75);
    $endForm = RenderViews::buildEndFormWithButtons($buttonArray);

    $tableRows .= RenderViews::tableData('', '', '', '', 'tdc1', array($endForm), 'row');
    $html .= RenderViews::table('95%', '0', '5', '0', 'tableIndent', $tableRows);

    define('HEADING', TXT_291);
    define('BODY_CONTENT', $html);
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
            if (is_int($key)) {
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
function showItemType($itemTypeID, $values)
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

    // Start form
    $formHtml = RenderViews::buildStartForm($action, 'POST', 'form-horizontal');

    // Basic fields
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
    $groupMembershipHtml = '';

    while ($row = Database::fetchArray($result)) {
        $isChecked = in_array($row['group_id'], $groupMembershipArray, true);
        $checkedValue = $isChecked ? $row['group_id'] : '';

        $nameEscaped = htmlspecialchars($row['group_name'], ENT_QUOTES, 'UTF-8');

        // Render checkbox with no label text so only the input appears on the right
        $checkboxHtml = RenderViews::buildCheckBox('group_' . $row['group_id'], $row['group_id'], $checkedValue, 'form-control', '');

        // Each group on its own line; name bold left, checkbox right, then an explicit line break
        $groupMembershipHtml .= '<div style="display:flex;justify-content:space-between;align-items:center;">' . $nameEscaped . '' . $checkboxHtml . '</div><br />';
    }

    $fields[TXT_71] = $groupMembershipHtml;


    // Enabled select
    $fields[TXT_90] = RenderViews::buildSelectDropdown('enabled', ['Yes', 'No'], [TXT_93, TXT_94], $fieldValues['enabled'] ?? '');

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

    // Query enabled custom fields
    $columnArray = ['*'];
    $condition = "WHERE enabled = 'Yes' ORDER BY custom_field_name";
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $fieldCount = Database::numRows($result);

    $menuValues = [0 => 0];
    $displayValues = [0 => TXT_409];
    for ($i = 1; $i <= $fieldCount; $i++) {
        $menuValues[$i] = $i;
        $displayValues[$i] = $i;
    }
    // Build enabled custom fields list as block rows
    $rows = [];
    $firstDropdown = true;

    while ($row = Database::fetchArray($result)) {
        $isChecked = in_array($row['custom_field_id'], $customFieldArray, true);
        $checkedValue = $isChecked ? $row['custom_field_id'] : '';

        $sortValue = $customFieldSortArray['field_order_' . $row['custom_field_id']] ?? '';

        $checkbox = RenderViews::buildCheckBox(
            'custom_field_id_' . $row['custom_field_id'],
            $row['custom_field_id'],
            $checkedValue,
            'form-control',
            ''  // label handled separately
        );

        $select = RenderViews::buildSelectDropdown(
            'custom_field_sort_' . $row['custom_field_id'],
            $menuValues,
            $displayValues,
            $sortValue
        );

        $nameEscaped = htmlspecialchars($row['custom_field_name'], ENT_QUOTES, 'UTF-8');

        // Use margin spacing for the first row and modest spacing for others
        $rowStyle = $firstDropdown ? ' style="margin:1em 0;"' : ' style="margin-bottom:0.5em;"';

        $rows[] = '<div class="field-row"' . $rowStyle . '>'
            . '<span class="cf-name">' . $nameEscaped . '</span> '
            . $checkbox . ' '
            . '<span class="cf-enabled">' . htmlspecialchars('(enabled)', ENT_QUOTES, 'UTF-8') . '</span> '
            . $select
            . '</div>';

        $firstDropdown = false;
    }

    $customFieldsHtml = '<div class="custom-fields-list">' . implode("\n", $rows) . '</div>';
    $fields[TXT_222] = $customFieldsHtml;

    // Hidden field
    $fields[] = RenderViews::buildHiddenInput('item_type_id', $itemTypeID);

    // Javascript field validation setup (preserved)
    $jsFieldNameArray = "['item_type_name']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . TXT_223 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";

    // Buttons
    $buttons = [];
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_74, $javascript);
    $buttons[] = RenderViews::buildFormButton('reset', 'reset', TXT_75);

    // Render fields grid and buttons
    $formHtml .= RenderViews::buildFormFieldsGrid($fields);
    $formHtml .= RenderViews::buildEndFormWithButtons($buttons, 1, 1);

    // Wrap in a content block and render as vertical card to keep page layout consistent
    $bodyBlock[] = [
        'title' => $isNew ? TXT_85 : TXT_286,
        'html' => $formHtml,
    ];
    $body = RenderViews::buildVerticalCards($bodyBlock);

    // Set heading and body and include page
    //define('HEADING', $isNew ? TXT_85 : TXT_286);
    define('BODY_CONTENT', $body);
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
        $html = RenderViews::showResponse('<strong>' . $_POST['custom_field_name'] . '</strong> ' . TXT_162, RenderViews::buildURL(ITEM_BASE_URL, TXT_362, 'URL'));
        define('BODY_CONTENT', $html);
    } else {
        $html = RenderViews::showResponse($_POST['custom_field_name'] . ' ' . TXT_163, RenderViews::buildURL(ITEM_BASE_URL . '&option=new_custom_field', TXT_362, 'URL'));
        define('BODY_CONTENT', $html);
    }
    define('HEADING', TXT_139);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
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
        $i = 0;
        foreach ($_POST as $key => $value) {
            // Custom field check boxes are the only numeric form fields
            if (stristr($key, 'custom_field_id')) {
                // Build insert array
                $customFieldArray['item_type_id'] = $array['item_type_id'];
                $customFieldArray['custom_field_id'] = $value;
                $customFieldArray['custom_field_order'] = $_POST['custom_field_sort_' . $value];
                // Insert form field values into row
                $sql = Database::sqlInsert('item_type_custom_fields', $customFieldArray);
                Database::query($sql, DSN, SET_SHOW_SQL);
                // Posted field is not longer required
                unset($_POST[$key]);
            }
            // Build group membership delimited string
            if (stristr($key, 'group_')) {
                if ($i == 0) {
                    $array['group_security'] = '}-{' . $value . '}-{';
                } else {
                    $array['group_security'] .= $value . '}-{';
                }
                unset ($_POST[$key]);
                $i++;
            }
            if (stristr($key, 'custom_field_sort')) {
                unset ($_POST[$key]);
            }
        }
        // Build insert array
        $columnArray = array_merge($array, $_POST);
        // Insert form field values into row
        $sql = Database::sqlInsert('item_types', $columnArray);
        Database::query($sql, DSN, SET_SHOW_SQL);
        $html = RenderViews::showResponse($_POST['item_type_name'] . ' ' . TXT_162, RenderViews::buildURL(ITEM_BASE_URL, TXT_362, 'URL'));
        define('BODY_CONTENT', $html);
    } else {
        $html = RenderViews::showResponse($_POST['item_type_name'] . ' ' . TXT_163, RenderViews::buildURL(ITEM_BASE_URL . '&option=new_item_type', TXT_363, 'URL'));
        define('BODY_CONTENT', $html);
    }
    define('HEADING', TXT_85);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
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
    // Build individual form controls (values and labels come from language constants)
    $searchOptions = RenderViews::buildSelectDropdown(
        'type',
        ['custom_field_name', 'item_type_name'],
        [TXT_86, TXT_87],
        ''
    );
    $searchOperator = RenderViews::buildSelectDropdown(
        'operator',
        ['LIKE', '='],
        [TXT_80, TXT_81],
        ''
    );
    $searchCriteria = RenderViews::buildTextInput('criteria', '');

    // Compose labelled fields for RenderViews::buildForm
    // Omit the old buildFormSectionHeading; use the form title parameter instead.
    $formFields = [];
    $formFields[TXT_82] = '<div>' . $searchOptions . ' ' . $searchOperator . ' ' . $searchCriteria . '</div>';

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
function showFieldTypeResults()
{
    $html = '';
    // Build sql based on group or user type
    if ($_POST['type'] == 'custom_field_name') {
        $table = 'custom_fields';
    } elseif ($_POST['type'] == 'item_type_name') {
        $table = 'item_types';
    }

    $columnArray = array('*');
    if ($_POST['operator'] == '=') {
        $condition = "WHERE " . $_POST['type'] . " = '" . $_POST['criteria'] . "' ORDER BY enabled DESC, " . $_POST['type'] . " ASC";
    } elseif ($_POST['operator'] == 'LIKE') {
        $condition = "WHERE " . $_POST['type'] . " LIKE '%" . $_POST['criteria'] . "%' ORDER BY enabled DESC, " . $_POST['type'] . " ASC";
    }
    $sql = Database::sqlSelect($table, $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    if (Database::numRows($result) == 0) {
        $html .= TXT_115;
    } else {
        $i = 0;
        while ($row = Database::fetchArray($result)) {
            // RenderViews table data differently for user and group search results
            if ($table == 'custom_fields') {
                $fields[TXT_151] = '<strong>' . $row['custom_field_name'] . ' (' . $row['field_type'] . ')' . '</strong>';
                $fields[TXT_426] = ($row['field_type'] == 'workerField' or $row['field_type'] == 'workerFieldMenu') ? 'worker_field_' . $row['custom_field_id'] : 'custom_field_' . $row['custom_field_id'];
                $action = '<a href ="' . ITEM_BASE_URL . '&option=modify_custom_field&custom_field_id=' . $row['custom_field_id'] . '" class="URL">' . TXT_224 . '</a>  ';
                if ($row['field_type'] == 'buildSelectDropdown' or $row['field_type'] == 'subMenu' or $row['field_type'] == 'workerFieldMenu') {
                    $action .= ' - <a href ="' . ITEM_BASE_URL . '&option=modify_menu_values&custom_field_id=' . $row['custom_field_id'] . '" class="URL">' . TXT_287 . '</a>  ';
                }
                if ($row['field_type'] == 'subMenu') {
                    $action .= ' - <a href ="' . ITEM_BASE_URL . '&option=modify_menu_value_filters&custom_field_id=' . $row['custom_field_id'] . '" class="URL">' . TXT_291 . '</a>  ';
                }
                $fields[TXT_451] = $row['enabled'];
                $fields[TXT_198] = $action;
                $html .= RenderViews::buildFormFieldsGrid($fields);
                $html .= RenderViews::buildHorizontalSeparator();
                unset ($fields);
//				$class = RenderViews::setOddEvenClass($i, 'trc1', 'trc2');
//				$tableRows .= RenderViews::tableData('', array('20%','20%','20%','40%'), array('left','left','left','left'), '' , $class, $cellData, 'row');
            } elseif ($table == 'item_types') {
                $fields[TXT_151] = $row['item_type_name'];
                $fields[TXT_426] = $row['item_type_id'];
                $fields[TXT_451] = $row['enabled'];
                $action = RenderViews::buildURL(ITEM_BASE_URL . '&option=modify_item_type&item_type_id=' . $row['item_type_id'], TXT_286, 'URL');
                $fields[TXT_198] = $action;
                $html .= RenderViews::buildFormFieldsGrid($fields);
                $html .= RenderViews::buildHorizontalSeparator();
                unset ($fields);

            }
            $i++;
        }
    }

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
    // Remove all existing custom field table entries and then re add changed selection
    $condition = "WHERE item_type_id = '" . $itemTypeID . "'";
    $sql = Database::sqlDelete('item_type_custom_fields', $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
    $i = 0;
    foreach ($_POST as $key => $value) {
        // Custom field check boxes are the only numeric form fields
        if (stristr($key, 'custom_field_id_')) {
            // Build insert array
            $customFieldArray['item_type_id'] = $itemTypeID;
            $customFieldArray['custom_field_id'] = $value;
            $customFieldArray['custom_field_order'] = $_POST['custom_field_sort_' . $value];
            // Insert form field values into row
            $sql = Database::sqlInsert('item_type_custom_fields', $customFieldArray);
            Database::query($sql, DSN, SET_SHOW_SQL);
            // Posted field is not longer required
            unset ($_POST['custom_field_id_' . $value]);
            unset($_POST['custom_field_sort_' . $value]);
        }
        // Build group membership delimited string
        if (stristr($key, 'group_')) {
            if ($i == 0) {
                $securityArray['group_security'] = '}-{' . $value . '}-{';
            } else {
                $securityArray['group_security'] .= $value . '}-{';
            }
            unset ($_POST[$key]);
            $i++;
        }        //Handle sort fields not related to selected custom fields
        if (stristr($key, 'custom_field_sort_')) {
            unset ($_POST[$key]);
        }
    }
    // Build insert array
    if (isset($securityArray['group_security'])) {
        $columnArray = array_merge($_POST, $securityArray);
    } else {
        $columnArray = $_POST;
    }
    // Set condition
    $condition = "WHERE item_type_id = '$itemTypeID'";
    // Update form field values into row
    $sql = Database::sqlUpdate('item_types', $columnArray, $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
    $html = RenderViews::showResponse($_POST['item_type_name'] . ' ' . TXT_164, RenderViews::buildURL(ITEM_BASE_URL, TXT_362, 'URL'));
    define('BODY_CONTENT', $html);
    define('HEADING', TXT_139);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
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
    $html = RenderViews::showResponse('<strong>' . $_POST['custom_field_name'] . '</strong> ' . TXT_164, RenderViews::buildURL(ITEM_BASE_URL, TXT_362, 'URL'));
    define('BODY_CONTENT', $html);
    define('HEADING', TXT_139);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
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
function showMultiLevelMenu($multiLevelMenuID = '', $values = ''): void
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
    $fieldSelect = RenderViews::buildSelectDropdown(
        'multi_level_menu_id',
        $menuIDArray ?: [''],
        $menuNameArray ?: [TXT_267],
        $selectedMenu
    );

    // Build sort value arrays for dropdowns
    $columnArray = ['*'];
    $condition = "WHERE enabled = 'Yes' AND field_type = 'buildSelectDropdown'";
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $fieldCount = Database::numRows($result) ?: 0;

    $menuValues = [];
    $displayValues = [];
    $menuValues[0] = 0;
    $displayValues[0] = TXT_661;
    for ($i = 1; $i <= (int)$fieldCount; $i++) {
        $menuValues[$i] = $i;
        $displayValues[$i] = $i;
    }

    // Build custom fields list (one line per field)
    $customFields = [];
    // Re-run query to fetch fields to build list
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    while ($row = Database::fetchArray($result)) {
        $id = (string)($row['custom_field_id'] ?? '');
        $nameEscaped = htmlspecialchars($row['custom_field_name'] ?? '', ENT_QUOTES, 'UTF-8');

        $isChecked = !empty($customFieldIDArray) && in_array($id, $customFieldIDArray, true);
        $checkedValue = $isChecked ? (string)$id : '';

        $checkbox = RenderViews::buildCheckBox(
            'custom_field_id_' . $id,
            $id,
            $checkedValue,
            'form-control',
            ''
        );

        $sortValue = $customFieldSortArray[$id] ?? '0';
        $sortDropdown = RenderViews::buildSelectDropdown(
            'custom_field_sort_' . $id,
            $menuValues,
            $displayValues,
            $sortValue
        );

        $customFields[] = '<div style="display:flex;justify-content:space-between;align-items:center;">'
            . '<strong>' . $nameEscaped . '</strong>'
            . '<span>' . $checkbox . ' ' . $sortDropdown . '</span>'
            . '</div>';
    }

    $customFieldsHtml = '<div class="custom-fields-list">' . implode('<br />', $customFields) . '</div>';

    // Determine form title
    $title = $isNew ? TXT_663 : TXT_664;

    // Build form fields for RenderViews::buildForm
    $formFields = [];

    // Use the old buildFormSectionHeading method (keeps legacy appearance)
    // Include hidden id adjacent to the heading so it's inside the form
    $formFields[''] = RenderViews::buildFormSectionHeading(TXT_662) . RenderViews::buildHiddenInput('multi_level_menu_id', $multiLevelMenuID);

    $formFields[TXT_659] = $fieldSelect;
    $formFields[TXT_665] = $customFieldsHtml;

    // Create form buttons
    $buttons = [];
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_74);
    $buttons[] = RenderViews::buildFormButton('reset', 'reset', TXT_75);

    // Render the form using the modern helper; pass the card title as the form title
    $bodyContent = RenderViews::buildForm(
        $title,
        $formAction,
        $formFields,
        $buttons
    );

    define('BODY_CONTENT', $bodyContent);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function addUpdateMultiLevelMenu($multiLevelMenuID)
{
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
    showMultiLevelMenu($multiLevelMenuID);

}

function showMultiLevelMenuItems($multiLevelMenuID = '', $fieldValues = '')
{
    $columnArray = array('menu_relationship', 'menu_value_links', 'menu_levels');
    $condition = "WHERE custom_field_id = '$multiLevelMenuID'";
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);

    if ($row['menu_relationship'] == '') {
        $html = RenderViews::showResponse(TXT_667);
    } else {
        $html = RenderViews::buildStartForm('index.php?controller=administration_item_settings&option=update_multilevel_menu_items', 'POST', 'form-horizontal');
        $tableRows = '';
        $customFieldArray = array_filter(explode('}-{', $row['menu_relationship']));

        foreach ($customFieldArray as $key => $value) {
            if ($value != '') {
                $fieldArray = explode(',', $value);
                $customFieldIDArray[$fieldArray[0]] = $fieldArray[1];
            }
        }
        //Sort custom fields by level
        asort($customFieldIDArray);
        $arrayInt = 1;
        $fieldArray = unserialize($row['menu_value_links']);
        $itemCount = $row['menu_levels'];
        foreach ($customFieldIDArray as $key => $value) {
            $columnArray = array('menu_value_id', 'menu_value');
            $condition = "WHERE custom_field_id = '$key'";
            $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            while ($row = Database::fetchArray($result)) {
                $idArray[] = $row['menu_value_id'];
                $nameArray[] = $row['menu_value'];
            }
            $i = 1;
            $menuHTML = '';
            while ($i <= $itemCount) {
                $fieldValue = (isset($fieldArray[$i][$key])) ? $fieldArray[$i][$key] : '';
                //echo $fieldValue;
                $menuHTML .= RenderViews::buildSelectDropdown('custom_field_id_' . $key . '-' . $i, $idArray, $nameArray, $fieldValue) . ' - ' . $i . '<br>';
                $i++;
            }
            $tableColumnArray[] = $menuHTML;
            $columnArray = array('custom_field_name');
            $condition = "WHERE custom_field_id = '" . $key . "'";
            $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            $row = Database::fetchArray($result);
            $headingColumnArray[] = '<strong>' . TXT_668 . ' ' . $arrayInt . ':' . $row['custom_field_name'] . '</strong>';
            unset($idArray, $nameArray);
            $arrayInt++;
        }
        $html .= RenderViews::buildHiddenInput('multi_level_menu_id', $multiLevelMenuID);

        $tableRows .= RenderViews::tableData('2', '', '', '', 'tdc1', $headingColumnArray, 'row');
        $tableRows .= RenderViews::tableData('2', '', '', '', 'tdc1', $tableColumnArray, 'row');
        $buttonArray[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_74);
        $buttonArray[] = RenderViews::buildFormButton('reset', 'reset', TXT_75);
        $endForm = RenderViews::buildEndFormWithButtons($buttonArray);
        $tableRows .= RenderViews::tableData('2', '', '', '', 'tdc1', array($endForm), 'row');
        $html .= RenderViews::table('95%', '0', '0', '0', 'tableIndent', $tableRows);
    }
    define('HEADING', TXT_666);
    define('BODY_CONTENT', $html);
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
        showSearchOptions();
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
        showItemSettingsOptions();
        break;
}
?>