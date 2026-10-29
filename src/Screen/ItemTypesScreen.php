<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\MenuOptions;
use Adlexone\support\RenderNavigation;
use Adlexone\Http\Page;
use Adlexone\Auth\Access;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\support\FormState;
use Adlexone\Http\View;
use Adlexone\Html\Html;
use Adlexone\Http\Router;

/**
 * Screen formerly loaded from legacy/.
 */
final class ItemTypesScreen
{
    public static function open(): void
    {
        MenuOptions::prepare();
        Page::current()->setNavOption('__section__');
        RenderNavigation::applyConfigureNav([['label' => 'Record types', 'href' => '?manage=item-types']]);









































        switch ((string) ($_GET['option'] ?? '')) {
            case 'new_item_type':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::showItemType('', FormState::keys($_SESSION, 'remove'));
                $_SESSION = FormState::keys($_SESSION, 'unset');
                break;
            case 'add_item_type':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::addItemType();
                break;
            case 'modify_item_type':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::showItemType((string) ($_GET['item_type_id'] ?? ''));
                break;
            case 'update_item_type':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::updateItemType((string) ($_POST['item_type_id'] ?? ''));
                break;
            case 'manage_item_types':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::showItemTypes();
                break;
            case 'delete_item_type':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::deleteItemType();
                break;
            default:
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::showItemTypes();
                break;
        }
    }
    /**
     * showItemType()
     *
     * Shows add and edit item type pages.  Controller logic: new_item_type, edit_item_type
     *
     * @param string $itemTypeID Item type ID
     * @param array $values Field values passes in via $_SESSION array for retaining form field values if error occurred during entry
     * @return
     */
    public static function showItemType($itemTypeID, $values = [])
    {
        $isNew = ($itemTypeID == '');
        $action = $isNew ? '?manage=item-types&option=add_item_type' : '?manage=item-types&option=update_item_type';
        // Load field values
        if ($isNew) {
            $fieldValues = is_array($values) ? $values : [];
        } else {
            $columnArray = ['item_type_id', 'item_type_name', 'user_security', 'enabled'];
            $fieldValues = Database::first('item_types', $columnArray, 'item_type_id = ?', [(int) $itemTypeID]);
            if ($fieldValues === null) {
                View::respond(TXT_115, Blocks::link(self::url('option=manage_item_types'), TXT_362));
                return;
            }
        }

        $fields = [];
        $fields[TXT_87] = Form::text('item_type_name', $fieldValues['item_type_name'] ?? '');

        // User assignment select
        $columnArray = ['user_id', 'user_name'];
        $result = Database::select('users', $columnArray);
        $userIDArray = [''];
        $userArray = [TXT_267];
        foreach ($result as $row) {
            $userIDArray[] = $row['user_id'];
            $userArray[] = $row['user_name'];
        }
        $fields[TXT_269] = Form::select('user_security', $userIDArray, $userArray, $fieldValues['user_security'] ?? '');

        $columnArray = ['group_id', 'group_name'];
        $condition = "ORDER BY group_name ASC";
        $result = Database::select('groups', $columnArray, $condition);

        $groupMembershipArray = array_map('strval', \Adlexone\Data\GroupMembership::itemTypeGroupIds((int) ($fieldValues['item_type_id'] ?? 0)));
        $groupMembershipHtml = '<div class="group-security-list">';
        foreach ($result as $row) {
            $isChecked = in_array((string)$row['group_id'], $groupMembershipArray, true);
            $checkedValue = $isChecked ? (string)$row['group_id'] : '';
            $groupMembershipHtml .= Form::checkbox(
                'group_' . $row['group_id'],
                (string)$row['group_id'],
                $checkedValue,
                (string)$row['group_name']
            );
        }
        $groupMembershipHtml .= '</div>';

        $fields[TXT_90] = Form::select('enabled', ['Yes', 'No'], [TXT_93, TXT_94], $fieldValues['enabled'] ?? '');
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
            $result = Database::select('item_type_custom_fields', $columnArray, 'item_type_id = ?', [(int) $itemTypeID]);
            $customFieldArray = [];
            $customFieldSortArray = [];
            if (count($result) != 0) {
                foreach ($result as $row) {
                    $customFieldArray[$row['custom_field_id']] = $row['custom_field_id'];
                    $customFieldSortArray['field_order_' . $row['custom_field_id']] = $row['custom_field_order'];
                }
            }
        }

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
            $typeLabel = FieldTypes::label($typeKey);
            $itemHtml = '<li class="field-picker-item">'
                . '<label class="checkbox"><input type="checkbox" name="custom_field_id_' . $fieldId . '" value="' . $fieldId . '"' . ($isSelected ? ' checked' : '') . '> '
                . '<span>' . Html::e((string)$row['custom_field_name']) . '</span> '
                . '<span class="field-picker-type">' . Html::e($typeLabel) . '</span></label>'
                . '<span class="field-picker-moves">'
                . '<button type="button" class="btn field-picker-move" data-move="up">' . Html::e(TXT_686) . '</button>'
                . '<button type="button" class="btn field-picker-move" data-move="down">' . Html::e(TXT_687) . '</button>'
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
            . '<div><div class="label">' . Html::e(TXT_684) . '</div><ul id="availableFields" class="field-picker-list">' . $availableItems . '</ul></div>'
            . '<div><div class="label">' . Html::e(TXT_685) . '</div><ul id="selectedFields" class="field-picker-list">' . $selectedHtml . '</ul></div>'
            . '</div>';
        Page::current()->addScript('field-picker');

        $fields[TXT_222] = $pickerHtml;
        $fields[''] = Form::hidden('item_type_id', $itemTypeID);

        $javascript = Form::fieldCheck(TXT_468, [''], ['item_type_name'], [''], [TXT_223], [true]);
        $heading = $isNew ? 'Add type' : 'Edit type';
        Page::current()->suggestTitle($heading);
        Page::current()->addCrumb($heading);

        Page::current()->setBody(Form::form(
            $heading,
            $action,
            $fields,
            [
                Form::button('submit', 'submit_button', TXT_74, $javascript),
                Form::button('reset', 'reset', TXT_75),
            ]
        ));
    }

    /**
     * addItemType()
     *
     * Adds item type.  Controller logic: add_item_type
     */
    public static function addItemType()
    {
        $columns = self::postedTypeColumns();
        $back = Blocks::link(self::url('option=new_item_type'), TXT_363);
        if ($columns['item_type_name'] === '') {
            View::respond('Enter a record type name.', $back);
            return;
        }
        if (Database::exists('item_types', 'LOWER(item_type_name) = LOWER(?)', [$columns['item_type_name']])) {
            View::respond($columns['item_type_name'] . ' ' . TXT_163, $back);
            return;
        }
        $selectedFields = self::selectedCustomFieldsFromPost();
        $ids = \Adlexone\Data\GroupMembership::idsFromPost($_POST);
        $typeId = Database::insert('item_types', $columns);
        foreach ($selectedFields as $fieldId => $order) {
            Database::insert('item_type_custom_fields', [
                'item_type_id' => $typeId,
                'custom_field_id' => (int) $fieldId,
                'custom_field_order' => (int) $order,
            ]);
        }
        \Adlexone\Data\GroupMembership::setItemTypeGroups($typeId, $ids);
        View::respond($columns['item_type_name'] . ' ' . TXT_162, Blocks::link(self::url('option=manage_item_types'), TXT_362));
    }

    /**
     * Posted values for the columns the record type form owns.
     *
     * @return array{item_type_name: string, user_security: string, enabled: string}
     */
    private static function postedTypeColumns(): array
    {
        $text = static fn (string $key): string => is_scalar($_POST[$key] ?? null) ? trim((string) $_POST[$key]) : '';
        $owner = $text('user_security');

        return [
            'item_type_name' => $text('item_type_name'),
            'user_security' => ctype_digit($owner) ? $owner : '',
            'enabled' => $text('enabled') === 'No' ? 'No' : 'Yes',
        ];
    }

    /**
     * Item types, with edit and delete.
     */
    public static function showItemTypes(): void
    {
        $owners = \Adlexone\Application\ApplicationSettings::typeOwners();
        $appSlug = trim((string) ($_GET['application'] ?? ''));
        $appName = '';
        if ($appSlug !== '') {
            $app = \Adlexone\Application\ApplicationStore::findBySlug($appSlug);
            $appName = $app === null ? '' : (string) $app['name'];
        }
        $unused = ($_GET['unused'] ?? '') === '1' && $appName === '';
        $groups = [];
        foreach (Database::select('item_types', '*', '', [], 'item_type_name ASC') as $row) {
            $id = (int) $row['item_type_id'];
            $apps = $owners[$id] ?? [];
            if ($appName !== '') {
                if (!in_array($appName, $apps, true)) {
                    continue;
                }
            } elseif ($unused) {
                if ($apps !== []) {
                    continue;
                }
            } elseif ($apps === []) {
                continue;
            }
            $label = $apps === [] ? 'Not used by an application' : implode(', ', $apps);
            $groups[$label][] = self::itemTypeRecord($row);
        }
        ksort($groups);
        $listGroups = [];
        foreach ($groups as $label => $rows) {
            $listGroups[] = ['label' => count($groups) > 1 || $appName !== '' ? $label : '', 'rows' => $rows];
        }
        Page::current()->suggestTitle($appName !== '' ? 'Record types · ' . $appName : 'Record types');
        Page::current()->setBody(Blocks::vertical([
            ['title' => TXT_50, 'html' => self::typeRecordList($listGroups, $unused, $appName !== '')],
        ]));
    }

    /**
     * @param list<array{label?: string, rows: list<array<string, mixed>>}> $groups
     */
    public static function typeRecordList(array $groups, bool $unused = false, bool $scoped = false): string
    {
        $toggle = '';
        if (!$scoped) {
            $href = $unused ? self::url() : self::url('unused=1');
            $toggle = '<div class="record-list__tools">'
                . Blocks::link($href, $unused ? 'In use' : 'Unused', '', 'btn btn--sm')
                . '</div>';
        }

        return Blocks::recordList([
            'column' => 'Record type',
            'columns' => [
                ['key' => 'enabled', 'label' => 'Enabled'],
            ],
            'searchLabel' => TXT_3,
            'primary' => ['href' => self::url('option=new_item_type'), 'label' => 'Add type'],
            'toolbar' => $toggle,
            'empty' => $unused ? 'Every record type is used by an application.' : 'No record types for this view.',
            'emptyIllustration' => 'empty-types',
            'emptyTitle' => $unused ? 'Every type is in use' : 'No record types yet',
            'emptyCopy' => $unused ? '' : 'A record type sets the fields a record uses.',
            'noMatch' => TXT_689,
            'groups' => $groups === [] ? [['rows' => []]] : $groups,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function itemTypeRecord(array $row): array
    {
        $name = (string)$row['item_type_name'];
        $id = rawurlencode((string)$row['item_type_id']);

        return [
            'name' => $name,
            'href' => self::url('option=modify_item_type&item_type_id=') . $id,
            'cells' => [
                'enabled' => (string)$row['enabled'],
            ],
            'actions' => [
                [
                    'href' => self::url('option=modify_item_type&item_type_id=') . $id,
                    'label' => TXT_626,
                    'tone' => 'quiet',
                ],
                [
                    'href' => self::url('option=delete_item_type&item_type_id=') . $id,
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
    public static function updateItemType($itemTypeID)
    {
        $itemTypeID = (int) $itemTypeID;
        $back = Blocks::link(self::url('option=manage_item_types'), TXT_362);
        if (!Database::exists('item_types', 'item_type_id = ?', [$itemTypeID])) {
            View::respond(TXT_115, $back);
            return;
        }
        $columns = self::postedTypeColumns();
        if ($columns['item_type_name'] === '') {
            View::respond('Enter a record type name.', $back);
            return;
        }
        if (Database::exists('item_types', 'LOWER(item_type_name) = LOWER(?) AND item_type_id <> ?', [$columns['item_type_name'], $itemTypeID])) {
            View::respond($columns['item_type_name'] . ' ' . TXT_163, $back);
            return;
        }
        $selectedFields = self::selectedCustomFieldsFromPost();
        $ids = \Adlexone\Data\GroupMembership::idsFromPost($_POST);
        Database::delete('item_type_custom_fields', 'item_type_id = ?', [$itemTypeID]);
        foreach ($selectedFields as $fieldId => $order) {
            Database::insert('item_type_custom_fields', [
                'item_type_id' => $itemTypeID,
                'custom_field_id' => (int) $fieldId,
                'custom_field_order' => (int) $order,
            ]);
        }
        Database::update('item_types', $columns, 'item_type_id = ?', [$itemTypeID]);
        \Adlexone\Data\GroupMembership::setItemTypeGroups($itemTypeID, $ids);
        View::respond($columns['item_type_name'] . ' ' . TXT_164, $back);
    }

    /**
     * Checked custom fields from the item-type form, read before any POST keys are removed.
     * Sort inputs can arrive before their checkbox, so the order is captured up front.
     *
     * @return array<string, string> custom field id => sort order
     */
    public static function selectedCustomFieldsFromPost(): array
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
     * Group ids posted as group_{id}.
     */
    public static function groupSecurityFromPost(): string
    {
        return \Adlexone\Data\GroupMembership::toDelimited(
            \Adlexone\Data\GroupMembership::idsFromPost($_POST)
        );
    }

    /**
     * Drop checkbox, sort and group inputs so they are not written onto item_types.
     */
    public static function stripItemTypeFormFields(): void
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
    public static function deleteItemType(): void
    {
        $itemTypeID = (string)($_GET['item_type_id'] ?? '');
        $back = Blocks::link(self::url('option=manage_item_types'), TXT_362);
        if ($itemTypeID === '' || !ctype_digit($itemTypeID)) {
            View::respond(TXT_115, $back);
            return;
        }

            $row = Database::first('item_types', '*', 'item_type_id = ?', [$itemTypeID]);
        if (!$row) {
            View::respond(TXT_115, $back);
            return;
        }

        $name = (string)$row['item_type_name'];
        if (Database::exists('items', 'item_type_id = ?', [$itemTypeID])) {
            View::respond($name . ' ' . TXT_691, $back);
            return;
        }

        Database::delete('item_type_custom_fields', 'item_type_id = ?', [(int) $itemTypeID]);
        Database::delete('item_type_groups', 'item_type_id = ?', [(int) $itemTypeID]);
        Database::delete('item_types', 'item_type_id = ?', [(int) $itemTypeID]);
        View::respond($name . ' ' . TXT_47, $back);
    }

    private static function url(string $query = ''): string
    {
        $scope = trim((string) ($_GET['application'] ?? ''));
        if ($scope !== '') {
            $query = 'application=' . rawurlencode($scope) . ($query !== '' ? '&' . ltrim($query, '&') : '');
        }

        return Router::manageUrl('item-types', $query);
    }
}
