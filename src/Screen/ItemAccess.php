<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\RecordQuery;
use Adlexone\Application\Rules;
use Adlexone\Database\Database;
use Adlexone\Application\Actions;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Html\Html;

final class ItemAccess
{
/**
     * Sets the owner from the record side panel and returns to the record.
     */
    public static function updateOwner(): void
    {
        $itemId = (int) (Request::postedValue('item_id') ?? 0);
        $next = trim((string) (Request::postedValue('user_security') ?? ''));
        $allowed = array_map('strval', array_keys(Rules::people()));
        $allowed[] = '0';
        $notice = '';
        if (in_array($next, $allowed, true)) {
            $row = Database::first('items', ['user_security'], 'item_id = ?', [$itemId]);
            if ((int) $next !== (int) ($row['user_security'] ?? 0)) {
                RecordQuery::saveOwner($itemId, (int) $next);
                $notice = 'Owner updated.';
            }
        }
        if (ItemScreen::returnToItem($itemId, $notice)) {
            return;
        }
        ItemSheet::showItem((string) $itemId);
    }

    public static function showSecurityAssignment(string $itemID): void
    {
        // Build form action
        $action = ItemScreen::recordUrl('item=' . $itemID . '&option=update_security');

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
        Request::forgetPosted('submit_button', 'reset');

        // Collect selected group values, trim and ignore empty entries
        $selected = array_values(array_filter(Request::postedAll(), fn($v) => trim((string)$v) !== ''));
        $ids = array_map(static fn($v) => (int) $v, $selected);
        Request::setPosted('groups', $ids);

        // Execute any configured actions for this update
        Actions::executeAction($itemID, 'update_item', false);

        try {
            \Adlexone\Data\GroupMembership::setItemGroups((int) $itemID, $ids);
        } catch (\Throwable $e) {
            // Render an error response (keeps behaviour simple and user-friendly)
            View::respond(TXT_321, $e->getMessage());
            return;
        }

        // Build return URL and render success response via the modern helper
        $logEntry   = (SET_LOG_ENTRY === 'yes') ? '&log_entry=yes' : '';
        $attachments = (SET_ATTACHMENTS === 'yes') ? '&attachments=yes' : '';
        $url = ItemScreen::recordUrl('item=' . $itemID . '&option=show_item' . $logEntry . $attachments);

        View::respond(TXT_320, Blocks::link($url, TXT_353, 'URL'));
    }
}
