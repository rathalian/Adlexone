<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Database\RecordListQuery;
use Adlexone\Auth\Permission;
use Adlexone\Application\ApplicationAccess;
use Adlexone\Application\ApplicationSettings;
use Adlexone\Application\RecordQuery;
use Adlexone\Application\Rules;
use Adlexone\Application\WorkflowFields;
use Adlexone\Auth\Csrf;
use Adlexone\Database\Database;
use Adlexone\support\Actions;
use Adlexone\Data\AttachmentUpload;
use Adlexone\Html\FieldControl;
use Adlexone\Data\FieldCheck;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\FieldValidation;
use Adlexone\Data\MenuOptions;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Http\Router;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Text\Wording;
use Adlexone\Html\Html;

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
                Access::permitUse();
                ItemList::showMyItems($_SESSION['access_user_id'], Request::query('item_id'));
                break;
            case 'show_item_types' :
                Access::permitOpen();
                ItemList::showItemTypes(Request::query('default_item_type'));
                break;
            case 'new_item' :
                Access::permitOpen();
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
                Access::permitOpen();
                ItemSheet::addItem();
                break;
            case 'show_item' :
                Access::permitUse();
                ItemSheet::showItem(Request::queryValue('item_id') ?? '', Request::postedAll(), Request::queryValue('log_entry') ?? 'no', Request::queryValue('attachments') ?? 'no');
                break;
            case 'log_entry' :
                Access::permitOpen();
                self::returnToItem(Request::queryValue('item_id'), '', 'notes');
                ItemLog::showLogEntry(Request::queryValue('item_id'));
                break;
            case 'add_log_entry' :
                Access::permitOpen();
                ItemLog::addLogEntry(Request::postedValue('item_id'));
                break;
            case 'delete_log' :
                Access::permitControl();
                ItemLog::deleteLog(Request::queryValue('item_id'), Request::queryValue('log_item_sequence'));
                break;
            case 'update_item' :
                Access::permitOpen();
                ItemSheet::updateItem(Request::postedValue('item_id'));
                break;
            case 'update_owner' :
                Access::permitEdit();
                ItemAccess::updateOwner();
                break;
            case 'change_security' :
                Access::permitEdit();
                self::returnToItem(Request::queryValue('item_id'), '', 'access');
                ItemAccess::showSecurityAssignment(Request::queryValue('item_id'));
                break;
            case 'update_security' :
                Access::permitEdit();
                ItemAccess::updateSecurityAssignment(Request::queryValue('item_id'));
                break;
            case 'show_attachments' :
                Access::permitUse();
                self::returnToItem(Request::queryValue('item_id'), '', 'files');
                ItemFiles::showAttachments(Request::queryValue('item_id'));
                break;
            case 'add_attachment' :
                Access::permitOpen();
                ItemFiles::addAttachment(Request::queryValue('item_id'));
                break;
            case 'download_attachment' :
                Access::permitUse();
                ItemFiles::downloadAttachment(Request::queryValue('id'));
                break;
            case 'delete_attachment' :
                Access::permitControl();
                ItemFiles::deleteAttachment(Request::queryValue('id'), Request::queryValue('item_id'));
                break;
            case 'delete_item' :
                Access::permitControl();
                ItemSheet::deleteItem(Request::queryValue('item_id'));
                break;
            case 'transform_item' :
                Access::permitControl();
                ItemSheet::transformItem(Request::postedValue('item_id'), Request::postedValue('transform_type'), Request::postedValue('item_type_id'));
                break;
            default :
                Access::permitUse();
                ItemList::showMyItems($_SESSION['access_user_id']);
        }
    }
    /**
     * Item options that name an existing item, with the weakest role number
     * (largest value) that may use them.
     */
    private const ITEM_OPTION_ROLES = [
        'show_item' => 5,
        'show_attachments' => 5,
        'download_attachment' => 5,
        'log_entry' => 4,
        'add_log_entry' => 4,
        'add_attachment' => 4,
        'update_item' => 4,
        'update_owner' => 3,
        'change_security' => 3,
        'update_security' => 3,
        'delete_attachment' => 2,
        'delete_log' => 2,
        'delete_item' => 2,
        'transform_item' => 2,
    ];

    /**
     * Stops an item option when the item is missing, hidden from the signed-in
     * person, or needs a stronger role on that item than they hold.
     */
    private static function authoriseItemOption(string $option): bool
    {
        $needed = self::ITEM_OPTION_ROLES[$option] ?? null;
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
        if (!self::userCanAccessItem($itemId) || self::itemRole($itemId) > $needed) {
            Access::deny();
        }
        return true;
    }

    /**
     * The signed-in person's role on one item: the strongest group role, an
     * administrator role, or manager when they own the item.
     */
    public static function itemRole(int $itemId): int
    {
        $sessionRole = Access::legacyRole();
        if ($sessionRole <= 2) {
            return $sessionRole;
        }
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $role = 5;
        foreach (array_intersect(GroupMembership::userGroupIds($userId), GroupMembership::itemGroupIds($itemId)) as $groupId) {
            $group = Database::first('groups', ['role'], 'group_id = ?', [(int) $groupId]);
            if ($group !== null) {
                $role = min($role, (int) $group['role']);
            }
        }
        if (Database::exists('items', 'item_id = ? AND user_security = ?', [$itemId, $userId])) {
            $role = min($role, 3);
        }
        return $role;
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
        $role = Access::legacyRole();
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
