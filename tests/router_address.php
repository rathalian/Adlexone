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

$load = static function (array $query): void {
    $_GET = $query;
    $_POST = [];
    Page::reset();
    Request::prepare();
};

$load([
    'controller' => 'item_management_manage',
    'subcontroller' => 'item_management_manage',
    'option' => 'my_items',
]);
$check(Request::query('manage') === '', 'a controller address is not copied onto manage');
$check(Router::requested() === 'home', 'a controller address opens home');

$load(['controller' => 'security', 'option' => 'manage_users']);
$check(Router::requested() === 'home', 'a controller alias does not open that manage screen');

$load(['screen' => 'palette']);
$check(Router::requested() === 'home', 'screen is not an address');

$load(['engine' => 'items', 'option' => 'my_items']);
$check(Request::query('manage') === '', 'engine is not an address');
$check(Router::requested() === 'home', 'engine does not choose the page');

$load(['subcontroller' => 'search_management_manage', 'option' => 'show_quick_search']);
$check(Router::requested() === 'home', 'subcontroller does not choose the page');

$load(['manage' => 'records', 'option' => 'my_items']);
$check(Router::requested() === 'records', 'manage=records asks for records');
$check(Router::matches(Router::ITEMS), 'manage=records opens the item screen');
$check(Router::engine() === Router::ITEMS, 'my_items selects the item screen');

$load(['manage' => 'records', 'item' => '44']);
$check(Router::engine() === Router::ITEMS, 'item selects the item screen');
$check(Request::query('item_id') === '44', 'item is copied to the internal item id');
$check(Request::query('option') === 'show_item', 'item opens the record');

$load(['manage' => 'records', 'item_id' => '44']);
$check(Router::engine() === null, 'item_id alone does not select the record');

$load(['manage' => 'items']);
$check(Router::requested() === 'items', 'manage=items keeps the fields name');
$check(Router::matches('manage_items'), 'manage=items opens Fields');
$check(!Router::matches(Router::ITEMS), 'manage=items does not open the record list');

$load(['manage' => 'print', 'item' => '44']);
$check(Router::matches('full_page_view'), 'manage=print opens the print screen');

$load(['manage' => 'activity']);
$check(Router::matches('home_activity'), 'manage=activity opens recent activity');

$load(['manage' => 'assigned', 'focus' => 'overdue']);
$check(Router::matches('home_assigned'), 'manage=assigned opens assigned work');
$check(Request::query('focus') === 'overdue', 'a screen filter stays on the query');

$load(['manage' => 'record-types']);
$check(Router::matches('manage_item_types'), 'manage=record-types opens record types');
$load(['manage' => 'item-types']);
$check(Router::requested() === 'home', 'manage=item-types does not open record types');

$load(['manage' => 'palette']);
$check(Router::requested() === 'palette', 'manage=palette asks for the palette');

foreach ([
    'item_management_manage' => 'the record list',
    'search_management_manage' => 'search',
    'full_page_view' => 'print',
    'home_activity' => 'recent activity',
    'home_assigned' => 'assigned work',
    'manage_fields' => 'Fields',
    'manage_security' => 'People',
    'manage_items' => 'Fields',
] as $internal => $screen) {
    $load(['manage' => $internal]);
    $check(Router::requested() === 'home', 'manage=' . $internal . ' does not open ' . $screen);
}

$check(Router::manageUrl('records') === '?manage=records', 'records is a manage address');
$check(Router::manageUrl('search') === '?manage=search', 'search is a manage address');
$check(Router::manageUrl('activity', 'scope=mine') === '?manage=activity&scope=mine', 'activity keeps its filter after the place');
$check(Router::manageUrl('assigned', 'focus=overdue') === '?manage=assigned&focus=overdue', 'assigned keeps focus after the place');
$check(Router::manageUrl('palette') === '?manage=palette', 'palette is a manage address');
$check(Router::manageUrl('print', 'item=44') === '?manage=print&item=44', 'print is its own place');
$check(Router::manageUrl('search', 'option=show_quick_search') === '?manage=search&option=show_quick_search', 'a search action stays on manage=search');

$load([
    'application' => 'publishing',
    'nav' => '1',
    'option' => 'show_item',
    'item' => '44',
]);
$check(Request::query('manage') === '', 'an application address is not copied onto manage');
$check(Request::query('app') === 'publishing', 'application is copied to app');
$check(Router::requested() === 'application', 'an application address opens the application');
$check(Router::applicationUrl('publishing', 1) === '?application=publishing&nav=1', 'a publishing link stays on application and nav');
$check(Router::engine() === Router::ITEMS, 'an item inside Publishing selects the item screen');
Page::current()->enterApplication('publishing', 1);
$check(
    Router::applicationUrl(Page::current()->applicationSlug(), Page::current()->navId()) === '?application=publishing&nav=1',
    'the next link inside Publishing is the application address'
);

$load(['app' => 'publishing']);
$check(Router::requested() === 'home', 'app alone does not open an application');
$check(Request::query('app') === 'publishing', 'app stays on the query for a screen that reads it');

$check(Router::homeTarget('application:publishing') === '?application=publishing', 'a stored application home stays on the application address');
$check(Router::homeTarget('home') === './', 'a stored home opens the site root');
$check(Router::homeTarget('security') === '?manage=security', 'a stored security home uses manage');
$check(Router::homeTarget('records') === '?manage=records', 'a stored records home uses manage=records');
$check(Router::homeTarget('print') === '?manage=print', 'a stored print home uses manage=print');
$check(Router::homeTarget('item_management_manage') === './', 'a stored internal item name opens the site root');
$check(Router::homeTarget('home_activity') === './', 'a stored internal activity name opens the site root');
$check(Router::homeTarget('full_page_view') === './', 'a stored internal print name opens the site root');
$check(Router::homeTarget('quick_launch') === './', 'a stored quick launch opens the site root');
foreach (['home', 'application:publishing', 'security', 'records', 'print', 'item_management_manage', 'home_activity'] as $stored) {
    $target = Router::homeTarget($stored);
    $check(!str_contains($target, 'controller=') && !str_contains($target, 'subcontroller='), $stored . ' does not use controller in the address');
}

$router = (string) file_get_contents(dirname(__DIR__) . '/src/Http/Router.php');
$check(!str_contains($router, 'function absorb('), 'absorb is gone');
$check(!str_contains($router, 'function continueUrl'), 'continueUrl is gone');
$check(!str_contains($router, "query('controller')"), 'the router does not read controller');
$check(!str_contains($router, "query('subcontroller')"), 'the router does not read subcontroller');
$check(!str_contains($router, "query('screen')"), 'the router does not read screen');
$check(!str_contains($router, "query('engine')"), 'the router does not read engine');
$check(!str_contains($router, '?controller='), 'the router does not build a controller address');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
