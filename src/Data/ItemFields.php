<?php
declare(strict_types=1);

namespace Adlexone\Data;

use Adlexone\support\Database;

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
            $text = $value === null ? '' : (string) $value;
            Database::run(
                'INSERT INTO item_field_values (item_id, custom_field_id, value) VALUES (?, ?, ?)
                 ON CONFLICT(item_id, custom_field_id) DO UPDATE SET value = excluded.value',
                [$itemId, $fieldId, $text]
            );
        }
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
            if (is_string($key) && preg_match('/^custom_field_\d+$/', $key)) {
                unset($data[$key]);
            }
        }
        unset($data['group_security']);
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
     */
    public static function matchSql(int $fieldId, string $operator, string $value): string
    {
        $fieldId = (int) $fieldId;
        $op = strtoupper(trim($operator));
        $escaped = Database::escape($value);
        return match ($op) {
            'LIKE' => "item_id IN (SELECT item_id FROM item_field_values WHERE custom_field_id = {$fieldId} AND value LIKE '%{$escaped}%')",
            '<>', '!=' => "item_id IN (SELECT item_id FROM item_field_values WHERE custom_field_id = {$fieldId} AND value <> '{$escaped}')",
            '>' => "item_id IN (SELECT item_id FROM item_field_values WHERE custom_field_id = {$fieldId} AND value > '{$escaped}')",
            '<' => "item_id IN (SELECT item_id FROM item_field_values WHERE custom_field_id = {$fieldId} AND value < '{$escaped}')",
            default => "item_id IN (SELECT item_id FROM item_field_values WHERE custom_field_id = {$fieldId} AND value = '{$escaped}')",
        };
    }

    /**
     * Rewrite legacy SQL that still references items.custom_field_N.
     */
    public static function rewriteLegacySql(string $sql): string
    {
        $sql = preg_replace_callback(
            '/\b(?:items\.)?custom_field_(\d+)\s*(=|<>|!=|LIKE|>|<)\s*\'([^\']*)\'/i',
            static function (array $m): string {
                return self::matchSql((int) $m[1], $m[2], $m[3]);
            },
            $sql
        ) ?? $sql;

        // SELECT list: drop custom_field_N projections (callers that need values hydrate later).
        $sql = preg_replace('/,\s*(?:items\.)?custom_field_\d+\b/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\b(?:items\.)?custom_field_\d+\s*,\s*/i', '', $sql) ?? $sql;

        return $sql;
    }

    public static function fieldIdFromKey(string $key): ?int
    {
        if (preg_match('/^custom_field_(\d+)$/', $key, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
