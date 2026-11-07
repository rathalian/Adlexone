<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Http\Router;

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
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    return is_string($contents) ? $contents : '';
};

$load = static function (array $query): void {
    $_GET = $query;
    $_POST = [];
    Page::reset();
    Request::prepare();
};

$pages = [
    'records' => 'My Items stays the records page',
    'search' => 'Search stays the search page',
    'fields' => 'Fields stays the fields page',
    'security' => 'People stays the security page',
    'rules' => 'Rules stays the rules page',
];
foreach ($pages as $manage => $message) {
    $load(['manage' => $manage]);
    $check(Router::requested() === $manage, $manage . ' is still a public address');
    $check(Router::page('manage=' . $manage) === '?manage=' . $manage, $message);
}

$load(['application' => 'publishing']);
$check(Router::requested() === 'application', 'an application address stays on application');
$check(Router::applicationUrl('publishing') === '?application=publishing', 'Publishing keeps its address');

$load(['manage' => 'records', 'item' => '12']);
$check(Router::engine() === Router::ITEMS, 'opening a record stays on the item screen');
$check(Request::query('option') === 'view', 'opening a record still uses view');

$itemOptions = [
    'mine', 'choose_type', 'new_record', 'add_record', 'view', 'note', 'add_note',
    'delete_note', 'update_record', 'update_owner', 'access', 'update_access', 'files',
    'add_file', 'download_file', 'delete_file', 'delete_record', 'transform',
];
foreach ($itemOptions as $option) {
    $load(['manage' => 'records', 'option' => $option]);
    $check(Router::engine() === Router::ITEMS, $option . ' stays a record action');
}

$searchOptions = [
    'find', 'results', 'saved', 'open_saved', 'delete_saved',
    'run_quick', 'quick', 'edit_saved', 'update_saved',
];
foreach ($searchOptions as $option) {
    $load(['manage' => 'search', 'option' => $option]);
    $check(Router::engine() === Router::SEARCH, $option . ' stays a search action');
}

$english = $read('translations/English.lang.php');
$check(str_contains($english, 'TXT_44 = "My Items"'), 'the records heading is still My Items');
$check(str_contains($read('src/Screen/ItemList.php'), 'TXT_44'), 'the record list still uses the My Items label');
$check(str_contains($read('src/Screen/SearchScreen.php'), "'label' => 'Search'"), 'search still names itself Search');
$check(str_contains($read('src/Screen/SearchResults.php'), 'Search again'), 'search results still offer Search again');
$check(str_contains($read('src/Html/AppHero.php'), 'How to use'), 'an application still offers How to use');
$check(str_contains($read('src/Html/AppHero.php'), 'New record'), 'an application still offers New record');
$check(str_contains($read('src/Screen/FieldsScreen.php'), 'Form::form('), 'fields still builds its form with Form::form');
$check(str_contains($read('src/Screen/SecurityScreen.php'), "'label' => 'People'"), 'security still names itself People');
$check(str_contains($read('src/Screen/RulesScreen.php'), "'title' => 'Rules'"), 'rules still titles the page Rules');
$check(str_contains($read('src/Screen/HomeDashboard.php'), '<h2>Applications</h2>'), 'home still heads the application list');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
