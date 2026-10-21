<?php
declare(strict_types=1);

namespace Adlexone\Database;

use Adlexone\Application\Rules;
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
    private const VERSION = 11;

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
            $current = 5;
        }
        if ($current < 6) {
            self::applyV6();
            self::mark(6);
            $current = 6;
        }
        if ($current < 7) {
            self::applyV7();
            self::mark(7);
            $current = 7;
        }
        if ($current < 8) {
            self::applyV8();
            self::mark(8);
            $current = 8;
        }
        if ($current < 9) {
            self::applyV9();
            self::mark(9);
            $current = 9;
        }
        if ($current < 10) {
            self::applyV10();
            self::mark(10);
            $current = 10;
        }
        if ($current < 11) {
            self::applyV11();
            self::mark(11);
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
            "DELETE FROM action_definitions WHERE package_file IN (
                'Time_Manager.actions.php',
                'Email_Notifications.actions.php',
                'Action_Triggers.actions.php',
                'Log_Updates.actions.php'
            )"
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
     * Per-application settings JSON, and announcements scoped by application slug.
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
     * and orphan announcements scoped to it.
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

    /**
     * Application rules: event, field, operator, value, and outcome as columns.
     */
    private static function applyV6(): void
    {
        Database::exec(
            'CREATE TABLE IF NOT EXISTS application_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                application_id INTEGER NOT NULL,
                item_type_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                enabled INTEGER NOT NULL DEFAULT 1,
                event TEXT NOT NULL,
                field_key TEXT NOT NULL DEFAULT "",
                operator TEXT NOT NULL DEFAULT "always",
                match_value TEXT NOT NULL DEFAULT "",
                outcome TEXT NOT NULL,
                recipient TEXT NOT NULL DEFAULT "owner",
                address TEXT NOT NULL DEFAULT "",
                subject TEXT NOT NULL DEFAULT "",
                body TEXT NOT NULL DEFAULT "",
                role_id INTEGER NOT NULL DEFAULT 5,
                action_field TEXT NOT NULL DEFAULT "",
                action_value TEXT NOT NULL DEFAULT ""
            )'
        );
        Database::exec('CREATE INDEX IF NOT EXISTS idx_application_rules_type ON application_rules (item_type_id, event)');
        Database::exec('CREATE INDEX IF NOT EXISTS idx_application_rules_app ON application_rules (application_id)');
    }

    /**
     * A rule can set a field or the owner, as well as email or write a log line.
     */
    private static function applyV7(): void
    {
        if (!Database::tableExists('application_rules')) {
            return;
        }
        if (!Database::columnExists('application_rules', 'action_field')) {
            Database::exec('ALTER TABLE application_rules ADD COLUMN action_field TEXT NOT NULL DEFAULT ""');
        }
        if (!Database::columnExists('application_rules', 'action_value')) {
            Database::exec('ALTER TABLE application_rules ADD COLUMN action_value TEXT NOT NULL DEFAULT ""');
        }
    }

    /**
     * Ordered actions, richer conditions, a run log, and the old action packages.
     */
    private static function applyV8(): void
    {
        if (!Database::tableExists('application_rules')) {
            return;
        }
        foreach ([
            'sort_order' => 'INTEGER NOT NULL DEFAULT 0',
            'match_from' => 'TEXT NOT NULL DEFAULT ""',
            'skip_actor' => 'INTEGER NOT NULL DEFAULT 1',
            'min_log_role' => 'INTEGER NOT NULL DEFAULT 0',
        ] as $column => $type) {
            if (!Database::columnExists('application_rules', $column)) {
                Database::exec('ALTER TABLE application_rules ADD COLUMN ' . $column . ' ' . $type);
            }
        }
        Database::exec(
            'CREATE TABLE IF NOT EXISTS application_rule_steps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rule_id INTEGER NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                outcome TEXT NOT NULL,
                recipient TEXT NOT NULL DEFAULT "owner",
                address TEXT NOT NULL DEFAULT "",
                subject TEXT NOT NULL DEFAULT "",
                body TEXT NOT NULL DEFAULT "",
                role_id INTEGER NOT NULL DEFAULT 5,
                action_field TEXT NOT NULL DEFAULT "",
                action_value TEXT NOT NULL DEFAULT "",
                user_id INTEGER NOT NULL DEFAULT 0,
                group_id INTEGER NOT NULL DEFAULT 0
            )'
        );
        Database::exec('CREATE INDEX IF NOT EXISTS idx_application_rule_steps_rule ON application_rule_steps (rule_id, sort_order)');
        Database::exec(
            'CREATE TABLE IF NOT EXISTS application_rule_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rule_id INTEGER NOT NULL,
                item_id INTEGER NOT NULL,
                ran_at INTEGER NOT NULL,
                status TEXT NOT NULL,
                detail TEXT NOT NULL DEFAULT ""
            )'
        );
        Database::exec('CREATE INDEX IF NOT EXISTS idx_application_rule_runs_rule ON application_rule_runs (rule_id, id)');
        Database::exec('CREATE INDEX IF NOT EXISTS idx_application_rule_runs_item ON application_rule_runs (item_id, id)');
        self::backfillRuleSteps();
        self::migrateActionPackages();
    }

    private static function backfillRuleSteps(): void
    {
        foreach (Database::select('application_rules', '*', '', [], 'id ASC') as $rule) {
            $id = (int) ($rule['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ((int) ($rule['sort_order'] ?? 0) <= 0) {
                Database::update('application_rules', ['sort_order' => $id * 10], 'id = ?', [$id]);
            }
            if (Database::first('application_rule_steps', ['id'], 'rule_id = ?', [$id]) !== null) {
                continue;
            }
            if ((string) ($rule['outcome'] ?? '') === '') {
                continue;
            }
            Database::insert('application_rule_steps', [
                'rule_id' => $id,
                'sort_order' => 0,
                'outcome' => (string) $rule['outcome'],
                'recipient' => (string) ($rule['recipient'] ?? 'owner'),
                'address' => (string) ($rule['address'] ?? ''),
                'subject' => (string) ($rule['subject'] ?? ''),
                'body' => (string) ($rule['body'] ?? ''),
                'role_id' => (int) ($rule['role_id'] ?? 5),
                'action_field' => (string) ($rule['action_field'] ?? ''),
                'action_value' => (string) ($rule['action_value'] ?? ''),
                'user_id' => 0,
                'group_id' => 0,
            ]);
        }
    }

    /**
     * Turn trigger-plus-email package rows into one rule each, then remove them.
     */
    /**
     * Studio, Matters, House, and anything else already installed give way
     * to the case-file applications. The builder installs those on the next request.
     */
    private static function applyV9(): void
    {
        \Adlexone\Application\ApplicationBuilder::removeInstalledApplications();
    }

    /**
     * Retype known date and number fields created as free text.
     */
    private static function applyV10(): void
    {
        if (!Database::tableExists('custom_fields')) {
            return;
        }
        $dates = [
            'Closed on', 'Completed on', 'Settled on', 'Done on', 'Response due',
            'Decided on', 'Due', 'Placed on', 'Signed on',
        ];
        foreach (Database::select('custom_fields', ['custom_field_id', 'custom_field_name', 'field_type']) as $row) {
            $name = (string) ($row['custom_field_name'] ?? '');
            $base = trim(preg_replace('/\s+\([^)]+\)\s*$/', '', $name) ?? $name);
            $type = \Adlexone\support\FieldTypes::normalise((string) ($row['field_type'] ?? ''));
            $id = (int) $row['custom_field_id'];
            if (in_array($base, $dates, true) && $type === \Adlexone\support\FieldTypes::TEXT_BOX) {
                Database::update(
                    'custom_fields',
                    ['field_type' => \Adlexone\support\FieldTypes::DATE, 'validation_type' => 'date'],
                    'custom_field_id = ?',
                    [$id]
                );
            }
            if (in_array($base, ['Amount', 'Reserve'], true) && $type === \Adlexone\support\FieldTypes::TEXT_BOX) {
                Database::update(
                    'custom_fields',
                    ['field_type' => \Adlexone\support\FieldTypes::NUMBER, 'validation_type' => 'numeric'],
                    'custom_field_id = ?',
                    [$id]
                );
            }
        }
    }

    /**
     * Announcements are part of opening an application, not a separate permission.
     */
    private static function applyV11(): void
    {
        $where = "permission GLOB 'app.*.announce' OR permission = 'servicecentre.announce'";
        if (Database::tableExists('group_permissions')) {
            Database::delete('group_permissions', $where);
        }
        if (Database::tableExists('application_nav') && Database::columnExists('application_nav', 'permission')) {
            Database::run('UPDATE application_nav SET permission = \'\' WHERE ' . $where);
        }
        if (Database::tableExists('applications') && Database::columnExists('applications', 'permission')) {
            Database::run(
                "UPDATE applications SET permission = replace(permission, '.announce', '.use') WHERE permission GLOB 'app.*.announce'"
            );
        }
    }

    private static function migrateActionPackages(): void
    {
        if (!Database::tableExists('action_definitions')) {
            return;
        }
        $rows = Database::select('action_definitions', '*', '', [], 'action_id ASC');
        if ($rows === []) {
            return;
        }
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['action_id']] = $row;
        }
        $consumed = [];
        foreach ($rows as $row) {
            $function = (string) ($row['package_function'] ?? '');
            if ($function !== 'TriggerActionCustomField' && $function !== 'TriggerActionSystemField') {
                continue;
            }
            $targetId = (int) ($row['action_data'] ?? 0);
            $target = $byId[$targetId] ?? null;
            $step = $target !== null ? self::stepFromPackage($target) : null;
            if ($step === null) {
                continue;
            }
            $typeId = (int) ($row['item_type_id'] ?? 0);
            if ($typeId <= 0 && $target !== null) {
                $typeId = (int) ($target['item_type_id'] ?? 0);
            }
            $applicationId = self::applicationIdForItemType($typeId);
            if ($applicationId <= 0 || $typeId <= 0) {
                continue;
            }
            $condition = self::conditionFromPackage($row);
            $params = explode('}-{', (string) ($target['action_parameters'] ?? ''));
            $includeActor = strtolower((string) ($params[2] ?? 'yes')) !== 'no';
            Rules::add($applicationId, [
                'item_type_id' => $typeId,
                'name' => (string) ($target['action_name'] ?? $row['action_name'] ?? 'Rule'),
                'enabled' => (string) ($row['enabled'] ?? 'Yes') === 'Yes' ? 1 : 0,
                'event' => $condition['event'],
                'field_key' => $condition['field_key'],
                'operator' => $condition['operator'],
                'match_value' => $condition['match_value'],
                'match_from' => $condition['match_from'],
                'min_log_role' => $condition['min_log_role'],
                'skip_actor' => $includeActor ? 0 : 1,
                'sort_order' => (int) $row['action_id'] * 10,
                'steps' => [$step],
            ]);
            $consumed[(int) $row['action_id']] = true;
            $consumed[$targetId] = true;
        }
        foreach ($rows as $row) {
            $id = (int) $row['action_id'];
            if (isset($consumed[$id])) {
                continue;
            }
            $step = self::stepFromPackage($row);
            if ($step === null) {
                continue;
            }
            $typeId = (int) ($row['item_type_id'] ?? 0);
            $applicationId = self::applicationIdForItemType($typeId);
            if ($applicationId <= 0 || $typeId <= 0) {
                continue;
            }
            $params = explode('}-{', (string) ($row['action_parameters'] ?? ''));
            $includeActor = strtolower((string) ($params[2] ?? 'yes')) !== 'no';
            Rules::add($applicationId, [
                'item_type_id' => $typeId,
                'name' => (string) ($row['action_name'] ?? 'Rule'),
                'enabled' => (string) ($row['enabled'] ?? 'Yes') === 'Yes' ? 1 : 0,
                'event' => Rules::EVENT_UPDATE,
                'operator' => Rules::OP_ALWAYS,
                'skip_actor' => $includeActor ? 0 : 1,
                'sort_order' => $id * 10,
                'steps' => [$step],
            ]);
            $consumed[$id] = true;
        }
        foreach (array_keys($consumed) as $id) {
            Database::delete('action_definitions', 'action_id = ?', [(int) $id]);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private static function stepFromPackage(array $row): ?array
    {
        $function = (string) ($row['package_function'] ?? '');
        if ($function === 'SendEmail') {
            $data = explode('}-{', (string) ($row['action_data'] ?? ''));
            $params = explode('}-{', (string) ($row['action_parameters'] ?? ''));
            $recipient = self::recipientFromPackage((string) ($params[1] ?? 'owner'));
            return [
                'outcome' => Rules::OUTCOME_EMAIL,
                'recipient' => $recipient,
                'address' => trim((string) ($params[0] ?? '')),
                'subject' => (string) ($data[1] ?? ''),
                'body' => (string) ($data[2] ?? ''),
            ];
        }
        if ($function === 'UpdateLog') {
            $data = explode('}-{', (string) ($row['action_data'] ?? ''));
            return [
                'outcome' => Rules::OUTCOME_LOG,
                'body' => (string) ($data[1] ?? ''),
                'role_id' => (int) ($row['action_parameters'] ?? 5),
            ];
        }
        return null;
    }

    private static function recipientFromPackage(string $option): string
    {
        return match ($option) {
            'creator' => Rules::TO_CREATOR,
            'creator_owner' => Rules::TO_BOTH,
            'owner_groups' => Rules::TO_OWNER_GROUPS,
            'creator_groups' => Rules::TO_CREATOR_GROUPS,
            'creator_owner_groups' => Rules::TO_BOTH_GROUPS,
            'groups' => Rules::TO_ITEM_GROUPS,
            'recipients' => Rules::TO_ADDRESS,
            default => Rules::TO_OWNER,
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array{event: string, field_key: string, operator: string, match_value: string, match_from: string, min_log_role: int}
     */
    private static function conditionFromPackage(array $row): array
    {
        $blank = [
            'event' => Rules::EVENT_UPDATE,
            'field_key' => '',
            'operator' => Rules::OP_ALWAYS,
            'match_value' => '',
            'match_from' => '',
            'min_log_role' => 0,
        ];
        $type = (string) ($row['action_type'] ?? '');
        $function = (string) ($row['package_function'] ?? '');
        if ($function === 'TriggerActionSystemField') {
            $map = [
                'user_security' => 'owner',
                'creator_security' => 'creator',
                'item_title' => 'item_title',
                'group_security' => 'groups',
            ];
            $field = $map[(string) ($row['action_condition_pre'] ?? '')] ?? '';
            $blank['event'] = str_starts_with($type, 'create') ? Rules::EVENT_CREATE : Rules::EVENT_UPDATE;
            if (str_contains($type, 'log')) {
                $blank['event'] = Rules::EVENT_LOG;
            }
            if (str_contains($type, 'attachment')) {
                $blank['event'] = Rules::EVENT_ATTACHMENT;
            }
            if ($field !== '') {
                $blank['field_key'] = $field;
                $blank['operator'] = Rules::OP_CHANGED;
            }
            return $blank;
        }
        $post = explode('}-{', (string) ($row['action_condition_post'] ?? ''));
        $pre = explode('}-{', (string) ($row['action_condition_pre'] ?? ''));
        $field = self::packageFieldKey((string) ($post[0] ?? ''));
        $op = (string) ($post[1] ?? '==');
        $value = (string) ($post[2] ?? '');
        $role = (int) ($post[3] ?? 0);
        if ($type === 'create_item_every_item') {
            $blank['event'] = Rules::EVENT_CREATE;
            return $blank;
        }
        if ($type === 'update_item_log_entry') {
            $blank['event'] = Rules::EVENT_LOG;
            $blank['min_log_role'] = $role;
            return $blank;
        }
        if ($type === 'item_attachment') {
            $blank['event'] = Rules::EVENT_ATTACHMENT;
            return $blank;
        }
        if ($type === 'update_item_any_trigger') {
            $blank['field_key'] = $field;
            $blank['operator'] = Rules::OP_CHANGED;
            return $blank;
        }
        if ($type === 'update_item_all_met') {
            $blank['field_key'] = $field !== '' ? $field : self::packageFieldKey((string) ($pre[0] ?? ''));
            $blank['operator'] = Rules::OP_FROM_TO;
            $blank['match_from'] = (string) ($pre[2] ?? '');
            $blank['match_value'] = $value;
            return $blank;
        }
        $blank['event'] = str_starts_with($type, 'create') ? Rules::EVENT_CREATE : Rules::EVENT_UPDATE;
        $blank['field_key'] = $field;
        $blank['operator'] = $op === '!=' || $op === '<>' ? Rules::OP_NEQ : Rules::OP_EQ;
        $blank['match_value'] = $value;
        return $blank;
    }

    private static function packageFieldKey(string $field): string
    {
        if (preg_match('/(\d+)\s*$/', $field, $match) === 1 && str_contains($field, 'field')) {
            return 'custom_field_' . $match[1];
        }
        return match ($field) {
            'user_security' => 'owner',
            'creator_security' => 'creator',
            'item_title' => 'item_title',
            'group_security' => 'groups',
            default => '',
        };
    }

    private static function applicationIdForItemType(int $typeId): int
    {
        if ($typeId <= 0 || !Database::tableExists('applications')) {
            return 0;
        }
        $needle = (string) $typeId;
        foreach (Database::select('applications', ['application_id', 'settings_json'], '', [], 'application_id ASC') as $app) {
            $settings = json_decode((string) ($app['settings_json'] ?? ''), true);
            if (is_array($settings) && (string) ($settings['default_item_type_id'] ?? '') === $needle) {
                return (int) $app['application_id'];
            }
        }
        if (Database::tableExists('application_nav')) {
            foreach (Database::select('application_nav', ['application_id', 'config_json'], '', [], 'nav_id ASC') as $link) {
                $config = json_decode((string) ($link['config_json'] ?? ''), true);
                if (is_array($config) && (string) ($config['item_type_id'] ?? '') === $needle) {
                    return (int) $link['application_id'];
                }
            }
        }
        $first = Database::first('applications', ['application_id'], '', [], 'application_id ASC');
        return (int) ($first['application_id'] ?? 0);
    }
}
