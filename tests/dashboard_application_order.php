<?php
declare(strict_types=1);

/**
 * The dashboard must preserve the application order supplied by ApplicationStore::menuItems().
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Screen\HomeDashboard;

$application = static function (string $name, int $touched): array {
    return [
        'slug' => strtolower($name),
        'name' => $name,
        'hint' => '',
        'icon' => 'ic-grid',
        'href' => '?application=' . strtolower($name),
        'type' => 0,
        'accent' => '#2563eb',
        'count' => 0,
        'touched' => $touched,
        'series' => [],
        'owners' => [],
    ];
};

$apps = [
    $application('Matters', 10),
    $application('Conveyancing', 20),
    $application('Claims', 30),
];

// Claims has more assigned work and more recent activity. Those figures belong on its card,
// but neither should override the order chosen on Configure → Applications.
$assigned = [
    ['name' => 'Claims', 'count' => 9],
    ['name' => 'Conveyancing', 'count' => 2],
];

$method = new ReflectionMethod(HomeDashboard::class, 'applications');
$html = (string) $method->invoke(null, $apps, $assigned);

$matters = strpos($html, '>Matters</span>');
$conveyancing = strpos($html, '>Conveyancing</span>');
$claims = strpos($html, '>Claims</span>');

if ($matters === false || $conveyancing === false || $claims === false) {
    fwrite(STDERR, "FAIL dashboard did not render every application card\n");
    exit(1);
}
if (!($matters < $conveyancing && $conveyancing < $claims)) {
    fwrite(STDERR, "FAIL dashboard reordered applications by assigned work or activity\n");
    exit(1);
}
if (!str_contains($html, '9 records assigned')) {
    fwrite(STDERR, "FAIL dashboard lost assigned-work figures while preserving order\n");
    exit(1);
}

echo "dashboard application order checks passed\n";
