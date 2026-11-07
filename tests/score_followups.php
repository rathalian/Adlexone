<?php
declare(strict_types=1);

/**
 * The announcement body is escaped, a stored menu link cannot revive an object,
 * and the home page binds the record ids it asks for.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Data\MenuOptions;
use Adlexone\Database\Database;
use Adlexone\Screen\HomeRecords;

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
};

$root = dirname(__DIR__);
$announcements = (string) file_get_contents($root . '/src/Screen/AnnouncementsScreen.php');
$check(str_contains($announcements, "nl2br(Html::e((string) \$row['message']), false)"), 'an announcement body is escaped before the line breaks');
$check(!str_contains($announcements, "notice__body\">' . \$row['message']"), 'an announcement body is not written straight into the page');

$menus = (string) file_get_contents($root . '/src/Data/MenuOptions.php');
$check(str_contains($menus, "['allowed_classes' => false]"), 'a stored menu link cannot revive a class');
$check(!str_contains($menus, 'custom_field_id = " .'), 'a menu lookup does not concatenate a field id');
$check(!str_contains($menus, 'menu_value_id = " .'), 'a menu lookup does not concatenate a value id');

$home = (string) file_get_contents($root . '/src/Screen/HomeDashboard.php');
$records = (string) file_get_contents($root . '/src/Screen/HomeRecords.php');
$check(str_contains($home, '<h2>Applications</h2>'), 'the home page still names the applications section');
$check(!str_contains($home . $records, "IN (' . implode(',', array_map('intval'"), 'a home query does not interpolate an id list');
$check(substr_count($home, "\n") < 800, 'the home page is under 800 lines');

if (!defined('DSN')) {
    define('DSN', 'sqlite::memory:');
}
$dsn = DSN;

class StoredMenu
{
    public function __wakeup(): void
    {
        file_put_contents('/tmp/menu-wakeup', 'woke');
    }
}

@unlink('/tmp/menu-wakeup');
Database::exec(
    'CREATE TABLE custom_fields (custom_field_id INTEGER PRIMARY KEY, custom_field_name TEXT, field_type TEXT, sub_menu INTEGER, menu_relationship TEXT, menu_value_links TEXT)',
    $dsn
);
Database::exec(
    'CREATE TABLE custom_field_menu_values (menu_value_id INTEGER PRIMARY KEY, custom_field_id INTEGER, menu_value TEXT, parent_menu_value_id INTEGER NOT NULL DEFAULT 0, sub_menu_values TEXT)',
    $dsn
);
Database::exec(
    'CREATE TABLE items (item_id INTEGER PRIMARY KEY, item_title TEXT, item_type_id INTEGER, create_date INTEGER, core_log_updated INTEGER, user_security INTEGER, creator_security INTEGER)',
    $dsn
);
Database::insert('custom_field_menu_values', [
    'menu_value_id' => 41,
    'custom_field_id' => 7,
    'menu_value' => 'Barton',
    'parent_menu_value_id' => 0,
]);
Database::insert('custom_fields', [
    'custom_field_id' => 9,
    'custom_field_name' => 'Place',
    'field_type' => 'multiLevelMenu',
    'sub_menu' => 0,
    'menu_relationship' => '7,1',
    'menu_value_links' => serialize([[7 => 41]]),
]);
Database::insert('custom_fields', [
    'custom_field_id' => 10,
    'custom_field_name' => 'Unsafe',
    'field_type' => 'multiLevelMenu',
    'sub_menu' => 0,
    'menu_relationship' => '8,1',
    'menu_value_links' => serialize(new StoredMenu()),
]);

MenuOptions::prepare();
$imported = Database::rows(
    'SELECT menu_value FROM custom_field_menu_values WHERE custom_field_id = ? ORDER BY menu_value_id',
    [9],
    $dsn
);
$check(array_column($imported, 'menu_value') === ['Barton'], 'a stored list of menu values is still imported');
$unsafe = Database::rows('SELECT menu_value_id FROM custom_field_menu_values WHERE custom_field_id = ?', [10], $dsn);
$check($unsafe === [], 'a stored object is not imported as a menu value');
$check(!is_file('/tmp/menu-wakeup'), 'a stored object is not woken while the menu is read');

$keep = (int) Database::insert('custom_field_menu_values', [
    'custom_field_id' => 9,
    'menu_value' => 'Kingston',
    'parent_menu_value_id' => 0,
]);
$drop = (int) Database::insert('custom_field_menu_values', [
    'custom_field_id' => 9,
    'menu_value' => 'Civic',
    'parent_menu_value_id' => 0,
]);
MenuOptions::delete(9, $drop);
$left = array_column(
    Database::rows('SELECT menu_value FROM custom_field_menu_values WHERE custom_field_id = ? ORDER BY menu_value', [9], $dsn),
    'menu_value'
);
$check($left === ['Barton', 'Kingston'], 'deleting a menu value removes that value and leaves the others');
$check($keep > 0, 'the kept menu value was stored');

$now = time();
Database::insert('items', [
    'item_id' => 1,
    'item_title' => 'Draft',
    'item_type_id' => 4,
    'create_date' => $now,
    'core_log_updated' => $now,
    'user_security' => 2,
]);
Database::insert('items', [
    'item_id' => 2,
    'item_title' => 'Other',
    'item_type_id' => 9,
    'create_date' => $now,
    'core_log_updated' => $now,
    'user_security' => 2,
]);
$stats = HomeRecords::stats([4]);
$check(($stats[4]['n'] ?? 0) === 1 && !isset($stats[9]), 'a home count asks for one record type and gets that type');
$weekly = HomeRecords::weekly([4]);
$check(array_sum($weekly[4] ?? []) === 1, 'a home week count includes the record added this week');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
