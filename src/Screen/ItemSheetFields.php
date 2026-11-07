<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Application\WorkflowFields;
use Adlexone\Database\Database;
use Adlexone\Data\FieldCheck;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\FieldValidation;
use Adlexone\Data\MenuOptions;
use Adlexone\Html\Blocks;
use Adlexone\Html\FieldControl;
use Adlexone\Html\Form;
use Adlexone\Html\Html;
use Adlexone\Http\Page;

/**
 * One custom field as the sheet draws it: which field, how it is drawn, and the stored text.
 */
final class SheetField
{
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $value,
        public readonly string $source,
    ) {
    }
}

/**
 * Custom fields on the record sheet. A value the person cannot edit is escaped before it is shown.
 */
final class ItemSheetFields
{
    public static function lockedValue(string $name, string $value, string $text): string
    {
        return Form::hidden($name, $value)
            . '<div class="readonly-value">' . Html::e($text) . '</div>';
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $posted
     */
    public static function fill(
        array &$fields,
        FieldCheck $check,
        int $typeId,
        bool $creating,
        bool $locked,
        bool $plainLabels,
        array $stored,
        array $posted,
    ): void {
        $rows = Database::select(
            'item_type_custom_fields',
            ['custom_field_id'],
            'item_type_id = ?',
            [$typeId],
            'custom_field_order ASC'
        );
        $hasPost = $creating ? isset($posted['user_security']) : isset($posted['item_id']);
        foreach ($rows as $link) {
            $row = self::fieldRow((int) $link['custom_field_id']);
            if ($row === null) {
                continue;
            }
            $row['field_type'] = FieldTypes::normalise((string) ($row['field_type'] ?? ''));
            if ($row['enabled'] != 'Yes') {
                continue;
            }
            $source = (string) $row['field_type'];
            if (($source === 'workerField' || $source === 'workerFieldMenu') && !Access::canAssignRecords()) {
                continue;
            }
            $id = (int) $row['custom_field_id'];
            if (($creating || !$locked) && MenuOptions::coveredByParentOnItemType($id, $typeId)) {
                continue;
            }
            $prefix = stristr($source, 'worker') ? 'worker_field_' : 'custom_field_';
            if ($plainLabels) {
                $row['custom_field_name'] = self::label((string) $row['custom_field_name']);
            }
            $key = 'custom_field_' . $id;
            if ($creating) {
                $value = $hasPost ? (string) ($stored[$key] ?? '') : (string) ($row['default_value'] ?? '');
                $menuValue = $value;
            } elseif (!$hasPost) {
                $value = (string) ($stored[$key] ?? '');
                $menuValue = $value;
            } else {
                $value = stripslashes((string) ($posted[$key] ?? ''));
                $menuValue = (string) ($posted[$key] ?? '');
            }
            $check->add($row, $prefix . $row['custom_field_id']);
            $field = new SheetField($id, ($locked && $source !== 'fieldSeparator') ? 'text' : $source, $value, $source);
            $html = self::control($field, $row, $typeId, $creating, $hasPost, $menuValue, $stored, $posted);
            if ($html !== null) {
                $fields[(string) $row['custom_field_name']] = $html;
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $posted
     */
    private static function control(
        SheetField $field,
        array $row,
        int $typeId,
        bool $creating,
        bool $hasPost,
        string $menuValue,
        array $stored,
        array $posted,
    ): ?string {
        return match ($field->type) {
            'text' => self::readOnly($field, $row),
            FieldTypes::TEXT_BOX, 'password', 'hidden', FieldTypes::TEXT_AREA, 'workerField' => self::input($field, $row, $field->type === 'workerField'),
            'URL' => $creating ? self::input($field, $row, false) : self::url($field),
            'dynamicURL' => $creating ? self::input($field, $row, false) : self::dynamicUrl($field, $row),
            FieldTypes::MENU => self::menu($field, $row, $typeId, $creating, $hasPost, $menuValue, $stored, $posted),
            FieldTypes::CHECK_BOX => Form::checkbox(
                'custom_field_' . $field->id,
                (string) $field->id,
                (!$creating && $field->value !== '' && $field->value !== '0') ? (string) $field->id : ''
            ),
            'workerFieldMenu' => self::workerMenu($row),
            default => self::marked($field, $row, $typeId),
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function readOnly(SheetField $field, array $row): string
    {
        $shown = $field->source === FieldTypes::TEXT_AREA
            ? '<br />' . nl2br(Html::e($field->value), false) . '<br /><br />'
            : Html::e(FieldValidation::display($row, $field->value));

        return $shown . Form::hidden('custom_field_' . $field->id, $field->value);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function input(SheetField $field, array $row, bool $workerName): ?string
    {
        $prefix = $workerName ? 'worker_field_' : 'custom_field_';

        return FieldControl::render($field->type, $prefix . $field->id, $field->value, $row);
    }

    private static function url(SheetField $field): string
    {
        $link = '';
        if ($field->value !== '') {
            $http = (!stristr($field->value, 'http') && !stristr($field->value, 'https')) ? 'http://' : '';
            $link = ' ' . Blocks::link($http . $field->value, TXT_480, 'URL', SET_IMAGE_PATH . 'hyperlink.png', '', '_blank');
        }

        return Form::url('custom_field_' . $field->id, $field->value) . $link;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function dynamicUrl(SheetField $field, array $row): string
    {
        $link = '';
        if ($field->value !== '') {
            $href = str_replace('<?php echo INSERT; ?>', $field->value, (string) ($row['data'] ?? ''));
            $link = ' ' . Blocks::link($href, TXT_480, 'URL', SET_IMAGE_PATH . 'hyperlink.png', '', '_blank');
        }

        return Form::text('custom_field_' . $field->id, $field->value) . $link;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $posted
     */
    private static function menu(
        SheetField $field,
        array $row,
        int $typeId,
        bool $creating,
        bool $hasPost,
        string $menuValue,
        array $stored,
        array $posted,
    ): string {
        $childId = (int) ($row['sub_menu'] ?? 0);
        $bag = ($creating || !$hasPost) ? $stored : $posted;
        $childStored = $childId > 0 ? (string) ($bag['custom_field_' . $childId] ?? '') : '';

        return self::menuHtml($typeId, $field->id, $menuValue, $childStored, $hasPost ? $posted : []);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function workerMenu(array $row): string
    {
        $menu = [];
        $result = Database::select(
            'custom_field_menu_values',
            ['menu_value'],
            'custom_field_id = ?',
            [(int) $row['custom_field_id']]
        );
        foreach ($result as $menuRow) {
            $menu[] = $menuRow['menu_value'];
        }

        return Form::select(
            'worker_field_menu_' . $row['custom_field_id'],
            $menu,
            $menu,
            $row['default_value'] ?? ''
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function marked(SheetField $field, array $row, int $typeId): ?string
    {
        $control = self::input($field, $row, false);
        if ($control === null) {
            return null;
        }

        return self::markWait($typeId, $field->id, $control);
    }

    private static function label(string $name): string
    {
        $plain = WorkScreen::plainFieldName($name);
        if ($plain === '') {
            return $name;
        }

        return ucfirst($plain);
    }

    /**
     * @param array<string, mixed> $request
     */
    private static function menuHtml(int $typeId, int $fieldId, string $stored, string $childStored, array $request): string
    {
        $choices = WorkflowFields::scopedStatusValues($typeId, $fieldId);
        if ($choices !== null) {
            if ($stored !== '' && !in_array($stored, $choices, true)) {
                $choices[] = $stored;
            }
            $html = Form::select('custom_field_' . $fieldId, $choices, $choices, $stored);
        } else {
            $html = MenuOptions::controls($fieldId, $stored, $childStored, $request);
        }

        return self::markWait($typeId, $fieldId, $html);
    }

    private static function markWait(int $typeId, int $fieldId, string $html): string
    {
        if ($html === '' || $typeId <= 0 || !Page::current()->inApplication()) {
            return $html;
        }
        $slug = Page::current()->applicationSlug();
        $status = WorkflowFields::resolve($slug, $typeId, WorkflowFields::STATUS);
        $due = WorkflowFields::resolve($slug, $typeId, WorkflowFields::DUE);
        if ($status === null || $due === null) {
            return $html;
        }
        $waits = WorkflowFields::waitStatuses($slug, $typeId);
        if ($waits === []) {
            return $html;
        }
        if ($fieldId === (int) $status['id']) {
            $html = preg_replace(
                '/<select\b/',
                '<select data-waits="' . Html::e(implode('|', $waits)) . '" data-due-name="custom_field_' . (int) $due['id'] . '"',
                $html,
                1
            ) ?? $html;
            Page::current()->addScript('work-board');
        }
        if ($fieldId === (int) $due['id']) {
            $html = preg_replace('/<input\b/', '<input data-due-field="1"', $html, 1) ?? $html;
            Page::current()->addScript('work-board');
        }

        return $html;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fieldRow(int $fieldId): ?array
    {
        if ($fieldId <= 0) {
            return null;
        }

        return Database::first('custom_fields', '*', 'custom_field_id = ?', [$fieldId]);
    }
}
