<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Permission;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\support\Database;
use Adlexone\support\FieldTypes;

/**
 * Guided creation of a business application: record type, fields, and nav.
 */
final class ApplicationBuilder
{
    private static bool $installingShowcase = false;

    public const BLUEPRINT_BLANK = 'blank';
    public const BLUEPRINT_STUDIO = 'studio';
    public const BLUEPRINT_MATTERS = 'matters';
    public const BLUEPRINT_HOUSE = 'house';

    /**
     * @return array<string, array{label: string, hint: string, record: string, icon: string, rule: string, fields: list<array<string, mixed>>, screens: list<string>}>
     */
    public static function blueprints(): array
    {
        $coreScreens = [ScreenCatalog::WORK, ScreenCatalog::CREATE, ScreenCatalog::SEARCH_LIST];

        return [
            self::BLUEPRINT_STUDIO => [
                'label' => 'Studio',
                'hint' => 'Live jobs, from enquiry to delivered.',
                'record' => 'Job',
                'icon' => 'ic-item-mgmt',
                'rule' => 'Emails the owner when a job reaches Review.',
                'fields' => [
                    ['name' => 'Stage', 'type' => FieldTypes::MENU, 'values' => ['Enquiry', 'Sketch', 'Making', 'Review', 'Delivered']],
                    ['name' => 'Client', 'type' => FieldTypes::TEXT_BOX],
                    ['name' => 'Next step', 'type' => FieldTypes::TEXT_BOX],
                ],
                'screens' => $coreScreens,
            ],
            self::BLUEPRINT_MATTERS => [
                'label' => 'Matters',
                'hint' => 'Waiting on us, or waiting on them.',
                'record' => 'Matter',
                'icon' => 'ic-knowledgebase',
                'rule' => 'Emails the owner when a matter is waiting on us.',
                'fields' => [
                    ['name' => 'Waiting', 'type' => FieldTypes::MENU, 'values' => ['Waiting on us', 'Waiting on them', 'Done']],
                    ['name' => 'Client', 'type' => FieldTypes::TEXT_BOX],
                    ['name' => 'Next step', 'type' => FieldTypes::TEXT_BOX],
                ],
                'screens' => $coreScreens,
            ],
            self::BLUEPRINT_HOUSE => [
                'label' => 'House',
                'hint' => 'The week on the board, and a notice on the wall.',
                'record' => 'Evening',
                'icon' => 'ic-announcements',
                'rule' => 'Emails the owner when something is on tonight.',
                'fields' => [
                    ['name' => 'When', 'type' => FieldTypes::MENU, 'values' => ['This week', 'Tonight', 'Done']],
                    ['name' => 'Place', 'type' => FieldTypes::TEXT_BOX],
                ],
                'screens' => [
                    ScreenCatalog::WORK,
                    ScreenCatalog::CREATE,
                    ScreenCatalog::ANNOUNCEMENTS,
                    ScreenCatalog::SEARCH_LIST,
                ],
            ],
            self::BLUEPRINT_BLANK => [
                'label' => 'Blank',
                'hint' => 'A shell and one record. Add fields when you are ready.',
                'record' => '',
                'icon' => 'ic-launch',
                'rule' => '',
                'fields' => [],
                'screens' => $coreScreens,
            ],
        ];
    }

    /**
     * @param list<string>|null $screens Built-in screen ids; null = blueprint default
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
            $recordTypeName = trim((string) ($blueprints[$blueprint]['record'] ?? ''));
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

        $allowedScreens = array_keys(ScreenCatalog::builderScreens());
        $chosen = $screens ?? $blueprints[$blueprint]['screens'];
        $chosen = array_values(array_filter(
            $chosen,
            static fn (string $id): bool => in_array($id, $allowedScreens, true)
        ));
        if ($chosen === []) {
            $chosen = [ScreenCatalog::WORK, ScreenCatalog::CREATE, ScreenCatalog::SEARCH_LIST];
        }

        $itemTypeId = self::createItemType($recordTypeName);
        $fieldDefs = $blueprints[$blueprint]['fields'];
        $fieldIds = self::createBlueprintFields($fieldDefs, $name);
        self::attachFields($itemTypeId, $fieldIds);
        $fieldIdsByName = [];
        $idIndex = 0;
        foreach ($fieldDefs as $field) {
            $fieldName = trim((string) ($field['name'] ?? ''));
            if ($fieldName === '') {
                continue;
            }
            if (!isset($fieldIds[$idIndex])) {
                break;
            }
            $fieldIdsByName[$fieldName] = $fieldIds[$idIndex];
            $idIndex++;
        }

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
        $labels = ScreenCatalog::builderScreens();
        $icons = [
            ScreenCatalog::WORK => 'ic-search',
            ScreenCatalog::CREATE => 'ic-itemtype-add',
            ScreenCatalog::SEARCH_LIST => 'ic-search',
            ScreenCatalog::ANNOUNCEMENTS => 'ic-announcements',
            ScreenCatalog::SETTINGS => 'ic-settings',
        ];
        $order = 10;
        foreach ($chosen as $capability) {
            $config = in_array($capability, [ScreenCatalog::WORK, ScreenCatalog::CREATE, ScreenCatalog::SEARCH_ADVANCED], true)
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

        Rules::seedBlueprint($applicationId, $itemTypeId, $blueprint, $fieldIdsByName);

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

    /**
     * Studio, Matters, and House as applications you can open, with work already on the board.
     * Skips any slug that already exists.
     */
    public static function installShowcase(): void
    {
        if (self::$installingShowcase) {
            return;
        }
        self::$installingShowcase = true;
        try {
            self::installShowcaseOnce();
        } finally {
            self::$installingShowcase = false;
        }
    }

    private static function installShowcaseOnce(): void
    {
        foreach (self::showcase() as $slug => $spec) {
            if (ApplicationStore::slugInUse($slug, 0)) {
                continue;
            }
            $meta = self::blueprints()[$spec['blueprint']];
            $record = (string) $meta['record'];
            if (self::itemTypeNameInUse($record)) {
                $record .= ' (' . $meta['label'] . ')';
            }
            $created = self::create(
                (string) $meta['label'],
                $slug,
                (string) $meta['hint'],
                (string) $meta['icon'],
                $spec['blueprint'],
                $record
            );
            Database::update('applications', ['sort_order' => $spec['sort']], 'application_id = ?', [(int) $created['application_id']]);
            self::seedShowcaseCards((int) $created['item_type_id'], $meta['fields'], $spec['cards']);
            $notice = $spec['notice'] ?? null;
            if (is_array($notice) && Database::tableExists('announcements')) {
                Database::insert('announcements', [
                    'time' => time(),
                    'subject' => (string) $notice['subject'],
                    'message' => (string) $notice['message'],
                    'type' => '',
                    'application' => $slug,
                ]);
            }
        }
    }

    /**
     * @return array<string, array{blueprint: string, sort: int, cards: list<array{title: string, fields: array<string, string>}>, notice?: array{subject: string, message: string}}>
     */
    private static function showcase(): array
    {
        return [
            'studio' => [
                'blueprint' => self::BLUEPRINT_STUDIO,
                'sort' => 1,
                'cards' => [
                    ['title' => 'Harbour chair', 'fields' => ['Stage' => 'Making', 'Client' => 'Northwind', 'Next step' => 'Oil the arms']],
                    ['title' => 'Lane house mark', 'fields' => ['Stage' => 'Sketch', 'Client' => 'Lane', 'Next step' => 'Send two directions']],
                    ['title' => 'Winter menu', 'fields' => ['Stage' => 'Review', 'Client' => 'Osteria', 'Next step' => 'Wait for their note']],
                    ['title' => 'Gate latch', 'fields' => ['Stage' => 'Enquiry', 'Client' => 'Holm', 'Next step' => 'Visit the site']],
                ],
            ],
            'matters' => [
                'blueprint' => self::BLUEPRINT_MATTERS,
                'sort' => 2,
                'cards' => [
                    ['title' => 'Roof brief', 'fields' => ['Waiting' => 'Waiting on us', 'Client' => 'Holm', 'Next step' => 'Revise the section']],
                    ['title' => 'Lease note', 'fields' => ['Waiting' => 'Waiting on them', 'Client' => 'Lane', 'Next step' => 'Their solicitor']],
                    ['title' => 'Sign-off', 'fields' => ['Waiting' => 'Done', 'Client' => 'Northwind', 'Next step' => 'File the drawings']],
                ],
            ],
            'house' => [
                'blueprint' => self::BLUEPRINT_HOUSE,
                'sort' => 3,
                'cards' => [
                    ['title' => 'Thursday sitting', 'fields' => ['When' => 'Tonight', 'Place' => 'Dining room']],
                    ['title' => 'Late gallery', 'fields' => ['When' => 'This week', 'Place' => 'West room']],
                    ['title' => 'Sunday lunch', 'fields' => ['When' => 'This week', 'Place' => 'Terrace']],
                ],
                'notice' => [
                    'subject' => 'Tonight',
                    'message' => 'Thursday sitting is in the dining room. Doors at six.',
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $fieldDefs
     * @param list<array{title: string, fields: array<string, string>}> $cards
     */
    private static function seedShowcaseCards(int $itemTypeId, array $fieldDefs, array $cards): void
    {
        $links = Database::select(
            'item_type_custom_fields',
            ['custom_field_id'],
            'item_type_id = ?',
            [$itemTypeId],
            'custom_field_order ASC'
        );
        $fieldIds = [];
        foreach ($fieldDefs as $index => $field) {
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '' || !isset($links[$index])) {
                continue;
            }
            $fieldIds[$name] = (int) $links[$index]['custom_field_id'];
        }
        $owner = self::showcaseOwnerId();
        $groups = GroupMembership::itemTypeGroupIds($itemTypeId);
        foreach ($cards as $card) {
            $itemId = Database::insert('items', [
                'create_date' => time(),
                'item_type_id' => $itemTypeId,
                'creator_security' => $owner,
                'user_security' => $owner,
                'item_title' => $card['title'],
            ]);
            $values = [];
            foreach ($card['fields'] as $name => $value) {
                $fieldId = $fieldIds[$name] ?? 0;
                if ($fieldId > 0) {
                    $values['custom_field_' . $fieldId] = $value;
                }
            }
            ItemFields::saveFromArray($itemId, $values);
            GroupMembership::setItemGroups($itemId, $groups);
        }
    }

    private static function showcaseOwnerId(): int
    {
        $row = Database::first('users', ['user_id'], 'user_name = ?', ['Administrator']);
        if ($row !== null) {
            return (int) $row['user_id'];
        }
        $row = Database::first('users', ['user_id'], '', [], 'user_id ASC');

        return (int) ($row['user_id'] ?? 0);
    }
}
