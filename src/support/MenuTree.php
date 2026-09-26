<?php
declare(strict_types=1);

namespace Adlexone\support;

/**
 * Menu values for a custom field of type menu.
 *
 * Top-level values (parent 0) are a single dropdown. Values with children
 * are the next level of the same menu.
 */
final class MenuTree
{
    private static bool $ready = false;

    public static function ensure(): void
    {
        if (self::$ready || !defined('DSN')) {
            return;
        }
        self::$ready = true;

        try {
            $tables = Database::buildArray(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'custom_field_menu_values'"
            );
        } catch (\Throwable $e) {
            return;
        }
        if ($tables === []) {
            return;
        }

        $column = Database::buildArray(
            "SELECT name FROM pragma_table_info('custom_field_menu_values') WHERE name = 'parent_menu_value_id'"
        );
        if ($column === []) {
            Database::query(
                'ALTER TABLE custom_field_menu_values ADD COLUMN parent_menu_value_id INTEGER NOT NULL DEFAULT 0',
                DSN
            );
        }

        self::migrateLegacyMenus();
    }

    /**
     * @return array<int, array{menu_value_id:int, custom_field_id:int, menu_value:string, parent_menu_value_id:int}>
     */
    public static function nodes(string $fieldId): array
    {
        self::ensure();
        if ($fieldId === '' || !ctype_digit($fieldId)) {
            return [];
        }
        $rows = Database::buildArray(
            "SELECT menu_value_id, custom_field_id, menu_value, parent_menu_value_id
             FROM custom_field_menu_values
             WHERE custom_field_id = '" . $fieldId . "'
             ORDER BY menu_value_id ASC"
        );
        $nodes = [];
        $ids = [];
        foreach ($rows as $row) {
            $id = (int)$row['menu_value_id'];
            $ids[$id] = true;
            $nodes[] = [
                'menu_value_id' => $id,
                'custom_field_id' => (int)$row['custom_field_id'],
                'menu_value' => (string)$row['menu_value'],
                'parent_menu_value_id' => (int)$row['parent_menu_value_id'],
            ];
        }
        foreach ($nodes as &$node) {
            if ($node['parent_menu_value_id'] !== 0 && !isset($ids[$node['parent_menu_value_id']])) {
                $node['parent_menu_value_id'] = 0;
            }
        }
        unset($node);
        return $nodes;
    }

    /**
     * @param array<int, array{menu_value_id:int, custom_field_id:int, menu_value:string, parent_menu_value_id:int}> $nodes
     * @return array<int, array{menu_value_id:int, custom_field_id:int, menu_value:string, parent_menu_value_id:int, depth:int}>
     */
    public static function ordered(array $nodes): array
    {
        $byParent = [];
        foreach ($nodes as $node) {
            $byParent[$node['parent_menu_value_id']][] = $node;
        }
        $out = [];
        $walk = static function (int $parent, int $depth) use (&$walk, &$out, $byParent): void {
            foreach ($byParent[$parent] ?? [] as $node) {
                $node['depth'] = $depth;
                $out[] = $node;
                $walk((int)$node['menu_value_id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $out;
    }

    public static function hasChildren(string $fieldId): bool
    {
        foreach (self::nodes($fieldId) as $node) {
            if ($node['parent_menu_value_id'] !== 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ids that cannot be the parent of $valueId (itself and its descendants).
     *
     * @return array<string, true>
     */
    public static function blockedParentIds(string $fieldId, string $valueId): array
    {
        $blocked = [$valueId => true];
        $byParent = [];
        foreach (self::nodes($fieldId) as $node) {
            $byParent[(string)$node['parent_menu_value_id']][] = (string)$node['menu_value_id'];
        }
        $stack = [$valueId];
        while ($stack !== []) {
            $id = array_pop($stack);
            foreach ($byParent[$id] ?? [] as $child) {
                if (!isset($blocked[$child])) {
                    $blocked[$child] = true;
                    $stack[] = $child;
                }
            }
        }
        return $blocked;
    }

    public static function parentIsAllowed(string $fieldId, string $valueId, string $parentId): bool
    {
        if ($parentId === '0' || $parentId === '') {
            return true;
        }
        if (!ctype_digit($parentId)) {
            return false;
        }
        $blocked = $valueId !== '' ? self::blockedParentIds($fieldId, $valueId) : [];
        if (isset($blocked[$parentId])) {
            return false;
        }
        foreach (self::nodes($fieldId) as $node) {
            if ((string)$node['menu_value_id'] === $parentId) {
                return true;
            }
        }
        return false;
    }

    public static function deleteValue(string $menuValueId): void
    {
        if ($menuValueId === '' || !ctype_digit($menuValueId)) {
            return;
        }
        $children = Database::buildArray(
            "SELECT menu_value_id FROM custom_field_menu_values WHERE parent_menu_value_id = '" . $menuValueId . "'"
        );
        foreach ($children as $child) {
            self::deleteValue((string)$child['menu_value_id']);
        }
        Database::query(
            Database::sqlDelete('custom_field_menu_values', "WHERE menu_value_id = '" . $menuValueId . "'"),
            DSN
        );
    }

    /**
     * Write the deepest selected level into custom_field_{id} and drop the level inputs.
     */
    public static function collapseLevelInputs(): void
    {
        $fieldIds = [];
        foreach (array_keys($_POST) as $key) {
            if (preg_match('/^menu_level_(\d+)_\d+$/', (string)$key, $match) === 1) {
                $fieldIds[$match[1]] = true;
            }
        }
        foreach (array_keys($fieldIds) as $fieldId) {
            $path = self::pathFromPost((string)$fieldId, self::nodes((string)$fieldId));
            $leaf = ($path === null || $path === []) ? '' : (string)$path[array_key_last($path)]['menu_value'];
            $_POST['custom_field_' . $fieldId] = $leaf;
        }
        foreach (array_keys($_POST) as $key) {
            if (str_starts_with((string)$key, 'menu_level_')) {
                unset($_POST[$key]);
            }
        }
    }

    /**
     * One dropdown, or a stack of dropdowns when the menu has children.
     */
    public static function render(string $fieldId, string $stored): string
    {
        $nodes = self::nodes($fieldId);
        if (!self::hasChildren($fieldId)) {
            $labels = [];
            foreach ($nodes as $node) {
                if ($node['parent_menu_value_id'] === 0) {
                    $labels[] = $node['menu_value'];
                }
            }
            return self::select('custom_field_' . $fieldId, $labels, $stored, '');
        }

        $posted = self::pathFromPost($fieldId, $nodes);
        $path = $posted ?? self::pathFromStored($nodes, $stored);
        $byParent = [];
        foreach ($nodes as $node) {
            $byParent[$node['parent_menu_value_id']][] = $node;
        }

        $html = '<div class="menu-levels">';
        $parentId = 0;
        for ($level = 0; $level < 12; $level++) {
            $children = $byParent[$parentId] ?? [];
            if ($children === []) {
                break;
            }
            $labels = [];
            foreach ($children as $child) {
                $labels[] = $child['menu_value'];
            }
            $selected = isset($path[$level]) ? (string)$path[$level]['menu_value'] : '';
            $html .= self::select(
                'menu_level_' . $fieldId . '_' . $level,
                $labels,
                $selected,
                defined('TXT_284') ? TXT_284 : '',
                ' onchange="this.form.submit()"'
            );
            if ($selected === '') {
                break;
            }
            $next = null;
            foreach ($children as $child) {
                if ($child['menu_value'] === $selected) {
                    $next = $child;
                    break;
                }
            }
            if ($next === null) {
                break;
            }
            $parentId = (int)$next['menu_value_id'];
        }
        return $html . '</div>';
    }

    /**
     * @param array<int, string> $labels
     */
    private static function select(string $name, array $labels, string $selected, string $blank, string $extra = ''): string
    {
        $html = '<select name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" class="select"' . $extra . '>';
        if ($labels === []) {
            $empty = defined('TXT_366') ? TXT_366 : '';
            $html .= '<option value="">' . htmlspecialchars($empty, ENT_QUOTES, 'UTF-8') . '</option>';
        } else {
            if ($blank !== '') {
                $html .= '<option value="">' . htmlspecialchars($blank, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            foreach ($labels as $label) {
                $safe = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
                $isSelected = $label === $selected ? ' selected' : '';
                $html .= '<option value="' . $safe . '"' . $isSelected . '>' . $safe . '</option>';
            }
        }
        return $html . '</select>';
    }

    /**
     * @param array<int, array{menu_value_id:int, menu_value:string, parent_menu_value_id:int}> $nodes
     * @return array<int, array{menu_value_id:int, menu_value:string, parent_menu_value_id:int}>|null
     */
    private static function pathFromPost(string $fieldId, array $nodes): ?array
    {
        if (!array_key_exists('menu_level_' . $fieldId . '_0', $_POST)) {
            return null;
        }
        $byParent = [];
        foreach ($nodes as $node) {
            $byParent[$node['parent_menu_value_id']][] = $node;
        }
        $path = [];
        $parent = 0;
        for ($level = 0; $level < 12; $level++) {
            $key = 'menu_level_' . $fieldId . '_' . $level;
            if (!array_key_exists($key, $_POST)) {
                break;
            }
            $label = (string)$_POST[$key];
            if ($label === '') {
                break;
            }
            $match = null;
            foreach ($byParent[$parent] ?? [] as $node) {
                if ($node['menu_value'] === $label) {
                    $match = $node;
                    break;
                }
            }
            if ($match === null) {
                break;
            }
            $path[] = $match;
            $parent = (int)$match['menu_value_id'];
        }
        return $path;
    }

    /**
     * @param array<int, array{menu_value_id:int, menu_value:string, parent_menu_value_id:int}> $nodes
     * @return array<int, array{menu_value_id:int, menu_value:string, parent_menu_value_id:int}>
     */
    private static function pathFromStored(array $nodes, string $stored): array
    {
        if ($stored === '') {
            return [];
        }
        $byId = [];
        $matches = [];
        foreach ($nodes as $node) {
            $byId[$node['menu_value_id']] = $node;
            if ($node['menu_value'] === $stored) {
                $matches[] = $node;
            }
        }
        if ($matches === []) {
            return [];
        }
        usort($matches, static function (array $a, array $b) use ($byId): int {
            return self::depthOf($b, $byId) <=> self::depthOf($a, $byId)
                ?: $a['menu_value_id'] <=> $b['menu_value_id'];
        });
        $path = [];
        $node = $matches[0];
        $guard = 0;
        while ($node !== null && $guard < 12) {
            array_unshift($path, $node);
            $parent = (int)$node['parent_menu_value_id'];
            $node = $parent > 0 ? ($byId[$parent] ?? null) : null;
            $guard++;
        }
        return $path;
    }

    /**
     * @param array{menu_value_id:int, parent_menu_value_id:int} $node
     * @param array<int, array{parent_menu_value_id:int}> $byId
     */
    private static function depthOf(array $node, array $byId): int
    {
        $depth = 0;
        $parent = (int)$node['parent_menu_value_id'];
        $guard = 0;
        while ($parent > 0 && isset($byId[$parent]) && $guard < 12) {
            $depth++;
            $parent = (int)$byId[$parent]['parent_menu_value_id'];
            $guard++;
        }
        return $depth;
    }

    private static function migrateLegacyMenus(): void
    {
        $legacy = Database::buildArray(
            "SELECT custom_field_id, field_type FROM custom_fields WHERE field_type IN ('subMenu', 'subMenuChild', 'multiLevelMenu')"
        );
        $pendingLists = Database::buildArray(
            "SELECT menu_value_id FROM custom_field_menu_values WHERE sub_menu_values IS NOT NULL AND sub_menu_values != '' LIMIT 1"
        );
        if ($legacy === [] && $pendingLists === []) {
            return;
        }

        foreach ($legacy as $field) {
            if ((string)$field['field_type'] !== 'multiLevelMenu') {
                continue;
            }
            $column = 'custom_field_' . (int)$field['custom_field_id'];
            $existing = Database::buildArray(
                "SELECT name FROM pragma_table_info('items') WHERE name = '" . $column . "'"
            );
            if ($existing === []) {
                Database::query('ALTER TABLE items ADD ' . $column . ' text', DSN);
            }
        }

        $parents = Database::buildArray(
            "SELECT menu_value_id, custom_field_id, sub_menu_values
             FROM custom_field_menu_values
             WHERE sub_menu_values IS NOT NULL AND sub_menu_values != ''"
        );
        foreach ($parents as $parent) {
            $parentId = (int)$parent['menu_value_id'];
            $fieldId = (int)$parent['custom_field_id'];
            foreach (explode(',', (string)$parent['sub_menu_values']) as $label) {
                $label = trim($label);
                if ($label === '') {
                    continue;
                }
                $exists = Database::buildArray(
                    "SELECT menu_value_id FROM custom_field_menu_values
                     WHERE custom_field_id = '" . $fieldId . "'
                     AND parent_menu_value_id = " . $parentId . "
                     AND menu_value = '" . Database::escape($label) . "'"
                );
                if ($exists !== []) {
                    continue;
                }
                Database::query(Database::sqlInsert('custom_field_menu_values', [
                    'menu_value_id' => Database::newID('custom_field_menu_values', 'menu_value_id'),
                    'custom_field_id' => $fieldId,
                    'menu_value' => $label,
                    'sub_menu_values' => '',
                    'parent_menu_value_id' => $parentId,
                ]), DSN);
            }
            Database::query(Database::sqlUpdate(
                'custom_field_menu_values',
                ['sub_menu_values' => ''],
                "WHERE menu_value_id = '" . $parentId . "'"
            ), DSN);
        }

        Database::query(
            "UPDATE custom_fields SET enabled = 'No' WHERE field_type = 'subMenuChild'",
            DSN
        );
        Database::query(
            "UPDATE custom_fields SET field_type = 'menu', sub_menu = 0 WHERE field_type IN ('subMenu', 'subMenuChild', 'multiLevelMenu')",
            DSN
        );
    }
}
