<?php
declare(strict_types=1);

namespace Adlexone\Data;

use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\FieldValidation;

/**
 * Custom field values for items (EAV).
 *
 * Form POST and legacy code still use custom_field_{id} keys; storage is
 * item_field_values so the items table schema stays stable.
 */
final class ItemFields
{
    /**
     * @return array<string, string> custom_field_{id} => value
     */
    public static function getMap(int $itemId): array
    {
        $map = [];
        foreach (Database::select('item_field_values', ['custom_field_id', 'value'], 'item_id = ?', [$itemId]) as $row) {
            $map['custom_field_' . (int) $row['custom_field_id']] = (string) ($row['value'] ?? '');
        }
        return $map;
    }

    /**
     * Merge EAV values onto an items row (or leave as-is when already present).
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public static function hydrate(array $item): array
    {
        $itemId = (int) ($item['item_id'] ?? 0);
        if ($itemId <= 0) {
            return $item;
        }
        foreach (self::getMap($itemId) as $column => $value) {
            if (!array_key_exists($column, $item) || $item[$column] === null || $item[$column] === '') {
                $item[$column] = $value;
            }
        }
        return $item;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function hydrateMany(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['item_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return $rows;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $valueRows = Database::rows(
            'SELECT item_id, custom_field_id, value FROM item_field_values WHERE item_id IN (' . $placeholders . ')',
            array_values($ids)
        );
        $byItem = [];
        foreach ($valueRows as $row) {
            $byItem[(int) $row['item_id']]['custom_field_' . (int) $row['custom_field_id']] = (string) ($row['value'] ?? '');
        }

        foreach ($rows as $i => $row) {
            $id = (int) ($row['item_id'] ?? 0);
            if ($id > 0 && isset($byItem[$id])) {
                $rows[$i] = array_merge($byItem[$id], $row);
                // Prefer EAV over missing/null keys already merged above; re-apply EAV for empty.
                foreach ($byItem[$id] as $col => $val) {
                    if (!array_key_exists($col, $rows[$i]) || $rows[$i][$col] === null || $rows[$i][$col] === '') {
                        $rows[$i][$col] = $val;
                    }
                }
            }
        }
        return $rows;
    }

    /**
     * Persist custom_field_* keys from a form/row array. Other keys ignored.
     *
     * @param array<string, mixed> $data
     */
    public static function saveFromArray(int $itemId, array $data): void
    {
        if ($itemId <= 0) {
            return;
        }
        foreach ($data as $key => $value) {
            if (!is_string($key) || !preg_match('/^custom_field_(\d+)$/', $key, $m)) {
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            $fieldId = (int) $m[1];
            $text = self::storedText($fieldId, $value === null ? '' : (string) $value);
            Database::run(
                'INSERT INTO item_field_values (item_id, custom_field_id, value) VALUES (?, ?, ?)
                 ON CONFLICT(item_id, custom_field_id) DO UPDATE SET value = excluded.value',
                [$itemId, $fieldId, $text]
            );
        }
    }

    /**
     * A date is stored in the site format, so 6 October is 06-10-2026 and not a month-first string.
     */
    private static function storedText(int $fieldId, string $text): string
    {
        $text = trim($text);
        if ($text === '' || $fieldId <= 0 || !Database::tableExists('custom_fields')) {
            return $text;
        }
        $field = Database::first('custom_fields', ['field_type', 'validation_type'], 'custom_field_id = ?', [$fieldId]);
        if ($field === null) {
            return $text;
        }
        $kind = FieldValidation::kind([
            'field_type' => FieldTypes::normalise((string) ($field['field_type'] ?? '')),
            'validation_type' => (string) ($field['validation_type'] ?? ''),
        ]);
        if ($kind !== 'date' || FieldValidation::parseDate($text, false) === null) {
            return $text;
        }

        return FieldValidation::formatDate($text);
    }

    /**
     * Strip custom_field_* keys so they are not written onto items.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function withoutFieldColumns(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if (!is_string($key)) {
                continue;
            }
            if (preg_match('/^custom_field_\d+$/', $key) === 1 || str_starts_with($key, 'group_')) {
                unset($data[$key]);
            }
        }
        unset($data['group_security'], $data['groups_posted'], $data['_csrf']);

        return $data;
    }

    public static function deleteForItem(int $itemId): void
    {
        Database::delete('item_field_values', 'item_id = ?', [$itemId]);
    }

    public static function deleteField(int $fieldId): void
    {
        Database::delete('item_field_values', 'custom_field_id = ?', [$fieldId]);
    }

    public static function copyItem(int $fromItemId, int $toItemId): void
    {
        foreach (Database::select('item_field_values', ['custom_field_id', 'value'], 'item_id = ?', [$fromItemId]) as $row) {
            Database::run(
                'INSERT INTO item_field_values (item_id, custom_field_id, value) VALUES (?, ?, ?)
                 ON CONFLICT(item_id, custom_field_id) DO UPDATE SET value = excluded.value',
                [$toItemId, (int) $row['custom_field_id'], (string) ($row['value'] ?? '')]
            );
        }
    }

    /**
     * SQL fragment matching items by one custom field predicate.
     * The value is a placeholder. The column and the comparison are fixed text.
     */
    public static function matchSql(int $fieldId, string $operator, string $value): BoundQuery
    {
        $fieldId = (int) $fieldId;
        $op = strtoupper(trim($operator));
        $from = "item_id IN (SELECT item_id FROM item_field_values WHERE custom_field_id = {$fieldId} AND ";

        if (in_array($op, ['DATE=', 'DATE>=', 'DATE<='], true)) {
            $day = FieldValidation::parseDate($value);
            if ($day === null) {
                return new BoundQuery('0');
            }
            $compare = $op === 'DATE=' ? '=' : substr($op, 4);

            return new BoundQuery($from . self::storedDay() . " {$compare} ?)", [$day->format('Y-m-d')]);
        }
        if ($op === 'TICKED') {
            return new BoundQuery($from . "TRIM(value) NOT IN ('', '0'))");
        }
        if ($op === 'UNTICKED') {
            return new BoundQuery("item_id NOT IN (SELECT item_id FROM item_field_values WHERE custom_field_id = {$fieldId} AND TRIM(value) NOT IN ('', '0'))");
        }
        if (in_array($op, ['>', '>=', '<', '<='], true) && is_numeric($value)) {
            return new BoundQuery($from . "TRIM(value) <> '' AND CAST(value AS REAL) {$op} ?)", [(float) $value]);
        }

        if ($op === 'CONTAINS' || $op === 'LIKE') {
            return new BoundQuery(
                $from . "value LIKE ? ESCAPE '\\')",
                ['%' . Database::escapeLike($value) . '%']
            );
        }
        if ($op === 'NOT') {
            return new BoundQuery(
                "item_id NOT IN (SELECT item_id FROM item_field_values WHERE custom_field_id = {$fieldId} AND value = ?)",
                [$value]
            );
        }

        return match ($op) {
            '<>', '!=' => new BoundQuery($from . 'value <> ?)', [$value]),
            '>', '>=', '<', '<=' => new BoundQuery($from . "value {$op} ?", [$value]),
            default => new BoundQuery($from . 'value = ?)', [$value]),
        };
    }

    /**
     * A stored date as Y-m-d, whichever of the two stored forms it is in.
     */
    private static function storedDay(): string
    {
        $v = "REPLACE(TRIM(value), '/', '-')";

        return "(CASE WHEN {$v} GLOB '[0-9][0-9]-[0-9][0-9]-[0-9][0-9][0-9][0-9]*'"
            . " THEN substr({$v}, 7, 4) || '-' || substr({$v}, 4, 2) || '-' || substr({$v}, 1, 2)"
            . " WHEN {$v} GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]*' THEN substr({$v}, 1, 10)"
            . ' ELSE NULL END)';
    }

    public static function fieldIdFromKey(string $key): ?int
    {
        if (preg_match('/^custom_field_(\d+)$/', $key, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
