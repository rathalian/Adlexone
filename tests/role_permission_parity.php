<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Application\ApplicationAccess;
use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
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

$previous = static function (int $userRole, int $ceiling): bool {
    if (ApplicationAccess::satisfiesLegacyRole($ceiling)) {
        return true;
    }
    if (isset($_SESSION['access_permissions'])) {
        $userRole = Access::legacyRole();
    }

    return $ceiling >= $userRole;
};

foreach ([0, 1, 2, 3, 4, 5] as $role) {
    $_SESSION['access_role_id'] = $role;
    $_SESSION['access_permissions'] = Permission::forLegacyRole($role);
    foreach ([0, 1, 2, 3, 4, 5] as $ceiling) {
        $check(
            Access::allows($ceiling) === $previous($role, $ceiling),
            "role {$role} ceiling {$ceiling} matches the previous gate"
        );
    }
}

$_SESSION['access_role_id'] = 5;
unset($_SESSION['access_permissions']);
$check(Access::allows(5) === true, 'a signed-in person with no permission list can open an ordinary page');
$check(Access::allows(2) === false, 'a signed-in person with no permission list cannot administer');

$named = [
    5 => 'allowsUse',
    4 => 'allowsOpen',
    3 => 'allowsEdit',
    2 => 'allowsControl',
    1 => 'allowsAdminister',
    0 => 'allowsSystem',
];
$_SESSION['access_role_id'] = 1;
$_SESSION['access_permissions'] = Permission::forLegacyRole(1);
foreach ($named as $ceiling => $method) {
    $check(Access::$method() === Access::allows($ceiling), $method . ' uses ceiling ' . $ceiling);
}
$check(Access::show('save', 1) === 'save', 'an administrator sees an administer-only action');
$check(Access::show('save', 0) === null, 'an administrator does not see a system-only action');

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
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(dirname(__DIR__) . '/src/Screen', FilesystemIterator::SKIP_DOTS)
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
        $screen .= $line . "\n";
    }
}
$check(!str_contains($screen, 'access_role_id'), 'screens do not read the session role number');
$check(!str_contains($screen, '$_GET'), 'screens do not read the query superglobal');
$check(!str_contains($screen, '$_POST'), 'screens do not read the posted superglobal');
$check(!str_contains($screen, 'terminateIfRoleNotAllowed'), 'screens do not call the numbered role gate');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
