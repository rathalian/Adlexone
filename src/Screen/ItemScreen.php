<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Auth\Role;
use Adlexone\Auth\Permission;
use Adlexone\Application\ApplicationAccess;
use Adlexone\Application\ApplicationSettings;
use Adlexone\Database\Database;
use Adlexone\Data\GroupMembership;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Http\Router;
use Adlexone\Html\Form;
use Adlexone\Http\View;

final class ItemScreen
{

    

    

    public static function open(): void
    {
        if (Page::current()->inApplication()) {
            ApplicationAccess::requireUse();
        } elseif (!Access::can(Permission::ADMIN_ITEMS)) {
            Access::deny();
        }

        $option = Request::query('option');
        if (!self::authoriseItemOption($option)) {
            return;
        }

        /**
         * Logic to render the appropriate template or call wrapper functions
         * Option is captured from the value selected via a hyperlink
         */
        switch ($option) {
            case 'my_items' :
                Access::require(Permission::APP_ACCESS);
                ItemList::showMyItems($_SESSION['access_user_id'], Request::query('item_id'));
                break;
            case 'show_item_types' :
                Access::requireEditRecords();
                ItemList::showItemTypes(Request::query('default_item_type'));
                break;
            case 'new_item' :
                Access::requireEditRecords();
                $itemTypeId = Request::posted('item_type_id');
                if ($itemTypeId === '') {
                    $itemTypeId = Request::query('item_type_id');
                }
                if ($itemTypeId === '') {
                    $target = self::recordUrl('option=show_item_types');
                    $defaultType = trim((string) (Request::queryValue('default_item_type') ?? ''));
                    if ($defaultType === '' && Page::current()->inApplication()) {
                        $defaultType = ApplicationSettings::defaultItemTypeId(Page::current()->applicationSlug());
                    }
                    if ($defaultType !== '') {
                        $target .= '&default_item_type=' . rawurlencode($defaultType);
                    }
                    header('Location: ' . $target);
                    exit;
                }
                ItemSheet::showItemAdd($itemTypeId, Request::postedAll());
                break;
            case 'add_item' :
                Access::requireEditRecords();
                ItemWrite::addItem();
                break;
            case 'show_item' :
                Access::require(Permission::APP_ACCESS);
                ItemSheet::showItem(Request::queryValue('item_id') ?? '', Request::postedAll(), Request::queryValue('log_entry') ?? 'no', Request::queryValue('attachments') ?? 'no');
                break;
            case 'log_entry' :
                Access::requireEditRecords();
                self::returnToItem(Request::queryValue('item_id'), '', 'notes');
                ItemLog::showLogEntry(Request::queryValue('item_id'));
                break;
            case 'add_log_entry' :
                Access::requireEditRecords();
                ItemLog::addLogEntry(Request::postedValue('item_id'));
                break;
            case 'delete_log' :
                Access::requireControlRecords();
                ItemLog::deleteLog(Request::queryValue('item_id'), Request::queryValue('log_item_sequence'));
                break;
            case 'update_item' :
                Access::requireEditRecords();
                ItemWrite::updateItem(Request::postedValue('item_id'));
                break;
            case 'update_owner' :
                Access::requireAssignRecords();
                ItemAccess::updateOwner();
                break;
            case 'change_security' :
                Access::requireAssignRecords();
                self::returnToItem(Request::queryValue('item_id'), '', 'access');
                ItemAccess::showSecurityAssignment(Request::queryValue('item_id'));
                break;
            case 'update_security' :
                Access::requireAssignRecords();
                ItemAccess::updateSecurityAssignment(Request::queryValue('item_id'));
                break;
            case 'show_attachments' :
                Access::require(Permission::APP_ACCESS);
                self::returnToItem(Request::queryValue('item_id'), '', 'files');
                ItemFiles::showAttachments(Request::queryValue('item_id'));
                break;
            case 'add_attachment' :
                Access::requireEditRecords();
                ItemFiles::addAttachment(Request::queryValue('item_id'));
                break;
            case 'download_attachment' :
                Access::require(Permission::APP_ACCESS);
                ItemFiles::downloadAttachment(Request::queryValue('id'));
                break;
            case 'delete_attachment' :
                Access::requireControlRecords();
                ItemFiles::deleteAttachment(Request::queryValue('id'), Request::queryValue('item_id'));
                break;
            case 'delete_item' :
                Access::requireControlRecords();
                ItemWrite::deleteItem(Request::queryValue('item_id'));
                break;
            case 'transform_item' :
                Access::requireControlRecords();
                ItemWrite::transformItem(Request::postedValue('item_id'), Request::postedValue('transform_type'), Request::postedValue('item_type_id'));
                break;
            default :
                Access::require(Permission::APP_ACCESS);
                ItemList::showMyItems($_SESSION['access_user_id']);
        }
    }
    /**
     * Item options that name an existing item, and the record power they need.
     * view opens it, edit changes it and adds a note or file, assign changes
     * the owner and the groups, control deletes it.
     */
    private const ITEM_OPTION_POWER = [
        'show_item' => 'view',
        'show_attachments' => 'view',
        'download_attachment' => 'view',
        'log_entry' => 'edit',
        'add_log_entry' => 'edit',
        'add_attachment' => 'edit',
        'update_item' => 'edit',
        'update_owner' => 'assign',
        'change_security' => 'assign',
        'update_security' => 'assign',
        'delete_attachment' => 'control',
        'delete_log' => 'control',
        'delete_item' => 'control',
        'transform_item' => 'control',
    ];

    /**
     * Stops an item option when the item is missing, hidden from the signed-in
     * person, or needs a stronger role on that item than they hold.
     */
    private static function authoriseItemOption(string $option): bool
    {
        $needed = self::ITEM_OPTION_POWER[$option] ?? null;
        if ($needed === null || $option === 'show_item') {
            return true;
        }
        if ($option === 'download_attachment' || $option === 'delete_attachment') {
            $attachment = Database::first('item_attachments', ['item_id'], 'id = ?', [(int) (Request::queryValue('id') ?? 0)]);
            $itemId = $attachment === null ? 0 : (int) $attachment['item_id'];
        } elseif (in_array($option, ['add_log_entry', 'update_item', 'update_owner', 'transform_item'], true)) {
            $itemId = (int) (Request::postedValue('item_id') ?? 0);
        } else {
            $itemId = (int) (Request::queryValue('item_id') ?? 0);
        }
        if ($itemId <= 0 || !Database::exists('items', 'item_id = ?', [$itemId])) {
            View::respond(TXT_616);
            return false;
        }
        if (!self::userCanAccessItem($itemId) || !Role::levelAtLeast(self::itemLevel($itemId), $needed)) {
            Access::deny();
        }
        return true;
    }

    public static function canReviseItem(int $itemId): bool
    {
        return Role::levelAtLeast(self::itemLevel($itemId), 'assign');
    }

    public static function canEditItem(int $itemId): bool
    {
        return Role::levelAtLeast(self::itemLevel($itemId), 'edit');
    }

    /**
     * The signed-in person's power on one record.
     * Someone who can delete records holds control on every record.
     * The owner can assign. A shared group contributes its own record permission.
     */
    private static function itemLevel(int $itemId): string
    {
        if (Access::canControlRecords()) {
            return 'control';
        }
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $level = 'view';
        foreach (array_intersect(GroupMembership::userGroupIds($userId), GroupMembership::itemGroupIds($itemId)) as $groupId) {
            $level = Role::strongerLevel($level, Role::recordLevelForGroup((int) $groupId));
        }
        if (Database::exists('items', 'item_id = ? AND user_security = ?', [$itemId, $userId])) {
            $level = Role::strongerLevel($level, 'assign');
        }

        return $level;
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
        if (Access::canControlRecords()) {
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
     * Application records stay on the item. Returns true after sending the browser there.
     */
    public static function returnToItem($itemID, string $notice = '', string $sheet = ''): bool
    {
        if (!Page::current()->inApplication()) {
            return false;
        }
        if (trim($notice) !== '') {
            View::flash($notice);
        }
        $url = self::recordUrl('item=' . rawurlencode((string) $itemID));
        if (in_array($sheet, ['access', 'files', 'notes'], true)) {
            $url .= '&sheet=' . $sheet;
        }
        header('Location: ' . $url);
        exit;
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
                $id = $r['custom_field_id'] ?? '';
                $name = $r['custom_field_name'] ?? '';
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
            $listValues[$i] = $row['user_id'];
            $listDisplayValues[$i] = $row['user_name'];
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
            $listValues[$i] = $row['group_id'];
            $listDisplayValues[$i] = $row['group_name'];
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
        $listValues = [];
        $listDisplayValues = [];
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
                if (trim((string) ($filterArray[$a] ?? '')) == (string) ($row['item_type_name'] ?? '')) {
                    $listValues[$i] = $row['item_type_id'];
                    $listDisplayValues[$i] = $row['item_type_name'];
                }
            } else {
                $listValues[$i] = $row['item_type_id'];
                $listDisplayValues[$i] = $row['item_type_name'];
            }
            $a++;
            $i++;
        }
        // Create the list box
        return Form::select('item_type_id', $listValues, $listDisplayValues, '');
    }

    

    /**
     * The next address on this screen. Inside an application that is the application. Outside it is the record list.
     */
    public static function recordUrl(string $query = ''): string
    {
        $page = Page::current();
        if ($page->inApplication()) {
            return Router::applicationUrl($page->applicationSlug(), $page->navId(), $query);
        }

        return Router::manageUrl('records', $query);
    }

}
