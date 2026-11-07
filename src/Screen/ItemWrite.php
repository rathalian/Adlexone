<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Application\WorkflowFields;
use Adlexone\Database\Database;
use Adlexone\Application\Actions;
use Adlexone\Data\FieldValidation;
use Adlexone\Data\MenuOptions;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Html\Blocks;
use Adlexone\Http\View;


final class ItemWrite
{


/**
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private static function postedFieldErrors(array $post): array
    {
        $errors = [];
        if (trim((string) ($post['item_title'] ?? '')) === '') {
            $errors['item_title'] = defined('TXT_476') ? (string) TXT_476 : 'Enter a name.';
        }
        $typeId = (int) ($post['item_type_id'] ?? 0);
        $slug = Page::current()->inApplication() ? Page::current()->applicationSlug() : '';

        return $errors + FieldValidation::postedErrors($post, $typeId) + WorkflowFields::dueError($typeId, $slug, $post);
    }


    private static function personExists($id): bool
    {
        return is_numeric($id) && Database::exists('users', 'user_id = ?', [(int) $id]);
    }


/**
     * The items columns a person may set when creating an item.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function newItemColumns(array $post): array
    {
        $me = (int) ($_SESSION['access_user_id'] ?? 0);
        $owner = trim((string) ($post['user_security'] ?? ''));
        $canAssign = Access::canAssignRecords()
            || ItemSheet::canReviseType((int) ($post['item_type_id'] ?? 0));
        if ($canAssign && $owner === '0') {
            $chosen = 0;
        } elseif ($canAssign && self::personExists($owner)) {
            $chosen = (int) $owner;
        } else {
            $chosen = $me;
        }

        return [
            'item_type_id' => (int) ($post['item_type_id'] ?? 0),
            'item_title' => trim((string) ($post['item_title'] ?? '')),
            'creator_security' => $me,
            'user_security' => $chosen,
        ];
    }


/**
     * The items columns a person may change on an existing item.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function itemUpdateColumns(int $itemId, array $post): array
    {
        $columns = [];
        $canRevise = ItemScreen::canReviseItem($itemId);
        if ($canRevise && array_key_exists('item_title', $post)) {
            $columns['item_title'] = trim((string) $post['item_title']);
        }
        if ($canRevise && !Page::current()->inApplication()) {
            foreach (['creator_security', 'user_security'] as $key) {
                if (isset($post[$key]) && self::personExists($post[$key])) {
                    $columns[$key] = (int) $post[$key];
                }
            }
        }
        return $columns;
    }


/**
     * Takes information posted from the new item page and adds the item to the database, then shows the result.
     *
     * @return void
     */
    public static function addItem()
    {
        // Return to the form if dynamic actions are still occurring, such as sub buildSelectDropdown selection etc
        if (!Request::hasPostedKey('item_type_id')) {
            // halt adding the item as we may have shortcutted here
            View::respond(TXT_535);
            return;
        }
        if (!Database::exists('item_types', 'item_type_id = ?', [(int) Request::postedValue('item_type_id')])) {
            View::respond(TXT_535);
            return;
        }
        if (!ItemScreen::applicationAllowsItemType((int) Request::postedValue('item_type_id'))) {
            Access::deny();
        }
        if (!Request::hasPostedKey('submit_button')) {
            // Reload form setting values based on posted form values, triggered from javascript submits
            ItemSheet::showItemAdd(Request::postedValue('item_type_id'), Request::postedAll());
        } else {
            ItemSheet::$formErrors = self::postedFieldErrors(Request::postedAll());
            if (ItemSheet::$formErrors !== []) {
                ItemSheet::showItemAdd(Request::postedValue('item_type_id'), Request::postedAll());
                return;
            }
            if (ADD_ATTACHMENTS == 'yes' && ($_FILES['attachment']['name'] ?? '') != '') {
                // Item row must exist before attachment metadata references it.
            }
            $array = [];
            $array['create_date'] = time();
            $array['core_log_updated'] = time();
            $groupIds = GroupMembership::idsFromPost(Request::postedAll());
            $logText = trim((string) (Request::postedValue('log_entry') ?? ''));
            Request::forgetPosted('submit_button', 'reset', 'MAX_FILE_SIZE', 'attachment', 'item_id', '_csrf', 'log_entry', 'role_id');
            Request::forgetPosted('groups_posted');
            foreach (array_keys(Request::postedAll()) as $postKey) {
                if (str_starts_with((string) $postKey, 'group_')) {
                    Request::forgetPosted($postKey);
                }
            }
            $posted = Request::postedAll();
            MenuOptions::collapseRequest($posted);
            Request::fillPosted($posted);
            $dbArray = [];
            foreach (Request::postedAll() as $key => $value) {
                if (!stristr($key, 'worker_field') and !stristr($key, 'worker_field_menu')) {
                    $dbArray[$key] = $value;
                }
            }
            $fieldPayload = array_merge($array, $dbArray);
            $insertArray = $array + self::newItemColumns($fieldPayload);
            $itemId = Database::insert('items', $insertArray);
            ItemFields::saveFromArray($itemId, $fieldPayload);
            GroupMembership::setItemGroups($itemId, $groupIds);
            if (ADD_ATTACHMENTS == 'yes' && ($_FILES['attachment']['name'] ?? '') != '') {
                ItemFiles::addAttachment($itemId, false, false);
                Actions::executeAction($itemId, 'item_attachment', false);
            }
            if ($logText !== '' && SET_LOG_ENTRY === 'yes') {
                Request::setPosted('log_entry', $logText);
                ItemLog::addLogEntry($itemId, false, false);
            }
            Actions::executeAction($itemId, 'create_item', false);
            $createdTitle = trim((string) (Request::postedValue('item_title') ?? ''));
            if (ItemScreen::returnToItem($itemId, ($createdTitle !== '' ? $createdTitle : 'The record') . ' created.')) {
                return;
            }
            header('Location: ' . ItemScreen::recordUrl('item=' . rawurlencode((string) $itemId) . '&option=view'));
            exit;
        }
    }


/**
     * Select target item type to copies or move an item to
     * s
     * @param mixed $itemID Item ID
     * @param mixed $transformType Copy or move
     * @param mixed $targetItemTypeID Item type to land on
     * @return void
     */
    public static function transformItem($itemID, $transformType, $targetItemTypeID)
    {
        switch ($transformType) {
            case 'transform' :
                //Alter existing item to new item type (retains old field data if required)
                $columnArray['item_type_id'] = $targetItemTypeID;
                $condition = 'WHERE item_id = ' . (int) $itemID;
                Database::update('items', $columnArray, $condition);
                $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
                $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
                View::respond(TXT_450, Blocks::link(ItemScreen::recordUrl('item=' . $itemID . '&option=view' . $logEntry . $attachments), TXT_262, 'URL'));
                return;

            case 'copy' :
                //Copies existing data to new item
                //Get existing data
                $columnArray = array('*');
                $condition = 'WHERE item_id = ' . (int) $itemID;
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
                $condition = 'WHERE item_id = ' . (int) $itemID;
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
                $condition = 'WHERE item_id = ' . (int) $itemID;
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
                View::respond(TXT_392, Blocks::link(ItemScreen::recordUrl('item=' . $newItemColumnArray['item_id'] . '&option=view' . $logEntry . $attachments), TXT_262, 'URL'));
                return;
        }

        //Copy to another item

        //Tranform to another item type

    }


    public static function updateItem($itemID)
    {
        // Return to the form if dynamic actions are still occurring, such as sub buildSelectDropdown selection etc
        if (!Request::hasPostedKey('item_type_id')) {
            // halt adding the item as we may have shortcutted here
            View::respond(TXT_535);
            return;
        } elseif (!Request::hasPostedKey('submit_button')) {
            // Reload form setting values based on posted form values, triggered from javascript submits
            ItemSheet::showItem(Request::postedValue('item_id'), Request::postedAll(), Request::queryValue('log_entry') ?? 'no', Request::queryValue('attachments') ?? 'no');
        } else {
            ItemSheet::$formErrors = self::postedFieldErrors(Request::postedAll());
            if (ItemSheet::$formErrors !== []) {
                ItemSheet::showItem(Request::postedValue('item_id'), Request::postedAll(), Request::queryValue('log_entry') ?? 'no', Request::queryValue('attachments') ?? 'no');
                return;
            }
            // Remove unwanted posted information
            Request::forgetPosted('submit_button', 'reset', 'item_id', '_csrf', 'MAX_FILE_SIZE');
            $posted = Request::postedAll();
            MenuOptions::collapseRequest($posted);
            Request::fillPosted($posted);
            if (Request::hasPostedKey('groups_posted')) {
                $postedGroups = \Adlexone\Data\GroupMembership::idsFromPost(Request::postedAll());
                Request::forgetPosted('groups_posted');
                foreach (array_keys(Request::postedAll()) as $postKey) {
                    if (preg_match('/^group_\d+$/', (string) $postKey) === 1) {
                        Request::forgetPosted($postKey);
                    }
                }
                Request::setPosted('groups', $postedGroups);
            }
            if (Request::hasPostedKey('groups_only')) {
                Request::forgetPosted('groups_only');
                $onlyGroups = Request::postedValue('groups');
                Actions::executeAction($itemID, 'update_record', false);
                if (is_array($onlyGroups)) {
                    \Adlexone\Data\GroupMembership::setItemGroups((int) $itemID, array_map('intval', $onlyGroups));
                }
                if (!ItemScreen::returnToItem($itemID, 'Access saved.', 'access')) {
                    View::respond(TXT_320, Blocks::link(ItemScreen::recordUrl('item=' . $itemID . '&option=view'), TXT_353, 'URL'));
                }
                return;
            }
            // Handle the log update
            if (Request::hasPostedKey('log_entry') and Request::postedValue('log_entry') != '') {
                ItemLog::addLogEntry($itemID, false, false);
            }
            // Execute actions
            Actions::executeAction($itemID, 'update_record', false);
            Request::forgetPosted('log_entry', 'role_id');
            // Remove worker fields
            foreach (Request::postedAll() as $key => $value) {
                //Don't insert work field values - they aren't persistent
                if (stristr($key, 'worker_field') or stristr($key, 'worker_field_menu')) {
                    Request::forgetPosted($key);
                }
            }
            // Update system fields, they are the only remaining POST variables
            $fieldPayload = Request::postedAll();
            $itemColumns = self::itemUpdateColumns((int) $itemID, $fieldPayload);
            if ($itemColumns !== []) {
                Database::update('items', $itemColumns, 'item_id = ?', [(int) $itemID]);
            }
            ItemFields::saveFromArray((int) $itemID, $fieldPayload);
            if (isset($fieldPayload['groups']) && is_array($fieldPayload['groups'])) {
                GroupMembership::setItemGroups((int) $itemID, array_map('intval', $fieldPayload['groups']));
            }
            if (ADD_ATTACHMENTS == 'yes' && ($_FILES['attachment']['name'] ?? '') !== '') {
                ItemFiles::addAttachment($itemID, false, true);
            }
            $savedTitle = trim((string) (Request::postedValue('item_title') ?? ''));
            $message = ($savedTitle !== '') ? $savedTitle . ' updated.' : TXT_272;
            if (ItemScreen::returnToItem($itemID, (string) $message)) {
                return;
            }
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            View::respond($message, Blocks::link(ItemScreen::recordUrl('item=' . $itemID . '&option=view' . $logEntry . $attachments), TXT_262, 'URL'));
            return;
        }
    }


    public static function deleteItem($itemID)
    {
        $itemID = (int) $itemID;
        Database::transaction(static function () use ($itemID): void {
            Database::run('DELETE FROM items WHERE item_id = ' . $itemID);
            Database::run('DELETE FROM core_log WHERE item_id = ' . $itemID);
            Database::run('DELETE FROM item_attachments WHERE item_id = ' . $itemID);
            ItemFields::deleteForItem($itemID);
            GroupMembership::setItemGroups($itemID, []);
        });
        if (Page::current()->inApplication()) {
            View::flash('The record has been deleted.');
            header('Location: ' . ItemScreen::recordUrl());
            exit;
        }
        ItemList::showMyItems($_SESSION['access_user_id']);
    }
}
