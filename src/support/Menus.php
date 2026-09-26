<?php
declare(strict_types=1);

namespace Adlexone\support;

/**
 * One menu model for every dropdown.
 *
 * A menu is a custom field of type "menu". Its options live in custom_field_menu_values.
 * A menu can depend on another menu (custom_fields.parent_field_id). Each option then
 * belongs to one value of that menu (custom_field_menu_values.parent_value_id), so a
 * single list, a dependent list, and any deeper chain are managed the same way.
 *
 * Older field types (subMenu, subMenuChild, multiLevelMenu) and the comma-separated
 * sub_menu_values column are rewritten into this shape once, on startup.
 */
final class Menus
{
    private static bool $ready = false;

    /** @var array<int, array<string, mixed>> */
    private static array $fields = [];

    private static bool $fieldsLoaded = false;

    private static int $depth = 0;

    public static function ensureReady(): void
    {
        if (self::$ready) {
            return;
        }
        self::$ready = true;

        try {
            $pdo = new \PDO((string)constant('DSN'), null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('PRAGMA busy_timeout = 5000;');
        } catch (\Throwable $e) {
            error_log('Menu schema upgrade skipped: ' . $e->getMessage());
            return;
        }

        $table = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'custom_fields'")->fetchColumn();
        if ($table === false) {
            return;
        }

        self::ensureColumn($pdo, 'custom_fields', 'parent_field_id', 'INTEGER NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'custom_field_menu_values', 'parent_value_id', 'INTEGER NOT NULL DEFAULT 0');
        self::migrateLegacy($pdo);
    }

    /**
     * Menus this field is allowed to depend on.
     *
     * @return list<array<string, mixed>>
     */
    public static function candidateParents(int $fieldId): array
    {
        self::loadFields();
        $candidates = [];
        foreach (self::$fields as $field) {
            if (FieldTypes::normalise((string)($field['field_type'] ?? '')) !== FieldTypes::MENU) {
                continue;
            }
            if ((string)($field['enabled'] ?? '') !== 'Yes') {
                continue;
            }
            $id = (int)$field['custom_field_id'];
            if ($fieldId > 0 && $id === $fieldId) {
                continue;
            }
            if (!self::parentIsAllowed($fieldId, $id)) {
                continue;
            }
            $candidates[] = $field;
        }
        usort($candidates, static fn(array $a, array $b): int => strcasecmp((string)$a['custom_field_name'], (string)$b['custom_field_name']));
        return $candidates;
    }

    public static function parentIsAllowed(int $fieldId, int $parentId): bool
    {
        if ($parentId === 0) {
            return true;
        }
        self::loadFields();
        $seen = [];
        $current = $parentId;
        while ($current > 0) {
            if (($fieldId > 0 && $current === $fieldId) || isset($seen[$current])) {
                return false;
            }
            $seen[$current] = true;
            $field = self::$fields[$current] ?? null;
            if ($field === null || FieldTypes::normalise((string)($field['field_type'] ?? '')) !== FieldTypes::MENU) {
                return false;
            }
            $current = (int)($field['parent_field_id'] ?? 0);
        }
        return true;
    }

    public static function hasDependents(int $fieldId): bool
    {
        self::loadFields();
        foreach (self::$fields as $field) {
            if ((int)($field['parent_field_id'] ?? 0) === $fieldId) {
                return true;
            }
        }
        return false;
    }

    public static function fieldName(int $fieldId): string
    {
        $field = self::field($fieldId);
        return $field === null ? '' : (string)($field['custom_field_name'] ?? '');
    }

    /**
     * Selected text of the menu this field depends on, or null when it depends on nothing.
     * An empty string means the parent has no usable selection, so this menu has no options yet.
     *
     * @param array<string, mixed> $field
     * @param array<string, mixed> $source Posted or stored item values keyed as custom_field_{id}.
     */
    public static function parentSelection(array $field, array $source): ?string
    {
        $parentId = (int)($field['parent_field_id'] ?? 0);
        if ($parentId <= 0 || self::$depth > 20) {
            return null;
        }
        self::$depth++;

        $parent = self::field($parentId);
        if ($parent === null) {
            self::$depth--;
            return '';
        }

        $key = 'custom_field_' . $parentId;
        $raw = array_key_exists($key, $source) ? (string)$source[$key] : (string)($parent['default_value'] ?? '');
        $valid = $raw !== '' && in_array($raw, self::optionValues($parentId, $source), true);
        self::$depth--;
        return $valid ? $raw : '';
    }

    /**
     * Option texts for a menu, filtered by the current parent selection when it has one.
     *
     * @param array<string, mixed> $source
     * @return list<string>
     */
    public static function optionValues(int $fieldId, array $source): array
    {
        $field = self::field($fieldId);
        if ($field === null) {
            return [];
        }
        $parentFieldId = (int)($field['parent_field_id'] ?? 0);
        if ($parentFieldId <= 0) {
            return self::texts($fieldId, 'parent_value_id = 0');
        }

        $selected = self::parentSelection($field, $source);
        if ($selected === null || $selected === '') {
            return [];
        }
        $parentIds = self::valueIds($parentFieldId, $selected, $source);
        if ($parentIds === []) {
            return [];
        }
        return self::texts($fieldId, 'parent_value_id IN (' . implode(',', $parentIds) . ')');
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $source
     */
    public static function selectHtml(array $field, string $selected, array $source, string $inputName, bool $reloadOnChange): string
    {
        self::ensureReady();
        $fieldId = (int)($field['custom_field_id'] ?? 0);
        $options = self::optionValues($fieldId, $source);
        $parentFieldId = (int)($field['parent_field_id'] ?? 0);
        $needsBlank = $parentFieldId > 0 || self::hasDependents($fieldId);

        $values = [];
        $labels = [];
        if ($needsBlank) {
            $parentChosen = $parentFieldId > 0 && (self::parentSelection($field, $source) ?? '') !== '';
            $values[] = '';
            $labels[] = ($options === [] && $parentChosen) ? TXT_296 : TXT_284;
        }
        foreach ($options as $option) {
            $values[] = $option;
            $labels[] = $option;
        }
        if ($values === []) {
            $values = null;
            $labels = null;
        }

        $other = $reloadOnChange ? 'onChange="this.form.submit()"' : '';
        return RenderViews::buildSelectDropdown($inputName, $values, $labels, $selected, $other);
    }

    /**
     * Drop the dependency from menus that pointed at a field which is no longer a menu.
     * Their options become a single list.
     */
    public static function detachDependents(int $fieldId): void
    {
        if ($fieldId <= 0) {
            return;
        }
        $children = Database::buildArray(
            "SELECT custom_field_id FROM custom_fields WHERE parent_field_id = '" . $fieldId . "'"
        );
        foreach ($children as $child) {
            $id = (int)$child['custom_field_id'];
            Database::query(Database::sqlUpdate('custom_fields', ['parent_field_id' => '0'], "WHERE custom_field_id = '" . $id . "'"));
            Database::query(
                "UPDATE custom_field_menu_values SET parent_value_id = 0 WHERE custom_field_id = '" . $id . "'"
            );
        }
        self::$fieldsLoaded = false;
        self::$fields = [];
    }

    /**
     * Keep stored item text in step when an option is renamed.
     */
    public static function renameStoredValues(int $fieldId, string $from, string $to): void
    {
        if ($from === $to || $fieldId <= 0) {
            return;
        }
        $column = 'custom_field_' . $fieldId;
        $existing = Database::buildArray("SELECT name FROM pragma_table_info('items') WHERE name = '" . $column . "'");
        if ($existing === []) {
            return;
        }
        Database::query(Database::sqlUpdate(
            'items',
            [$column => $to],
            "WHERE " . $column . " = '" . Database::escape($from) . "'"
        ));
    }

    private static function ensureColumn(\PDO $pdo, string $table, string $column, string $definition): void
    {
        $columns = $pdo->query("PRAGMA table_info('" . $table . "')")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($columns as $info) {
            if (($info['name'] ?? '') === $column) {
                return;
            }
        }
        $pdo->exec('ALTER TABLE "' . $table . '" ADD COLUMN "' . $column . '" ' . $definition);
    }

    private static function migrateLegacy(\PDO $pdo): void
    {
        $count = (int)$pdo->query(
            "SELECT COUNT(*) FROM custom_fields WHERE field_type IN ('subMenu', 'subMenuChild', 'multiLevelMenu')"
        )->fetchColumn();
        if ($count === 0) {
            return;
        }

        $pdo->beginTransaction();
        try {
            $containers = $pdo->query(
                "SELECT * FROM custom_fields WHERE field_type = 'multiLevelMenu'"
            )->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($containers as $container) {
                self::migrateMultiLevel($pdo, $container);
                $id = (int)$container['custom_field_id'];
                $pdo->exec("DELETE FROM item_type_custom_fields WHERE custom_field_id = " . $id);
                $pdo->exec("DELETE FROM custom_field_menu_values WHERE custom_field_id = " . $id);
                $pdo->exec("DELETE FROM custom_fields WHERE custom_field_id = " . $id);
            }

            $parents = $pdo->query(
                "SELECT * FROM custom_fields WHERE field_type = 'subMenu'"
            )->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($parents as $parent) {
                self::migrateSubMenu($pdo, $parent);
            }

            $pdo->exec(
                "UPDATE custom_fields SET field_type = 'menu', sub_menu = 0 WHERE field_type = 'subMenuChild'"
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $container
     */
    private static function migrateMultiLevel(\PDO $pdo, array $container): void
    {
        $links = @unserialize((string)($container['menu_value_links'] ?? ''));
        if (!is_array($links) || $links === []) {
            return;
        }

        $ordered = [];
        foreach (array_filter(explode('}-{', (string)($container['menu_relationship'] ?? ''))) as $part) {
            $bits = explode(',', $part);
            $id = (int)($bits[0] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $ordered[$id] = (int)($bits[1] ?? 0);
        }
        asort($ordered);
        $chain = array_map('intval', array_keys($ordered));
        if (count($chain) < 2) {
            return;
        }

        for ($i = 1, $n = count($chain); $i < $n; $i++) {
            $pdo->exec(
                "UPDATE custom_fields SET parent_field_id = " . $chain[$i - 1]
                . " WHERE custom_field_id = " . $chain[$i]
                . " AND field_type IN ('menu', 'buildSelectDropdown')"
            );
        }

        foreach ($links as $combo) {
            if (!is_array($combo)) {
                continue;
            }
            $previousValueId = 0;
            foreach ($chain as $index => $fieldId) {
                $valueId = (int)($combo[$fieldId] ?? $combo[(string)$fieldId] ?? 0);
                if ($valueId <= 0) {
                    $previousValueId = 0;
                    continue;
                }
                if ($index === 0) {
                    $previousValueId = $valueId;
                    continue;
                }
                if ($previousValueId <= 0) {
                    continue;
                }
                $previousValueId = self::attachValueToParent($pdo, $valueId, $previousValueId, $fieldId);
            }
        }
    }

    private static function attachValueToParent(\PDO $pdo, int $valueId, int $parentValueId, int $fieldId): int
    {
        $statement = $pdo->prepare('SELECT * FROM custom_field_menu_values WHERE menu_value_id = ?');
        $statement->execute([$valueId]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (int)$row['custom_field_id'] !== $fieldId) {
            return $valueId;
        }
        $currentParent = (int)($row['parent_value_id'] ?? 0);
        if ($currentParent === 0) {
            $update = $pdo->prepare('UPDATE custom_field_menu_values SET parent_value_id = ? WHERE menu_value_id = ?');
            $update->execute([$parentValueId, $valueId]);
            return $valueId;
        }
        if ($currentParent === $parentValueId) {
            return $valueId;
        }

        $existing = $pdo->prepare(
            'SELECT menu_value_id FROM custom_field_menu_values WHERE custom_field_id = ? AND menu_value = ? AND parent_value_id = ?'
        );
        $existing->execute([$fieldId, $row['menu_value'], $parentValueId]);
        $found = $existing->fetchColumn();
        if ($found !== false) {
            return (int)$found;
        }

        $next = (int)$pdo->query('SELECT COALESCE(MAX(menu_value_id), 0) + 1 FROM custom_field_menu_values')->fetchColumn();
        $insert = $pdo->prepare(
            'INSERT INTO custom_field_menu_values (menu_value_id, custom_field_id, menu_value, sub_menu_values, parent_value_id) VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([$next, $fieldId, $row['menu_value'], '', $parentValueId]);
        return $next;
    }

    /**
     * @param array<string, mixed> $parent
     */
    private static function migrateSubMenu(\PDO $pdo, array $parent): void
    {
        $parentId = (int)$parent['custom_field_id'];
        $childId = (int)($parent['sub_menu'] ?? 0);
        if ($childId > 0 && $childId !== $parentId) {
            $pdo->exec(
                "UPDATE custom_fields SET field_type = 'menu', parent_field_id = " . $parentId . ", sub_menu = 0 WHERE custom_field_id = " . $childId
            );
            $values = $pdo->query(
                'SELECT menu_value_id, menu_value, sub_menu_values FROM custom_field_menu_values WHERE custom_field_id = ' . $parentId
            )->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($values as $value) {
                $labels = array_filter(array_map('trim', explode(',', (string)($value['sub_menu_values'] ?? ''))), static fn(string $label): bool => $label !== '');
                foreach ($labels as $label) {
                    $exists = $pdo->prepare(
                        'SELECT menu_value_id FROM custom_field_menu_values WHERE custom_field_id = ? AND menu_value = ? AND parent_value_id = ?'
                    );
                    $exists->execute([$childId, $label, (int)$value['menu_value_id']]);
                    if ($exists->fetchColumn() !== false) {
                        continue;
                    }
                    $next = (int)$pdo->query('SELECT COALESCE(MAX(menu_value_id), 0) + 1 FROM custom_field_menu_values')->fetchColumn();
                    $insert = $pdo->prepare(
                        'INSERT INTO custom_field_menu_values (menu_value_id, custom_field_id, menu_value, sub_menu_values, parent_value_id) VALUES (?, ?, ?, ?, ?)'
                    );
                    $insert->execute([$next, $childId, $label, '', (int)$value['menu_value_id']]);
                }
                $clear = $pdo->prepare('UPDATE custom_field_menu_values SET sub_menu_values = ? WHERE menu_value_id = ?');
                $clear->execute(['', (int)$value['menu_value_id']]);
            }
        }
        $pdo->exec(
            "UPDATE custom_fields SET field_type = 'menu', sub_menu = 0 WHERE custom_field_id = " . $parentId
        );
    }

    /**
     * @return list<string>
     */
    private static function texts(int $fieldId, string $parentClause): array
    {
        $rows = Database::buildArray(
            "SELECT menu_value FROM custom_field_menu_values WHERE custom_field_id = '" . $fieldId . "' AND " . $parentClause . ' ORDER BY menu_value_id'
        );
        $texts = [];
        foreach ($rows as $row) {
            $text = (string)$row['menu_value'];
            if (!in_array($text, $texts, true)) {
                $texts[] = $text;
            }
        }
        return $texts;
    }

    /**
     * @param array<string, mixed> $source
     * @return list<int>
     */
    private static function valueIds(int $fieldId, string $text, array $source): array
    {
        if ($text === '') {
            return [];
        }
        $field = self::field($fieldId);
        if ($field === null) {
            return [];
        }
        $rows = Database::buildArray(
            "SELECT menu_value_id, parent_value_id FROM custom_field_menu_values WHERE custom_field_id = '"
            . $fieldId . "' AND menu_value = '" . Database::escape($text) . "'"
        );
        $parentFieldId = (int)($field['parent_field_id'] ?? 0);
        if ($parentFieldId <= 0) {
            $ids = [];
            foreach ($rows as $row) {
                if ((int)$row['parent_value_id'] === 0) {
                    $ids[] = (int)$row['menu_value_id'];
                }
            }
            return $ids;
        }

        $parentText = (string)self::parentSelection($field, $source);
        $allowedParents = $parentText === '' ? [] : self::valueIds($parentFieldId, $parentText, $source);
        $ids = [];
        foreach ($rows as $row) {
            if (in_array((int)$row['parent_value_id'], $allowedParents, true)) {
                $ids[] = (int)$row['menu_value_id'];
            }
        }
        return $ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function field(int $fieldId): ?array
    {
        self::loadFields();
        return self::$fields[$fieldId] ?? null;
    }

    private static function loadFields(): void
    {
        if (self::$fieldsLoaded) {
            return;
        }
        self::ensureReady();
        self::$fields = [];
        foreach (Database::buildArray(Database::sqlSelect('custom_fields', '*')) as $row) {
            self::$fields[(int)$row['custom_field_id']] = $row;
        }
        self::$fieldsLoaded = true;
    }
}
