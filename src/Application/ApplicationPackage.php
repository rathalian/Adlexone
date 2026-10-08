<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Data\GroupMembership;
use Adlexone\FrameOne\AppSettings;
use Adlexone\support\Database;
use Adlexone\support\FieldTypes;

/**
 * Export / import a business application as a portable JSON solution package.
 */
final class ApplicationPackage
{
    public const FORMAT = 'inlay.solution';
    public const VERSION = 3;

    /**
     * @return array<string, mixed>
     */
    public static function export(int $applicationId): array
    {
        $app = ApplicationStore::find($applicationId);
        if ($app === null) {
            throw new \InvalidArgumentException('Application not found.');
        }

        $nav = ApplicationStore::navigation($applicationId);
        $typeIds = [];
        $defaultType = trim((string) (($app['settings']['default_item_type_id'] ?? '')));
        if ($defaultType !== '' && ctype_digit($defaultType)) {
            $typeIds[] = (int) $defaultType;
        }
        foreach ($nav as $link) {
            $tid = trim((string) ($link['config']['item_type_id'] ?? ''));
            if ($tid !== '' && ctype_digit($tid)) {
                $typeIds[] = (int) $tid;
            }
        }
        $typeIds = array_values(array_unique($typeIds));

        $types = [];
        $fieldsById = [];
        foreach ($typeIds as $typeId) {
            $type = Database::first('item_types', '*', 'item_type_id = ?', [$typeId]);
            if ($type === null) {
                continue;
            }
            $links = Database::select(
                'item_type_custom_fields',
                '*',
                'item_type_id = ?',
                [$typeId],
                'custom_field_order ASC'
            );
            $fieldRefs = [];
            foreach ($links as $link) {
                $fid = (int) $link['custom_field_id'];
                $fieldRefs[] = [
                    'custom_field_id' => $fid,
                    'order' => (int) $link['custom_field_order'],
                ];
                if (!isset($fieldsById[$fid])) {
                    $field = Database::first('custom_fields', '*', 'custom_field_id = ?', [$fid]);
                    if ($field === null) {
                        continue;
                    }
                    $menus = Database::select(
                        'custom_field_menu_values',
                        ['menu_value', 'parent_menu_value_id'],
                        'custom_field_id = ?',
                        [$fid],
                        'menu_value_id ASC'
                    );
                    $fieldsById[$fid] = [
                        'name' => (string) $field['custom_field_name'],
                        'field_type' => FieldTypes::normalise((string) $field['field_type']),
                        'required' => (string) ($field['required'] ?? 'No'),
                        'enabled' => (string) ($field['enabled'] ?? 'Yes'),
                        'default_value' => (string) ($field['default_value'] ?? ''),
                        'menu_values' => array_values(array_map(
                            static fn (array $m): string => (string) $m['menu_value'],
                            array_filter($menus, static fn (array $m): bool => (int) $m['parent_menu_value_id'] === 0)
                        )),
                    ];
                }
            }
            $groupNames = [];
            foreach (GroupMembership::itemTypeGroupIds($typeId) as $gid) {
                $group = Database::first('groups', ['group_name'], 'group_id = ?', [$gid]);
                if ($group !== null) {
                    $groupNames[] = (string) $group['group_name'];
                }
            }
            $types[] = [
                'source_id' => $typeId,
                'name' => (string) $type['item_type_name'],
                'enabled' => (string) ($type['enabled'] ?? 'Yes'),
                'fields' => $fieldRefs,
                'groups' => $groupNames,
            ];
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => gmdate('c'),
            'application' => [
                'slug' => (string) $app['slug'],
                'name' => (string) $app['name'],
                'hint' => (string) $app['hint'],
                'icon' => (string) $app['icon'],
                'settings' => AppSettings::get((string) $app['slug']),
            ],
            'access' => Permission::exportAppGrants((string) $app['slug']),
            'navigation' => array_map(static fn (array $link): array => [
                'label' => (string) $link['label'],
                'capability' => (string) $link['capability'],
                'icon' => (string) $link['icon'],
                'config' => $link['config'],
            ], $nav),
            'item_types' => $types,
            'fields' => $fieldsById,
            'saved_searches' => self::exportSavedSearches((string) $app['slug']),
            'actions' => self::exportActions($typeIds),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function exportSavedSearches(string $slug): array
    {
        if (!Database::tableExists('saved_searches')) {
            return [];
        }
        $out = [];
        foreach (Database::select('saved_searches', '*', 'application = ?', [$slug], 'search_name ASC') as $row) {
            $out[] = [
                'search_name' => (string) $row['search_name'],
                'search_description' => (string) ($row['search_description'] ?? ''),
                'user' => (string) ($row['user'] ?? 'system'),
                'saved_search_sql' => (string) ($row['saved_search_sql'] ?? ''),
                'criteria_json' => (string) ($row['criteria_json'] ?? '{}'),
            ];
        }
        return $out;
    }

    /**
     * @param list<int> $typeIds
     * @return list<array<string, mixed>>
     */
    private static function exportActions(array $typeIds): array
    {
        if ($typeIds === [] || !Database::tableExists('action_definitions')) {
            return [];
        }
        $out = [];
        foreach ($typeIds as $typeId) {
            foreach (Database::select('action_definitions', '*', 'item_type_id = ?', [$typeId], 'action_id ASC') as $row) {
                $out[] = [
                    'action_name' => (string) ($row['action_name'] ?? ''),
                    'action_condition_pre' => (string) ($row['action_condition_pre'] ?? ''),
                    'action_condition_post' => (string) ($row['action_condition_post'] ?? ''),
                    'action_data' => (string) ($row['action_data'] ?? ''),
                    'action_parameters' => (string) ($row['action_parameters'] ?? ''),
                    'action_type' => (string) ($row['action_type'] ?? ''),
                    'package_file' => (string) ($row['package_file'] ?? ''),
                    'package_function' => (string) ($row['package_function'] ?? ''),
                    'enabled' => (string) ($row['enabled'] ?? 'Yes'),
                    'item_type_name' => (string) (Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [$typeId])['item_type_name'] ?? ''),
                    'source_item_type_id' => $typeId,
                ];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $package
     * @return array{application_id: int, slug: string}
     */
    public static function import(array $package, string $slugOverride = ''): array
    {
        if (($package['format'] ?? '') !== self::FORMAT) {
            throw new \InvalidArgumentException('Not an Inlay solution package.');
        }
        $appMeta = $package['application'] ?? null;
        if (!is_array($appMeta)) {
            throw new \InvalidArgumentException('Package is missing application data.');
        }
        $name = trim((string) ($appMeta['name'] ?? ''));
        $slug = ApplicationBuilder::slugify(
            $slugOverride !== '' ? $slugOverride : (string) ($appMeta['slug'] ?? ''),
            $name
        );
        if ($name === '' || $slug === '') {
            throw new \InvalidArgumentException('Package application name/slug is invalid.');
        }
        if (ApplicationStore::slugInUse($slug, 0)) {
            throw new \InvalidArgumentException('That slug is already used. Choose another.');
        }

        $fieldsPackage = is_array($package['fields'] ?? null) ? $package['fields'] : [];
        $oldToNewField = [];
        foreach ($fieldsPackage as $oldId => $field) {
            if (!is_array($field)) {
                continue;
            }
            $fieldName = (string) ($field['name'] ?? '');
            if ($fieldName === '') {
                continue;
            }
            $existing = Database::first('custom_fields', ['custom_field_id'], 'custom_field_name = ?', [$fieldName]);
            if ($existing !== null) {
                $oldToNewField[(int) $oldId] = (int) $existing['custom_field_id'];
                continue;
            }
            $type = FieldTypes::normalise((string) ($field['field_type'] ?? FieldTypes::TEXT_BOX));
            $newId = Database::insert('custom_fields', [
                'custom_field_name' => $fieldName,
                'field_type' => $type,
                'default_value' => (string) ($field['default_value'] ?? ''),
                'sub_menu' => 0,
                'enabled' => (string) ($field['enabled'] ?? 'Yes'),
                'field_reference' => 0,
                'data' => '',
                'validation_type' => '',
                'required' => (string) ($field['required'] ?? 'No'),
            ]);
            Database::update('custom_fields', ['field_reference' => $newId], 'custom_field_id = ?', [$newId]);
            foreach ($field['menu_values'] ?? [] as $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                Database::insert('custom_field_menu_values', [
                    'custom_field_id' => $newId,
                    'menu_value' => $value,
                    'parent_menu_value_id' => 0,
                ]);
            }
            $oldToNewField[(int) $oldId] = $newId;
        }

        /** @var array<int, int> $oldToNewType source_id => new id */
        $oldToNewType = [];
        /** @var array<string, int> $typeNameToId */
        $typeNameToId = [];
        $firstTypeId = 0;
        foreach ($package['item_types'] ?? [] as $type) {
            if (!is_array($type)) {
                continue;
            }
            $originalTypeName = trim((string) ($type['name'] ?? ''));
            if ($originalTypeName === '') {
                continue;
            }
            $sourceId = (int) ($type['source_id'] ?? 0);
            $typeName = $originalTypeName;
            if (Database::first('item_types', ['item_type_id'], 'item_type_name = ?', [$typeName]) !== null) {
                $typeName .= ' (' . $name . ')';
            }
            $typeId = Database::insert('item_types', [
                'item_type_name' => $typeName,
                'user_security' => '',
                'enabled' => (string) ($type['enabled'] ?? 'Yes'),
            ]);
            foreach ($type['fields'] ?? [] as $ref) {
                if (!is_array($ref)) {
                    continue;
                }
                $oldFid = (int) ($ref['custom_field_id'] ?? 0);
                $newFid = $oldToNewField[$oldFid] ?? 0;
                if ($newFid <= 0) {
                    continue;
                }
                Database::insert('item_type_custom_fields', [
                    'item_type_id' => $typeId,
                    'custom_field_id' => $newFid,
                    'custom_field_order' => (int) ($ref['order'] ?? 10),
                ]);
            }
            $typeNameToId[$originalTypeName] = $typeId;
            if ($sourceId > 0) {
                $oldToNewType[$sourceId] = $typeId;
            }
            if ($firstTypeId === 0) {
                $firstTypeId = $typeId;
            }
            $groupIds = [];
            foreach ($type['groups'] ?? [] as $groupName) {
                $groupName = trim((string) $groupName);
                if ($groupName === '') {
                    continue;
                }
                $group = Database::first('groups', ['group_id'], 'group_name = ?', [$groupName]);
                if ($group !== null) {
                    $groupIds[] = (int) $group['group_id'];
                }
            }
            if ($groupIds !== []) {
                GroupMembership::setItemTypeGroups($typeId, $groupIds);
            }
        }

        $settings = is_array($appMeta['settings'] ?? null) ? $appMeta['settings'] : [];
        $oldDefault = trim((string) ($settings['default_item_type_id'] ?? ''));
        if ($oldDefault !== '' && ctype_digit($oldDefault) && isset($oldToNewType[(int) $oldDefault])) {
            $settings['default_item_type_id'] = (string) $oldToNewType[(int) $oldDefault];
        } elseif ($firstTypeId > 0) {
            $settings['default_item_type_id'] = (string) $firstTypeId;
        }

        $max = 0;
        foreach (ApplicationStore::all() as $existing) {
            $max = max($max, (int) $existing['sort_order']);
        }
        $usePerm = Permission::appUse($slug);
        $applicationId = ApplicationStore::insertApplication([
            'slug' => $slug,
            'name' => $name,
            'hint' => (string) ($appMeta['hint'] ?? ''),
            'icon' => (string) ($appMeta['icon'] ?? 'ic-launch'),
            'permission' => $usePerm,
            'enabled' => 1,
            'sort_order' => $max + 10,
            'entry_mode' => 'shell',
            'settings_json' => json_encode($settings, JSON_UNESCAPED_SLASHES),
        ]);

        $grants = is_array($package['access'] ?? null) ? $package['access'] : [];
        $grantedGroups = Permission::importAppGrants($slug, $grants);
        foreach ($oldToNewType as $newTypeId) {
            if (GroupMembership::itemTypeGroupIds($newTypeId) === []) {
                GroupMembership::setItemTypeGroups($newTypeId, $grantedGroups);
            }
        }

        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        if ($userId > 0) {
            Access::hydrateSession($userId);
        }

        $navOrder = 10;
        foreach ($package['navigation'] ?? [] as $link) {
            if (!is_array($link)) {
                continue;
            }
            $capability = \Adlexone\FrameOne\Library::resolve((string) ($link['capability'] ?? ''));
            if ($capability === '' || !isset(Capabilities::catalog()[$capability])) {
                continue;
            }
            $config = is_array($link['config'] ?? null) ? $link['config'] : [];
            $oldType = trim((string) ($config['item_type_id'] ?? ''));
            if ($oldType !== '' && ctype_digit($oldType)) {
                $mapped = $oldToNewType[(int) $oldType] ?? 0;
                if ($mapped > 0) {
                    $config['item_type_id'] = (string) $mapped;
                } elseif ($firstTypeId > 0) {
                    $config['item_type_id'] = (string) $firstTypeId;
                }
            }
            ApplicationStore::insertNav(
                $applicationId,
                (string) ($link['label'] ?? Capabilities::label($capability)),
                $capability,
                '',
                (string) ($link['icon'] ?? ''),
                $config,
                $navOrder
            );
            $navOrder += 10;
        }

        self::importSavedSearches($slug, is_array($package['saved_searches'] ?? null) ? $package['saved_searches'] : []);
        self::importActions($typeNameToId, $oldToNewType, is_array($package['actions'] ?? null) ? $package['actions'] : []);

        return ['application_id' => $applicationId, 'slug' => $slug];
    }

    /**
     * @param list<array<string, mixed>> $searches
     */
    private static function importSavedSearches(string $slug, array $searches): void
    {
        if (!Database::tableExists('saved_searches')) {
            return;
        }
        foreach ($searches as $search) {
            if (!is_array($search)) {
                continue;
            }
            $searchName = trim((string) ($search['search_name'] ?? ''));
            if ($searchName === '') {
                continue;
            }
            Database::insert('saved_searches', [
                'user' => (string) ($search['user'] ?? 'system'),
                'search_name' => $searchName,
                'search_description' => (string) ($search['search_description'] ?? ''),
                'saved_search_sql' => (string) ($search['saved_search_sql'] ?? ''),
                'application' => $slug,
                'criteria_json' => (string) ($search['criteria_json'] ?? '{}'),
            ]);
        }
    }

    /**
     * @param array<string, int> $typeNameToId
     * @param array<int, int> $oldToNewType
     * @param list<array<string, mixed>> $actions
     */
    private static function importActions(array $typeNameToId, array $oldToNewType, array $actions): void
    {
        if ($actions === [] || !Database::tableExists('action_definitions')) {
            return;
        }
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $typeId = 0;
            $sourceType = (int) ($action['source_item_type_id'] ?? 0);
            if ($sourceType > 0 && isset($oldToNewType[$sourceType])) {
                $typeId = $oldToNewType[$sourceType];
            } else {
                $typeName = (string) ($action['item_type_name'] ?? '');
                $typeId = $typeNameToId[$typeName] ?? 0;
            }
            if ($typeId <= 0) {
                continue;
            }
            Database::insert('action_definitions', [
                'action_name' => (string) ($action['action_name'] ?? 'Action'),
                'action_condition_pre' => (string) ($action['action_condition_pre'] ?? ''),
                'action_condition_post' => (string) ($action['action_condition_post'] ?? ''),
                'action_data' => (string) ($action['action_data'] ?? ''),
                'item_type_id' => $typeId,
                'action_parameters' => (string) ($action['action_parameters'] ?? ''),
                'action_type' => (string) ($action['action_type'] ?? ''),
                'package_file' => (string) ($action['package_file'] ?? ''),
                'package_function' => (string) ($action['package_function'] ?? ''),
                'enabled' => (string) ($action['enabled'] ?? 'Yes'),
            ]);
        }
    }
}
