<?php
declare(strict_types=1);

use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\MenuOptions;
use Adlexone\support\RenderViews;
use Adlexone\support\RenderNavigation;

MenuOptions::prepare();
RenderNavigation::applySectionNav('Item types', RenderNavigation::itemSettingsURLs());
define('ITEM_TYPES_BASE_URL', 'index.php?manage=item-types');


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
    $action = $isNew ? 'index.php?manage=item-types&option=add_item_type' : 'index.php?manage=item-types&option=update_item_type';
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
        RenderViews::buildResponse($_POST['item_type_name'] . ' ' . TXT_162, RenderViews::buildURL(ITEM_TYPES_BASE_URL . '&option=manage_item_types', TXT_362));
        return;
    }
    RenderViews::buildResponse($_POST['item_type_name'] . ' ' . TXT_163, RenderViews::buildURL(ITEM_TYPES_BASE_URL . '&option=new_item_type', TXT_363));
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
function typeRecordList(array $rows): string
{
    return RenderViews::buildRecordList([
        'column' => TXT_151,
        'columns' => [
            ['key' => 'enabled', 'label' => TXT_451],
        ],
        'searchLabel' => TXT_3,
        'primary' => ['href' => 'index.php?manage=item-types&option=new_item_type', 'label' => TXT_692],
        'empty' => TXT_115,
        'noMatch' => TXT_689,
        'groups' => [['rows' => $rows]],
    ]);
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
        'href' => ITEM_TYPES_BASE_URL . '&option=modify_item_type&item_type_id=' . $id,
        'cells' => [
            'enabled' => (string)$row['enabled'],
        ],
        'actions' => [
            [
                'href' => ITEM_TYPES_BASE_URL . '&option=modify_item_type&item_type_id=' . $id,
                'label' => TXT_626,
                'tone' => 'quiet',
            ],
            [
                'href' => ITEM_TYPES_BASE_URL . '&option=delete_item_type&item_type_id=' . $id,
                'label' => TXT_47,
                'tone' => 'danger',
                'confirm' => $name . "\n" . TXT_400,
            ],
        ],
    ];
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
    RenderViews::buildResponse($_POST['item_type_name'] . ' ' . TXT_164, RenderViews::buildURL(ITEM_TYPES_BASE_URL . '&option=manage_item_types', TXT_362));
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
 * Deletes an item type that has no items, and its custom-field links.
 */
function deleteItemType(): void
{
    $itemTypeID = (string)($_GET['item_type_id'] ?? '');
    $back = RenderViews::buildURL(ITEM_TYPES_BASE_URL . '&option=manage_item_types', TXT_362);
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


switch ((string) ($_GET['option'] ?? '')) {
    case 'new_item_type':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showItemType('', RenderViews::processVBLPrefixedKeys($_SESSION, 'remove'));
        $_SESSION = RenderViews::processVBLPrefixedKeys($_SESSION, 'unset');
        break;
    case 'add_item_type':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        addItemType();
        break;
    case 'modify_item_type':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showItemType((string) ($_GET['item_type_id'] ?? ''));
        break;
    case 'update_item_type':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        updateItemType((string) ($_POST['item_type_id'] ?? ''));
        break;
    case 'manage_item_types':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showItemTypes();
        break;
    case 'delete_item_type':
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        deleteItemType();
        break;
    default:
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ITEMS);
        showItemTypes();
        break;
}
