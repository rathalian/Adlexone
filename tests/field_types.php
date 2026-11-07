<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Data\FieldTypes;

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
$check($sql === 'field_type = ?', 'a menu match is one bound comparison');
$check($params === ['menu'], 'a menu match binds the current type');

[$sql, $params] = FieldTypes::sqlInList(FieldTypes::TEXT_BOX);
$check($params === ['textBox'], 'a text field binds only textBox');

[$sql, $params] = FieldTypes::sqlInList('');
$check($sql === '1 = 0' && $params === [], 'an empty type matches nothing');

$source = (string) file_get_contents(dirname(__DIR__) . '/src/Data/FieldTypes.php');
$check(!str_contains($source, 'buildTextInput'), 'a text field has no builder alias');
$check(!str_contains($source, 'subMenu'), 'a menu has no submenu alias');
$menu = (string) file_get_contents(dirname(__DIR__) . '/src/Data/MenuOptions.php');
$check(!str_contains($menu, 'importMultiLevelMenus'), 'menu prepare does not import an older menu shape');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
