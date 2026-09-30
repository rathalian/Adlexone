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
use Adlexone\support\Actions;
use Adlexone\support\FieldTypes;
use Adlexone\support\MenuOptions;
use Adlexone\Data\GroupMembership;

/**
 * True when the signed-in user may open the item (owner, creator, admin, or shared group).
 */
function userCanAccessItem(int $itemId): bool
{
    $role = (int) ($_SESSION['access_role_id'] ?? 5);
    if ($role <= 2) {
        return true;
    }
    $userId = (int) ($_SESSION['access_user_id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }
    if (Database::exists('items', 'item_id = ? AND (user_security = ? OR creator_security = ?)', [$itemId, $userId, $userId])) {
        return true;
    }
    return GroupMembership::userSharesItemGroup($userId, $itemId);
}

/**
 * Sync item_groups from a legacy group_security string (or POST value).
 */
function syncItemGroupsFromSecurity(int $itemId, ?string $encoded): void
{
    GroupMembership::setItemGroups($itemId, GroupMembership::parseDelimited($encoded));
}

/**
 * Controller Constants
 */
if (!defined('MAN_BASE_URL')) {
    define('MAN_BASE_URL', 'index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage');
}

/**
 *
 * This routine will double check that the ID's are for knowledgebase items and if they are update
 * the database to say that they have been returned
 *
 * @param $knowledgeIds
 * @return none
 */
function updateKnowledgeCount($knowledgeIds): void
{

    foreach ($knowledgeIds as $values) {
        if (substr($values, -1) == '*') {
            $values = substr($values, 0, -1);
            $validId = false;
            if (Database::exists('items', 'WHERE item_id =' . $values . ' AND item_type_id = ' . APP_COUNT_ITEM_TYPE)) {
                $validId = true;
            }
        } else {
            $validId = True;
        }
        $event_id = (isset($_GET['event_id'])) ? $_GET['event_id'] : '';
        $selectionCriteria = 'WHERE item_id =' . $values . ' AND event_id = "' . $event_id . '"';
        if ($validId == True) {
            if (Database::exists('system_log', $selectionCriteria)) {
                $sql = 'UPDATE ' . 'system_log SET event_counter = event_counter + 1 ' . $selectionCriteria;
            } else {
                unset($columnArray);
                $columnArray['item_id'] = $values;
                $columnArray['create_date'] = time();
                $columnArray['event_counter'] = 1;
                $columnArray['event_id'] = $event_id;
                Database::insert('system_log', $columnArray);
                unset($columnArray);
            }

        }
    }
}

/**
 * Shows a list of items defined by item id, filtered by specific item where applicable
 *
 * @param integer $itemIDArray Item ID array
 * @param integer $itemID Item ID to show specifically - must exist in array
 * @param string $orderSQL Order by clause
 */
function showItems($itemIDArray, $itemID = '', $orderSQL = '')
{
    // Get data from database
    global $html;
    $columnArray = array('item_id', 'create_date', 'item_type_id', 'item_title');
    // Override item id list if a specific id has been requested
    if ($itemID != '') {
        $condition = "WHERE item_id = '$itemID'";
    } else {
        $condition = "WHERE ";
        $i = 0;
        // handle empty arrays (i.e. no result returned)
        if (!is_array($itemIDArray)) {
            $condition .= "item_id = ''";
        } else {
            foreach ($itemIDArray as $a) {
                if ($i == 0) {
                    $condition .= "item_id = '$a'";
                } else {
                    $condition .= " OR item_id = '$a'";
                }
                $i++;
            }
        }
    }
    $countrecs = count((array)$itemIDArray);
    $countBoolean = (($countrecs / SET_ITEMS_PAGE) > 1);

    if ($orderSQL == '') {
        $orderSQL = @$_POST['currsort'];
    }

    if ($orderSQL != '') {
        $condition .= " ORDER BY $orderSQL";
        $currsort = $orderSQL;
    } else {
        $condition .= " ORDER BY item_id DESC";
        $currsort = "item_id DESC";
    }

    $condition .= " LIMIT ";

    if (isset($_POST['pageset']) && $_POST['pageset'] != 1) {
        $condition .= (($_POST['pageset'] - 1) * SET_ITEMS_PAGE) . ",";
    }

    $condition .= SET_ITEMS_PAGE;
    $sql = Database::sqlSelect('items', $columnArray, $condition);

    if (isset($_SESSION['item_sql']) and (@$_GET['option'] != 'add_item') and isset($_POST['search_type'])) {
        $origSQL = $_SESSION['item_sql'];
        unset($_SESSION['item_sql']);
        $origSQL = str_replace("\'", "'", $origSQL);
        $condition = ' ';
        if (isset($_POST['pageset']) && $_POST['pageset'] != 1) {
            $condition = (($_POST['pageset'] - 1) * SET_ITEMS_PAGE) . ",";
        }
        $condition .= SET_ITEMS_PAGE;
        if (strpos($origSQL, "LIMIT") > 0) {
            $origSQL = substr($origSQL, 0, strpos($origSQL, 'LIMIT')) . ' LIMIT ' . $condition;
        }
        if (isset($_POST['pageset'])) {
            $currsort = substr($origSQL, strpos($origSQL, 'ORDER BY') + 9, strpos($origSQL, 'LIMIT') - strpos($origSQL, 'ORDER BY') - 9);
        } else {
            $currsort = substr($origSQL, strpos($origSQL, 'ORDER BY') + 9, strlen($origSQL));
        }
    } else {
        $origSQL = $sql;
    }

    if (isset($_POST['currsort']) && $_POST['currsort'] != '') {
        if ($_POST['currsort'] == substr($currsort, 0, strpos($currsort, " "))) {
            $newsort = (strpos($currsort, "DESC") == 0) ? $currsort . " DESC " : $_POST['currsort'] . " ";
        } else {
            $newsort = (strpos($currsort, "DESC") == 0 && strpos($_POST['currsort'], "DESC") == 0) ? $_POST['currsort'] . " DESC " : $_POST['currsort'] . " ";
        }
        $origSQL = str_replace("ORDER BY " . $currsort, "ORDER BY " . $newsort, $origSQL);
    }

    $newsort = "";
        $result = Database::rows($origSQL);

    $listHtml = '';
    $sortLinks = '';
    if ($countrecs > 1) {
        $sortLinks = '<div>'
            . RenderViews::outputIfRoleAllowed('<a href="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_id\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_102', 'TXT_102') . '</strong></a>', $_SESSION['access_role_id'], 4)
            . ' '
            . RenderViews::outputIfRoleAllowed('<a href="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_title\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_84', 'TXT_84') . '</strong></a>', $_SESSION['access_role_id'], 4)
            . ' '
            . RenderViews::outputIfRoleAllowed('<a href="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'create_date\'; document.repform.submit()" class="URL"><strong>' . TXT_225 . '</strong></a>', $_SESSION['access_role_id'], 4)
            . ' '
            . RenderViews::outputIfRoleAllowed('<a href="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_type_id\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_226', 'TXT_226') . '</strong></a>', $_SESSION['access_role_id'], 4)
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
                $columnArray = array('item_type_name');
                $condition = "WHERE item_type_id = '" . $row['item_type_id'] . "'";
                $itemTypeRow = Database::first('item_types', $columnArray, $condition);
                $itemTypeName = $itemTypeRow['item_type_name'];
                $itemTypeID = $row['item_type_id'];
            }
            $action = RenderViews::outputIfRoleAllowed('<a href="' . MAN_BASE_URL . '&option=show_attachments&item_id=' . $row['item_id'] . '" class="URL">' . TXT_389 . '</a>', $_SESSION['access_role_id'], 4);
            $action .= RenderViews::outputIfRoleAllowed(' - <a href="' . MAN_BASE_URL . '&option=change_security&item_id=' . $row['item_id'] . '" class="URL">' . TXT_28 . '</a>', $_SESSION['access_role_id'], 3);
            $action .= RenderViews::outputIfRoleAllowed(' - <a href ="' . MAN_BASE_URL . '&option=log_entry&item_id=' . $row['item_id'] . '" class="URL">' . TXT_246 . '</a>', $_SESSION['access_role_id'], 4);
            $action .= RenderViews::outputIfRoleAllowed(' - ' . RenderViews::buildURL('index.php?controller=full_page_view&option=print_item&item_id=' . $row['item_id'], TXT_625, 'URL', '', '', '_blank'), $_SESSION['access_role_id'], 5);
            $action .= RenderViews::outputIfRoleAllowed(
                ' - ' . RenderViews::buildURL(
                    MAN_BASE_URL . '&option=delete_item&item_id=' . $row['item_id'],
                    TXT_315,
                    '',
                    'URL',
                    'onclick="' . RenderViews::confirmAttribute(TXT_400) . '"'
                ),
                $_SESSION['access_role_id'],
                2
            );
            $listHtml .= RenderViews::buildFormFieldsGrid([
                RenderViews::getLanguageConstant('LA_102', 'TXT_102') => (string)$row['item_id'],
                RenderViews::getLanguageConstant('LA_84', 'TXT_84') => '<a href ="' . MAN_BASE_URL . '&option=show_item&item_id=' . $row['item_id'] . $logEntry . $attachments . '" class="URL">' . $title . '</a>',
                TXT_225 => date(SET_DATE_FORMAT, $row['create_date']),
                RenderViews::getLanguageConstant('LA_226', 'TXT_226') => (string)$itemTypeName,
                TXT_388 => $action,
            ]);
            $listHtml .= RenderViews::buildHorizontalSeparator();
            unset($action);
        }
    } else {
        $listHtml = RenderViews::buildFormFieldsGrid(['' => htmlspecialchars(TXT_115, ENT_QUOTES, 'UTF-8')]);
    }
    $paginationHTML = '';
    $hidden = '';
    if ($countBoolean) {
        $paginationHTML = '<div class="pagination pagination-centered "><ul>';
        $pageset = (isset($_POST['pageset'])) ? $_POST['pageset'] : 1;
        if ($pageset > 1) {
            $paginationHTML .= RenderViews::outputIfRoleAllowed('<li><a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value--; document.repform.submit()">Prev</a>', $_SESSION['access_role_id'], 5);
        } else {
            $paginationHTML .= "";
        }
        $istart = max(1, ($pageset - 3));
        $iend = max(10, $pageset + 3);
        $iend = ($iend > (int)(($countrecs / SET_ITEMS_PAGE) + 1)) ? (int)($countrecs / SET_ITEMS_PAGE) + 1 : $iend;
        if ($istart > 1 && $iend - $istart < 10) {
            $istart = max($iend - 10, 1);
        }
        for ($i = $istart; $i <= $iend; $i++) {
            if ($i == $pageset) {
                $paginationHTML .= '<li class="active"><a href ="#">' . $i . '</a></li>';
            } else {
                $paginationHTML .= RenderViews::outputIfRoleAllowed('<li><a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value=' . $i . '; document.repform.submit()"> ' . $i . ' </a></li>', $_SESSION['access_role_id'], 5);
            }
        }
        if ($pageset == $iend) {
            $paginationHTML .= "";
        } else {
            $paginationHTML .= RenderViews::outputIfRoleAllowed('<li><a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value++; document.repform.submit()">Next</a></li>', $_SESSION['access_role_id'], 5);
        }
        if ($itemID == '') {
            $paginationHTML .= '</ul></div>';
            $hidden .= RenderViews::buildHiddenInput('pageset', (string)$pageset);
        }
    }
    if ($itemID == '') {
        $_SESSION['item_sql'] = $origSQL;
        $hidden .= RenderViews::buildHiddenInput('currsort', (string)$newsort);
        $hidden .= RenderViews::buildHiddenInput('search', (string)($pageset ?? ''));
        $hidden .= RenderViews::buildHiddenInput('countrecs', (string)$countrecs);
        $hidden .= RenderViews::buildHiddenInput('search_type', (string)($_GET['option'] ?? ''));
    }

    $html = RenderViews::buildForm(
        RenderViews::getLanguageConstant('LA_44', 'TXT_44'),
        'index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=my_items',
        ['' => $sortLinks . $listHtml . ($paginationHTML ?? '') . ($hidden ?? '')],
        [],
        ['name' => 'repform', 'id' => 'repform']
    );
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
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

function showItemTypes($defaultItemType = '')
{
    $fields = [];

    // Get all item types
    $condition = "WHERE enabled = 'Yes' ORDER BY item_type_name ASC";
    $result = Database::select('item_types', ['item_type_id', 'item_type_name'], $condition);

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
                            if ($_SESSION['access_role_id'] > 2) {
                                $groupRow = Database::first('groups', ['role'], 'group_id = ?', [(int) $group]);
                                if ($groupRow !== null && (string) $groupRow['role'] === '5') {
                                    $viewerGroupFound = true;
                                }
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
        showItemAdd($valueArray[0], $_POST);
        return;
    }

    if (!$noItemTypes) {
        $fields[RenderViews::getLanguageConstant('LA_66', 'TXT_66')] = RenderViews::buildSelectDropdown('item_type_id', $valueArray, $displayArray, $defaultItemType);
        define('BODY_CONTENT', RenderViews::buildForm(
            RenderViews::getLanguageConstant('LA_46', 'TXT_46'),
            MAN_BASE_URL . '&option=new_item',
            $fields,
            [RenderViews::buildFormButton('submit', 'submit_button', TXT_69)]
        ));
        RenderViews::renderThemePage('main_page_content', SET_THEME);
        return;
    }

    RenderViews::buildResponse(TXT_341);
}


/**
 * Displays the "Add Item" form for a given item type and populates values.
 *
 * This function is responsible for rendering a form to add a new item of a specific type.
 * - On the first load (when `$values['user_security']` is not set), it retrieves default values
 *   from the database for the specified item type.
 * - If the form is being reloaded (e.g., after a validation error), it uses the `$values` array
 *   (typically `$_POST` or a session-stored array) to pre-populate the form fields.
 * - The form is built using modern, semantic HTML with `RenderViews` helpers for inputs, dropdowns,
 *   and buttons, ensuring accessibility and user-friendliness.
 *
 * **Security Considerations:**
 * - The function ensures that only authorized users can access and modify certain fields based on
 *   their role.
 * - Group membership and user-specific security settings are applied to restrict access to sensitive fields.
 *
 * **Notes:**
 * - `$itemTypeID` is expected to be a valid identifier for the item type (integer or string).
 * - `$values` should be an array-like structure containing form field keys and their respective values.
 * - The function uses prepared statements for database queries to prevent SQL injection.
 *
 * @param mixed $itemTypeID Identifier for the item type (int|string).
 * @param array $values Array of submitted or default values for the form fields.
 * @return void Renders the form and exits by rendering the theme page later in the function.
 */
function showItemAdd($itemTypeID, $values)
{
    // Show values from database only if they have not been set via a form refresh (we use user_security as it is always set)
    if (!isset($values['user_security'])) {
        // Setup item type information for display and later use when the item is first created
        $columnArray = array('*');
        $condition = "WHERE item_type_id = '" . $itemTypeID . "'";
        $itemTypeFields = Database::first('item_types', $columnArray, $condition);
        if (!is_array($itemTypeFields)) {
            $itemTypeFields = [];
        }
    } else {
        // Use passed in values.  Only occurs when user comes back to incomplete form
        // as original item type is pass in from item type selection page is no more
        foreach ($values as $key => $value) {
            $itemTypeFields[$key] = stripslashes($value);
        }
    }

    // Get item type name
    $columnArray = array('item_type_name');
    $condition = "WHERE item_type_id = '" . $itemTypeID . "'";
    $row = Database::first('item_types', $columnArray, $condition);
    if (!is_array($row) || ($row['item_type_name'] ?? '') === '' || ($itemTypeFields['item_type_id'] ?? '') === '') {
        $picker = \Adlexone\Http\Router::continueUrl('items') . '&option=show_item_types';
        define('BODY_CONTENT', RenderViews::buildVerticalCards([
            [
                'title' => TXT_68,
                'html' => '<p class="record-list__empty">' . htmlspecialchars(TXT_68, ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<div class="form-actions">'
                    . RenderViews::buildURL($picker, TXT_69, '', 'btn btn--primary btn--sm')
                    . '</div>',
            ],
        ]));
        RenderViews::renderThemePage('main_page_content', SET_THEME);
        return;
    }
    $itemTypeName = $row['item_type_name'];
    if ($_SESSION['access_role_id'] <= 2) {//Admin, Inlay Admin, Global Inlay Admin have write access
        $itemRole = 2;
    } else {
        $itemRole = 5;
        $userGroupIds = GroupMembership::userGroupIds((int) ($_SESSION['access_user_id'] ?? 0));
        $typeGroupIds = GroupMembership::itemTypeGroupIds((int) ($itemTypeFields['item_type_id'] ?? 0));
        foreach (array_intersect($userGroupIds, $typeGroupIds) as $groupId) {
            $groupRow = Database::first('groups', ['role'], 'group_id = ?', [(int) $groupId]);
            if ($groupRow !== null) {
                $itemRole = ((int) $groupRow['role'] < $itemRole) ? (int) $groupRow['role'] : $itemRole;
            }
        }
    }
    if ($itemRole < 4) {//Admins and manager can set
        // If the item type does not specify a default user use the logged in user
        if (!isset($values['user_security'])) {
            if (!isset($itemTypeFields['user_security'])) {
                $itemTypeFields['user_security'] = $_SESSION['access_user_id'];
            }
            $itemTypeFields['creator_security'] = $_SESSION['access_user_id'];
        }
        // Security is setup by default from the item
        $columnArray = array('user_id', 'user_name');
        $condition = "WHERE lastactive <> 'inactive' ORDER BY user_name ASC";
        foreach (Database::select('users', $columnArray, $condition) as $row) {
            $userIDArray[] = $row['user_id'];
            $userArray[] = $row['user_name'];
        }// while
        $fields[RenderViews::getLanguageConstant('LA_551', 'TXT_551')] = RenderViews::buildSelectDropdown('creator_security', $userIDArray, $userArray, $itemTypeFields['creator_security']);
        $columnArray = array('user_id', 'user_name');
        $condition = "WHERE lastactive <> 'inactive' AND role <= " . SET_OWNER_MENU . " ORDER BY user_name ASC";
        foreach (Database::select('users', $columnArray, $condition) as $row) {
            $filterdUserIDArray[] = $row['user_id'];
            $filteredUserArray[] = $row['user_name'];
        }// while
        $fields[RenderViews::getLanguageConstant('LA_269', 'TXT_269')] = RenderViews::buildSelectDropdown('user_security', $filterdUserIDArray, $filteredUserArray, $itemTypeFields['user_security']);
    } else {
        $columnArray = array('user_name');
        $condition = "WHERE user_id ='" . $_SESSION['access_user_id'] . "'";
        $row = Database::first('users', $columnArray, $condition);
        $fields[RenderViews::getLanguageConstant('LA_551', 'TXT_551')] = RenderViews::buildHiddenInput('creator_security', $_SESSION['access_user_id']) . $row['user_name'];
        if ($itemTypeFields['user_security'] == '') {
            $columnArray = array('user_name');
            $condition = "WHERE user_id ='" . $_SESSION['access_user_id'] . "'";
            $row = Database::first('users', $columnArray, $condition);
            $fields[RenderViews::getLanguageConstant('LA_269', 'TXT_269')] = RenderViews::buildHiddenInput('user_security', $_SESSION['access_user_id']) . $row['user_name'];
        } else {
            $columnArray = array('user_name');
            $condition = "WHERE user_id ='" . $itemTypeFields['user_security'] . "'";
            $row = Database::first('users', $columnArray, $condition);
            $fields[RenderViews::getLanguageConstant('LA_269', 'TXT_269')] = RenderViews::buildHiddenInput('user_security', $itemTypeFields['user_security']) . $row['user_name'];
        }
    }
    // Create group membership list
    $groupMembership = '';
    $groupSecurity = \Adlexone\Data\GroupMembership::toDelimited(
        \Adlexone\Data\GroupMembership::itemTypeGroupIds((int) ($itemTypeFields['item_type_id'] ?? 0))
    );
    if ($groupSecurity !== '') {
        $groupArray = explode('}-{', $groupSecurity);
        $i = 0;
        $condition = '';
        foreach ($groupArray as $a) {
            if ($a === '') {
                continue;
            }
            if ($i == 0) {
                $condition = "WHERE group_id='" . $a . "'";
            } else {
                $condition .= " OR group_id='" . $a . "'";
            }
            $i++;
        }
        if ($condition !== '') {
            $columnArray = array('group_id', 'group_name');
            $result = Database::select('groups', $columnArray, $condition);
            $i = 0;
            foreach ($result as $row) {
                if ($i == 0) {
                    $groupMembership = $row['group_name'];
                } else {
                    $groupMembership .= ', ' . $row['group_name'];
                }
                $i++;
            }
        }
    }
    $fields[TXT_270] = '<div class="readonly-value">' . htmlspecialchars($groupMembership, ENT_QUOTES, 'UTF-8') . '</div>'
        . RenderViews::buildHiddenInput('group_security', $groupSecurity);
    $fields[RenderViews::getLanguageConstant('LA_84', 'TXT_84')] = RenderViews::buildTextInput('item_title', $itemTypeFields['item_title'] ?? '');    // Setup custom field display
    $columnArray = array('custom_field_id');
    $condition = "WHERE item_type_id = '" . $itemTypeID . "' ORDER BY custom_field_order ASC";
    $customFieldResult = Database::select('item_type_custom_fields', $columnArray, $condition);
    $JSValidation = array();
    $dtFormat = (SET_DATE_FORMAT == "d-m-Y, h:i A") ? ",DMY" : ",MDY";
    $JSValidation[0] = 'onclick="javascript:return fieldCheck(\'' . TXT_468 . '\',';
    $JSValidation[6] = '])"';
    $jsValFields = 0;
    $sep = 0;
    foreach ($customFieldResult as $customFields) {
        $columnArray = array('*');
        $condition = "WHERE custom_field_id = '" . $customFields['custom_field_id'] . "'";
        $row = Database::first('custom_fields', $columnArray, $condition);
        $row['field_type'] = FieldTypes::normalise($row['field_type']);
        if ($row['enabled'] != 'Yes') {
            continue;
        }
        if (($row['field_type'] == 'workerField' or $row['field_type'] == 'workerFieldMenu') and $_SESSION['access_role_id'] > 3) {
            continue;
        }
        if (MenuOptions::coveredByParentOnItemType((int)$row['custom_field_id'], (int)$itemTypeID)) {
            continue;
        }
        if (stristr($row['field_type'], 'worker')) {
            $prepend = 'worker_field_';
        } else {
            $prepend = 'custom_field_';
        }
        // Override default value if previous values have already been selected.  user_security is a POST variable and is only set when the page is submitted
        if (!isset($values['user_security'])) {
            $defaultValue = $row['default_value'];
            $subMenuFilter = $row['default_value'];
        } else {
            $defaultValue = @$itemTypeFields['custom_field_' . $row['custom_field_id']];
            $subMenuFilter = @$itemTypeFields['custom_field_' . $row['custom_field_id']];
        }

        // Add the Javascript validation back into the system
        if (($row['required'] == 'Yes' || ($row['validation_type'] != NULL && strtoupper($row['validation_type']) != 'NULL'))) {
            if ($row['validation_type'] != NULL && strtoupper($row['validation_type']) != 'NULL') {
                if ($row['validation_type'] == "date") {
                    $JSValidation[1] = (@$JSValidation[1] == '') ? "['" . $row['validation_type'] . $dtFormat . "'" : $JSValidation[1] . ",'" . $row['validation_type'] . $dtFormat . "'";
                } else {
                    if ($row['validation_type'] == "time") {
                        $JSValidation[1] = (@$JSValidation[1] == '') ? "['" . $row['validation_type'] . ",HHMMSS'" : $JSValidation[1] . ",'" . $row['validation_type'] . ",HHMMSS'";
                    } else {
                        if ($row['validation_type'] == "datetime") {
                            $JSValidation[1] = (@$JSValidation[1] == '') ? "['" . $row['validation_type'] . $dtFormat . ",HHMMSS'" : $JSValidation[1] . ",'" . $row['validation_type'] . $dtFormat . ",HHMMSS'";
                        } else {
                            $JSValidation[1] = (@$JSValidation[1] == '') ? "['" . $row['validation_type'] . "'" : $JSValidation[1] . ",'" . $row['validation_type'] . "'";
                        }
                    }
                }
            } else {
                $JSValidation[1] = (@$JSValidation[1] == '') ? "[''" : $JSValidation[1] . ",''";
            }
            $JSValidation[2] = (@$JSValidation[2] == '') ? "],['" . $prepend . $row['custom_field_id'] . "'" : $JSValidation[2] . ",'" . $prepend . $row['custom_field_id'] . "'";
            $jsValFields++;
            if ($row['required'] == 'Yes') {
                $JSValidation[4] = (@$JSValidation[4] == '') ? "],['" . TXT_517 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[4] . ",'" . TXT_517 . " (" . $row['custom_field_name'] . ")'";
                $JSValidation[5] = (@$JSValidation[5] == '') ? "],[true" : $JSValidation[5] . ",true";
            } else {
                $JSValidation[4] = (@$JSValidation[4] == '') ? "],[''" : $JSValidation[4] . ",''";
                $JSValidation[5] = (@$JSValidation[5] == '') ? "],[false" : $JSValidation[5] . ",false";
            }

            switch ($row['validation_type']) {
                case 'numeric' :
                    $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_516 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_516 . " (" . $row['custom_field_name'] . ")'";
                    break;
                case 'string' :
                    $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_518 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_518 . " (" . $row['custom_field_name'] . ")'";
                    break;
                case 'alphanumeric' :
                    $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_519 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_519 . " (" . $row['custom_field_name'] . ")'";
                    break;
                case 'date' :
                    $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . str_replace('#1', substr($dtFormat, 1), TXT_520) . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . str_replace('#1', substr($dtFormat, 1), TXT_520) . " (" . $row['custom_field_name'] . ")'";
                    break;
                case 'time' :
                    $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_521 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_521 . " (" . $row['custom_field_name'] . ")'";
                    break;
                case 'dateTime' :
                    $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . str_replace('#1', substr($dtFormat, 1), TXT_522) . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . str_replace('#1', substr($dtFormat, 1), TXT_522) . " (" . $row['custom_field_name'] . ")'";
                    break;
                case 'ip' :
                    $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_523 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_523 . " (" . $row['custom_field_name'] . ")'";
                    break;
                case 'email' :
                    $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_524 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_524 . " (" . $row['custom_field_name'] . ")'";
                    break;
                default :
                    $JSValidation[3] = (@$JSValidation[3] == '') ? "],[''" : $JSValidation[3] . ",''";
            }
        }
        switch ($row['field_type']) {
            case FieldTypes::TEXT_BOX :
                $fields[$row['custom_field_name']] = RenderViews::buildTextInput('custom_field_' . $row['custom_field_id'], $defaultValue);
                break;
            case 'password' :
                $fields[$row['custom_field_name']] = RenderViews::buildPasswordInput('custom_field_' . $row['custom_field_id'], $defaultValue);
                break;
            case 'hidden' :
                $fields[$row['custom_field_name']] = RenderViews::buildHiddenInput('custom_field_' . $row['custom_field_id'], $defaultValue);
                break;
            case FieldTypes::TEXT_AREA :
                $fields[$row['custom_field_name']] = RenderViews::buildTextArea('custom_field_' . $row['custom_field_id'], $defaultValue, SET_FORM_FIELD_HEIGHT);
                break;
            case FieldTypes::MENU :
                $childId = (int)($row['sub_menu'] ?? 0);
                $childStored = ($childId > 0) ? (string)($itemTypeFields['custom_field_' . $childId] ?? '') : '';
                $fields[$row['custom_field_name']] = MenuOptions::controls(
                    (int)$row['custom_field_id'],
                    (string)$defaultValue,
                    $childStored,
                    (is_array($values) && isset($values['user_security'])) ? $values : []
                );
                break;
            case FieldTypes::CHECK_BOX :
                $fields[$row['custom_field_name']] = RenderViews::buildCheckBox('custom_field_' . $row['custom_field_id'], $row['custom_field_id'], '');
                break;
            case 'URL' :
                $fields[$row['custom_field_name']] = RenderViews::buildTextInput('custom_field_' . $row['custom_field_id'], $defaultValue);
                break;
            case 'dynamicURL' :
                $fields[$row['custom_field_name']] = RenderViews::buildTextInput('custom_field_' . $row['custom_field_id'], $defaultValue);
                break;
            case 'workerField' :
                $fields[$row['custom_field_name']] = RenderViews::buildTextInput('worker_field_' . $row['custom_field_id'], $defaultValue);
                break;
            case 'workerFieldMenu' :
                $columnArray = array('default_value');
                $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                $defaultValueArray = Database::first('custom_fields', $columnArray, $condition);
                // Get any menus values
                $columnArray = array('menu_value');
                $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                $result = Database::select('custom_field_menu_values', $columnArray, $condition);
                // Build buildSelectDropdown array
                foreach ($result as $menuRow) {
                    $menuArray[] = $menuRow['menu_value'];
                }// while
                $fields[$row['custom_field_name']] = RenderViews::buildSelectDropdown('worker_field_menu_' . $row['custom_field_id'], @$menuArray, @$menuArray, $defaultValueArray['default_value']);
                unset($menuArray);
                break;
            default :
                break;
        }
    }

    $fields[''] = RenderViews::buildHiddenInput('item_type_id', (string)$itemTypeFields['item_type_id']);
    $formOptions = [];
    if (ADD_ATTACHMENTS == 'yes') {
        $fields[TXT_393] = RenderViews::buildFileInput('attachment', SET_MAX_ATTACHMENT * 1000000);
        $formOptions['enctype'] = 'multipart/form-data';
    }
    //Javascript field validation
    if (@$JSValidation[2] == "") {
        $jsFieldNameArray = ",['item_title']";
    } else {
        $jsFieldNameArray = $JSValidation[2] . ",'item_title'";
    }

    if (@$JSValidation[1] == "") {
        if ($jsValFields == 0) {
            $jsTestTypeArray = "['']";
        } else {
            $jsTestTypeArray = "[" . str_repeat("'',", $jsValFields) . "''";
        }
    } else {
        $jsTestTypeArray = $JSValidation[1] . ",''";
    }
    if (@$JSValidation[3] == "" || @$JSValidation[3] == ",") {
        if ($jsValFields == 0) {
            $jsErrorMsgArray = ",['']";
        } else {
            $jsErrorMsgArray = "],[" . str_repeat("'',", $jsValFields) . "''";
        }
    } else {
        $jsErrorMsgArray = $JSValidation[3] . ",''";
    }

    if (@$JSValidation[4] == "") {
        $jsRequiredMsgArray = ",['" . TXT_476 . "']";
    } else {
        $jsRequiredMsgArray = $JSValidation[4] . ",'" . TXT_476 . "'";
    }

    if (@$JSValidation[5] == "") {
        $jsRequiredArray = ",[true";
    } else {
        $jsRequiredArray = $JSValidation[5] . ",true";
    }

    $javascript = (isset($JSValidation[0])) ? $JSValidation[0] . $jsTestTypeArray . $jsFieldNameArray . $jsErrorMsgArray . $jsRequiredMsgArray . $jsRequiredArray . $JSValidation[6] : '';
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_57, $javascript);
    $bodyContent = RenderViews::buildForm(RenderViews::getLanguageConstant('LA_46', 'TXT_67') . ' — ' . $itemTypeName,MAN_BASE_URL. '&option=add_item',$fields,$buttons,$formOptions);
    define('BODY_CONTENT', $bodyContent);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Shows the item for modification.
 *
 * @param string $itemID Item ID
 */
function showItem($itemID, $values = '', $addLogEntry = 'no', $attachments = 'no')
{
    //Check to see if the item exists
    $sql = "SELECT item_id FROM items WHERE item_id='" . $itemID . "'";
        $result = Database::rows($sql);
    if (count($result) == 0) {
       RenderViews::buildResponse(TXT_616);
    } else {
        $i = userCanAccessItem((int) $itemID) ? 1 : 0;
        if ($i > 0) {//The user is allowed to access the task
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            // Load item values or use posted field values
            if (!isset($values['item_id'])) {
                // Setup item  information for display
                $columnArray = array('*');
                $condition = "WHERE item_id = '" . $itemID . "'";
                $itemFields = Database::first('items', $columnArray, $condition);
                $itemFields = ItemFields::hydrate($itemFields ?? []);
                // Build item table
            } else {
                // Use passed in values.  Only occurs when user comes back to incomplete form
                // as original item type is pass in from item type selection page is no more
                foreach ($values as $key => $value) {
                    $itemFields[$key] = stripslashes($value);
                }
            }
            // Get item type name
            $columnArray = array('item_type_name');
            $condition = "WHERE item_type_id = '" . $itemFields['item_type_id'] . "'";
            $row = Database::first('item_types', $columnArray, $condition);
            $itemTypeName = $row['item_type_name'];

            if ($_SESSION['access_role_id'] < 4) {//Managers, admins and oneorzero admins and global admins
                // Build table array
                // Security is setup by default from the item
                $columnArray = array('user_id', 'user_name');
                $condition = "ORDER BY user_name ASC";
                foreach (Database::select('users', $columnArray, $condition) as $row) {
                    $userIDArray[] = $row['user_id'];
                    $userArray[] = $row['user_name'];
                }// while
                $itemField[RenderViews::getLanguageConstant('LA_551', 'TXT_551')] = RenderViews::buildSelectDropdown('creator_security', $userIDArray, $userArray, $itemFields['creator_security']);
                $columnArray = array('user_id', 'user_name');
                $condition = "WHERE role <= " . SET_OWNER_MENU . " ORDER BY user_name ASC";
                foreach (Database::select('users', $columnArray, $condition) as $row) {
                    $filterdUserIDArray[] = $row['user_id'];
                    $filteredUserArray[] = $row['user_name'];
                }// while
                $itemField[RenderViews::getLanguageConstant('LA_269', 'TXT_269')] = RenderViews::buildSelectDropdown('user_security', $filterdUserIDArray, $filteredUserArray, $itemFields['user_security']);
            } else {
                $columnArray = array('user_name');
                $condition = "WHERE user_id = '" . $itemFields['creator_security'] . "'";
                $row = Database::first('users', $columnArray, $condition);
                $itemField[TXT_551] = RenderViews::buildHiddenInput('creator_security', $itemFields['creator_security']) . $row['user_name'];
                $columnArray = array('user_name');
                $condition = "WHERE user_id = '" . $itemFields['user_security'] . "'";
                $row = Database::first('users', $columnArray, $condition);
                $itemField[TXT_269] = RenderViews::buildHiddenInput('user_security', $itemFields['user_security']) . $row['user_name'];
            }
            $itemRole = 5;
            $userGroupIds = GroupMembership::userGroupIds((int) ($_SESSION['access_user_id'] ?? 0));
            $itemGroupIds = GroupMembership::itemGroupIds((int) $itemID);
            foreach (array_intersect($userGroupIds, $itemGroupIds) as $groupId) {
                $groupRow = Database::first('groups', ['role'], 'group_id = ?', [(int) $groupId]);
                if ($groupRow !== null) {
                    $itemRole = ((int) $groupRow['role'] < $itemRole) ? (int) $groupRow['role'] : $itemRole;
                }
            }
            $html = '';
            //Override at a user level if we have to
            if ($_SESSION['access_role_id'] <= 2) {//Admin, Inlay Admin, Global Inlay Admin and owners have write access
                $itemRole = $_SESSION['access_role_id'];
                //they are the administrator so give them administrator rights
            }
            //Set at a manager level if the user is the owner but not an administrator
            if ($_SESSION['access_user_id'] == $itemFields['user_security'] and $itemRole > 3) {
                $itemRole = 3;
                //they are owner so give them manager rights
            }
            if ($itemRole < 4) {
                // Create group membership list
                $groupArray = $itemGroupIds;
                $i = 0;
                foreach ($groupArray as $a) {
                    if ($i == 0) {
                        $condition = "WHERE group_id='" . $a . "'";
                    } else {
                        $condition .= "OR group_id='" . $a . "'";
                    }
                    $i++;
                }
                $columnArray = array('group_id', 'group_name');
                $result = Database::select('groups', $columnArray, $condition);
                $i = 0;
                $groupMembership = '';
                foreach ($result as $row) {
                    if ($i == 0) {
                        $groupMembership = $row['group_name'];
                    } else {
                        $groupMembership .= ', ' . $row['group_name'];
                    }
                    $i++;
                }// while
                $changeSecurityURL = RenderViews::buildURL(
                    'index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=change_security&item_id=' . $itemID,
                    TXT_268,
                    '',
                    'btn btn--sm'
                );
                $itemField[TXT_270] = '<div class="criteria-row"><div class="readonly-value">' . htmlspecialchars($groupMembership, ENT_QUOTES, 'UTF-8') . '</div>'
                    . $changeSecurityURL . '</div>'
                    . RenderViews::buildHiddenInput(
                        'group_security',
                        \Adlexone\Data\GroupMembership::toDelimited(
                            \Adlexone\Data\GroupMembership::itemGroupIds((int) $itemID)
                        )
                    );
            }
            if ($itemRole >= 4) {//Users and viewers can read only
                $itemField[RenderViews::getLanguageConstant('LA_84', 'TXT_84')] = RenderViews::buildHiddenInput('item_title', $itemFields['item_title']) . $itemFields['item_title'];
            } else {
                $itemField[RenderViews::getLanguageConstant('LA_84', 'TXT_84')] = RenderViews::buildTextInput('item_title', $itemFields['item_title']);
            }
            // Setup custom field display
            $columnArray = array('custom_field_id');
            $condition = "WHERE item_type_id = '" . $itemFields['item_type_id'] . "' ORDER BY custom_field_order ASC";
            $customFieldResult = Database::select('item_type_custom_fields', $columnArray, $condition);
            $JSValidation = array();
            $dtFormat = (SET_DATE_FORMAT == "d-m-Y, h:i A") ? ",DMY" : ",MDY";
            $JSValidation[0] = 'onclick="javascript:return fieldCheck(\'' . TXT_468 . '\',';
            $JSValidation[6] = '])"';
            $jsValFields = 0;
            $sep = 0;
            foreach ($customFieldResult as $customFields) {
                $columnArray = array('*');
                $condition = "WHERE custom_field_id = '" . $customFields['custom_field_id'] . "'";
                $row = Database::first('custom_fields', $columnArray, $condition);
                $row['field_type'] = FieldTypes::normalise($row['field_type']);
                if ($row['enabled'] != 'Yes') {
                    continue;
                }
                if (($row['field_type'] == 'workerField' or $row['field_type'] == 'workerFieldMenu') and $_SESSION['access_role_id'] > 3) {
                    continue;
                }
                if ($itemRole < 4 && MenuOptions::coveredByParentOnItemType((int)$row['custom_field_id'], (int)($itemFields['item_type_id'] ?? 0))) {
                    continue;
                }
                if (stristr($row['field_type'], 'worker')) {
                    $prepend = 'worker_field_';
                } else {
                    $prepend = 'custom_field_';
                }
                // Override database value if values have already been selected
                if (!isset($values['item_id'])) {
                    $value = @$itemFields['custom_field_' . $row['custom_field_id']];
                    $menuValue = @$itemFields['custom_field_' . $row['custom_field_id']];
                } else {
                    $value = @stripslashes($values['custom_field_' . $row['custom_field_id']]);
                    $menuValue = @$values['custom_field_' . $row['custom_field_id']];
                }

                // Add the Javascript validation back into the system
                if (($row['required'] == 'Yes' || $row['validation_type'] != NULL)) {

                    if ($row['validation_type'] != NULL) {
                        if ($row['validation_type'] == "date") {
                            $JSValidation[1] = (@$JSValidation[1] == '') ? "['" . $row['validation_type'] . $dtFormat . "'" : $JSValidation[1] . ",'" . $row['validation_type'] . $dtFormat . "'";
                        } else {
                            if ($row['validation_type'] == "time") {
                                $JSValidation[1] = (@$JSValidation[1] == '') ? "['" . $row['validation_type'] . ",HHMMSS'" : $JSValidation[1] . ",'" . $row['validation_type'] . ",HHMMSS'";
                            } else {
                                if ($row['validation_type'] == "datetime") {
                                    $JSValidation[1] = (@$JSValidation[1] == '') ? "['" . $row['validation_type'] . $dtFormat . ",HHMMSS'" : $JSValidation[1] . ",'" . $row['validation_type'] . ",HHMMSS'";
                                } else {
                                    $JSValidation[1] = (@$JSValidation[1] == '') ? "['" . $row['validation_type'] . "'" : $JSValidation[1] . ",'" . $row['validation_type'] . "'";
                                }
                            }
                        }
                    } else {
                        $JSValidation[1] = (@$JSValidation[1] == '') ? "[''" : $JSValidation[1] . ",''";
                    }

                    $JSValidation[2] = (@$JSValidation[2] == '') ? "],['" . $prepend . $row['custom_field_id'] . "'" : $JSValidation[2] . ",'" . $prepend . $row['custom_field_id'] . "'";
                    $jsValFields++;

                    if ($row['required'] == 'Yes') {
                        $JSValidation[4] = (@$JSValidation[4] == '') ? "],['" . TXT_517 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[4] . ",'" . TXT_517 . " (" . $row['custom_field_name'] . ")'";
                        $JSValidation[5] = (@$JSValidation[5] == '') ? "],[true" : $JSValidation[5] . ",true";
                    } else {
                        $JSValidation[4] = (@$JSValidation[4] == '') ? "],[''," : $JSValidation[4] . ",''";
                        $JSValidation[5] = (@$JSValidation[5] == '') ? "],[false" : $JSValidation[5] . ",false";
                    }

                    switch ($row['validation_type']) {
                        case 'numeric' :
                            $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_516 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_516 . " (" . $row['custom_field_name'] . ")'";
                            break;
                        case 'string' :
                            $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_518 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_518 . " (" . $row['custom_field_name'] . ")'";
                            break;
                        case 'alphanumeric' :
                            $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_519 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_519 . " (" . $row['custom_field_name'] . ")'";
                            break;
                        case 'date' :
                            $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . str_replace('#1', substr($dtFormat, 1), TXT_520) . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . str_replace('#1', substr($dtFormat, 1), TXT_520) . " (" . $row['custom_field_name'] . ")'";
                            break;
                        case 'time' :
                            $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_521 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_521 . " (" . $row['custom_field_name'] . ")'";
                            break;
                        case 'dateTime' :
                            $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . str_replace('#1', substr($dtFormat, 1), TXT_522) . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . str_replace('#1', substr($dtFormat, 1), TXT_522) . " (" . $row['custom_field_name'] . ")'";
                            break;
                        case 'ip' :
                            $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_523 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_523 . " (" . $row['custom_field_name'] . ")'";
                            break;
                        case 'email' :
                            $JSValidation[3] = (@$JSValidation[3] == '') ? "],['" . TXT_524 . " (" . $row['custom_field_name'] . ")'" : $JSValidation[3] . ",'" . TXT_524 . " (" . $row['custom_field_name'] . ")'";
                            break;
                        default :
                            $JSValidation[3] = (@$JSValidation[3] == '') ? "],[''" : $JSValidation[3] . ",''";
                    }
                }

                if (($itemRole >= 4) and ($row['field_type'] != 'fieldSeparator')) {//Users and viewers can read only so we override the field type
                    if ($row['field_type'] != FieldTypes::TEXT_AREA) {
                        $hiddenValue = $value;
                        $fieldType = 'text';
                    } else {
                        $hiddenValue = $value;
                        $value = str_replace("\n", "<br />", $value);
                        $value = '<br />' . $value . '<br /><br />';
                        $fieldType = 'text';
                    }
                } else {
                    $fieldType = $row['field_type'];
                }
                switch ($fieldType) {
                    case 'text' :
                        $itemField[$row['custom_field_name']] = $value . RenderViews::buildHiddenInput('custom_field_' . $row['custom_field_id'], $hiddenValue);
                        break;
                    case FieldTypes::TEXT_BOX :
                        $itemField[$row['custom_field_name']] = RenderViews::buildTextInput('custom_field_' . $row['custom_field_id'], $value);
                        break;
                    case 'password' :
                        $itemField[$row['custom_field_name']] = RenderViews::buildPasswordInput('custom_field_' . $row['custom_field_id'], $value);
                        break;
                    case 'hidden' :
                        $itemField[$row['custom_field_name']] = RenderViews::buildHiddenInput('custom_field_' . @$row['custom_field_id'], $value);
                        break;
                    case FieldTypes::TEXT_AREA :
                        $itemField[$row['custom_field_name']] = RenderViews::buildTextArea('custom_field_' . $row['custom_field_id'], $value, SET_FORM_FIELD_HEIGHT);
                        break;
                    case FieldTypes::MENU :
                        $childId = (int)($row['sub_menu'] ?? 0);
                        $childStored = $childId > 0
                            ? (string)((is_array($values) && isset($values['item_id']))
                                ? ($values['custom_field_' . $childId] ?? '')
                                : ($itemFields['custom_field_' . $childId] ?? ''))
                            : '';
                        $itemField[$row['custom_field_name']] = MenuOptions::controls(
                            (int)$row['custom_field_id'],
                            (string)$menuValue,
                            $childStored,
                            (is_array($values) && isset($values['item_id'])) ? $values : []
                        );
                        break;

                    case FieldTypes::CHECK_BOX :
                        $itemField[$row['custom_field_name']] = RenderViews::buildCheckBox('custom_field_' . $row['custom_field_id'], $value, $row['custom_field_id'], 'form-control');
                        break;
                    case 'URL' :
                        if ($value != '') {
                            $http = (!stristr($value, 'http') and !stristr($value, 'https')) ? 'http://' : '';
                            $url = ' ' . RenderViews::buildURL($http . $value, TXT_480, 'URL', SET_IMAGE_PATH . 'hyperlink.png', '', '_blank');
                        }
                        $itemField[$row['custom_field_name']] = RenderViews::buildTextInput('custom_field_' . $row['custom_field_id'], $value) . @$url;
                        break;
                    case 'dynamicURL' :
                        if ($value != '') {
                            $url = ' ' . RenderViews::buildURL(str_replace('<?php echo INSERT; ?>', $value, $row['data']), TXT_480, 'URL', SET_IMAGE_PATH . 'hyperlink.png', '', '_blank');
                        }
                        $itemField[$row['custom_field_name']] = RenderViews::buildTextInput('custom_field_' . $row['custom_field_id'], $value) . @$url;
                        break;
                    case 'workerField' :
                        $itemField[$row['custom_field_name']] = RenderViews::buildTextInput('worker_field_' . $row['custom_field_id'], $value);
                        break;
                    case 'workerFieldMenu' :
                        $columnArray = array('default_value');
                        $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                        $defaultValueArray = Database::first('custom_fields', $columnArray, $condition);
                        // Get any menus values
                        $columnArray = array('menu_value');
                        $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                        $result = Database::select('custom_field_menu_values', $columnArray, $condition);
                        // Build buildSelectDropdown array
                        foreach ($result as $menuRow) {
                            $menuArray[] = $menuRow['menu_value'];
                        }// while
                        $itemField[$row['custom_field_name']] = RenderViews::buildSelectDropdown('worker_field_menu_' . $row['custom_field_id'], @$menuArray, @$menuArray, $defaultValueArray['default_value']);
                        unset($menuArray);
                        break;
                    default :
                        break;
                }
            }

            //Javascript field validation
            if (@$JSValidation[2] == "") {
                $jsFieldNameArray = "['item_title']";
            } else {
                $jsFieldNameArray = $JSValidation[2] . ",'item_title'";
            }

            if (@$JSValidation[1] == "") {
                if ($jsValFields == 0) {
                    $jsTestTypeArray = "['']";
                } else {
                    $jsTestTypeArray = "[" . str_repeat("'',", $JSValFields) . "''";
                }
            } else {
                $jsTestTypeArray = $JSValidation[1] . ",''";
            }

            if (@$JSValidation[3] == "" || $JSValidation[3] == ",") {
                if ($jsValFields == 0) {
                    $jsErrorMsgArray = ",['']";
                } else {
                    $jsErrorMsgArray = "],[" . str_repeat("'',", $jsValFields) . "''";
                }
            } else {
                $jsErrorMsgArray = $JSValidation[3] . ",''";
            }

            if (@$JSValidation[4] == "") {
                $jsRequiredMsgArray = "['" . TXT_476 . "']";
            } else {
                $jsRequiredMsgArray = $JSValidation[4] . ",'" . TXT_476 . "'";
            }

            if (@$JSValidation[5] == "") {
                $jsRequiredArray = "[true]";
            } else {
                $jsRequiredArray = $JSValidation[5] . ",true";
            }
            $javascript = (isset($JSValidation[0])) ? $JSValidation[0] . $jsTestTypeArray . ',' . $jsFieldNameArray . ',' . $jsErrorMsgArray . ',' . $jsRequiredMsgArray . ',' . $jsRequiredArray . $JSValidation[6] : '';
            if ($_SESSION['access_role_id'] <= 4) {
                $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_56, $javascript);
            }

            $itemField[''] = RenderViews::buildHiddenInput('item_type_id', $itemFields['item_type_id']).RenderViews::buildHiddenInput('item_id', $itemFields['item_id']);
            $html .= RenderViews::buildForm(RenderViews::getLanguageConstant('LA_102', 'TXT_102') . ': ' . $itemFields['item_id'] . ' - ' . $itemTypeName,MAN_BASE_URL . '&option=update_item',$itemField,$buttons);

            //Show transform option
            if ($_SESSION['access_role_id'] <= 2) {//Administrator, Adlexone Administrator or Global Administrator
                // Administrative functions
                $columnArray = array('item_type_id', 'item_type_name');
                $result = Database::select('item_types', $columnArray);
                if (count($result) > 0) {
                    foreach ($result as $row) {
                        $valueArray[] = $row['item_type_id'];
                        $displayArray[] = $row['item_type_name'];
                    }
                } else {
                    $valueArray[] = '';
                    $displayArray[] = TXT_341;
                }
                $html .= '<br />
';
                $itemTypeMenu = RenderViews::buildSelectDropdown('item_type_id', $valueArray, $displayArray, '');
                $transformationTypeMenu = RenderViews::buildSelectDropdown('transform_type', array('copy', 'transform'), array(TXT_443, TXT_444), '');
                $hiddenItemID = RenderViews::buildHiddenInput('item_id', $itemID);
                $transformButton[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_446,  'onClick="javascript:return confirm(\'' . TXT_448 . '\')"');

                $transinputArray[TXT_445] = $transformationTypeMenu;
                $transinputArray[TXT_447] = $itemTypeMenu . $hiddenItemID;
                $html .= RenderViews::buildForm(TXT_446,MAN_BASE_URL . '&option=transform_item',$transinputArray,$transformButton);
            }

            if ($itemID != '' && defined('APP_COUNT_ITEM_TYPE') && $itemFields['item_type_id'] == APP_COUNT_ITEM_TYPE) {
                updateKnoweldgeCount(array($itemID));
            }

            define('BODY_CONTENT', $html);
            RenderViews::renderThemePage('main_page_content', SET_THEME);
        } else {
            // Not allowed
            RenderViews::buildResponse(TXT_452);
        }
    }
}

/**
 * Takes information posted from the new item page and adds the item to the database then renders a succcess page
 * @return Rendered HTML response
 */
function addItem()
{
    // Return to the form if dynamic actions are still occurring, such as sub buildSelectDropdown selection etc
    if (!isset($_POST['item_type_id'])) {
        // halt adding the item as we may have shortcutted here
        RenderViews::buildResponse(TXT_535);
        return;
    } elseif (!isset($_POST['submit_button'])) {
        // Reload form setting values based on posted form values, triggered from javascript submits
        showItemAdd($_POST['item_type_id'], $_POST);
    } else {
        if (ADD_ATTACHMENTS == 'yes' && ($_FILES['attachment']['name'] ?? '') != '') {
            // Item row must exist before attachment metadata references it.
        }
        $array = [];
        $array['create_date'] = time();
        $array['core_log_updated'] = time();
        unset($_POST['submit_button'], $_POST['reset'], $_POST['MAX_FILE_SIZE'], $_POST['attachment'], $_POST['item_id']);
        MenuOptions::collapseRequest($_POST);
        $dbArray = [];
        foreach ($_POST as $key => $value) {
            if (!stristr($key, 'worker_field') and !stristr($key, 'worker_field_menu')) {
                $dbArray[$key] = $value;
            }
        }
        $insertArray = array_merge($array, $dbArray);
        $fieldPayload = $insertArray;
        $insertArray = ItemFields::withoutFieldColumns($insertArray);
        $itemId = Database::insert('items', $insertArray);
        ItemFields::saveFromArray($itemId, $fieldPayload);
        GroupMembership::setItemGroups(
            $itemId,
            GroupMembership::parseDelimited((string) ($fieldPayload['group_security'] ?? ''))
        );
        if (ADD_ATTACHMENTS == 'yes' && ($_FILES['attachment']['name'] ?? '') != '') {
            addAttachment($itemId, false, false);
            Actions::executeAction($itemId, 'item_attachment', false);
        }
        Actions::executeAction($itemId, 'create_item', false);
        header('Location: ' . MAN_BASE_URL . '&option=show_item&item_id=' . rawurlencode((string) $itemId));
        exit;
    }
}

/**
 * Select target item type to copies or move an item to
 * s
 * @param $itemID Item ID
 * @param $transformMethod Copy or Move
 * @return unknown_type
 */
function transformItem($itemID, $transformType, $targetItemTypeID)
{
    switch ($transformType) {
        case 'transform' :
            //Alter existing item to new item type (retains old field data if required)
            $columnArray['item_type_id'] = $targetItemTypeID;
            $condition = "WHERE item_id = '" . $itemID . "'";
            Database::update('items', $columnArray, $condition);
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            RenderViews::buildResponse(TXT_450, RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments, TXT_262, 'URL'));
            return;

        case 'copy' :
            //Copies existing data to new item
            //Get existing data
            $columnArray = array('*');
            $condition = "WHERE item_id = '" . $itemID . "'";
            $row = Database::first('items', $columnArray, $condition);
            $row = ItemFields::hydrate($row ?? []);
            //Create new item based on existing data
            foreach ($row as $key => $value) {
                if (!is_integer($key)) {//ignore integer keys in array
                    $newItemColumnArray[$key] = addslashes($value);
                }
            }
            unset($newItemColumnArray['item_id']);
            $newItemColumnArray = ItemFields::withoutFieldColumns($newItemColumnArray);
            $newItemColumnArray['item_type_id'] = $targetItemTypeID;
            $newId = Database::insert('items', $newItemColumnArray);
            ItemFields::copyItem((int) $itemID, $newId);
            GroupMembership::setItemGroups($newId, GroupMembership::itemGroupIds((int) $itemID));
            $newItemColumnArray['item_id'] = $newId;
            //Copy log entries
            $logColumnArray = array('*');
            $condition = "WHERE item_id = '" . $itemID . "'";
            foreach (Database::select('core_log', $logColumnArray, $condition) as $row) {
                //Create new log entries based on existing data
                foreach ($row as $key => $value) {
                    if (!is_integer($key)) {//ignore integer keys in array
                        $newLogColumnArray[$key] = addslashes($value);
                    }
                }
                unset($newLogColumnArray['id']);
                $newLogColumnArray['item_id'] = $newId;
                Database::insert('core_log', $newLogColumnArray);
            }
            //Copy attachment entries
            $attachmentColumnArray = array('*');
            $condition = "WHERE item_id = '" . $itemID . "'";
            foreach (Database::select('item_attachments', $attachmentColumnArray, $condition) as $row) {
                //Create new attachment entries based on existing data
                foreach ($row as $key => $value) {
                    if (!is_integer($key)) {//ignore integer keys in array
                        $newAttachmentColumnArray[$key] = $value;
                    }
                }
                unset($newAttachmentColumnArray['id']);
                $newAttachmentColumnArray['item_id'] = $newItemColumnArray['item_id'];
                Database::insert('item_attachments', $newAttachmentColumnArray);
            }
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            RenderViews::buildResponse(TXT_392, RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $newItemColumnArray['item_id'] . $logEntry . $attachments, TXT_262, 'URL'));
            return;
    }

    //Copy to another item

    //Tranform to another item type

}

function updateItem($itemID)
{
    // Return to the form if dynamic actions are still occurring, such as sub buildSelectDropdown selection etc
    if (!isset($_POST['item_type_id'])) {
        // halt adding the item as we may have shortcutted here
        RenderViews::buildResponse(TXT_535);
        return;
    } elseif (!isset($_POST['submit_button'])) {
        // Reload form setting values based on posted form values, triggered from javascript submits
        showItem($_POST['item_id'], $_POST, $_GET['log_entry'] ?? 'no', $_GET['attachments'] ?? 'no');
    } else {
        // Remove unwanted posted information
        unset($_POST['submit_button'], $_POST['reset'], $_POST['item_id']);
        MenuOptions::collapseRequest($_POST);
        // Handle the log update
        if (isset($_POST['log_entry']) and $_POST['log_entry'] != '') {
            addLogEntry($itemID, false, false);
        }
        // Execute actions
        Actions::executeAction($itemID, 'update_item', false);
        unset($_POST['log_entry'], $_POST['role_id']);
        // Remove worker fields
        foreach ($_POST as $key => $value) {
            //Don't insert work field values - they aren't persistent
            if (stristr($key, 'worker_field') or stristr($key, 'worker_field_menu')) {
                unset($_POST[$key]);
            }
        }
        // Update system fields, they are the only remaining POST variables
        $condition = "WHERE item_id='$itemID'";
        $fieldPayload = $_POST;
        Database::update('items', ItemFields::withoutFieldColumns($_POST), $condition);
        ItemFields::saveFromArray((int) $itemID, $fieldPayload);
        if (isset($fieldPayload['group_security'])) {
            syncItemGroupsFromSecurity((int) $itemID, (string) $fieldPayload['group_security']);
        }
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        $message = ($_POST['item_title'] != '') ? TXT_263 : TXT_272;
        RenderViews::buildResponse($message, RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments, TXT_262, 'URL'));
        return;
    }
}

/**
 * Creates form for adding new log entry and displays existing log.
 *
 * @param integer $itemID Item ID
 */
function showLogEntry($itemID)
{
    $showLog = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $backHref = MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $showLog . $attachments;
    $logField[RenderViews::getLanguageConstant('LA_244', 'TXT_244')] = RenderViews::buildTextArea('log_entry', '', SET_FORM_FIELD_HEIGHT);
    $roleIDs = array(2, 3, 4, 5);
    $roleNames = array(TXT_192, TXT_193, TXT_194, TXT_303);
    $logField[TXT_469] = '<div>' . RenderViews::buildSelectDropdown('role_id', $roleIDs, $roleNames, 5)
        . '<div class="field-picker-type">' . htmlspecialchars(TXT_470, ENT_QUOTES, 'UTF-8') . '</div></div>';

    $columnArray = array('*');
    $condition = "WHERE item_id = '" . $itemID . "'";
    $result = Database::select('items', $columnArray, $condition);
    $hidden = '';
    foreach ($result as $row) {
        foreach ($row as $key => $value) {
            if (!is_numeric($key) and $value != '') {
                $hidden .= RenderViews::buildHiddenInput($key, $value);
            }
        }
    }
    $logField[' '] = $hidden;
    define('BODY_CONTENT', RenderViews::buildForm(
        RenderViews::getLanguageConstant('LA_243', 'TXT_243'),
        MAN_BASE_URL . '&option=add_log_entry',
        $logField,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_74),
            '<a class="btn" href="' . htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(TXT_385, ENT_QUOTES, 'UTF-8') . '</a>',
        ]
    ) . showItemLog($itemID));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Adds the log entry to the database.
 *
 * @param integer $itemID Item ID
 */
function addLogEntry($itemID, $showInformation = true, $executeAction = true)
{
    // Remove unwanted form variables
    unset($_POST['submit_button'], $_POST['reset']);
    // Get new log sequence (id is AUTOINCREMENT)
    $condition = "WHERE item_id='$itemID'";
    $SequenceID = Database::newID('core_log', 'log_item_sequence', $condition);
    // Add log entry
    $columnArray['item_id'] = $itemID;
    $columnArray['create_date'] = time();
    $columnArray['item_identifier'] = 0;
    //0 Reserved for item log update
    $columnArray['log_item_sequence'] = $SequenceID;
    $columnArray['log_text'] = $_POST['log_entry'];
    $columnArray['security_id'] = $_SESSION['access_user_id'];
    $columnArray['role_id'] = $_POST['role_id'];
    // Insert into log table
    Database::insert('core_log', $columnArray);
    // Update entry in item table
    $condition = "WHERE item_id = '$itemID'";
    $itemColumnArray['core_log_updated'] = $columnArray['create_date'];
    Database::update('items', $itemColumnArray, $condition);
    if ($executeAction == true) {
        // Execute actions
        Actions::executeAction($itemID, 'update_item_log_entry', false);
    }
    if ($showInformation == true) {
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        RenderViews::buildResponse(TXT_248, RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments, TXT_262, 'URL'));
        return;
    }
}

/**
 * Shows the selected items log
 *
 * @param integer $itemID Item ID
 * @return Return the log in html format
 */
// Refactored snippet: render logs into a vertical card (escape user content, keep line breaks & double-spaces)
function showItemLog($itemID)
{
    $itemsHtml = '';
    $columnArray = ['*'];
    $condition = "WHERE item_id = :item_id ORDER BY log_item_sequence DESC";
    $result = Database::select('core_log', $columnArray, $condition, ['item_id' => $itemID]);

    foreach ($result as $row) {
        if ($_SESSION['access_role_id'] <= $row['role_id']) {
            $userName = '';
            if ((string)$row['security_id'] !== '') {
                $userResult = Database::select('users', ['user_name'], 'WHERE user_id = ' . (int)$row['security_id']);
                $userRow = $userResult[0] ?? null;
                $userName = is_array($userRow) ? (string)($userRow['user_name'] ?? '') : '';
            }
            $role = match ((int)$row['role_id']) {
                0 => TXT_190,
                1 => TXT_191,
                2 => TXT_192,
                3 => TXT_193,
                4 => TXT_194,
                5 => TXT_303,
                default => ''
            };

            $heading = '<div class="log-heading"><strong>'
                . htmlspecialchars(date(SET_DATE_FORMAT, (int)$row['create_date']), ENT_QUOTES, 'UTF-8')
                . ' ' . htmlspecialchars((string)TXT_260, ENT_QUOTES, 'UTF-8') . ' '
                . htmlspecialchars((string)$userName, ENT_QUOTES, 'UTF-8')
                . ($role !== '' ? ' (' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . ')' : '')
                . '</strong></div>';

            // Sanitize and format log text
            $text = htmlspecialchars((string)$row['log_text'], ENT_QUOTES, 'UTF-8');
            $text = nl2br($text);
            $text = str_replace('  ', '&nbsp;&nbsp;', $text);

            $itemsHtml .= '<div class="log-item">' . $heading . '<div class="log-text">' . $text . '</div></div>';
        }
    }

    if ($itemsHtml === '') {
        $itemsHtml = '<p class="record-list__empty">' . htmlspecialchars(TXT_115, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $blocks = [
        ['title' => TXT_146, 'html' => $itemsHtml]
    ];

    return RenderViews::buildVerticalCards($blocks);
}

function showCustomFields(): string
{
    // Load all custom fields once
    $columnArray = ['custom_field_id', 'custom_field_name'];
    $condition = ' ORDER BY custom_field_name ASC';
    $result = Database::select('custom_fields', $columnArray, $condition);

    $rows = [];
    foreach ($result as $r) {
        $rows[] = $r;
    }

    $fields = [];
    for ($a = 0; $a < SET_CUSTOM_FIELDS_IN_SEARCH; $a++) {
        // Build select options
        $listValues = [0 => ''];
        $listDisplayValues = [0 => TXT_108];
        foreach ($rows as $r) {
            $id = $r['custom_field_id'] ?? $r[0] ?? '';
            $name = $r['custom_field_name'] ?? $r[1] ?? '';
            $listValues[] = $id;
            $listDisplayValues[] = $name;
        }

        $customFieldList = RenderViews::buildSelectDropdown('custom_field_' . $a, $listValues, $listDisplayValues, '');
        $customFieldTextBox = RenderViews::buildTextInput('custom_field_value_' . $a);
        $andOrDisplayValues = [TXT_109, TXT_110];
        $andOr = ['AND', 'OR'];
        $operatorValues = ['=', 'LIKE', '<>', '>', '<'];
        $operatorDisplayValues = [TXT_111, TXT_112, TXT_318, TXT_364, TXT_365];
        $customFieldAndOr = RenderViews::buildSelectDropdown('custom_field_andor_' . $a, $andOr, $andOrDisplayValues, 'AND');
        $customFieldOperator = RenderViews::buildSelectDropdown('custom_field_operator_' . $a, $operatorValues, $operatorDisplayValues, '=');

        // Compose a three-column row using flex; inner HTML comes from trusted RenderViews helpers
        $elementHtml = '<div class="cf-row" style="display:flex;gap:0.5rem;align-items:center;">'
            . '<div style="flex:0 0 30%;">' . $customFieldList . '</div>'
            . '<div style="flex:0 0 40%;">' . $customFieldOperator . '&nbsp;' . $customFieldTextBox . '</div>'
            . '<div style="flex:0 0 30%;">' . $customFieldAndOr . '</div>'
            . '</div>';

        // Use an invisible, unique label so the grid prints the element without a visible label
        $labelKey = "\u{200B}" . $a;
        $fields[$labelKey] = $elementHtml;
    }

    // Hidden count field (use invisible label to avoid visible label)
    $fields["\u{200B}count"] = RenderViews::buildHiddenInput('custom_field_count', (string)$a);

    // Return a modern form block containing just the custom-field rows
    return RenderViews::buildForm('', '#', $fields, []);
}

function showUsers()
{
    // Grab data from database
    $sql = "select user_id, user_name FROM users ORDER BY user_name";
        $result = Database::rows($sql);
    // Set the selected option to 'All item definitions'
    $listValues[0] = '';
    $listDisplayValues[0] = TXT_105;
    $i = 1;
    foreach ($result as $row) {
        $listValues[$i] = $row[0];
        $listDisplayValues[$i] = $row[1];
        $i++;
    }
    // Create the list box
    return RenderViews::buildSelectDropdown('user_security', $listValues, $listDisplayValues, '');
}

function showSecurityGroups()
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
        $listValues[$i] = $row[0];
        $listDisplayValues[$i] = $row[1];
        $i++;
    }
    // Create the list box
    return RenderViews::buildSelectDropdown('security_groups', $listValues, $listDisplayValues, '');
}

function showItemTypeMenu($filterArray = '', $showAll = false)
{
    // Get all item item definitions from database
    $columnArray = array('item_type_id', 'item_type_name');
    $result = Database::select('item_types', $columnArray);
    // Set the selected option to 'All item definitions'
    $i = 0;
    if ($showAll == true) {
        $listValues[0] = '';
        $listDisplayValues[0] = TXT_100;
        $i = 1;
    }
    $a = 0;
    // Display all item types or only a subset based on the filter array
    foreach ($result as $row) {
        if ($filterArray != '') {
            if (trim($filterArray[$a]) == $row[1]) {
                $listValues[$i] = $row[0];
                $listDisplayValues[$i] = $row[1];
            }
        } else {
            $listValues[$i] = $row[0];
            $listDisplayValues[$i] = $row[1];
        }
        $a++;
        $i++;
    }
    // Create the list box
    return RenderViews::buildSelectDropdown('item_type_id', $listValues, $listDisplayValues, '');
}

/**
 * deleteLog()
 *
 * Deletes the selected log item.
 */
function deleteLog($itemID, $logItemSequence)
{
    // Setup log sql and execute query
    $condition = "WHERE item_id='" . $itemID . "' AND log_item_sequence='" . $logItemSequence . "'";
    Database::delete('core_log', $condition);
    showLogEntry($itemID);
}

function showSecurityAssignment(string $itemID): void
{
    // Build form action
    $action = MAN_BASE_URL . '&option=update_security&item_id=' . $itemID;

    $groupArray = array_map('strval', \Adlexone\Data\GroupMembership::itemGroupIds((int) $itemID));

    // Fetch all groups (ordered)
    $sql = "SELECT group_id, group_name, description FROM groups ORDER BY group_name";
        $result = Database::rows($sql);

    $list = '<div class="group-security-list">';
    foreach ($result as $row) {
        $groupId = (string)$row['group_id'];
        $groupName = (string)$row['group_name'];
        $description = (string)($row['description'] ?? '');
        $selectedValue = in_array($groupId, $groupArray, true) ? $groupId : '';
        $list .= '<div class="group-security-item">'
            . RenderViews::buildCheckBox($groupId, $groupId, $selectedValue, 'checkbox', $groupName);
        if ($description !== '') {
            $list .= '<div class="record-list__meta">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $list .= '</div>';
    }
    $list .= '</div>';
    $fields = [TXT_270 => $list];

    // Buttons
    $buttons = [
        RenderViews::buildFormButton('submit', 'submit_button', TXT_74),
        RenderViews::buildFormButton('reset', 'reset', TXT_75),
    ];

    // Render the form inside a vertical card (title uses the same heading constant)
    $html = RenderViews::buildForm(TXT_322, $action, $fields, $buttons);

    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function updateSecurityAssignment(string $itemID): void
{
    // Remove unwanted POST variables
    unset($_POST['submit_button'], $_POST['reset']);

    // Collect selected group values, trim and ignore empty entries
    $selected = array_values(array_filter($_POST, fn($v) => trim((string)$v) !== ''));
    $ids = array_map(static fn($v) => (int) $v, $selected);

    // Execute any configured actions for this update
    Actions::executeAction($itemID, 'update_item', false);

    try {
        \Adlexone\Data\GroupMembership::setItemGroups((int) $itemID, $ids);
    } catch (\Throwable $e) {
        // Render an error response (keeps behaviour simple and user-friendly)
        RenderViews::buildResponse(TXT_321 ?? 'Update failed', $e->getMessage());
        return;
    }

    // Build return URL and render success response via the modern helper
    $logEntry   = (SET_LOG_ENTRY === 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS === 'yes') ? '&attachments=yes' : '';
    $url = MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments;

    RenderViews::buildResponse(TXT_320, RenderViews::buildURL($url, TXT_353, 'URL'));
}

function showMyItems($userID, $itemID = '')
{
    $uid = (int) ($_SESSION['access_user_id'] ?? 0);
    $userItemArray = Database::select('items', ['item_id'], 'user_security = ? OR creator_security = ?', [$uid, $uid]);
    $groupItemArray = array_map(
        static fn(int $id): array => ['item_id' => $id],
        \Adlexone\Data\GroupMembership::itemIdsForUser($uid)
    );
    $mergedArray = array_merge($groupItemArray, $userItemArray);
    $itemArray = array_unique($mergedArray, SORT_REGULAR);
    showItems($itemArray, $itemID, 'item_id DESC');
}

function addAttachment($itemID, $showAttachments = true, $executeAction = true)
{
    // Upload the attachment
    $fileArray = File::uploadItemAttachment(SET_MAX_ATTACHMENT, 'attachment', time(), SET_ATTACHMENTS_PATH);
    // Add attachment
    $columnArray['item_id'] = $itemID;
    $columnArray['create_date'] = time();
    $columnArray['file_name'] = $fileArray['time_name'];
    $columnArray['file_type'] = $fileArray['type'];
    $columnArray['file_size'] = $fileArray['size'];
    $columnArray['added_by'] = $_SESSION['access_user_id'];
    // Insert into attachment table
    Database::insert('item_attachments', $columnArray);
    // Execute actions

    if (($columnArray['file_size'] > 0) && ($executeAction == true)) {
        if (!isset($_POST['creator_security'])) {
            $sql = "SELECT * from items WHERE item_id = '" . $itemID . "'";
                        $result = Database::rows($sql);
            $row = $result[0] ?? null;
            foreach ($row as $key => $value) {
                if (!is_int($key)) {
                    $_POST[$key] = $value;
                }
            }
        }
        Actions::executeAction($itemID, 'item_attachment', false);
    }

    if ($showAttachments) {
        showAttachments($itemID);
    }
}

function showAttachments($itemID)
{
    $canDelete = $_SESSION['access_role_id'] <= 2;
    $sql = "SELECT * FROM item_attachments WHERE item_id = '$itemID'";
        $result = Database::rows($sql);
    $rows = [];
    foreach ($result as $row) {
        $actions = [];
        if ($canDelete) {
            $actions[] = [
                'href' => MAN_BASE_URL . '&option=delete_attachment&item_id=' . $itemID . '&id=' . $row['id'],
                'label' => TXT_47,
                'tone' => 'danger',
                'confirm' => TXT_400,
            ];
        }
        $rows[] = [
            'name' => str_replace('_', ' ', substr((string)$row['file_name'], 11)),
            'href' => MAN_BASE_URL . '&option=download_attachment&id=' . $row['id'],
            'cells' => [
                'type' => (string)$row['file_type'],
                'size' => (string)round(((int)$row['file_size']) / 1000),
                'opened' => date(SET_DATE_FORMAT, (int)$row['create_date']),
            ],
            'actions' => $actions,
        ];
    }
    $list = RenderViews::buildRecordList([
        'column' => TXT_394,
        'columns' => [
            ['key' => 'type', 'label' => TXT_395],
            ['key' => 'size', 'label' => TXT_396],
            ['key' => 'opened', 'label' => TXT_397],
        ],
        'empty' => TXT_399,
        'groups' => [['rows' => $rows]],
    ]);

    $showLog = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $backHref = MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $showLog . $attachments;
    $fields = [
        '' => $list,
        TXT_393 => RenderViews::buildFileInput('attachment', (string)(SET_MAX_ATTACHMENT * 1000000)),
    ];
    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_389,
        MAN_BASE_URL . '&option=add_attachment&item_id=' . $itemID,
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_74),
            '<a class="btn" href="' . htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(TXT_385, ENT_QUOTES, 'UTF-8') . '</a>',
        ],
        ['enctype' => 'multipart/form-data']
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function downloadAttachment($id)
{
    // Get attachment information
    $condition = "WHERE id='$id'";
    $row = Database::first('item_attachments', array('*'), $condition);
    $fileName = SET_ATTACHMENTS_PATH . $row['file_name'];
    // Output to browser after emptying the output buffer
    ob_clean();
    header("Expires: 0");
    header("Pragma: cache");
    header("Cache-Control: private");
    header("Content-Type: $row[file_type]");
    header("Content-Length: $row[file_size]");
    header("Content-Disposition: attachment; filename=\"" . substr($row['file_name'], 11) . "\"");
    readfile($fileName);
    exit;
}

function deleteAttachment($id, $itemID)
{
    // Delete attachment file
    $condition = "WHERE id='$id'";
    $row = Database::first('item_attachments', array('file_name'), $condition);
    // Check for dependant items and only delete file if 1 item is associated
    $condition = "WHERE file_name='" . $row['file_name'] . "'";
    $result = Database::select('item_attachments', array('file_name'), $condition);
    if (count($result) == 1) {
        unlink(SET_ATTACHMENTS_PATH . $row['file_name']);
    }
    // Delete entry from attachment table
    $condition = "WHERE id='$id'";
    Database::delete('item_attachments', $condition);
    showAttachments($itemID);
}

function deleteItem($itemID)
{
    $itemID = (int) $itemID;
    Database::run("DELETE FROM items WHERE item_id = '$itemID'");
    Database::run("DELETE FROM core_log WHERE item_id = '$itemID'");
    Database::run("DELETE FROM item_attachments WHERE item_id = '$itemID'");
    ItemFields::deleteForItem($itemID);
    GroupMembership::setItemGroups($itemID, []);
    showMyItems($_SESSION['access_user_id']);
}

/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
    case 'my_items' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        showMyItems($_SESSION['access_user_id'], @$_GET['item_id']);
        break;
    case 'show_item_types' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
        showItemTypes(@$_GET['default_item_type']);
        break;
    case 'new_item' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
        $itemTypeId = $_POST['item_type_id'] ?? $_GET['item_type_id'] ?? '';
        if ($itemTypeId === '' || $itemTypeId === null) {
            $target = \Adlexone\Http\Router::continueUrl('items') . '&option=show_item_types';
            if (defined('SERVICECENTRE_SET_ITEM_TYPE') && (string)SERVICECENTRE_SET_ITEM_TYPE !== '') {
                $target .= '&default_item_type=' . rawurlencode((string)SERVICECENTRE_SET_ITEM_TYPE);
            }
            header('Location: ' . $target);
            exit;
        }
        showItemAdd($itemTypeId, $_POST);
        break;
    case 'add_item' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
        addItem();
        break;
    case 'show_item' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        showItem($_GET['item_id'] ?? '', $_POST, $_GET['log_entry'] ?? 'no', $_GET['attachments'] ?? 'no');
        break;
    case 'log_entry' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
        showLogEntry($_GET['item_id']);
        break;
    case 'add_log_entry' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
        addLogEntry($_POST['item_id']);
        break;
    case 'delete_log' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
        deleteLog($_GET['item_id'], $_GET['log_item_sequence']);
        break;
    case 'update_item' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
        updateItem($_POST['item_id']);
        break;
    case 'change_security' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 3);
        showSecurityAssignment($_GET['item_id']);
        break;
    case 'update_security' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 3);
        updateSecurityAssignment($_GET['item_id']);
        break;
    case 'show_attachments' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        showAttachments($_GET['item_id']);
        break;
    case 'add_attachment' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
        addAttachment($_GET['item_id']);
        break;
    case 'download_attachment' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        downloadAttachment($_GET['id']);
        break;
    case 'delete_attachment' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
        deleteAttachment($_GET['id'], $_GET['item_id']);
        break;
    case 'delete_item' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
        deleteItem($_GET['item_id']);
        break;
    case 'transform_item' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
        transformItem($_POST['item_id'], $_POST['transform_type'], $_POST['item_type_id']);
        break;
    default :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        showMyItems($_SESSION['access_user_id']);
}
