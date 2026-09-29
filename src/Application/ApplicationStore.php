<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Access;
use Adlexone\support\Database;

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
                legacy_key TEXT NOT NULL DEFAULT ""
            )'
        );
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

    public static function seedIfEmpty(): void
    {
        $existing = Database::firstResultParams('SELECT COUNT(*) AS c FROM applications', []);
        if ($existing !== null && (int) ($existing['c'] ?? 0) > 0) {
            return;
        }

        $serviceType = defined('SERVICECENTRE_SET_ITEM_TYPE') ? (string) SERVICECENTRE_SET_ITEM_TYPE : '';
        $knowledgeType = defined('KNOWLEDGEBASE_SET_KB_ITEM_TYPE') ? (string) KNOWLEDGEBASE_SET_KB_ITEM_TYPE : '';
        $text = static function (string $constant, string $fallback): string {
            return defined($constant) ? (string) constant($constant) : $fallback;
        };

        $serviceId = self::insertApplication([
            'slug' => 'service-centre',
            'name' => 'Service Centre',
            'hint' => 'Tickets and announcements',
            'icon' => 'ic-servicecentre',
            'permission' => 'servicecentre.use',
            'enabled' => 1,
            'sort_order' => 10,
            'entry_mode' => 'shell',
            'legacy_controller' => 'app_servicecentre_main',
            'legacy_key' => 'app_servicecentre_main',
        ]);
        self::insertNav($serviceId, $text('APP_SC_TXT_1', 'Tickets'), 'servicecentre.tickets', 'servicecentre.search', 'ic-search', [], 10);
        self::insertNav($serviceId, $text('APP_SC_TXT_2', 'New ticket'), 'items.create', 'servicecentre.use', 'ic-create-ticket', ['item_type_id' => $serviceType], 20);
        self::insertNav($serviceId, $text('APP_SC_TXT_60', 'Searches'), 'search.saved_list', 'servicecentre.search', 'ic-my-ticket-searches', [], 30);
        self::insertNav($serviceId, $text('APP_SC_TXT_38', 'Announcements'), 'announcements', 'servicecentre.use', 'ic-announcements', [], 40);
        self::insertNav($serviceId, $text('APP_SC_TXT_79', 'Settings'), 'servicecentre.settings', 'servicecentre.settings', 'ic-settings', [], 50);

        $knowledgeId = self::insertApplication([
            'slug' => 'knowledge-hub',
            'name' => 'Knowledge Hub',
            'hint' => 'Articles and search',
            'icon' => 'ic-knowledgebase',
            'permission' => 'knowledgebase.use',
            'enabled' => 1,
            'sort_order' => 20,
            'entry_mode' => 'shell',
            'legacy_controller' => 'app_oneorzeroknowledgebase_main',
            'legacy_key' => 'app_oneorzeroknowledgebase_main',
        ]);
        self::insertNav($knowledgeId, $text('APP_KB_TXT_68', 'Knowledge'), 'knowledge.home', 'knowledgebase.use', 'ic-knowledgebase', [], 10);
        self::insertNav($knowledgeId, $text('APP_KB_TXT_49', 'New article'), 'items.create', 'knowledgebase.use', 'ic-new-article', ['item_type_id' => $knowledgeType], 20);
        self::insertNav($knowledgeId, $text('APP_KB_TXT_47', 'Search'), 'search.advanced', 'knowledgebase.use', 'ic-article-search', ['item_type_id' => $knowledgeType], 30);
        self::insertNav($knowledgeId, $text('APP_KB_TXT_31', 'Settings'), 'knowledge.settings', 'knowledgebase.settings', 'ic-kb-settings', [], 40);

        self::insertApplication([
            'slug' => 'report-manager',
            'name' => 'Report Manager',
            'hint' => 'Reports and saved views',
            'icon' => 'ic-search',
            'permission' => 'reports.use',
            'enabled' => 1,
            'sort_order' => 30,
            'entry_mode' => 'legacy',
            'legacy_controller' => 'app_oneorzeroreportmanager_main',
            'legacy_key' => 'app_oneorzeroreportmanager_main',
        ]);
        self::insertApplication([
            'slug' => 'time-manager',
            'name' => 'Time Manager',
            'hint' => 'Time entries',
            'icon' => 'ic-time',
            'permission' => 'app.access',
            'enabled' => 1,
            'sort_order' => 40,
            'entry_mode' => 'legacy',
            'legacy_controller' => 'app_oneorzerotimemanager_main',
            'legacy_key' => 'app_oneorzerotimemanager_main',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        self::ensureReady();
        $rows = Database::buildArray('SELECT * FROM applications ORDER BY sort_order ASC, name ASC');
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
                    : 'index.php?controller=application&app=' . rawurlencode((string) $app['slug']),
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
        $row = Database::firstResultParams('SELECT * FROM applications WHERE application_id = ?', [$id]);
        return $row === null ? null : self::application($row);
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        self::ensureReady();
        $row = Database::firstResultParams('SELECT * FROM applications WHERE slug = ?', [$slug]);
        return $row === null ? null : self::application($row);
    }

    public static function slugInUse(string $slug, int $exceptId = 0): bool
    {
        self::ensureReady();
        $row = Database::firstResultParams(
            'SELECT application_id FROM applications WHERE slug = ? AND application_id <> ?',
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
        $id = Database::newID('applications', 'application_id');
        Database::queryParams(
            'INSERT INTO applications (
                application_id, slug, name, hint, icon, permission, enabled, sort_order, entry_mode, legacy_controller, legacy_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $fields['slug'],
                $fields['name'],
                $fields['hint'] ?? '',
                $fields['icon'] ?? 'ic-launch',
                $fields['permission'],
                (int) ($fields['enabled'] ?? 1),
                (int) ($fields['sort_order'] ?? 0),
                $fields['entry_mode'] ?? 'shell',
                $fields['legacy_controller'] ?? '',
                $fields['legacy_key'] ?? '',
            ]
        );
        return $id;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function updateApplication(int $id, array $fields): void
    {
        self::ensureReady();
        Database::queryParams(
            'UPDATE applications
             SET slug = ?, name = ?, hint = ?, icon = ?, permission = ?, enabled = ?
             WHERE application_id = ?',
            [
                $fields['slug'],
                $fields['name'],
                $fields['hint'] ?? '',
                $fields['icon'] ?? 'ic-launch',
                $fields['permission'],
                (int) ($fields['enabled'] ?? 0),
                $id,
            ]
        );
    }

    public static function deleteApplication(int $id): void
    {
        self::ensureReady();
        Database::queryParams('DELETE FROM application_nav WHERE application_id = ?', [$id]);
        Database::queryParams('DELETE FROM applications WHERE application_id = ?', [$id]);
    }

    public static function moveApplication(int $id, string $direction): void
    {
        self::ensureReady();
        $rows = Database::buildArray('SELECT application_id FROM applications ORDER BY sort_order ASC, name ASC');
        self::swapOrder($rows, 'application_id', 'applications', 'application_id', $id, $direction);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function navigation(int $applicationId): array
    {
        self::ensureReady();
        $rows = Database::buildArray(
            'SELECT * FROM application_nav WHERE application_id = ' . (int) $applicationId . ' ORDER BY sort_order ASC, nav_id ASC'
        );
        return array_map([self::class, 'nav'], $rows);
    }

    /** @return array<string, mixed>|null */
    public static function findNav(int $id): ?array
    {
        self::ensureReady();
        $row = Database::firstResultParams('SELECT * FROM application_nav WHERE nav_id = ?', [$id]);
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
        $id = Database::newID('application_nav', 'nav_id');
        if ($sortOrder === null) {
            $max = Database::firstResultParams(
                'SELECT COALESCE(MAX(sort_order), 0) AS s FROM application_nav WHERE application_id = ?',
                [$applicationId]
            );
            $sortOrder = (int) ($max['s'] ?? 0) + 10;
        }
        Database::queryParams(
            'INSERT INTO application_nav (
                nav_id, application_id, label, capability, permission, icon, config_json, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $applicationId,
                $label,
                $capability,
                $permission,
                $icon,
                json_encode($config, JSON_UNESCAPED_SLASHES),
                $sortOrder,
            ]
        );
        return $id;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function updateNav(int $id, string $label, string $capability, string $permission, string $icon, array $config): void
    {
        self::ensureReady();
        Database::queryParams(
            'UPDATE application_nav
             SET label = ?, capability = ?, permission = ?, icon = ?, config_json = ?
             WHERE nav_id = ?',
            [$label, $capability, $permission, $icon, json_encode($config, JSON_UNESCAPED_SLASHES), $id]
        );
    }

    public static function deleteNav(int $id): void
    {
        self::ensureReady();
        Database::queryParams('DELETE FROM application_nav WHERE nav_id = ?', [$id]);
    }

    public static function moveNav(int $id, string $direction): void
    {
        self::ensureReady();
        $nav = self::findNav($id);
        if ($nav === null) {
            return;
        }
        $rows = Database::buildArray(
            'SELECT nav_id FROM application_nav WHERE application_id = ' . (int) $nav['application_id'] . ' ORDER BY sort_order ASC, nav_id ASC'
        );
        self::swapOrder($rows, 'nav_id', 'application_nav', 'nav_id', $id, $direction);
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
        Database::queryParams(
            'UPDATE ' . $table . ' SET sort_order = ? WHERE ' . $idColumn . ' = ?',
            [$rightOrder, $left]
        );
        Database::queryParams(
            'UPDATE ' . $table . ' SET sort_order = ? WHERE ' . $idColumn . ' = ?',
            [$leftOrder, $right]
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function application(array $row): array
    {
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
