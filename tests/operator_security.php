<?php
declare(strict_types=1);

/**
 * Site manager: dashboard markup, and a stress test of the management site.
 * The HTTP checks run against a local operator server started by this script.
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Adlexone\Http\Page;
use Adlexone\Platform\DefaultAccount;
use Adlexone\Platform\OperatorScreen;
use Adlexone\Platform\PlatformDb;
use Adlexone\Platform\Runtime;
use Adlexone\Platform\SiteUsage;

$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$check = static function (bool $ok, string $message) use ($fail): void {
    if (!$ok) {
        $fail($message);
    }
};

$tmp = sys_get_temp_dir() . '/adlexone-operator-' . bin2hex(random_bytes(4));
$stores = $tmp . '/adlexone-stores';
$platform = $tmp . '/platform.sqlite';
$server = null;
$serverPid = 0;
register_shutdown_function(static function () use (&$server, &$serverPid, $tmp): void {
    if (is_resource($server)) {
        if ($serverPid > 0) {
            @posix_kill($serverPid, 15);
        }
        @proc_terminate($server);
        @proc_close($server);
    }
    if (is_dir($tmp)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isDir()) {
                @unlink($file->getPathname());
            } else {
                @rmdir($file->getPathname());
            }
        }
        @rmdir($tmp);
    }
});

if (!mkdir($stores, 0700, true) && !is_dir($stores)) {
    $fail('Cannot create ' . $stores);
}
mkdir($tmp . '/target', 0700, true);
file_put_contents($tmp . '/target/keep.txt', 'KEEP-MARKER');
symlink($tmp . '/target', $stores . '/pivot');
$threw = false;
try {
    SiteUsage::assertInside($stores, $stores . '/pivot/');
} catch (\Throwable $e) {
    $threw = str_contains($e->getMessage(), 'inside the stores directory');
}
$check($threw, 'a store folder that is a link out of the stores directory is refused');
$check(is_file($tmp . '/target/keep.txt'), 'refusing the link does not remove the target');

$long = str_repeat('a', 250) . '.example.com';
$threw = false;
try {
    SiteUsage::assertHostname($long, 'operate.example.com');
} catch (\Throwable) {
    $threw = true;
}
$check($threw, 'a hostname longer than 253 characters is refused');
$threw = false;
try {
    SiteUsage::assertHostname('operate.example.com', 'operate.example.com');
} catch (\Throwable $e) {
    $threw = str_contains($e->getMessage(), 'operator site');
}
$check($threw, 'the operator hostname is refused for a customer');

PlatformDb::addOperator($platform, 'reid', 'correct-horse-battery');
for ($i = 0; $i < PlatformDb::LOGIN_LIMIT; $i++) {
    PlatformDb::recordLoginFailure($platform, '198.51.100.20');
}
$check(PlatformDb::loginFailureCount($platform, '198.51.100.20') === PlatformDb::LOGIN_LIMIT, 'failed attempts are counted for one address');
$check(PlatformDb::loginFailureCount($platform, '198.51.100.21') === 0, 'a different address is not locked by those attempts');
PlatformDb::clearLoginFailures($platform, '198.51.100.20');
$check(PlatformDb::loginFailureCount($platform, '198.51.100.20') === 0, 'a successful sign-in path can clear the count');

$label = 'Health <img src=x onerror=alert(1)>';
PlatformDb::addStore($platform, 'health', $label, 'health.example.com', 'records@health.example.com');

putenv('INLAY_STORES=' . $stores);
putenv('INLAY_OPERATOR_HOST=operate.example.com');
putenv('INLAY_PLATFORM_DB=' . $platform);
$_SERVER['HTTP_HOST'] = 'operate.example.com';
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = 'off';
$_GET = [];
$_POST = [];
if (!defined('VERSION')) {
    define('VERSION', '1.0');
}
Runtime::define($root . DIRECTORY_SEPARATOR);
$check(Runtime::mode() === Runtime::OPERATOR, 'the render uses operator mode');
Runtime::startSession();
$check(session_name() === 'ADLEXONE_OPERATOR', 'the operator session is not the customer session');

ob_start();
OperatorScreen::handle();
Page::current()->send();
$loginHtml = (string) ob_get_clean();
$check(str_contains($loginHtml, 'Operator sign in'), 'a public address shows the operator sign-in');
$check(!str_contains($loginHtml, DefaultAccount::PASSWORD), 'a public address does not receive the default password');
$check(!str_contains($loginHtml, 'homehero'), 'sign-in is not the dashboard');
$check(!str_contains($loginHtml, $label), 'sign-in does not list customer sites');

Page::reset();
$_SESSION['operator_id'] = 1;
$_SESSION['operator_name'] = 'reid';
ob_start();
OperatorScreen::handle();
Page::current()->send();
$dash = (string) ob_get_clean();
$check(str_contains($dash, 'homehero'), 'the signed-in host page is the dashboard');
$check(str_contains($dash, 'sectionnav'), 'the dashboard uses the site section bar');
$check(str_contains($dash, 'appbar'), 'the dashboard uses the site top bar');
$check(str_contains($dash, 'Each row is one site'), 'the dashboard states what the totals are');
$check(str_contains($dash, '&lt;img'), 'a display name is escaped');
$check(!str_contains($dash, '<img src=x'), 'a display name is not rendered as markup');
$check(!str_contains($dash, 'install-steps'), 'the signed-in manager is not the installer steps');
$check(!str_contains($dash, 'data-palette-open'), 'the manager does not open customer search');
$check(str_contains($dash, 'name="operator_action" value="sign_out"'), 'sign-out is a posted action');
$check(!str_contains($dash, '?action=logoff'), 'sign-out is not the customer sign-out link');
$check(!str_contains($dash, DefaultAccount::PASSWORD), 'the dashboard does not show the default password');

Page::reset();
$_GET['view'] = 'add';
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['HTTP_HOST'] = 'operate.example.com';
ob_start();
OperatorScreen::handle();
Page::current()->send();
$add = (string) ob_get_clean();
$check(str_contains($add, 'Create the store'), 'add a site is its own screen');
$check(!str_contains($add, DefaultAccount::PASSWORD), 'a public address does not receive the default admin password');
unset($_GET['view']);

$cookies = [];
$http = static function (string $method, string $url, array $fields = [], ?array $cookieJar = null, array $extraHeaders = []) use (&$cookies, $fail): array {
    $jar = $cookieJar ?? $cookies;
    $command = ['curl', '-sS', '-D', '-', '--output', '-', '--max-time', '60', '-X', $method];
    if ($jar !== []) {
        $pairs = [];
        foreach ($jar as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $command[] = '-H';
        $command[] = 'Cookie: ' . implode('; ', $pairs);
    }
    foreach ($extraHeaders as $header) {
        $command[] = '-H';
        $command[] = $header;
    }
    if ($method === 'POST') {
        $command[] = '--data';
        $command[] = http_build_query($fields);
    }
    $command[] = $url;
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        $fail('curl did not start');
    }
    $raw = (string) stream_get_contents($pipes[1]);
    $error = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        $fail('curl failed: ' . $error);
    }
    $parts = preg_split("/\r\n\r\n/", $raw, 2);
    $headerText = (string) ($parts[0] ?? '');
    $body = (string) ($parts[1] ?? '');
    $response = $headerText === '' ? [] : preg_split("/\r\n/", $headerText);
    if ($cookieJar === null) {
        foreach ($response as $line) {
            if (stripos($line, 'Set-Cookie:') !== 0) {
                continue;
            }
            $pair = explode(';', trim(substr($line, strlen('Set-Cookie:'))), 2)[0];
            $eq = strpos($pair, '=');
            if ($eq === false) {
                continue;
            }
            $cookies[trim(substr($pair, 0, $eq))] = trim(substr($pair, $eq + 1));
        }
    }
    $status = 0;
    if (isset($response[0]) && preg_match('/\s(\d{3})\s/', $response[0], $match) === 1) {
        $status = (int) $match[1];
    }

    return ['status' => $status, 'headers' => $response, 'body' => $body];
};
$tokenOf = static function (string $html) use ($fail): string {
    if (preg_match('/name="_csrf" value="([^"]+)"/', $html, $match) !== 1) {
        $fail('the page did not include a CSRF token');
    }

    return $match[1];
};
$headerHas = static function (array $headers, string $name, string $needle) use ($fail): void {
    foreach ($headers as $line) {
        if (stripos($line, $name . ':') === 0 && str_contains(strtolower($line), strtolower($needle))) {
            return;
        }
    }
    $fail($name . ' did not include ' . $needle);
};

$port = 0;
for ($candidate = 18173; $candidate < 18190; $candidate++) {
    $probe = @stream_socket_server('tcp://127.0.0.1:' . $candidate);
    if ($probe !== false) {
        fclose($probe);
        $port = $candidate;
        break;
    }
}
if ($port === 0) {
    $fail('no free port for the operator server');
}
$log = $tmp . '/server.log';
$env = getenv();
if (!is_array($env)) {
    $env = [];
}
$env['INLAY_STORES'] = $stores;
$env['INLAY_OPERATOR_HOST'] = '127.0.0.1';
$env['INLAY_PLATFORM_DB'] = $platform;
$command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'];
$pipes = [];
$server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, $env);
if (!is_resource($server)) {
    $fail('the operator server did not start');
}
$status = proc_get_status($server);
$serverPid = (int) ($status['pid'] ?? 0);
$ready = false;
for ($i = 0; $i < 50; $i++) {
    $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
    if (is_resource($socket)) {
        fclose($socket);
        $ready = true;
        break;
    }
    usleep(100000);
}
$check($ready, 'the operator server did not accept connections');
$base = 'http://127.0.0.1:' . $port . '/';

$open = $http('GET', $base);
$check($open['status'] === 200, 'the sign-in page answers, got ' . $open['status']);
$headerHas($open['headers'], 'Content-Security-Policy', "default-src 'self'");
$headerHas($open['headers'], 'X-Frame-Options', 'DENY');
$headerHas($open['headers'], 'X-Content-Type-Options', 'nosniff');
$headerHas($open['headers'], 'Cache-Control', 'no-store');
$headerHas($open['headers'], 'Set-Cookie', 'ADLEXONE_OPERATOR=');
$headerHas($open['headers'], 'Set-Cookie', 'HttpOnly');
$headerHas($open['headers'], 'Set-Cookie', 'SameSite=Lax');
$secure = false;
foreach ($open['headers'] as $line) {
    if (stripos($line, 'Set-Cookie:') === 0 && str_contains($line, 'Secure')) {
        $secure = true;
    }
}
$check(!$secure, 'plain HTTP does not mark the operator cookie Secure');
$check(str_contains($open['body'], 'Operator sign in'), 'the server shows operator sign-in');
$check(!str_contains($open['body'], 'onerror'), 'sign-in does not include a customer display name');

$foreign = $http('GET', $base, [], [], ['Host: civic.example.com', 'X-Forwarded-Proto: https']);
$check(str_contains($foreign['body'], 'Not a customer site'), 'another hostname is not the manager');
$check(!str_contains($foreign['body'], 'homehero'), 'another hostname does not render the dashboard');
$forwardedSecure = false;
foreach ($foreign['headers'] as $line) {
    if (stripos($line, 'Set-Cookie:') === 0 && str_contains($line, 'Secure')) {
        $forwardedSecure = true;
    }
}
$check(!$forwardedSecure, 'a forwarded protocol from an untrusted address does not mark the cookie Secure');

$managed = $http('GET', $base . '?host=manage', [], [], ['Host: civic.example.com']);
$check(str_contains($managed['body'], 'Operator sign in'), '?host=manage on another hostname asks for the operator password');
$check(!str_contains($managed['body'], 'onerror'), '?host=manage does not list sites before sign-in');

$token = $tokenOf($open['body']);
$before = PlatformDb::operatorCount($platform);
$created = $http('POST', $base, [
    '_csrf' => $token,
    'operator_action' => 'create_operator',
    'user_name' => 'intruder',
    'password' => 'correct-horse-battery',
    'password_confirm' => 'correct-horse-battery',
]);
$check(PlatformDb::operatorCount($platform) === $before, 'an existing operator cannot be joined by a second account');
$check(str_contains($created['body'], 'Sign in first'), 'creating an operator after the first account is refused');
$check(PlatformDb::operatorByName($platform, 'intruder') === null, 'the intruder account was not stored');

$expired = $http('POST', $base, [
    'operator_action' => 'login',
    'user_name' => 'reid',
    'password' => 'correct-horse-battery',
]);
$check(str_contains($expired['body'], 'form expired'), 'a sign-in without the token is refused');
$check(!str_contains($expired['body'], 'homehero'), 'a missing token does not open the dashboard');

$token = $tokenOf($expired['body'] !== '' ? $expired['body'] : $open['body']);
if (!str_contains($expired['body'], '_csrf')) {
    $again = $http('GET', $base);
    $token = $tokenOf($again['body']);
}
$injected = $http('POST', $base, [
    '_csrf' => $token,
    'operator_action' => 'login',
    'user_name' => "reid' OR '1'='1",
    'password' => 'correct-horse-battery',
]);
$check(str_contains($injected['body'], 'not recognised'), 'a username is not a query');
$check(!str_contains($injected['body'], 'homehero'), 'the injected username does not sign in');

$token = $tokenOf($injected['body']);
$signed = $http('POST', $base, [
    '_csrf' => $token,
    'operator_action' => 'login',
    'user_name' => 'reid',
    'password' => 'correct-horse-battery',
]);
$check(str_contains($signed['body'], 'homehero'), 'the operator password opens the dashboard');
$check(str_contains($signed['body'], '&lt;img'), 'the live dashboard escapes the display name');
$check(!str_contains($signed['body'], '<img src=x'), 'the live dashboard does not render the display name as markup');
$check(isset($cookies['ADLEXONE_OPERATOR']), 'the session cookie is the operator cookie');
$check(!isset($cookies['PHPSESSID']), 'the manager does not set the customer session cookie');

$token = $tokenOf($signed['body']);
$traversal = $http('POST', $base . '?view=add', [
    '_csrf' => $token,
    'operator_action' => 'create_store',
    'label' => 'Health',
    'slug' => '../health',
    'host' => 'files.example.com',
    'admin_email' => '',
    'admin_user' => '',
    'admin_password' => '',
]);
$check(str_contains($traversal['body'], 'slug'), 'a slug that leaves the stores directory is refused');
$check(!is_dir($tmp . '/health'), 'the refused slug does not create a folder beside the stores directory');

$token = $tokenOf($traversal['body']);
$sameHost = $http('POST', $base . '?view=add', [
    '_csrf' => $token,
    'operator_action' => 'create_store',
    'label' => 'Health',
    'slug' => 'health2',
    'host' => '127.0.0.1',
    'admin_email' => '',
    'admin_user' => '',
    'admin_password' => '',
]);
$check(str_contains($sameHost['body'], 'operator site'), 'the operator address cannot also be a customer hostname');

$token = $tokenOf($sameHost['body']);
$made = $http('POST', $base . '?view=add', [
    '_csrf' => $token,
    'operator_action' => 'create_store',
    'label' => 'Records',
    'slug' => 'records',
    'host' => 'records.example.com',
    'admin_email' => 'records@health.example.com',
    'admin_user' => '',
    'admin_password' => '',
]);
$check($made['status'] === 302, 'creating a site redirects, got ' . $made['status']);
$location = '';
foreach ($made['headers'] as $line) {
    if (stripos($line, 'Location:') === 0) {
        $location = trim(substr($line, strlen('Location:')));
    }
}
$check($location !== '' && !str_contains($location, '://'), 'the redirect stays on this site, got ' . $location);
$check(is_dir($stores . '/records'), 'the site folder was created');
$check(is_file($stores . '/records/config/adlexone_settings.json'), 'the site settings were created');

file_put_contents($stores . '/records/inside.txt', 'INSIDE-MARKER');
file_put_contents($tmp . '/outside.txt', 'OUTSIDE-MARKER-7f3a');
symlink($tmp . '/outside.txt', $stores . '/records/linked.txt');
$site = $http('GET', $base . '?site=records');
$token = $tokenOf($site['body']);
$backup = $http('POST', $base . '?site=records', [
    '_csrf' => $token,
    'operator_action' => 'backup',
    'store_id' => (string) (PlatformDb::storeBySlug($platform, 'records')['store_id'] ?? 0),
]);
$check($backup['status'] === 200, 'backup answers, got ' . $backup['status']);
$check(str_starts_with($backup['body'], 'PK'), 'backup is a zip');
$check(str_contains($backup['body'], 'INSIDE-MARKER'), 'backup includes a file from the site folder');
$check(!str_contains($backup['body'], 'OUTSIDE-MARKER-7f3a'), 'backup does not follow a link out of the site folder');

$token = $tokenOf($site['body']);
$wrong = $http('POST', $base . '?site=records', [
    '_csrf' => $token,
    'operator_action' => 'delete',
    'store_id' => (string) (PlatformDb::storeBySlug($platform, 'records')['store_id'] ?? 0),
    'confirm_slug' => 'records-typo',
]);
$check(is_dir($stores . '/records'), 'the wrong slug does not delete the folder');
$check(str_contains($wrong['body'], 'Type the slug'), 'the page asks for the slug');

$missing = $http('POST', $base . '?site=records', [
    '_csrf' => $tokenOf($wrong['body']),
    'operator_action' => 'delete',
    'store_id' => '99999',
    'confirm_slug' => 'records',
]);
$check(str_contains($missing['body'], 'not found'), 'an unknown store id is refused');
$check(is_dir($stores . '/records'), 'the unknown store id does not delete another site');

PlatformDb::addStore($platform, 'pivot', 'Pivot', 'pivot.example.com', '');
$pivot = $http('POST', $base . '?site=pivot', [
    '_csrf' => $tokenOf($http('GET', $base . '?site=pivot')['body']),
    'operator_action' => 'delete',
    'store_id' => (string) (PlatformDb::storeBySlug($platform, 'pivot')['store_id'] ?? 0),
    'confirm_slug' => 'pivot',
]);
$check(is_file($tmp . '/target/keep.txt'), 'deleting a linked folder does not remove the target');
$check(PlatformDb::storeBySlug($platform, 'pivot') !== null, 'the linked folder stays on the list when delete is refused');
$check(str_contains($pivot['body'], 'inside the stores directory'), 'the page says the folder has to stay inside the stores directory');

$old = $cookies['ADLEXONE_OPERATOR'] ?? '';
$still = $http('GET', $base . '?action=logoff');
$check(str_contains($still['body'], 'homehero'), 'a customer sign-out address does not end the operator session');
$out = $http('POST', $base, [
    '_csrf' => $tokenOf($still['body']),
    'operator_action' => 'sign_out',
]);
$check($out['status'] === 302, 'sign-out redirects');
$after = $http('GET', $base);
$check(str_contains($after['body'], 'Operator sign in'), 'sign-out returns to the sign-in page');
$replay = $http('GET', $base, [], ['ADLEXONE_OPERATOR' => $old]);
$check(str_contains($replay['body'], 'Operator sign in'), 'the session from before sign-out cannot open the dashboard');

$token = $tokenOf($after['body']);
for ($i = 0; $i < PlatformDb::LOGIN_LIMIT; $i++) {
    $attempt = $http('POST', $base, [
        '_csrf' => $token,
        'operator_action' => 'login',
        'user_name' => 'reid',
        'password' => 'wrong-password-' . $i,
    ]);
    $check(str_contains($attempt['body'], 'not recognised'), 'wrong password ' . $i . ' is refused');
    $token = $tokenOf($attempt['body']);
}
$locked = $http('POST', $base, [
    '_csrf' => $token,
    'operator_action' => 'login',
    'user_name' => 'reid',
    'password' => 'correct-horse-battery',
]);
$check(str_contains($locked['body'], 'Too many attempts'), 'the address is asked to wait after repeated failures');
$check(!str_contains($locked['body'], 'homehero'), 'the right password does not bypass the wait');

echo "operator security checks passed\n";
