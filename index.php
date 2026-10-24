<?php
/**
 * Adlexone FlowIQ License Agreement 1.0
 * 
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where 
 *  Adlexone FlowIQ files contain the Adlexone FlowIQ license in the header of the file, the
 *  Adlexone FlowIQ License header must remain.
 * 3. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the data created or managed by your Adlexone FlowIQ installation.
 * 4. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for your Adlexone FlowIQ configuration or the hosting environment
 *  your Adlexone FlowIQ installation operates in. 
 * 5. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the Adlexone FlowIQ where the software has been modified by
 *  third parties (i.e. other than Adlexone), unless an agreement has been
 *  reached with Adlexone.
 * 6. By using the Adlexone FlowIQ, you are indicating your acceptance of the
 *  stated Adlexone FlowIQ License terms and conditions. 
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */
// Check for minimum PHP version. PHP_VERSION_ID covers the patch level (8.1.0 is 80100)
// and ignores any vendor suffix on the version string, such as 8.1.2-1ubuntu2.14.
// Keep this block in syntax that PHP 5.4 can parse, so an old server reads the message.
if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80100) {
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8', true, 500);
    }
    exit("Inlay needs PHP 8.1 or later. This server is running PHP " . PHP_VERSION . ".\n");
}
$missingExtensions = array();
foreach (array('pdo', 'pdo_sqlite', 'dom', 'libxml', 'ctype') as $extension) {
    if (!extension_loaded($extension)) {
        $missingExtensions[] = $extension;
    }
}
if ($missingExtensions !== array()) {
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8', true, 500);
    }
    exit(
        "Inlay cannot start. PHP " . PHP_VERSION . " is missing: " . implode(', ', $missingExtensions) . ".\n\n"
        . "Turn these extensions on for this site, then reload the page. In cPanel, open Select PHP Version and tick them under Extensions. "
        . "On a server you manage, install the matching PHP packages, for example php-sqlite3 and php-xml on Debian or Ubuntu.\n"
    );
}
//***** Initialize Adlexone - source / full version build
define('VERSION','1.0');
define('LAYOUT_PATH', 'layouts/');
define('THEME_PATH', 'site/themes/');
define('SITE_APPS_PATH', 'site/apps/');
include 'bootstrap/bootstrap.php'; #Allows viewing of source

