<?php
/** Bootstrap file to initialize the application
 *
 * This file sets up error reporting, loads configuration settings,
 * starts sessions, and includes necessary dependencies.
 */


/** * Autoload required classes using Composer
 */
require_once __DIR__ . '/../vendor/autoload.php';

/** * Import necessary classes
 */
use Adlexone\support\RenderViews;
use Adlexone\support\SharedMethods;
/**
 * Define system paths and constants
 */
$baseDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
define('SET_INSTALL_PATH', $baseDir);
define('SET_CONFIGURATION_PATH', $baseDir . 'config' . DIRECTORY_SEPARATOR);
define('SET_ATTACHMENTS_PATH', $baseDir . 'storage' . DIRECTORY_SEPARATOR . 'attachments' . DIRECTORY_SEPARATOR);
define('DSN', 'sqlite:' . $baseDir . 'storage' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'adlexone.sqlite');
define('SET_WRITEABLE_DIRECTORY', $baseDir . 'writeable' . DIRECTORY_SEPARATOR);

/**
 * Load configuration settings from JSON files in the config directory and its subdirectories
 */
$configDir = SET_CONFIGURATION_PATH;
$jsonFiles = glob($configDir . '*.json');

foreach ($jsonFiles as $jsonFile) {
    SharedMethods::loadJsonSettingsToConstants($jsonFile);
}

$subDirs = glob($configDir . '*', GLOB_ONLYDIR);
foreach ($subDirs as $subDir) {
    $subJsonFiles = glob($subDir . '/*.json');
    foreach ($subJsonFiles as $jsonFile) {
        Adlexone\support\SharedMethods::loadJsonSettingsToConstants($jsonFile);
    }
}

/** * Enable error reporting for debugging (disable in production)
 */

if (SET_DEBUG_MODE == 'Yes') {
    error_reporting(SET_ERROR_REPORTING_LEVEL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');

}



/** * Start session if not already started
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Start output buffering to prevent headers already sent errors
 */
ob_start();


/**
 * Define URL constants
 */
$urlArray = explode('/index.php', $_SERVER['PHP_SELF']);
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$port = ($_SERVER['SERVER_PORT'] != '80') ? ':' . $_SERVER['SERVER_PORT'] : '';
// Return the base URL of the installation e.g
define('BASE_URL', $protocol . $_SERVER['SERVER_NAME'] . $port . $urlArray[0]);
// Return the full URL of the current script e.g http://mydomain.com/adlexone/index.php
define('FULL_SCRIPT_PATH', $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);

// Set default values
if (!defined('SET_DEFAULT_THEME')) {
    // define('SET_DEFAULT_THEME', 'aims');
    define('SET_DEFAULT_THEME', 'new');
}

// Apply addslashesRecursive() to all data and strip scripts
//$_GET = SharedMethods::stripScriptsRecursive(SharedMethods::addslashesRecursive($_GET));
//$_POST = SharedMethods::stripScriptsRecursive(SharedMethods::addslashesRecursive($_POST));
//$_COOKIE = SharedMethods::stripScriptsRecursive(SharedMethods::addslashesRecursive($_COOKIE));
//$_REQUEST = SharedMethods::stripScriptsRecursive(SharedMethods::addslashesRecursive($_REQUEST));


// Set the required controller using the 'controller' GET variable or load the
// logon page if the user has not logged on
if (isset($_GET['action'])) {
    $urlaction = $_GET['action'];
} else {
    $urlaction = '';
}
if (!isset ($_SESSION['access_user_id']) or $urlaction === 'logoff') {
    // Set random secure id
    $_SESSION['secure_id'] = md5(substr(md5(uniqid(rand(), true)), 0, 20));
    SharedMethods::loadConstantFromIni(SET_INSTALL_PATH . 'translations/' . SET_DEFAULT_LANGUAGE . '.lang.php');
    define('SET_THEME', SET_DEFAULT_THEME);
    include 'app/Http/Controllers/login.php';

} else {
    // Handle accessing the root / with session variables still set
    if (!isset($_GET['controller'])) {
        $_GET['controller'] = $_SESSION['access_home_controller'];
    }
    // Setup theme and other user options.  If user is not logged in the theme is set to use default
    // Override default theme which represents a empty value for the users theme setting
    if ($_SESSION['access_theme'] != '') {
        define('SET_THEME', $_SESSION['access_theme']);
    } else {
        define('SET_THEME', SET_DEFAULT_THEME);
    }
    // Set other user defined constants
    define('SET_DEFAULT_PAGE', $_SESSION['access_home_controller']); //Softwares Default Page
    define('SET_HOME_PAGE', $_SESSION['access_home_controller']); //Users home page
    define('SET_LANGUAGE', $_SESSION['access_language']); //Users language
    define('SET_IMAGE_PATH', 'themes/' . SET_THEME . '/images/');
    if ($_SESSION['access_show_graphics'] === 'Yes') {
        define('SET_SHOW_IMAGES', 'Yes');
    } else {
        define('SET_SHOW_IMAGES', 'No');
    }
// Load all language files from translations directory and subdirectories
    $translationsDir = SET_INSTALL_PATH . 'translations/';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($translationsDir)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php' && str_ends_with($file->getFilename(), '.lang.php')) {
            SharedMethods::loadConstantFromIni($file->getPathname());
        }
    }


    \Adlexone\support\MenuOptions::prepare();

    if (@$_GET['controller'] === 'full_page_view') {
        include 'app/Http/Controllers/full_page_view.php';
    } else {
        include 'app/Http/Controllers/main.php';
    }
}