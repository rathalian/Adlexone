<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Data\ItemFields;
use Adlexone\Screen\WorkScreen;
use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\FieldValidation;

/**
 * Records and fields for the screens an application adds beyond Work.
 *
 * The set is the same one Work uses: owned, created, or shared through a group.
 */
final class RecordQuery
{
    /**
     * @param array<string, mixed> $config
     * @return array{type_id: int, ids: list<int>, items: array<int, array{id: int, title: string, created: int, owner_id: int, owner: string}>}|null
     */
    public static function load(array $config): ?array
    {
        $typeId = self::typeId($config);
        if ($typeId <= 0) {
            return null;
        }
        $ids = WorkScreen::applicationAccessibleItemIds($typeId);
        $items = [];
        if ($ids !== []) {
            $rows = Database::select(
                'items',
                ['item_id', 'item_title', 'create_date', 'user_security'],
                'item_id IN (' . self::intList($ids) . ')'
            );
            $owners = [];
            foreach ($rows as $row) {
                $id = (int) $row['item_id'];
                $ownerId = (int) ($row['user_security'] ?? 0);
                $items[$id] = [
                    'id' => $id,
                    'title' => trim((string) ($row['item_title'] ?? '')),
                    'created' => (int) ($row['create_date'] ?? 0),
                    'owner_id' => $ownerId,
                    'owner' => '',
                ];
                if ($ownerId > 0) {
                    $owners[$ownerId] = true;
                }
            }
            if ($owners !== []) {
                $names = self::userNames(array_keys($owners));
                foreach ($items as $id => $item) {
                    $items[$id]['owner'] = $names[$item['owner_id']] ?? '';
                }
            }
        }

        return [
            'type_id' => $typeId,
            'ids' => $ids,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function typeId(array $config): int
    {
        $raw = trim((string) ($config['item_type_id'] ?? ''));
        if ($raw !== '' && ctype_digit($raw)) {
            return (int) $raw;
        }
        $slug = \Adlexone\Http\Page::current()->inApplication()
            ? \Adlexone\Http\Page::current()->applicationSlug()
            : '';
        if ($slug === '') {
            $slug = trim((string) ($_SESSION['application_slug'] ?? ''));
        }
        $fallback = $slug !== '' ? ApplicationSettings::defaultItemTypeId($slug) : '';

        return ctype_digit($fallback) ? (int) $fallback : 0;
    }

    /**
     * @return list<array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}>
     */
    public static function fields(int $typeId): array
    {
        if ($typeId <= 0) {
            return [];
        }
        $fields = [];
        $links = Database::select(
            'item_type_custom_fields',
            ['custom_field_id'],
            'item_type_id = ?',
            [$typeId],
            'custom_field_order ASC'
        );
        foreach ($links as $link) {
            $row = Database::first(
                'custom_fields',
                ['custom_field_id', 'custom_field_name', 'field_type', 'enabled'],
                'custom_field_id = ?',
                [(int) $link['custom_field_id']]
            );
            if ($row === null || (string) ($row['enabled'] ?? '') !== 'Yes') {
                continue;
            }
            $name = (string) $row['custom_field_name'];
            $plain = WorkScreen::plainFieldName($name);
            $type = FieldTypes::normalise((string) ($row['field_type'] ?? ''));
            $id = (int) $row['custom_field_id'];
            $values = [];
            if ($type === FieldTypes::MENU) {
                $scoped = WorkflowFields::scopedStatusValues($typeId, $id);
                if ($scoped !== null) {
                    $values = $scoped;
                } else {
                    foreach (Database::select('custom_field_menu_values', ['menu_value'], 'custom_field_id = ?', [$id], 'menu_value_id ASC') as $menu) {
                        $value = trim((string) ($menu['menu_value'] ?? ''));
                        if ($value !== '') {
                            $values[] = $value;
                        }
                    }
                }
            }
            $fields[] = [
                'id' => $id,
                'name' => $name,
                'plain' => $plain,
                'label' => $plain === '' ? $name : ucfirst($plain),
                'type' => $type,
                'values' => $values,
            ];
        }

        return $fields;
    }

    /**
     * @param list<array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}> $fields
     * @return array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}|null
     */
    public static function field(array $fields, int $id): ?array
    {
        foreach ($fields as $field) {
            if ($field['id'] === $id) {
                return $field;
            }
        }

        return null;
    }

    /**
     * The field on the link, or the one this screen would pick on its own.
     *
     * @param list<array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}> $fields
     * @param array<string, mixed> $config
     * @return array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}|null
     */
    public static function chosen(array $fields, array $config, string $purpose): ?array
    {
        $picked = self::field($fields, (int) ($config['field_id'] ?? 0));
        if ($picked !== null) {
            return $picked;
        }

        return match ($purpose) {
            'menu' => self::prefer($fields, FieldTypes::MENU, ['status']) ?? self::firstOf($fields, FieldTypes::MENU),
            'date' => self::dueDate($fields),
            'party' => self::party($fields),
            'step' => self::prefer($fields, FieldTypes::TEXT_BOX, ['next step'])
                ?? self::prefer($fields, FieldTypes::TEXT_AREA, ['next step']),
            default => null,
        };
    }

    /**
     * Status values that still count as open. Null means every record stays.
     *
     * @param array<string, mixed> $config
     * @param array{id: int, values: list<string>}|null $status
     * @return list<string>|null
     */
    public static function openValues(array $config, ?array $status): ?array
    {
        $listed = self::strings($config['open_statuses'] ?? []);
        if ($listed !== []) {
            return $listed;
        }
        if ($status === null || count($status['values']) < 2) {
            return null;
        }
        $values = $status['values'];
        array_pop($values);

        return $values;
    }

    /**
     * @param list<string>|null $open
     */
    public static function isOpen(?array $open, string $value): bool
    {
        if ($open === null || $value === '') {
            return true;
        }

        return in_array($value, $open, true);
    }

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    public static function values(array $ids, int $fieldId): array
    {
        $map = [];
        if ($fieldId <= 0 || $ids === []) {
            return $map;
        }
        $rows = Database::rows(
            'SELECT item_id, value FROM item_field_values WHERE custom_field_id = ? AND item_id IN (' . self::intList($ids) . ')',
            [$fieldId]
        );
        foreach ($rows as $row) {
            $map[(int) $row['item_id']] = trim((string) ($row['value'] ?? ''));
        }

        return $map;
    }

    /**
     * @param list<int> $ids
     * @param list<int> $fieldIds
     * @return array<int, array<int, string>>
     */
    public static function valueMap(array $ids, array $fieldIds): array
    {
        $map = [];
        $fieldIds = array_values(array_filter(array_map('intval', $fieldIds), static fn (int $id): bool => $id > 0));
        if ($ids === [] || $fieldIds === []) {
            return $map;
        }
        $rows = Database::rows(
            'SELECT item_id, custom_field_id, value FROM item_field_values WHERE item_id IN ('
            . self::intList($ids) . ') AND custom_field_id IN (' . self::intList($fieldIds) . ')'
        );
        foreach ($rows as $row) {
            $map[(int) $row['item_id']][(int) $row['custom_field_id']] = trim((string) ($row['value'] ?? ''));
        }

        return $map;
    }

    public static function saveField(int $itemId, int $fieldId, string $value): void
    {
        if ($itemId <= 0 || $fieldId <= 0) {
            return;
        }
        $key = 'custom_field_' . $fieldId;
        $previous = $_POST[$key] ?? null;
        $_POST[$key] = $value;
        Rules::run($itemId, 'update_item');
        ItemFields::saveFromArray($itemId, [$key => $value]);
        if ($previous === null) {
            unset($_POST[$key]);
        } else {
            $_POST[$key] = $previous;
        }
    }

    public static function saveOwner(int $itemId, int $userId): void
    {
        if ($itemId <= 0) {
            return;
        }
        $previous = $_POST['user_security'] ?? null;
        $_POST['user_security'] = (string) $userId;
        Rules::run($itemId, 'update_item');
        Database::update('items', ['user_security' => $userId], 'item_id = ?', [$itemId]);
        if ($previous === null) {
            unset($_POST['user_security']);
        } else {
            $_POST['user_security'] = $previous;
        }
    }

    /**
     * A person is shown by full name, and by username only when no name is set.
     *
     * @param array<string, mixed> $user
     */
    public static function displayName(array $user): string
    {
        $full = trim(trim((string) ($user['first_name'] ?? '')) . ' ' . trim((string) ($user['last_name'] ?? '')));

        return $full !== '' ? $full : trim((string) ($user['user_name'] ?? ''));
    }

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    public static function userNames(array $ids): array
    {
        $names = [];
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return $names;
        }
        foreach (Database::select('users', ['user_id', 'user_name', 'first_name', 'last_name'], 'user_id IN (' . self::intList($ids) . ')') as $user) {
            $names[(int) $user['user_id']] = self::displayName($user);
        }

        return $names;
    }

    public static function display(array $field, string $value): string
    {
        if ($field['type'] === FieldTypes::CHECK_BOX) {
            return ($value !== '' && $value !== '0') ? 'Yes' : '';
        }
        if ($field['type'] === FieldTypes::DATE || $field['type'] === FieldTypes::DATETIME) {
            $date = FieldValidation::parseDate($value);
            if ($date === null) {
                return $value;
            }
            $format = defined('SET_DATE_FORMAT') ? trim(explode(',', (string) SET_DATE_FORMAT)[0]) : 'j M Y';

            return $date->format($format !== '' ? $format : 'j M Y');
        }

        return $value;
    }

    /**
     * @param list<int>|array<int, mixed> $ids
     */
    public static function intList(array $ids): string
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }

        return $clean === [] ? '0' : implode(',', $clean);
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return list<int>
     */
    public static function ints(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $item = (int) $item;
            if ($item > 0) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param list<array{id: int, plain: string, type: string}> $fields
     * @param list<string> $names
     * @return array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}|null
     */
    private static function prefer(array $fields, string $type, array $names): ?array
    {
        foreach ($names as $name) {
            foreach ($fields as $field) {
                if ($field['type'] === $type && $field['plain'] === $name) {
                    return $field;
                }
            }
        }
        if ($type === FieldTypes::MENU) {
            foreach ($fields as $field) {
                if ($field['type'] === $type && str_contains($field['plain'], 'status')) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * @param list<array{id: int, type: string}> $fields
     * @return array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}|null
     */
    private static function firstOf(array $fields, string $type): ?array
    {
        foreach ($fields as $field) {
            if ($field['type'] === $type) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param list<array{id: int, plain: string, type: string}> $fields
     * @return array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}|null
     */
    private static function dueDate(array $fields): ?array
    {
        $dates = [];
        foreach ($fields as $field) {
            if ($field['type'] === FieldTypes::DATE || $field['type'] === FieldTypes::DATETIME) {
                $dates[] = $field;
            }
        }
        foreach ($dates as $field) {
            if (str_contains($field['plain'], 'due')) {
                return $field;
            }
        }
        foreach ($dates as $field) {
            if (!str_ends_with($field['plain'], ' on')) {
                return $field;
            }
        }

        return $dates[0] ?? null;
    }

    /**
     * @param list<array{id: int, plain: string, type: string}> $fields
     * @return array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}|null
     */
    private static function party(array $fields): ?array
    {
        $named = self::prefer($fields, FieldTypes::TEXT_BOX, ['writer', 'client', 'applicant', 'place', 'insurer', 'reported by']);
        if ($named !== null) {
            return $named;
        }
        foreach ($fields as $field) {
            if ($field['type'] !== FieldTypes::TEXT_BOX) {
                continue;
            }
            if (in_array($field['plain'], ['next step', 'reference'], true)) {
                continue;
            }

            return $field;
        }

        return null;
    }
}
