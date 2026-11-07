<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Auth\Role;
use Adlexone\Data\FieldCheck;
use Adlexone\Data\GroupMembership;
use Adlexone\Database\Database;
use Adlexone\Data\ItemFields;
use Adlexone\Html\Blocks;
use Adlexone\Html\Breadcrumb;
use Adlexone\Html\Form;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;
use Adlexone\Http\Page;
use Adlexone\Http\View;
use Adlexone\Text\Wording;

final class ItemSheet
{
    /** @var array<string, string> */
    public static array $formErrors = [];

    /**
     * @param array<string, mixed> $formOptions
     * @return array<string, mixed>
     */
    private static function withFormErrors(array $formOptions): array
    {
        if (self::$formErrors === []) {
            return $formOptions;
        }
        $formOptions['errors'] = self::$formErrors;
        $formOptions['error_title'] = defined('TXT_468') ? (string) TXT_468 : 'Check the form';

        return $formOptions;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function showItemAdd($itemTypeID, $values)
    {
        if (!ItemScreen::applicationAllowsItemType((int) $itemTypeID)) {
            Access::deny();
        }
        $posted = is_array($values) ? $values : [];
        if (!isset($posted['user_security'])) {
            $itemTypeFields = Database::first('item_types', '*', 'item_type_id = ?', [(int) $itemTypeID]);
            if (!is_array($itemTypeFields)) {
                $itemTypeFields = [];
            }
        } else {
            $itemTypeFields = [];
            foreach ($posted as $key => $value) {
                $itemTypeFields[$key] = is_string($value) ? stripslashes($value) : $value;
            }
        }

        $row = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [(int) $itemTypeID]);
        if (!is_array($row) || ($row['item_type_name'] ?? '') === '' || ($itemTypeFields['item_type_id'] ?? '') === '') {
            $picker = ItemScreen::recordUrl('option=show_item_types');
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
        $itemTypeName = (string) $row['item_type_name'];
        $inApplication = Page::current()->inApplication();
        $canRevise = self::canReviseType((int) ($itemTypeFields['item_type_id'] ?? 0));
        $people = ItemSheetSide::newPeople([], $itemTypeFields, $posted, $inApplication, $canRevise);
        $fields = $people['fields'];
        $selectedGroups = isset($posted['user_security'])
            ? GroupMembership::idsFromPost($posted)
            : GroupMembership::itemTypeGroupIds((int) $itemTypeID);
        if (!$inApplication) {
            $fields[TXT_270] = ItemSheetSide::groupPicker($selectedGroups);
        }
        $fields[$inApplication ? 'Name' : Wording::get('LA_84', 'TXT_84')] = Form::text('item_title', $itemTypeFields['item_title'] ?? '');
        $check = new FieldCheck();
        ItemSheetFields::fill($fields, $check, (int) $itemTypeID, true, false, $inApplication, $itemTypeFields, $posted);
        $fields[''] = Form::hidden('item_type_id', (string) $itemTypeFields['item_type_id']) . $people['hidden'];
        $formOptions = [];
        if ($inApplication) {
            $redisplay = isset($posted['user_security']);
            $draftNote = $redisplay ? (string) ($posted['log_entry'] ?? '') : '';
            $tools = ItemSheetTools::recordTools(0, (int) $itemTypeID, $canRevise, $selectedGroups, $draftNote);
            if ($tools['multipart']) {
                $formOptions['enctype'] = 'multipart/form-data';
            }
            $formOptions['lead'] = $tools['lead'];
            $formOptions['inside'] = $tools['inside'];
            $formOptions['after'] = $tools['after'];
            $formOptions['record'] = true;
            $formOptions['kicker'] = $itemTypeName;
            $badge = self::openingBadge((int) $itemTypeID, $itemTypeFields);
            if ($badge !== '') {
                $formOptions['badge'] = $badge;
            }
        } elseif (ADD_ATTACHMENTS == 'yes') {
            $fields[TXT_393] = Form::file('attachment', (string) ((int) SET_MAX_ATTACHMENT * 1000000));
            $formOptions['enctype'] = 'multipart/form-data';
        }
        $formOptions = self::withFormErrors($formOptions);
        $buttons = [Form::button('submit', 'submit_button', TXT_57, $check->withTitle()->onclick())];
        $createHeading = $inApplication
            ? 'New ' . $itemTypeName
            : Wording::get('LA_46', 'TXT_67') . ' — ' . $itemTypeName;
        Page::current()->setBody(Form::form($createHeading, ItemScreen::recordUrl('option=add_item'), $fields, $buttons, $formOptions));
    }

    /**
     * @param array<string, mixed>|string $values
     */
    public static function showItem($itemID, $values = '', $addLogEntry = 'no', $attachments = 'no')
    {
        if (Database::first('items', ['item_id'], 'item_id = ?', [(int) $itemID]) === null) {
            View::respond(TXT_616);
            return;
        }
        if (!ItemScreen::userCanAccessItem((int) $itemID)) {
            View::respond(TXT_452);
            return;
        }
        $posted = is_array($values) ? $values : [];
        $itemFields = ItemFields::hydrate(Database::first('items', ['*'], 'item_id = ?', [(int) $itemID]) ?? []);
        if (isset($posted['item_id'])) {
            foreach ($posted as $key => $value) {
                $itemFields[$key] = is_string($value) ? stripslashes($value) : $value;
            }
        }
        $typeRow = Database::first(
            'item_types',
            ['item_type_name'],
            'item_type_id = ?',
            [(int) $itemFields['item_type_id']]
        );
        $itemTypeName = is_array($typeRow) ? (string) ($typeRow['item_type_name'] ?? '') : '';
        $inApplication = Page::current()->inApplication();
        $people = ItemSheetSide::recordPeople([], $itemFields, $inApplication);
        $itemField = $people['fields'];
        $itemGroupIds = GroupMembership::itemGroupIds((int) $itemID);
        $canRevise = ItemScreen::canReviseItem((int) $itemID);
        if (!$inApplication && $canRevise) {
            $itemField[TXT_270] = ItemSheetSide::groupField((int) $itemID, array_map('intval', $itemGroupIds));
        }
        $titleLabel = $inApplication ? 'Name' : Wording::get('LA_84', 'TXT_84');
        $title = (string) ($itemFields['item_title'] ?? '');
        $itemField[$titleLabel] = $canRevise
            ? Form::text('item_title', $itemFields['item_title'])
            : ItemSheetFields::lockedValue('item_title', $title, $title);
        $check = new FieldCheck();
        ItemSheetFields::fill(
            $itemField,
            $check,
            (int) $itemFields['item_type_id'],
            false,
            !$canRevise,
            $inApplication,
            $itemFields,
            $posted
        );
        $formOptions = [];
        if ($inApplication) {
            $selectedGroups = isset($posted['groups_posted'])
                ? GroupMembership::idsFromPost($posted)
                : $itemGroupIds;
            $tools = ItemSheetTools::recordTools(
                (int) $itemID,
                (int) ($itemFields['item_type_id'] ?? 0),
                $canRevise,
                $selectedGroups
            );
            $formOptions['lead'] = $tools['lead'];
            $formOptions['inside'] = $tools['inside'];
            $formOptions['after'] = $tools['after'];
            $formOptions['record'] = true;
            $formOptions['kicker'] = $itemTypeName;
            $formOptions = self::recordHeading($formOptions, $itemFields);
        }
        $formOptions = self::withFormErrors($formOptions);
        $buttons = [];
        if (Access::canEditRecords()) {
            $buttons[] = Form::button('submit', 'submit_button', 'Save', $check->withTitle()->onclick());
        }
        $itemField[''] = Form::hidden('item_type_id', (string) $itemFields['item_type_id'])
            . Form::hidden('item_id', (string) $itemFields['item_id'])
            . $people['hidden'];
        $recordTitle = trim((string) ($itemFields['item_title'] ?? ''));
        $formTitle = $inApplication
            ? ($recordTitle !== '' ? $recordTitle : $itemTypeName)
            : Wording::get('LA_102', 'TXT_102') . ': ' . $itemFields['item_id'] . ' - ' . $itemTypeName;
        $html = '';
        if ($inApplication) {
            Page::current()->setTitle($formTitle);
            $backLabel = trim(str_replace("\u{00A0}", ' ', Page::current()->navLabel()));
            if ($backLabel === '' || strcasecmp($backLabel, $formTitle) === 0) {
                $backLabel = 'Work';
            }
            $html .= '<p class="record-back"><a href="' . Html::e(ItemScreen::recordUrl()) . '">Back to '
                . Html::e($backLabel) . '</a></p>';
        } else {
            Page::current()->setCrumbs(Breadcrumb::trail([['label' => $formTitle]]));
        }
        $html .= Form::form($formTitle, ItemScreen::recordUrl('option=update_item'), $itemField, $buttons, $formOptions);
        if (!$inApplication && Access::canControlRecords()) {
            $html .= ItemSheetTools::transformForm((string) $itemID);
        }
        if ($itemID != '' && defined('APP_COUNT_ITEM_TYPE') && $itemFields['item_type_id'] == APP_COUNT_ITEM_TYPE) {
            SearchResults::updateKnoweldgeCount([(string) $itemID]);
        }
        if ($inApplication) {
            $html = '<div class="workspace"><div class="workspace__main">' . $html . '</div>'
                . ItemSheetSide::recordSide((int) $itemID, $itemFields, array_map('intval', $itemGroupIds), $canRevise)
                . '</div>';
        }
        Page::current()->setBody($html);
    }

    /**
     * @param array<string, mixed> $formOptions
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function recordHeading(array $formOptions, array $item): array
    {
        $ownerId = (int) ($item['user_security'] ?? 0);
        if ($ownerId > 0) {
            $owner = Database::first('users', ['user_name', 'first_name', 'last_name'], 'user_id = ?', [$ownerId]);
            if ($owner !== null) {
                $ownerName = trim((string) ($owner['first_name'] ?? '') . ' ' . (string) ($owner['last_name'] ?? ''));
                if ($ownerName === '') {
                    $ownerName = trim((string) ($owner['user_name'] ?? ''));
                }
                if ($ownerName !== '') {
                    $formOptions['mark'] = $ownerName;
                }
            }
        }
        $facts = [];
        $openedAt = (int) ($item['create_date'] ?? 0);
        $updatedAt = (int) ($item['core_log_updated'] ?? 0);
        if ($openedAt > 0) {
            $facts[] = 'Opened ' . Presence::ago($openedAt);
        }
        if ($updatedAt > $openedAt) {
            $facts[] = 'Updated ' . Presence::ago($updatedAt);
        }
        if ($facts !== []) {
            $formOptions['facts'] = $facts;
        }
        $status = WorkScreen::workStatusField((int) ($item['item_type_id'] ?? 0));
        if ($status !== null) {
            $badge = trim((string) ($item['custom_field_' . $status['id']] ?? ''));
            if ($badge !== '') {
                $formOptions['badge'] = $badge;
            }
        }

        return $formOptions;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function openingBadge(int $typeId, array $fields): string
    {
        $status = WorkScreen::workStatusField($typeId);
        if ($status === null) {
            return '';
        }
        $badge = trim((string) ($fields['custom_field_' . $status['id']] ?? ''));
        if ($badge !== '') {
            return $badge;
        }
        $default = Database::first('custom_fields', ['default_value'], 'custom_field_id = ?', [$status['id']]);
        $badge = trim((string) ($default['default_value'] ?? ''));
        if ($badge !== '') {
            return $badge;
        }
        $first = Database::first(
            'custom_field_menu_values',
            ['menu_value'],
            'custom_field_id = ? ORDER BY menu_value_id ASC',
            [$status['id']]
        );

        return trim((string) ($first['menu_value'] ?? ''));
    }

    /**
     * True when the signed-in person can set the owner and groups on a new record of this type.
     */
    public static function canReviseType(int $typeId): bool
    {
        if (Access::canControlRecords()) {
            return true;
        }
        $level = 'view';
        $userGroupIds = GroupMembership::userGroupIds((int) ($_SESSION['access_user_id'] ?? 0));
        foreach (array_intersect($userGroupIds, GroupMembership::itemTypeGroupIds($typeId)) as $groupId) {
            $level = Role::strongerLevel($level, Role::recordLevelForGroup((int) $groupId));
        }

        return Role::levelAtLeast($level, 'assign');
    }

    public static function addItem()
    {
        ItemWrite::addItem();
    }

    public static function transformItem($itemID, $transformType, $targetItemTypeID)
    {
        ItemWrite::transformItem($itemID, $transformType, $targetItemTypeID);
    }

    public static function updateItem($itemID)
    {
        ItemWrite::updateItem($itemID);
    }

    public static function deleteItem($itemID)
    {
        ItemWrite::deleteItem($itemID);
    }
}
