<?php
declare(strict_types=1);

/**
 * website/ is the sales site. It stays in the repository and out of the product.
 */
$root = dirname(__DIR__);

$release = (string) file_get_contents($root . '/bin/build-release.php');
if (!preg_match("/const EXCLUDE = \\[(.*?)\\];/s", $release, $match) || !preg_match("/'website'/", $match[1])) {
    fwrite(STDERR, "The release must leave out website/.\n");
    exit(1);
}

$htaccess = (string) file_get_contents($root . '/.htaccess');
if (!preg_match('/RewriteRule \^\([^)]*\|website\)/', $htaccess)) {
    fwrite(STDERR, "The install-root .htaccess must refuse website/.\n");
    exit(1);
}

$home = (string) file_get_contents($root . '/website/index.html');
foreach (['User demo', 'Administrators demo', 'Using Leave requests', 'Setting up Leave requests', 'id="demo-user"', 'id="demo-admin"'] as $needle) {
    if (!str_contains($home, $needle)) {
        fwrite(STDERR, "The home page must offer the Leave requests demos ({$needle}).\n");
        exit(1);
    }
}
$shots = ['user-1.png', 'user-2.png', 'user-3.png', 'user-4.png', 'admin-1.png', 'admin-2.png', 'admin-3.png'];
foreach ($shots as $shot) {
    if (!str_contains($home, 'assets/demo/' . $shot) || !is_file($root . '/website/assets/demo/' . $shot)) {
        fwrite(STDERR, "The home page is missing the demo picture {$shot}.\n");
        exit(1);
    }
}
if (str_contains($home, 'Approve Home to Roost') || str_contains($home, 'id="approved"') || str_contains($home, 'id="view-parties"')) {
    fwrite(STDERR, "The Publishing specimen is no longer the home page.\n");
    exit(1);
}
if (str_contains($home, 'hero-desk.jpg') || str_contains($home, 'How a record looks in use')) {
    fwrite(STDERR, "The home page hero is the Leave requests demos. The desk photograph and the second specimen are gone.\n");
    exit(1);
}

$pages = ['index.html', 'work.html', 'hosting.html', 'ask.html'];
foreach ($pages as $page) {
    $path = $root . '/website/' . $page;
    if (!is_file($path)) {
        fwrite(STDERR, "The sales site is missing website/{$page}.\n");
        exit(1);
    }
    $html = (string) file_get_contents($path);
    if (!str_contains($html, 'css/site.css') || !str_contains($html, 'Adlexone')) {
        fwrite(STDERR, "website/{$page} is not a page of the sales site.\n");
        exit(1);
    }
    if (preg_match('/<script\\b/i', $html) || preg_match('/https?:\\/\\//i', $html)) {
        fwrite(STDERR, "website/{$page} must stay self-contained. No scripts and no remote addresses.\n");
        exit(1);
    }
    if (!str_contains($html, 'name="robots" content="noindex, nofollow, noarchive"')) {
        fwrite(STDERR, "website/{$page} must stay out of search indexes while the live site is under construction.\n");
        exit(1);
    }
    if (!str_contains($html, 'Under construction')) {
        fwrite(STDERR, "website/{$page} must show the under-construction tag.\n");
        exit(1);
    }
}

$robots = (string) file_get_contents($root . '/website/robots.txt');
if (!preg_match('/User-agent:\\s*\\*/i', $robots) || !preg_match('/Disallow:\\s*\\//', $robots)) {
    fwrite(STDERR, "website/robots.txt must refuse every crawler.\n");
    exit(1);
}

$siteHtaccess = (string) file_get_contents($root . '/website/.htaccess');
if (!str_contains($siteHtaccess, 'X-Robots-Tag') || !str_contains($siteHtaccess, 'noindex')) {
    fwrite(STDERR, "website/.htaccess must send X-Robots-Tag noindex.\n");
    exit(1);
}

if (!is_file($root . '/website/css/site.css') || !is_file($root . '/website/fonts/OFL.txt')) {
    fwrite(STDERR, "The sales site is missing its stylesheet or the font licence.\n");
    exit(1);
}

$pictures = [
    'hero-desk.jpg',
    'one-folder.jpg',
    'many-folders.jpg',
    'manuscripts.jpg',
    'record-card.jpg',
    'date-stamp.jpg',
    'locked-cabinet.jpg',
    'letter-parcel.jpg',
];
foreach ($pictures as $picture) {
    if (!is_file($root . '/website/assets/pictures/' . $picture)) {
        fwrite(STDERR, "The sales site is missing website/assets/pictures/{$picture}.\n");
        exit(1);
    }
}

$php = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/website', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (str_ends_with(strtolower($file->getFilename()), '.php')) {
        $php[] = $file->getPathname();
    }
}
if ($php !== []) {
    fwrite(STDERR, "website/ must not contain PHP:\n  " . implode("\n  ", $php) . "\n");
    exit(1);
}

echo "sales site stays out of the product\n";
