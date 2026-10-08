<?php
declare(strict_types=1);

namespace Adlexone\Database;

use Adlexone\Data\GroupMembership;
use Adlexone\support\Database;

/**
 * Shared platform schema upgrades.
 *
 * Application packs must not own tables here — only shared entities, with
 * optional application slug/id references (e.g. saved_searches.application).
 */
final class SchemaMigrator
{
    private const VERSION = 5;

    private static bool $done = false;

    public static function migrate(): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        Database::exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version INTEGER NOT NULL PRIMARY KEY,
                applied_at INTEGER NOT NULL
            )'
        );

        $current = (int) (Database::row('SELECT MAX(version) AS v FROM schema_migrations')['v'] ?? 0);
        if ($current < 1) {
            self::applyV1();
            self::mark(1);
            $current = 1;
        }
        if ($current < 2) {
            self::applyV2();
            self::mark(2);
            $current = 2;
        }
        if ($current < 3) {
            self::applyV3();
            self::mark(3);
            $current = 3;
        }
        if ($current < 4) {
            self::applyV4();
            self::mark(4);
            $current = 4;
        }
        if ($current < 5) {
            self::applyV5();
            self::mark(5);
        }
    }

    private static function mark(int $version): void
    {
        Database::insertIgnore('schema_migrations', [
            'version' => $version,
            'applied_at' => time(),
        ]);
    }

    /**
     * Shared identity/ACL junctions, indexes, drop app-specific tables.
     */
    private static function applyV1(): void
    {
        Database::exec(
            'CREATE TABLE IF NOT EXISTS user_groups (
                user_id INTEGER NOT NULL,
                group_id INTEGER NOT NULL,
                PRIMARY KEY (user_id, group_id)
            )'
        );
        Database::exec(
            'CREATE TABLE IF NOT EXISTS item_groups (
                item_id INTEGER NOT NULL,
                group_id INTEGER NOT NULL,
                PRIMARY KEY (item_id, group_id)
            )'
        );
        Database::exec(
            'CREATE TABLE IF NOT EXISTS item_type_groups (
                item_type_id INTEGER NOT NULL,
                group_id INTEGER NOT NULL,
                PRIMARY KEY (item_type_id, group_id)
            )'
        );

        self::migrateUserGroupsFromLegacy();
        self::migrateItemGroupsFromLegacy();
        self::migrateItemTypeGroupsFromLegacy();

        if (Database::tableExists('group_members')) {
            Database::exec('DROP TABLE IF EXISTS group_members');
        }
        if (Database::tableExists('user_permissions')) {
            Database::exec('DROP TABLE IF EXISTS user_permissions');
        }

        self::dropAppSpecificTables();
        self::purgeRetiredApplications();
        self::purgeRetiredPermissions();
        self::purgeRetiredSavedSearches();
        self::purgeTimeManagerActions();
        self::ensureSharedIndexes();
    }

    /**
     * Drop leftover delimiter-only reliance markers; keep synced columns for forms.
     */
    private static function applyV2(): void
    {
        self::dropAppSpecificTables();
        self::purgeRetiredApplications();
        self::ensureSharedIndexes();
        self::dropDeadCustomFieldMetaColumns();
    }

    private static function migrateUserGroupsFromLegacy(): void
    {
        if (!Database::tableExists('group_members')) {
            return;
        }
        if (Database::count('user_groups') > 0) {
            return;
        }
        foreach (Database::select('group_members', ['user_id', 'groups']) as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            GroupMembership::setUserGroups($userId, GroupMembership::parseDelimited((string) ($row['groups'] ?? '')));
        }
    }

    private static function migrateItemGroupsFromLegacy(): void
    {
        if (!Database::tableExists('items') || !Database::columnExists('items', 'group_security')) {
            return;
        }
        if (Database::count('item_groups') > 0) {
            return;
        }
        foreach (Database::select('items', ['item_id', 'group_security']) as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $ids = GroupMembership::parseDelimited((string) ($row['group_security'] ?? ''));
            foreach ($ids as $groupId) {
                Database::insertIgnore('item_groups', [
                    'item_id' => $itemId,
                    'group_id' => $groupId,
                ]);
            }
        }
    }

    private static function migrateItemTypeGroupsFromLegacy(): void
    {
        if (!Database::tableExists('item_types') || !Database::columnExists('item_types', 'group_security')) {
            return;
        }
        if (Database::count('item_type_groups') > 0) {
            return;
        }
        foreach (Database::select('item_types', ['item_type_id', 'group_security']) as $row) {
            $typeId = (int) ($row['item_type_id'] ?? 0);
            if ($typeId <= 0) {
                continue;
            }
            $ids = GroupMembership::parseDelimited((string) ($row['group_security'] ?? ''));
            foreach ($ids as $groupId) {
                Database::insertIgnore('item_type_groups', [
                    'item_type_id' => $typeId,
                    'group_id' => $groupId,
                ]);
            }
        }
    }

    private static function dropAppSpecificTables(): void
    {
        foreach ([
            'timemanager_time_table',
            'timemanager_temp_time_table',
            'reportmanager_reports',
            'reportmanager_multi',
            'AIMS_timemanager_time_table',
            'AIMS_timemanager_temp_time_table',
            'AIMS_reportmanager_reports',
            'AIMS_reportmanager_multi',
            'ooz_timemanager_time_table',
            'ooz_timemanager_temp_time_table',
            'ooz_reportmanager_reports',
            'ooz_reportmanager_multi',
        ] as $table) {
            if (Database::tableExists($table)) {
                Database::exec('DROP TABLE IF EXISTS ' . Database::escapeIdentifier($table));
            }
        }
    }

    private static function purgeRetiredApplications(): void
    {
        if (!Database::tableExists('applications')) {
            return;
        }
        $slugs = ['knowledge-hub', 'report-manager', 'time-manager', 'test'];
        foreach ($slugs as $slug) {
            $app = Database::first('applications', ['application_id'], 'slug = ?', [$slug]);
            if ($app === null) {
                continue;
            }
            $id = (int) $app['application_id'];
            Database::delete('application_nav', 'application_id = ?', [$id]);
            Database::delete('applications', 'application_id = ?', [$id]);
        }
        // Legacy controller leftovers
        Database::run(
            "DELETE FROM applications WHERE legacy_controller IN (
                'app_oneorzeroknowledgebase_main',
                'app_oneorzeroreportmanager_main',
                'app_oneorzerotimemanager_main'
            )"
        );
    }

    private static function purgeRetiredPermissions(): void
    {
        if (!Database::tableExists('group_permissions')) {
            return;
        }
        Database::run(
            "DELETE FROM group_permissions WHERE permission IN (
                'knowledgebase.use',
                'knowledgebase.manage',
                'knowledgebase.settings',
                'reports.use',
                'reports.manage'
            )"
        );
    }

    private static function purgeRetiredSavedSearches(): void
    {
        if (!Database::tableExists('saved_searches')) {
            return;
        }
        Database::run(
            "DELETE FROM saved_searches WHERE application IN (
                'app_oneorzeroreportmanager_main',
                'app_oneorzerotimemanager_main',
                'app_oneorzeroknowledgebase_main'
            )"
        );
    }

    private static function purgeTimeManagerActions(): void
    {
        if (!Database::tableExists('action_definitions')) {
            return;
        }
        Database::run(
            "DELETE FROM action_definitions WHERE package_file = 'Time_Manager.actions.php'"
        );
    }

    private static function ensureSharedIndexes(): void
    {
        $indexes = [
            'CREATE INDEX IF NOT EXISTS idx_items_item_type_id ON items (item_type_id)',
            'CREATE INDEX IF NOT EXISTS idx_items_user_security ON items (user_security)',
            'CREATE INDEX IF NOT EXISTS idx_items_creator_security ON items (creator_security)',
            'CREATE INDEX IF NOT EXISTS idx_core_log_item_seq ON core_log (item_id, log_item_sequence)',
            'CREATE INDEX IF NOT EXISTS idx_user_groups_group ON user_groups (group_id)',
            'CREATE INDEX IF NOT EXISTS idx_item_groups_group ON item_groups (group_id)',
            'CREATE INDEX IF NOT EXISTS idx_item_type_groups_group ON item_type_groups (group_id)',
            'CREATE INDEX IF NOT EXISTS idx_menu_values_field ON custom_field_menu_values (custom_field_id)',
            'CREATE INDEX IF NOT EXISTS idx_type_fields_type ON item_type_custom_fields (item_type_id)',
            'CREATE INDEX IF NOT EXISTS idx_saved_searches_app ON saved_searches (application)',
            'CREATE INDEX IF NOT EXISTS idx_application_nav_app ON application_nav (application_id)',
            'CREATE INDEX IF NOT EXISTS idx_user_identities_user ON user_identities (user_id)',
        ];
        foreach ($indexes as $sql) {
            try {
                Database::exec($sql);
            } catch (\Throwable) {
                // Table may not exist on a brand-new empty install yet.
            }
        }
    }

    private static function dropDeadCustomFieldMetaColumns(): void
    {
        // SQLite 3.35+ supports DROP COLUMN. Skip quietly if unsupported or missing.
        if (!Database::tableExists('custom_fields')) {
            return;
        }
        foreach (['menu_relationship', 'menu_value_links', 'data_source_name', 'menu_levels'] as $column) {
            if (!Database::columnExists('custom_fields', $column)) {
                continue;
            }
            try {
                Database::exec('ALTER TABLE custom_fields DROP COLUMN ' . Database::escapeIdentifier($column));
            } catch (\Throwable) {
                // Older SQLite or in-use — leave column.
            }
        }
        if (Database::tableExists('custom_field_menu_values')
            && Database::columnExists('custom_field_menu_values', 'sub_menu_values')
        ) {
            try {
                Database::exec('ALTER TABLE custom_field_menu_values DROP COLUMN sub_menu_values');
            } catch (\Throwable) {
            }
        }
    }

    /**
     * EAV custom fields, drop group_security dual-write columns, criteria_json searches.
     */
    private static function applyV3(): void
    {
        Database::exec(
            'CREATE TABLE IF NOT EXISTS item_field_values (
                item_id INTEGER NOT NULL,
                custom_field_id INTEGER NOT NULL,
                value TEXT NOT NULL DEFAULT "",
                PRIMARY KEY (item_id, custom_field_id)
            )'
        );
        Database::exec('CREATE INDEX IF NOT EXISTS idx_item_field_values_field ON item_field_values (custom_field_id)');
        Database::exec('CREATE INDEX IF NOT EXISTS idx_item_field_values_field_value ON item_field_values (custom_field_id, value)');

        self::migrateCustomFieldColumnsToEav();
        self::rebuildItemsCoreOnly();
        self::rebuildItemTypesWithoutGroupSecurity();
        self::ensureCriteriaJsonColumn();
        self::migrateSavedSearchCriteria();
        self::ensureSharedIndexes();
    }

    private static function migrateCustomFieldColumnsToEav(): void
    {
        if (!Database::tableExists('items')) {
            return;
        }
        $columns = Database::columns('items');
        $fieldCols = array_values(array_filter(
            $columns,
            static fn(string $c): bool => (bool) preg_match('/^custom_field_\d+$/', $c)
        ));
        if ($fieldCols === []) {
            return;
        }
        if (Database::count('item_field_values') > 0) {
            // Still drop wide columns below even if EAV already populated.
        } else {
            $rows = Database::select('items', array_merge(['item_id'], $fieldCols));
            foreach ($rows as $row) {
                $itemId = (int) ($row['item_id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }
                foreach ($fieldCols as $col) {
                    if (!preg_match('/^custom_field_(\d+)$/', $col, $m)) {
                        continue;
                    }
                    $value = $row[$col] ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    Database::run(
                        'INSERT OR IGNORE INTO item_field_values (item_id, custom_field_id, value) VALUES (?, ?, ?)',
                        [$itemId, (int) $m[1], (string) $value]
                    );
                }
            }
        }
    }

    private static function rebuildItemsCoreOnly(): void
    {
        if (!Database::tableExists('items')) {
            return;
        }
        $hasWide = false;
        foreach (Database::columns('items') as $col) {
            if ($col === 'group_security' || preg_match('/^custom_field_\d+$/', $col)) {
                $hasWide = true;
                break;
            }
        }
        if (!$hasWide) {
            return;
        }

        Database::exec(
            'CREATE TABLE items__core (
                item_id INTEGER NOT NULL PRIMARY KEY,
                create_date INTEGER NOT NULL,
                core_log_updated INTEGER,
                item_type_id INTEGER NOT NULL,
                creator_security INTEGER,
                user_security INTEGER NOT NULL,
                item_title TEXT NOT NULL
            )'
        );
        Database::exec(
            'INSERT INTO items__core (item_id, create_date, core_log_updated, item_type_id, creator_security, user_security, item_title)
             SELECT item_id, create_date, core_log_updated, item_type_id, creator_security, user_security, item_title FROM items'
        );
        Database::exec('DROP TABLE items');
        Database::exec('ALTER TABLE items__core RENAME TO items');
        Database::exec('CREATE INDEX IF NOT EXISTS idx_items_create_date ON items (create_date)');
        Database::exec('CREATE INDEX IF NOT EXISTS idx_items_title ON items (item_title)');
        Database::exec('CREATE INDEX IF NOT EXISTS idx_items_item_type_id ON items (item_type_id)');
        Database::exec('CREATE INDEX IF NOT EXISTS idx_items_user_security ON items (user_security)');
        Database::exec('CREATE INDEX IF NOT EXISTS idx_items_creator_security ON items (creator_security)');
    }

    private static function rebuildItemTypesWithoutGroupSecurity(): void
    {
        if (!Database::tableExists('item_types') || !Database::columnExists('item_types', 'group_security')) {
            return;
        }
        Database::exec(
            'CREATE TABLE item_types__core (
                item_type_id INTEGER NOT NULL PRIMARY KEY,
                item_type_name TEXT,
                user_security TEXT,
                enabled TEXT
            )'
        );
        Database::exec(
            'INSERT INTO item_types__core (item_type_id, item_type_name, user_security, enabled)
             SELECT item_type_id, item_type_name, user_security, enabled FROM item_types'
        );
        Database::exec('DROP TABLE item_types');
        Database::exec('ALTER TABLE item_types__core RENAME TO item_types');
    }

    private static function ensureCriteriaJsonColumn(): void
    {
        if (!Database::tableExists('saved_searches')) {
            return;
        }
        if (!Database::columnExists('saved_searches', 'criteria_json')) {
            Database::exec('ALTER TABLE saved_searches ADD COLUMN criteria_json TEXT NOT NULL DEFAULT "{}"');
        }
    }

    private static function migrateSavedSearchCriteria(): void
    {
        if (!Database::tableExists('saved_searches')) {
            return;
        }
        foreach (Database::select('saved_searches', ['search_id', 'saved_search_sql', 'criteria_json']) as $row) {
            $existing = trim((string) ($row['criteria_json'] ?? ''));
            if ($existing !== '' && $existing !== '{}') {
                continue;
            }
            $sql = (string) ($row['saved_search_sql'] ?? '');
            if ($sql === '') {
                continue;
            }
            $criteria = [
                'version' => 1,
                'legacy_sql' => $sql,
            ];
            Database::update(
                'saved_searches',
                ['criteria_json' => json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'],
                'search_id = ?',
                [(int) $row['search_id']]
            );
        }
    }

    /**
     * FrameOne: per-app settings JSON, announcements scoped by application slug.
     */
    private static function applyV4(): void
    {
        if (Database::tableExists('applications') && !Database::columnExists('applications', 'settings_json')) {
            Database::exec('ALTER TABLE applications ADD COLUMN settings_json TEXT NOT NULL DEFAULT "{}"');
        }
        if (Database::tableExists('announcements') && !Database::columnExists('announcements', 'application')) {
            Database::exec('ALTER TABLE announcements ADD COLUMN application TEXT NOT NULL DEFAULT ""');
            Database::exec('CREATE INDEX IF NOT EXISTS idx_announcements_app ON announcements (application)');
            // Legacy Service Centre announcements become scoped to that slug when present.
            if (Database::first('applications', ['application_id'], 'slug = ?', ['service-centre']) !== null) {
                Database::update('announcements', ['application' => 'service-centre'], "application = '' OR application IS NULL");
            }
        }
    }

    /**
     * Retire Service Centre as a product: drop the seeded app, legacy permissions,
     * and orphan announcements scoped to it. Helpdesk remains a Builder blueprint.
     */
    private static function applyV5(): void
    {
        if (Database::tableExists('applications')) {
            $sc = Database::first('applications', ['application_id'], 'slug = ?', ['service-centre']);
            if ($sc !== null) {
                $id = (int) $sc['application_id'];
                Database::delete('application_nav', 'application_id = ?', [$id]);
                Database::delete('applications', 'application_id = ?', [$id]);
            }
        }
        if (Database::tableExists('announcements') && Database::columnExists('announcements', 'application')) {
            Database::delete('announcements', 'application = ?', ['service-centre']);
        }
        if (Database::tableExists('saved_searches')) {
            Database::delete('saved_searches', 'application = ?', ['service-centre']);
            Database::delete('saved_searches', 'application = ?', ['app_servicecentre_main']);
        }
        if (Database::tableExists('group_permissions')) {
            foreach (['servicecentre.use', 'servicecentre.search', 'servicecentre.announce', 'servicecentre.settings'] as $perm) {
                Database::delete('group_permissions', 'permission = ?', [$perm]);
            }
        }
        if (Database::tableExists('users')) {
            Database::run(
                "UPDATE users SET home_controller = 'home', home_controller_name = 'Home'
                 WHERE home_controller IN ('application:service-centre', 'app_servicecentre_main', 'app_oneorzerohelpdesk_main')"
            );
        }
    }
}
