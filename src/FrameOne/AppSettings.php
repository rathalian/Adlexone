<?php
declare(strict_types=1);

namespace Adlexone\FrameOne;

use Adlexone\Application\ApplicationStore;
use Adlexone\support\Database;

/**
 * Per-application settings stored on applications.settings_json.
 */
final class AppSettings
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
     * Record types this application is allowed to create/search/list.
     * Drawn from settings default + nav link configs.
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
}
