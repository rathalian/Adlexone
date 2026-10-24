<?php
declare(strict_types=1);
/**
 * Document root. Only this folder is on the web.
 * CSS, fonts, and theme images live beside it and are streamed from here.
 */
$uriPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
$relative = ltrim(rawurldecode($uriPath), '/');
$local = __DIR__ . '/' . $relative;
if (
    PHP_SAPI === 'cli-server'
    && $relative !== ''
    && $relative !== 'index.php'
    && is_file($local)
) {
    return false;
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

if (\Adlexone\Platform\StaticFiles::serve(dirname(__DIR__), $uriPath)) {
    exit;
}

chdir(dirname(__DIR__));
require 'index.php';
