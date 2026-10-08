<?php
declare(strict_types=1);

namespace Adlexone\support;

/**
 * One menu model for custom fields.
 *
 * Options are a tree (parent_menu_value_id = 0 is the top level). A flat menu is a tree
 * with no children. Nested choices are further levels of the same menu. Older submenu,
 * submenu-child, and multi-level menu records are folded into this tree on first use.
 */
final class MenuOptions
{
    private static bool $ready = false;

    /** @var array<int, int> parent field id => legacy child field id */
    private static array $legacyChild = [];

    /** @var array<int, int> child field id => parent field id */
    private static array $childOf = [];

    /** @var array<int, string> */
    private static array $names = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    private static array $nodeCache = [];

    public static function prepare(): void
    {
        if (self::$ready) {
            return;
        }
        self::$ready = true;
        if (!self::tableExists('custom_fields') || !self::tableExists('custom_field_menu_values')) {
            return;
        }
        self::ensureParentColumn();
        self::importCommaChildren();
        self::importMultiLevelMenus();
        Database::run("UPDATE custom_fields SET field_type = 'menu' WHERE field_type IN ('subMenu', 'subMenuChild', 'multiLevelMenu')");
        self::loadLinks();
    }

    public static function isLegacyChild(int $fieldId): bool
    {
        self::prepare();
        return isset(self::$childOf[$fieldId]);
    }

    /**
     * Hide the old child field on an item form when its parent menu is on the same item type.
     * The parent menu draws the nested level and writes this field's column.
     */
    public static function coveredByParentOnItemType(int $childFieldId, int $itemTypeId): bool
    {
        self::prepare();
        $parentId = self::$childOf[$childFieldId] ?? 0;
        if ($parentId <= 0 || $itemTypeId <= 0) {
            return false;
        }
        $row = Database::row(
            "SELECT custom_field_id FROM item_type_custom_fields WHERE item_type_id = " . $itemTypeId
            . " AND custom_field_id = " . $parentId . " LIMIT 1"
        );
        return $row !== null;
    }

    public static function legacyChildId(int $fieldId): int
    {
        self::prepare();
        return self::$legacyChild[$fieldId] ?? 0;
    }

    /**
     * Cascading selects for one menu field.
     * The first select is named custom_field_{id}. Further levels use the legacy child
     * column when one exists, otherwise menu_level_{id}_{n}, which collapseRequest() folds
     * back into the menu column on save.
     *
     * @param array<string, mixed> $request Posted form values when the form was refreshed
     */
    public static function controls(int $fieldId, string $stored, string $childStored, array $request): string
    {
        self::prepare();
        $byParent = self::byParent($fieldId);
        $childId = self::legacyChildId($fieldId);
        $path = array_key_exists('custom_field_' . $fieldId, $request)
            ? self::pathFromRequest($fieldId, $childId, $byParent, $request)
            : self::pathFromStored($fieldId, $stored, $childStored);

        $html = '<div class="menu-cascade">';
        $parentId = 0;
        $level = 0;
        while ($level < 12) {
            $options = $byParent[$parentId] ?? [];
            if ($options === [] && $level > 0) {
                break;
            }
            $selected = (string)($path[$level]['menu_value'] ?? '');
            $nested = false;
            foreach ($options as $option) {
                if (!empty($byParent[(int)$option['menu_value_id']])) {
                    $nested = true;
                    break;
                }
            }
            if ($level === 1 && $childId > 0 && isset(self::$names[$childId])) {
                $html .= '<div class="label" style="margin-top:0.75rem">'
                    . htmlspecialchars(self::$names[$childId], ENT_QUOTES, 'UTF-8') . '</div>';
            } elseif ($level > 0) {
                $html .= '<div style="height:0.5rem"></div>';
            }
            $values = [];
            $labels = [];
            if ($nested || $level > 0) {
                $values[] = '';
                $labels[] = TXT_284;
            }
            foreach ($options as $option) {
                $values[] = (string)$option['menu_value'];
                $labels[] = (string)$option['menu_value'];
            }
            $extra = $nested ? 'onchange="this.form.submit()"' : '';
            $html .= RenderViews::buildSelectDropdown(self::inputName($fieldId, $childId, $level), $values, $labels, $selected, $extra);

            $chosen = null;
            foreach ($options as $option) {
                if ((string)$option['menu_value'] === $selected) {
                    $chosen = $option;
                    break;
                }
            }
            if ($chosen === null || empty($byParent[(int)$chosen['menu_value_id']])) {
                break;
            }
            $parentId = (int)$chosen['menu_value_id'];
            $level++;
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Fold extra cascade inputs into real item columns and drop the temporary names.
     *
     * @param array<string, mixed> $request
     */
    public static function collapseRequest(array &$request): void
    {
        self::prepare();
        $extra = [];
        foreach (array_keys($request) as $key) {
            if (preg_match('/^menu_level_(\d+)_(\d+)$/', (string)$key, $match) === 1) {
                $extra[(int)$match[1]][(int)$match[2]] = (string)$request[$key];
                unset($request[$key]);
            }
        }

        foreach (self::$legacyChild as $parentId => $childId) {
            $key = 'custom_field_' . $parentId;
            if (!array_key_exists($key, $request)) {
                continue;
            }
            $root = self::findChild(self::byParent($parentId), 0, (string)$request[$key]);
            $hasChildren = $root !== null && !empty(self::byParent($parentId)[(int)$root['menu_value_id']]);
            if (!$hasChildren) {
                $request['custom_field_' . $childId] = '';
            }
            if (!empty($extra[$parentId])) {
                ksort($extra[$parentId]);
                $specific = (string)($request['custom_field_' . $childId] ?? '');
                foreach ($extra[$parentId] as $value) {
                    if ($value !== '') {
                        $specific = $value;
                    }
                }
                $request['custom_field_' . $childId] = $specific;
                unset($extra[$parentId]);
            }
        }

        foreach ($extra as $fieldId => $levels) {
            if (!array_key_exists('custom_field_' . $fieldId, $request)) {
                continue;
            }
            ksort($levels);
            $chosen = (string)$request['custom_field_' . $fieldId];
            foreach ($levels as $value) {
                if ($value !== '') {
                    $chosen = $value;
                }
            }
            $request['custom_field_' . $fieldId] = $chosen;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function flattened(int $fieldId): array
    {
        self::prepare();
        $out = [];
        self::walk(self::byParent($fieldId), 0, 0, '', $out);
        return $out;
    }

    public static function editorRows(int $fieldId): string
    {
        $rows = '';
        foreach (self::flattened($fieldId) as $node) {
            $id = (int)$node['menu_value_id'];
            $rows .= '<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(8rem,14rem) auto;gap:0.75rem;align-items:center;margin:0 0 0.5rem '
                . ((int)$node['depth'] * 1.25) . 'rem">'
                . RenderViews::buildTextInput((string)$id, (string)$node['menu_value'])
                . RenderViews::buildHiddenInput('menu_value_old_id_' . $id, (string)$node['menu_value'])
                . self::parentSelect('parent_' . $id, $fieldId, (int)$node['parent_menu_value_id'], $id)
                . RenderViews::buildURL(
                    ITEM_BASE_URL . '&option=delete_menu_value&menu_value_id=' . $id . '&custom_field_id=' . $fieldId,
                    TXT_47,
                    '',
                    'URL',
                    'onClick="javascript:return confirm(\'' . TXT_400 . '\')"'
                )
                . '</div>';
        }
        if ($rows === '') {
            $rows = htmlspecialchars(TXT_366, ENT_QUOTES, 'UTF-8');
        }
        return $rows;
    }

    public static function parentSelect(string $name, int $fieldId, int $selected, int $excludeId = 0): string
    {
        $values = ['0'];
        $labels = [TXT_694];
        foreach (self::flattened($fieldId) as $node) {
            $id = (int)$node['menu_value_id'];
            if ($id === $excludeId || self::isUnder($fieldId, $id, $excludeId)) {
                continue;
            }
            $values[] = (string)$id;
            $labels[] = (string)$node['path'];
        }
        return RenderViews::buildSelectDropdown($name, $values, $labels, (string)$selected);
    }

    public static function valueExists(int $fieldId, string $value, int $parentId): bool
    {
        self::prepare();
        $row = Database::row(
            "SELECT menu_value_id FROM custom_field_menu_values WHERE custom_field_id = " . $fieldId
            . " AND parent_menu_value_id = " . $parentId
            . " AND menu_value = '" . Database::escape($value) . "'"
        );
        return $row !== null;
    }

    public static function add(int $fieldId, string $value, int $parentId): void
    {
        self::prepare();
        if (!self::parentBelongs($fieldId, $parentId)) {
            $parentId = 0;
        }
        Database::insert('custom_field_menu_values', [
            'custom_field_id' => $fieldId,
            'menu_value' => $value,
            'parent_menu_value_id' => $parentId,
        ]);
        unset(self::$nodeCache[$fieldId]);
    }

    /**
     * @param array<string, mixed> $request
     */
    public static function applyEdits(int $fieldId, array $request): void
    {
        self::prepare();
        $old = [];
        foreach ($request as $key => $value) {
            if (str_starts_with((string)$key, 'menu_value_old_id_')) {
                $old[substr((string)$key, strlen('menu_value_old_id_'))] = (string)$value;
            }
        }
        $oldValues = array_values($old);
        foreach ($request as $key => $value) {
            if (!ctype_digit((string)$key)) {
                continue;
            }
            $id = (int)$key;
            $newValue = trim((string)$value);
            $previous = $old[(string)$id] ?? null;
            if ($previous === null || $newValue === '' || $newValue === $previous) {
                continue;
            }
            Database::update(
                'custom_field_menu_values',
                ['menu_value' => $newValue],
                "WHERE menu_value_id = '" . $id . "' AND custom_field_id = '" . $fieldId . "'"
            );
            if (!in_array($newValue, $oldValues, true)) {
                self::renameStoredItems($fieldId, $previous, $newValue);
                $childId = self::legacyChildId($fieldId);
                if ($childId > 0) {
                    self::renameStoredItems($childId, $previous, $newValue);
                }
            }
        }
        foreach ($request as $key => $value) {
            if (!str_starts_with((string)$key, 'parent_')) {
                continue;
            }
            unset(self::$nodeCache[$fieldId]);
            $id = (int)substr((string)$key, strlen('parent_'));
            if ($id <= 0) {
                continue;
            }
            $parentId = (int)$value;
            if ($parentId === $id || self::isUnder($fieldId, $parentId, $id) || !self::parentBelongs($fieldId, $parentId)) {
                continue;
            }
            Database::update(
                'custom_field_menu_values',
                ['parent_menu_value_id' => $parentId],
                "WHERE menu_value_id = '" . $id . "' AND custom_field_id = '" . $fieldId . "'"
            );
        }
        unset(self::$nodeCache[$fieldId]);
    }

    public static function delete(int $fieldId, int $valueId): void
    {
        self::prepare();
        $ids = [$valueId];
        self::collectDescendants($fieldId, $valueId, $ids);
        $list = implode(', ', array_map(static fn(int $id): string => (string)$id, $ids));
        Database::run("DELETE FROM custom_field_menu_values WHERE custom_field_id = " . $fieldId . " AND menu_value_id IN (" . $list . ")");
        unset(self::$nodeCache[$fieldId]);
    }

    private static function ensureParentColumn(): void
    {
        if (Database::columnExists('custom_field_menu_values', 'parent_menu_value_id')) {
            return;
        }
        Database::exec('ALTER TABLE custom_field_menu_values ADD parent_menu_value_id INTEGER NOT NULL DEFAULT 0');
    }

    private static function importCommaChildren(): void
    {
        // SchemaMigrator drops this legacy column after import; skip when gone.
        if (!Database::columnExists('custom_field_menu_values', 'sub_menu_values')) {
            return;
        }
        $rows = Database::rows(
            "SELECT menu_value_id, custom_field_id, sub_menu_values FROM custom_field_menu_values WHERE sub_menu_values IS NOT NULL AND sub_menu_values <> ''"
        );
        foreach ($rows as $row) {
            $parentId = (int)$row['menu_value_id'];
            $fieldId = (int)$row['custom_field_id'];
            $existingChild = Database::row(
                "SELECT menu_value_id FROM custom_field_menu_values WHERE parent_menu_value_id = " . $parentId . " LIMIT 1"
            );
            if ($existingChild === null) {
                foreach (explode(',', (string)$row['sub_menu_values']) as $label) {
                    $label = trim($label);
                    if ($label === '') {
                        continue;
                    }
                    self::findOrCreate($fieldId, $parentId, $label);
                }
            }
            Database::update(
                'custom_field_menu_values',
                ['sub_menu_values' => ''],
                "WHERE menu_value_id = '" . $parentId . "'"
            );
        }
    }

    private static function importMultiLevelMenus(): void
    {
        // SchemaMigrator drops these legacy columns after import; skip when gone.
        if (!Database::columnExists('custom_fields', 'menu_relationship')
            || !Database::columnExists('custom_fields', 'menu_value_links')
        ) {
            return;
        }
        $menus = Database::rows(
            "SELECT custom_field_id, menu_relationship, menu_value_links FROM custom_fields WHERE field_type = 'multiLevelMenu'"
        );
        foreach ($menus as $menu) {
            $fieldId = (int)$menu['custom_field_id'];
            self::ensureItemColumn($fieldId);
            $order = self::relationshipOrder((string)($menu['menu_relationship'] ?? ''));
            $links = @unserialize((string)($menu['menu_value_links'] ?? ''));
            if ($order === [] || !is_array($links)) {
                continue;
            }
            foreach ($links as $combination) {
                if (!is_array($combination)) {
                    continue;
                }
                $parentId = 0;
                foreach ($order as $sourceFieldId) {
                    $valueId = (int)($combination[$sourceFieldId] ?? $combination[(string)$sourceFieldId] ?? 0);
                    if ($valueId <= 0) {
                        continue;
                    }
                    $source = Database::row(
                        "SELECT menu_value FROM custom_field_menu_values WHERE menu_value_id = " . $valueId
                    );
                    $label = is_array($source) ? trim((string)($source['menu_value'] ?? '')) : '';
                    if ($label === '') {
                        continue;
                    }
                    $parentId = self::findOrCreate($fieldId, $parentId, $label);
                }
            }
            self::backfillMultiLevel($fieldId, $order);
        }
    }

    /**
     * Copy the deepest selected level from the old linked fields onto this menu's column.
     *
     * @param array<int, int> $order
     */
    private static function backfillMultiLevel(int $fieldId, array $order): void
    {
        $target = 'custom_field_' . $fieldId;
        if (!self::columnExists('items', $target)) {
            return;
        }
        $sources = [];
        foreach ($order as $sourceId) {
            $column = 'custom_field_' . $sourceId;
            if (self::columnExists('items', $column)) {
                $sources[] = $sourceId;
            }
        }
        if ($sources === []) {
            return;
        }
        $select = 'item_id, ' . $target . ', ' . implode(', ', array_map(static fn(int $id): string => 'custom_field_' . $id, $sources));
        foreach (Database::rows('SELECT ' . $select . ' FROM items') as $item) {
            if (trim((string)($item[$target] ?? '')) !== '') {
                continue;
            }
            $parentId = 0;
            $leaf = '';
            foreach ($sources as $sourceId) {
                $label = trim((string)($item['custom_field_' . $sourceId] ?? ''));
                if ($label === '') {
                    break;
                }
                $node = self::findChild(self::byParent($fieldId), $parentId, $label);
                if ($node === null) {
                    break;
                }
                $leaf = $label;
                $parentId = (int)$node['menu_value_id'];
            }
            if ($leaf === '') {
                continue;
            }
            Database::update(
                'items',
                [$target => $leaf],
                "WHERE item_id = '" . (int)$item['item_id'] . "'"
            );
        }
    }

    /**
     * @return array<int, int> field ids in level order
     */
    private static function relationshipOrder(string $relationship): array
    {
        $order = [];
        foreach (array_filter(explode('}-{', $relationship)) as $part) {
            $bits = explode(',', $part);
            $id = (int)($bits[0] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $order[$id] = (int)($bits[1] ?? 0);
        }
        asort($order);
        return array_map('intval', array_keys($order));
    }

    private static function findOrCreate(int $fieldId, int $parentId, string $label): int
    {
        $existing = Database::row(
            "SELECT menu_value_id FROM custom_field_menu_values WHERE custom_field_id = " . $fieldId
            . " AND parent_menu_value_id = " . $parentId
            . " AND menu_value = '" . Database::escape($label) . "'"
        );
        if ($existing !== null) {
            return (int)$existing['menu_value_id'];
        }
        $id = (int) Database::insert('custom_field_menu_values', [
            'custom_field_id' => $fieldId,
            'menu_value' => $label,
            'parent_menu_value_id' => $parentId,
        ]);
        unset(self::$nodeCache[$fieldId]);
        return $id;
    }

    private static function ensureItemColumn(int $fieldId): void
    {
        // Values are stored in item_field_values; items has a fixed core schema.
        unset($fieldId);
    }

    private static function columnExists(string $table, string $column): bool
    {
        if (!self::tableExists($table)) {
            return false;
        }
        return Database::columnExists($table, $column);
    }

    private static function loadLinks(): void
    {
        self::$legacyChild = [];
        self::$childOf = [];
        self::$names = [];
        $rows = Database::select('custom_fields', ['custom_field_id', 'custom_field_name', 'sub_menu'], '');
        foreach ($rows as $row) {
            $id = (int)$row['custom_field_id'];
            self::$names[$id] = (string)$row['custom_field_name'];
            $child = (int)($row['sub_menu'] ?? 0);
            if ($child > 0 && $child !== $id) {
                self::$legacyChild[$id] = $child;
                self::$childOf[$child] = $id;
            }
        }
    }

    private static function inputName(int $fieldId, int $childId, int $level): string
    {
        if ($level === 0) {
            return 'custom_field_' . $fieldId;
        }
        if ($level === 1 && $childId > 0) {
            return 'custom_field_' . $childId;
        }
        return 'menu_level_' . $fieldId . '_' . $level;
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $byParent
     * @param array<string, mixed> $request
     * @return array<int, array<string, mixed>>
     */
    private static function pathFromRequest(int $fieldId, int $childId, array $byParent, array $request): array
    {
        $path = [];
        $parentId = 0;
        for ($level = 0; $level < 12; $level++) {
            $name = self::inputName($fieldId, $childId, $level);
            if (!array_key_exists($name, $request)) {
                break;
            }
            $node = self::findChild($byParent, $parentId, (string)$request[$name]);
            if ($node === null) {
                break;
            }
            $path[] = $node;
            $parentId = (int)$node['menu_value_id'];
        }
        return $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function pathFromStored(int $fieldId, string $stored, string $childStored): array
    {
        if ($stored === '') {
            return [];
        }
        $byParent = self::byParent($fieldId);
        $root = self::findChild($byParent, 0, $stored);
        if ($root !== null) {
            $path = [$root];
            if ($childStored !== '') {
                $child = self::findChild($byParent, (int)$root['menu_value_id'], $childStored);
                if ($child !== null) {
                    $path[] = $child;
                }
            }
            return $path;
        }
        $indexed = self::indexed($fieldId);
        $match = null;
        foreach ($indexed as $node) {
            if ((string)$node['menu_value'] === $stored) {
                $match = $node;
            }
        }
        if ($match === null) {
            return [];
        }
        $path = [];
        $cursor = $match;
        while ($cursor !== null) {
            array_unshift($path, $cursor);
            $parentId = (int)$cursor['parent_menu_value_id'];
            $cursor = $parentId > 0 ? ($indexed[$parentId] ?? null) : null;
        }
        return $path;
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $byParent
     * @return array<string, mixed>|null
     */
    private static function findChild(array $byParent, int $parentId, string $label): ?array
    {
        if ($label === '') {
            return null;
        }
        foreach ($byParent[$parentId] ?? [] as $node) {
            if ((string)$node['menu_value'] === $label) {
                return $node;
            }
        }
        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function indexed(int $fieldId): array
    {
        if (!isset(self::$nodeCache[$fieldId])) {
            $rows = Database::rows(
                "SELECT menu_value_id, custom_field_id, menu_value, parent_menu_value_id FROM custom_field_menu_values WHERE custom_field_id = "
                . $fieldId . " ORDER BY menu_value_id ASC"
            );
            $indexed = [];
            foreach ($rows as $row) {
                $row['menu_value_id'] = (int)$row['menu_value_id'];
                $row['parent_menu_value_id'] = (int)($row['parent_menu_value_id'] ?? 0);
                $indexed[(int)$row['menu_value_id']] = $row;
            }
            self::$nodeCache[$fieldId] = $indexed;
        }
        return self::$nodeCache[$fieldId];
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function byParent(int $fieldId): array
    {
        $grouped = [];
        foreach (self::indexed($fieldId) as $node) {
            $grouped[(int)$node['parent_menu_value_id']][] = $node;
        }
        return $grouped;
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $byParent
     * @param array<int, array<string, mixed>> $out
     */
    private static function walk(array $byParent, int $parentId, int $depth, string $prefix, array &$out): void
    {
        foreach ($byParent[$parentId] ?? [] as $node) {
            $label = (string)$node['menu_value'];
            $path = $prefix === '' ? $label : $prefix . ' / ' . $label;
            $node['depth'] = $depth;
            $node['path'] = $path;
            $out[] = $node;
            self::walk($byParent, (int)$node['menu_value_id'], $depth + 1, $path, $out);
        }
    }

    private static function isUnder(int $fieldId, int $nodeId, int $ancestorId): bool
    {
        if ($ancestorId <= 0 || $nodeId <= 0) {
            return false;
        }
        $indexed = self::indexed($fieldId);
        $cursor = $indexed[$nodeId] ?? null;
        $guard = 0;
        while ($cursor !== null && $guard < 20) {
            $parentId = (int)$cursor['parent_menu_value_id'];
            if ($parentId === $ancestorId) {
                return true;
            }
            $cursor = $parentId > 0 ? ($indexed[$parentId] ?? null) : null;
            $guard++;
        }
        return false;
    }

    private static function parentBelongs(int $fieldId, int $parentId): bool
    {
        if ($parentId === 0) {
            return true;
        }
        $node = self::indexed($fieldId)[$parentId] ?? null;
        return $node !== null && (int)$node['custom_field_id'] === $fieldId;
    }

    /**
     * @param array<int, int> $ids
     */
    private static function collectDescendants(int $fieldId, int $parentId, array &$ids): void
    {
        foreach (self::byParent($fieldId)[$parentId] ?? [] as $node) {
            $id = (int)$node['menu_value_id'];
            $ids[] = $id;
            self::collectDescendants($fieldId, $id, $ids);
        }
    }

    private static function renameStoredItems(int $fieldId, string $old, string $new): void
    {
        if (!self::tableExists('items')) {
            return;
        }
        $column = 'custom_field_' . $fieldId;
        if (!Database::columnExists('items', $column)) {
            return;
        }
        Database::update(
            'items',
            [$column => $new],
            "WHERE " . $column . " = '" . Database::escape($old) . "'"
        );
    }

    private static function tableExists(string $name): bool
    {
        return Database::tableExists($name);
    }
}
