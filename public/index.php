<?php
declare(strict_types=1);
/**
 * Front controller that optionally loads Composer/vendor-style autoload,
 * then hands off to the legacy index.php so routing via $_GET remains intact.
 */
$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}
chdir(dirname(__DIR__));
require 'index.php';
