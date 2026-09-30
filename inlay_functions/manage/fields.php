<?php
declare(strict_types=1);

use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\MenuOptions;
use Adlexone\support\RenderViews;
use Adlexone\support\RenderNavigation;

MenuOptions::prepare();
RenderNavigation::applySectionNav('Fields', RenderNavigation::itemSettingsURLs());
define('FIELDS_BASE_URL', 'index.php?manage=fields');

/**
 * Shows add and edit custom field pages.  Controller logic: new_custom_field, edit_custom_field
 *
 * @param string $customFieldID Custom Fields ID
 * @param array $values Field values passes in via $_SESSION array for retaining form field values if error occurred during entry
 */
function showCustomField($customFieldID = '', $values = '')
{
    if ($customFieldID == '') {
        $formAction = 'index.php?manage=fields&option=add_custom_field';
        $fieldValues = $values;
        $title = TXT_62;
    } else {
        $columnArray = array('*');
        $condition = "WHERE custom_field_id = '$customFieldID'";
        $fieldValues = Database::first('custom_fields', $columnArray, $condition);
        $formAction = 'index.php?manage=fields&option=update_custom_field';
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
        'index.php?manage=fields&option=add_update_menu_value',
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
            $html .= '<br><br><a href ="' . FIELDS_BASE_URL . '&option=modify_menu_values&custom_field_id=' . $customFieldID . '" class="URL">' . TXT_31 . '</a>  ';
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
        RenderViews::buildResponse($_POST['custom_field_name'] . ' ' . TXT_162, RenderViews::buildURL(FIELDS_BASE_URL . '&option=manage_fields', TXT_362));
        return;
    }
    RenderViews::buildResponse($_POST['custom_field_name'] . ' ' . TXT_163, RenderViews::buildURL(FIELDS_BASE_URL . '&option=new_custom_field', TXT_362));
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
        'index.php?manage=fields&option=field_type_search',
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
 * @param array<int, array<string, mixed>> $rows
 */
function fieldRecordList(array $rows): string
{
    return RenderViews::buildRecordList([
        'column' => TXT_151,
        'columns' => [
            ['key' => 'type', 'label' => TXT_395],
            ['key' => 'key', 'label' => TXT_152],
            ['key' => 'enabled', 'label' => TXT_451],
        ],
        'searchLabel' => TXT_3,
        'primary' => ['href' => FIELDS_BASE_URL . '&option=new_custom_field', 'label' => TXT_692],
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
        'href' => FIELDS_BASE_URL . '&option=modify_custom_field&custom_field_id=' . $id,
        'label' => TXT_626,
        'tone' => 'quiet',
    ]];
    if ((FieldTypes::normalise((string)$row['field_type']) === FieldTypes::MENU || $row['field_type'] == 'workerFieldMenu')
        && !MenuOptions::isLegacyChild((int)$row['custom_field_id'])) {
        $actions[] = [
            'href' => FIELDS_BASE_URL . '&option=modify_menu_values&custom_field_id=' . $id,
            'label' => TXT_287,
            'tone' => 'quiet',
        ];
    }
    $actions[] = [
        'href' => FIELDS_BASE_URL . '&option=delete_custom_field&custom_field_id=' . $id,
        'label' => TXT_47,
        'tone' => 'danger',
        'confirm' => $name . "\n" . TXT_400,
    ];

    return [
        'name' => $name,
        'href' => FIELDS_BASE_URL . '&option=modify_custom_field&custom_field_id=' . $id,
        'cells' => [
            'type' => (string)$row['field_type'],
            'key' => $key,
            'enabled' => (string)$row['enabled'],
        ],
        'actions' => $actions,
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
    if ($table !== 'custom_fields') {
        header('Location: index.php?manage=item-types');
        exit;
    }

    $rows = [];
    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $rows[] = customFieldRecord($row);
        }
    }

    $html = fieldRecordList($rows);

    $bodyBlock = [
        'title' => TXT_113,
        'html' => $html,
    ];
    $contentBody = RenderViews::buildVerticalCards([$bodyBlock]);
    define('BODY_CONTENT', $contentBody);
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
    Database::update('custom_fields', $columnArray, $condition);
    RenderViews::buildResponse($_POST['custom_field_name'] . ' ' . TXT_164, RenderViews::buildURL(FIELDS_BASE_URL . '&option=manage_fields', TXT_362));
}



/**
 * Deletes a custom field, its menu values, item-type links, and the items column when one was added.
 */
function deleteCustomField(): void
{
    $customFieldID = (string)($_GET['custom_field_id'] ?? '');
    if ($customFieldID === '' || !ctype_digit($customFieldID)) {
        RenderViews::buildResponse(TXT_115, RenderViews::buildURL(FIELDS_BASE_URL . '&option=manage_fields', TXT_362));
        return;
    }

    $row = Database::first('custom_fields', '*', "WHERE custom_field_id = '" . $customFieldID . "'");
    if (!$row) {
        RenderViews::buildResponse(TXT_115, RenderViews::buildURL(FIELDS_BASE_URL . '&option=manage_fields', TXT_362));
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

    RenderViews::buildResponse($name . ' ' . TXT_47, RenderViews::buildURL(FIELDS_BASE_URL . '&option=manage_fields', TXT_362));
}


switch ((string) ($_GET['option'] ?? '')) {
    case 'new_custom_field':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showCustomField('', RenderViews::processVBLPrefixedKeys($_SESSION, 'remove'));
        $_SESSION = RenderViews::processVBLPrefixedKeys($_SESSION, 'unset');
        break;
    case 'add_custom_field':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        addCustomField();
        break;
    case 'modify_custom_field':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showCustomField((string) ($_GET['custom_field_id'] ?? ''));
        break;
    case 'modify_menu_values':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        modifyMenuValues((string) ($_GET['custom_field_id'] ?? ''));
        break;
    case 'add_update_menu_value':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        addUpdateMenuValue((string) ($_POST['custom_field_id'] ?? ''));
        break;
    case 'update_menu_value_filter':
    case 'modify_menu_value_filters':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        modifyMenuValues((string) ($_GET['custom_field_id'] ?? $_POST['custom_field_id'] ?? ''));
        break;
    case 'delete_menu_value':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        deleteMenuValue((string) ($_GET['menu_value_id'] ?? ''), (string) ($_GET['custom_field_id'] ?? ''));
        break;
    case 'update_custom_field':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        updateCustomField((string) ($_POST['custom_field_id'] ?? ''));
        break;
    case 'delete_custom_field':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        deleteCustomField();
        break;
    case 'manage_fields':
    case 'manage_fields_types':
    case 'new_multilevel_menu_relationship':
    case 'add_multilevel_menu':
    case 'update_multilevel_menu':
    case 'show_multilevel_menu':
    case 'show_multilevel_menu_items':
    case 'update_multilevel_menu_items':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showFields();
        break;
    case 'field_type_search':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showFieldTypeResults();
        break;
    case 'search':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showSearchOptions();
        break;
    default:
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showFields();
        break;
}
