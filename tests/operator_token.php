<?php
declare(strict_types=1);

/**
 * The first operator account needs the token written beside the operator database.
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Adlexone\Http\Page;
use Adlexone\Platform\OperatorScreen;
use Adlexone\Platform\PlatformDb;
use Adlexone\Platform\Runtime;
use Adlexone\Platform\SetupToken;

$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$check = static function (bool $ok, string $message) use ($fail): void {
    if (!$ok) {
        $fail($message);
    }
};

$tmp = sys_get_temp_dir() . '/adlexone-operator-token-' . bin2hex(random_bytes(4));
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

putenv('INLAY_STORES=' . $tmp . '/stores');
putenv('INLAY_OPERATOR_HOST=operate.example.com');
putenv('INLAY_PLATFORM_DB=' . $platform);
mkdir($tmp . '/stores', 0700, true);
$_SERVER['HTTP_HOST'] = 'operate.example.com';
$_SERVER['HTTPS'] = 'off';
$_SERVER['REMOTE_ADDR'] = '203.0.113.40';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];
$_POST = [];
if (!defined('VERSION')) {
    define('VERSION', '1.0');
}
Runtime::define($root . DIRECTORY_SEPARATOR);
Runtime::startSession();

ob_start();
OperatorScreen::handle();
Page::current()->send();
$html = (string) ob_get_clean();
$tokenPath = SetupToken::path();
$check(is_file($tokenPath), 'opening the account page writes the setup token');
$check((fileperms($tokenPath) & 0777) === 0600, 'the setup token is mode 600');
$secret = trim((string) file_get_contents($tokenPath));
$check(strlen($secret) === 32, 'the setup token is 32 hex characters');
$check(str_contains($html, 'Create the operator account'), 'the first visit asks for the operator account');
$check(str_contains($html, $tokenPath), 'the page names the token file');
$check(!str_contains($html, $secret), 'the page does not show the token');
$check(preg_match('/name="_csrf" value="([^"]+)"/', $html, $match) === 1, 'the account form includes a CSRF token');
$csrf = $match[1];

Page::reset();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    '_csrf' => $csrf,
    'operator_action' => 'create_operator',
    'user_name' => 'reid',
    'password' => 'correct-horse-battery',
    'password_confirm' => 'correct-horse-battery',
    'setup_token' => 'not-the-token',
];
ob_start();
OperatorScreen::handle();
Page::current()->send();
$refused = (string) ob_get_clean();
$check(PlatformDb::operatorCount($platform) === 0, 'a wrong setup token does not create the operator');
$check(str_contains($refused, 'does not match'), 'the page says the token does not match');
$check(is_file($tokenPath), 'a refused attempt leaves the token file in place');

Page::reset();
$_POST['setup_token'] = $secret;
$_POST['_csrf'] = $match[1];
if (preg_match('/name="_csrf" value="([^"]+)"/', $refused, $fresh) === 1) {
    $_POST['_csrf'] = $fresh[1];
}
ob_start();
OperatorScreen::handle();
Page::current()->send();
$created = (string) ob_get_clean();
$check(PlatformDb::operatorCount($platform) === 1, 'the token from the file creates the operator account');
$check(str_contains($created, 'homehero'), 'the new operator is signed in');
$check(!is_file($tokenPath), 'the token file is removed once the account exists');
$check(!str_contains($created, $secret), 'the signed-in page does not show the token');

echo "operator token checks passed\n";
