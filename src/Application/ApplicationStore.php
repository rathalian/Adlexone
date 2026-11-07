<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Database\Database;

/**
 * Configured applications and their navigation.
 */
final class ApplicationStore
{
    private static bool $ready = false;

    public static function ensureReady(): void
    {
        if (self::$ready) {
            return;
        }
        self::ensureSchema();
        self::$ready = true;
        self::seedIfEmpty();
        self::applyStartingApplications();
        self::addSearchTab();
    }

    public static function ensureSchema(): void
    {
        Database::ensure();
    }

    /**
     * The setup choice is applied once. Deleting a sample does not bring it back.
     */
    private static function applyStartingApplications(): void
    {
        if (!Database::tableExists('applications') || !Database::tableExists('site_setup')) {
            return;
        }
        if (Database::first('site_setup', ['choice']) !== null) {
            return;
        }
        if (Database::count('applications') > 0) {
            self::markStartingApplications('existing');
            return;
        }
        $choice = defined('SET_STARTING_APPLICATIONS') ? trim((string) SET_STARTING_APPLICATIONS) : 'none';
        if (!in_array($choice, ['samples', 'exemplar', 'none'], true)) {
            $choice = 'none';
        }
        if ($choice === 'samples') {
            ApplicationBuilder::installShowcase();
        } elseif ($choice === 'exemplar') {
            ApplicationBuilder::installExemplar();
        }
        self::markStartingApplications($choice);
    }

    private static function markStartingApplications(string $choice): void
    {
        if (Database::first('site_setup', ['choice']) !== null) {
            return;
        }
        Database::insert('site_setup', [
            'choice' => $choice,
            'applied_at' => time(),
        ]);
    }

    /**
     * An empty install stays empty until setup chooses applications.
     * Use Manage → Builder to compose another business app.
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
            $items[] = [
                'label' => $app['name'],
                'hint' => $app['hint'],
                'icon' => $app['icon'],
                'href' => \Adlexone\Http\Router::applicationUrl((string) $app['slug']),
                'app' => $app['slug'],
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
            'icon' => $fields['icon'] ?? 'ic-grid',
            'permission' => $fields['permission'],
            'enabled' => (int) ($fields['enabled'] ?? 1),
            'sort_order' => (int) ($fields['sort_order'] ?? 0),
            'entry_mode' => $fields['entry_mode'] ?? 'shell',
        ];
        if (Database::columnExists('applications', 'legacy_key')) {
            $row['legacy_key'] = (string) ($fields['legacy_key'] ?? '');
        }
        if (Database::columnExists('applications', 'guide')) {
            $row['guide'] = (string) ($fields['guide'] ?? '');
        }
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
        $update = [
            'slug' => $newSlug,
            'name' => $fields['name'],
            'hint' => $fields['hint'] ?? '',
            'icon' => $fields['icon'] ?? 'ic-grid',
            'permission' => $fields['permission'],
            'enabled' => (int) ($fields['enabled'] ?? 0),
        ];
        if (Database::columnExists('applications', 'guide')) {
            $update['guide'] = (string) ($fields['guide'] ?? '');
        }
        Database::update('applications', $update, 'application_id = ?', [$id]);
    }

    /**
     * The record type chosen on the application details page.
     * Screens that keep a record type use this one.
     */
    public static function assignRecordType(int $applicationId, string $typeId): void
    {
        self::ensureReady();
        $app = self::find($applicationId);
        if ($app === null) {
            return;
        }
        $typeId = trim($typeId);
        $settings = is_array($app['settings']) ? $app['settings'] : [];
        $settings['default_item_type_id'] = $typeId;
        $settings['record_type_chosen'] = '1';
        ApplicationSettings::put((string) $app['slug'], $settings);

        $catalog = Capabilities::catalog();
        foreach (self::navigation($applicationId) as $link) {
            $capability = (string) $link['capability'];
            $kind = (string) ($catalog[ScreenCatalog::resolve($capability)]['config'] ?? 'none');
            if (!in_array($kind, ['item_type', 'field', 'activity', 'register', 'intake'], true)) {
                continue;
            }
            $config = is_array($link['config']) ? $link['config'] : [];
            if ($typeId === '') {
                unset($config['item_type_id']);
            } else {
                $config['item_type_id'] = $typeId;
            }
            self::updateNav(
                (int) $link['nav_id'],
                (string) $link['label'],
                $capability,
                (string) $link['permission'],
                (string) $link['icon'],
                $config
            );
        }
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

        Database::transaction(function () use ($id, $slug, $typeIds): void {
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
            Rules::deleteForApplication($id);
            Database::delete('application_nav', 'application_id = ?', [$id]);
            Database::delete('applications', 'application_id = ?', [$id]);

            foreach ($typeIds as $typeId) {
                if (!self::itemTypeUsedByOtherApps($typeId, $id)) {
                    self::deleteExclusiveItemType($typeId);
                }
            }
        });
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
        if (Database::tableExists('application_rules')) {
            Database::delete('application_rules', 'item_type_id = ?', [$typeId]);
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
     * An application that lets people create records also gets a Search tab, once,
     * placed after New. The marker keeps a removed tab from coming back.
     */
    private static function addSearchTab(): void
    {
        if (!self::tableExists('application_nav') || !Database::columnExists('applications', 'settings_json')) {
            return;
        }
        foreach (Database::select('applications', '*') as $row) {
            $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);
            $settings = is_array($settings) ? $settings : [];
            if (($settings['search_tab_added'] ?? '') === '1') {
                continue;
            }
            $applicationId = (int) $row['application_id'];
            $links = Database::select('application_nav', '*', 'application_id = ?', [$applicationId], 'sort_order ASC, nav_id ASC');
            $create = null;
            $has = false;
            foreach ($links as $link) {
                $capability = ScreenCatalog::resolve((string) $link['capability']);
                $has = $has || $capability === ScreenCatalog::SEARCH_ADVANCED;
                if ($capability === ScreenCatalog::CREATE && $create === null) {
                    $create = $link;
                }
            }
            if (!$has && $create !== null) {
                $config = json_decode((string) ($create['config_json'] ?? '{}'), true);
                self::insertNav(
                    $applicationId,
                    'Search',
                    ScreenCatalog::SEARCH_ADVANCED,
                    (string) ($create['permission'] ?? ''),
                    'ic-search',
                    is_array($config) ? $config : [],
                    (int) $create['sort_order'] + 5
                );
            }
            $settings['search_tab_added'] = '1';
            Database::update('applications', ['settings_json' => json_encode($settings, JSON_UNESCAPED_SLASHES)], 'application_id = ?', [$applicationId]);
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
            $choices[] = ['application:' . $app['slug'], (string) $app['name']];
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
        return (string) ($app['slug'] ?? '');
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
            'guide' => (string) ($row['guide'] ?? ''),
            'icon' => (string) $row['icon'],
            'permission' => (string) $row['permission'],
            'enabled' => (int) $row['enabled'] === 1,
            'sort_order' => (int) $row['sort_order'],
            'entry_mode' => (string) $row['entry_mode'],
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
