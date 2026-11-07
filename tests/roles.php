<?php
declare(strict_types=1);

/**
 * An existing database replaces the 0–5 ladder with named roles and one break-glass account.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'adlexone-roles-' . getmypid() . '.sqlite';
if (is_file($tmp)) {
    unlink($tmp);
}
define('DSN', 'sqlite:' . $tmp);

use Adlexone\Auth\Permission;
use Adlexone\Auth\Role;
use Adlexone\Data\GroupMembership;
use Adlexone\Database\Database;
use Adlexone\Database\SchemaMigrator;

$failed = 0;
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
function check(bool $ok, string $message): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

Database::exec('CREATE TABLE schema_migrations (version INTEGER NOT NULL PRIMARY KEY, applied_at INTEGER NOT NULL)');
Database::exec('INSERT INTO schema_migrations (version, applied_at) VALUES (19, 0)');
Database::exec(
    'CREATE TABLE users (
        user_id INTEGER PRIMARY KEY,
        user_name TEXT,
        role INTEGER NOT NULL
    )'
);
Database::exec(
    'CREATE TABLE groups (
        group_id INTEGER PRIMARY KEY,
        group_name TEXT NOT NULL,
        description TEXT,
        role INTEGER NOT NULL
    )'
);
Database::exec(
    'CREATE TABLE group_permissions (
        group_id INTEGER NOT NULL,
        permission TEXT NOT NULL,
        PRIMARY KEY (group_id, permission)
    )'
);
Database::exec('CREATE TABLE user_groups (user_id INTEGER NOT NULL, group_id INTEGER NOT NULL, PRIMARY KEY (user_id, group_id))');
Database::exec("INSERT INTO users (user_id, user_name, role) VALUES (1, 'alex', 0), (2, 'blair', 0), (3, 'casey', 4)");
Database::exec("INSERT INTO groups (group_id, group_name, description, role) VALUES (1, 'Managers', '', 3), (2, 'Viewers', '', 5)");
Database::exec("INSERT INTO group_permissions (group_id, permission) VALUES (1, 'records.edit'), (1, 'admin.items')");
Database::exec(
    'CREATE TABLE core_log (
        id INTEGER PRIMARY KEY,
        item_id INTEGER NOT NULL,
        role_id INTEGER,
        group_id INTEGER NOT NULL DEFAULT 0,
        log_text TEXT
    )'
);
Database::exec("INSERT INTO core_log (id, item_id, role_id, group_id, log_text) VALUES
    (1, 1, 5, 0, 'everyone'),
    (2, 1, 4, 0, 'users'),
    (3, 1, 1, 0, 'admins'),
    (4, 1, 0, 0, 'open'),
    (5, 1, 5, 2, 'group')");

SchemaMigrator::migrate();

$userColumns = array_column(Database::rows('PRAGMA table_info(users)'), 'name');
$groupColumns = array_column(Database::rows('PRAGMA table_info(groups)'), 'name');
check(!in_array('role', $userColumns, true), 'a person no longer stores a role number');
check(in_array('break_glass', $userColumns, true), 'a person can be the break-glass account');
check(!in_array('role', $groupColumns, true), 'a group no longer stores a role number');
check(in_array('role_id', $groupColumns, true), 'a group points at a role');

$glass = Database::rows('SELECT user_id FROM users WHERE break_glass = 1');
check(count($glass) === 1 && (int) $glass[0]['user_id'] === 1, 'the lowest full-access person is the one break-glass account');
check((int) Database::row('SELECT break_glass FROM users WHERE user_id = 2')['break_glass'] === 0, 'a second full-access person is an ordinary account');

$blair = Database::row(
    'SELECT r.slug FROM user_groups ug
     INNER JOIN groups g ON g.group_id = ug.group_id
     INNER JOIN roles r ON r.role_id = g.role_id
     WHERE ug.user_id = 2'
);
check(($blair['slug'] ?? '') === Role::SITE_ADMINISTRATOR, 'a second full-access person joins a site administrator group');

$managers = Database::row(
    'SELECT r.slug, r.system FROM groups g INNER JOIN roles r ON r.role_id = g.role_id WHERE g.group_id = 1'
);
check((int) ($managers['system'] ?? 1) === 0, 'a group whose ticks differ from its old number gets a custom role');
$managerPerms = array_column(Database::rows(
    'SELECT rp.permission FROM groups g INNER JOIN role_permissions rp ON rp.role_id = g.role_id WHERE g.group_id = 1'
), 'permission');
check(in_array(Permission::RECORDS_EDIT, $managerPerms, true), 'the custom role keeps edit');
check(!in_array(Permission::RECORDS_ASSIGN, $managerPerms, true), 'the custom role does not regain assign');

$viewers = Database::row(
    'SELECT r.slug FROM groups g INNER JOIN roles r ON r.role_id = g.role_id WHERE g.group_id = 2'
);
check(($viewers['slug'] ?? '') === Role::VIEWER, 'an untouched viewer group keeps the viewer role');
check((int) Database::row('SELECT MAX(version) AS v FROM schema_migrations')['v'] === SchemaMigrator::version(), 'the upgrade reaches the current version');

$logColumns = array_column(Database::rows('PRAGMA table_info(core_log)'), 'name');
check(!in_array('role_id', $logColumns, true), 'a note no longer stores a role number');
check(in_array('audience', $logColumns, true), 'a note stores an audience');
$notes = [];
foreach (Database::rows('SELECT id, audience, group_id FROM core_log ORDER BY id') as $note) {
    $notes[(int) $note['id']] = $note;
}
check(($notes[1]['audience'] ?? null) === '', 'a public note is for everyone');
check(($notes[2]['audience'] ?? null) === Role::USER, 'a user note keeps the user audience');
check(($notes[3]['audience'] ?? null) === Role::SITE_ADMINISTRATOR, 'a site administrator note keeps that audience');
check(($notes[4]['audience'] ?? null) === '', 'an unrestricted old note is for everyone');
check((int) ($notes[5]['group_id'] ?? 0) === 2, 'a group note keeps its group');

$_SESSION['access_user_id'] = 3;
check(GroupMembership::canSeeLogLine(0, '') === true, 'a viewer can read a note for everyone');
check(GroupMembership::canSeeLogLine(0, Role::USER) === false, 'a viewer cannot read a user note');
check(GroupMembership::canSeeLogLine(2, '') === false, 'a viewer outside a group cannot read that group note');
$_SESSION['access_user_id'] = 1;
check(GroupMembership::canSeeLogLine(0, Role::USER) === true, 'the break-glass account can read a user note');

@unlink($tmp);
echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
