<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Permission;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Database\Database;
use Adlexone\Data\FieldTypes;

/**
 * Guided creation of a business application: record type, fields, and nav.
 */
final class ApplicationBuilder
{
    private static bool $installingShowcase = false;

    public const BLUEPRINT_BLANK = 'blank';
    public const BLUEPRINT_MATTERS = 'matters';
    public const BLUEPRINT_CONVEYANCING = 'conveyancing';
    public const BLUEPRINT_CLAIMS = 'claims';
    public const BLUEPRINT_LEAVE = 'leave';
    public const BLUEPRINT_COMPLAINTS = 'complaints';
    public const BLUEPRINT_COMMITTEE = 'committee';
    public const BLUEPRINT_AUDIT = 'audit';
    public const BLUEPRINT_INCIDENTS = 'incidents';
    public const BLUEPRINT_CANDIDATES = 'candidates';
    public const BLUEPRINT_CONTRACTS = 'contracts';
    public const BLUEPRINT_PUBLISHING = 'publishing';
    public static function blueprints(): array
    {
        return BlueprintCatalog::blueprints();
    }


    /**
     * @param list<string>|null $screens Built-in screen ids; null = blueprint default
     * @param list<int> $groupIds groups to receive app permissions (empty = admins + creator)
     * @param list<string> $permLevels use|settings
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
        array $permLevels = [],
        ?string $guide = null
    ): array {
        $name = trim($name);
        $slug = self::slugify($slug, $name);
        $hint = trim($hint);
        $recordTypeName = trim($recordTypeName);
        $blueprints = BlueprintCatalog::blueprints();

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
            $icon = 'ic-grid';
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
            $chosen = [ScreenCatalog::WORK, ScreenCatalog::CREATE, ScreenCatalog::SEARCH_ADVANCED, ScreenCatalog::SEARCH_LIST];
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
        $statusId = 0;
        $stepId = 0;
        $dueId = 0;
        $dueFallback = 0;
        $openStatuses = [];
        $statusValues = [];
        $waitStatuses = [];
        $closed = array_values(array_filter(array_map('strval', $blueprints[$blueprint]['closed'] ?? [])));
        foreach ($fieldDefs as $field) {
            $fieldName = trim((string) ($field['name'] ?? ''));
            $fieldId = (int) ($fieldIdsByName[$fieldName] ?? 0);
            $plain = self::plainName($fieldName);
            if ($plain === 'status') {
                $statusId = $fieldId;
                $statusValues = array_values(array_filter(array_map('strval', $field['values'] ?? [])));
                $waitStatuses = array_values(array_filter(array_map('strval', $field['waits'] ?? [])));
                $openStatuses = $statusValues;
                if ($closed !== []) {
                    $openStatuses = array_values(array_filter(
                        $openStatuses,
                        static fn (string $value): bool => !in_array($value, $closed, true)
                    ));
                } elseif (count($openStatuses) > 1) {
                    array_pop($openStatuses);
                }
            } elseif ($plain === 'next step') {
                $stepId = $fieldId;
            } elseif ($dueId === 0 && str_contains($plain, 'due')) {
                $dueId = $fieldId;
            } elseif (
                $dueFallback === 0
                && (string) ($field['type'] ?? '') === FieldTypes::DATE
                && !str_ends_with($plain, ' on')
            ) {
                $dueFallback = $fieldId;
            }
        }
        if ($dueId === 0) {
            $dueId = $dueFallback;
        }
        $applicationSettings = WorkflowFields::store([
            'default_item_type_id' => (string) $itemTypeId,
            'search_tab_added' => '1',
        ], $itemTypeId, $statusId, $stepId, $dueId, $openStatuses, $statusValues, $waitStatuses);
        $applicationId = ApplicationStore::insertApplication([
            'slug' => $slug,
            'name' => $name,
            'hint' => $hint !== '' ? $hint : $blueprints[$blueprint]['hint'],
            'guide' => $guide === null ? trim((string) ($blueprints[$blueprint]['guide'] ?? '')) : trim($guide),
            'icon' => $icon,
            'permission' => $usePerm,
            'enabled' => 1,
            'sort_order' => $max + 10,
            'entry_mode' => 'shell',
            'settings_json' => json_encode($applicationSettings, JSON_UNESCAPED_SLASHES),
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
            ScreenCatalog::WORK => 'ic-work',
            ScreenCatalog::CREATE => 'ic-plus',
            ScreenCatalog::SEARCH_ADVANCED => 'ic-search',
            ScreenCatalog::SEARCH_LIST => 'ic-saved',
            ScreenCatalog::ANNOUNCEMENTS => 'ic-announce',
            ScreenCatalog::SETTINGS => 'ic-sliders',
            ScreenCatalog::COUNTS => 'ic-counts',
            ScreenCatalog::OWNERS => 'ic-owners',
            ScreenCatalog::STEPS => 'ic-steps',
            ScreenCatalog::DIARY => 'ic-diary',
            ScreenCatalog::PARTIES => 'ic-parties',
            ScreenCatalog::ACTIVITY => 'ic-activity',
            ScreenCatalog::FILES => 'ic-files',
            ScreenCatalog::AGE => 'ic-ageing',
            ScreenCatalog::REGISTER => 'ic-register',
            ScreenCatalog::INTAKE => 'ic-intake',
        ];
        $order = 10;
        foreach ($chosen as $capability) {
            $config = in_array($capability, [
                ScreenCatalog::WORK,
                ScreenCatalog::CREATE,
                ScreenCatalog::SEARCH_ADVANCED,
                ScreenCatalog::COUNTS,
                ScreenCatalog::OWNERS,
                ScreenCatalog::STEPS,
                ScreenCatalog::DIARY,
                ScreenCatalog::PARTIES,
                ScreenCatalog::ACTIVITY,
                ScreenCatalog::FILES,
                ScreenCatalog::AGE,
                ScreenCatalog::REGISTER,
                ScreenCatalog::INTAKE,
            ], true)
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

        self::seedRules($applicationId, $itemTypeId, $blueprints[$blueprint]['rules'] ?? [], $fieldIdsByName);
        self::seedSearch($slug, $itemTypeId, $blueprints[$blueprint]['search'] ?? null);

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
        unset($appName);
        self::ensureSharedColumn();
        $ids = [];
        foreach ($fields as $field) {
            $desired = trim((string) ($field['name'] ?? ''));
            if ($desired === '') {
                continue;
            }
            $type = FieldTypes::normalise((string) ($field['type'] ?? FieldTypes::TEXT_BOX));
            $sharedId = self::sharedFieldId($desired, $type);
            $values = $field['values'] ?? [];
            if ($sharedId > 0) {
                if ($type === FieldTypes::MENU && is_array($values)) {
                    self::ensureMenuValues($sharedId, $values);
                }
                $ids[] = $sharedId;
                continue;
            }
            $row = [
                'custom_field_name' => $desired,
                'field_type' => $type,
                'default_value' => '',
                'sub_menu' => 0,
                'enabled' => 'Yes',
                'field_reference' => 0,
                'data' => '',
                'validation_type' => FieldTypes::impliedValidation($type),
                'required' => 'No',
            ];
            if (self::isSharedDefinition($desired, $type) && Database::columnExists('custom_fields', 'shared')) {
                $row['shared'] = 1;
            }
            $id = Database::insert('custom_fields', $row);
            Database::update('custom_fields', ['field_reference' => $id], 'custom_field_id = ?', [$id]);

            if ($type === FieldTypes::MENU && is_array($values)) {
                self::ensureMenuValues($id, $values);
            }
            $ids[] = $id;
        }
        return $ids;
    }

    private static function ensureSharedColumn(): void
    {
        if (Database::tableExists('custom_fields') && !Database::columnExists('custom_fields', 'shared')) {
            Database::exec('ALTER TABLE custom_fields ADD COLUMN shared INTEGER NOT NULL DEFAULT 0');
        }
    }

    private static function isSharedDefinition(string $name, string $type): bool
    {
        return match (self::plainName($name)) {
            'status' => $type === FieldTypes::MENU,
            'next step' => $type === FieldTypes::TEXT_BOX,
            'note' => $type === FieldTypes::TEXT_AREA,
            default => false,
        };
    }

    private static function sharedFieldId(string $name, string $type): int
    {
        if (!self::isSharedDefinition($name, $type) || !Database::columnExists('custom_fields', 'shared')) {
            return 0;
        }
        $row = Database::first(
            'custom_fields',
            ['custom_field_id'],
            'shared = 1 AND field_type = ? AND LOWER(custom_field_name) = LOWER(?)',
            [$type, $name],
            'custom_field_id ASC'
        );

        return (int) ($row['custom_field_id'] ?? 0);
    }

    /**
     * @param list<mixed> $values
     */
    private static function ensureMenuValues(int $fieldId, array $values): void
    {
        if ($fieldId <= 0) {
            return;
        }
        $have = [];
        foreach (Database::select('custom_field_menu_values', ['menu_value'], 'custom_field_id = ?', [$fieldId]) as $row) {
            $have[(string) ($row['menu_value'] ?? '')] = true;
        }
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '' || isset($have[$value])) {
                continue;
            }
            Database::insert('custom_field_menu_values', [
                'custom_field_id' => $fieldId,
                'menu_value' => $value,
                'parent_menu_value_id' => 0,
            ]);
            $have[$value] = true;
        }
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
     * Drop every application currently installed. Used once, by the schema
     * migration, before the case-file applications are installed.
     */
    public static function removeInstalledApplications(): void
    {
        if (!Database::tableExists('applications')) {
            return;
        }
        $slugs = [];
        foreach (Database::select('applications', ['slug']) as $row) {
            $slugs[] = (string) $row['slug'];
        }
        foreach ($slugs as $slug) {
            self::dropSlug($slug);
        }
        self::deleteUnlinkedFields();
    }

    /**
     * The case-file applications, with work already on the board.
     * An application is left alone once its mark field is present.
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

    /**
     * Publishing, with (Example) in the title, and the sample manuscripts.
     */
    public static function installExemplar(): void
    {
        if (self::$installingShowcase) {
            return;
        }
        self::$installingShowcase = true;
        try {
            $spec = self::showcase()['publishing'] ?? null;
            if (is_array($spec)) {
                self::installOne('publishing', $spec, BlueprintCatalog::blueprints(), 'Publishing (Example)');
            }
        } finally {
            self::$installingShowcase = false;
        }
    }

    /**
     * Record the statuses that start a wait on showcase applications already installed.
     * A later edit of that list is left in place.
     */
    public static function noteWaits(): void
    {
        if (!Database::tableExists('applications') || !Database::columnExists('applications', 'settings_json')) {
            return;
        }
        $blueprints = BlueprintCatalog::blueprints();
        foreach (self::showcase() as $slug => $spec) {
            $meta = $blueprints[$spec['blueprint']] ?? null;
            if (!is_array($meta)) {
                continue;
            }
            $waits = [];
            foreach ($meta['fields'] as $field) {
                if (($field['name'] ?? '') === 'Status') {
                    $waits = array_values(array_filter(array_map('strval', $field['waits'] ?? [])));
                }
            }
            $row = Database::first('applications', ['settings_json'], 'slug = ?', [$slug]);
            if ($row === null) {
                continue;
            }
            $settings = json_decode((string) ($row['settings_json'] ?? ''), true);
            if (!is_array($settings)) {
                $settings = [];
            }
            $typeId = (int) ($settings['default_item_type_id'] ?? 0);
            if ($typeId <= 0) {
                continue;
            }
            $workflow = is_array($settings['workflow_fields'] ?? null) ? $settings['workflow_fields'] : [];
            $block = $workflow[(string) $typeId] ?? [];
            $block = is_array($block) ? $block : [];
            if (array_key_exists('wait_statuses', $block)) {
                continue;
            }
            $block['wait_statuses'] = $waits;
            $workflow[(string) $typeId] = $block;
            $settings['workflow_fields'] = $workflow;
            Database::update(
                'applications',
                ['settings_json' => json_encode($settings, JSON_UNESCAPED_SLASHES) ?: '{}'],
                'slug = ?',
                [$slug]
            );
        }
    }

    private static function installShowcaseOnce(): void
    {
        self::dropSlug('studio');
        self::dropSlug('house');
        if (
            self::slugExists('repairs')
            && !self::recordTypeWasChosen('repairs')
            && self::applicationHasMark('repairs', 'Booked for')
        ) {
            self::dropSlug('repairs');
        }
        self::deleteUnlinkedFields();
        $blueprints = BlueprintCatalog::blueprints();
        foreach (self::showcase() as $slug => $spec) {
            $mark = (string) ($blueprints[$spec['blueprint']]['mark'] ?? '');
            if (
                self::slugExists($slug)
                && $mark !== ''
                && !self::recordTypeWasChosen($slug)
                && !self::applicationHasMark($slug, $mark)
            ) {
                self::dropSlug($slug);
            }
        }
        self::deleteUnlinkedFields();
        foreach (self::showcase() as $slug => $spec) {
            self::installOne($slug, $spec, $blueprints, null);
        }
    }

    /**
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $blueprints
     */
    private static function installOne(string $slug, array $spec, array $blueprints, ?string $name): void
    {
        if (self::slugExists($slug) || !isset($blueprints[$spec['blueprint']])) {
            return;
        }
        $meta = $blueprints[$spec['blueprint']];
        $record = (string) $meta['record'];
        if (self::itemTypeNameInUse($record)) {
            $record .= ' (' . $meta['label'] . ')';
        }
        $created = self::create(
            $name ?? (string) $meta['label'],
            $slug,
            (string) $meta['hint'],
            (string) $meta['icon'],
            (string) $spec['blueprint'],
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

    /**
     * Bring showcase applications already on a site up to the current blueprints:
     * the screens the work uses, the guide, and the field labels.
     * Runs once, from the schema migration. A later edit on that site is left in place.
     */
    public static function alignInstalled(): void
    {
        if (!Database::tableExists('applications')) {
            return;
        }
        $blueprints = BlueprintCatalog::blueprints();
        foreach (self::showcase() as $slug => $spec) {
            $blueprint = (string) $spec['blueprint'];
            if (!isset($blueprints[$blueprint])) {
                continue;
            }
            self::alignOne($slug, $blueprints[$blueprint]);
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function alignOne(string $slug, array $meta): void
    {
        $app = Database::first('applications', ['application_id', 'settings_json'], 'slug = ?', [$slug]);
        if ($app === null) {
            return;
        }
        $applicationId = (int) $app['application_id'];
        $settings = json_decode((string) ($app['settings_json'] ?? ''), true);
        if (!is_array($settings)) {
            $settings = [];
        }
        $typeId = (int) ($settings['default_item_type_id'] ?? 0);
        Database::update('applications', [
            'guide' => trim((string) ($meta['guide'] ?? '')),
        ], 'application_id = ?', [$applicationId]);
        if ($typeId <= 0) {
            return;
        }
        self::canonicalFieldNames($typeId, $meta['fields'] ?? []);
        self::alignNavigation($applicationId, $typeId, $meta['screens'] ?? []);
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    private static function canonicalFieldNames(int $typeId, array $fields): void
    {
        foreach ($fields as $field) {
            $canonical = trim((string) ($field['name'] ?? ''));
            if ($canonical === '') {
                continue;
            }
            $id = self::fieldIdOnType($typeId, $canonical);
            if ($id <= 0) {
                continue;
            }
            $row = Database::first('custom_fields', ['custom_field_name'], 'custom_field_id = ?', [$id]);
            if ($row !== null && (string) $row['custom_field_name'] !== $canonical) {
                Database::update('custom_fields', ['custom_field_name' => $canonical], 'custom_field_id = ?', [$id]);
            }
        }
    }

    /**
     * @param list<string> $screens
     */
    private static function alignNavigation(int $applicationId, int $typeId, array $screens): void
    {
        if (!Database::tableExists('application_nav') || $screens === []) {
            return;
        }
        $labels = ScreenCatalog::builderScreens();
        $known = array_keys($labels);
        $navs = Database::select('application_nav', '*', 'application_id = ?', [$applicationId], 'sort_order ASC, nav_id ASC');
        $keep = [];
        foreach ($navs as $nav) {
            $capability = ScreenCatalog::resolve((string) $nav['capability']);
            if (!in_array($capability, $known, true)) {
                continue;
            }
            if (!in_array($capability, $screens, true) || isset($keep[$capability])) {
                Database::delete('application_nav', 'nav_id = ?', [(int) $nav['nav_id']]);
                continue;
            }
            $keep[$capability] = $nav;
        }
        $order = 10;
        $typeConfig = json_encode(['item_type_id' => (string) $typeId], JSON_UNESCAPED_SLASHES) ?: '{}';
        foreach ($screens as $capability) {
            if (!is_string($capability) || !isset($labels[$capability])) {
                continue;
            }
            if (isset($keep[$capability])) {
                Database::update('application_nav', [
                    'label' => $labels[$capability],
                    'sort_order' => $order,
                    'icon' => self::screenIcon($capability),
                ], 'nav_id = ?', [(int) $keep[$capability]['nav_id']]);
            } else {
                Database::insert('application_nav', [
                    'application_id' => $applicationId,
                    'label' => $labels[$capability],
                    'capability' => $capability,
                    'permission' => '',
                    'icon' => self::screenIcon($capability),
                    'config_json' => self::screenNeedsType($capability) ? $typeConfig : '{}',
                    'sort_order' => $order,
                ]);
            }
            $order += 10;
        }
    }

    private static function screenNeedsType(string $capability): bool
    {
        return in_array($capability, [
            ScreenCatalog::WORK,
            ScreenCatalog::CREATE,
            ScreenCatalog::SEARCH_ADVANCED,
            ScreenCatalog::DIARY,
            ScreenCatalog::PARTIES,
            ScreenCatalog::INTAKE,
        ], true);
    }

    private static function screenIcon(string $capability): string
    {
        return [
            ScreenCatalog::WORK => 'ic-work',
            ScreenCatalog::CREATE => 'ic-plus',
            ScreenCatalog::SEARCH_ADVANCED => 'ic-search',
            ScreenCatalog::SEARCH_LIST => 'ic-saved',
            ScreenCatalog::ANNOUNCEMENTS => 'ic-announce',
            ScreenCatalog::DIARY => 'ic-diary',
            ScreenCatalog::PARTIES => 'ic-parties',
            ScreenCatalog::INTAKE => 'ic-intake',
        ][$capability] ?? '';
    }
    private static function showcase(): array
    {
        return BlueprintCatalog::showcase();
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
        $types = [];
        foreach ($fieldDefs as $index => $field) {
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '' || !isset($links[$index])) {
                continue;
            }
            $fieldIds[$name] = (int) $links[$index]['custom_field_id'];
            $types[$name] = FieldTypes::normalise((string) ($field['type'] ?? ''));
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
                if ($fieldId <= 0) {
                    continue;
                }
                if (($types[$name] ?? '') === FieldTypes::CHECK_BOX && strcasecmp($value, 'Yes') === 0) {
                    $value = (string) $fieldId;
                }
                $values['custom_field_' . $fieldId] = $value;
            }
            ItemFields::saveFromArray($itemId, $values);
            GroupMembership::setItemGroups($itemId, $groups);
        }
    }

    private static function showcaseOwnerId(): int
    {
        $row = Database::first('users', ['user_id'], 'user_name = ?', [\Adlexone\Platform\DefaultAccount::USERNAME]);
        if ($row === null) {
            $row = Database::first('users', ['user_id'], 'user_name = ?', ['Administrator']);
        }
        if ($row !== null) {
            return (int) $row['user_id'];
        }
        $row = Database::first('users', ['user_id'], '', [], 'user_id ASC');

        return (int) ($row['user_id'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function check(string $name): array
    {
        return ['name' => $name, 'type' => FieldTypes::CHECK_BOX];
    }

    /**
     * @param list<array<string, mixed>> $rules
     * @param array<string, int> $fieldIdsByName
     */
    private static function seedRules(int $applicationId, int $itemTypeId, array $rules, array $fieldIdsByName): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $fieldName = (string) ($rule['field'] ?? '');
            $fieldId = $fieldName === '' ? 0 : (int) ($fieldIdsByName[$fieldName] ?? 0);
            $operator = (string) ($rule['operator'] ?? Rules::OP_ALWAYS);
            if ($operator !== Rules::OP_ALWAYS && $fieldId <= 0) {
                continue;
            }
            $steps = [];
            foreach ($rule['steps'] ?? [] as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $outcome = (string) ($step['outcome'] ?? '');
                $target = (string) ($step['field'] ?? '');
                $actionField = '';
                if ($target !== '') {
                    $targetId = (int) ($fieldIdsByName[$target] ?? 0);
                    if ($targetId <= 0) {
                        continue;
                    }
                    $actionField = 'custom_field_' . $targetId;
                } elseif ($outcome === Rules::OUTCOME_STAMP || $outcome === Rules::OUTCOME_SET_FIELD) {
                    continue;
                }
                $steps[] = [
                    'outcome' => $outcome,
                    'recipient' => (string) ($step['recipient'] ?? Rules::TO_OWNER),
                    'subject' => (string) ($step['subject'] ?? ''),
                    'body' => (string) ($step['body'] ?? ''),
                    'action_field' => $actionField,
                    'action_value' => (string) ($step['value'] ?? $step['owner'] ?? ''),
                ];
            }
            if ($steps === []) {
                continue;
            }
            Rules::add($applicationId, [
                'item_type_id' => $itemTypeId,
                'name' => (string) ($rule['name'] ?? ''),
                'event' => (string) ($rule['event'] ?? Rules::EVENT_UPDATE),
                'field_key' => $fieldId > 0 ? 'custom_field_' . $fieldId : '',
                'operator' => $operator,
                'match_value' => (string) ($rule['match'] ?? ''),
                'steps' => $steps,
            ]);
        }
    }

    /**
     * @param array{name?: string, description?: string, field?: string, value?: string}|null $search
     */
    private static function seedSearch(string $slug, int $typeId, ?array $search): void
    {
        if ($search === null || $slug === '' || $typeId <= 0 || !Database::tableExists('saved_searches')) {
            return;
        }
        $name = trim((string) ($search['name'] ?? ''));
        $value = trim((string) ($search['value'] ?? ''));
        if ($name === '' || $value === '') {
            return;
        }
        if (Database::first('saved_searches', ['search_id'], 'application = ? AND search_name = ?', [$slug, $name]) !== null) {
            return;
        }
        $fieldId = self::fieldIdOnType($typeId, (string) ($search['field'] ?? 'Status'));
        if ($fieldId <= 0) {
            return;
        }
        $criteria = [
            'version' => 1,
            'filters' => [
                ['field' => 'item_type_id', 'op' => '=', 'value' => (string) $typeId, 'andor' => 'AND'],
                ['field' => 'custom_field_' . $fieldId, 'op' => '=', 'value' => $value, 'andor' => 'AND'],
            ],
            'display' => [],
        ];
        Database::insert('saved_searches', [
            'user' => 'all',
            'search_name' => $name,
            'search_description' => (string) ($search['description'] ?? ''),
            'application' => $slug,
            'criteria_json' => json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    private static function fieldIdOnType(int $typeId, string $name): int
    {
        $want = self::plainName($name);
        foreach (Database::select('item_type_custom_fields', ['custom_field_id'], 'item_type_id = ?', [$typeId], 'custom_field_order ASC') as $link) {
            $field = Database::first('custom_fields', ['custom_field_name'], 'custom_field_id = ?', [(int) $link['custom_field_id']]);
            if ($field !== null && self::plainName((string) $field['custom_field_name']) === $want) {
                return (int) $link['custom_field_id'];
            }
        }

        return 0;
    }

    private static function slugExists(string $slug): bool
    {
        return $slug !== '' && Database::first('applications', ['application_id'], 'slug = ?', [$slug]) !== null;
    }

    /**
     * The details page has chosen this application's record type.
     * The showcase installer leaves that choice in place.
     */
    private static function recordTypeWasChosen(string $slug): bool
    {
        $row = Database::first('applications', ['settings_json'], 'slug = ?', [$slug]);
        if ($row === null) {
            return false;
        }
        $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);

        return is_array($settings) && (string) ($settings['record_type_chosen'] ?? '') === '1';
    }

    private static function applicationHasMark(string $slug, string $mark): bool
    {
        $row = Database::first('applications', ['settings_json'], 'slug = ?', [$slug]);
        if ($row === null) {
            return false;
        }
        $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);
        $typeId = is_array($settings) ? (int) ($settings['default_item_type_id'] ?? 0) : 0;

        return $typeId > 0 && self::fieldIdOnType($typeId, $mark) > 0;
    }

    private static function plainName(string $name): string
    {
        $name = strtolower(trim($name));
        $previous = null;
        while ($name !== $previous) {
            $previous = $name;
            $name = trim((string) preg_replace('/\s+\d+$/', '', $name));
            $name = trim((string) preg_replace('/\s*\([^)]*\)$/', '', $name));
        }

        return $name;
    }

    private static function dropSlug(string $slug): void
    {
        if ($slug === '' || !Database::tableExists('applications')) {
            return;
        }
        $app = Database::first('applications', ['application_id', 'settings_json'], 'slug = ?', [$slug]);
        if ($app === null) {
            return;
        }
        $applicationId = (int) $app['application_id'];
        $typeIds = [];
        $settings = json_decode((string) ($app['settings_json'] ?? '{}'), true);
        $defaultType = is_array($settings) ? (int) ($settings['default_item_type_id'] ?? 0) : 0;
        if ($defaultType > 0) {
            $typeIds[] = $defaultType;
        }
        if (Database::tableExists('application_nav')) {
            foreach (Database::select('application_nav', ['config_json'], 'application_id = ?', [$applicationId]) as $nav) {
                $config = json_decode((string) ($nav['config_json'] ?? '{}'), true);
                $typeId = is_array($config) ? (int) ($config['item_type_id'] ?? 0) : 0;
                if ($typeId > 0) {
                    $typeIds[] = $typeId;
                }
            }
        }
        $typeIds = array_values(array_unique($typeIds));
        $fieldIds = [];
        foreach ($typeIds as $typeId) {
            if (self::typeIsShared($typeId, $applicationId)) {
                continue;
            }
            foreach (Database::select('item_type_custom_fields', ['custom_field_id'], 'item_type_id = ?', [$typeId]) as $link) {
                $fieldIds[] = (int) $link['custom_field_id'];
            }
            self::deleteRecordsOfType($typeId);
        }
        if (class_exists(Permission::class)) {
            Permission::revokeApp($slug);
        }
        if (Database::tableExists('saved_searches')) {
            Database::delete('saved_searches', 'application = ?', [$slug]);
        }
        if (Database::tableExists('announcements')) {
            Database::delete('announcements', 'application = ?', [$slug]);
        }
        if (Database::tableExists('users')) {
            Database::run(
                'UPDATE users SET home_controller = ? WHERE home_controller = ?',
                ['', 'application:' . $slug]
            );
        }
        Rules::deleteForApplication($applicationId);
        Database::delete('application_nav', 'application_id = ?', [$applicationId]);
        Database::delete('applications', 'application_id = ?', [$applicationId]);
        foreach ($typeIds as $typeId) {
            if (self::typeIsShared($typeId, $applicationId) || Database::exists('items', 'item_type_id = ?', [$typeId])) {
                continue;
            }
            Database::delete('item_type_custom_fields', 'item_type_id = ?', [$typeId]);
            Database::delete('item_type_groups', 'item_type_id = ?', [$typeId]);
            if (Database::tableExists('action_definitions')) {
                Database::delete('action_definitions', 'item_type_id = ?', [$typeId]);
            }
            Database::delete('item_types', 'item_type_id = ?', [$typeId]);
        }
        foreach (array_unique($fieldIds) as $fieldId) {
            if ($fieldId <= 0 || Database::exists('item_type_custom_fields', 'custom_field_id = ?', [$fieldId])) {
                continue;
            }
            Database::delete('item_field_values', 'custom_field_id = ?', [$fieldId]);
            if (Database::tableExists('custom_field_menu_values')) {
                Database::delete('custom_field_menu_values', 'custom_field_id = ?', [$fieldId]);
            }
            Database::delete('custom_fields', 'custom_field_id = ?', [$fieldId]);
        }
    }

    private static function typeIsShared(int $typeId, int $exceptApplicationId): bool
    {
        if ($typeId <= 0 || !Database::tableExists('applications')) {
            return false;
        }
        foreach (Database::select('applications', ['application_id', 'settings_json']) as $app) {
            if ((int) $app['application_id'] === $exceptApplicationId) {
                continue;
            }
            $settings = json_decode((string) ($app['settings_json'] ?? '{}'), true);
            if (is_array($settings) && (int) ($settings['default_item_type_id'] ?? 0) === $typeId) {
                return true;
            }
        }
        if (!Database::tableExists('application_nav')) {
            return false;
        }
        foreach (Database::select('application_nav', ['application_id', 'config_json'], 'application_id <> ?', [$exceptApplicationId]) as $nav) {
            $config = json_decode((string) ($nav['config_json'] ?? '{}'), true);
            if (is_array($config) && (int) ($config['item_type_id'] ?? 0) === $typeId) {
                return true;
            }
        }

        return false;
    }

    private static function deleteUnlinkedFields(): void
    {
        if (!Database::tableExists('custom_fields') || !Database::tableExists('item_type_custom_fields')) {
            return;
        }
        foreach (Database::select('custom_fields', ['custom_field_id']) as $field) {
            $fieldId = (int) $field['custom_field_id'];
            if ($fieldId <= 0 || Database::exists('item_type_custom_fields', 'custom_field_id = ?', [$fieldId])) {
                continue;
            }
            Database::delete('item_field_values', 'custom_field_id = ?', [$fieldId]);
            if (Database::tableExists('custom_field_menu_values')) {
                Database::delete('custom_field_menu_values', 'custom_field_id = ?', [$fieldId]);
            }
            Database::delete('custom_fields', 'custom_field_id = ?', [$fieldId]);
        }
    }

    private static function deleteRecordsOfType(int $typeId): void
    {
        if ($typeId <= 0) {
            return;
        }
        $ids = [];
        foreach (Database::select('items', ['item_id'], 'item_type_id = ?', [$typeId]) as $row) {
            $ids[] = (int) $row['item_id'];
        }
        foreach ($ids as $id) {
            Database::delete('item_field_values', 'item_id = ?', [$id]);
            Database::delete('item_groups', 'item_id = ?', [$id]);
            Database::delete('core_log', 'item_id = ?', [$id]);
            if (Database::tableExists('item_attachments')) {
                Database::delete('item_attachments', 'item_id = ?', [$id]);
            }
            if (Database::tableExists('system_log')) {
                Database::delete('system_log', 'item_id = ?', [$id]);
            }
            if (Database::tableExists('application_rule_runs')) {
                Database::delete('application_rule_runs', 'item_id = ?', [$id]);
            }
            Database::delete('items', 'item_id = ?', [$id]);
        }
    }
}
