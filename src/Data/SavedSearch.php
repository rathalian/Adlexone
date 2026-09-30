<?php
declare(strict_types=1);

namespace Adlexone\Data;

use Adlexone\support\Database;

/**
 * Saved search criteria (JSON) compiled to SQL against the shared schema.
 *
 * Legacy rows may still carry saved_search_sql; execution prefers criteria_json
 * and falls back to rewriting legacy SQL for custom_field_* columns.
 */
final class SavedSearch
{
    /**
     * Build criteria from the advanced-search POST payload.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function criteriaFromPost(array $post): array
    {
        $criteria = [
            'version' => 1,
            'filters' => [],
            'display' => [],
        ];

        if (!empty($post['item_type_id'])) {
            $criteria['filters'][] = [
                'field' => 'item_type_id',
                'op' => '=',
                'value' => (string) $post['item_type_id'],
                'andor' => 'AND',
            ];
        }
        if (!empty($post['item_id'])) {
            $criteria['filters'][] = [
                'field' => 'item_id',
                'op' => (string) ($post['id_operator'] ?? '='),
                'value' => (string) $post['item_id'],
                'andor' => (string) ($post['id_andor'] ?? 'AND'),
            ];
        }
        if (!empty($post['hour_range'])) {
            $criteria['filters'][] = [
                'field' => (string) ($post['hour_type'] ?? 'core_log_updated'),
                'op' => html_entity_decode((string) ($post['hour_range_operator'] ?? '<='), ENT_COMPAT, 'UTF-8'),
                'value' => (string) (time() - (int) ((float) $post['hour_range'] * 3600)),
                'andor' => (string) ($post['hour_range_andor'] ?? 'AND'),
            ];
        }
        foreach (['1', '2'] as $n) {
            if (empty($post['date_' . $n])) {
                continue;
            }
            $parsed = date_parse((string) $post['date_' . $n]);
            $time = mktime(
                (int) ($parsed['hour'] ?? 0),
                (int) ($parsed['minute'] ?? 0),
                (int) ($parsed['second'] ?? 0),
                (int) ($parsed['month'] ?? 1),
                (int) ($parsed['day'] ?? 1),
                (int) ($parsed['year'] ?? 1970)
            );
            $criteria['filters'][] = [
                'field' => (string) ($post['date_type_' . $n] ?? 'create_date'),
                'op' => html_entity_decode((string) ($post['date_operator_' . $n] ?? '>='), ENT_COMPAT, 'UTF-8'),
                'value' => (string) $time,
                'andor' => (string) ($post['date_andor_' . $n] ?? 'AND'),
            ];
        }
        if (!empty($post['creator_security'])) {
            $criteria['filters'][] = [
                'field' => 'creator_security',
                'op' => html_entity_decode((string) ($post['security_creators_operator'] ?? '='), ENT_COMPAT, 'UTF-8'),
                'value' => (string) $post['creator_security'],
                'andor' => (string) ($post['security_creators_andor'] ?? 'AND'),
            ];
        }
        if (!empty($post['user_security'])) {
            $criteria['filters'][] = [
                'field' => 'user_security',
                'op' => html_entity_decode((string) ($post['security_users_operator'] ?? '='), ENT_COMPAT, 'UTF-8'),
                'value' => (string) $post['user_security'],
                'andor' => (string) ($post['security_users_andor'] ?? 'AND'),
            ];
        }
        if (!empty($post['security_groups'])) {
            $criteria['filters'][] = [
                'field' => 'group_id',
                'op' => html_entity_decode((string) ($post['security_groups_operator'] ?? '='), ENT_COMPAT, 'UTF-8'),
                'value' => (string) $post['security_groups'],
                'andor' => (string) ($post['security_groups_andor'] ?? 'AND'),
            ];
        }
        if (!empty($post['item_title'])) {
            $criteria['filters'][] = [
                'field' => 'item_title',
                'op' => (string) ($post['item_title_operator'] ?? 'LIKE'),
                'value' => (string) $post['item_title'],
                'andor' => (string) ($post['title_andor'] ?? 'AND'),
            ];
        }

        $count = (int) ($post['custom_field_count'] ?? 0);
        for ($i = 0; $i < $count; $i++) {
            $value = $post['custom_field_value_' . $i] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }
            $fieldId = (int) ($post['custom_field_' . $i] ?? 0);
            if ($fieldId <= 0) {
                continue;
            }
            $criteria['filters'][] = [
                'field' => 'custom_field_' . $fieldId,
                'op' => html_entity_decode((string) ($post['custom_field_operator_' . $i] ?? '='), ENT_COMPAT, 'UTF-8'),
                'value' => (string) $value,
                'andor' => (string) ($post['custom_field_andor_' . $i] ?? 'AND'),
            ];
        }

        foreach ($post as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'disp') && $value !== '') {
                $criteria['display'][] = (string) $value;
            }
        }

        return $criteria;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public static function compile(array $criteria, string $select = 'item_id, item_type_id, item_title'): string
    {
        $filters = $criteria['filters'] ?? [];
        if (!is_array($filters) || $filters === []) {
            return 'SELECT ' . $select . ' FROM items';
        }

        $sql = 'SELECT ' . $select . ' FROM items WHERE ';
        $parts = [];
        $first = true;
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $field = (string) ($filter['field'] ?? '');
            $op = (string) ($filter['op'] ?? '=');
            $value = (string) ($filter['value'] ?? '');
            $andor = strtoupper((string) ($filter['andor'] ?? 'AND'));
            if ($andor !== 'OR') {
                $andor = 'AND';
            }
            $clause = self::clause($field, $op, $value);
            if ($clause === '') {
                continue;
            }
            if ($first) {
                $parts[] = $clause;
                $first = false;
            } else {
                $parts[] = $andor . ' ' . $clause;
            }
        }
        if ($parts === []) {
            return 'SELECT ' . $select . ' FROM items';
        }
        return $sql . implode(' ', $parts);
    }

    private static function clause(string $field, string $op, string $value): string
    {
        if (preg_match('/^custom_field_(\d+)$/', $field, $m)) {
            return ItemFields::matchSql((int) $m[1], $op, $value);
        }
        if ($field === 'group_id') {
            $id = (int) $value;
            if ($op === '<>' || $op === '!=') {
                return "item_id NOT IN (SELECT item_id FROM item_groups WHERE group_id = {$id})";
            }
            return "item_id IN (SELECT item_id FROM item_groups WHERE group_id = {$id})";
        }

        $allowed = [
            'item_id', 'item_type_id', 'item_title', 'create_date', 'core_log_updated',
            'creator_security', 'user_security',
        ];
        if (!in_array($field, $allowed, true)) {
            return '';
        }
        $op = html_entity_decode($op, ENT_COMPAT, 'UTF-8');
        $escaped = Database::escape($value);
        return match (strtoupper($op)) {
            'LIKE' => "{$field} LIKE '%{$escaped}%'",
            '<>', '!=' => "{$field} <> '{$escaped}'",
            '>', '>=', '<', '<=' => "{$field} {$op} '{$escaped}'",
            default => "{$field} = '{$escaped}'",
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function resolveSql(array $row): string
    {
        $json = trim((string) ($row['criteria_json'] ?? ''));
        if ($json !== '' && $json !== '{}') {
            $criteria = json_decode($json, true);
            if (is_array($criteria)) {
                if (isset($criteria['legacy_sql']) && is_string($criteria['legacy_sql'])) {
                    return ItemFields::rewriteLegacySql(
                        str_replace('session_user', (string) ($_SESSION['access_user_id'] ?? ''), $criteria['legacy_sql'])
                    );
                }
                return self::compile($criteria);
            }
        }
        $legacy = (string) ($row['saved_search_sql'] ?? '');
        if ($legacy === '') {
            return 'SELECT item_id, item_type_id, item_title FROM items WHERE 0';
        }
        return ItemFields::rewriteLegacySql(
            str_replace('session_user', (string) ($_SESSION['access_user_id'] ?? ''), $legacy)
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public static function encode(array $criteria): string
    {
        return json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
