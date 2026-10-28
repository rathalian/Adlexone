<?php
declare(strict_types=1);

namespace Adlexone\Data;

use Adlexone\support\Database;

/**
 * Saved search criteria (JSON) compiled to SQL against the shared schema.
 *
 * A saved search runs from its filters. A stored statement is not read and not written.
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
    public static function compile(array $criteria, string $select = 'item_id, item_type_id, item_title'): BoundQuery
    {
        $filters = $criteria['filters'] ?? [];
        if (!is_array($filters) || $filters === []) {
            return new BoundQuery('SELECT ' . $select . ' FROM items');
        }

        $sql = 'SELECT ' . $select . ' FROM items WHERE ';
        $parts = [];
        $params = [];
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
            if ($clause->sql === '') {
                continue;
            }
            $parts[] = $first ? $clause->sql : $andor . ' ' . $clause->sql;
            $first = false;
            foreach ($clause->params as $param) {
                $params[] = $param;
            }
        }
        if ($parts === []) {
            return new BoundQuery('SELECT ' . $select . ' FROM items');
        }

        return new BoundQuery($sql . implode(' ', $parts), $params);
    }

    private static function clause(string $field, string $op, string $value): BoundQuery
    {
        if (preg_match('/^custom_field_(\d+)$/', $field, $m)) {
            return ItemFields::matchSql((int) $m[1], $op, $value);
        }
        if ($field === 'q') {
            return self::textClause($value);
        }
        if ($field === 'create_date_days' || $field === 'core_log_updated_days') {
            $days = (int) $value;
            if ($days <= 0) {
                return new BoundQuery('');
            }
            $column = $field === 'create_date_days' ? 'create_date' : 'COALESCE(core_log_updated, create_date)';

            return new BoundQuery($column . ' >= ' . (time() - $days * 86400));
        }
        if ($field === 'group_id') {
            $id = (int) $value;
            if ($op === '<>' || $op === '!=') {
                return new BoundQuery("item_id NOT IN (SELECT item_id FROM item_groups WHERE group_id = {$id})");
            }

            return new BoundQuery("item_id IN (SELECT item_id FROM item_groups WHERE group_id = {$id})");
        }

        $allowed = [
            'item_id', 'item_type_id', 'item_title', 'create_date', 'core_log_updated',
            'creator_security', 'user_security',
        ];
        if (!in_array($field, $allowed, true)) {
            return new BoundQuery('');
        }
        $op = strtoupper(trim(html_entity_decode($op, ENT_COMPAT, 'UTF-8')));
        if (!in_array($op, ['=', '<>', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'], true)) {
            $op = '=';
        }
        if ($value === 'session_user' && in_array($field, ['creator_security', 'user_security'], true)) {
            $value = (string) (int) ($_SESSION['access_user_id'] ?? 0);
        }
        if ($op === 'LIKE' || $op === 'NOT LIKE') {
            return new BoundQuery(
                "{$field} {$op} ? ESCAPE '\\'",
                ['%' . Database::escapeLike($value) . '%']
            );
        }

        return new BoundQuery("{$field} {$op} ?", [$value]);
    }

    /**
     * @return list<string> the words and "quoted phrases" of a search box
     */
    public static function terms(string $text): array
    {
        preg_match_all('/"([^"]+)"|(\S+)/u', $text, $found, PREG_SET_ORDER);
        $terms = [];
        foreach ($found as $match) {
            $term = trim(($match[2] ?? '') !== '' ? $match[2] : $match[1]);
            if ($term !== '') {
                $terms[] = $term;
            }
        }

        return array_slice($terms, 0, 8);
    }

    /**
     * Every term must appear in the name, the number, a field value, or a note.
     */
    private static function textClause(string $text): BoundQuery
    {
        $clauses = [];
        $params = [];
        foreach (self::terms($text) as $term) {
            $like = '%' . Database::escapeLike($term) . '%';
            $number = ctype_digit($term) ? ' OR item_id = ' . (int) $term : '';
            $clauses[] = "(item_title LIKE ? ESCAPE '\\'{$number}"
                . " OR item_id IN (SELECT item_id FROM item_field_values WHERE value LIKE ? ESCAPE '\\'"
                . " AND custom_field_id NOT IN (SELECT custom_field_id FROM custom_fields WHERE field_type IN ('password', 'hidden')))"
                . " OR item_id IN (SELECT item_id FROM core_log WHERE log_text LIKE ? ESCAPE '\\'))";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($clauses === []) {
            return new BoundQuery('');
        }

        return new BoundQuery('(' . implode(' AND ', $clauses) . ')', $params);
    }

    /**
     * Filters to keep. A stored statement is dropped. A search that was only a statement matches nothing.
     *
     * @param array<string, mixed> $criteria
     * @return array<string, mixed>
     */
    public static function withoutStoredSql(array $criteria, string $storedSql = ''): array
    {
        $hadSql = trim($storedSql) !== ''
            || (isset($criteria['legacy_sql']) && is_string($criteria['legacy_sql']) && trim($criteria['legacy_sql']) !== '');
        unset($criteria['legacy_sql']);
        $filters = $criteria['filters'] ?? [];
        if (!is_array($filters)) {
            $filters = [];
        }
        if ($hadSql && $filters === []) {
            return [
                'version' => 1,
                'filters' => [
                    ['field' => 'item_id', 'op' => '=', 'value' => '0', 'andor' => 'AND'],
                ],
            ];
        }
        $criteria['filters'] = $filters;
        if (!isset($criteria['version'])) {
            $criteria['version'] = 1;
        }

        return $criteria;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function resolve(array $row): BoundQuery
    {
        $decoded = json_decode(trim((string) ($row['criteria_json'] ?? '')), true);
        $criteria = is_array($decoded) ? $decoded : [];

        return self::compile(self::withoutStoredSql($criteria, (string) ($row['saved_search_sql'] ?? '')));
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public static function encode(array $criteria): string
    {
        return json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
