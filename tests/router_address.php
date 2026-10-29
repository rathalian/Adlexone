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
$check(Request::query('manage') === 'records', 'the old item address becomes manage=records');
$check(Request::query('controller') === 'item_management_manage', 'the original controller stays in the query');
$check(Request::query('subcontroller') === 'item_management_manage', 'subcontroller stays so the engine can still see it');
$check(Router::requested() === 'records', 'the old item address asks for records');
$check(Router::matches(Router::ITEMS), 'the old item address opens the item screen');
$check(Router::engine() === Router::ITEMS, 'the old item address still selects the item engine');
$check(Router::manageUrl('records') === '?manage=records', 'records is a manage address');
$check(Router::manageUrl('search') === '?manage=search', 'search is a manage address');
$check(Router::manageUrl('activity', 'scope=mine') === '?manage=activity&scope=mine', 'activity keeps its filter after the place');
$check(Router::manageUrl('assigned', 'focus=overdue') === '?manage=assigned&focus=overdue', 'assigned keeps focus after the place');
$check(Router::manageUrl('palette') === '?manage=palette', 'palette is a manage address');
$check(Router::manageUrl('print', 'item=44') === '?manage=print&item=44', 'print is its own place');

$load(['manage' => 'records', 'option' => 'my_items']);
$check(Router::requested() === 'records', 'manage=records asks for records');
$check(Router::matches(Router::ITEMS), 'manage=records opens the item screen');

$load(['manage' => 'items']);
$check(Router::requested() === 'items', 'manage=items keeps the fields name');
$check(Router::matches('manage_items'), 'manage=items opens Fields');
$check(!Router::matches(Router::ITEMS), 'manage=items does not open the record list');

$load(['controller' => 'items', 'option' => 'manage_fields']);
$check(Request::query('manage') === 'items', 'controller=items becomes manage=items');
$check(Router::matches('manage_items'), 'controller=items still opens Fields');

$load(['controller' => 'security', 'option' => 'manage_users']);
$check(Request::query('manage') === 'security', 'a manage alias arriving as controller becomes manage');
$check(Request::query('controller') === 'security', 'the original controller alias stays in the query');

$load(['controller' => 'administration_security']);
$check(Request::query('manage') === 'security', 'an older administration name becomes the public manage name');

$load(['controller' => 'full_page_view', 'option' => 'print_item', 'item_id' => '44']);
$check(Request::query('manage') === 'print', 'full_page_view becomes manage=print');
$check(Router::matches('full_page_view'), 'manage=print opens the print screen');

$load(['controller' => 'home_activity']);
$check(Request::query('manage') === 'activity', 'home_activity becomes manage=activity');
$check(Router::matches('home_activity'), 'manage=activity opens recent activity');

$load(['controller' => 'home_assigned', 'focus' => 'overdue']);
$check(Request::query('manage') === 'assigned', 'home_assigned becomes manage=assigned');
$check(Request::query('focus') === 'overdue', 'a screen filter stays on the query');

$load(['controller' => 'palette']);
$check(Request::query('manage') === 'palette', 'palette becomes manage=palette');
$check(Router::requested() === 'palette', 'manage=palette asks for the palette');

$load(['screen' => 'full_page_view']);
$check(Request::query('manage') === 'print', 'screen=full_page_view folds into manage=print');

$load(['screen' => 'palette']);
$check(Request::query('manage') === 'palette', 'screen=palette folds into manage=palette');

$load(['engine' => 'items', 'option' => 'my_items']);
$check(Request::query('manage') === 'records', 'engine=items becomes manage=records');
$check(Router::engine() === Router::ITEMS, 'engine=items still selects the item engine');

$load(['engine' => 'search', 'option' => 'show_quick_search']);
$check(Request::query('manage') === 'search', 'engine=search becomes manage=search');
$check(Router::manageUrl('search', 'option=show_quick_search') === '?manage=search&option=show_quick_search', 'a search action stays on manage=search');

$load(['subcontroller' => 'search_management_manage', 'option' => 'show_quick_search']);
$check(Request::query('manage') === 'search', 'a search subcontroller becomes manage=search');
$check(Request::query('subcontroller') === 'search_management_manage', 'the search subcontroller stays in the query');

$load([
    'application' => 'publishing',
    'nav' => '1',
    'engine' => 'items',
    'option' => 'show_item',
    'item' => '44',
]);
$check(Request::query('manage') === '', 'an application address is not copied onto manage');
$check(Request::query('app') === 'publishing', 'application is still copied to app');
$check(Router::requested() === 'application', 'an application address opens the application');
$check(Router::applicationUrl('publishing', 1) === '?application=publishing&nav=1', 'a publishing link stays on application and nav');
Page::current()->enterApplication('publishing', 1);
$check(
    Router::applicationUrl(Page::current()->applicationSlug(), Page::current()->navId()) === '?application=publishing&nav=1',
    'the next link inside Publishing is the application address'
);
$check(Router::engine() === Router::ITEMS, 'an item inside Publishing still selects the item engine');

$load([
    'controller' => 'application',
    'app' => 'publishing',
    'subcontroller' => 'item_management_manage',
    'option' => 'my_items',
]);
$check(Request::query('manage') === '', 'an application controller is not rewritten to records');
$check(Request::query('subcontroller') === 'item_management_manage', 'the application subcontroller stays in the query');
$check(Router::requested() === 'application', 'controller=application still opens the application');

$load(['controller' => 'home']);
$check(Request::query('manage') === '', 'home is not given a manage name');
$check(Router::requested() === 'home', 'controller=home still opens home');

$check(Router::homeTarget('application:publishing') === '?application=publishing', 'a stored application home stays on the application address');
$check(Router::homeTarget('home') === './', 'a stored home opens the site root');
$check(Router::homeTarget('security') === '?manage=security', 'a stored security home uses manage');
$check(Router::homeTarget('item_management_manage') === '?manage=records', 'a stored item screen uses manage=records');
$check(Router::homeTarget('home_activity') === '?manage=activity', 'a stored activity home uses manage=activity');
$check(Router::homeTarget('full_page_view') === '?manage=print', 'a stored print screen uses manage=print');
$check(Router::homeTarget('manage_security') === '?manage=security', 'a stored security screen uses manage=security');
$check(Router::homeTarget('manage_fields') === '?manage=fields', 'a stored fields screen uses manage=fields');
$check(Router::homeTarget('quick_launch') === './', 'a stored quick launch opens the site root');
$check(Router::homeTarget('app_servicecentre_main') === './', 'an unknown stored home opens the site root');
foreach (['home', 'application:publishing', 'security', 'item_management_manage', 'home_activity', 'full_page_view', 'manage_fields', 'print', 'quick_launch', 'app_servicecentre_main'] as $stored) {
    $target = Router::homeTarget($stored);
    $check(!str_contains($target, 'controller=') && !str_contains($target, 'subcontroller='), $stored . ' does not use controller in the address');
}
$router = (string) file_get_contents(dirname(__DIR__) . '/src/Http/Router.php');
$check(!str_contains($router, 'function continueUrl'), 'continueUrl is gone');
$check(!str_contains($router, "Request::set('controller'"), 'open does not write controller');
$check(!str_contains($router, '?controller='), 'the router does not build a controller address');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
