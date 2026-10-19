<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\support\Database;

/**
 * Settings stored on applications.settings_json for one application.
 */
final class ApplicationSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function get(string $slug): array
    {
        if (!Database::tableExists('applications') || !Database::columnExists('applications', 'settings_json')) {
            return [];
        }
        $row = Database::first('applications', ['settings_json'], 'slug = ?', [$slug]);
        if ($row === null) {
            return [];
        }
        $data = json_decode((string) ($row['settings_json'] ?? '{}'), true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function put(string $slug, array $settings): void
    {
        if (!Database::columnExists('applications', 'settings_json')) {
            return;
        }
        Database::update(
            'applications',
            ['settings_json' => json_encode($settings, JSON_UNESCAPED_SLASHES)],
            'slug = ?',
            [$slug]
        );
    }

    public static function defaultItemTypeId(string $slug): string
    {
        return trim((string) (self::get($slug)['default_item_type_id'] ?? ''));
    }

    /**
     * Record types this application may create, search, and list.
     * Taken from the default item type and from navigation link config.
     *
     * @return list<int>
     */
    public static function allowedItemTypeIds(string $slug): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return [];
        }
        $ids = [];
        $default = self::defaultItemTypeId($slug);
        if ($default !== '' && ctype_digit($default)) {
            $ids[] = (int) $default;
        }
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            return array_values(array_unique($ids));
        }
        foreach (ApplicationStore::navigation((int) $app['application_id']) as $link) {
            $tid = trim((string) ($link['config']['item_type_id'] ?? ''));
            if ($tid !== '' && ctype_digit($tid)) {
                $ids[] = (int) $tid;
            }
        }
        return array_values(array_unique($ids));
    }

    public static function allowsItemType(string $slug, int $typeId): bool
    {
        if ($typeId <= 0) {
            return false;
        }
        $allowed = self::allowedItemTypeIds($slug);
        return $allowed !== [] && in_array($typeId, $allowed, true);
    }

    /**
     * Enabled applications that use each record type.
     *
     * @return array<int, list<string>>
     */
    public static function typeOwners(): array
    {
        $owners = [];
        foreach (ApplicationStore::all() as $app) {
            if (empty($app['enabled'])) {
                continue;
            }
            $name = (string) $app['name'];
            foreach (self::allowedItemTypeIds((string) $app['slug']) as $typeId) {
                $owners[$typeId][$name] = $name;
            }
        }
        $lists = [];
        foreach ($owners as $typeId => $names) {
            $values = array_values($names);
            sort($values);
            $lists[(int) $typeId] = $values;
        }

        return $lists;
    }

    /**
     * Enabled applications that use each custom field, via their record types.
     *
     * @return array<int, list<string>>
     */
    public static function fieldOwners(): array
    {
        $typeOwners = self::typeOwners();
        if ($typeOwners === []) {
            return [];
        }
        $ids = array_map('intval', array_keys($typeOwners));
        $rows = Database::select(
            'item_type_custom_fields',
            ['item_type_id', 'custom_field_id'],
            'item_type_id IN (' . implode(',', $ids) . ')'
        );
        $owners = [];
        foreach ($rows as $row) {
            $fieldId = (int) $row['custom_field_id'];
            foreach ($typeOwners[(int) $row['item_type_id']] ?? [] as $name) {
                $owners[$fieldId][$name] = $name;
            }
        }
        $lists = [];
        foreach ($owners as $fieldId => $names) {
            $values = array_values($names);
            sort($values);
            $lists[(int) $fieldId] = $values;
        }

        return $lists;
    }
}
