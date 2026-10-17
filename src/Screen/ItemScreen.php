<?php
namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Application\ApplicationAccess;
use Adlexone\Application\ApplicationSettings;
use Adlexone\support\Database;
use Adlexone\support\Actions;
use Adlexone\support\AttachmentUpload;
use Adlexone\Html\FieldControl;
use Adlexone\support\FieldTypes;
use Adlexone\support\MenuOptions;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Http\Page;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Text\Wording;
use Adlexone\Html\Html;

/**
 * Screen formerly loaded from legacy/.
 */
final class ItemScreen
{
    public static function open(): void
    {
        if (Page::current()->inApplication()) {
            ApplicationAccess::requireUse();
        } elseif (!Access::can(Permission::ADMIN_ITEMS)) {
            Access::deny();
        }









        /**
         * Controller Constants
         */
        if (!defined('MAN_BASE_URL')) {
            define('MAN_BASE_URL', '?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage');
        }


















































        /**
         * Logic to render the appropriate template or call wrapper functions
         * Option is captured from the value selected via a hyperlink
         */
        switch (@$_GET['option']) {
            case 'my_items' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                self::showMyItems($_SESSION['access_user_id'], @$_GET['item_id']);
                break;
            case 'show_item_types' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
                self::showItemTypes(@$_GET['default_item_type']);
                break;
            case 'new_item' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
                $itemTypeId = $_POST['item_type_id'] ?? $_GET['item_type_id'] ?? '';
                if ($itemTypeId === '' || $itemTypeId === null) {
                    $target = \Adlexone\Http\Router::continueUrl('items') . '&option=show_item_types';
                    $defaultType = trim((string) ($_GET['default_item_type'] ?? ''));
                    if ($defaultType === '' && Page::current()->inApplication()) {
                        $defaultType = ApplicationSettings::defaultItemTypeId(Page::current()->applicationSlug());
                    }
                    if ($defaultType !== '') {
                        $target .= '&default_item_type=' . rawurlencode($defaultType);
                    }
                    header('Location: ' . $target);
                    exit;
                }
                self::showItemAdd($itemTypeId, $_POST);
                break;
            case 'add_item' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
                self::addItem();
                break;
            case 'show_item' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                self::showItem($_GET['item_id'] ?? '', $_POST, $_GET['log_entry'] ?? 'no', $_GET['attachments'] ?? 'no');
                break;
            case 'log_entry' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
                self::showLogEntry($_GET['item_id']);
                break;
            case 'add_log_entry' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
                self::addLogEntry($_POST['item_id']);
                break;
            case 'delete_log' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
                self::deleteLog($_GET['item_id'], $_GET['log_item_sequence']);
                break;
            case 'update_item' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
                self::updateItem($_POST['item_id']);
                break;
            case 'change_security' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 3);
                self::showSecurityAssignment($_GET['item_id']);
                break;
            case 'update_security' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 3);
                self::updateSecurityAssignment($_GET['item_id']);
                break;
            case 'show_attachments' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                self::showAttachments($_GET['item_id']);
                break;
            case 'add_attachment' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 4);
                self::addAttachment($_GET['item_id']);
                break;
            case 'download_attachment' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                self::downloadAttachment($_GET['id']);
                break;
            case 'delete_attachment' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
                self::deleteAttachment($_GET['id'], $_GET['item_id']);
                break;
            case 'delete_item' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
                self::deleteItem($_GET['item_id']);
                break;
            case 'transform_item' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
                self::transformItem($_POST['item_id'], $_POST['transform_type'], $_POST['item_type_id']);
                break;
            default :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                self::showMyItems($_SESSION['access_user_id']);
        }
    }
    /**
     * Whether this item type may be used in the current application shell.
     */
    public static function applicationAllowsItemType(int $typeId): bool
    {
        if (!Page::current()->inApplication()) {
            return true;
        }
        return ApplicationSettings::allowsItemType(Page::current()->applicationSlug(), $typeId);
    }

    /**
     * True when the signed-in user may open the item (owner, creator, admin, or shared group).
     */
    public static function userCanAccessItem(int $itemId): bool
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
    public static function syncItemGroupsFromSecurity(int $itemId, ?string $encoded): void
    {
        GroupMembership::setItemGroups($itemId, GroupMembership::parseDelimited($encoded));
    }

    /**
     * Checkbox list of groups. Checked ids are the item type's groups, or the boxes the person already ticked.
     *
     * @param list<int> $selectedIds
     */
    public static function itemGroupPicker(array $selectedIds): string
    {
        $selected = array_map('strval', $selectedIds);
        $rows = Database::select('groups', ['group_id', 'group_name', 'description'], '', [], 'group_name ASC');
        $list = '<div class="group-security-list">';
        foreach ($rows as $row) {
            $groupId = (string) $row['group_id'];
            $checked = in_array($groupId, $selected, true) ? $groupId : '';
            $list .= '<div class="group-security-item">'
                . Form::checkbox('group_' . $groupId, $groupId, $checked, (string) $row['group_name']);
            $description = trim((string) ($row['description'] ?? ''));
            if ($description !== '') {
                $list .= '<div class="record-list__meta">' . Html::e($description) . '</div>';
            }
            $list .= '</div>';
        }
        if ($rows === []) {
            $list .= '<p class="record-list__empty">No groups yet.</p>';
        }
        $list .= Form::hidden('groups_posted', '1');
        $list .= '</div>';

        return $list;
    }

    /**
     * @param list<int> $ids
     */
    private static function groupNames(array $ids): string
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return '';
        }
        $names = [];
        foreach (Database::select('groups', ['group_name'], 'group_id IN (' . implode(',', $ids) . ')', [], 'group_name ASC') as $row) {
            $names[] = (string) $row['group_name'];
        }

        return implode(', ', $names);
    }

    /**
     *
     * This routine will double check that the ID's are for knowledgebase items and if they are update
     * the database to say that they have been returned
     *
     * @param $knowledgeIds
     * @return none
     */
    public static function updateKnowledgeCount($knowledgeIds): void
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
    public static function showItems($itemIDArray, $itemID = '', $orderSQL = '')
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
                . Access::outputIfRoleAllowed('<a href="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_id\'; document.repform.submit()" class="URL"><strong>' . Wording::get('LA_102', 'TXT_102') . '</strong></a>', $_SESSION['access_role_id'], 4)
                . ' '
                . Access::outputIfRoleAllowed('<a href="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_title\'; document.repform.submit()" class="URL"><strong>' . Wording::get('LA_84', 'TXT_84') . '</strong></a>', $_SESSION['access_role_id'], 4)
                . ' '
                . Access::outputIfRoleAllowed('<a href="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'create_date\'; document.repform.submit()" class="URL"><strong>' . TXT_225 . '</strong></a>', $_SESSION['access_role_id'], 4)
                . ' '
                . Access::outputIfRoleAllowed('<a href="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_type_id\'; document.repform.submit()" class="URL"><strong>' . Wording::get('LA_226', 'TXT_226') . '</strong></a>', $_SESSION['access_role_id'], 4)
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
                $action = Access::outputIfRoleAllowed('<a href="' . MAN_BASE_URL . '&option=show_attachments&item_id=' . $row['item_id'] . '" class="URL">' . TXT_389 . '</a>', $_SESSION['access_role_id'], 4);
                $action .= Access::outputIfRoleAllowed(' - <a href="' . MAN_BASE_URL . '&option=change_security&item_id=' . $row['item_id'] . '" class="URL">' . TXT_28 . '</a>', $_SESSION['access_role_id'], 3);
                $action .= Access::outputIfRoleAllowed(' - <a href ="' . MAN_BASE_URL . '&option=log_entry&item_id=' . $row['item_id'] . '" class="URL">' . TXT_246 . '</a>', $_SESSION['access_role_id'], 4);
                $action .= Access::outputIfRoleAllowed(' - ' . Blocks::link('?controller=full_page_view&option=print_item&item_id=' . $row['item_id'], TXT_625, 'URL', '', '', '_blank'), $_SESSION['access_role_id'], 5);
                $action .= Access::outputIfRoleAllowed(
                    ' - ' . Blocks::link(
                        MAN_BASE_URL . '&option=delete_item&item_id=' . $row['item_id'],
                        TXT_315,
                        '',
                        'URL',
                        'onclick="' . Form::confirm(TXT_400) . '"'
                    ),
                    $_SESSION['access_role_id'],
                    2
                );
                $listHtml .= Form::fields([
                    Wording::get('LA_102', 'TXT_102') => (string)$row['item_id'],
                    Wording::get('LA_84', 'TXT_84') => '<a href ="' . MAN_BASE_URL . '&option=show_item&item_id=' . $row['item_id'] . $logEntry . $attachments . '" class="URL">' . $title . '</a>',
                    TXT_225 => date(SET_DATE_FORMAT, $row['create_date']),
                    Wording::get('LA_226', 'TXT_226') => (string)$itemTypeName,
                    TXT_388 => $action,
                ]);
                $listHtml .= Blocks::separator();
                unset($action);
            }
        } else {
            $listHtml = Form::fields(['' => Html::e(TXT_115)]);
        }
        $paginationHTML = '';
        $hidden = '';
        if ($countBoolean) {
            $paginationHTML = '<div class="pagination pagination-centered "><ul>';
            $pageset = (isset($_POST['pageset'])) ? $_POST['pageset'] : 1;
            if ($pageset > 1) {
                $paginationHTML .= Access::outputIfRoleAllowed('<li><a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value--; document.repform.submit()">Prev</a>', $_SESSION['access_role_id'], 5);
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
                    $paginationHTML .= Access::outputIfRoleAllowed('<li><a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value=' . $i . '; document.repform.submit()"> ' . $i . ' </a></li>', $_SESSION['access_role_id'], 5);
                }
            }
            if ($pageset == $iend) {
                $paginationHTML .= "";
            } else {
                $paginationHTML .= Access::outputIfRoleAllowed('<li><a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value++; document.repform.submit()">Next</a></li>', $_SESSION['access_role_id'], 5);
            }
            if ($itemID == '') {
                $paginationHTML .= '</ul></div>';
                $hidden .= Form::hidden('pageset', (string)$pageset);
            }
        }
        if ($itemID == '') {
            $_SESSION['item_sql'] = $origSQL;
            $hidden .= Form::hidden('currsort', (string)$newsort);
            $hidden .= Form::hidden('search', (string)($pageset ?? ''));
            $hidden .= Form::hidden('countrecs', (string)$countrecs);
            $hidden .= Form::hidden('search_type', (string)($_GET['option'] ?? ''));
        }

        $html = Form::form(
            Wording::get('LA_44', 'TXT_44'),
            '?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=my_items',
            ['' => $sortLinks . $listHtml . ($paginationHTML ?? '') . ($hidden ?? '')],
            [],
            ['name' => 'repform', 'id' => 'repform']
        );
        Page::current()->setBody($html);
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

    public static function showItemTypes($defaultItemType = '')
    {
        $fields = [];

        // Get all item types
        $condition = "WHERE enabled = 'Yes' ORDER BY item_type_name ASC";
        $result = Database::select('item_types', ['item_type_id', 'item_type_name'], $condition);

        if (Page::current()->inApplication()) {
            $allowed = ApplicationSettings::allowedItemTypeIds(Page::current()->applicationSlug());
            $result = array_values(array_filter(
                $result,
                static fn (array $row): bool => in_array((int) $row['item_type_id'], $allowed, true)
            ));
            if ($defaultItemType === '' || $defaultItemType === null) {
                $defaultItemType = ApplicationSettings::defaultItemTypeId(Page::current()->applicationSlug());
            }
        }

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
            self::showItemAdd($valueArray[0], $_POST);
            return;
        }

        if (!$noItemTypes) {
            $inApplication = Page::current()->inApplication();
            $fields[$inApplication ? 'What is this' : Wording::get('LA_66', 'TXT_66')] = Form::select('item_type_id', $valueArray, $displayArray, $defaultItemType);
            Page::current()->setBody(Form::form(
                $inApplication ? 'New' : Wording::get('LA_46', 'TXT_46'),
                MAN_BASE_URL . '&option=new_item',
                $fields,
                [Form::button('submit', 'submit_button', $inApplication ? 'Continue' : TXT_69)]
            ));
            return;
        }

        if (Page::current()->inApplication()) {
            Page::current()->setBody(Blocks::emptyState([
                'illustration' => 'empty-work',
                'title' => 'Nothing to add yet',
                'copy' => 'This application does not have anything new to open.',
            ]));
            return;
        }
        View::respond(TXT_341);
    }

    /**
     * Displays the "Add Item" form for a given item type and populates values.
     *
     * This function is responsible for rendering a form to add a new item of a specific type.
     * - On the first load (when `$values['user_security']` is not set), it retrieves default values
     *   from the database for the specified item type.
     * - If the form is being reloaded (e.g., after a validation error), it uses the `$values` array
     *   (typically `$_POST` or a session-stored array) to pre-populate the form fields.
     * - The form is built using modern, semantic HTML with `Form` helpers for inputs, dropdowns,
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
    public static function showItemAdd($itemTypeID, $values)
    {
        if (!self::applicationAllowsItemType((int) $itemTypeID)) {
            Access::deny();
        }
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
            Page::current()->setBody(Blocks::vertical([
                [
                    'title' => TXT_68,
                    'html' => '<p class="record-list__empty">' . Html::e(TXT_68) . '</p>'
                        . '<div class="form-actions">'
                        . Blocks::link($picker, TXT_69, '', 'btn btn--primary btn--sm')
                        . '</div>',
                ],
            ]));
            return;
        }
        $itemTypeName = $row['item_type_name'];
        $inApplication = Page::current()->inApplication();
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
        $peopleHidden = '';
        if ($itemRole < 4) {//Admins and manager can set
            // If the item type does not specify a default user use the logged in user
            if (!isset($values['user_security'])) {
                if (!isset($itemTypeFields['user_security'])) {
                    $itemTypeFields['user_security'] = $_SESSION['access_user_id'];
                }
                $itemTypeFields['creator_security'] = $_SESSION['access_user_id'];
            }
            if ($inApplication) {
                $peopleHidden = Form::hidden('creator_security', (string) $itemTypeFields['creator_security'])
                    . Form::hidden('user_security', (string) $itemTypeFields['user_security']);
            } else {
                // Security is setup by default from the item
                $columnArray = array('user_id', 'user_name');
                $condition = "WHERE lastactive <> 'inactive' ORDER BY user_name ASC";
                foreach (Database::select('users', $columnArray, $condition) as $row) {
                    $userIDArray[] = $row['user_id'];
                    $userArray[] = $row['user_name'];
                }// while
                $fields[Wording::get('LA_551', 'TXT_551')] = Form::select('creator_security', $userIDArray, $userArray, $itemTypeFields['creator_security']);
                $columnArray = array('user_id', 'user_name');
                $condition = "WHERE lastactive <> 'inactive' AND role <= " . SET_OWNER_MENU . " ORDER BY user_name ASC";
                foreach (Database::select('users', $columnArray, $condition) as $row) {
                    $filterdUserIDArray[] = $row['user_id'];
                    $filteredUserArray[] = $row['user_name'];
                }// while
                $fields[Wording::get('LA_269', 'TXT_269')] = Form::select('user_security', $filterdUserIDArray, $filteredUserArray, $itemTypeFields['user_security']);
            }
        } elseif ($inApplication) {
            $ownerId = ($itemTypeFields['user_security'] ?? '') === ''
                ? (string) $_SESSION['access_user_id']
                : (string) $itemTypeFields['user_security'];
            $peopleHidden = Form::hidden('creator_security', (string) $_SESSION['access_user_id'])
                . Form::hidden('user_security', $ownerId);
        } else {
            $columnArray = array('user_name');
            $condition = "WHERE user_id ='" . $_SESSION['access_user_id'] . "'";
            $row = Database::first('users', $columnArray, $condition);
            $fields[Wording::get('LA_551', 'TXT_551')] = Form::hidden('creator_security', $_SESSION['access_user_id']) . $row['user_name'];
            if ($itemTypeFields['user_security'] == '') {
                $columnArray = array('user_name');
                $condition = "WHERE user_id ='" . $_SESSION['access_user_id'] . "'";
                $row = Database::first('users', $columnArray, $condition);
                $fields[Wording::get('LA_269', 'TXT_269')] = Form::hidden('user_security', $_SESSION['access_user_id']) . $row['user_name'];
            } else {
                $columnArray = array('user_name');
                $condition = "WHERE user_id ='" . $itemTypeFields['user_security'] . "'";
                $row = Database::first('users', $columnArray, $condition);
                $fields[Wording::get('LA_269', 'TXT_269')] = Form::hidden('user_security', $itemTypeFields['user_security']) . $row['user_name'];
            }
        }
        // Groups the record belongs to. The item type's groups start checked.
        $selectedGroups = isset($values['user_security'])
            ? GroupMembership::idsFromPost($values)
            : GroupMembership::itemTypeGroupIds((int) $itemTypeID);
        if (!$inApplication) {
            $fields[TXT_270] = self::itemGroupPicker($selectedGroups);
        }
        $fields[$inApplication ? 'Name' : Wording::get('LA_84', 'TXT_84')] = Form::text('item_title', $itemTypeFields['item_title'] ?? '');    // Setup custom field display
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
                case FieldTypes::TEXT_BOX:
                case 'password':
                case 'hidden':
                case FieldTypes::TEXT_AREA:
                case 'URL':
                case 'dynamicURL':
                case 'workerField':
                    $control = FieldControl::render(
                        (string) $row['field_type'],
                        ($row['field_type'] === 'workerField' ? 'worker_field_' : 'custom_field_') . $row['custom_field_id'],
                        $defaultValue,
                        $row
                    );
                    if ($control !== null) {
                        $fields[$row['custom_field_name']] = $control;
                    }
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
                    $fields[$row['custom_field_name']] = Form::checkbox('custom_field_' . $row['custom_field_id'], $row['custom_field_id'], '');
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
                    $fields[$row['custom_field_name']] = Form::select('worker_field_menu_' . $row['custom_field_id'], @$menuArray, @$menuArray, $defaultValueArray['default_value']);
                    unset($menuArray);
                    break;
                default :
                    $control = FieldControl::render(
                        (string) $row['field_type'],
                        'custom_field_' . $row['custom_field_id'],
                        $defaultValue,
                        $row
                    );
                    if ($control !== null) {
                        $fields[$row['custom_field_name']] = $control;
                    }
                    break;
            }
        }

        if ($inApplication) {
            $fields['Access'] = self::itemGroupPicker($selectedGroups);
        }
        $fields[''] = Form::hidden('item_type_id', (string)$itemTypeFields['item_type_id']) . $peopleHidden;
        $formOptions = [];
        if (ADD_ATTACHMENTS == 'yes') {
            $fields[TXT_393] = Form::file('attachment', SET_MAX_ATTACHMENT * 1000000);
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
        $buttons[] = Form::button('submit', 'submit_button', TXT_57, $javascript);
        $createHeading = $inApplication
            ? 'New ' . $itemTypeName
            : Wording::get('LA_46', 'TXT_67') . ' — ' . $itemTypeName;
        $bodyContent = Form::form($createHeading,MAN_BASE_URL. '&option=add_item',$fields,$buttons,$formOptions);
        Page::current()->setBody($bodyContent);
    }

    /**
     * Shows the item for modification.
     *
     * @param string $itemID Item ID
     */
    public static function showItem($itemID, $values = '', $addLogEntry = 'no', $attachments = 'no')
    {
        //Check to see if the item exists
        $sql = "SELECT item_id FROM items WHERE item_id='" . $itemID . "'";
            $result = Database::rows($sql);
        if (count($result) == 0) {
           View::respond(TXT_616);
        } else {
            $i = self::userCanAccessItem((int) $itemID) ? 1 : 0;
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
                $inApplication = Page::current()->inApplication();
                $itemField = [];
                $peopleHidden = '';
                if ($inApplication) {
                    $peopleHidden = Form::hidden('creator_security', (string) ($itemFields['creator_security'] ?? ''))
                        . Form::hidden('user_security', (string) ($itemFields['user_security'] ?? ''));
                } elseif ($_SESSION['access_role_id'] < 4) {//Managers, admins and oneorzero admins and global admins
                    // Build table array
                    // Security is setup by default from the item
                    $columnArray = array('user_id', 'user_name');
                    $condition = "ORDER BY user_name ASC";
                    foreach (Database::select('users', $columnArray, $condition) as $row) {
                        $userIDArray[] = $row['user_id'];
                        $userArray[] = $row['user_name'];
                    }// while
                    $itemField[Wording::get('LA_551', 'TXT_551')] = Form::select('creator_security', $userIDArray, $userArray, $itemFields['creator_security']);
                    $columnArray = array('user_id', 'user_name');
                    $condition = "WHERE role <= " . SET_OWNER_MENU . " ORDER BY user_name ASC";
                    foreach (Database::select('users', $columnArray, $condition) as $row) {
                        $filterdUserIDArray[] = $row['user_id'];
                        $filteredUserArray[] = $row['user_name'];
                    }// while
                    $itemField[Wording::get('LA_269', 'TXT_269')] = Form::select('user_security', $filterdUserIDArray, $filteredUserArray, $itemFields['user_security']);
                } else {
                    $columnArray = array('user_name');
                    $condition = "WHERE user_id = '" . $itemFields['creator_security'] . "'";
                    $row = Database::first('users', $columnArray, $condition);
                    $itemField[TXT_551] = Form::hidden('creator_security', $itemFields['creator_security']) . $row['user_name'];
                    $columnArray = array('user_name');
                    $condition = "WHERE user_id = '" . $itemFields['user_security'] . "'";
                    $row = Database::first('users', $columnArray, $condition);
                    $itemField[TXT_269] = Form::hidden('user_security', $itemFields['user_security']) . $row['user_name'];
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
                if (!$inApplication && $itemRole < 4) {
                    // Create group membership list
                    $groupArray = array_values(array_filter(array_map('intval', $itemGroupIds)));
                    $groupMembership = '';
                    if ($groupArray !== []) {
                        $result = Database::select(
                            'groups',
                            ['group_id', 'group_name'],
                            'group_id IN (' . implode(',', $groupArray) . ')'
                        );
                        $names = [];
                        foreach ($result as $row) {
                            $names[] = (string) $row['group_name'];
                        }
                        $groupMembership = implode(', ', $names);
                    }
                    $changeSecurityURL = Blocks::link(
                        '?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=change_security&item_id=' . $itemID,
                        TXT_268,
                        '',
                        'btn btn--sm'
                    );
                    $itemField[TXT_270] = '<div class="criteria-row"><div class="readonly-value">' . Html::e($groupMembership) . '</div>'
                        . $changeSecurityURL . '</div>'
                        . Form::hidden(
                            'group_security',
                            \Adlexone\Data\GroupMembership::toDelimited(
                                \Adlexone\Data\GroupMembership::itemGroupIds((int) $itemID)
                            )
                        );
                }
                $titleLabel = $inApplication ? 'Name' : Wording::get('LA_84', 'TXT_84');
                if ($itemRole >= 4) {//Users and viewers can read only
                    $itemField[$titleLabel] = Form::hidden('item_title', $itemFields['item_title']) . $itemFields['item_title'];
                } else {
                    $itemField[$titleLabel] = Form::text('item_title', $itemFields['item_title']);
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
                            $itemField[$row['custom_field_name']] = $value . Form::hidden('custom_field_' . $row['custom_field_id'], $hiddenValue);
                            break;
                        case FieldTypes::TEXT_BOX:
                        case 'password':
                        case 'hidden':
                        case FieldTypes::TEXT_AREA:
                        case 'workerField':
                            $control = FieldControl::render(
                                (string) $fieldType,
                                ($fieldType === 'workerField' ? 'worker_field_' : 'custom_field_') . $row['custom_field_id'],
                                $value,
                                $row
                            );
                            if ($control !== null) {
                                $itemField[$row['custom_field_name']] = $control;
                            }
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
                            $itemField[$row['custom_field_name']] = Form::checkbox('custom_field_' . $row['custom_field_id'], $value, $row['custom_field_id']);
                            break;
                        case 'URL' :
                            if ($value != '') {
                                $http = (!stristr($value, 'http') and !stristr($value, 'https')) ? 'http://' : '';
                                $url = ' ' . Blocks::link($http . $value, TXT_480, 'URL', SET_IMAGE_PATH . 'hyperlink.png', '', '_blank');
                            }
                            $itemField[$row['custom_field_name']] = Form::text('custom_field_' . $row['custom_field_id'], $value) . @$url;
                            break;
                        case 'dynamicURL' :
                            if ($value != '') {
                                $url = ' ' . Blocks::link(str_replace('<?php echo INSERT; ?>', $value, $row['data']), TXT_480, 'URL', SET_IMAGE_PATH . 'hyperlink.png', '', '_blank');
                            }
                            $itemField[$row['custom_field_name']] = Form::text('custom_field_' . $row['custom_field_id'], $value) . @$url;
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
                            $itemField[$row['custom_field_name']] = Form::select('worker_field_menu_' . $row['custom_field_id'], @$menuArray, @$menuArray, $defaultValueArray['default_value']);
                            unset($menuArray);
                            break;
                        default :
                            $control = FieldControl::render(
                                (string) $fieldType,
                                'custom_field_' . $row['custom_field_id'],
                                $value,
                                $row
                            );
                            if ($control !== null) {
                                $itemField[$row['custom_field_name']] = $control;
                            }
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
                    $buttons[] = Form::button('submit', 'submit_button', TXT_56, $javascript);
                }

                $itemField[''] = Form::hidden('item_type_id', $itemFields['item_type_id']).Form::hidden('item_id', $itemFields['item_id']) . $peopleHidden;
                $recordTitle = trim((string) ($itemFields['item_title'] ?? ''));
                $formTitle = $inApplication
                    ? ($recordTitle !== '' ? $recordTitle : $itemTypeName)
                    : Wording::get('LA_102', 'TXT_102') . ': ' . $itemFields['item_id'] . ' - ' . $itemTypeName;
                $html .= Form::form($formTitle,MAN_BASE_URL . '&option=update_item',$itemField,$buttons);

                if ($inApplication) {
                    $selectedGroups = (is_array($values) && isset($values['groups_posted']))
                        ? GroupMembership::idsFromPost($values)
                        : $itemGroupIds;
                    $html .= self::groupCard(
                        (string) $itemID,
                        (string) ($itemFields['item_type_id'] ?? ''),
                        $selectedGroups,
                        $itemRole < 4
                    );
                    if (SET_LOG_ENTRY === 'yes') {
                        $html .= self::logCard((string) $itemID, $itemRole < 4);
                    }
                    if (SET_ATTACHMENTS === 'yes') {
                        $html .= self::attachmentCard((string) $itemID, $itemRole < 4);
                    }
                }

                //Show transform option
                if (!$inApplication && $_SESSION['access_role_id'] <= 2) {//Administrator, Adlexone Administrator or Global Administrator
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
                    $itemTypeMenu = Form::select('item_type_id', $valueArray, $displayArray, '');
                    $transformationTypeMenu = Form::select('transform_type', array('copy', 'transform'), array(TXT_443, TXT_444), '');
                    $hiddenItemID = Form::hidden('item_id', $itemID);
                    $transformButton[] = Form::button('submit', 'submit_button', TXT_446,  'onClick="javascript:return confirm(\'' . TXT_448 . '\')"');

                    $transinputArray[TXT_445] = $transformationTypeMenu;
                    $transinputArray[TXT_447] = $itemTypeMenu . $hiddenItemID;
                    $html .= Form::form(TXT_446,MAN_BASE_URL . '&option=transform_item',$transinputArray,$transformButton);
                }

                if ($itemID != '' && defined('APP_COUNT_ITEM_TYPE') && $itemFields['item_type_id'] == APP_COUNT_ITEM_TYPE) {
                    updateKnoweldgeCount(array($itemID));
                }

                if ($itemRole < 4) {
                    $html .= \Adlexone\Application\Rules::recordCard((int) $itemID);
                }
                Page::current()->setBody($html);
            } else {
                // Not allowed
                View::respond(TXT_452);
            }
        }
    }

    /**
     * Takes information posted from the new item page and adds the item to the database then renders a succcess page
     * @return Rendered HTML response
     */
    public static function addItem()
    {
        // Return to the form if dynamic actions are still occurring, such as sub buildSelectDropdown selection etc
        if (!isset($_POST['item_type_id'])) {
            // halt adding the item as we may have shortcutted here
            View::respond(TXT_535);
            return;
        }
        if (!self::applicationAllowsItemType((int) $_POST['item_type_id'])) {
            Access::deny();
        }
        if (!isset($_POST['submit_button'])) {
            // Reload form setting values based on posted form values, triggered from javascript submits
            self::showItemAdd($_POST['item_type_id'], $_POST);
        } else {
            if (ADD_ATTACHMENTS == 'yes' && ($_FILES['attachment']['name'] ?? '') != '') {
                // Item row must exist before attachment metadata references it.
            }
            $array = [];
            $array['create_date'] = time();
            $array['core_log_updated'] = time();
            $groupIds = GroupMembership::idsFromPost($_POST);
            unset($_POST['submit_button'], $_POST['reset'], $_POST['MAX_FILE_SIZE'], $_POST['attachment'], $_POST['item_id'], $_POST['_csrf']);
            unset($_POST['groups_posted']);
            foreach (array_keys($_POST) as $postKey) {
                if (str_starts_with((string) $postKey, 'group_')) {
                    unset($_POST[$postKey]);
                }
            }
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
            GroupMembership::setItemGroups($itemId, $groupIds);
            if (ADD_ATTACHMENTS == 'yes' && ($_FILES['attachment']['name'] ?? '') != '') {
                self::addAttachment($itemId, false, false);
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
    public static function transformItem($itemID, $transformType, $targetItemTypeID)
    {
        switch ($transformType) {
            case 'transform' :
                //Alter existing item to new item type (retains old field data if required)
                $columnArray['item_type_id'] = $targetItemTypeID;
                $condition = "WHERE item_id = '" . $itemID . "'";
                Database::update('items', $columnArray, $condition);
                $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
                $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
                View::respond(TXT_450, Blocks::link(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments, TXT_262, 'URL'));
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
                View::respond(TXT_392, Blocks::link(MAN_BASE_URL . '&option=show_item&item_id=' . $newItemColumnArray['item_id'] . $logEntry . $attachments, TXT_262, 'URL'));
                return;
        }

        //Copy to another item

        //Tranform to another item type

    }

    public static function updateItem($itemID)
    {
        // Return to the form if dynamic actions are still occurring, such as sub buildSelectDropdown selection etc
        if (!isset($_POST['item_type_id'])) {
            // halt adding the item as we may have shortcutted here
            View::respond(TXT_535);
            return;
        } elseif (!isset($_POST['submit_button'])) {
            // Reload form setting values based on posted form values, triggered from javascript submits
            self::showItem($_POST['item_id'], $_POST, $_GET['log_entry'] ?? 'no', $_GET['attachments'] ?? 'no');
        } else {
            // Remove unwanted posted information
            unset($_POST['submit_button'], $_POST['reset'], $_POST['item_id'], $_POST['_csrf']);
            MenuOptions::collapseRequest($_POST);
            if (isset($_POST['groups_posted'])) {
                unset($_POST['groups_posted']);
                $_POST['group_security'] = \Adlexone\Data\GroupMembership::toDelimited(
                    \Adlexone\Data\GroupMembership::idsFromPost($_POST)
                );
                foreach (array_keys($_POST) as $postKey) {
                    if (preg_match('/^group_\d+$/', (string) $postKey) === 1) {
                        unset($_POST[$postKey]);
                    }
                }
            }
            if (isset($_POST['groups_only'])) {
                unset($_POST['groups_only']);
                Actions::executeAction($itemID, 'update_item', false);
                if (isset($_POST['group_security'])) {
                    self::syncItemGroupsFromSecurity((int) $itemID, (string) $_POST['group_security']);
                }
                if (!self::returnToItem($itemID)) {
                    View::respond(TXT_320, Blocks::link(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID, TXT_353, 'URL'));
                }
                return;
            }
            // Handle the log update
            if (isset($_POST['log_entry']) and $_POST['log_entry'] != '') {
                self::addLogEntry($itemID, false, false);
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
                self::syncItemGroupsFromSecurity((int) $itemID, (string) $fieldPayload['group_security']);
            }
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            $message = ($_POST['item_title'] != '') ? TXT_263 : TXT_272;
            View::respond($message, Blocks::link(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments, TXT_262, 'URL'));
            return;
        }
    }

    /**
     * Creates form for adding new log entry and displays existing log.
     *
     * @param integer $itemID Item ID
     */
    public static function showLogEntry($itemID)
    {
        $showLog = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $backHref = MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $showLog . $attachments;
        $logField[Wording::get('LA_244', 'TXT_244')] = Form::textarea('log_entry', '', SET_FORM_FIELD_HEIGHT);
        $roleIDs = array(2, 3, 4, 5);
        $roleNames = array(TXT_192, TXT_193, TXT_194, TXT_303);
        $logField[TXT_469] = '<div>' . Form::select('role_id', $roleIDs, $roleNames, 5)
            . '<div class="field-picker-type">' . Html::e(TXT_470) . '</div></div>';

        $columnArray = array('*');
        $condition = "WHERE item_id = '" . $itemID . "'";
        $result = Database::select('items', $columnArray, $condition);
        $hidden = '';
        foreach ($result as $row) {
            foreach ($row as $key => $value) {
                if (!is_numeric($key) and $value != '') {
                    $hidden .= Form::hidden($key, $value);
                }
            }
        }
        $logField[' '] = $hidden;
        Page::current()->setBody(Form::form(
            Wording::get('LA_243', 'TXT_243'),
            MAN_BASE_URL . '&option=add_log_entry',
            $logField,
            [
                Form::button('submit', 'submit_button', TXT_74),
                '<a class="btn" href="' . Html::e($backHref) . '">' . Html::e(TXT_385) . '</a>',
            ]
        ) . self::showItemLog($itemID));
    }

    /**
     * Adds the log entry to the database.
     *
     * @param integer $itemID Item ID
     */
    public static function addLogEntry($itemID, $showInformation = true, $executeAction = true)
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
        $logText = trim((string) ($_POST['log_entry'] ?? ''));
        if ($logText === '') {
            if ($showInformation == true && !self::returnToItem($itemID)) {
                self::showLogEntry($itemID);
            }
            return;
        }
        $columnArray['log_text'] = $logText;
        $columnArray['security_id'] = $_SESSION['access_user_id'];
        $columnArray['role_id'] = $_POST['role_id'] ?? 5;
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
            if (self::returnToItem($itemID)) {
                return;
            }
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            View::respond(TXT_248, Blocks::link(MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments, TXT_262, 'URL'));
            return;
        }
    }

    /**
     * Application records stay on the item. Returns true after sending the browser there.
     */
    private static function returnToItem($itemID): bool
    {
        if (!Page::current()->inApplication()) {
            return false;
        }
        header('Location: ' . MAN_BASE_URL . '&item=' . rawurlencode((string) $itemID));
        exit;
    }

    /**
     * Shows the selected items log
     *
     * @param integer $itemID Item ID
     * @return Return the log in html format
     */
    // Refactored snippet: render logs into a vertical card (escape user content, keep line breaks & double-spaces)
    public static function showItemLog($itemID, string $title = '', string $empty = '')
    {
        $blocks = [
            ['title' => $title !== '' ? $title : TXT_146, 'html' => self::logEntriesHtml($itemID, $empty, true)]
        ];

        return Blocks::vertical($blocks);
    }

    private static function logEntriesHtml($itemID, string $empty, bool $withRole): string
    {
        $itemsHtml = '';
        $result = Database::select('core_log', ['*'], 'WHERE item_id = :item_id ORDER BY log_item_sequence DESC', ['item_id' => $itemID]);

        foreach ($result as $row) {
            if ($_SESSION['access_role_id'] > $row['role_id']) {
                continue;
            }
            $userName = '';
            if ((string) $row['security_id'] !== '') {
                $userRow = Database::first('users', ['user_name'], 'WHERE user_id = ' . (int) $row['security_id']);
                $userName = is_array($userRow) ? (string) ($userRow['user_name'] ?? '') : '';
            }
            $role = '';
            if ($withRole) {
                $role = match ((int) $row['role_id']) {
                    0 => TXT_190,
                    1 => TXT_191,
                    2 => TXT_192,
                    3 => TXT_193,
                    4 => TXT_194,
                    5 => TXT_303,
                    default => ''
                };
            }

            $heading = '<div class="log-heading"><strong>'
                . Html::e(date(SET_DATE_FORMAT, (int) $row['create_date']))
                . ($userName !== '' ? ' ' . Html::e((string) TXT_260) . ' ' . Html::e($userName) : '')
                . ($role !== '' ? ' (' . Html::e($role) . ')' : '')
                . '</strong></div>';
            $text = Html::e((string) $row['log_text']);
            $text = nl2br($text);
            $text = str_replace('  ', '&nbsp;&nbsp;', $text);
            $itemsHtml .= '<div class="log-item">' . $heading . '<div class="log-text">' . $text . '</div></div>';
        }

        if ($itemsHtml === '') {
            $emptyText = $empty !== '' ? $empty : (string) TXT_115;
            $itemsHtml = '<p class="record-list__empty">' . Html::e($emptyText) . '</p>';
        }

        return $itemsHtml;
    }

    /**
     * @param list<int> $selectedIds
     */
    private static function groupCard(string $itemID, string $itemTypeId, array $selectedIds, bool $canEdit): string
    {
        if (!$canEdit) {
            $names = self::groupNames($selectedIds);
            $html = $names === ''
                ? '<p class="record-list__empty">Not shared.</p>'
                : '<div class="readonly-value">' . Html::e($names) . '</div>';

            return Blocks::vertical([['title' => 'Access', 'html' => $html, 'class' => 'card--sheet', 'disclosure' => true]]);
        }

        return Form::form(
            'Access',
            MAN_BASE_URL . '&option=update_item',
            [
                '' => self::itemGroupPicker($selectedIds)
                    . Form::hidden('item_id', $itemID)
                    . Form::hidden('item_type_id', $itemTypeId)
                    . Form::hidden('groups_only', '1'),
            ],
            [Form::button('submit', 'submit_button', TXT_56)],
            ['disclosure' => true]
        );
    }

    private static function logCard(string $itemID, bool $canEdit): string
    {
        $entries = self::logEntriesHtml($itemID, 'No notes yet.', false);
        if (!$canEdit) {
            return Blocks::vertical([['title' => 'Notes', 'html' => $entries, 'class' => 'card--sheet', 'disclosure' => true]]);
        }

        $note = preg_replace('/<textarea\b/', '<textarea aria-label="Note"', Form::textarea('log_entry', '', '4'), 1);

        return Form::form(
            'Notes',
            MAN_BASE_URL . '&option=add_log_entry',
            [
                '' => '<div class="form-grid">' . $entries
                    . $note
                    . Form::hidden('item_id', $itemID)
                    . Form::hidden('role_id', '5')
                    . '</div>',
            ],
            [Form::button('submit', 'submit_button', 'Add')],
            ['disclosure' => true]
        );
    }

    public static function showCustomFields(): string
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

            $customFieldList = Form::select('custom_field_' . $a, $listValues, $listDisplayValues, '');
            $customFieldTextBox = Form::text('custom_field_value_' . $a);
            $andOrDisplayValues = [TXT_109, TXT_110];
            $andOr = ['AND', 'OR'];
            $operatorValues = ['=', 'LIKE', '<>', '>', '<'];
            $operatorDisplayValues = [TXT_111, TXT_112, TXT_318, TXT_364, TXT_365];
            $customFieldAndOr = Form::select('custom_field_andor_' . $a, $andOr, $andOrDisplayValues, 'AND');
            $customFieldOperator = Form::select('custom_field_operator_' . $a, $operatorValues, $operatorDisplayValues, '=');

            // Compose a three-column row; inner HTML comes from trusted Form and Blocks helpers
            $elementHtml = '<div class="cf-row">'
                . '<div class="cf-row__field">' . $customFieldList . '</div>'
                . '<div class="cf-row__op">' . $customFieldOperator . ' ' . $customFieldTextBox . '</div>'
                . '<div class="cf-row__join">' . $customFieldAndOr . '</div>'
                . '</div>';

            // Use an invisible, unique label so the grid prints the element without a visible label
            $labelKey = "\u{200B}" . $a;
            $fields[$labelKey] = $elementHtml;
        }

        // Hidden count field (use invisible label to avoid visible label)
        $fields["\u{200B}count"] = Form::hidden('custom_field_count', (string)$a);

        // Return a modern form block containing just the custom-field rows
        return Form::form('', '#', $fields, []);
    }

    public static function showUsers()
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
        return Form::select('user_security', $listValues, $listDisplayValues, '');
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
            $listValues[$i] = $row[0];
            $listDisplayValues[$i] = $row[1];
            $i++;
        }
        // Create the list box
        return Form::select('security_groups', $listValues, $listDisplayValues, '');
    }

    public static function showItemTypeMenu($filterArray = '', $showAll = false)
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
        return Form::select('item_type_id', $listValues, $listDisplayValues, '');
    }

    /**
     * deleteLog()
     *
     * Deletes the selected log item.
     */
    public static function deleteLog($itemID, $logItemSequence)
    {
        // Setup log sql and execute query
        $condition = "WHERE item_id='" . $itemID . "' AND log_item_sequence='" . $logItemSequence . "'";
        Database::delete('core_log', $condition);
        self::showLogEntry($itemID);
    }

    public static function showSecurityAssignment(string $itemID): void
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
                . Form::checkbox($groupId, $groupId, $selectedValue, $groupName);
            if ($description !== '') {
                $list .= '<div class="record-list__meta">' . Html::e($description) . '</div>';
            }
            $list .= '</div>';
        }
        $list .= '</div>';
        $fields = [TXT_270 => $list];

        // Buttons
        $buttons = [
            Form::button('submit', 'submit_button', TXT_74),
            Form::button('reset', 'reset', TXT_75),
        ];

        // Render the form inside a vertical card (title uses the same heading constant)
        $html = Form::form(TXT_322, $action, $fields, $buttons);

        Page::current()->setBody($html);
    }

    public static function updateSecurityAssignment(string $itemID): void
    {
        // Remove unwanted POST variables
        unset($_POST['submit_button'], $_POST['reset']);

        // Collect selected group values, trim and ignore empty entries
        $selected = array_values(array_filter($_POST, fn($v) => trim((string)$v) !== ''));
        $ids = array_map(static fn($v) => (int) $v, $selected);
        $_POST['group_security'] = \Adlexone\Data\GroupMembership::toDelimited($ids);

        // Execute any configured actions for this update
        Actions::executeAction($itemID, 'update_item', false);

        try {
            \Adlexone\Data\GroupMembership::setItemGroups((int) $itemID, $ids);
        } catch (\Throwable $e) {
            // Render an error response (keeps behaviour simple and user-friendly)
            View::respond(TXT_321 ?? 'Update failed', $e->getMessage());
            return;
        }

        // Build return URL and render success response via the modern helper
        $logEntry   = (SET_LOG_ENTRY === 'yes') ? '&log_entry=yes' : '';
        $attachments = (SET_ATTACHMENTS === 'yes') ? '&attachments=yes' : '';
        $url = MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $logEntry . $attachments;

        View::respond(TXT_320, Blocks::link($url, TXT_353, 'URL'));
    }

    public static function showMyItems($userID, $itemID = '')
    {
        $uid = (int) ($_SESSION['access_user_id'] ?? 0);
        $userItemArray = Database::select('items', ['item_id'], 'user_security = ? OR creator_security = ?', [$uid, $uid]);
        $groupItemArray = array_map(
            static fn(int $id): array => ['item_id' => $id],
            \Adlexone\Data\GroupMembership::itemIdsForUser($uid)
        );
        $mergedArray = array_merge($groupItemArray, $userItemArray);
        $itemArray = array_unique($mergedArray, SORT_REGULAR);
        self::showItems($itemArray, $itemID, 'item_id DESC');
    }

    public static function addAttachment($itemID, $showAttachments = true, $executeAction = true)
    {
        // Upload the attachment
        $fileArray = AttachmentUpload::uploadItemAttachment(SET_MAX_ATTACHMENT, 'attachment', time(), SET_ATTACHMENTS_PATH);
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
            if (self::returnToItem($itemID)) {
                return;
            }
            self::showAttachments($itemID);
        }
    }

    private static function attachmentListHtml(string $itemID, string $empty): string
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

        return Blocks::recordList([
            'column' => TXT_394,
            'columns' => [
                ['key' => 'type', 'label' => TXT_395],
                ['key' => 'size', 'label' => TXT_396],
                ['key' => 'opened', 'label' => TXT_397],
            ],
            'empty' => $empty,
            'groups' => [['rows' => $rows]],
        ]);
    }

    private static function attachmentCard(string $itemID, bool $canEdit): string
    {
        $list = self::attachmentListHtml($itemID, 'No files yet.');
        if (!$canEdit) {
            return Blocks::vertical([
                ['title' => 'Attachments', 'html' => $list, 'class' => 'card--sheet', 'disclosure' => true],
            ]);
        }

        $file = preg_replace(
            '/<input(?=[^>]*\bname="attachment")/',
            '<input aria-label="File"',
            Form::file('attachment', (string) (SET_MAX_ATTACHMENT * 1000000)),
            1
        );

        return Form::form(
            'Attachments',
            MAN_BASE_URL . '&option=add_attachment&item_id=' . rawurlencode($itemID),
            [
                '' => '<div class="form-grid">' . $list . $file . '</div>',
            ],
            [Form::button('submit', 'submit_button', 'Add')],
            ['enctype' => 'multipart/form-data', 'disclosure' => true]
        );
    }

    public static function showAttachments($itemID)
    {
        $list = self::attachmentListHtml((string) $itemID, (string) TXT_399);

        $showLog = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $backHref = MAN_BASE_URL . '&option=show_item&item_id=' . $itemID . $showLog . $attachments;
        $fields = [
            '' => $list,
            TXT_393 => Form::file('attachment', (string)(SET_MAX_ATTACHMENT * 1000000)),
        ];
        Page::current()->setBody(Form::form(
            TXT_389,
            MAN_BASE_URL . '&option=add_attachment&item_id=' . $itemID,
            $fields,
            [
                Form::button('submit', 'submit_button', TXT_74),
                '<a class="btn" href="' . Html::e($backHref) . '">' . Html::e(TXT_385) . '</a>',
            ],
            ['enctype' => 'multipart/form-data']
        ));
    }

    public static function downloadAttachment($id)
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

    public static function deleteAttachment($id, $itemID)
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
        if (self::returnToItem($itemID)) {
            return;
        }
        self::showAttachments($itemID);
    }

    public static function deleteItem($itemID)
    {
        $itemID = (int) $itemID;
        Database::run("DELETE FROM items WHERE item_id = '$itemID'");
        Database::run("DELETE FROM core_log WHERE item_id = '$itemID'");
        Database::run("DELETE FROM item_attachments WHERE item_id = '$itemID'");
        ItemFields::deleteForItem($itemID);
        GroupMembership::setItemGroups($itemID, []);
        self::showMyItems($_SESSION['access_user_id']);
    }
}
