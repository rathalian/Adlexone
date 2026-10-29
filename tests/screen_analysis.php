<?php
declare(strict_types=1);

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
};

$root = dirname(__DIR__);
$neon = (string) file_get_contents($root . '/phpstan.neon.dist');
$baseline = (string) file_get_contents($root . '/phpstan-baseline.neon');
$workflow = (string) file_get_contents($root . '/.github/workflows/tests.yml');

$check(str_contains($neon, "        - src/Screen\n"), 'PHPStan analyses every screen');
$check(!str_contains($baseline, 'ItemScreen.php'), 'the item screen is clear of the PHPStan baseline');
$check(!str_contains($baseline, 'SearchScreen.php'), 'the search screen is clear of the PHPStan baseline');
$check(str_contains($workflow, 'php tests/run.php'), 'CI runs the test command');
$check(!str_contains($workflow, 'for f in tests/*.php'), 'CI does not keep a second loop over the checks');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
