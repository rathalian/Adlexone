<?php
declare(strict_types=1);

/**
 * Names the screens read from the language file. PHPStan does not load that file.
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
