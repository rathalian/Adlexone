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
$check(Request::query('option') === 'show_item', 'opening a record still uses show_item');

$itemOptions = [
    'my_items', 'show_item_types', 'new_item', 'add_item', 'show_item', 'log_entry', 'add_log_entry',
    'delete_log', 'update_item', 'update_owner', 'change_security', 'update_security', 'show_attachments',
    'add_attachment', 'download_attachment', 'delete_attachment', 'delete_item', 'transform_item',
];
foreach ($itemOptions as $option) {
    $load(['manage' => 'records', 'option' => $option]);
    $check(Router::engine() === Router::ITEMS, $option . ' stays a record action');
}

$searchOptions = [
    'show_item_search', 'show_search_results', 'show_saved_searches', 'saved_search', 'delete_saved_search',
    'quick_search', 'show_quick_search', 'edit_saved_search', 'update_saved_search',
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
