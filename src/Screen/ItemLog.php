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

final class ItemLog
{
/**
     * Creates form for adding new log entry and displays existing log.
     *
     * @param integer $itemID Item ID
     */
    public static function showLogEntry($itemID)
    {
        $showLog = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $backHref = ItemScreen::recordUrl('item=' . $itemID . '&option=show_item' . $showLog . $attachments);
        $logField[Wording::get('LA_244', 'TXT_244')] = Form::textarea('log_entry', '', SET_FORM_FIELD_HEIGHT);
        $groupIds = ['0'];
        $groupLabels = ['Everyone'];
        foreach (\Adlexone\Application\Rules::groups() as $id => $name) {
            $groupIds[] = (string) $id;
            $groupLabels[] = $name;
        }
        $logField['Visible to'] = '<div>' . Form::select('group_id', $groupIds, $groupLabels, '0')
            . '<div class="field-picker-type">Everyone means anyone who can open the record.</div></div>';

        $columnArray = array('*');
        $condition = 'WHERE item_id = ' . (int) $itemID;
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
            ItemScreen::recordUrl('option=add_log_entry'),
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
        Request::forgetPosted('submit_button', 'reset');
        // Get new log sequence (id is AUTOINCREMENT)
        $condition = 'WHERE item_id = ' . (int) $itemID;
        $SequenceID = Database::newID('core_log', 'log_item_sequence', $condition);
        // Add log entry
        $columnArray['item_id'] = $itemID;
        $columnArray['create_date'] = time();
        $columnArray['item_identifier'] = 0;
        //0 Reserved for item log update
        $columnArray['log_item_sequence'] = $SequenceID;
        $logText = trim((string) (Request::postedValue('log_entry') ?? ''));
        if ($logText === '') {
            if ($showInformation == true && !ItemScreen::returnToItem($itemID)) {
                self::showLogEntry($itemID);
            }
            return;
        }
        $columnArray['log_text'] = $logText;
        $columnArray['security_id'] = $_SESSION['access_user_id'];
        $columnArray['role_id'] = 5;
        if (\Adlexone\Database\Database::columnExists('core_log', 'group_id')) {
            $columnArray['group_id'] = (int) (Request::postedValue('group_id') ?? 0);
        }
        // Insert into log table
        Database::insert('core_log', $columnArray);
        // Update entry in item table
        $condition = 'WHERE item_id = ' . (int) $itemID;
        $itemColumnArray['core_log_updated'] = $columnArray['create_date'];
        Database::update('items', $itemColumnArray, $condition);
        if ($executeAction == true) {
            // Execute actions
            Actions::executeAction($itemID, 'update_item_log_entry', false);
        }
        if ($showInformation == true) {
            $fromTimeline = Request::hasPostedKey('from_timeline');
            if (ItemScreen::returnToItem($itemID, $fromTimeline ? 'Note added.' : (string) TXT_248, $fromTimeline ? '' : 'notes')) {
                return;
            }
            $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
            $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
            View::respond(TXT_248, Blocks::link(ItemScreen::recordUrl('item=' . $itemID . '&option=show_item' . $logEntry . $attachments), TXT_262, 'URL'));
            return;
        }
    }

    public static function showItemLog($itemID, string $title = '', string $empty = '')
    {
        $blocks = [
            ['title' => $title !== '' ? $title : TXT_146, 'html' => self::logEntriesHtml($itemID, $empty, true)]
        ];

        return Blocks::vertical($blocks);
    }

    public static function logEntriesHtml($itemID, string $empty, bool $withRole): string
    {
        $itemsHtml = '';
        $result = Database::select('core_log', ['*'], 'WHERE item_id = :item_id ORDER BY log_item_sequence DESC', ['item_id' => $itemID]);

        foreach ($result as $row) {
            if (!\Adlexone\Data\GroupMembership::canSeeLogLine((int) ($row['group_id'] ?? 0), (int) ($row['role_id'] ?? 5))) {
                continue;
            }
            $userName = '';
            if ((string) $row['security_id'] !== '') {
                $userRow = Database::first('users', ['user_name'], 'WHERE user_id = ' . (int) $row['security_id']);
                $userName = is_array($userRow) ? (string) ($userRow['user_name'] ?? '') : '';
            }
            $audience = '';
            $groupId = (int) ($row['group_id'] ?? 0);
            if ($withRole && $groupId > 0) {
                $group = Database::first('groups', ['group_name'], 'group_id = ?', [$groupId]);
                $audience = trim((string) ($group['group_name'] ?? ''));
            }

            $heading = '<div class="log-heading"><strong>'
                . Html::e(date(SET_DATE_FORMAT, (int) $row['create_date']))
                . ($userName !== '' ? ' ' . Html::e((string) TXT_260) . ' ' . Html::e($userName) : '')
                . ($audience !== '' ? ' (' . Html::e($audience) . ')' : '')
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
     * deleteLog()
     *
     * Deletes the selected log item.
     */
    public static function deleteLog($itemID, $logItemSequence)
    {
        // Setup log sql and execute query
        $condition = 'WHERE item_id = ' . (int) $itemID . ' AND log_item_sequence = ' . (int) $logItemSequence;
        Database::delete('core_log', $condition);
        self::showLogEntry($itemID);
    }
}
