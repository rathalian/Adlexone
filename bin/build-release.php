<?php
declare(strict_types=1);

/**
 * Builds a release zip from the last commit on the current branch.
 *
 *   php bin/build-release.php [--with-public] [output-directory]
 *
 * The zip unpacks straight into a web server document root (no wrapper folder).
 * It never holds config/*.json, a SQLite database, or attachments, so unpacking
 * a newer release over a live site replaces the code and keeps the data.
 *
 * The zip is for a document root at the install folder, where the root
 * .htaccess keeps settings, data, and code closed. It leaves out public/,
 * because public/.htaccess would reopen that folder under the install root.
 * Pass --with-public for a host that lets you set the document root to public/.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "PHP 8.1 or later is needed to build a release.\n");
    exit(1);
}
foreach (['phar', 'zlib'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "The {$extension} extension is needed to build a release.\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
chdir($root);

$withPublic = false;
$positional = [];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--with-public') {
        $withPublic = true;
    } elseif (str_starts_with($argument, '-')) {
        fwrite(STDERR, "Unknown option {$argument}.\nUsage: php bin/build-release.php [--with-public] [output-directory]\n");
        exit(1);
    } else {
        $positional[] = $argument;
    }
}

/** Paths in the repository that stay out of the release. */
const EXCLUDE = [
    '.cursor',
    '.gitattributes',
    '.gitignore',
    'AGENTS.md',
    'bin',
    'composer.phar',
    'dist',
    'tests',
    'site/themes/workos-demo.html',
];

/** Paths that must exist in the zip, or the site cannot run. */
const REQUIRED = [
    '.htaccess',
    'index.php',
    'bootstrap/bootstrap.php',
    'bootstrap/mbstring_fallback.php',
    'vendor/composer/autoload_files.php',
    'vendor/autoload.php',
    'vendor/composer/autoload_classmap.php',
    'config/.htaccess',
    'config.example/adlexone_settings.json',
    'storage/.htaccess',
    'storage/database/shared_schema.sql',
    'storage/attachments/.gitkeep',
    'layouts',
    'site/themes',
    'src',
    'translations',
];

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function run(string $command): string
{
    $output = [];
    $code = 0;
    exec($command . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fail("`{$command}` failed:\n" . implode("\n", $output));
    }

    return trim(implode("\n", $output));
}

function removeTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    rmdir($path);
}

/** @return list<string> Paths relative to $base, using forward slashes. */
function listFiles(string $base): array
{
    $found = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        $found[] = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
    }
    sort($found);

    return $found;
}

if (!is_dir($root . '/.git')) {
    fail('This is not a git checkout. A release is built from a commit.');
}

$indexSource = (string) file_get_contents($root . '/index.php');
if (preg_match("/define\\('VERSION',\\s*'([^']+)'\\)/", $indexSource, $match) !== 1) {
    fail("index.php does not define VERSION.");
}
$version = $match[1];
$commit = run('git rev-parse --short=10 HEAD');
$branch = run('git rev-parse --abbrev-ref HEAD');

exec('git diff --quiet HEAD', $unused, $dirty);
if ($dirty !== 0) {
    fwrite(STDERR, "Note: uncommitted changes are not in the release. It is built from commit {$commit}.\n");
}

$classmap = (string) file_get_contents($root . '/vendor/composer/autoload_classmap.php');
$unmapped = [];
foreach (listFiles($root . '/src') as $relative) {
    if (!str_ends_with($relative, '.php')) {
        continue;
    }
    $source = (string) file_get_contents($root . '/src/' . $relative);
    $declaresType = preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+\w+/m', $source) === 1;
    if ($declaresType && !str_contains($classmap, "'/src/" . $relative . "'")) {
        $unmapped[] = 'src/' . $relative;
    }
}
if ($unmapped !== []) {
    fail(
        "The Composer classmap is out of date. Run `php composer.phar dump-autoload -o`, commit vendor/, and build again.\nNot mapped:\n  "
        . implode("\n  ", $unmapped)
    );
}

$outDir = $positional[0] ?? ($root . '/dist');
if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fail("Cannot create {$outDir}.");
}
$outDir = realpath($outDir) ?: $outDir;
$name = "inlay-{$version}-{$commit}" . ($withPublic ? '-public' : '');
$zipPath = $outDir . DIRECTORY_SEPARATOR . $name . '.zip';

$work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inlay-release-' . bin2hex(random_bytes(4));
$staging = $work . DIRECTORY_SEPARATOR . 'site';
mkdir($staging, 0755, true);

try {
    $tarPath = $work . DIRECTORY_SEPARATOR . 'source.tar';
    run('git archive --format=tar --output=' . escapeshellarg($tarPath) . ' HEAD');
    (new PharData($tarPath))->extractTo($staging, null, true);

    $exclude = $withPublic ? EXCLUDE : [...EXCLUDE, 'public'];
    foreach ($exclude as $path) {
        removeTree($staging . '/' . $path);
    }

    $leaked = array_filter(
        listFiles($staging),
        static fn (string $file): bool => preg_match('#^config/[^/]+\.json$#', $file) === 1
            || preg_match('#\.sqlite(-wal|-shm)?$#', $file) === 1
            || (str_starts_with($file, 'storage/attachments/') && $file !== 'storage/attachments/.gitkeep')
    );
    if ($leaked !== []) {
        fail("Live settings or data are tracked in git and would ship:\n  " . implode("\n  ", $leaked));
    }

    $required = $withPublic ? [...REQUIRED, 'public/index.php'] : REQUIRED;
    foreach ($required as $path) {
        if (!file_exists($staging . '/' . $path)) {
            fail("The release is missing {$path}.");
        }
    }

    file_put_contents(
        $staging . '/bootstrap/RELEASE.txt',
        "Inlay {$version}\nCommit {$commit} ({$branch})\nBuilt " . gmdate('Y-m-d H:i:s') . " UTC\n"
    );

    if (is_file($zipPath)) {
        unlink($zipPath);
    }
    $zip = new PharData($zipPath, 0, null, Phar::ZIP);
    $zip->buildFromDirectory($staging);
    $zip->compressFiles(Phar::GZ);
    unset($zip);
} finally {
    removeTree($work);
}

$check = new PharData($zipPath);
$entries = [];
foreach (new RecursiveIteratorIterator($check) as $entry) {
    $entries[] = ltrim(str_replace('\\', '/', substr((string) $entry->getPathname(), strlen('phar://' . $zipPath))), '/');
}
foreach (['index.php', '.htaccess', 'vendor/autoload.php'] as $path) {
    if (!in_array($path, $entries, true)) {
        fail("The finished zip does not contain {$path}.");
    }
}

$hash = hash_file('sha256', $zipPath);
file_put_contents($zipPath . '.sha256', $hash . '  ' . basename($zipPath) . "\n");

printf(
    "Built %s\n  %d files, %s KB\n  sha256 %s\n",
    $zipPath,
    count($entries),
    number_format((int) round(filesize($zipPath) / 1024)),
    $hash
);
