<?php
declare(strict_types=1);

/**
 * A new database stores named roles, a break-glass flag, and note audiences.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'adlexone-roles-' . getmypid() . '.sqlite';
if (is_file($tmp)) {
    unlink($tmp);
}
define('DSN', 'sqlite:' . $tmp);

use Adlexone\Auth\Role;
use Adlexone\Data\GroupMembership;
use Adlexone\Database\Database;

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

Database::ensure();

$userColumns = array_column(Database::rows('PRAGMA table_info(users)'), 'name');
$groupColumns = array_column(Database::rows('PRAGMA table_info(groups)'), 'name');
$logColumns = array_column(Database::rows('PRAGMA table_info(core_log)'), 'name');
check(!in_array('role', $userColumns, true), 'a person does not store a role number');
check(in_array('break_glass', $userColumns, true), 'a person can be the break-glass account');
check(!in_array('role', $groupColumns, true), 'a group does not store a role number');
check(in_array('role_id', $groupColumns, true), 'a group points at a role');
check(!in_array('role_id', $logColumns, true), 'a note does not store a role number');
check(in_array('audience', $logColumns, true), 'a note stores an audience');
check((int) Database::row('SELECT MAX(version) AS v FROM schema_migrations')['v'] === Database::SCHEMA_VERSION, 'a new database is the current version');
check((string) Database::row("SELECT slug FROM roles WHERE role_id = 5")['slug'] === Role::VIEWER, 'the viewer role is seeded');

Database::exec("INSERT INTO users (user_id, user_name, break_glass) VALUES (1, 'alex', 1), (3, 'casey', 0)");
Database::exec("INSERT INTO groups (group_id, group_name, role_id) VALUES (2, 'Viewers', 5)");
Database::exec('INSERT INTO user_groups (user_id, group_id) VALUES (3, 2)');

$_SESSION['access_user_id'] = 3;
check(GroupMembership::canSeeLogLine(0, '') === true, 'a viewer can read a note for everyone');
check(GroupMembership::canSeeLogLine(0, Role::USER) === false, 'a viewer cannot read a user note');
check(GroupMembership::canSeeLogLine(2, '') === true, 'a viewer in the group can read that group note');
check(GroupMembership::canSeeLogLine(9, '') === false, 'a viewer outside a group cannot read that group note');
check(Role::visibleAudiences(3) === [''], 'a viewer reads only notes for everyone');
$_SESSION['access_user_id'] = 1;
check(GroupMembership::canSeeLogLine(0, Role::USER) === true, 'the break-glass account can read a user note');
check(in_array(Role::SITE_ADMINISTRATOR, Role::visibleAudiences(1), true), 'the break-glass account can read a site administrator note');

@unlink($tmp);
echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
