<?php
declare(strict_types=1);

namespace Adlexone\FrameOne;

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
}
