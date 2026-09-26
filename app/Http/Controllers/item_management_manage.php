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
            if (Database::sqlLookup('items', 'WHERE item_id =' . $values . ' AND item_type_id = ' . APP_COUNT_ITEM_TYPE, DSN, SET_SHOW_SQL)) {
                $validId = true;
            }
        } else {
            $validId = True;
        }
        $event_id = (isset($_GET['event_id'])) ? $_GET['event_id'] : '';
        $selectionCriteria = 'WHERE item_id =' . $values . ' AND event_id = "' . $event_id . '"';
        if ($validId == True) {
            if (Database::sqlLookup('system_log', $selectionCriteria, DSN, SET_SHOW_SQL)) {
                $sql = 'UPDATE ' . 'system_log SET event_counter = event_counter + 1 ' . $selectionCriteria;
            } else {
                unset($columnArray);
                $columnArray['item_id'] = $values;
                $columnArray['create_date'] = time();
                $columnArray['event_counter'] = 1;
                $columnArray['event_id'] = $event_id;
                $sql = Database::sqlInsert('system_log', $columnArray);
                unset($columnArray);
            }
            Database::query($sql, DSN, SET_SHOW_SQL);
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
    $result = Database::query($origSQL, DSN, SET_SHOW_SQL);

    // Create Table and populate with data (and formatting)
    // Set required style info
    if ($countrecs > 1) {
        $tableHeadings = array(RenderViews::outputIfRoleAllowed('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_id\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_102', 'TXT_102') . '</strong></a>', $_SESSION['access_role_id'], 4), RenderViews::outputIfRoleAllowed('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_title\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_84', 'TXT_84') . '</strong></a>', $_SESSION['access_role_id'], 4), RenderViews::outputIfRoleAllowed('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'create_date\'; document.repform.submit()" class="URL"><strong>' . TXT_225 . '</strong></a>', $_SESSION['access_role_id'], 4), RenderViews::outputIfRoleAllowed('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_type_id\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_226', 'TXT_226') . '</strong></a>', $_SESSION['access_role_id'], 4), TXT_388);
    } else {
        $tableHeadings = array(RenderViews::getLanguageConstant('LA_102', 'TXT_102'), RenderViews::getLanguageConstant('LA_84', 'TXT_84'), TXT_225, RenderViews::getLanguageConstant('LA_226', 'TXT_226'), TXT_388);
    }

    $i = 0;
    //Open the table and render the headings
//    $html = RenderViews::tbStartTable('table table-bordered table-striped table-hover');
//    $html .= RenderViews::tbTableHeadings($tableHeadings);
    if (Database::numRows($result) > 0) {
        $itemTypeID = '';
        while ($row = Database::fetchArray($result)) {
            $cellData[] = $row['item_id'];
            if ($row['item_title'] == '') {
                $title = TXT_357;
            } else {
                $title = $row['item_title'];
            }
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            $cellData[] = '<a href ="' . MAN_BASE_URL . '&option=show_item&item_id=' . $row['item_id'] . $logEntry . $attachments . '" class="URL">' . $title . '</a>';
            $cellData[] = date(SET_DATE_FORMAT, $row['create_date']);
            // Get item type name from database
            if ($itemTypeID != $row['item_type_id']) {// We don't need to recheck as the last check was for the same item type id
                $columnArray = array('item_type_name');
                $condition = "WHERE item_type_id = '" . $row['item_type_id'] . "'";
                $sql = Database::sqlSelect('item_types', $columnArray, $condition);
                $itemTypeResult = Database::query($sql, DSN, SET_SHOW_SQL);
                $itemTypeRow = Database::fetchArray($itemTypeResult);
                $itemTypeName = $itemTypeRow['item_type_name'];
                //We set this so we can use it later if the next check is the same item type
                $cellData[] = $itemTypeName;
                $itemTypeID = $row['item_type_id'];
            } else {
                $cellData[] = $itemTypeName;
            }
            //			$itemTypeRow = Database::fetchArray($itemTypeResult);
            //			$cellData[] = $itemTypeRow['item_type_name'];
            // Setup actions and secure
            // $action .= RenderViews::outputIfRoleAllowed('<a href ="index.php?controller='.$_GET['controller'].'&subcontroller=action_management_manage&option=trigger_actions&item_id=' . $row[0] . '" class="URL">' . TXT_242 . '</a> - ',$_SESSION['access_role_id'], 2);
            $action = RenderViews::outputIfRoleAllowed('<a href="' . MAN_BASE_URL . '&option=show_attachments&item_id=' . $row['item_id'] . '" class="URL">' . TXT_389 . '</a>', $_SESSION['access_role_id'], 4);
            $action .= RenderViews::outputIfRoleAllowed(' - <a href="' . MAN_BASE_URL . '&option=change_security&item_id=' . $row['item_id'] . '" class="URL">' . TXT_28 . '</a>', $_SESSION['access_role_id'], 3);
            $action .= RenderViews::outputIfRoleAllowed(' - <a href ="' . MAN_BASE_URL . '&option=log_entry&item_id=' . $row['item_id'] . '" class="URL">' . TXT_246 . '</a>', $_SESSION['access_role_id'], 4);
            $action .= RenderViews::outputIfRoleAllowed(' - ' . RenderViews::buildURL('index.php?controller=full_page_view&option=print_item&item_id=' . $row['item_id'], TXT_625, 'URL', '', '', '_blank'), $_SESSION['access_role_id'], 5);
            $action .= RenderViews::outputIfRoleAllowed(' - <a href ="' . MAN_BASE_URL . '&option=delete_item&item_id=' . $row['item_id'] . TXT_400 . 'null">' . TXT_315 . '</a>', $_SESSION['access_role_id'], 2);
            $cellData[] = $action;
            //RenderViews table rows
            $html .= RenderViews::tbTableRows($cellData);
            $i++;
            unset($cellData, $action);
        }
    } else {
        $html .= RenderViews::tbTableRows(array(TXT_115));
    }
    $html .= RenderViews::buildStartForm('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=my_items', 'POST', 'form-horizontal');
    //End the table
    $html .= RenderViews::endTable();
    unset($cellData);
    //Displa y next/prev buildSelectDropdown
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
            $html .= $paginationHTML;
            $html .= RenderViews::buildHiddenInput('pageset', $pageset);
        }
    }
    if ($itemID == '') {
        $_SESSION['item_sql'] = $origSQL;
        $html .= RenderViews::buildHiddenInput('currsort', $newsort);
        //$html .= RenderViews::buildHiddenInput('sess',htmlentities($origSQL,ENT_COMPAT, 'UTF-8'));
        $html .= RenderViews::buildHiddenInput('search', @$pageset);
        $html .= RenderViews::buildHiddenInput('countrecs', $countrecs);
        $html .= RenderViews::buildHiddenInput('search_type', @$_GET['option']);
    }

    define('HEADING', RenderViews::getLanguageConstant('LA_44', 'TXT_44'));
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
    $bodyBlocks = [];
    $formHtml = RenderViews::buildStartForm(MAN_BASE_URL . '&option=new_item', 'POST', 'form-horizontal');
    $fields = [];

    // Get all item types
    $columnArray = ['item_type_id', 'item_type_name', 'group_security'];
    $condition = "WHERE enabled = 'Yes' ORDER BY item_type_name ASC";
    $sql = Database::sqlSelect('item_types', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    $noItemTypes = false;
    if (Database::numRows($result) > 0) {
        if (SET_SECURE_TYPE != 'yes') {
            while ($row = Database::fetchArray($result)) {
                $valueArray[] = $row['item_type_id'];
                $displayArray[] = $row['item_type_name'];
            }
        } else {
            $sql = "SELECT groups FROM group_members WHERE user_id = '" . $_SESSION['access_user_id'] . "'";
            $userResult = Database::query($sql, DSN, SET_SHOW_SQL);
            $userRow = Database::fetchArray($userResult);
            if (Database::numRows($userResult) == 0) {
                $noItemTypes = true;
            } else {
                $groupArray = explode('}-{', $userRow['groups']);
                while ($row = Database::fetchArray($result)) {
                    $typeFound = false;
                    $viewerGroupFound = false;
                    foreach ($groupArray as $group) {
                        if (stristr($row['group_security'], '}-{' . $group . '}-{')) {
                            $typeFound = true;
                            if ($_SESSION['access_role_id'] > 2) {
                                $sql = "SELECT role FROM groups WHERE group_id = '$group'";
                                $groupResult = Database::query($sql, DSN, SET_SHOW_SQL);
                                $groupRow = Database::fetchArray($groupResult);
                                if ($groupRow['role'] == '5') {
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

        $formHtml .= RenderViews::buildFormFieldsGrid($fields);
        $buttons[''] = RenderViews::buildFormButton('submit', 'submit_button', TXT_69);
        $formHtml .= RenderViews::buildEndFormWithButtons($buttons);
        $bodyBlocks[] = [
            'title' => RenderViews::getLanguageConstant('LA_46', 'TXT_46'),
            'html' => $formHtml,
            'full' => true,
        ];
    } else {
        RenderViews::buildResponse(TXT_341);
    }

    //define('HEADING', RenderViews::getLanguageConstant('LA_46', 'TXT_46'));
    define('BODY_CONTENT', RenderViews::buildVerticalCards($bodyBlocks));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
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
        $sql = Database::sqlSelect('item_types', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $itemTypeFields = Database::fetchArray($result);
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
    $sql = Database::sqlSelect('item_types', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);
    $itemTypeName = $row['item_type_name'];
    if ($_SESSION['access_role_id'] <= 2) {//Admin, FlowIQ Admin, Global FlowIQ Admin have write access
        $itemRole = 2;
    } else {
        $itemRole = 5;
        //set default role
        //Get role specific to item
        $sql = "SELECT groups FROM group_members WHERE user_id = '" . $_SESSION['access_user_id'] . "'";
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        if (Database::numRows($result) != 0) {
            $row = Database::fetchArray($result);
            $userGroups = $row['groups'];
            $groupArray = explode('}-{', $userGroups);
            $itemGroupArray = explode('}-{', $itemTypeFields['group_security']);
            foreach ($itemGroupArray as $a) {
                if (stristr($userGroups, '}-{' . $a . '}-{')) {
                    $sql = "SELECT role FROM groups WHERE group_id = '" . $a . "'";
                    $result = Database::query($sql, DSN, SET_SHOW_SQL);
                    $row = Database::fetchArray($result);
                    $itemRole = ($row['role'] < $itemRole) ? $row['role'] : $itemRole;
                }
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
        $sql = Database::sqlSelect('users', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        while ($row = Database::fetchArray($result)) {
            $userIDArray[] = $row['user_id'];
            $userArray[] = $row['user_name'];
        }// while
        $fields[RenderViews::getLanguageConstant('LA_551', 'TXT_551')] = RenderViews::buildSelectDropdown('creator_security', $userIDArray, $userArray, $itemTypeFields['creator_security']);
        $columnArray = array('user_id', 'user_name');
        $condition = "WHERE lastactive <> 'inactive' AND role <= " . SET_OWNER_MENU . " ORDER BY user_name ASC";
        $sql = Database::sqlSelect('users', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        while ($row = Database::fetchArray($result)) {
            $filterdUserIDArray[] = $row['user_id'];
            $filteredUserArray[] = $row['user_name'];
        }// while
        $fields[RenderViews::getLanguageConstant('LA_269', 'TXT_269')] = RenderViews::buildSelectDropdown('user_security', $filterdUserIDArray, $filteredUserArray, $itemTypeFields['user_security']);
    } else {
        $columnArray = array('user_name');
        $condition = "WHERE user_id ='" . $_SESSION['access_user_id'] . "'";
        $sql = Database::sqlSelect('users', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $row = Database::fetchArray($result);
        $fields[RenderViews::getLanguageConstant('LA_551', 'TXT_551')] = RenderViews::buildHiddenInput('creator_security', $_SESSION['access_user_id']) . $row['user_name'];
        if ($itemTypeFields['user_security'] == '') {
            $columnArray = array('user_name');
            $condition = "WHERE user_id ='" . $_SESSION['access_user_id'] . "'";
            $sql = Database::sqlSelect('users', $columnArray, $condition);
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            $row = Database::fetchArray($result);
            $fields[RenderViews::getLanguageConstant('LA_269', 'TXT_269')] = RenderViews::buildHiddenInput('user_security', $_SESSION['access_user_id']) . $row['user_name'];
        } else {
            $columnArray = array('user_name');
            $condition = "WHERE user_id ='" . $itemTypeFields['user_security'] . "'";
            $sql = Database::sqlSelect('users', $columnArray, $condition);
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            $row = Database::fetchArray($result);
            $fields[RenderViews::getLanguageConstant('LA_269', 'TXT_269')] = RenderViews::buildHiddenInput('user_security', $itemTypeFields['user_security']) . $row['user_name'];
        }
    }
    // Create group membership list
    $groupArray = explode('}-{', $itemTypeFields['group_security']);
    $i = 0;
    foreach ($groupArray as $a) {
        if ($i == 0) {
            $condition = "WHERE group_id='" . $a . "'";
        } else {
            $condition .= " OR group_id='" . $a . "'";
        }
        $i++;
    }
    $columnArray = array('group_id', 'group_name');
    $sql = Database::sqlSelect('groups', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $i = 0;
    $groupMembership = '';
    while ($row = Database::fetchArray($result)) {
        if ($i == 0) {
            $groupMembership = $row['group_name'];
        } else {
            $groupMembership .= ', ' . $row['group_name'];
        }
        $i++;
    }// while
    $fields[TXT_270] = RenderViews::buildTextInput('group_security', $groupMembership, '', true);
    $fields[TXT_84] = RenderViews::buildTextInput('item_title', $itemTypeFields['item_title'] ?? '');    // Setup custom field display
    $columnArray = array('custom_field_id');
    $condition = "WHERE item_type_id = '" . $itemTypeID . "' ORDER BY custom_field_order ASC";
    $sql = Database::sqlSelect('item_type_custom_fields', $columnArray, $condition);
    $customFieldResult = Database::query($sql, DSN, SET_SHOW_SQL);
    $JSValidation = array();
    $dtFormat = (SET_DATE_FORMAT == "d-m-Y, h:i A") ? ",DMY" : ",MDY";
    $JSValidation[0] = 'onclick="javascript:return fieldCheck(\'' . TXT_468 . '\',';
    $JSValidation[6] = '])"';
    $jsValFields = 0;
    $sep = 0;
    while ($customFields = Database::fetchArray($customFieldResult)) {
        $columnArray = array('*');
        $condition = "WHERE custom_field_id = '" . $customFields['custom_field_id'] . "'";
        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $row = Database::fetchArray($result);
        if ($row['enabled'] != 'Yes') {
            continue;
        }
        if (($row['field_type'] == 'workerField' or $row['field_type'] == 'workerFieldMenu') and $_SESSION['access_role_id'] > 3) {
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
            case 'buildTextInput' :
                $fields[$row['custom_field_name']] = RenderViews::buildTextInput('custom_field_' . $row['custom_field_id'], $defaultValue);
                break;
            case 'password' :
                $fields[$row['custom_field_name']] = RenderViews::buildPasswordInput('custom_field_' . $row['custom_field_id'], $defaultValue);
                break;
            case 'hidden' :
                $fields[$row['custom_field_name']] = RenderViews::buildHiddenInput('custom_field_' . $row['custom_field_id'], $defaultValue);
                break;
            case 'buildTextArea' :
                $fields[$row['custom_field_name']] = RenderViews::buildTextArea('custom_field_' . $row['custom_field_id'], $defaultValue, SET_FORM_FIELD_HEIGHT);
                break;
            case 'buildSelectDropdown' :
                // Get any menus values
                $columnArray = array('menu_value');
                $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                // Build buildSelectDropdown array
                while ($menuRow = Database::fetchArray($result)) {
                    $menuArray[] = $menuRow['menu_value'];
                }// while
                $fields[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $row['custom_field_id'], @$menuArray, @$menuArray, $defaultValue);
                unset($menuArray);
                break;
            case 'subMenu' :
                // Get any menus values
                $columnArray = array('menu_value');
                $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                // Build buildSelectDropdown array
                // Set default value
                $menuArray[] = '';
                $displaymenuArray[] = TXT_284;
                while ($menuRow = Database::fetchArray($result)) {
                    $menuArray[] = $menuRow['menu_value'];
                    $displaymenuArray[] = $menuRow['menu_value'];
                }// while
                // Show parent buildSelectDropdown
                $fields[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $row['custom_field_id'], $menuArray, $displaymenuArray, $defaultValue, 'onChange="document.addItem.submit();"');
                unset($menuArray, $displaymenuArray);
                // Get sub buildSelectDropdown
                $columnArray = array('sub_menu_values');
                // Parent buildSelectDropdown value sets the criteria for building the sub buildSelectDropdown
                $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "' AND menu_value = '" . $subMenuFilter . "'";
                $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $subMenuRow = Database::fetchArray($result);
                // Set default buildSelectDropdown value if parent buildSelectDropdown has not been refreshed
                if (!empty($subMenuRow) && !empty($subMenuRow['sub_menu_values'])) {
                    $subMenuArray = explode(',', $subMenuRow['sub_menu_values']);
                } else {
                    // Use the existing fallback used elsewhere in the file
                    $subMenuArray = array(TXT_296);
                }
                // Show sub buildSelectDropdown
                // Get sub buildSelectDropdown details
                $columnArray = array('custom_field_id', 'custom_field_name');
                $condition = "WHERE custom_field_id = '" . $row['sub_menu'] . "'";
                $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $subMenuRow = Database::fetchArray($result);
                if (!isset($values['user_security'])) {
                    $subMenuDefault = $row['default_value'];
                } else {
                    $subMenuDefault = @$itemTypeFields['custom_field_' . $subMenuRow['custom_field_id']];
                }
                $fields[$subMenuRow['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $subMenuRow['custom_field_id'], $subMenuArray, $subMenuArray, $subMenuDefault);
                unset($subMenuArray);
                break;

            case 'multiLevelMenu' :
                $columnArray = array('*');
                $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $row = Database::fetchArray($result);
                $relationshipArray = unserialize($row['menu_value_links']);
                $itemCount = $row['menu_levels'];
                if ($row['menu_relationship'] != '') {

                    $customFieldArray = explode('}-{', $row['menu_relationship']);
                    foreach ($customFieldArray as $key => $value) {
                        if ($value != '') {
                            $fieldArray = explode(',', $value);
                            $customFieldIDArray[$fieldArray[0]] = $fieldArray[1];
                            //key is custom field id
                        }
                    }

                    $arrayCount = count($customFieldIDArray);
                    $count = 1;

                    foreach ($customFieldIDArray as $key => $value) {

                        $columnArray = array('custom_field_name');
                        $condition = "WHERE custom_field_id = '" . $key . "'";
                        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
                        $result = Database::query($sql, DSN, SET_SHOW_SQL);
                        $row = Database::fetchArray($result);
                        unset($columnArray);

                        if ($count > 1) {
                            //prepare dependancy array
                            $keyDetected = false;
                            $tempCustomFieldIDArray = $customFieldIDArray;
                            foreach ($tempCustomFieldIDArray as $idKey => $idValue) {
                                if ($idKey == $key) {
                                    $keyDetected = true;
                                    unset($tempCustomFieldIDArray[$idKey]);
                                } elseif ($keyDetected == true) {
                                    unset($tempCustomFieldIDArray[$idKey]);
                                }
                            }
                            foreach ($tempCustomFieldIDArray as $tempKey => $tempValue) {
                                //get buildSelectDropdown id of parent buildSelectDropdown value
                                $columnArray = array('menu_value_id');
                                $condition = "WHERE menu_value = '" . @$_POST['custom_field_' . $tempKey] . "'";
                                $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                                $valueRow = Database::fetchArray($result);
                                $parentMenuValueID[$tempKey] = $valueRow['menu_value_id'];
                                unset($columnArray);
                            }
                        }
                        // Get any menus values
                        $columnArray = array('menu_value', 'menu_value_id');
                        $condition = "WHERE custom_field_id = '" . $key . "'";
                        $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                        $result = Database::query($sql, DSN, SET_SHOW_SQL);
                        // Build buildSelectDropdown array
                        // Set default value
                        $menuArray[] = '';
                        $displaymenuArray[] = TXT_670;
                        $idArray = array();
                        while ($menuRow = Database::fetchArray($result)) {
                            if ($count == 1) {
                                foreach ($relationshipArray as $array) {
                                    if (($array[$key] == $menuRow['menu_value_id']) and !in_array($menuRow['menu_value_id'], $idArray)) {
                                        $idArray[] = $menuRow['menu_value_id'];
                                        $menuArray[] = $menuRow['menu_value'];
                                        $displaymenuArray[] = $menuRow['menu_value'];
                                    }
                                }
                            } else if (!isset($values['custom_field_' . $previousKey])) {
                                unset($displaymenuArray, $menuArray);
                                $menuArray[] = '';
                                $displaymenuArray[] = TXT_670;
                            } else {

                                foreach ($relationshipArray as $array) {
                                    //Carry out parent field value match
                                    $keyDetected = false;
                                    //this is where the bug is
                                    foreach ($parentMenuValueID as $aKey) {
                                        if (!in_array($aKey, $array)) {
                                            $keyDetected = false;
                                            break;
                                        } else {
                                            $keyDetected = true;
                                        }

                                    }

                                    if ($keyDetected == true and ($array[$previousKey] == $parentMenuValueID[$previousKey]) and ($array[$key] == $menuRow['menu_value_id']) and !in_array($menuRow['menu_value_id'], $idArray)) {

                                        $idArray[] = $menuRow['menu_value_id'];
                                        $menuArray[] = $menuRow['menu_value'];
                                        $displaymenuArray[] = $menuRow['menu_value'];
                                    }
                                    //}
                                }
                            }
                        }// while
                        $previousKey = $key;
                        if ($count != $arrayCount) {
                            $fields[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $key, $menuArray, $displaymenuArray, @$values['custom_field_' . $key], 'onChange="document.addItem.submit();"');
                        } else {
                            $fields[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $key, $menuArray, $displaymenuArray, @$values['custom_field_' . $key]);
                        }

                        unset($menuArray, $displaymenuArray, $columnArray);
                        $count++;
                    }

                }
                break;
            case 'buildCheckBox' :
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
                $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $defaultValueArray = Database::fetchArray($result);
                // Get any menus values
                $columnArray = array('menu_value');
                $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                // Build buildSelectDropdown array
                while ($menuRow = Database::fetchArray($result)) {
                    $menuArray[] = $menuRow['menu_value'];
                }// while
                $fields[$row['custom_field_name']] = RenderViews::buildSelectDropdown('worker_field_menu_' . $row['custom_field_id'], @$menuArray, @$menuArray, $defaultValueArray['default_value']);
                unset($menuArray);
                break;
            case 'dataSourceMenu' :
                //lookup data source name
                $i = 1;
                while ($i <= SET_DS_DATA_SOURCE_COUNT) {
                    if (constant('SET_DS_NAME_' . $i) == $row['data_source_name']) {
                        $dataSourceDSN = 'Driver={' . constant('SET_DS_DRIVER_' . $i) . '};Server=[' . constant('SET_DS_DATABASE_HOST_' . $i) . '];Database=[' . constant('SET_DS_DATABASE_NAME_' . $i) . '];UID=[' . constant('SET_DS_DATABASE_USER_' . $i) . '];PWD=[' . constant('SET_DS_DATABASE_PWD_' . $i) . '];Port=[' . constant('SET_DS_DATABASE_PORT_' . $i) . ']';
                        break;
                    }
                    $i++;
                }
                $sql = constant('SET_DS_SQL_' . $i);
                $result = Database::query($sql, $dataSourceDSN, SET_SHOW_SQL);
                // Build buildSelectDropdown array
                while ($menuRow = Database::fetchArray($result)) {
                    $valueArray[] = $menuRow[0];
                    $displayArray[] = $menuRow[1];
                }// while
                $fields[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $row['custom_field_id'], @$valueArray, @$displayArray, $defaultValue);
                unset($valueArray, $displayArray);
                break;
            default :
                break;
        }
    }

    if (ADD_ATTACHMENTS == 'yes') {
        $attachmentField[TXT_393] = RenderViews::buildFileInput('attachment', SET_MAX_ATTACHMENT * 1000000, '', 'btn btn-default');
        $html .= RenderViews::buildFormFieldsGrid($attachmentField);
    }
    $html .= RenderViews::buildHiddenInput('item_type_id', $itemTypeFields['item_type_id']);
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
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_57, 'btn btn-default', $javascript);
    $bodyContent = RenderViews::buildForm(RenderViews::getLanguageConstant('LA_67', 'TXT_67') . ' - ' . $itemTypeName,MAN_BASE_URL. '&option=add_item',$fields,$buttons);
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
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    if (Database::numRows($result) == 0) {
       RenderViews::buildResponse(TXT_616);
    } else {
        $i = 0;
        if ($_SESSION['access_role_id'] <= 2) {//Admin, FlowIQ Admin, Global FlowIQ Admin have access
            $i++;
        } else {
            // Check to see if we have access to it
            $sql = "SELECT item_id FROM items WHERE (user_security = '" . $_SESSION['access_user_id'] . "' OR creator_security  = '" . $_SESSION['access_user_id'] . "') and item_id='" . $itemID . "'";
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            if (Database::numRows($result) > 0) {
                $i++;
                //No need to go any further
            } else {
                // Get all items by group assignment
                $sql = "SELECT groups FROM group_members WHERE user_id = '" . $_SESSION['access_user_id'] . "'";
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $row = Database::fetchArray($result);
                $groupArray = explode('}-{', $row['groups']);
                $sql = "SELECT group_security FROM items WHERE item_id='" . $itemID . "'";
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $row = Database::fetchArray($result);
                foreach ($groupArray as $a) {
                    if (stristr($row['group_security'], '}-{' . $a . '}-{')) {
                        $i++;
                    }
                }
            }
        }
        if ($i > 0) {//The user is allowed to access the task
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            // Load item values or use posted field values
            if (!isset($values['item_id'])) {
                // Setup item  information for display
                $columnArray = array('*');
                $condition = "WHERE item_id = '" . $itemID . "'";
                $sql = Database::sqlSelect('items', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $itemFields = Database::fetchArray($result);
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
            $sql = Database::sqlSelect('item_types', $columnArray, $condition);
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            $row = Database::fetchArray($result);
            $itemTypeName = $row['item_type_name'];

            if ($_SESSION['access_role_id'] < 4) {//Managers, admins and oneorzero admins and global admins
                // Build table array
                // Security is setup by default from the item
                $columnArray = array('user_id', 'user_name');
                $condition = "ORDER BY user_name ASC";
                $sql = Database::sqlSelect('users', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                while ($row = Database::fetchArray($result)) {
                    $userIDArray[] = $row['user_id'];
                    $userArray[] = $row['user_name'];
                }// while
                $itemField[RenderViews::getLanguageConstant('LA_551', 'TXT_551')] = RenderViews::buildSelectDropdown('creator_security', $userIDArray, $userArray, $itemFields['creator_security']);
                $columnArray = array('user_id', 'user_name');
                $condition = "WHERE role <= " . SET_OWNER_MENU . " ORDER BY user_name ASC";
                $sql = Database::sqlSelect('users', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                while ($row = Database::fetchArray($result)) {
                    $filterdUserIDArray[] = $row['user_id'];
                    $filteredUserArray[] = $row['user_name'];
                }// while
                $itemField[RenderViews::getLanguageConstant('LA_269', 'TXT_269')] = RenderViews::buildSelectDropdown('user_security', $filterdUserIDArray, $filteredUserArray, $itemFields['user_security']);
            } else {
                $columnArray = array('user_name');
                $condition = "WHERE user_id = '" . $itemFields['creator_security'] . "'";
                $sql = Database::sqlSelect('users', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $row = Database::fetchArray($result);
                $itemField[TXT_551] = RenderViews::buildHiddenInput('creator_security', $itemFields['creator_security']) . $row['user_name'];
                $columnArray = array('user_name');
                $condition = "WHERE user_id = '" . $itemFields['user_security'] . "'";
                $sql = Database::sqlSelect('users', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $row = Database::fetchArray($result);
                $itemField[TXT_269] = RenderViews::buildHiddenInput('user_security', $itemFields['user_security']) . $row['user_name'];
            }
            $itemRole = 5;
            //set default role
            //Get role specific to item
            $sql = "SELECT groups FROM group_members WHERE user_id = '" . $_SESSION['access_user_id'] . "'";
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            if (Database::numRows($result) != 0) {

                $row = Database::fetchArray($result);
                $groupArray = explode('}-{', $row['groups']);
                $itemGroupArray = explode('}-{', $itemFields['group_security']);
                if (is_array($itemGroupArray)) {
                    foreach ($itemGroupArray as $a) {
                        if (stristr(@$row['groups'], '}-{' . $a . '}-{')) {
                            $sql = "SELECT role FROM groups WHERE group_id = '" . $a . "'";
                            $result = Database::query($sql, DSN, SET_SHOW_SQL);
                            $row = Database::fetchArray($result);
                            $itemRole = ($row['role'] < $itemRole) ? $row['role'] : $itemRole;
                        }
                    }
                }
            }
            $html = '';
            //Override at a user level if we have to
            if ($_SESSION['access_role_id'] <= 2) {//Admin, FlowIQ Admin, Global FlowIQ Admin and owners have write access
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
                $groupArray = explode('}-{', $itemFields['group_security']);
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
                $sql = Database::sqlSelect('groups', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $i = 0;
                $groupMembership = '';
                while ($row = Database::fetchArray($result)) {
                    if ($i == 0) {
                        $groupMembership = $row['group_name'];
                    } else {
                        $groupMembership .= ', ' . $row['group_name'];
                    }
                    $i++;
                }// while
                $changeSecurityURL = ' - (' . RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=change_security&item_id=' . $itemID, TXT_268, 'URL') . ')';
                $html .= RenderViews::buildHiddenInput('group_security', $itemFields['group_security']);
                $itemField[TXT_270] = RenderViews::buildTextInput('item_title', $groupMembership, '', TRUE);
            }
            if ($itemRole >= 4) {//Users and viewers can read only
                $itemField[RenderViews::getLanguageConstant('LA_84', 'TXT_84')] = RenderViews::buildHiddenInput('item_title', $itemFields['item_title']) . $itemFields['item_title'];
            } else {
                $itemField[RenderViews::getLanguageConstant('LA_84', 'TXT_84')] = RenderViews::buildTextInput('item_title', $itemFields['item_title']);
            }
            // Setup custom field display
            $columnArray = array('custom_field_id');
            $condition = "WHERE item_type_id = '" . $itemFields['item_type_id'] . "' ORDER BY custom_field_order ASC";
            $sql = Database::sqlSelect('item_type_custom_fields', $columnArray, $condition);
            $customFieldResult = Database::query($sql, DSN, SET_SHOW_SQL);
            $JSValidation = array();
            $dtFormat = (SET_DATE_FORMAT == "d-m-Y, h:i A") ? ",DMY" : ",MDY";
            $JSValidation[0] = 'onclick="javascript:return fieldCheck(\'' . TXT_468 . '\',';
            $JSValidation[6] = '])"';
            $jsValFields = 0;
            $sep = 0;
            while ($customFields = Database::fetchArray($customFieldResult)) {
                $columnArray = array('*');
                $condition = "WHERE custom_field_id = '" . $customFields['custom_field_id'] . "'";
                $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                $row = Database::fetchArray($result);
                if ($row['enabled'] != 'Yes') {
                    continue;
                }
                if (($row['field_type'] == 'workerField' or $row['field_type'] == 'workerFieldMenu') and $_SESSION['access_role_id'] > 3) {
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
                    if ($row['field_type'] != 'buildTextArea') {
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
                    case 'buildTextInput' :
                        $itemField[$row['custom_field_name']] = RenderViews::buildTextInput('custom_field_' . $row['custom_field_id'], $value);
                        break;
                    case 'password' :
                        $itemField[$row['custom_field_name']] = RenderViews::buildPasswordInput('custom_field_' . $row['custom_field_id'], $value);
                        break;
                    case 'hidden' :
                        $itemField[$row['custom_field_name']] = RenderViews::buildHiddenInput('custom_field_' . @$row['custom_field_id'], $value);
                        break;
                    case 'buildTextArea' :
                        $itemField[$row['custom_field_name']] = RenderViews::buildTextArea('custom_field_' . $row['custom_field_id'], $value, SET_FORM_FIELD_HEIGHT);
                        break;
                    case 'buildSelectDropdown' :
                        // Get any menus values
                        $columnArray = array('menu_value');
                        $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                        $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                        $result = Database::query($sql, DSN, SET_SHOW_SQL);
                        // Build buildSelectDropdown array
                        while ($menuRow = Database::fetchArray($result)) {
                            $menuArray[] = $menuRow['menu_value'];
                        }// while
                        $itemField[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $row['custom_field_id'], @$menuArray, @$menuArray, $menuValue, 'form-control');
                        unset($menuArray);
                        break;
                    case 'subMenu' :
                        // Get any menus values
                        $columnArray = array('menu_value');
                        $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                        $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                        $result = Database::query($sql, DSN, SET_SHOW_SQL);
                        // Build buildSelectDropdown array
                        // Set default value
                        $menuArray[] = '';
                        $displaymenuArray[] = TXT_284;
                        while ($menuRow = Database::fetchArray($result)) {
                            $menuArray[] = $menuRow['menu_value'];
                            $displaymenuArray[] = $menuRow['menu_value'];
                        }// while
                        // Show parent buildSelectDropdown
                        $itemField[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $row['custom_field_id'], $menuArray, $displaymenuArray, $menuValue, 'onChange="document.updateItem.submit();"');
                        unset($menuArray, $displaymenuArray);

                        // Get sub buildSelectDropdown
                        $columnArray = array('sub_menu_values');
                        // Parent buildSelectDropdown value sets the criteria for building the sub buildSelectDropdown
                        $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "' AND menu_value = '" . $menuValue . "'";
                        $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                        $result = Database::query($sql, DSN, SET_SHOW_SQL);
                        $subMenuRow = Database::fetchArray($result);
                        // Set default buildSelectDropdown value if parent buildSelectDropdown has not been refreshed
                        if ($subMenuRow['sub_menu_values'] != '') {
                            $subMenuArray = explode(',', $subMenuRow['sub_menu_values']);
                        } else {
                            $subMenuArray = array(TXT_296);
                        }
                        // Show sub buildSelectDropdown
                        // Get sub buildSelectDropdown details
                        $columnArray = array('custom_field_id', 'custom_field_name');
                        $condition = "WHERE custom_field_id = '" . $row['sub_menu'] . "'";
                        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
                        $result = Database::query($sql, DSN, SET_SHOW_SQL);
                        $subMenuRow = Database::fetchArray($result);
                        if (!isset($values['item_id'])) {
                            $subMenuValue = $itemFields['custom_field_' . $row['sub_menu']];
                        } else {
                            $subMenuValue = $values['custom_field_' . $row['sub_menu']];
                        }
                        $itemField[$subMenuRow['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $subMenuRow['custom_field_id'], $subMenuArray, $subMenuArray, $subMenuValue);
                        unset($subMenuArray);
                        break;

                    case 'multiLevelMenu' :
                        $columnArray = array('*');
                        $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
                        $result = Database::query($sql, DSN, SET_SHOW_SQL);
                        $row = Database::fetchArray($result);
                        $relationshipArray = unserialize($row['menu_value_links']);
                        $itemCount = $row['menu_levels'];
                        if ($row['menu_relationship'] != '') {

                            $customFieldArray = explode('}-{', $row['menu_relationship']);
                            foreach ($customFieldArray as $key => $value) {
                                if ($value != '') {
                                    $fieldArray = explode(',', $value);
                                    $customFieldIDArray[$fieldArray[0]] = $fieldArray[1];
                                    //key is custom field id
                                }
                            }

                            $arrayCount = count($customFieldIDArray);
                            $count = 1;

                            foreach ($customFieldIDArray as $key => $value) {

                                $columnArray = array('custom_field_name');
                                $condition = "WHERE custom_field_id = '" . $key . "'";
                                $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
                                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                                $row = Database::fetchArray($result);
                                unset($columnArray);
                                if (!isset($values['item_id'])) {

                                    $menuArray = array('', $itemFields['custom_field_' . $key]);
                                    $displaymenuArray = array(TXT_670, $itemFields['custom_field_' . $key]);
                                    $itemField[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $key, $menuArray, $displaymenuArray, $itemFields['custom_field_' . $key], 'onChange="document.updateItem.submit();"');
                                    unset($menuArray, $displaymenuArray);

                                } else {

                                    if ($count > 1) {
                                        //prepare dependancy array
                                        $keyDetected = false;
                                        $tempCustomFieldIDArray = $customFieldIDArray;
                                        foreach ($tempCustomFieldIDArray as $idKey => $idValue) {
                                            if ($idKey == $key) {
                                                $keyDetected = true;
                                                unset($tempCustomFieldIDArray[$idKey]);
                                            } elseif ($keyDetected == true) {
                                                unset($tempCustomFieldIDArray[$idKey]);
                                            }
                                        }
                                        foreach ($tempCustomFieldIDArray as $tempKey => $tempValue) {
                                            //get buildSelectDropdown id of parent buildSelectDropdown value
                                            $columnArray = array('menu_value_id');
                                            $condition = "WHERE menu_value = '" . @$_POST['custom_field_' . $tempKey] . "'";
                                            $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                                            $result = Database::query($sql, DSN, SET_SHOW_SQL);
                                            $valueRow = Database::fetchArray($result);
                                            $parentMenuValueID[$tempKey] = $valueRow['menu_value_id'];
                                            unset($columnArray);
                                        }
                                    }
                                    // Get any menus values
                                    $columnArray = array('menu_value', 'menu_value_id');
                                    $condition = "WHERE custom_field_id = '" . $key . "'";
                                    $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                                    $result = Database::query($sql, DSN, SET_SHOW_SQL);
                                    // Build buildSelectDropdown array
                                    // Set default value
                                    $menuArray[] = '';
                                    $displaymenuArray[] = TXT_670;
                                    $idArray = array();
                                    while ($menuRow = Database::fetchArray($result)) {
                                        if ($count == 1) {
                                            foreach ($relationshipArray as $array) {
                                                if (($array[$key] == $menuRow['menu_value_id']) and !in_array($menuRow['menu_value_id'], $idArray)) {
                                                    $idArray[] = $menuRow['menu_value_id'];
                                                    $menuArray[] = $menuRow['menu_value'];
                                                    $displaymenuArray[] = $menuRow['menu_value'];
                                                }
                                            }
                                        } else if (!isset($values['custom_field_' . $previousKey])) {
                                            unset($displaymenuArray, $menuArray);
                                            $menuArray[] = '';
                                            $displaymenuArray[] = TXT_670;
                                        } else {

                                            foreach ($relationshipArray as $array) {
                                                //Carry out parent field value match
                                                $keyDetected = false;
                                                //this is where the bug is
                                                foreach ($parentMenuValueID as $aKey) {
                                                    if (!in_array($aKey, $array)) {
                                                        $keyDetected = false;
                                                        break;
                                                    } else {
                                                        $keyDetected = true;
                                                    }

                                                }

                                                if ($keyDetected == true and ($array[$previousKey] == $parentMenuValueID[$previousKey]) and ($array[$key] == $menuRow['menu_value_id']) and !in_array($menuRow['menu_value_id'], $idArray)) {

                                                    $idArray[] = $menuRow['menu_value_id'];
                                                    $menuArray[] = $menuRow['menu_value'];
                                                    $displaymenuArray[] = $menuRow['menu_value'];
                                                }
                                                //}
                                            }
                                        }
                                    }// while
                                    $previousKey = $key;
                                    if ($count != $arrayCount) {
                                        $itemField[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $key, $menuArray, $displaymenuArray, @$values['custom_field_' . $key], 'onChange="document.updateItem.submit();"');
                                    } else {
                                        $itemField[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $key, $menuArray, $displaymenuArray, @$values['custom_field_' . $key]);
                                    }

                                    unset($menuArray, $displaymenuArray, $columnArray);
                                    $count++;
                                }
                            }

                        }
                        break;

                    case 'buildCheckBox' :
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
                        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
                        $result = Database::query($sql, DSN, SET_SHOW_SQL);
                        $defaultValueArray = Database::fetchArray($result);
                        // Get any menus values
                        $columnArray = array('menu_value');
                        $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                        $sql = Database::sqlSelect('custom_field_menu_values', $columnArray, $condition);
                        $result = Database::query($sql, DSN, SET_SHOW_SQL);
                        // Build buildSelectDropdown array
                        while ($menuRow = Database::fetchArray($result)) {
                            $menuArray[] = $menuRow['menu_value'];
                        }// while
                        $itemField[$row['custom_field_name']] = RenderViews::buildSelectDropdown('worker_field_menu_' . $row['custom_field_id'], @$menuArray, @$menuArray, $defaultValueArray['default_value']);
                        unset($menuArray);
                        break;
                    case 'dataSourceMenu' :
                        //lookup data source name
                        $i = 1;
                        while ($i <= SET_DS_DATA_SOURCE_COUNT) {
                            if (constant('SET_DS_NAME_' . $i) == $row['data_source_name']) {
                                $dataSourceDSN = 'Driver={' . constant('SET_DS_DRIVER_' . $i) . '};Server=[' . constant('SET_DS_DATABASE_HOST_' . $i) . '];Database=[' . constant('SET_DS_DATABASE_NAME_' . $i) . '];UID=[' . constant('SET_DS_DATABASE_USER_' . $i) . '];PWD=[' . constant('SET_DS_DATABASE_PWD_' . $i) . '];Port=[' . constant('SET_DS_DATABASE_PORT_' . $i) . ']';
                                break;
                            }
                            $i++;
                        }
                        $sql = constant('SET_DS_SQL_' . $i);
                        $result = Database::query($sql, $dataSourceDSN, SET_SHOW_SQL);
                        // Build buildSelectDropdown array
                        while ($menuRow = Database::fetchArray($result)) {
                            $valueArray[] = $menuRow[0];
                            $displayArray[] = $menuRow[1];
                        }// while
                        $itemField[$row['custom_field_name']] = RenderViews::buildSelectDropdown('custom_field_' . $row['custom_field_id'], @$valueArray, @$displayArray, $menuValue, 'form-control');
                        unset($valueArray, $displayArray);
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

            $itemField[] = RenderViews::buildHiddenInput('item_type_id', $itemFields['item_type_id']).RenderViews::buildHiddenInput('item_id', $itemFields['item_id']);
            $html .= RenderViews::buildForm(RenderViews::getLanguageConstant('LA_102', 'TXT_102') . ': ' . $itemFields['item_id'] . ' - ' . $itemTypeName,MAN_BASE_URL . '&option=update_item',$itemField,$buttons);

            //Show transform option
            if ($_SESSION['access_role_id'] <= 2) {//Administrator, Adlexone Administrator or Global Administrator
                // Administrative functions
                $columnArray = array('item_type_id', 'item_type_name');
                $sql = Database::sqlSelect('item_types', $columnArray);
                $result = Database::query($sql, DSN, SET_SHOW_SQL);
                if (Database::numRows($result) > 0) {
                    while ($row = Database::fetchArray($result)) {
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
        $html = RenderViews::showResponse(TXT_535, '');
        define('HEADING', TXT_218);
        define('BODY_CONTENT', $html);
        RenderViews::renderThemePage('main_page_content', SET_THEME);
    } elseif (!isset($_POST['submit_button'])) {
        // Reload form setting values based on posted form values, triggered from javascript submits
        showItemAdd($_POST['item_type_id'], $_POST);
    } else {
        $array['item_id'] = Database::newID('items', 'item_id');
        if (ADD_ATTACHMENTS == 'yes' and $_FILES['attachment']['name'] != '') {
            addAttachment($array['item_id'], false, false);
        }
        // Set create date and core log updated as array
        $array['create_date'] = time();
        $array['core_log_updated'] = time();
        // Remove unwanted form variables
        unset($_POST['submit_button'], $_POST['reset'], $_POST['MAX_FILE_SIZE'], $_POST['attachment']);
        // Set item id as array
        // Merge arrays for item insert query
        foreach ($_POST as $key => $value) {
            //Don't insert work field values - they aren't persistent
            if (!stristr($key, 'worker_field') and !stristr($key, 'worker_field_menu')) {
                $dbArray[$key] = $value;
            }
        }
        $insertArray = array_merge($array, $dbArray);
        // Insert form field values into row
        $sql = Database::sqlInsert('items', $insertArray);
        Database::query($sql, DSN, SET_SHOW_SQL);
        // Execute actions
        Actions::executeAction($array['item_id'], 'create_item', false);
        if ($_FILES['attachment']['name'] != '') {
            Actions::executeAction($array['item_id'], 'item_attachment', false);
        }
        // Deal with empty item title but add the item first
        showItems('', $array['item_id']);
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
            $sql = Database::sqlUpdate('items', $columnArray, $condition);
            Database::query($sql, DSN, SET_SHOW_SQL);
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            $html = RenderViews::showResponse(TXT_450, RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments, TXT_262, 'URL'));
            define('HEADING', TXT_449);
            define('BODY_CONTENT', $html);
            RenderViews::renderThemePage('main_page_content', SET_THEME);
            break;

        case 'copy' :
            //Copies existing data to new item
            //Get existing data
            $columnArray = array('*');
            $condition = "WHERE item_id = '" . $itemID . "'";
            $sql = Database::sqlSelect('items', $columnArray, $condition);
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            $row = Database::fetchArray($result);
            //Create new item based on existing data
            foreach ($row as $key => $value) {
                if (!is_integer($key)) {//ignore integer keys in array
                    $newItemColumnArray[$key] = addslashes($value);
                }
            }
            $newItemColumnArray['item_id'] = Database::newID('items', 'item_id');
            //get new item id value
            $newItemColumnArray['item_type_id'] = $targetItemTypeID;
            //override item type id
            $sql = Database::sqlInsert('items', $newItemColumnArray);
            Database::query($sql, DSN, SET_SHOW_SQL);
            //Copy log entries
            $logColumnArray = array('*');
            $condition = "WHERE item_id = '" . $itemID . "'";
            $sql = Database::sqlSelect('core_log', $logColumnArray, $condition);
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            while ($row = Database::fetchArray($result)) {
                //Create new log entries based on existing data
                foreach ($row as $key => $value) {
                    if (!is_integer($key)) {//ignore integer keys in array
                        $newLogColumnArray[$key] = addslashes($value);
                    }
                }
                $newLogColumnArray['id'] = Database::newID('core_log', 'id');
                //get new id value;
                $newLogColumnArray['item_id'] = $newItemColumnArray['item_id'];
                //override id
                $sql = Database::sqlInsert('core_log', $newLogColumnArray);
                Database::query($sql, DSN, SET_SHOW_SQL);
            }
            //Copy attachment entries
            $attachmentColumnArray = array('*');
            $condition = "WHERE item_id = '" . $itemID . "'";
            $sql = Database::sqlSelect('item_attachments', $attachmentColumnArray, $condition);
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            while ($row = Database::fetchArray($result)) {
                //Create new attachment entries based on existing data
                foreach ($row as $key => $value) {
                    if (!is_integer($key)) {//ignore integer keys in array
                        $newAttachmentColumnArray[$key] = $value;
                    }
                }
                $newAttachmentColumnArray['id'] = Database::newID('item_attachments', 'id');
                //get new id value;
                $newAttachmentColumnArray['item_id'] = $newItemColumnArray['item_id'];
                //override id
                $sql = Database::sqlInsert('item_attachments', $newAttachmentColumnArray);
                Database::query($sql, DSN, SET_SHOW_SQL);
            }
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            $html = RenderViews::showResponse(TXT_392, RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $newItemColumnArray['item_id'] . $logEntry . $attachments, TXT_262, 'URL'));
            define('HEADING', TXT_449);
            define('BODY_CONTENT', $html);
            RenderViews::renderThemePage('main_page_content', SET_THEME);

            break;
    }

    //Copy to another item

    //Tranform to another item type

}

function updateItem($itemID)
{
    // Return to the form if dynamic actions are still occurring, such as sub buildSelectDropdown selection etc
    if (!isset($_POST['item_type_id'])) {
        // halt adding the item as we may have shortcutted here
        $html = RenderViews::showResponse(TXT_535, '');
        define('HEADING', TXT_218);
        define('BODY_CONTENT', $html);
        RenderViews::renderThemePage('main_page_content', SET_THEME);
    } elseif (!isset($_POST['submit_button'])) {
        // Reload form setting values based on posted form values, triggered from javascript submits
        showItem($_POST['item_id'], $_POST, $_GET['log_entry'], $_GET['attachments']);
    } else {
        // Remove unwanted posted information
        unset($_POST['submit_button'], $_POST['reset'], $_POST['item_id']);
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
        $sql = Database::sqlUpdate('items', $_POST, $condition);
        Database::query($sql, DSN, SET_SHOW_SQL);
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        if ($_POST['item_title'] != '') {
            $html = RenderViews::showResponse(TXT_263, RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments, TXT_262, 'URL'));
        } else {
            $html = RenderViews::showResponse(TXT_272, RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments, TXT_262, 'URL'));
        }
        define('HEADING', RenderViews::getLanguageConstant('LA_48', 'TXT_48'));
        define('BODY_CONTENT', $html);
        RenderViews::renderThemePage('main_page_content', SET_THEME);
    }
}

/**
 * Creates form for adding new log entry and displays existing log.
 *
 * @param integer $itemID Item ID
 */
function showLogEntry($itemID)
{
    // Create form
    $html = RenderViews::buildStartForm(MAN_BASE_URL . '&option=add_log_entry', 'POST', 'form-horizontal');
    $showLog = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $url = RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $showLog . $attachments, TXT_385, 'URL');
    $logField[RenderViews::getLanguageConstant('LA_244', 'TXT_244')] = RenderViews::buildTextArea('log_entry', '', SET_FORM_FIELD_HEIGHT);
    $roleIDs = array(2, 3, 4, 5);
    $roleNames = array(TXT_192, TXT_193, TXT_194, TXT_303);
    $logField[TXT_469] = RenderViews::buildSelectDropdown('role_id', $roleIDs, $roleNames, 5) . ' - ' . TXT_470;
    $logField[''] = RenderViews::buildFormButton('submit', 'submit_button', TXT_74);
    $html .= RenderViews::buildFormFieldsGrid($logField);
    $columnArray = array('*');
    $condition = "WHERE item_id = '" . $itemID . "'";
    $sql = Database::sqlSelect('items', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    while ($row = Database::fetchArray($result)) {
        foreach ($row as $key => $value) {
            if (!is_numeric($key) and $value != '') {
                $html .= RenderViews::buildHiddenInput($key, $value);
            }
        }
    }
    $html .= RenderViews::endForm();
    $html .= showItemLog($itemID);
    define('BODY_CONTENT', $html);
    define('HEADING', RenderViews::getLanguageConstant('LA_243', 'TXT_243') . ' - ' . $url);
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
    // Get new log id and sequence
    $LogID = Database::newID('core_log', 'id');
    $condition = "WHERE item_id='$itemID'";
    $SequenceID = Database::newID('core_log', 'log_item_sequence', $condition);
    // Add log entry
    $columnArray['id'] = $LogID;
    $columnArray['item_id'] = $itemID;
    $columnArray['create_date'] = time();
    $columnArray['item_identifier'] = 0;
    //0 Reserved for item log update
    $columnArray['log_item_sequence'] = $SequenceID;
    $columnArray['log_text'] = $_POST['log_entry'];
    $columnArray['security_id'] = $_SESSION['access_user_id'];
    $columnArray['role_id'] = $_POST['role_id'];
    // Insert into log table
    $sql = Database::sqlInsert('core_log', $columnArray);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    // Update entry in item table
    $condition = "WHERE item_id = '$itemID'";
    $itemColumnArray['core_log_updated'] = $columnArray['create_date'];
    $sql = Database::sqlUpdate('items', $itemColumnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    if ($executeAction == true) {
        // Execute actions
        Actions::executeAction($itemID, 'update_item_log_entry', false);
    }
    if ($showInformation == true) {
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        $html = RenderViews::showResponse(TXT_248, RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments, TXT_262, 'URL'));
        define('HEADING', RenderViews::getLanguageConstant('LA_243', 'TXT_243'));
        define('BODY_CONTENT', $html);
        RenderViews::renderThemePage('main_page_content', SET_THEME);
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
    $show = RenderViews::buildURL('#', TXT_373, 'URL', '', 'onclick="showElement(\'log_items\',\'tr\');return false;"');
    $hide = RenderViews::buildURL('#', TXT_374, 'URL', '', 'onclick="hideElement(\'log_items\',\'tr\');return false;"');
    $controls = '<div class="log-controls">' . $show . ' \ ' . $hide . '</div>';

    $itemsHtml = $controls;
    $columnArray = ['*'];
    $condition = "WHERE item_id = :item_id ORDER BY log_item_sequence DESC";
    $sql = Database::sqlSelect('core_log', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL, ['item_id' => $itemID]);

    while ($row = Database::fetchArray($result)) {
        if ($_SESSION['access_role_id'] <= $row['role_id']) {
            $userName = $row['security_id'] !== '' ? Database::sqlLookup('users', 'user_name', 'WHERE user_id = ' . (int)$row['security_id'], DSN, SET_SHOW_SQL) : '';
            $role = match ((int)$row['role_id']) {
                0 => TXT_190,
                1 => TXT_191,
                2 => TXT_192,
                3 => TXT_193,
                4 => TXT_194,
                5 => TXT_303,
                default => ''
            };

            $heading = sprintf(
                '<div class="log-heading"><strong><i>%s %s %s (%s - %s)</i></strong></div>',
                date(SET_DATE_FORMAT, (int)$row['create_date']),
                TXT_260,
                htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($rowUser['first_name'] ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($role, ENT_QUOTES, 'UTF-8')
            );

            // Sanitize and format log text
            $text = htmlspecialchars((string)$row['log_text'], ENT_QUOTES, 'UTF-8');
            $text = nl2br($text);
            $text = str_replace('  ', '&nbsp;&nbsp;', $text);

            $itemsHtml .= '<div class="log-item">' . $heading . '<div class="log-text">' . $text . '</div></div>';
        }
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
    $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    $rows = [];
    while ($r = Database::fetchArray($result)) {
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
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    // Set the selected option to 'All item definitions'
    $listValues[0] = '';
    $listDisplayValues[0] = TXT_105;
    $i = 1;
    while ($row = Database::fetchArray($result)) {
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
    $sql = Database::sqlSelect('groups', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    // Set the selected option to 'All item definitions'
    $listValues[0] = '';
    $listDisplayValues[0] = TXT_104;
    $i = 1;
    while ($row = Database::fetchArray($result)) {
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
    $sql = Database::sqlSelect('item_types', $columnArray);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    // Set the selected option to 'All item definitions'
    $i = 0;
    if ($showAll == true) {
        $listValues[0] = '';
        $listDisplayValues[0] = TXT_100;
        $i = 1;
    }
    $a = 0;
    // Display all item types or only a subset based on the filter array
    while ($row = Database::fetchArray($result)) {
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
    $sql = Database::sqlDelete('core_log', $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
    showLogEntry($itemID);
}

function showSecurityAssignment(string $itemID): void
{
    // Build form action
    $action = MAN_BASE_URL . '&option=update_security&item_id=' . $itemID;

    // Fetch current group membership (use parameterised query)
    $sql = "SELECT group_security FROM items WHERE item_id = :item_id";
    $result = Database::query($sql, DSN, SET_SHOW_SQL, ['item_id' => $itemID]);
    $row = Database::fetchArray($result);
    $groupArray = [];
    if (!empty($row['group_security'])) {
        $groupArray = array_map('trim', explode('}-{', (string)$row['group_security']));
    }

    // Fetch all groups (ordered)
    $sql = "SELECT group_id, group_name, description FROM groups ORDER BY group_name";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    $fields = [];
    while ($row = Database::fetchArray($result)) {
        $groupId = (string)$row['group_id'];
        $groupName = (string)$row['group_name'];
        $description = (string)$row['description'];

        // Determine whether checkbox should be pre-checked
        $selectedValue = in_array($groupId, $groupArray, true) ? $groupId : '';

        // Render checkbox with an empty label (group name will be the field label)
        $checkbox = RenderViews::buildCheckBox($groupId, $groupId, $selectedValue, 'form-control', '');

        // Use group name as the visible label and description + checkbox as the element
        $fields[$groupName] = ($description !== '' ? $description . ' ' : '') . $checkbox;
    }

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

    // Build the legacy delimiter format '}-\{val1\}-\{val2\}-\{'
    $groups = '';
    if (!empty($selected)) {
        $selected = array_map(fn($v) => (string)$v, $selected);
        $groups = '}-{' . implode('}-{', $selected) . '}-{';
    }

    // Execute any configured actions for this update
    Actions::executeAction($itemID, 'update_item', false);

    // Parameterised update to avoid SQL injection
    $sql = "UPDATE items SET group_security = :group_security WHERE item_id = :item_id";
    try {
        Database::query($sql, DSN, SET_SHOW_SQL, ['group_security' => $groups, 'item_id' => $itemID]);
    } catch (\Throwable $e) {
        // Render an error response (keeps behaviour simple and user-friendly)
        RenderViews::buildResponse(TXT_321 ?? 'Update failed', $e->getMessage());
        return;
    }

    // Build return URL and render success response via the modern helper
    $logEntry   = (SET_LOG_ENTRY === 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS === 'yes') ? '&attachments=yes' : '';
    $controller = $_GET['controller'] ?? '';
    $url = 'index.php?controller=' . $controller . '&subcontroller=item_management_manage&option=show_item&item_id=' . $itemID . $logEntry . $attachments;

    RenderViews::buildResponse(TXT_320, RenderViews::buildURL($url, TXT_353, 'URL'));
}

function showMyItems($userID, $itemID = '')
{
    // Get all items by user assignment
    $sql = "SELECT item_id FROM items WHERE (user_security = '" . $_SESSION['access_user_id'] . "' OR creator_security = '" . $_SESSION['access_user_id'] . "')";
    $userItemArray = Database::buildArray($sql, DSN, SET_SHOW_SQL);
    // Get all items by group assignment
    $sql = "SELECT groups FROM group_members WHERE user_id = '" . $_SESSION['access_user_id'] . "'";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);
    $groupArray = explode('}-{', $row['groups']);
    foreach ($groupArray as $group) {
        $sql = "SELECT item_id FROM items WHERE group_security LIKE '%}-{" . $group . "}-{%'";
        if (Database::buildArray($sql, DSN, SET_SHOW_SQL)) {
            $groupItemArray = Database::buildArray($sql, DSN, SET_SHOW_SQL);
        }
    }
    if (!is_array($userItemArray)) {
        $userItemArray[] = '';
    }
    if (!is_array(@$groupItemArray)) {
        $groupItemArray[] = '';
    }
    $mergedArray = array_merge($groupItemArray, $userItemArray);
    $itemArray = array_unique($mergedArray);
    showItems($itemArray, $itemID, 'item_id DESC');
}

function addAttachment($itemID, $showAttachments = true, $executeAction = true)
{
    // Upload the attachment
    $fileArray = File::uploadItemAttachment(SET_MAX_ATTACHMENT, 'attachment', time(), SET_ATTACHMENTS_PATH);
    // Add attachment
    $columnArray['id'] = Database::newID('item_attachments', 'id');
    //get new id value;
    $columnArray['item_id'] = $itemID;
    $columnArray['create_date'] = time();
    $columnArray['file_name'] = $fileArray['time_name'];
    $columnArray['file_type'] = $fileArray['type'];
    $columnArray['file_size'] = $fileArray['size'];
    $columnArray['added_by'] = $_SESSION['access_user_id'];
    // Insert into attachment table
    $sql = Database::sqlInsert('item_attachments', $columnArray);
    Database::query($sql, DSN, SET_SHOW_SQL);
    // Execute actions

    if (($columnArray['file_size'] > 0) && ($executeAction == true)) {
        if (!isset($_POST['creator_security'])) {
            $sql = "SELECT * from items WHERE item_id = '" . $itemID . "'";
            $result = Database::query($sql, DSN, SET_SHOW_SQL);
            $row = Database::fetchArray($result);
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
    //Show attachments in table
    if ($_SESSION['access_role_id'] <= 2) {
        $headingArray = array(TXT_394, TXT_395, TXT_396, TXT_397, TXT_198);
    } else {
        $headingArray = array(TXT_394, TXT_395, TXT_396, TXT_397);
    }
    $tableRows = RenderViews::tableData('', '', '', '', 'tdcHeading', $headingArray, 'row');
    $sql = "SELECT * FROM item_attachments WHERE item_id = '$itemID'";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    if (Database::numRows($result) == 0) {
        if ($_SESSION['access_role_id'] <= 2) {
            $tableRows .= RenderViews::tableData('5', '', 'left', 'trc1', '', array(TXT_399), 'row');
        } else {
            $tableRows .= RenderViews::tableData('4', '', 'left', 'trc1', '', array(TXT_399), 'row');
        }
    } else {
        $i = 0;
        while ($row = Database::fetchArray($result)) {
            $fileNameURL = RenderViews::buildURL(MAN_BASE_URL . '&option=download_attachment&id=' . $row['id'], str_replace('_', ' ', substr($row['file_name'], 11)), 'URL');
            $deleteURL = RenderViews::buildURL(MAN_BASE_URL . '&option=delete_attachment&item_id=' . $itemID . '&id=' . $row['id'], TXT_47, 'URL');
            if ($_SESSION['access_role_id'] <= 2) {
                $attachmentArray = array($fileNameURL, $row['file_type'], $row['file_size'] / 1000, date(SET_DATE_FORMAT, $row['create_date']), $deleteURL);
            } else {
                $attachmentArray = array($fileNameURL, $row['file_type'], $row['file_size'] / 1000, date(SET_DATE_FORMAT, $row['create_date']));
            }
            $class = RenderViews::setOddEvenClass($i, 'trc1', 'trc2');
            $tableRows .= RenderViews::tableData('', '', 'left', $class, '', $attachmentArray, 'row');
            $i++;
        }
    }
    $html = RenderViews::buildStartForm(MAN_BASE_URL . '&option=add_attachment&item_id=' . $itemID, 'POST', 'form-horizontal', '', '', 'multipart/form-data');
    $html .= RenderViews::table('100%', '0', '5', '0', 'tcBorder', $tableRows);
    $tableRows = RenderViews::tableData('2', '', array('left'), '', 'tdcHeading', array(TXT_393), 'row');
    $attachmentField = RenderViews::buildFileInput('attachment', SET_MAX_ATTACHMENT * 1000000, '', 'form-control');
    $tableRows .= RenderViews::tableData('', '', array('left'), '', 'tdc1', array($attachmentField), 'row');
    $html .= '
<br>
';
    $html .= RenderViews::table('100%', '0', '5', '0', 'tcBorder', $tableRows);
    $buttonArray[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_74);
    $buttonArray[] = RenderViews::buildFormButton('reset', 'reset', TXT_75);
    $endForm = RenderViews::buildEndFormWithButtons($buttonArray);
    $tableRows = RenderViews::tableData('', '', '', '', 'tdc1', array($endForm), 'row');
    $html .= RenderViews::table('95%', '0', '0', '0', 'tableIndent', $tableRows);
    $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $showLog = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $url = RenderViews::buildURL(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $showLog . $attachments, TXT_385, 'URL');
    define('HEADING', TXT_389 . ' - ' . $url);
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function downloadAttachment($id)
{
    // Get attachment information
    $condition = "WHERE id='$id'";
    $sql = Database::sqlSelect('item_attachments', array('*'), $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);
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
    $sql = Database::sqlSelect('item_attachments', array('file_name'), $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);
    // Check for dependant items and only delete file if 1 item is associated
    $condition = "WHERE file_name='" . $row['file_name'] . "'";
    $sql = Database::sqlSelect('item_attachments', array('file_name'), $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    if (Database::numRows($result) == 1) {
        unlink(SET_ATTACHMENTS_PATH . $row['file_name']);
    }
    // Delete entry from attachment table
    $condition = "WHERE id='$id'";
    $sql = Database::sqlDelete('item_attachments', $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
    showAttachments($itemID);
}

function deleteItem($itemID)
{
    // Delete item
    $sql = "DELETE FROM items WHERE item_id = '$itemID'";
    Database::query($sql, DSN, SET_SHOW_SQL);
    // Delete logs
    $sql = "DELETE FROM core_log WHERE item_id = '$itemID'";
    Database::query($sql, DSN, SET_SHOW_SQL);
    // Delete attachments
    $sql = "DELETE FROM item_attachments WHERE item_id = '$itemID'";
    Database::query($sql, DSN, SET_SHOW_SQL);
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
        showItemAdd($_POST['item_type_id'], $_POST);
        break;
    case 'add_item' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
        addItem();
        break;
    case 'show_item' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        showItem($_GET['item_id'], $_POST, $_GET['log_entry'], $_GET['attachments']);
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
        showMyItems($_SESSION['access_user_id']);
}
