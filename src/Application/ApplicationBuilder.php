<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Permission;
use Adlexone\Data\GroupMembership;
use Adlexone\FrameOne\Library;
use Adlexone\support\Database;
use Adlexone\support\FieldTypes;

/**
 * Guided creation of a business application: record type, fields, and nav.
 */
final class ApplicationBuilder
{
    public const BLUEPRINT_BLANK = 'blank';
    public const BLUEPRINT_CASE = 'case';
    public const BLUEPRINT_CRM = 'crm';
    public const BLUEPRINT_HELPDESK = 'helpdesk';

    /**
     * @return array<string, array{label: string, hint: string, fields: list<array<string, mixed>>, screens: list<string>}>
     */
    public static function blueprints(): array
    {
        $caseFields = [
            ['name' => 'Status', 'type' => FieldTypes::MENU, 'values' => ['New', 'In progress', 'Waiting', 'Closed']],
            ['name' => 'Priority', 'type' => FieldTypes::MENU, 'values' => ['Low', 'Normal', 'High']],
            ['name' => 'Category', 'type' => FieldTypes::MENU, 'values' => ['General', 'Support', 'Other']],
        ];
        $coreScreens = [Library::WORK, Library::CREATE, Library::SEARCH_LIST];

        return [
            self::BLUEPRINT_BLANK => [
                'label' => 'Blank',
                'hint' => 'A shell and one record type. Add fields when you are ready.',
                'fields' => [],
                'screens' => $coreScreens,
            ],
            self::BLUEPRINT_CASE => [
                'label' => 'Case / request',
                'hint' => 'Track work with status, priority, and category.',
                'fields' => $caseFields,
                'screens' => $coreScreens,
            ],
            self::BLUEPRINT_CRM => [
                'label' => 'Simple CRM',
                'hint' => 'Contacts with organisation, email, stage, and notes.',
                'fields' => [
                    ['name' => 'Organisation', 'type' => FieldTypes::TEXT_BOX],
                    ['name' => 'Email', 'type' => FieldTypes::TEXT_BOX],
                    ['name' => 'Stage', 'type' => FieldTypes::MENU, 'values' => ['Lead', 'Qualified', 'Customer', 'Inactive']],
                    ['name' => 'Notes', 'type' => FieldTypes::TEXT_AREA],
                ],
                'screens' => $coreScreens,
            ],
            self::BLUEPRINT_HELPDESK => [
                'label' => 'Helpdesk',
                'hint' => 'Cases, announcements, and settings for a support team.',
                'fields' => $caseFields,
                'screens' => [
                    Library::WORK,
                    Library::CREATE,
                    Library::SEARCH_LIST,
                    Library::ANNOUNCEMENTS,
                    Library::SETTINGS,
                ],
            ],
        ];
    }

    /**
     * @param list<string>|null $screens FrameOne capability ids; null = blueprint default
     * @param list<int> $groupIds groups to receive app permissions (empty = admins + creator)
     * @param list<string> $permLevels use|announce|settings
     * @return array{application_id: int, slug: string, item_type_id: int}
     */
    public static function create(
        string $name,
        string $slug,
        string $hint,
        string $icon,
        string $blueprint,
        string $recordTypeName,
        ?array $screens = null,
        array $groupIds = [],
        array $permLevels = []
    ): array {
        $name = trim($name);
        $slug = self::slugify($slug, $name);
        $hint = trim($hint);
        $recordTypeName = trim($recordTypeName);
        $blueprints = self::blueprints();

        if ($name === '' || $slug === '') {
            throw new \InvalidArgumentException('Enter an application name.');
        }
        if (!isset($blueprints[$blueprint])) {
            throw new \InvalidArgumentException('Choose a blueprint.');
        }
        if ($recordTypeName === '') {
            $recordTypeName = $name . ' record';
        }
        if (!in_array($icon, Capabilities::icons(), true)) {
            $icon = 'ic-launch';
        }
        if (ApplicationStore::slugInUse($slug, 0)) {
            throw new \InvalidArgumentException('That slug is already used.');
        }
        if (self::itemTypeNameInUse($recordTypeName)) {
            throw new \InvalidArgumentException('That record type name is already used.');
        }

        $allowedScreens = array_keys(Library::builderScreens());
        $chosen = $screens ?? $blueprints[$blueprint]['screens'];
        $chosen = array_values(array_filter(
            $chosen,
            static fn (string $id): bool => in_array($id, $allowedScreens, true)
        ));
        if ($chosen === []) {
            $chosen = [Library::WORK, Library::CREATE, Library::SEARCH_LIST];
        }

        $itemTypeId = self::createItemType($recordTypeName);
        $fieldIds = self::createBlueprintFields($blueprints[$blueprint]['fields'], $name);
        self::attachFields($itemTypeId, $fieldIds);

        $max = 0;
        foreach (ApplicationStore::all() as $app) {
            $max = max($max, (int) $app['sort_order']);
        }

        $usePerm = Permission::appUse($slug);
        $applicationId = ApplicationStore::insertApplication([
            'slug' => $slug,
            'name' => $name,
            'hint' => $hint !== '' ? $hint : $blueprints[$blueprint]['hint'],
            'icon' => $icon,
            'permission' => $usePerm,
            'enabled' => 1,
            'sort_order' => $max + 10,
            'entry_mode' => 'shell',
            'legacy_controller' => '',
            'legacy_key' => '',
            'settings_json' => json_encode([
                'default_item_type_id' => (string) $itemTypeId,
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $grantedGroups = Permission::grantAppToGroups($slug, $groupIds, $permLevels);
        GroupMembership::setItemTypeGroups($itemTypeId, $grantedGroups);
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        if ($userId > 0) {
            \Adlexone\Auth\Access::hydrateSession($userId);
        }

        $typeConfig = ['item_type_id' => (string) $itemTypeId];
        $labels = Library::builderScreens();
        $icons = [
            Library::WORK => 'ic-search',
            Library::CREATE => 'ic-itemtype-add',
            Library::SEARCH_LIST => 'ic-search',
            Library::ANNOUNCEMENTS => 'ic-announcements',
            Library::SETTINGS => 'ic-settings',
        ];
        $order = 10;
        foreach ($chosen as $capability) {
            $config = in_array($capability, [Library::WORK, Library::CREATE, Library::SEARCH_ADVANCED], true)
                ? $typeConfig
                : [];
            ApplicationStore::insertNav(
                $applicationId,
                $labels[$capability] ?? Capabilities::label($capability),
                $capability,
                '',
                $icons[$capability] ?? '',
                $config,
                $order
            );
            $order += 10;
        }

        return [
            'application_id' => $applicationId,
            'slug' => $slug,
            'item_type_id' => $itemTypeId,
        ];
    }

    public static function slugify(string $slug, string $name): string
    {
        $source = trim($slug) !== '' ? $slug : $name;
        $source = strtolower($source);
        $source = preg_replace('/[^a-z0-9]+/', '-', $source) ?? '';
        return trim($source, '-');
    }

    private static function itemTypeNameInUse(string $name): bool
    {
        return Database::first('item_types', ['item_type_id'], 'item_type_name = ?', [$name]) !== null;
    }

    private static function createItemType(string $name): int
    {
        return Database::insert('item_types', [
            'item_type_name' => $name,
            'user_security' => '',
            'enabled' => 'Yes',
        ]);
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<int>
     */
    private static function createBlueprintFields(array $fields, string $appName): array
    {
        $ids = [];
        foreach ($fields as $field) {
            $desired = (string) ($field['name'] ?? '');
            if ($desired === '') {
                continue;
            }
            $type = FieldTypes::normalise((string) ($field['type'] ?? FieldTypes::TEXT_BOX));
            $name = self::uniqueFieldName($desired, $appName);
            $id = Database::insert('custom_fields', [
                'custom_field_name' => $name,
                'field_type' => $type,
                'default_value' => '',
                'sub_menu' => 0,
                'enabled' => 'Yes',
                'field_reference' => 0,
                'data' => '',
                'validation_type' => '',
                'required' => 'No',
            ]);
            Database::update('custom_fields', ['field_reference' => $id], 'custom_field_id = ?', [$id]);

            $values = $field['values'] ?? [];
            if ($type === FieldTypes::MENU && is_array($values)) {
                foreach ($values as $value) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        continue;
                    }
                    Database::insert('custom_field_menu_values', [
                        'custom_field_id' => $id,
                        'menu_value' => $value,
                        'parent_menu_value_id' => 0,
                    ]);
                }
            }
            $ids[] = $id;
        }
        return $ids;
    }

    private static function uniqueFieldName(string $desired, string $appName): string
    {
        if (Database::first('custom_fields', ['custom_field_id'], 'custom_field_name = ?', [$desired]) === null) {
            return $desired;
        }
        $candidate = $desired . ' (' . $appName . ')';
        if (Database::first('custom_fields', ['custom_field_id'], 'custom_field_name = ?', [$candidate]) === null) {
            return $candidate;
        }
        $n = 2;
        while (Database::first('custom_fields', ['custom_field_id'], 'custom_field_name = ?', [$candidate . ' ' . $n]) !== null) {
            $n++;
        }
        return $candidate . ' ' . $n;
    }

    /**
     * @param list<int> $fieldIds
     */
    private static function attachFields(int $itemTypeId, array $fieldIds): void
    {
        $order = 10;
        foreach ($fieldIds as $fieldId) {
            Database::insert('item_type_custom_fields', [
                'item_type_id' => $itemTypeId,
                'custom_field_id' => $fieldId,
                'custom_field_order' => $order,
            ]);
            $order += 10;
        }
    }
}
