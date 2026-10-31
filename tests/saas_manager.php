<?php
declare(strict_types=1);

/**
 * SaaS installer gate, site usage, cost, update, and delete.
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Adlexone\Platform\InstallLayout;
use Adlexone\Platform\PlatformDb;
use Adlexone\Platform\Provisioner;
use Adlexone\Platform\Runtime;
use Adlexone\Platform\SaasInstallScreen;
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

$check(SiteUsage::assertOperatorHost('LocalHost') === 'localhost', 'localhost is an operator hostname');
$check(SiteUsage::assertOperatorHost('localhost:8000') === 'localhost', 'a port on localhost is not stored');
$check(SiteUsage::assertOperatorHost('Operate.Example.com') === 'operate.example.com', 'a dotted operator hostname is stored in lower case');
$threw = false;
try {
    SiteUsage::assertOperatorHost('health');
} catch (\Throwable $e) {
    $threw = str_contains($e->getMessage(), 'localhost is also accepted');
}
$check($threw, 'a name with no dot, other than localhost, is refused for the operator');
$threw = false;
try {
    SiteUsage::assertHostname('localhost', 'operate.example.com');
} catch (\Throwable) {
    $threw = true;
}
$check($threw, 'localhost is not a customer hostname');

$check(SaasInstallScreen::handles('', 'host') === true, 'the installer route is open when no stores directory is set');
$check(SaasInstallScreen::handles('', '', 'install') === true, '?host=install opens the installer');
$check(SaasInstallScreen::handles('', 'host', 'manage') === false, '?host=manage is not the installer');
$check(SaasInstallScreen::handles('/var/stores', 'host') === false, 'the installer route does not render when INLAY_STORES is set');
$check(SaasInstallScreen::handles('', 'site') === false, 'another install query is not the SaaS installer');
$check(Runtime::operatorAddress() === './', 'the operator address stays on this host');
$check(Runtime::operatorAddress('site=health') === './?site=health', 'a site link stays on this host');
$_GET['host'] = 'manage';
$check(Runtime::wantsManage() === true, '?host=manage asks for the customer sites screen');
$check(Runtime::operatorAddress() === './?host=manage', 'manage stays on the management address');
$check(Runtime::operatorAddress('site=health') === './?host=manage&site=health', 'a site link keeps manage');
unset($_GET['host']);

$check(SiteUsage::parseAmount('12.50') === 1250, '12.50 is 1250 cents');
$check(SiteUsage::parseAmount('0') === 0, 'zero is a real amount');
$check(SiteUsage::parseAmount('1000000.00') === 100000000, 'the upper bound is accepted');
$check(SiteUsage::parseCurrency('aud') === 'AUD', 'the currency code is stored in upper case');
foreach (['-1', '1000000.01', '12.555', '12,50', ''] as $bad) {
    $threw = false;
    try {
        SiteUsage::parseAmount($bad);
    } catch (\Throwable) {
        $threw = true;
    }
    $check($threw, 'refused amount ' . ($bad === '' ? '(blank)' : $bad));
}

$half = intdiv(SiteUsage::GIGABYTE, 2);
$check(
    SiteUsage::costCents(0, $half, 0, 0, 1) === 1,
    'half a gigabyte at 1 cent rounds up to 1 cent'
);
$check(
    SiteUsage::costCents(0, $half - 1, 0, 0, 1) === 0,
    'just under half a gigabyte at 1 cent rounds down'
);
$check(
    SiteUsage::costCents(3, 0, 1000, 250, 0) === 1750,
    'monthly amount plus people times the per-person amount'
);
$check(
    SiteUsage::costCents(0, SiteUsage::GIGABYTE, 0, 0, 200) === 200,
    'one gigabyte times the per-gigabyte amount'
);

$tmp = sys_get_temp_dir() . '/adlexone-saas-' . bin2hex(random_bytes(4));
$install = $tmp . '/app';
$public = $install . '/public';
$stores = $tmp . '/adlexone-stores';
if (!mkdir($public, 0700, true) && !is_dir($public)) {
    $fail('Cannot create ' . $public);
}
if (!mkdir($install . '/config', 0700, true) && !is_dir($install . '/config')) {
    $fail('Cannot create the install config directory');
}
file_put_contents(
    $install . '/config/platform.json',
    json_encode(['INLAY_STORAGE' => '/home/health/adlexone-data', 'INLAY_STORES' => '', 'INLAY_OPERATOR_HOST' => ''], JSON_PRETTY_PRINT) . "\n"
);
$_SERVER['DOCUMENT_ROOT'] = $public;
$_SERVER['HTTP_HOST'] = 'operate.example.com';

$blocked = SaasInstallScreen::blockReason($install);
$check($blocked === '', 'a copy with no administrator can open the installer, got: ' . $blocked);

$inside = SaasInstallScreen::assessStores($install, $public . '/stores', InstallLayout::PUBLIC_FOLDER, $public);
$check($inside['ok'] === false, 'a stores directory inside the website is refused');
$check(str_contains($inside['message'], 'outside the website'), 'the refusal says the stores directory has to sit outside the website');

$report = SaasInstallScreen::assessStores($install, $stores, InstallLayout::PUBLIC_FOLDER, $public);
$check($report['ok'] === true, 'a stores directory outside the website passes: ' . $report['message']);
$check($report['platform_db'] === $tmp . '/platform.sqlite', 'the operator database sits beside the stores directory, got ' . $report['platform_db']);
$shared = SaasInstallScreen::assessStores($install, sys_get_temp_dir() . '/adlexone-stores-shared', InstallLayout::PUBLIC_FOLDER, $public);
$check($shared['ok'] === false, 'a stores directory in the system temp directory is refused');
$check(str_contains($shared['message'], 'shared directory'), 'the refusal names the shared directory');
$check(PlatformDb::mayRestrictDirectory(sys_get_temp_dir()) === false, 'the system temp directory is not made private');
$check(PlatformDb::mayRestrictDirectory($tmp) === true, 'a private folder can be mode 700');

$siteFolder = $stores . '/health';
if (!mkdir($siteFolder, 0700, true) && !is_dir($siteFolder)) {
    $fail('Cannot create a site folder');
}
file_put_contents($siteFolder . '/adlexone.sqlite', 'x');
$nested = SaasInstallScreen::assessStores($install, $stores, InstallLayout::PUBLIC_FOLDER, $public);
$check($nested['ok'] === true, 'customer folders already inside the stores directory are left in place');
unlink($siteFolder . '/adlexone.sqlite');
$direct = $tmp . '/one-site';
mkdir($direct, 0700, true);
file_put_contents($direct . '/adlexone.sqlite', 'x');
$refusedSite = SaasInstallScreen::assessStores($install, $direct, InstallLayout::PUBLIC_FOLDER, $public);
$check($refusedSite['ok'] === false && str_contains($refusedSite['message'], 'one site'), 'a folder with adlexone.sqlite as a direct child is refused');

InstallLayout::rememberSaaS($install, $stores, 'Operate.Example.com', $tmp . '/platform.sqlite');
$saved = json_decode((string) file_get_contents($install . '/config/platform.json'), true);
$check(is_array($saved), 'platform.json is still JSON');
$check(($saved['INLAY_STORAGE'] ?? '') === '/home/health/adlexone-data', 'INLAY_STORAGE is left in place');
$check(realpath((string) ($saved['INLAY_STORES'] ?? '')) === realpath($stores), 'INLAY_STORES was written');
$check(($saved['INLAY_OPERATOR_HOST'] ?? '') === 'operate.example.com', 'the operator hostname is stored in lower case');
$check(($saved['INLAY_PLATFORM_DB'] ?? '') === $tmp . '/platform.sqlite', 'INLAY_PLATFORM_DB was written');
$check(SaasInstallScreen::blockReason($install) !== '', 'a server already in SaaS mode does not go through the installer again');

$legacy = $install . '/storage/database';
mkdir($legacy, 0700, true);
$legacySqlite = $legacy . '/adlexone.sqlite';
$live = new PDO('sqlite:' . $legacySqlite);
$live->exec('CREATE TABLE users (user_id INTEGER PRIMARY KEY, user_name TEXT)');
$live->exec("INSERT INTO users (user_name) VALUES ('reid')");
$live = null;
file_put_contents(
    $install . '/config/platform.json',
    json_encode(['INLAY_STORAGE' => '', 'INLAY_STORES' => '', 'INLAY_OPERATOR_HOST' => ''], JSON_PRETTY_PRINT) . "\n"
);
$already = SaasInstallScreen::blockReason($install);
$check(str_contains($already, $legacySqlite), 'an existing self-hosted administrator stops the installer');
$check(str_contains($already, 'already one site'), 'the page says this copy is already one site');

putenv('INLAY_OPERATOR_HOST=operate.example.com');
try {
    $envReason = SaasInstallScreen::blockReason($install);
} finally {
    putenv('INLAY_OPERATOR_HOST');
}
$check(str_contains($envReason, 'INLAY_OPERATOR_HOST'), 'a hostname set only in the environment is not written over by this page');

$folder = $stores . '/health';
mkdir($folder . '/config', 0700, true);
mkdir($folder . '/attachments', 0700, true);
file_put_contents($folder . '/adlexone.sqlite', str_repeat('a', 100));
file_put_contents($folder . '/adlexone.sqlite-wal', str_repeat('b', 40));
file_put_contents($folder . '/adlexone.sqlite-shm', str_repeat('c', 20));
file_put_contents($folder . '/config/adlexone_settings.json', json_encode([
    'SET_SITE_NAME' => 'Health',
    'SET_PUBLIC_BASE_URL' => 'https://health.example.com',
    'SMTP_PASSWORD' => 'secret',
]) . "\n");
file_put_contents($folder . '/attachments/letter.txt', 'file');
file_put_contents($folder . '/setup-token.txt', 'token');
$outside = $tmp . '/outside.txt';
file_put_contents($outside, str_repeat('z', 500));
symlink($outside, $folder . '/linked.txt');

$disk = SiteUsage::disk($folder);
$check($disk['database'] === 100, 'database bytes are the sqlite file');
$check($disk['wal'] === 40, 'write-ahead log bytes are separate');
$check($disk['shm'] === 20, 'shared memory bytes are separate');
$check($disk['settings'] === filesize($folder . '/config/adlexone_settings.json'), 'settings bytes are the config files');
$check($disk['uploads'] === 4, 'upload bytes are the attachments directory');
$check($disk['other'] === strlen('token'), 'other bytes include setup-token.txt');
$check($disk['total'] === $disk['database'] + $disk['wal'] + $disk['shm'] + $disk['settings'] + $disk['uploads'] + $disk['other'], 'the folder total is the sum of the parts');
$check($disk['total'] < Provisioner::folderBytes($folder), 'a link out of the folder is not counted');

$other = $stores . '/records';
mkdir($other . '/config', 0700, true);
file_put_contents($other . '/config/adlexone_settings.json', json_encode([
    'SET_SITE_NAME' => 'Records',
    'SET_PUBLIC_BASE_URL' => 'https://records.example.com',
    'SMTP_PASSWORD' => 'keep',
]) . "\n");

$platform = $tmp . '/platform.sqlite';
PlatformDb::addStore($platform, 'health', 'Health', 'health.example.com', 'records@health.example.com');
PlatformDb::addStore($platform, 'records', 'Records', 'records.example.com', '');
$health = PlatformDb::storeBySlug($platform, 'health');
$records = PlatformDb::storeBySlug($platform, 'records');
$check($health !== null && $records !== null, 'both sites are on the operator list');

$missing = $stores . '/missing';
PlatformDb::addStore($platform, 'missing', 'Missing', 'missing.example.com', '');
$missingRow = PlatformDb::storeBySlug($platform, 'missing');
if ($missingRow === null) {
    $fail('missing site was not stored');
}
SiteUsage::measure($platform, $missingRow, SiteUsage::folder($stores, 'missing'));
$check(!is_file(SiteUsage::folder($stores, 'missing') . 'adlexone.sqlite'), 'measuring a missing database does not create one');
$missingUsage = PlatformDb::usage($platform, (int) $missingRow['store_id']);
$check($missingUsage !== null && $missingUsage['people'] === null, 'a site with no database has unset people');
SiteUsage::deleteSite($platform, $stores, $missingRow, 'missing');

unlink($folder . '/adlexone.sqlite');
$counted = new PDO('sqlite:' . $folder . '/adlexone.sqlite');
$counted->exec('CREATE TABLE users (user_id INTEGER PRIMARY KEY)');
$counted->exec('INSERT INTO users (user_id) VALUES (1), (2)');
$counted->exec('CREATE TABLE applications (application_id INTEGER PRIMARY KEY, enabled INTEGER NOT NULL)');
$counted->exec('INSERT INTO applications (enabled) VALUES (1), (1), (0)');
$counted->exec('CREATE TABLE items (item_id INTEGER PRIMARY KEY)');
$counted->exec('INSERT INTO items (item_id) VALUES (1)');
$counted = null;
SiteUsage::measure($platform, $records, SiteUsage::folder($stores, 'records'));
SiteUsage::measure($platform, $health, $folder);
$usage = PlatformDb::usage($platform, (int) $health['store_id']);
$check($usage !== null && (int) $usage['people'] === 2, 'people are rows in users');
$check((int) $usage['applications'] === 2, 'disabled applications are omitted');
$check((int) $usage['records'] === 1, 'records are rows in items');
$check($usage['cost_cents'] === null, 'cost is unset when no rate is saved');

unlink($folder . '/adlexone.sqlite');
$figures = SiteUsage::hostFigures($platform);
$check($figures['people'] === 2, 'the host total reads the stored measurement');
file_put_contents($folder . '/adlexone.sqlite', '');

PlatformDb::saveRate($platform, 'AUD', 1000, 250, 200);
SiteUsage::applyRate($platform);
$usage = PlatformDb::usage($platform, (int) $health['store_id']);
$bytes = (int) $usage['database_bytes'] + (int) $usage['wal_bytes'] + (int) $usage['shm_bytes']
    + (int) $usage['settings_bytes'] + (int) $usage['uploads_bytes'] + (int) $usage['other_bytes'];
$expected = SiteUsage::costCents(2, $bytes, 1000, 250, 200);
$check((int) $usage['cost_cents'] === $expected, 'saving the rate recomputes cost from the stored measurement');
$recordsUsage = PlatformDb::usage($platform, (int) $records['store_id']);
$recordsBytes = (int) $recordsUsage['database_bytes'] + (int) $recordsUsage['wal_bytes'] + (int) $recordsUsage['shm_bytes']
    + (int) $recordsUsage['settings_bytes'] + (int) $recordsUsage['uploads_bytes'] + (int) $recordsUsage['other_bytes'];
$figures = SiteUsage::hostFigures($platform);
$check($figures['cost'] === $expected + SiteUsage::costCents(0, $recordsBytes, 1000, 250, 200), 'the host cost is the sum of measured sites');
$check($figures['unmeasured'] === 0, 'every site in this test has been measured');

SiteUsage::updateSite($platform, $stores, $health, 'Health records', 'health.example.com', 'records@health.example.com', 'operate.example.com');
$settings = json_decode((string) file_get_contents($folder . '/config/adlexone_settings.json'), true);
$check(($settings['SET_SITE_NAME'] ?? '') === 'Health records', 'the display name is written to the site settings');
$check(($settings['SET_PUBLIC_BASE_URL'] ?? '') === 'https://health.example.com', 'an unchanged hostname leaves the public URL');
$check(($settings['SMTP_PASSWORD'] ?? '') === 'secret', 'other settings keys stay');
$updated = PlatformDb::storeBySlug($platform, 'health');
$check(($updated['label'] ?? '') === 'Health records', 'the operator list stores the new display name');
$check(($updated['host'] ?? '') === 'health.example.com', 'the hostname is unchanged');
$check(is_dir($folder), 'the slug directory stays');

SiteUsage::updateSite($platform, $stores, $updated, 'Health records', 'care.example.com', 'office@health.example.com', 'operate.example.com');
$settings = json_decode((string) file_get_contents($folder . '/config/adlexone_settings.json'), true);
$check(($settings['SET_PUBLIC_BASE_URL'] ?? '') === 'https://care.example.com', 'a new hostname updates the public URL');
$check(PlatformDb::storeByHost($platform, 'care.example.com') !== null, 'the operator list stores the new hostname');
$otherSettings = json_decode((string) file_get_contents($other . '/config/adlexone_settings.json'), true);
$check(($otherSettings['SET_SITE_NAME'] ?? '') === 'Records', 'another site’s settings are untouched');
$check(($otherSettings['SMTP_PASSWORD'] ?? '') === 'keep', 'another site’s secrets are untouched');

unlink($other . '/config/adlexone_settings.json');
$before = PlatformDb::storeBySlug($platform, 'records');
$threw = false;
try {
    SiteUsage::updateSite($platform, $stores, $records, 'Records', 'files.example.com', '', 'operate.example.com');
} catch (\Throwable $e) {
    $threw = str_contains($e->getMessage(), 'missing');
}
$check($threw, 'a missing settings file stops the update');
$after = PlatformDb::storeBySlug($platform, 'records');
$check(($after['host'] ?? '') === ($before['host'] ?? ''), 'the operator row is not changed when the settings file is missing');

try {
    SiteUsage::deleteSite($platform, $stores, $updated, 'health-typo');
    $fail('a mismatched slug deleted the site');
} catch (\Throwable) {
}
$check(is_dir($folder), 'the folder remains when the typed slug does not match');
$check(is_dir($other), 'another site’s folder remains when delete is refused');
SiteUsage::deleteSite($platform, $stores, $updated, 'health');
$check(!is_dir($folder), 'the matching slug removes that folder');
$check(is_dir($other), 'delete removes only that site’s folder');
$check(is_file($platform), 'delete does not remove the operator database');
$check(PlatformDb::storeBySlug($platform, 'health') === null, 'the operator row is removed');
$check(PlatformDb::usage($platform, (int) $updated['store_id']) === null, 'the measurement row is removed with the site');

$manageRoot = sys_get_temp_dir() . '/adlexone-manage-' . bin2hex(random_bytes(4));
$manageStores = $manageRoot . '/stores';
if (!mkdir($manageRoot . '/config', 0700, true) && !is_dir($manageRoot . '/config')) {
    $fail('Cannot create the manage install');
}
mkdir($manageStores, 0700, true);
file_put_contents(
    $manageRoot . '/config/platform.json',
    json_encode([
        'INLAY_STORES' => $manageStores,
        'INLAY_OPERATOR_HOST' => 'operate.example.com',
        'INLAY_PLATFORM_DB' => $manageRoot . '/platform.sqlite',
    ], JSON_PRETTY_PRINT) . "\n"
);
$_SERVER['HTTP_HOST'] = 'health.example.com';
unset($_GET['host']);
Runtime::define($manageRoot . DIRECTORY_SEPARATOR);
$check(Runtime::mode() === Runtime::UNKNOWN, 'a hostname with no site is not the operator, got ' . Runtime::mode());
$_GET['host'] = 'manage';
Runtime::define($manageRoot . DIRECTORY_SEPARATOR);
$check(Runtime::mode() === Runtime::OPERATOR, '?host=manage opens the operator on another hostname, got ' . Runtime::mode());
$check(Runtime::paths()['platform_db'] === $manageRoot . '/platform.sqlite', 'manage uses the operator database');
unset($_GET['host']);

echo "saas manager checks passed\n";
