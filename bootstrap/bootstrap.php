<?php
/** Bootstrap file to initialize the application
 *
 * This file sets up error reporting, loads configuration settings,
 * starts sessions, and includes necessary dependencies.
 */


/**
 * Browser addresses stay on the site root. A request that names the front
 * script is sent to the same query without it. POST is left alone so a form
 * aimed at the old address still submits.
 */
$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
if (
    ($requestMethod === 'GET' || $requestMethod === 'HEAD')
    && preg_match('#^(.*?)/index\.php/?(?:\?.*)?$#', $requestUri) === 1
) {
    $parts = parse_url($requestUri);
    $path = (string) ($parts['path'] ?? '');
    $path = preg_replace('#/index\.php/?$#', '/', $path) ?? '/';
    if ($path === '') {
        $path = '/';
    }
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    header('Location: ' . $path . $query, true, 302);
    exit;
}

/** * Autoload required classes using Composer
 */
require_once __DIR__ . '/../vendor/autoload.php';

/** * Import necessary classes
 */
use Adlexone\Http\Page;
use Adlexone\Http\PublicBaseUrl;
use Adlexone\Http\Request;
use Adlexone\Http\Router;
use Adlexone\Platform\OperatorScreen;
use Adlexone\Platform\Runtime;
use Adlexone\Platform\SetupScreen;
use Adlexone\Platform\StatusScreen;
use Adlexone\support\SharedMethods;

/**
 * Paths, then settings. The database is opened only for an installed site.
 * Setup and the operator screen run before SchemaMigrator so a missing
 * SQLite file is not created as an empty database on a GET.
 */
$baseDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
Runtime::define($baseDir);
Runtime::loadConfig();
Runtime::applyTimezone();

/** * Enable error reporting for debugging (disable in production)
 */

if (defined('SET_DEBUG_MODE') && SET_DEBUG_MODE == 'Yes') {
    error_reporting(defined('SET_ERROR_REPORTING_LEVEL') ? (int) SET_ERROR_REPORTING_LEVEL : E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
}

Runtime::startSession();

if (Runtime::mode() === Runtime::OPERATOR) {
    OperatorScreen::handle();
    Page::current()->send();
    exit;
}
if (Runtime::mode() === Runtime::SETUP) {
    SetupScreen::handle();
    Page::current()->send();
    exit;
}
if (Runtime::mode() === Runtime::SUSPENDED || Runtime::mode() === Runtime::UNKNOWN) {
    StatusScreen::handle();
    Page::current()->send();
    exit;
}

// Shared platform schema (app packs must not own tables).
\Adlexone\Database\SchemaMigrator::migrate();

/** Start output buffering to prevent headers already sent errors
 */
ob_start();


/**
 * Public origin for redirects / OAuth. See Adlexone\Http\PublicBaseUrl.
 * Override with SET_PUBLIC_BASE_URL; trust X-Forwarded-* only via SET_TRUSTED_PROXIES.
 */
PublicBaseUrl::defineConstants();

// Set default values
if (!defined('SET_DEFAULT_THEME')) {
    define('SET_DEFAULT_THEME', 'inlay-stone');
}
if (!defined('SET_DEFAULT_LANGUAGE')) {
    define('SET_DEFAULT_LANGUAGE', 'English');
}
if (!defined('SET_HOME_LEAD')) {
    define('SET_HOME_LEAD', 'This site keeps the applications, records, and rules for the work.');
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
    $_SESSION['secure_id'] = bin2hex(random_bytes(16));
    SharedMethods::loadConstantFromIni(SET_INSTALL_PATH . 'translations/' . SET_DEFAULT_LANGUAGE . '.lang.php');
    define('SET_THEME', \Adlexone\Theme\Theme::forLogin());
    Router::open('login');

} else {
    // Refresh permissions on every authenticated request so group changes apply immediately.
    \Adlexone\Auth\Access::hydrateSession((int) $_SESSION['access_user_id']);

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        \Adlexone\Auth\Csrf::enforceOrDeny();
        // Screens write the posted fields to table columns, so the token must not reach them.
        unset($_POST[\Adlexone\Auth\Csrf::FIELD]);
    }

    Request::normaliseNumbers();

    // A stored home such as application:service-centre still arrives as controller.
    $requestedController = (string) ($_GET['controller'] ?? '');
    if (str_contains($requestedController, ':')) {
        [$requestedController, $requestedApp] = explode(':', $requestedController, 2);
        $_GET['controller'] = $requestedController;
        if (trim((string) ($_GET['app'] ?? '')) === '') {
            $_GET['app'] = $requestedApp;
        }
    }
    // Setup theme and other user options.  If user is not logged in the theme is set to use default
    // Override default theme which represents a empty value for the users theme setting
    if ($_SESSION['access_theme'] != '') {
        define('SET_THEME', \Adlexone\Theme\Theme::canonicalize((string) $_SESSION['access_theme']));
    } else {
        define('SET_THEME', \Adlexone\Theme\Theme::canonicalize((string) SET_DEFAULT_THEME));
    }
    define('SET_IMAGE_PATH', \Adlexone\Theme\Theme::imagePath());
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

    $requested = Router::requested();
    Page::current()->useLayout(Router::bare($requested) ? Page::RESPONSE : Page::SHELL);
    Router::open($requested);
}

Page::current()->send();
