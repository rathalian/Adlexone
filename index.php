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
// Check for minimum PHP version
(phpversion() >= "8.0") or exit ('ERROR: You must have PHP Verson 8.0 or higher installed');
$missingExtensions = array_values(array_filter(
    ['pdo', 'pdo_sqlite', 'dom', 'libxml', 'mbstring', 'ctype'],
    static fn (string $extension): bool => !extension_loaded($extension)
));
if ($missingExtensions !== []) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    exit(
        "Inlay cannot start. PHP " . PHP_VERSION . " is missing: " . implode(', ', $missingExtensions) . ".\n\n"
        . "Turn these extensions on for this site, then reload the page. In cPanel, open Select PHP Version and tick them under Extensions. "
        . "On a server you manage, install the matching PHP packages, for example php-sqlite3, php-xml, and php-mbstring on Debian or Ubuntu.\n"
    );
}
//***** Initialize Adlexone - source / full version build
define('VERSION','3.0');
define('LAYOUT_PATH', 'layouts/');
define('THEME_PATH', 'site/themes/');
define('SITE_APPS_PATH', 'site/apps/');
include 'bootstrap/bootstrap.php'; #Allows viewing of source

