<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$head = (string) file_get_contents($root . '/layouts/partials/head.php');
$robots = (string) file_get_contents($root . '/public/robots.txt');

if (!str_contains($head, '<meta name="robots" content="noindex, nofollow, noarchive">')) {
    fwrite(STDERR, "The shared page head must tell search engines not to index Adlexone pages.\n");
    exit(1);
}

if (!str_contains($robots, "User-agent: *\nAllow: /")) {
    fwrite(STDERR, "robots.txt must let crawlers read the HTML noindex directive.\n");
    exit(1);
}

echo "noindex directives ok\n";
