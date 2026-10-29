<?php
declare(strict_types=1);

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
};

$screens = [
    'ItemScreen.php',
    'SearchScreen.php',
    'SecurityScreen.php',
    'FieldsScreen.php',
    'PrintScreen.php',
    'SiteSettingsScreen.php',
];
foreach ($screens as $name) {
    $text = (string) file_get_contents(dirname(__DIR__) . '/src/Screen/' . $name);
    $check(preg_match('/@\$_(GET|POST|REQUEST)\b/', $text) !== 1, $name . ' does not silence a request read');
}

$search = (string) file_get_contents(dirname(__DIR__) . '/src/Screen/SearchScreen.php');
$item = (string) file_get_contents(dirname(__DIR__) . '/src/Screen/ItemScreen.php');
$check(!str_contains($search, "\$_GET['controller']"), 'search links do not read the controller straight from the query');
$check(!str_contains($item, "\$_GET['controller']"), 'item links do not read the controller straight from the query');
$check(str_contains($item, 'Request::queryPart(\'controller\')'), 'the item screen encodes the controller for its links');
$check(str_contains($search, 'Request::queryPart(\'controller\')'), 'the search screen encodes the controller for its links');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
