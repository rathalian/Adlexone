<?php
declare(strict_types=1);

/**
 * Screens and shared layout must not emit inline script.
 * script-src can stay limited to this origin.
 */
$root = dirname(__DIR__);
$failed = 0;

$fail = static function (string $message) use (&$failed): void {
    $failed++;
    fwrite(STDERR, $message . "\n");
};

$scan = static function (string $path) use ($fail): void {
    $text = (string) file_get_contents($path);
    if (preg_match('/onclick\s*=|javascript\s*:/i', $text) === 1) {
        $fail($path . ' still emits an inline script or a javascript: link');
    }
};

$directories = [
    $root . '/src/Screen',
    $root . '/src/Html',
    $root . '/layouts/pages',
    $root . '/layouts/partials',
];
foreach ($directories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $name = $file->getFilename();
        if (!str_ends_with($name, '.php') && !str_ends_with($name, '.js')) {
            continue;
        }
        $scan($file->getPathname());
    }
}

$support = new DirectoryIterator($root . '/src/support');
foreach ($support as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
        continue;
    }
    if ($file->getFilename() === 'RenderNavigation.php') {
        continue;
    }
    $scan($file->getPathname());
}

$app = (string) file_get_contents($root . '/layouts/js/app.js');
if (str_contains($app, 'new Function') || preg_match('/onclick\s*=/i', $app) === 1) {
    $fail('layouts/js/app.js still builds a script from an attribute');
}
if (!str_contains($app, 'data-field-check') || !str_contains($app, 'data-sort') || !str_contains($app, 'data-history')) {
    $fail('layouts/js/app.js does not handle the replacement controls');
}

$mode = (string) file_get_contents($root . '/layouts/js/mode.js');
if (!str_contains($mode, "setAttribute('data-mode'") && !str_contains($mode, 'setAttribute("data-mode"')) {
    $fail('layouts/js/mode.js must set data-mode before the page paints');
}

$head = (string) file_get_contents($root . '/layouts/partials/head.php');
if (preg_match('/<script(?![^>]*\bsrc=)/i', $head) === 1) {
    $fail('The page head still contains an inline script');
}
if (!str_contains($head, "jsHref('mode')")) {
    $fail('The page head must load layouts/js/mode.js');
}

$page = (string) file_get_contents($root . '/src/Http/Page.php');
if (preg_match('/script-src[^;]*unsafe-inline/', $page) === 1) {
    $fail('script-src still allows inline script');
}
if (!str_contains($page, "script-src 'self';")) {
    $fail("script-src must be 'self' only");
}

if ($failed > 0) {
    fwrite(STDERR, $failed . " checks failed\n");
    exit(1);
}

echo "inline script checks passed\n";
