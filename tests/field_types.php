<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\support\Database;
use Adlexone\support\FieldTypes;

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
};

[$sql, $params] = FieldTypes::sqlInList(FieldTypes::MENU);
$check($sql === 'field_type IN (?, ?, ?, ?, ?)', 'a menu match uses a placeholder for the type and each alias');
$check($params === ['menu', 'buildSelectDropdown', 'subMenu', 'subMenuChild', 'multiLevelMenu'], 'a menu match binds the type and the older names');
$check(!str_contains($sql, 'subMenu'), 'the alias text is not written into the SQL');

[$sql, $params] = FieldTypes::sqlInList(FieldTypes::MENU, false);
$check($sql === 'field_type IN (?, ?, ?, ?)', 'a menu rewrite leaves the current type out');
$check($params === ['buildSelectDropdown', 'subMenu', 'subMenuChild', 'multiLevelMenu'], 'a menu rewrite binds only the older names');

[$sql, $params] = FieldTypes::sqlInList(FieldTypes::TEXT_BOX);
$check($params === ['textBox', 'buildTextInput'], 'a text field keeps its builder alias');

[$sql, $params] = FieldTypes::sqlInList('password');
$check($sql === 'field_type IN (?)', 'a type with no alias is one placeholder');
$check($params === ['password'], 'a type with no alias is bound as itself');

[$sql, $params] = FieldTypes::sqlInList('password', false);
$check($sql === '1 = 0' && $params === [], 'an empty alias list matches nothing');

$source = (string) file_get_contents(dirname(__DIR__) . '/src/support/MenuOptions.php');
$check(str_contains($source, 'FieldTypes::sqlInList(FieldTypes::MENU, false)'), 'menu prepare asks for the older menu names');
$check(!str_contains($source, "field_type IN ('subMenu'"), 'menu prepare does not interpolate the alias list');

if (!defined('DSN')) {
    define('DSN', 'sqlite::memory:');
}
$dsn = DSN;
Database::exec('CREATE TABLE custom_fields (custom_field_id INTEGER PRIMARY KEY, field_type TEXT)', $dsn);
Database::exec("INSERT INTO custom_fields (field_type) VALUES ('subMenu'), ('textBox'), ('menu'), ('multiLevelMenu'), ('buildSelectDropdown')", $dsn);
[$clause, $bound] = FieldTypes::sqlInList(FieldTypes::MENU, false);
Database::run("UPDATE custom_fields SET field_type = 'menu' WHERE {$clause}", $bound, $dsn);
$types = array_column(Database::rows('SELECT field_type FROM custom_fields ORDER BY custom_field_id', [], $dsn), 'field_type');
$check($types === ['menu', 'textBox', 'menu', 'menu', 'menu'], 'the bound update rewrites only the older menu names');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
