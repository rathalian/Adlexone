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
foreach ([
    'Build an application for a line of work.',
    'The screen a person uses',
    'What you set',
    'id="you-set"',
    'See Repairs in use',
    'Kitchen light',
    'You can <a href="hosting.html">host it yourself</a>, or it can be <a href="ask.html">cloud hosted</a> by Adlexone.',
    'Blank starts with no fields.',
    'given a note, or given a file',
    'set the owner',
    'A menu can have a child menu',
    'The record has an owner',
    'id="repairs-demo"',
    'href="#repairs-demo"',
] as $needle) {
    if (!str_contains($home, $needle)) {
        fwrite(STDERR, "The home page must say what an application is, then offer Repairs as the example ({$needle}).\n");
        exit(1);
    }
}
if (
    str_contains($home, 'id="leave-demo"')
    || str_contains($home, 'id="admin-demo"')
    || str_contains($home, 'id="publishing-demo"')
    || str_contains($home, 'id="build-demo"')
    || str_contains($home, 'You run it on your server')
    || str_contains($home, 'You run the software on your server')
    || str_contains($home, 'when a status changes')
) {
    fwrite(STDERR, "The home page is the two beats and the Repairs tour. Hosting is the byline.\n");
    exit(1);
}
$homeShots = [
    'repairs-1.png',
    'repairs-2.png',
    'repairs-3.png',
    'repairs-4.png',
];
foreach ($homeShots as $shot) {
    if (!str_contains($home, 'assets/demo/' . $shot) || !is_file($root . '/website/assets/demo/' . $shot)) {
        fwrite(STDERR, "The home page is missing the demo picture {$shot}.\n");
        exit(1);
    }
}
if (str_contains($home, 'publishing-1.png') || str_contains($home, 'publishing-admin-1.png')) {
    fwrite(STDERR, "The home page uses the Repairs stills.\n");
    exit(1);
}
$kept = ['user-1.png', 'user-2.png', 'user-3.png', 'user-4.png'];
foreach ($kept as $shot) {
    if (!is_file($root . '/website/assets/demo/' . $shot)) {
        fwrite(STDERR, "The sales site is missing the leave still {$shot}.\n");
        exit(1);
    }
}
$work = (string) file_get_contents($root . '/website/work.html');
if (
    !str_contains($work, 'Ways to start.')
    || !str_contains($work, 'index.html#repairs-demo')
    || str_contains($work, 'index.html#leave-demo')
    || str_contains($work, 'index.html#publishing-demo')
    || str_contains($work, 'Publishing is the example')
    || !str_contains($work, '<h3>Search</h3>')
    || !str_contains($work, 'The name in the corner starts as Adlexone.')
    || !str_contains($work, 'Up to four actions run in order.')
) {
    fwrite(STDERR, "The applications page lists ways to start, with Repairs as the home example.\n");
    exit(1);
}
$ask = (string) file_get_contents($root . '/website/ask.html');
$hosting = (string) file_get_contents($root . '/website/hosting.html');
if (!str_contains($ask, 'host it yourself') || !str_contains($ask, 'Adlexone FlowIQ Licence') || !str_contains($ask, 'Health')) {
    fwrite(STDERR, "Ask stays a letter for a zip, with the licence and the Health example.\n");
    exit(1);
}
if (!str_contains($hosting, 'Host it yourself.') || !str_contains($hosting, 'health.example.com') || !str_contains($hosting, 'cloud hosted')) {
    fwrite(STDERR, "Hosting is the steps for running it yourself, opened from the byline.\n");
    exit(1);
}
foreach (['index.html', 'work.html', 'ask.html', 'hosting.html'] as $page) {
    $nav = (string) file_get_contents($root . '/website/' . $page);
    if (str_contains($nav, '>Hosting<')) {
        fwrite(STDERR, "website/{$page} must not put Hosting in the navigation.\n");
        exit(1);
    }
}
if (str_contains($home, 'Approve Home to Roost') || str_contains($home, 'id="approved"') || str_contains($home, 'id="view-parties"')) {
    fwrite(STDERR, "The home page uses stills. It does not embed the old Publishing specimen.\n");
    exit(1);
}
if (str_contains($home, 'hero-desk.jpg') || str_contains($home, 'How a record looks in use')) {
    fwrite(STDERR, "The home page hero is a screenshot. The desk photograph and the second specimen are gone.\n");
    exit(1);
}
if (str_contains($home, 'Under construction')) {
    fwrite(STDERR, "The home page is the sales page, held back from search while it is being tested.\n");
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
    if (!str_contains($html, '<meta name="robots" content="noindex, nofollow, noarchive">') || str_contains($html, 'Under construction')) {
        fwrite(STDERR, "website/{$page} is a testing page and must tell search engines not to index it.\n");
        exit(1);
    }
}

$robots = (string) file_get_contents($root . '/website/robots.txt');
if (!preg_match('/User-agent:\\s*\\*/i', $robots) || !preg_match('/Disallow:\\s*\\//', $robots)) {
    fwrite(STDERR, "website/robots.txt must tell crawlers not to read the testing site.\n");
    exit(1);
}

$siteHtaccess = (string) file_get_contents($root . '/website/.htaccess');
if (!str_contains($siteHtaccess, 'X-Robots-Tag') || !str_contains($siteHtaccess, 'noindex')) {
    fwrite(STDERR, "website/.htaccess must send a noindex header while the site is for testing.\n");
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
