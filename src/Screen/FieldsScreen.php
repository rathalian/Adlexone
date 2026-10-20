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

/**
 * Screen formerly loaded from legacy/.
 */
final class FieldsScreen
{
    public static function open(): void
    {
        MenuOptions::prepare();
        Page::current()->setNavOption('__section__');
        RenderNavigation::applySectionNav('Fields', RenderNavigation::itemSettingsURLs());
        $scope = trim((string) ($_GET['application'] ?? ''));
        define('FIELDS_BASE_URL', '?manage=fields' . ($scope !== '' ? '&application=' . rawurlencode($scope) : ''));
















































        switch ((string) ($_GET['option'] ?? '')) {
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
                self::showCustomField((string) ($_GET['custom_field_id'] ?? ''));
                break;
            case 'modify_menu_values':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::modifyMenuValues((string) ($_GET['custom_field_id'] ?? ''));
                break;
            case 'add_update_menu_value':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::addUpdateMenuValue((string) ($_POST['custom_field_id'] ?? ''));
                break;
            case 'update_menu_value_filter':
            case 'modify_menu_value_filters':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::modifyMenuValues((string) ($_GET['custom_field_id'] ?? $_POST['custom_field_id'] ?? ''));
                break;
            case 'delete_menu_value':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::deleteMenuValue((string) ($_GET['menu_value_id'] ?? ''), (string) ($_GET['custom_field_id'] ?? ''));
                break;
            case 'update_custom_field':
                Access::require(\Adlexone\Auth\Permission::ADMIN_ITEMS);
                self::updateCustomField((string) ($_POST['custom_field_id'] ?? ''));
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
            $formAction = '?manage=fields&option=add_custom_field';
            $fieldValues = $values;
            $title = 'Add field';
        } else {
            $columnArray = array('*');
            $condition = "WHERE custom_field_id = '$customFieldID'";
            $fieldValues = Database::first('custom_fields', $columnArray, $condition);
            $formAction = '?manage=fields&option=update_custom_field';
            $title = 'Edit field';
        }

        $fields[TXT_86] = Form::text('custom_field_name', @$fieldValues['custom_field_name']);
        $fields[TXT_210] = Form::text('default_value', @$fieldValues['default_value']);
        $fieldTypes = array(FieldTypes::TEXT_BOX, 'password', FieldTypes::TEXT_AREA, FieldTypes::MENU, 'hidden', 'URL', 'dynamicURL', 'workerField', 'workerFieldMenu');
        $fieldNames = array_map(static fn (string $type): string => FieldTypes::label($type), $fieldTypes);
        $fields[TXT_65] = Form::select('field_type', $fieldTypes, $fieldNames, FieldTypes::normalise(@$fieldValues['field_type']));
        $fields[TXT_477] = Form::text('data', @$fieldValues['data']);
        // Javascript field validation setup
        $fields[TXT_505] = Form::select('required', array('Yes', 'No'), array(TXT_93, TXT_94), @$fieldValues['required']);
        $validationTypes = array(NULL, 'numeric', 'string', 'alphanumeric', 'date', 'time', 'datetime', 'ip', 'email');
        $validationNames = array(TXT_525, TXT_507, TXT_508, TXT_509, TXT_510, TXT_511, TXT_512, TXT_513, TXT_514);
        $fields[TXT_506] = Form::select('validation_type', $validationTypes, $validationNames, @$fieldValues['validation_type']);
        $fields[TXT_90] = Form::select('enabled', array('Yes', 'No'), array(TXT_93, TXT_94), @$fieldValues['enabled']);
        $fields[''] = Form::hidden('custom_field_id', $customFieldID);
        $jsFieldNameArray = "['custom_field_name']";
        $jsTestTypeArray = "['']";
        $jsErrorMsgArray = "['']";
        $jsRequiredMsgArray = "['" . TXT_217 . "']";
        $jsRequiredArray = "[true]";
        $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";
        Page::current()->suggestTitle($title);

        Page::current()->setBody(Form::form(
            $title,
            $formAction,
            $fields,
            [
                Form::button('submit', 'submit_button', TXT_74, $javascript),
                Form::button('reset', 'reset', TXT_75),
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
        $fields[TXT_288] = '<div class="menu-editor-wrap"><p class="field-note">' . Html::e(TXT_695) . '</p>'
            . MenuOptions::editorRows($fieldId) . '</div>';
        $fields[TXT_292] = Form::text('menu_value', '');
        $fields[TXT_693] = MenuOptions::parentSelect('parent_menu_value_id', $fieldId, 0);
        $fields[''] = Form::hidden('custom_field_id', $customFieldID);

        $buttons = [
            Form::button('submit', 'add_new', TXT_545),
            Form::button('submit', 'update_existing', TXT_542),
            Form::button('reset', 'reset', TXT_75),
        ];

        Page::current()->setBody(Form::form(
            TXT_287,
            '?manage=fields&option=add_update_menu_value',
            $fields,
            $buttons
        ));
    }

    public static function addUpdateMenuValue($customFieldID)
    {
        $fieldId = (int)$customFieldID;
        if (isset($_POST['add_new']) and $_POST['add_new'] != '') {
            $value = trim((string)($_POST['menu_value'] ?? ''));
            $parentId = (int)($_POST['parent_menu_value_id'] ?? 0);
            if ($value !== '' && MenuOptions::valueExists($fieldId, $value, $parentId)) {
                $html = TXT_294;
                $html .= '<br><br><a href ="' . FIELDS_BASE_URL . '&option=modify_menu_values&custom_field_id=' . $customFieldID . '" class="URL">' . TXT_31 . '</a>  ';
                Page::current()->setBody($html);
                return;
            }
            if ($value !== '') {
                MenuOptions::add($fieldId, $value, $parentId);
            }
            self::modifyMenuValues($customFieldID);
            return;
        }
        if (isset($_POST['update_existing']) and $_POST['update_existing'] != '') {
            MenuOptions::applyEdits($fieldId, $_POST);
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
        // Remove unwanted POST variables
        unset ($_POST['submit_button'], $_POST['reset'], $_POST['custom_field_id']);
        // Check for duplicate and error handling
        $columnArray = array('custom_field_name');
        $condition = "WHERE custom_field_name = '" . $_POST['custom_field_name'] . "'";
        $result = Database::select('custom_fields', $columnArray, $condition);
        if (count($result) == 0) {
            // Insert form field values into row (AUTOINCREMENT custom_field_id)
            $columnArray = $_POST;
            unset($columnArray['custom_field_id']);
            $columnArray['data'] = html_entity_decode((string) ($columnArray['data'] ?? ''), ENT_COMPAT, 'UTF-8');
            $id = Database::insert('custom_fields', $columnArray);
            Database::update('custom_fields', ['field_reference' => $id], 'custom_field_id = ?', [$id]);
            // Field values live in item_field_values — items schema is not altered.
            View::respond($_POST['custom_field_name'] . ' ' . TXT_162, Blocks::link(FIELDS_BASE_URL . '&option=manage_fields', TXT_362));
            return;
        }
        View::respond($_POST['custom_field_name'] . ' ' . TXT_163, Blocks::link(FIELDS_BASE_URL . '&option=new_custom_field', TXT_362));
    }

    /**
     * showSearchOptions
     *
     * Render a small search form for custom fields or item types using the
     * Form::form pattern. The page heading constant `HEADING` is
     * deprecated — the form title is passed directly to `buildForm`.
     *
     * Note: `buildFormSectionHeading` is intentionally not used in this pattern.
     *
     * @return void
     */
    public static function showSearchOptions(): void
    {
        $formFields = [];
        $formFields[TXT_682] = Form::select(
            'type',
            ['custom_field_name', 'item_type_name'],
            [TXT_86, TXT_87],
            ''
        );
        $formFields[TXT_678] = Form::select(
            'operator',
            ['LIKE', '='],
            [TXT_80, TXT_81],
            ''
        );
        $formFields[TXT_82] = Form::text('criteria', '');

        // Create form buttons
        $buttons = [];
        $buttons[] = Form::button('submit', 'submit_button', TXT_74);
        $buttons[] = Form::button('reset', 'reset', TXT_75);

        // Render the form using the modern helper.
        // First parameter is the form title (replaces the deprecated HEADING constant).
        $html = Form::form(
            TXT_52,
            '?manage=fields&option=field_type_search',
            $formFields,
            $buttons
        );

        // Set the body content and include the main page layout
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
        $appSlug = trim((string) ($_GET['application'] ?? ''));
        $appName = '';
        if ($appSlug !== '') {
            $app = \Adlexone\Application\ApplicationStore::findBySlug($appSlug);
            $appName = $app === null ? '' : (string) $app['name'];
        }
        $unused = ($_GET['unused'] ?? '') === '1' && $appName === '';
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
            ['title' => TXT_53, 'html' => self::fieldRecordList($listGroups, $unused, $appName !== '')],
        ]));
    }

    /**
     * @param list<array{label?: string, rows: list<array<string, mixed>>}> $groups
     */
    public static function fieldRecordList(array $groups, bool $unused = false, bool $scoped = false): string
    {
        $toggle = '';
        if (!$scoped) {
            $href = $unused ? FIELDS_BASE_URL : FIELDS_BASE_URL . '&unused=1';
            $toggle = '<div class="record-list__tools">'
                . Blocks::link($href, $unused ? 'In use' : 'Unused', '', 'btn btn--sm')
                . '</div>';
        }

        return Blocks::recordList([
            'column' => 'Field',
            'columns' => [
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'enabled', 'label' => 'Enabled'],
            ],
            'searchLabel' => TXT_3,
            'primary' => ['href' => FIELDS_BASE_URL . '&option=new_custom_field', 'label' => 'Add field'],
            'toolbar' => $toggle,
            'empty' => $unused ? 'Every field is used by an application.' : 'No fields for this view.',
            'emptyIllustration' => 'empty-fields',
            'emptyTitle' => $unused ? 'Every field is in use' : 'No fields yet',
            'emptyCopy' => $unused ? 'Unused fields are listed when you ask for them.' : 'Add a field, then use it on a record type.',
            'noMatch' => TXT_689,
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
            'href' => FIELDS_BASE_URL . '&option=modify_custom_field&custom_field_id=' . $id,
            'label' => TXT_626,
            'tone' => 'quiet',
        ]];
        if ((FieldTypes::normalise((string)$row['field_type']) === FieldTypes::MENU || $row['field_type'] == 'workerFieldMenu')
            && !MenuOptions::isLegacyChild((int)$row['custom_field_id'])) {
            $actions[] = [
                'href' => FIELDS_BASE_URL . '&option=modify_menu_values&custom_field_id=' . $id,
                'label' => TXT_287,
                'tone' => 'quiet',
            ];
        }
        $actions[] = [
            'href' => FIELDS_BASE_URL . '&option=delete_custom_field&custom_field_id=' . $id,
            'label' => TXT_47,
            'tone' => 'danger',
            'confirm' => $name . "\n" . TXT_400,
        ];

        return [
            'name' => $name,
            'href' => FIELDS_BASE_URL . '&option=modify_custom_field&custom_field_id=' . $id,
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
        if (($_POST['type'] ?? '') == 'custom_field_name') {
            $table = 'custom_fields';
        } elseif (($_POST['type'] ?? '') == 'item_type_name') {
            $table = 'item_types';
        }
        if ($table === '') {
            self::showFields();
            return;
        }

        $columnArray = array('*');
        if ($_POST['operator'] == '=') {
            $condition = "WHERE " . $_POST['type'] . " = '" . $_POST['criteria'] . "' ORDER BY enabled DESC, " . $_POST['type'] . " ASC";
        } elseif ($_POST['operator'] == 'LIKE') {
            $condition = "WHERE " . $_POST['type'] . " LIKE '%" . $_POST['criteria'] . "%' ORDER BY enabled DESC, " . $_POST['type'] . " ASC";
        }
        $result = Database::select($table, $columnArray, $condition);
        if ($table !== 'custom_fields') {
            header('Location: ?manage=item-types');
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
            'title' => TXT_113,
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
        // Remove unwanted POST variables
        unset ($_POST['submit_button'], $_POST['reset']);
        // Build insert array
        $columnArray = $_POST;
        // Handle data column special characters
        $columnArray['data'] = html_entity_decode($columnArray['data'], ENT_COMPAT, 'UTF-8');
        // Set condition
        $condition = "WHERE custom_field_id = '$customFieldID'";
        // Update form field values into row
        Database::update('custom_fields', $columnArray, $condition);
        View::respond($_POST['custom_field_name'] . ' ' . TXT_164, Blocks::link(FIELDS_BASE_URL . '&option=manage_fields', TXT_362));
    }

    /**
     * Deletes a custom field, its menu values, item-type links, and the items column when one was added.
     */
    public static function deleteCustomField(): void
    {
        $customFieldID = (string)($_GET['custom_field_id'] ?? '');
        if ($customFieldID === '' || !ctype_digit($customFieldID)) {
            View::respond(TXT_115, Blocks::link(FIELDS_BASE_URL . '&option=manage_fields', TXT_362));
            return;
        }

        $row = Database::first('custom_fields', '*', "WHERE custom_field_id = '" . $customFieldID . "'");
        if (!$row) {
            View::respond(TXT_115, Blocks::link(FIELDS_BASE_URL . '&option=manage_fields', TXT_362));
            return;
        }

        $name = (string)$row['custom_field_name'];
        $type = (string)$row['field_type'];
        $condition = "WHERE custom_field_id = '" . $customFieldID . "'";
        Database::delete('custom_field_menu_values', $condition);
        Database::delete('item_type_custom_fields', $condition);
        Database::delete('custom_fields', $condition);
        \Adlexone\Data\ItemFields::deleteField((int) $customFieldID);

        View::respond($name . ' ' . TXT_47, Blocks::link(FIELDS_BASE_URL . '&option=manage_fields', TXT_362));
    }
}
