<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Http\Request;

use Adlexone\Database\Database;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\MenuOptions;
use Adlexone\Http\RenderNavigation;
use Adlexone\Http\Page;
use Adlexone\Auth\Access;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Data\FormState;
use Adlexone\Http\View;
use Adlexone\Html\Html;
use Adlexone\Http\Router;

/**
 */
final class FieldsScreen
{
    public static function open(): void
    {
        MenuOptions::prepare();
        Page::current()->setNavOption('__section__');
        RenderNavigation::applyConfigureNav([['label' => 'Fields', 'href' => self::url()]]);
















































        switch ((string) (Request::queryValue('option') ?? '')) {
            case 'new_custom_field':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::showCustomField('', FormState::keys($_SESSION, 'remove'));
                $_SESSION = FormState::keys($_SESSION, 'unset');
                break;
            case 'add_custom_field':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::addCustomField();
                break;
            case 'modify_custom_field':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::showCustomField((string) (Request::queryValue('custom_field_id') ?? ''));
                break;
            case 'modify_menu_values':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::modifyMenuValues((string) (Request::queryValue('custom_field_id') ?? ''));
                break;
            case 'add_update_menu_value':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::addUpdateMenuValue((string) (Request::postedValue('custom_field_id') ?? ''));
                break;
            case 'update_menu_value_filter':
            case 'modify_menu_value_filters':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::modifyMenuValues((string) (Request::queryValue('custom_field_id') ?? Request::postedValue('custom_field_id') ?? ''));
                break;
            case 'delete_menu_value':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::deleteMenuValue((string) (Request::queryValue('menu_value_id') ?? ''), (string) (Request::queryValue('custom_field_id') ?? ''));
                break;
            case 'update_custom_field':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::updateCustomField((string) (Request::postedValue('custom_field_id') ?? ''));
                break;
            case 'delete_custom_field':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::deleteCustomField();
                break;
            case 'manage_fields':
            case 'manage_fields_types':
            case 'new_multilevel_menu_relationship':
            case 'add_multilevel_menu':
            case 'update_multilevel_menu':
            case 'show_multilevel_menu':
            case 'show_multilevel_menu_items':
            case 'update_multilevel_menu_items':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::showFields();
                break;
            case 'field_type_search':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::showFieldTypeResults();
                break;
            case 'search':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::showSearchOptions();
                break;
            default:
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::showFields();
                break;
        }
    }
    /**
     * Shows add and edit custom field pages.  Controller logic: new_custom_field, edit_custom_field
     *
     * @param string $customFieldID Custom Fields ID
     * @param array $values Field values passes in via $_SESSION array for retaining form field values if error occurred during entry
     */
    public static function showCustomField($customFieldID = '', $values = '')
    {
        if ($customFieldID == '') {
            $formAction = self::url('option=add_custom_field');
            $fieldValues = is_array($values) ? $values : [];
            $title = 'Add field';
        } else {
            $fieldValues = Database::first('custom_fields', '*', 'custom_field_id = ?', [(int) $customFieldID]);
            if ($fieldValues === null) {
                View::respond('That field is not here.', Blocks::link(self::url('option=manage_fields'), 'Fields'));
                return;
            }
            $formAction = self::url('option=update_custom_field');
            $title = 'Edit field';
        }

        $fields['Name'] = Form::text('custom_field_name', @$fieldValues['custom_field_name']);
        $fields['Default'] = Form::text('default_value', @$fieldValues['default_value']);
        $fieldTypes = FieldTypes::choices();
        $fieldNames = array_map(static fn (string $type): string => FieldTypes::label($type), $fieldTypes);
        $fields['Type'] = Form::select('field_type', $fieldTypes, $fieldNames, FieldTypes::normalise(@$fieldValues['field_type']));
        $fields['Link template'] = Form::text('data', @$fieldValues['data'])
            . '<p class="form-help">For a link field, the value replaces &lt;?php echo INSERT; ?&gt; in this address.</p>';
        $fields['Required'] = Form::select('required', ['Yes', 'No'], ['Yes', 'No'], @$fieldValues['required']);
        $storedType = FieldTypes::normalise((string) (@$fieldValues['field_type'] ?? ''));
        $validationSelected = FieldTypes::impliedValidation($storedType);
        if ($validationSelected === '') {
            $validationSelected = FieldTypes::normaliseKind((string) (@$fieldValues['validation_type'] ?? ''));
        }
        $validationTypes = ['', 'numeric', 'string', 'alphanumeric', 'date', 'time', 'datetime', 'ip', 'email', 'url'];
        $validationNames = ['None', 'Number', 'Text', 'Letters and numbers', 'Date', 'Time', 'Date and time', 'IP address', 'Email', 'Web address'];
        $fields['Check'] = Form::select('validation_type', $validationTypes, $validationNames, $validationSelected)
            . '<p class="field-note">Date, time, email, number, and link fields check the value themselves. Use this for a text field that still needs a format.</p>';
        $fields['Enabled'] = Form::select('enabled', ['Yes', 'No'], ['Yes', 'No'], @$fieldValues['enabled']);
        $fields[''] = Form::hidden('custom_field_id', $customFieldID);
        $javascript = Form::fieldCheck('Check the form', [''], ['custom_field_name'], [''], ['Enter a field name.'], [true]);
        Page::current()->suggestTitle($title);
        Page::current()->addCrumb($title);

        Page::current()->setBody(Form::form(
            $title,
            $formAction,
            $fields,
            [
                Form::button('submit', 'submit_button', 'Save', $javascript),
                Form::button('reset', 'reset', 'Reset'),
            ]
        ));
    }

    /**
     * Modify buildSelectDropdown and sub buildSelectDropdown values
     *
     * @param string $customFieldID Custom Field ID
     */
    public static function modifyMenuValues($customFieldID)
    {
        $fieldId = (int)$customFieldID;
        $fields = [];
        $fields['Values'] = '<div class="menu-editor-wrap"><p class="field-note">One menu holds every level. Add a value, then add the next value under it.</p>'
            . MenuOptions::editorRows($fieldId) . '</div>';
        $fields['New value'] = Form::text('menu_value', '');
        $fields['Under'] = MenuOptions::parentSelect('parent_menu_value_id', $fieldId, 0);
        $fields[''] = Form::hidden('custom_field_id', $customFieldID);

        $buttons = [
            Form::button('submit', 'add_new', 'Add value'),
            Form::button('submit', 'update_existing', 'Save values'),
            Form::button('reset', 'reset', 'Reset'),
        ];

        Page::current()->setBody(Form::form(
            'Menu values',
            self::url('option=add_update_menu_value'),
            $fields,
            $buttons
        ));
    }

    public static function addUpdateMenuValue($customFieldID)
    {
        $fieldId = (int)$customFieldID;
        if (Request::hasPostedKey('add_new') and Request::postedValue('add_new') != '') {
            $value = trim((string)(Request::postedValue('menu_value') ?? ''));
            $parentId = (int)(Request::postedValue('parent_menu_value_id') ?? 0);
            if ($value !== '' && MenuOptions::valueExists($fieldId, $value, $parentId)) {
                View::respond(
                    'That value is already in this menu.',
                    Blocks::link(self::url('option=modify_menu_values&custom_field_id=' . $fieldId), 'Menu values')
                );
                return;
            }
            if ($value !== '') {
                MenuOptions::add($fieldId, $value, $parentId);
            }
            self::modifyMenuValues($customFieldID);
            return;
        }
        if (Request::hasPostedKey('update_existing') and Request::postedValue('update_existing') != '') {
            MenuOptions::applyEdits($fieldId, Request::postedAll());
            self::modifyMenuValues($customFieldID);
        }
    }

    /**
     * Delete a menu value and any values nested under it.
     *
     * @param string $menuValueID Menu Value ID
     * @param string $customFieldID Custom Field ID
     */
    public static function deleteMenuValue($menuValueID, $customFieldID)
    {
        MenuOptions::delete((int)$customFieldID, (int)$menuValueID);
        self::modifyMenuValues($customFieldID);
    }

    /**
     * addCustomField()
     *
     * Adds custom field.  Controller logic: add_custom_field
     */
    public static function addCustomField()
    {
        $columns = self::postedFieldColumns();
        $back = Blocks::link(self::url('option=new_custom_field'), 'Add a field');
        if ($columns['custom_field_name'] === '') {
            View::respond('Enter a field name.', $back);
            return;
        }
        if (Database::exists('custom_fields', 'LOWER(custom_field_name) = LOWER(?)', [$columns['custom_field_name']])) {
            View::respond($columns['custom_field_name'] . ' already exists.', $back);
            return;
        }
        $id = Database::insert('custom_fields', $columns);
        Database::update('custom_fields', ['field_reference' => $id], 'custom_field_id = ?', [$id]);
        View::respond($columns['custom_field_name'] . ' was added.', Blocks::link(self::url('option=manage_fields'), 'Fields'));
    }

    /**
     * Posted values for the columns a field form owns.
     *
     * @return array<string, mixed>
     */
    private static function postedFieldColumns(): array
    {
        $text = static fn (string $key): string => is_scalar(Request::postedValue($key) ?? null) ? trim((string) Request::postedValue($key)) : '';
        $yesNo = static fn (string $value, string $fallback): string => in_array($value, ['Yes', 'No'], true) ? $value : $fallback;
        $type = $text('field_type');

        return self::normaliseFieldPayload([
            'custom_field_name' => $text('custom_field_name'),
            'field_type' => in_array($type, FieldTypes::choices(), true) ? $type : FieldTypes::TEXT_BOX,
            'default_value' => $text('default_value'),
            'data' => html_entity_decode($text('data'), ENT_COMPAT, 'UTF-8'),
            'validation_type' => $text('validation_type'),
            'required' => $yesNo($text('required'), 'No'),
            'enabled' => $yesNo($text('enabled'), 'Yes'),
        ]);
    }

    /**
     * Search form for custom fields and record types.
     * The title is the first argument to Form::form. The page body is set with Page::setBody.
     */
    public static function showSearchOptions(): void
    {
        $formFields = [];
        $formFields['Look in'] = Form::select(
            'type',
            ['custom_field_name', 'item_type_name'],
            ['Field name', 'Record type name'],
            ''
        );
        $formFields['Match'] = Form::select(
            'operator',
            ['LIKE', '='],
            ['contains', 'is'],
            ''
        );
        $formFields['Words'] = Form::text('criteria', '');

        $buttons = [];
        $buttons[] = Form::button('submit', 'submit_button', 'Search');
        $buttons[] = Form::button('reset', 'reset', 'Reset');

        $html = Form::form(
            'Find a field or a record type',
            self::url('option=field_type_search'),
            $formFields,
            $buttons
        );

        Page::current()->setBody($html);
    }

    /**
     * showFieldTypeResults()
     *
     * Returns search results from a custom field or item type search.  Controller logic: field_type_search
     */
    /**
     * Custom fields, with edit and delete.
     */
    public static function showFields(): void
    {
        $owners = \Adlexone\Application\ApplicationSettings::fieldOwners();
        $appSlug = trim((string) (Request::queryValue('application') ?? ''));
        $appName = '';
        if ($appSlug !== '') {
            $app = \Adlexone\Application\ApplicationStore::findBySlug($appSlug);
            $appName = $app === null ? '' : (string) $app['name'];
        }
        $unused = (Request::queryValue('unused') ?? '') === '1' && $appName === '';
        $typeNames = \Adlexone\Application\ApplicationSettings::fieldTypeNames();
        $groups = [];
        foreach (Database::select('custom_fields', '*', '', [], 'custom_field_name ASC') as $row) {
            $id = (int) $row['custom_field_id'];
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
            $record = self::customFieldRecord($row);
            $types = $typeNames[$id] ?? [];
            $record['cells']['types'] = $types === [] ? '' : implode(', ', $types);
            $label = $apps === [] ? 'Not used by an application' : implode(', ', $apps);
            $groups[$label][] = $record;
        }
        ksort($groups);
        $listGroups = [];
        foreach ($groups as $label => $rows) {
            $listGroups[] = ['label' => count($groups) > 1 || $appName !== '' ? $label : '', 'rows' => $rows];
        }

        Page::current()->suggestTitle($appName !== '' ? 'Fields · ' . $appName : 'Fields');
        Page::current()->setBody(Blocks::vertical([
            ['title' => 'Fields', 'html' => self::fieldRecordList($listGroups, $unused, $appName !== '')],
        ]));
    }

    /**
     * @param list<array{label?: string, rows: list<array<string, mixed>>}> $groups
     */
    public static function fieldRecordList(array $groups, bool $unused = false, bool $scoped = false): string
    {
        $toggle = '';
        if (!$scoped) {
            $href = $unused ? self::url() : self::url('unused=1');
            $toggle = '<div class="record-list__tools">'
                . Blocks::link($href, $unused ? 'In use' : 'Unused', '', 'btn btn--sm')
                . '</div>';
        }

        return Blocks::recordList([
            'column' => 'Field',
            'columns' => [
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'types', 'label' => 'Record types'],
                ['key' => 'enabled', 'label' => 'Enabled'],
            ],
            'searchLabel' => 'Search',
            'primary' => ['href' => self::url('option=new_custom_field'), 'label' => 'Add field'],
            'toolbar' => $toggle,
            'empty' => $unused ? 'Every field is used by an application.' : 'No fields for this view.',
            'emptyIllustration' => 'empty-fields',
            'emptyTitle' => $unused ? 'Every field is in use' : 'No fields yet',
            'emptyCopy' => $unused ? 'Unused fields are listed when you ask for them.' : 'Add a field, then use it on a record type.',
            'noMatch' => 'No matches.',
            'groups' => $groups === [] ? [['rows' => []]] : $groups,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function customFieldRecord(array $row): array
    {
        $row['field_type'] = FieldTypes::normalise((string)($row['field_type'] ?? ''));
        $name = (string)$row['custom_field_name'];
        $id = rawurlencode((string)$row['custom_field_id']);
        $key = ($row['field_type'] == 'workerField' || $row['field_type'] == 'workerFieldMenu')
            ? 'worker_field_' . $row['custom_field_id']
            : 'custom_field_' . $row['custom_field_id'];
        $actions = [[
            'href' => self::url('option=modify_custom_field&custom_field_id=') . $id,
            'label' => 'Edit',
            'tone' => 'quiet',
        ]];
        if ((FieldTypes::normalise((string)$row['field_type']) === FieldTypes::MENU || $row['field_type'] == 'workerFieldMenu')
            && !MenuOptions::isLegacyChild((int)$row['custom_field_id'])) {
            $actions[] = [
                'href' => self::url('option=modify_menu_values&custom_field_id=') . $id,
                'label' => 'Menu values',
                'tone' => 'quiet',
            ];
        }
        $sharedInUse = (int) ($row['shared'] ?? 0) === 1
            && Database::exists('item_type_custom_fields', 'custom_field_id = ?', [(int) $row['custom_field_id']]);
        if (!$sharedInUse) {
            $actions[] = [
                'href' => self::url('option=delete_custom_field&custom_field_id=') . $id,
                'label' => 'Delete',
                'tone' => 'danger',
                'confirm' => $name . "\nThis cannot be undone.",
            ];
        }

        return [
            'name' => $name,
            'href' => self::url('option=modify_custom_field&custom_field_id=') . $id,
            'cells' => [
                'type' => FieldTypes::label((string) $row['field_type']),
                'enabled' => (string)$row['enabled'],
            ],
            'actions' => $actions,
        ];
    }

    public static function showFieldTypeResults()
    {
        $html = '';
        $table = '';
        if ((Request::postedValue('type') ?? '') == 'custom_field_name') {
            $table = 'custom_fields';
        } elseif ((Request::postedValue('type') ?? '') == 'item_type_name') {
            $table = 'item_types';
        }
        if ($table === '') {
            self::showFields();
            return;
        }

        $column = $table === 'custom_fields' ? 'custom_field_name' : 'item_type_name';
        $criteria = (string) (Request::postedValue('criteria') ?? '');
        if ((Request::postedValue('operator') ?? '') === '=') {
            $result = Database::select($table, '*', $column . ' = ?', [$criteria], 'enabled DESC, ' . $column . ' ASC');
        } else {
            $result = Database::select($table, '*', $column . " LIKE ? ESCAPE '\\'", ['%' . Database::escapeLike($criteria) . '%'], 'enabled DESC, ' . $column . ' ASC');
        }
        if ($table !== 'custom_fields') {
            header('Location: ' . Router::manageUrl('record-types'));
            exit;
        }

        $rows = [];
        if ($result && count($result) > 0) {
            foreach ($result as $row) {
                $rows[] = self::customFieldRecord($row);
            }
        }

        $html = self::fieldRecordList([['rows' => $rows]], false, true);

        $bodyBlock = [
            'title' => 'Search results',
            'html' => $html,
        ];
        $contentBody = Blocks::vertical([$bodyBlock]);
        Page::current()->setBody($contentBody);
    }

    /**
     * updateCustomField()
     *
     * Updates custom field information.  Controller logic: update_custom_field
     *
     * @param mixed $customFieldID Custom field id
     */
    public static function updateCustomField($customFieldID)
    {
        $customFieldID = (int) $customFieldID;
        $back = Blocks::link(self::url('option=manage_fields'), 'Fields');
        if (!Database::exists('custom_fields', 'custom_field_id = ?', [$customFieldID])) {
            View::respond('That field is not here.', $back);
            return;
        }
        $columns = self::postedFieldColumns();
        if ($columns['custom_field_name'] === '') {
            View::respond('Enter a field name.', $back);
            return;
        }
        if (Database::exists('custom_fields', 'LOWER(custom_field_name) = LOWER(?) AND custom_field_id <> ?', [$columns['custom_field_name'], $customFieldID])) {
            View::respond($columns['custom_field_name'] . ' already exists.', $back);
            return;
        }
        Database::update('custom_fields', $columns, 'custom_field_id = ?', [$customFieldID]);
        View::respond($columns['custom_field_name'] . ' was updated.', $back);
    }

    /**
     * Deletes a custom field, its menu values, item-type links, and the items column when one was added.
     */
    public static function deleteCustomField(): void
    {
        $customFieldID = (string)(Request::queryValue('custom_field_id') ?? '');
        if ($customFieldID === '' || !ctype_digit($customFieldID)) {
            View::respond('That field is not here.', Blocks::link(self::url('option=manage_fields'), 'Fields'));
            return;
        }

        $row = Database::first('custom_fields', '*', 'custom_field_id = ?', [(int) $customFieldID]);
        if (!$row) {
            View::respond('That field is not here.', Blocks::link(self::url('option=manage_fields'), 'Fields'));
            return;
        }

        $name = (string)$row['custom_field_name'];
        $type = (string)$row['field_type'];
        $params = [(int) $customFieldID];
        if ((int) ($row['shared'] ?? 0) === 1 && Database::exists('item_type_custom_fields', 'custom_field_id = ?', $params)) {
            View::respond($name . ' is used by a record type, so it stays.', Blocks::link(self::url('option=manage_fields'), 'Fields'));
            return;
        }
        Database::delete('custom_field_menu_values', 'custom_field_id = ?', $params);
        Database::delete('item_type_custom_fields', 'custom_field_id = ?', $params);
        Database::delete('custom_fields', 'custom_field_id = ?', $params);
        \Adlexone\Data\ItemFields::deleteField((int) $customFieldID);

        View::respond($name . ' was deleted.', Blocks::link(self::url('option=manage_fields'), 'Fields'));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function normaliseFieldPayload(array $payload): array
    {
        $type = FieldTypes::normalise((string) ($payload['field_type'] ?? FieldTypes::TEXT_BOX));
        $payload['field_type'] = $type;
        $implied = FieldTypes::impliedValidation($type);
        if ($implied !== '') {
            $payload['validation_type'] = $implied;
        } else {
            $payload['validation_type'] = FieldTypes::normaliseKind((string) ($payload['validation_type'] ?? ''));
        }
        return $payload;
    }

    private static function url(string $query = ''): string
    {
        $scope = trim((string) (Request::queryValue('application') ?? ''));
        if ($scope !== '') {
            $query = 'application=' . rawurlencode($scope) . ($query !== '' ? '&' . ltrim($query, '&') : '');
        }

        return Router::manageUrl('fields', $query);
    }
}
