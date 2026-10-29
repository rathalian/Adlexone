<?php
declare(strict_types=1);

/**
 * Names the screens read from the language file and the example settings.
 * PHPStan does not boot the site, so those constants are defined here.
 * APP_COUNT_ITEM_TYPE stays undefined: screens treat it as optional.
 */
$constants = parse_ini_file(__DIR__ . '/translations/English.lang.php');
if (is_array($constants)) {
    foreach ($constants as $name => $value) {
        if (!defined((string) $name)) {
            define((string) $name, $value);
        }
    }
}
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost');
}

$example = __DIR__ . '/config.example/';
foreach (glob($example . '*.json') ?: [] as $jsonFile) {
    if (strtolower(basename($jsonFile)) === 'platform.json') {
        continue;
    }
    $settings = json_decode((string) file_get_contents($jsonFile), true);
    if (!is_array($settings)) {
        continue;
    }
    foreach ($settings as $name => $value) {
        if (is_string($name) && !defined($name)) {
            define($name, $value);
        }
    }
}

if (!defined('SET_INSTALL_PATH')) {
    define('SET_INSTALL_PATH', __DIR__ . '/');
}
if (!defined('SET_CONFIGURATION_PATH')) {
    define('SET_CONFIGURATION_PATH', $example);
}
if (!defined('SET_ATTACHMENTS_PATH')) {
    define('SET_ATTACHMENTS_PATH', __DIR__ . '/storage/attachments/');
}
if (!defined('DSN')) {
    define('DSN', 'sqlite::memory:');
}
if (!defined('SET_IMAGE_PATH')) {
    define('SET_IMAGE_PATH', 'layouts/images/');
}
if (!defined('SET_THEME')) {
    define('SET_THEME', 'inlay-stone');
}
