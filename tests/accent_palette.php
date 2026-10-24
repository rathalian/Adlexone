<?php
declare(strict_types=1);

/**
 * Application accents follow the active theme: the first accent is the theme colour,
 * and the set has ten distinct colours.
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Adlexone\Html\Accent;

$themes = [];
foreach (glob($root . '/site/themes/*/theme.css') ?: [] as $css) {
    if (preg_match('/--inlay\s*:\s*(#[0-9a-fA-F]{6})/', (string) file_get_contents($css), $match) === 1) {
        $themes[basename(dirname($css))] = strtolower($match[1]);
    }
}
if ($themes === []) {
    fwrite(STDERR, "no themes found\n");
    exit(1);
}

$name = $argv[1] ?? '';
if ($name === '') {
    $failed = 0;
    foreach ($themes as $theme => $base) {
        $out = [];
        exec(PHP_BINARY . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($theme) . ' 2>&1', $out, $code);
        echo implode("\n", $out) . "\n";
        $failed += $code === 0 ? 0 : 1;
    }
    exit($failed > 0 ? 1 : 0);
}

define('SET_INSTALL_PATH', $root . '/');
define('SET_THEME', $name);
$palette = Accent::palette();
$problems = [];
if (count($palette) !== 10) {
    $problems[] = 'expected ten accents, got ' . count($palette);
}
if (($palette[0] ?? '') !== $themes[$name]) {
    $problems[] = 'first accent ' . ($palette[0] ?? 'none') . ' is not the theme colour ' . $themes[$name];
}
if (count(array_unique($palette)) !== count($palette)) {
    $problems[] = 'accents repeat';
}
foreach ($palette as $colour) {
    if (preg_match('/^#[0-9a-f]{6}$/', $colour) !== 1) {
        $problems[] = 'not a hex colour: ' . $colour;
    }
}
if ($problems !== []) {
    echo "FAIL {$name}: " . implode('; ', $problems) . "\n";
    exit(1);
}
echo "ok  {$name}: " . implode(' ', $palette) . "\n";
