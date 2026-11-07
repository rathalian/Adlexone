<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Auth\Role;
use Adlexone\Http\Page;
use Adlexone\Http\Request;

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
};

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
Page::reset();
unset($_GET['app'], $_SESSION['application_slug']);

unset($_SESSION['access_user_id'], $_SESSION['access_role_id'], $_SESSION['access_permissions']);
$check(Role::holderRank([]) === Role::VIEWER, 'a person with no permissions stands as a viewer');
$check(Access::canEditRecords() === false, 'a person with no permissions cannot edit');
$check(Access::canAdministerSite() === false, 'a person with no permissions cannot administer the site');

$_SESSION['access_permissions'] = Role::systemGrants()[Role::SITE_ADMINISTRATOR];
$check(Role::holderRank($_SESSION['access_permissions']) === Role::SITE_ADMINISTRATOR, 'a site administrator stands as a site administrator');
$check(Access::canAdministerSite() === true, 'a site administrator can administer the site');
$check(Access::can(Permission::ADMIN_SYSTEM) === false, 'a site administrator does not hold sign-in settings');
$check(Access::canControlRecords() === true, 'a site administrator can delete records');

$_SESSION['access_permissions'] = array_merge([Permission::ADMIN_SYSTEM], Role::systemGrants()[Role::SITE_ADMINISTRATOR]);
$check(Role::holderRank($_SESSION['access_permissions']) === 'break-glass', 'sign-in settings stand with the break-glass account');
$check(Access::can(Permission::ADMIN_SYSTEM) === true, 'full access holds sign-in settings');

$_SESSION['access_permissions'] = [Permission::RECORDS_EDIT, Permission::APP_ACCESS];
$check(Access::outputIfAllowed('save', Permission::RECORDS_EDIT) === 'save', 'edit permission shows a save action');
$check(Access::outputIfAllowed('save', Permission::RECORDS_ASSIGN) === null, 'edit permission hides an assign action');

$recordGrants = [
    Role::SITE_ADMINISTRATOR => [Permission::RECORDS_CONTROL, Permission::RECORDS_ASSIGN, Permission::RECORDS_EDIT],
    Role::ADMINISTRATOR => [Permission::RECORDS_CONTROL, Permission::RECORDS_ASSIGN, Permission::RECORDS_EDIT],
    Role::MANAGER => [Permission::RECORDS_ASSIGN, Permission::RECORDS_EDIT],
    Role::USER => [Permission::RECORDS_EDIT],
    Role::VIEWER => [],
];
foreach ($recordGrants as $role => $expected) {
    $granted = Role::systemGrants()[$role];
    foreach ([Permission::RECORDS_CONTROL, Permission::RECORDS_ASSIGN, Permission::RECORDS_EDIT] as $permission) {
        $keeps = in_array($permission, $expected, true);
        $check(
            in_array($permission, $granted, true) === $keeps,
            $role . ' ' . ($keeps ? 'keeps' : 'does not keep') . ' ' . $permission
        );
    }
    $_SESSION['access_permissions'] = $granted;
    $check(Access::canEditRecords() === in_array(Permission::RECORDS_EDIT, $granted, true), $role . ' edit follows the record permission');
    $check(Access::canAssignRecords() === in_array(Permission::RECORDS_ASSIGN, $granted, true), $role . ' assign follows the record permission');
    $check(Access::canControlRecords() === in_array(Permission::RECORDS_CONTROL, $granted, true), $role . ' control follows the record permission');
    $check(Role::holderRank($granted) === $role, $role . ' keeps its standing');
}
$_SESSION['access_permissions'] = [Permission::APP_ACCESS];
$check(Access::canEditRecords() === false, 'signing in does not edit a record');
$check(Access::canAssignRecords() === false, 'signing in does not assign an owner');
$_SESSION['access_permissions'] = [Permission::RECORDS_EDIT];
$check(Access::canEditRecords() === true, 'the edit permission saves a record');
$check(Access::canAssignRecords() === false, 'the edit permission does not assign an owner');
$check(Role::recordLevel([Permission::RECORDS_EDIT, Permission::ADMIN_ITEMS]) === 'edit', 'a field permission does not widen record power');
$check(Role::recordLevel([Permission::RECORDS_ASSIGN]) === 'assign', 'assign is the assign record power');
$check(Role::recordLevel([Permission::RECORDS_CONTROL]) === 'control', 'control is the control record power');
$check(Role::ownerSlug('5') === Role::VIEWER, 'an owner setting of 5 is the viewer role');
$check(Role::ownerSlug('viewer') === Role::VIEWER, 'an owner setting can already be the viewer role');
$check(Role::reaches(Role::USER, Role::VIEWER) === true, 'a user can own a record when viewers can');
$check(Role::reaches(Role::VIEWER, Role::USER) === false, 'a viewer is left off a user owner list');

$_GET = ['name' => '  Reid  ', 'go' => '', 'f' => ['a' => '1'], 'n' => '0'];
$_POST = ['title' => '  Note  ', 'empty' => ''];
$check(Request::queryValue('missing') === null, 'a missing query key stays missing');
$check(Request::queryValue('name') === 'Reid', 'a query string is trimmed');
$check(Request::queryValue('n') === '0', 'a query zero stays a zero');
$check(Request::queryValue('f') === ['a' => '1'], 'a query list is left as a list');
$check(Request::hasQueryKey('go') === true, 'an empty query key is still present');
$check(Request::blankQuery('go') === true, 'an empty query key is blank');
$check(Request::blankQuery('n') === true, 'a query zero stays blank, matching empty()');
$check(Request::postedValue('title') === 'Note', 'a posted string is trimmed');
$check(Request::postedValue('missing') === null, 'a missing posted key stays missing');
$check(Request::hasPostedKey('empty') === true, 'an empty posted key is still present');
Request::setPosted('title', 'Kept');
$check(Request::postedValue('title') === 'Kept', 'a posted value can be replaced');
Request::forgetPosted('title');
$check(Request::hasPostedKey('title') === false, 'a posted value can be dropped');

$screen = '';
$product = '';
foreach (['Screen', 'Html', 'Data'] as $dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__) . '/src/' . $dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $chunk = file_get_contents($file->getPathname());
        if (!is_string($chunk)) {
            continue;
        }
        foreach (explode("\n", $chunk) as $line) {
            $trim = ltrim($line);
            if (str_starts_with($trim, '//') || str_starts_with($trim, '*') || str_starts_with($trim, '#')) {
                continue;
            }
            if ($dir === 'Screen') {
                $screen .= $line . "\n";
            }
            $product .= $line . "\n";
        }
    }
}
$check(!str_contains($screen, 'access_role_id'), 'screens do not read the session role number');
$check(!str_contains($screen, '$_GET'), 'screens do not read the query superglobal');
$check(!str_contains($screen, '$_POST'), 'screens do not read the posted superglobal');
$check(!str_contains($screen, 'terminateIfRoleNotAllowed'), 'screens do not call the numbered role gate');
$check(!str_contains($product, 'legacyRole('), 'record checks do not call the role number');
$check(!str_contains($product, 'audienceRank('), 'record checks do not call the old audience rank');
$check(!str_contains($product, 'recordRank('), 'record checks do not call the record rank');
$check(!str_contains($product, 'logAudience('), 'notes do not call the log audience number');
$check(!str_contains($product, 'toDelimited('), 'group saves do not write the old group string');
$check(!str_contains($screen, "hidden('role_id'"), 'a note form does not post a role number');
$check(!str_contains($screen, 'Access::allows('), 'screens do not use a numbered ceiling');
$check(!str_contains($screen, "['role']"), 'screens do not read the stored role number');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
