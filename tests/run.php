<?php
declare(strict_types=1);

/**
 * Run every PHP check under tests/. A failing script stops the run.
 */
$files = glob(__DIR__ . '/*.php') ?: [];
sort($files, SORT_STRING);
$ran = 0;
foreach ($files as $file) {
    if (basename($file) === 'run.php') {
        continue;
    }
    $ran++;
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file);
    passthru($command, $status);
    if ($status !== 0) {
        fwrite(STDERR, basename($file) . " failed\n");
        exit($status === 0 ? 1 : $status);
    }
}
if ($ran === 0) {
    fwrite(STDERR, "no tests found\n");
    exit(1);
}
echo "ran {$ran} checks\n";
exit(0);
