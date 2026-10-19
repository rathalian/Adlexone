<?php
declare(strict_types=1);

/**
 * Provisions a throwaway store and checks the operator database, file modes, and static-file guard.
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Adlexone\Platform\PlatformDb;
use Adlexone\Platform\Provisioner;
use Adlexone\Platform\Runtime;
use Adlexone\Platform\StaticFiles;

$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$tmp = sys_get_temp_dir() . '/inlay-platform-' . bin2hex(random_bytes(4));
if (!mkdir($tmp, 0700, true) && !is_dir($tmp)) {
    $fail('Cannot create ' . $tmp);
}

$install = $root . DIRECTORY_SEPARATOR;
$config = $tmp . '/config/';
$sqlite = $tmp . '/database/adlexone.sqlite';
$attachments = $tmp . '/attachments/';

Provisioner::install($install, $config, $sqlite, $attachments, [
    'site_name' => 'Acme',
    'public_url' => 'https://acme.example.com',
    'username' => 'ada',
    'password' => 'correct-horse',
    'first_name' => 'Ada',
    'last_name' => 'Lovelace',
    'email' => 'ada@example.com',
]);

if (!is_file($sqlite)) {
    $fail('SQLite file was not created');
}
if (Runtime::needsSetup($sqlite)) {
    $fail('A store with an admin still looks uninstalled');
}
$mode = fileperms($sqlite) & 0777;
if ($mode !== 0600) {
    $fail('SQLite mode is ' . decoct($mode) . ', expected 600');
}
foreach ([$config, dirname($sqlite), $attachments] as $dir) {
    $dirMode = fileperms($dir) & 0777;
    if ($dirMode !== 0700) {
        $fail($dir . ' mode is ' . decoct($dirMode) . ', expected 700');
    }
}

$pdo = new PDO('sqlite:' . $sqlite);
$version = (int) $pdo->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn();
if ($version !== 9) {
    $fail('schema version is ' . $version);
}
$hash = (string) $pdo->query("SELECT password FROM users WHERE user_name = 'ada'")->fetchColumn();
if (!password_verify('correct-horse', $hash)) {
    $fail('Admin password did not verify');
}
$settings = json_decode((string) file_get_contents($config . 'adlexone_settings.json'), true);
if (!is_array($settings) || ($settings['SET_SITE_NAME'] ?? '') !== 'Acme') {
    $fail('Site name was not saved');
}
$advanced = json_decode((string) file_get_contents($config . 'advanced_settings.json'), true);
if (($advanced['SET_DEBUG_MODE'] ?? '') !== 'No' || ($advanced['SET_SHOW_SQL'] ?? '') !== 'No') {
    $fail('Debug flags were not forced off');
}

$empty = $tmp . '/empty/adlexone.sqlite';
Provisioner::install($install, $tmp . '/empty/config/', $empty, $tmp . '/empty/attachments/', [
    'site_name' => 'Pending',
    'public_url' => '',
    'username' => '',
    'password' => '',
    'first_name' => '',
    'last_name' => '',
    'email' => '',
]);
if (!Runtime::needsSetup($empty)) {
    $fail('A store with no admin should open the customer setup page');
}

$platform = $tmp . '/platform.sqlite';
PlatformDb::addOperator($platform, 'root', 'operator-secret');
if (PlatformDb::operatorCount($platform) !== 1) {
    $fail('Operator was not stored');
}
$row = PlatformDb::operatorByName($platform, 'root');
if ($row === null || !password_verify('operator-secret', (string) $row['password'])) {
    $fail('Operator password did not verify');
}
PlatformDb::addStore($platform, 'acme', 'Acme', 'acme.example.com', 'ada@example.com');
$found = PlatformDb::storeByHost($platform, 'acme.example.com');
if ($found === null || ($found['slug'] ?? '') !== 'acme') {
    $fail('Store was not found by host');
}
if (is_file($tmp . '/config/platform.json')) {
    $fail('Customer config must not receive platform.json');
}

ob_start();
$served = StaticFiles::serve($install, '/layouts/pages/login.php');
ob_end_clean();
if ($served) {
    $fail('PHP templates must not be served as static files');
}
ob_start();
$servedCss = StaticFiles::serve($install, '/layouts/css/base.css');
$css = ob_get_clean();
if (!$servedCss || !str_contains($css, '.page-setup')) {
    $fail('Theme CSS was not served from outside public/');
}
ob_start();
$traversed = StaticFiles::serve($install, '/layouts/../config/adlexone_settings.json');
ob_end_clean();
if ($traversed) {
    $fail('Path traversal was served');
}

$setupMode = trim((string) shell_exec(
    'INLAY_DATA=' . escapeshellarg($tmp . '/brand-new')
    . ' php ' . escapeshellarg(__DIR__ . '/platform_mode.php')
    . ' ' . escapeshellarg($root)
));
if ($setupMode !== Runtime::SETUP) {
    $fail('Empty data directory mode was ' . $setupMode);
}

echo "platform smoke ok\n";
