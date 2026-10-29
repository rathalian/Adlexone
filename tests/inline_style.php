<?php
declare(strict_types=1);

/**
 * Screens and shared layout must not emit a style attribute.
 * style-src can stay limited to this origin.
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Adlexone\Html\Accent;
use Adlexone\Html\Charts;
use Adlexone\Html\Measure;

$failed = 0;

$fail = static function (string $message) use (&$failed): void {
    $failed++;
    fwrite(STDERR, $message . "\n");
};

$scan = static function (string $path) use ($fail): void {
    $text = (string) file_get_contents($path);
    if (preg_match('/\sstyle\s*=/i', $text) === 1) {
        $fail($path . ' still emits a style attribute');
    }
};

$directories = [
    $root . '/src',
    $root . '/layouts/pages',
    $root . '/layouts/partials',
];
foreach ($directories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
            continue;
        }
        $scan($file->getPathname());
    }
}

$page = (string) file_get_contents($root . '/src/Http/Page.php');
if (preg_match('/style-src[^;]*unsafe-inline/', $page) === 1) {
    $fail('style-src still allows inline styles');
}
if (!str_contains($page, "style-src 'self';")) {
    $fail("style-src must be 'self' only");
}

$head = (string) file_get_contents($root . '/layouts/partials/head.php');
if (!str_contains($head, "layoutCssHref('scale')")) {
    $fail('The page head must load layouts/css/scale.css');
}

$css = (string) file_get_contents($root . '/layouts/css/scale.css');
foreach ([0, 1, 40, 330, 842, 1000] as $tenths) {
    $label = number_format($tenths / 10, 1, '.', '');
    if (!str_contains($css, '.w-' . $tenths . '{--w:' . $label . '%}')) {
        $fail('scale.css is missing width ' . $tenths);
    }
    if (!str_contains($css, '.h-' . $tenths . '{--h:' . $label . '%}')) {
        $fail('scale.css is missing height ' . $tenths);
    }
}
if (!str_contains($css, '.hue-210{--h:210}') || !str_contains($css, '.hue-0{--h:0}') || !str_contains($css, '.hue-359{--h:359}')) {
    $fail('scale.css is missing a hue class');
}
if (!str_contains($css, '.cr-37{--ar:37}') || !str_contains($css, '.cg-99{--ag:99}') || !str_contains($css, '.cb-235{--ab:235}')) {
    $fail('scale.css is missing a colour channel');
}
if (!str_contains($css, '.cr-0{--ar:0}') || !str_contains($css, '.cb-255{--ab:255}')) {
    $fail('scale.css does not cover the ends of a colour channel');
}

$base = (string) file_get_contents($root . '/layouts/css/base.css');
if (!str_contains($base, '.menu-depth-3{ margin-left:60px; }') || !str_contains($base, '.cols-12{ --cols:12; }')) {
    $fail('base.css is missing a menu indent or a column count');
}
if (!str_contains($base, 'background:rgb(var(--ar) var(--ag) var(--ab));')) {
    $fail('a theme swatch must take its colour from the channel classes');
}

$uplift = (string) file_get_contents($root . '/layouts/css/uplift.css');
if (str_contains($uplift, '[style*="--accent"]')) {
    $fail('uplift.css still looks for an inline accent');
}
if (!str_contains($uplift, '--accent:rgb(var(--ar) var(--ag) var(--ab));')) {
    $fail('an accent class must set the colour from its channels');
}

if (Measure::width(84.2) !== 'w-842' || Measure::width(0) !== 'w-0' || Measure::width(100) !== 'w-1000') {
    $fail('width classes must be tenths of a percent from 0 to 100');
}
if (Measure::width(-4) !== 'w-0' || Measure::width(140) !== 'w-1000' || Measure::width(NAN) !== 'w-0') {
    $fail('width classes must stay inside 0 to 100 percent');
}
if (Measure::height(4) !== 'h-40' || Measure::hue(210) !== 'hue-210' || Measure::hue(-1) !== 'hue-359') {
    $fail('height or hue classes are wrong');
}
if (Measure::depth(3) !== 'menu-depth-3' || Measure::depth(40) !== 'menu-depth-12' || Measure::columns(0) !== 'cols-1' || Measure::columns(20) !== 'cols-12') {
    $fail('indent or column classes are wrong');
}

$shares = Measure::portions([1, 1, 1]);
if (array_sum($shares) !== 1000 || count($shares) !== 3) {
    $fail('three equal parts must fill the bar');
}
if (Measure::portions([5, 5]) !== [500, 500] || Measure::portions([2]) !== [1000] || Measure::portions([0, 0]) !== [0, 0]) {
    $fail('portions must split a total and leave zeros alone');
}

if (Accent::classes('#2563eb') !== 'has-accent cr-37 cg-99 cb-235') {
    $fail('an accent class must carry the hex colour as channels');
}
if (Accent::classes('#abc') !== 'has-accent cr-170 cg-187 cb-204') {
    $fail('a short hex colour must expand');
}
if (Accent::channels('#1c1917') !== 'cr-28 cg-25 cb-23' || Accent::classes('blue') !== '' || Accent::classes('#12') !== '') {
    $fail('a colour that is not hex must not invent a class');
}

$bar = Charts::bars([
    ['label' => 'Open', 'value' => 3],
    ['label' => 'Closed', 'value' => 1],
]);
$stack = Charts::stack([
    ['label' => 'Open', 'value' => 3],
    ['label' => 'Closed', 'value' => 1],
]);
$columns = Charts::columns([
    ['label' => 'Mon', 'value' => 2],
    ['label' => 'Tue', 'value' => 4],
]);
foreach (['Open' => $bar, 'stack' => $stack, 'columns' => $columns] as $name => $html) {
    if (preg_match('/\sstyle\s*=/i', $html) === 1) {
        $fail($name . ' still emits a style attribute');
    }
}
if (!str_contains($bar, 'bars__fill w-1000') || !str_contains($bar, 'bars__fill w-333')) {
    $fail('a bar must use a width class');
}
if (substr_count($stack, 'stack__part') !== 2 || !preg_match_all('/w-(\d+)/', $stack, $found) || array_sum(array_map('intval', $found[1])) !== 1000) {
    $fail('stack parts must use width classes that add to the whole bar');
}
if (!str_contains($columns, 'columns__bar h-500') || !str_contains($columns, 'columns__bar h-1000')) {
    $fail('columns must use a height class');
}

if ($failed > 0) {
    fwrite(STDERR, $failed . " checks failed\n");
    exit(1);
}

echo "inline style checks passed\n";
