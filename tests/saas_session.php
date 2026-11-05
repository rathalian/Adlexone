<?php
declare(strict_types=1);

/**
 * A customer session stays on the site that started it.
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Adlexone\Platform\PlatformDb;
use Adlexone\Platform\Runtime;

$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$check = static function (bool $ok, string $message) use ($fail): void {
    if (!$ok) {
        $fail($message);
    }
};

$tmp = sys_get_temp_dir() . '/adlexone-session-' . bin2hex(random_bytes(4));
$platform = $tmp . '/platform.sqlite';
register_shutdown_function(static function () use ($tmp): void {
    if (!is_dir($tmp)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }
    @rmdir($tmp);
});
if (!mkdir($tmp, 0700, true) && !is_dir($tmp)) {
    $fail('Cannot create ' . $tmp);
}
PlatformDb::addStore($platform, 'health', 'Health', 'health.example.com', '');
PlatformDb::addStore($platform, 'records', 'Records', 'records.example.com', '');
putenv('INLAY_STORES=' . $tmp);
putenv('INLAY_OPERATOR_HOST=operate.example.com');
putenv('INLAY_PLATFORM_DB=' . $platform);
$_SERVER['HTTPS'] = 'off';
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_GET = [];

$_SERVER['HTTP_HOST'] = 'health.example.com';
Runtime::define($root . DIRECTORY_SEPARATOR);
$healthName = Runtime::sessionName();
$check(str_starts_with($healthName, 'ADLEXONE'), 'a customer session name is not the operator cookie');
$check($healthName !== 'ADLEXONE_OPERATOR', 'a customer session name is not ADLEXONE_OPERATOR');
$check($healthName !== 'PHPSESSID', 'a customer session name is not the shared default');

$_SERVER['HTTP_HOST'] = 'records.example.com';
Runtime::define($root . DIRECTORY_SEPARATOR);
$recordsName = Runtime::sessionName();
$check($recordsName !== $healthName, 'two customer sites do not share a session name');

Runtime::startSession();
$_SESSION['access_user_id'] = 9;
$check(($_SESSION['_adlexone_store'] ?? '') === 'records', 'the session is bound to the site that started it');

$_SERVER['HTTP_HOST'] = 'health.example.com';
Runtime::define($root . DIRECTORY_SEPARATOR);
Runtime::startSession();
$check(!isset($_SESSION['access_user_id']), 'a session opened on another site is dropped');
$check(($_SESSION['_adlexone_store'] ?? '') === 'health', 'the replacement session is bound to this site');

echo "saas session checks passed\n";
