<?php
declare(strict_types=1);

/**
 * Document root detection, storage-directory checks, and where a single site keeps its files.
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Adlexone\Platform\InstallLayout;
use Adlexone\Platform\Provisioner;
use Adlexone\Platform\Runtime;

$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$tmp = sys_get_temp_dir() . '/adlexone-layout-' . bin2hex(random_bytes(4));
$install = $tmp . '/app';
$public = $install . '/public';
if (!mkdir($public, 0700, true) && !is_dir($public)) {
    $fail('Cannot create ' . $public);
}

$check = static function (bool $ok, string $message) use ($fail): void {
    if (!$ok) {
        $fail($message);
    }
};

$check(InstallLayout::detect($install, $public) === InstallLayout::PUBLIC_FOLDER, 'document root public/ is the public folder');
$check(InstallLayout::detect($install, $install) === InstallLayout::PACKAGE, 'document root equal to the package is the package layout');
$check(InstallLayout::detect($install, $tmp . '/elsewhere') === InstallLayout::UNKNOWN, 'an unrelated document root is not a known layout');
$check(InstallLayout::detect($install, '') === InstallLayout::UNKNOWN, 'a missing document root is not a known layout');
$check(str_contains(InstallLayout::placementMessage($install, $public), 'public folder'), 'the public-folder message names that choice');
$check(str_contains(InstallLayout::placementMessage($install, ''), 'did not report'), 'a missing document root is explained');

$check(InstallLayout::absolute('storage') === null, 'a relative path is refused');
$check(InstallLayout::absolute('/tmp/foo/../../etc') === null, 'a path with .. is refused');
$check(InstallLayout::absolute('/tmp/adlexone-data') === '/tmp/adlexone-data', 'an absolute path is kept');
$check(InstallLayout::absolute('/tmp//adlexone-data/') === '/tmp/adlexone-data', 'extra slashes are removed');

$insidePublic = $public . '/uploads';
$refused = InstallLayout::probe($install, $insidePublic, InstallLayout::PUBLIC_FOLDER, $public, false);
$check($refused['ok'] === false, 'a folder inside public is refused');
$check(!is_dir($insidePublic), 'a refused folder is not created');
$check($refused['checks'] === [], 'a refused folder does not run the file checks');

$packageData = $install . '/data';
$refusedPackage = InstallLayout::probe($install, $packageData, InstallLayout::PACKAGE, $install, false);
$check($refusedPackage['ok'] === false && !is_dir($packageData), 'a new folder inside the package is refused when the package is the document root');

file_put_contents($tmp . '/not-a-folder', 'x');
$fileProbe = InstallLayout::probe($install, $tmp . '/not-a-folder', InstallLayout::PUBLIC_FOLDER, $public, false);
$check($fileProbe['ok'] === false && str_contains($fileProbe['message'], 'is a file'), 'a file cannot be the storage directory');

$linkTarget = $public . '/linked-target';
mkdir($linkTarget, 0700, true);
$link = $tmp . '/linked-data';
if (!symlink($linkTarget, $link)) {
    $fail('Cannot create symlink');
}
$linked = InstallLayout::probe($install, $link, InstallLayout::PUBLIC_FOLDER, $public, false);
$check($linked['ok'] === false, 'a symlink into public is refused');

$storage = $install . '/storage';
$packageOk = InstallLayout::probe($install, $storage, InstallLayout::PACKAGE, $install, true);
$check($packageOk['ok'] === true, 'storage/ is allowed when the package is the document root: ' . $packageOk['message']);
$passed = [];
foreach ($packageOk['checks'] as $row) {
    $passed[$row['id']] = $row['state'];
}
foreach (['create', 'read', 'update', 'delete', 'folder', 'record'] as $id) {
    $check(($passed[$id] ?? '') === 'pass', $id . ' passed for the package storage folder');
}
$left = glob($storage . '/.adlexone-probe-*') ?: [];
$check($left === [], 'the storage check removes its trial files');
$leftConfig = glob($install . '/config/.adlexone-probe-*') ?: [];
$check($leftConfig === [], 'the path check removes its trial file');

$outside = $tmp . '/outside';
$outsideOk = InstallLayout::probe($install, $outside, InstallLayout::PUBLIC_FOLDER, $public, true);
$check($outsideOk['ok'] === true, 'a folder outside public can be used: ' . $outsideOk['message']);
$check($outsideOk['created'] === true, 'a missing folder is created by the check');
$check(is_dir($outside), 'the created folder stays');

$blocked = $tmp . '/blocked-app';
mkdir($blocked . '/public', 0700, true);
file_put_contents($blocked . '/config', 'not-a-directory');
$blockedData = $tmp . '/blocked-data';
$blockedProbe = InstallLayout::probe($blocked, $blockedData, InstallLayout::PUBLIC_FOLDER, $blocked . '/public', true);
$check($blockedProbe['ok'] === false, 'setup refuses a storage directory when the path cannot be recorded');
$record = null;
foreach ($blockedProbe['checks'] as $row) {
    if ($row['id'] === 'record') {
        $record = $row;
    }
}
$check(is_array($record) && $record['state'] === 'fail', 'the record-path check fails when config is not a folder');

file_put_contents(
    $install . '/config/platform.json',
    json_encode(['INLAY_STORES' => '', 'INLAY_OPERATOR_HOST' => ''], JSON_PRETTY_PRINT) . "\n"
);
InstallLayout::remember($install, $outside);
$saved = json_decode((string) file_get_contents($install . '/config/platform.json'), true);
$check(is_array($saved), 'platform.json is still JSON');
$check(($saved['INLAY_STORAGE'] ?? '') === ($outsideOk['directory'] ?? $outside), 'the storage path is recorded');
$check(array_key_exists('INLAY_STORES', $saved) && array_key_exists('INLAY_OPERATOR_HOST', $saved), 'existing platform settings are kept');
$mode = fileperms($install . '/config/platform.json') & 0777;
$check($mode === 0600, 'platform.json is mode 600, got ' . decoct($mode));
$check(InstallLayout::savedStorage($install) === ($saved['INLAY_STORAGE'] ?? ''), 'the recorded path is read back');

$emptyFile = $tmp . '/empty-app';
mkdir($emptyFile . '/config', 0700, true);
file_put_contents($emptyFile . '/config/platform.json', "{\"INLAY_STORAGE\":\"\"}\n");
$check(InstallLayout::savedStorage($emptyFile) === '', 'an empty INLAY_STORAGE does not select a directory');

$runner = $tmp . '/paths.php';
file_put_contents($runner, <<<'PHP'
<?php
require $argv[1];
\Adlexone\Platform\Runtime::define($argv[2]);
$p = \Adlexone\Platform\Runtime::paths();
echo json_encode([
    'mode' => \Adlexone\Platform\Runtime::mode(),
    'config' => $p['config'],
    'sqlite' => $p['sqlite'],
    'attachments' => $p['attachments'],
], JSON_UNESCAPED_SLASHES);
PHP);

$run = static function (string $app, array $env = []) use ($runner, $root, $fail): array {
    $prefix = '';
    foreach ($env as $name => $value) {
        $prefix .= $name . '=' . escapeshellarg((string) $value) . ' ';
    }
    $command = $prefix . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' '
        . escapeshellarg($root . '/vendor/autoload.php') . ' ' . escapeshellarg($app);
    $out = shell_exec($command);
    $decoded = json_decode((string) $out, true);
    if (!is_array($decoded)) {
        $fail('Path probe failed: ' . $out);
    }

    return $decoded;
};

$fresh = $tmp . '/fresh';
mkdir($fresh . '/public', 0700, true);
$legacy = $run($fresh);
$check($legacy['mode'] === Runtime::SETUP, 'a new install still opens setup');
$check($legacy['config'] === $fresh . '/config/', 'the original settings folder is beside the code');
$check($legacy['sqlite'] === $fresh . '/storage/database/adlexone.sqlite', 'the original database path is unchanged');
$check($legacy['attachments'] === $fresh . '/storage/attachments/', 'the original uploads folder is unchanged');

$recorded = $run($install);
$check($recorded['config'] === rtrim((string) $saved['INLAY_STORAGE'], '/') . '/config/', 'a recorded storage directory holds settings');
$check($recorded['sqlite'] === rtrim((string) $saved['INLAY_STORAGE'], '/') . '/database/adlexone.sqlite', 'a recorded storage directory holds the database');
$check($recorded['attachments'] === rtrim((string) $saved['INLAY_STORAGE'], '/') . '/attachments/', 'a recorded storage directory holds uploads');

$dataRoot = $tmp . '/inlay-data';
$fromEnv = $run($fresh, ['INLAY_DATA' => $dataRoot]);
$check($fromEnv['mode'] === Runtime::SETUP, 'INLAY_DATA with no database opens setup');
$check($fromEnv['config'] === $dataRoot . '/config/', 'INLAY_DATA keeps settings directly inside that directory');
$check($fromEnv['sqlite'] === $dataRoot . '/storage/database/adlexone.sqlite', 'INLAY_DATA keeps the older database path');

$storageEnv = $tmp . '/env-storage';
$fromStorage = $run($install, ['INLAY_STORAGE' => $storageEnv]);
$check($fromStorage['sqlite'] === $storageEnv . '/database/adlexone.sqlite', 'INLAY_STORAGE in the environment wins over the recorded path');
$check($fromStorage['config'] === $storageEnv . '/config/', 'INLAY_STORAGE in the environment uses the storage directory for settings');

$both = $run($fresh, ['INLAY_STORAGE' => $storageEnv, 'INLAY_DATA' => $dataRoot]);
$check($both['sqlite'] === $storageEnv . '/database/adlexone.sqlite', 'INLAY_STORAGE wins when both environment values are set');

Provisioner::removeTree($tmp);

echo "install layout ok\n";
