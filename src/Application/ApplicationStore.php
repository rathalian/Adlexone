<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\FrameOne\Library;
use Adlexone\support\Database;

/**
 * Configured applications and their navigation.
 */
final class ApplicationStore
{
    private const SERVICE_CENTRE_SLUG = 'service-centre';

    private static bool $ready = false;

    public static function ensureReady(): void
    {
        if (self::$ready) {
            return;
        }
        self::ensureSchema();
        self::$ready = true;
        self::seedIfEmpty();
        self::retireServiceCentreLegacy();
        self::renameServiceCentreWork();
        self::renameServiceCentreAnnouncements();
        self::renameContactCentreCapabilities();
        self::migrateToFrameOneCapabilities();
    }

    public static function ensureSchema(): void
    {
        Database::exec(
            'CREATE TABLE IF NOT EXISTS applications (
                application_id INTEGER PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                hint TEXT NOT NULL DEFAULT "",
                icon TEXT NOT NULL DEFAULT "ic-launch",
                permission TEXT NOT NULL,
                enabled INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                entry_mode TEXT NOT NULL DEFAULT "shell",
                legacy_controller TEXT NOT NULL DEFAULT "",
                legacy_key TEXT NOT NULL DEFAULT "",
                settings_json TEXT NOT NULL DEFAULT "{}"
            )'
        );
        if (self::tableExists('applications') && !Database::columnExists('applications', 'settings_json')) {
            Database::exec('ALTER TABLE applications ADD COLUMN settings_json TEXT NOT NULL DEFAULT "{}"');
        }
        Database::exec(
            'CREATE TABLE IF NOT EXISTS application_nav (
                nav_id INTEGER PRIMARY KEY,
                application_id INTEGER NOT NULL,
                label TEXT NOT NULL,
                capability TEXT NOT NULL,
                permission TEXT NOT NULL DEFAULT "",
                icon TEXT NOT NULL DEFAULT "",
                config_json TEXT NOT NULL DEFAULT "{}",
                sort_order INTEGER NOT NULL DEFAULT 0
            )'
        );
    }

    /**
     * Empty installs start with no applications. Use Manage → Builder
     * (or the guided Create flow) to compose the first business app.
     * Existing Service Centre rows are left untouched.
     */
    public static function seedIfEmpty(): void
    {
        // Intentionally no default vertical (helpdesk) seed.
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        self::ensureReady();
        $rows = Database::select('applications', '*', '', [], 'sort_order ASC, name ASC');
        return array_map([self::class, 'application'], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function menuItems(): array
    {
        $items = [];
        foreach (self::all() as $app) {
            if (!$app['enabled'] || !Access::can((string) $app['permission'])) {
                continue;
            }
            $legacy = $app['entry_mode'] === 'legacy' && $app['legacy_controller'] !== '';
            $items[] = [
                'label' => $app['name'],
                'hint' => $app['hint'],
                'icon' => $app['icon'],
                'href' => $legacy
                    ? 'index.php?controller=' . rawurlencode((string) $app['legacy_controller'])
                    : \Adlexone\Http\Router::applicationUrl((string) $app['slug']),
                'controller' => $legacy ? $app['legacy_controller'] : 'application',
                'app' => $legacy ? '' : $app['slug'],
            ];
        }
        return $items;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        $row = Database::first('applications', '*', 'application_id = ?', [$id]);
        return $row === null ? null : self::application($row);
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        self::ensureReady();
        $row = Database::first('applications', '*', 'slug = ?', [$slug]);
        return $row === null ? null : self::application($row);
    }

    public static function slugInUse(string $slug, int $exceptId = 0): bool
    {
        self::ensureReady();
        $row = Database::first(
            'applications',
            ['application_id'],
            'slug = ? AND application_id <> ?',
            [$slug, $exceptId]
        );
        return $row !== null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function insertApplication(array $fields): int
    {
        self::ensureReady();
        $row = [
            'slug' => $fields['slug'],
            'name' => $fields['name'],
            'hint' => $fields['hint'] ?? '',
            'icon' => $fields['icon'] ?? 'ic-launch',
            'permission' => $fields['permission'],
            'enabled' => (int) ($fields['enabled'] ?? 1),
            'sort_order' => (int) ($fields['sort_order'] ?? 0),
            'entry_mode' => $fields['entry_mode'] ?? 'shell',
            'legacy_controller' => $fields['legacy_controller'] ?? '',
            'legacy_key' => $fields['legacy_key'] ?? '',
        ];
        if (Database::columnExists('applications', 'settings_json')) {
            $row['settings_json'] = $fields['settings_json'] ?? '{}';
        }
        return Database::insert('applications', $row);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function updateApplication(int $id, array $fields): void
    {
        self::ensureReady();
        $existing = self::find($id);
        if ($existing === null) {
            return;
        }
        $oldSlug = (string) $existing['slug'];
        $newSlug = (string) $fields['slug'];
        if ($oldSlug !== $newSlug) {
            Permission::renameApp($oldSlug, $newSlug);
            if (Database::tableExists('saved_searches')) {
                Database::update('saved_searches', ['application' => $newSlug], 'application = ?', [$oldSlug]);
            }
            if (Database::tableExists('users')) {
                Database::run(
                    'UPDATE users SET home_controller = ? WHERE home_controller = ?',
                    ['application:' . $newSlug, 'application:' . $oldSlug]
                );
            }
            $fields['permission'] = Permission::appUse($newSlug);
        }
        Database::update('applications', [
            'slug' => $newSlug,
            'name' => $fields['name'],
            'hint' => $fields['hint'] ?? '',
            'icon' => $fields['icon'] ?? 'ic-launch',
            'permission' => $fields['permission'],
            'enabled' => (int) ($fields['enabled'] ?? 0),
        ], 'application_id = ?', [$id]);
    }

    public static function deleteApplication(int $id): void
    {
        self::ensureReady();
        $app = self::find($id);
        if ($app === null) {
            return;
        }
        $slug = (string) $app['slug'];
        $typeIds = [];
        $default = trim((string) (($app['settings']['default_item_type_id'] ?? '')));
        if ($default !== '' && ctype_digit($default)) {
            $typeIds[] = (int) $default;
        }
        foreach (self::navigation($id) as $link) {
            $tid = trim((string) ($link['config']['item_type_id'] ?? ''));
            if ($tid !== '' && ctype_digit($tid)) {
                $typeIds[] = (int) $tid;
            }
        }
        $typeIds = array_values(array_unique($typeIds));

        Permission::revokeApp($slug);
        if (Database::tableExists('saved_searches')) {
            Database::delete('saved_searches', 'application = ?', [$slug]);
        }
        if (Database::tableExists('users')) {
            Database::run(
                'UPDATE users SET home_controller = ? WHERE home_controller = ?',
                ['', 'application:' . $slug]
            );
        }
        Database::delete('application_nav', 'application_id = ?', [$id]);
        Database::delete('applications', 'application_id = ?', [$id]);

        foreach ($typeIds as $typeId) {
            if (!self::itemTypeUsedByOtherApps($typeId, $id)) {
                self::deleteExclusiveItemType($typeId);
            }
        }
    }

    private static function itemTypeUsedByOtherApps(int $typeId, int $exceptApplicationId): bool
    {
        foreach (self::all() as $app) {
            if ((int) $app['application_id'] === $exceptApplicationId) {
                continue;
            }
            $default = trim((string) (($app['settings']['default_item_type_id'] ?? '')));
            if ($default === (string) $typeId) {
                return true;
            }
            foreach (self::navigation((int) $app['application_id']) as $link) {
                if (trim((string) ($link['config']['item_type_id'] ?? '')) === (string) $typeId) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function deleteExclusiveItemType(int $typeId): void
    {
        if ($typeId <= 0 || Database::exists('items', 'item_type_id = ?', [$typeId])) {
            return;
        }
        Database::delete('item_type_custom_fields', 'item_type_id = ?', [$typeId]);
        Database::delete('item_type_groups', 'item_type_id = ?', [$typeId]);
        if (Database::tableExists('action_definitions')) {
            Database::delete('action_definitions', 'item_type_id = ?', [$typeId]);
        }
        Database::delete('item_types', 'item_type_id = ?', [$typeId]);
    }

    public static function moveApplication(int $id, string $direction): void
    {
        self::ensureReady();
        $rows = Database::select('applications', ['application_id'], '', [], 'sort_order ASC, name ASC');
        self::swapOrder($rows, 'application_id', 'applications', 'application_id', $id, $direction);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function navigation(int $applicationId): array
    {
        self::ensureReady();
        $rows = Database::select(
            'application_nav',
            '*',
            'application_id = ?',
            [$applicationId],
            'sort_order ASC, nav_id ASC'
        );
        return array_map([self::class, 'nav'], $rows);
    }

    /** @return array<string, mixed>|null */
    public static function findNav(int $id): ?array
    {
        self::ensureReady();
        $row = Database::first('application_nav', '*', 'nav_id = ?', [$id]);
        return $row === null ? null : self::nav($row);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function insertNav(
        int $applicationId,
        string $label,
        string $capability,
        string $permission,
        string $icon,
        array $config,
        ?int $sortOrder = null
    ): int {
        self::ensureReady();
        if ($sortOrder === null) {
            $max = Database::first(
                'application_nav',
                'COALESCE(MAX(sort_order), 0) AS s',
                'application_id = ?',
                [$applicationId]
            );
            $sortOrder = (int) ($max['s'] ?? 0) + 10;
        }
        return Database::insert('application_nav', [
            'application_id' => $applicationId,
            'label' => $label,
            'capability' => $capability,
            'permission' => $permission,
            'icon' => $icon,
            'config_json' => json_encode($config, JSON_UNESCAPED_SLASHES),
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function updateNav(int $id, string $label, string $capability, string $permission, string $icon, array $config): void
    {
        self::ensureReady();
        Database::update('application_nav', [
            'label' => $label,
            'capability' => $capability,
            'permission' => $permission,
            'icon' => $icon,
            'config_json' => json_encode($config, JSON_UNESCAPED_SLASHES),
        ], 'nav_id = ?', [$id]);
    }

    public static function deleteNav(int $id): void
    {
        self::ensureReady();
        Database::delete('application_nav', 'nav_id = ?', [$id]);
    }

    public static function moveNav(int $id, string $direction): void
    {
        self::ensureReady();
        $nav = self::findNav($id);
        if ($nav === null) {
            return;
        }
        $rows = Database::select(
            'application_nav',
            ['nav_id'],
            'application_id = ?',
            [(int) $nav['application_id']],
            'sort_order ASC, nav_id ASC'
        );
        self::swapOrder($rows, 'nav_id', 'application_nav', 'nav_id', $id, $direction);
    }

    /**
     * Drop the old Service Centre controller name. Saved searches and home
     * pages follow the application slug.
     */
    private static function retireServiceCentreLegacy(): void
    {
        Database::update('applications', [
            'legacy_controller' => '',
            'legacy_key' => '',
            'permission' => Permission::APP_ACCESS,
        ], 'slug = ? AND legacy_key = ?', [self::SERVICE_CENTRE_SLUG, 'app_servicecentre_main']);
        if (self::tableExists('saved_searches')) {
            Database::update(
                'saved_searches',
                ['application' => self::SERVICE_CENTRE_SLUG],
                'application = ?',
                ['app_servicecentre_main']
            );
        }
        if (self::tableExists('users')) {
            Database::run(
                "UPDATE users SET home_controller = ? WHERE home_controller IN ('app_servicecentre_main', 'app_oneorzerohelpdesk_main')",
                ['application:' . self::SERVICE_CENTRE_SLUG]
            );
        }
    }

    /**
     * The Service Centre list is work, and creating an item is New.
     */
    private static function renameServiceCentreWork(): void
    {
        if (!self::tableExists('application_nav')) {
            return;
        }
        Database::update('application_nav', ['capability' => 'servicecentre.work'], "capability = 'servicecentre.tickets'");
        Database::run(
            "UPDATE application_nav SET label = 'Work' WHERE capability = 'servicecentre.work' AND label IN ('Tickets', 'Ticket')"
        );
        Database::run(
            "UPDATE application_nav SET label = 'New'
             WHERE capability = 'items.create'
               AND application_id = (SELECT application_id FROM applications WHERE slug = ?)
               AND label IN ('New ticket', 'New Ticket')",
            [self::SERVICE_CENTRE_SLUG]
        );
        Database::update(
            'applications',
            ['hint' => 'Work and announcements'],
            'slug = ? AND hint = ?',
            [self::SERVICE_CENTRE_SLUG, 'Tickets and announcements']
        );
    }

    /**
     * Announcements live in the Contact Centre function pack.
     */
    private static function renameServiceCentreAnnouncements(): void
    {
        if (!self::tableExists('application_nav')) {
            return;
        }
        Database::update(
            'application_nav',
            ['capability' => 'servicecentre.announcements'],
            "capability = 'announcements'"
        );
    }

    /**
     * Function packs use contact_centre.* ids under site/apps/.
     */
    private static function renameContactCentreCapabilities(): void
    {
        if (!self::tableExists('application_nav')) {
            return;
        }
        $map = [
            'servicecentre.work' => 'contact_centre.work',
            'servicecentre.tickets' => 'contact_centre.work',
            'servicecentre.announcements' => 'contact_centre.announcements',
            'announcements' => 'contact_centre.announcements',
            'servicecentre.settings' => 'contact_centre.settings',
        ];
        foreach ($map as $from => $to) {
            Database::update('application_nav', ['capability' => $to], 'capability = ?', [$from]);
        }
    }

    /**
     * Contact Centre pack screens → FrameOne built-ins (UI-configurable).
     */
    private static function migrateToFrameOneCapabilities(): void
    {
        if (!self::tableExists('application_nav')) {
            return;
        }
        foreach (Library::ALIASES as $from => $to) {
            Database::update('application_nav', ['capability' => $to], 'capability = ?', [$from]);
        }
    }

    private static function tableExists(string $table): bool
    {
        return Database::tableExists($table);
    }

    /**
     * Home-page choices for configured applications.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function homeChoices(): array
    {
        self::ensureReady();
        $choices = [];
        foreach (self::all() as $app) {
            if (empty($app['enabled'])) {
                continue;
            }
            $legacy = trim((string) ($app['legacy_controller'] ?? ''));
            if ((string) $app['entry_mode'] === 'shell' && $legacy === '') {
                $choices[] = ['application:' . $app['slug'], (string) $app['name']];
                continue;
            }
            if ($legacy !== '') {
                $choices[] = [$legacy, (string) $app['name']];
            }
        }
        return $choices;
    }

    /**
     * @param list<string> $values
     * @param list<string> $labels
     */
    public static function mergeHomeChoices(array &$values, array &$labels): void
    {
        $known = [];
        foreach ($values as $value) {
            $known[] = explode('}-{', (string) $value, 2)[0];
        }
        foreach (self::homeChoices() as [$controller, $name]) {
            if (in_array($controller, $known, true)) {
                continue;
            }
            $values[] = $controller . '}-{' . $name;
            $labels[] = $name;
            $known[] = $controller;
        }
    }

    public static function scopeKey(array $app): string
    {
        $legacy = trim((string) ($app['legacy_key'] ?? ''));
        return $legacy !== '' ? $legacy : (string) $app['slug'];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function searchScopes(): array
    {
        self::ensureReady();
        $options = [];
        foreach (self::all() as $app) {
            $options[] = [self::scopeKey($app), (string) $app['name']];
        }
        return $options;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function swapOrder(array $rows, string $idKey, string $table, string $idColumn, int $id, string $direction): void
    {
        $index = null;
        foreach ($rows as $i => $row) {
            if ((int) $row[$idKey] === $id) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($rows[$swap])) {
            return;
        }
        $left = (int) $rows[$index][$idKey];
        $right = (int) $rows[$swap][$idKey];
        $leftOrder = ($index + 1) * 10;
        $rightOrder = ($swap + 1) * 10;
        Database::update($table, ['sort_order' => $rightOrder], $idColumn . ' = ?', [$left]);
        Database::update($table, ['sort_order' => $leftOrder], $idColumn . ' = ?', [$right]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function application(array $row): array
    {
        $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);
        return [
            'application_id' => (int) $row['application_id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'hint' => (string) $row['hint'],
            'icon' => (string) $row['icon'],
            'permission' => (string) $row['permission'],
            'enabled' => (int) $row['enabled'] === 1,
            'sort_order' => (int) $row['sort_order'],
            'entry_mode' => (string) $row['entry_mode'],
            'legacy_controller' => (string) $row['legacy_controller'],
            'legacy_key' => (string) $row['legacy_key'],
            'settings' => is_array($settings) ? $settings : [],
            'settings_json' => (string) ($row['settings_json'] ?? '{}'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function nav(array $row): array
    {
        $config = json_decode((string) ($row['config_json'] ?? ''), true);
        return [
            'nav_id' => (int) $row['nav_id'],
            'application_id' => (int) $row['application_id'],
            'label' => (string) $row['label'],
            'capability' => (string) $row['capability'],
            'permission' => (string) $row['permission'],
            'icon' => (string) $row['icon'],
            'config' => is_array($config) ? $config : [],
            'sort_order' => (int) $row['sort_order'],
        ];
    }
}
