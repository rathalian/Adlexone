<?php
declare(strict_types=1);

/**
 * Locks the facts from the code health scan in design/code-health.md.
 *
 * A file may drop off an allowlist. A new file may not join one.
 * Unused-import counts may fall. They may not rise, and a file that was
 * clean may not gain an unused import. When a count falls, lower it here
 * in the same commit so the gain sticks.
 */

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

/** @param list<string> $trees */
$phpFiles = static function (array $trees) use ($root): array {
    $files = [];
    foreach ($trees as $tree) {
        $dir = $root . '/' . $tree;
        if (!is_dir($dir)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
    }
    sort($files, SORT_STRING);

    return $files;
};

$productTrees = ['src', 'layouts', 'bootstrap', 'public', 'bin', 'site'];

$neon = (string) file_get_contents($root . '/phpstan.neon.dist');
preg_match_all('/^        - (src\/[A-Za-z0-9_]+)\s*$/m', $neon, $pathMatch);
$analysed = $pathMatch[1];
$srcDirs = [];
foreach (glob($root . '/src/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $srcDirs[] = 'src/' . basename($dir);
}
sort($srcDirs, SORT_STRING);
$missing = array_values(array_diff($srcDirs, $analysed));
$unexpected = array_values(array_diff($missing, ['src/Text', 'src/Theme']));
$check(
    $unexpected === [],
    'PHPStan covers every src directory except Text and Theme'
    . ($unexpected === [] ? '' : ': ' . implode(', ', $unexpected))
);
$check(in_array('src/Screen', $analysed, true), 'PHPStan still analyses screens');

preg_match('/excludePaths:\n((?:        - .+\n)*)/', $neon, $excludeMatch);
$excludes = [];
if (isset($excludeMatch[1])) {
    preg_match_all('/^        - (\S+)\s*$/m', $excludeMatch[1], $excludeLines);
    $excludes = $excludeLines[1];
}
$extraExcludes = array_values(array_diff($excludes, ['src/Platform/SharedMethods.php']));
$check(
    $extraExcludes === [],
    'PHPStan excludes SharedMethods.php only'
    . ($extraExcludes === [] ? '' : ': ' . implode(', ', $extraExcludes))
);

/** @param '$_GET'|'$_POST' $name */
$superglobalFiles = static function (string $name) use ($phpFiles, $productTrees, $root): array {
    $found = [];
    foreach ($phpFiles($productTrees) as $rel) {
        $tokens = token_get_all((string) file_get_contents($root . '/' . $rel));
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === $name) {
                $found[] = $rel;
                break;
            }
        }
    }
    sort($found, SORT_STRING);

    return $found;
};

$getAllowed = [
    'bootstrap/bootstrap.php',
    'src/Application/ApplicationAccess.php',
    'src/Application/Capabilities.php',
    'src/Auth/AuthRouter.php',
    'src/Http/RenderNavigation.php',
    'src/Http/Request.php',
    'src/Theme/Theme.php',
];
$getExtra = array_values(array_diff($superglobalFiles('$_GET'), $getAllowed));
$check(
    $getExtra === [],
    '$_GET is read only from the scanned files'
    . ($getExtra === [] ? '' : ': ' . implode(', ', $getExtra))
);

$postAllowed = [
    'bootstrap/bootstrap.php',
    'src/Application/RecordQuery.php',
    'src/Application/RuleRunner.php',
    'src/Auth/AuthRouter.php',
    'src/Auth/Csrf.php',
    'src/Http/Request.php',
];
$postExtra = array_values(array_diff($superglobalFiles('$_POST'), $postAllowed));
$check(
    $postExtra === [],
    '$_POST is read only from the scanned files'
    . ($postExtra === [] ? '' : ': ' . implode(', ', $postExtra))
);

/** @return list<string> */
$filesContaining = static function (string $needle) use ($root, $productTrees): array {
    $found = [];
    foreach ($productTrees as $tree) {
        $dir = $root . '/' . $tree;
        if (!is_dir($dir)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['php', 'css', 'js'], true)) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (is_string($contents) && str_contains($contents, $needle)) {
                $found[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
    }
    sort($found, SORT_STRING);

    return $found;
};

$urlClassAllowed = [
    'src/Html/Form.php',
    'src/Screen/FieldsScreen.php',
    'src/Screen/ItemList.php',
];
$urlExtra = array_values(array_diff($filesContaining('class="URL"'), $urlClassAllowed));
$check(
    $urlExtra === [],
    'class="URL" stays in Form, the fields screen, and the item list'
    . ($urlExtra === [] ? '' : ': ' . implode(', ', $urlExtra))
);
$linkExtra = array_values(array_diff($filesContaining('class="link URL"'), ['src/Html/Form.php']));
$check(
    $linkExtra === [],
    'class="link URL" stays in Form.php'
    . ($linkExtra === [] ? '' : ': ' . implode(', ', $linkExtra))
);

$product = '';
foreach ($phpFiles($productTrees) as $rel) {
    $chunk = file_get_contents($root . '/' . $rel);
    if (is_string($chunk)) {
        $product .= $chunk;
    }
}
foreach ([
    'buildForm(',
    'buildStartForm',
    'buildFormFieldsGrid',
    'buildEndFormWithButtons',
    'buildFormSectionHeading',
] as $name) {
    $check(!str_contains($product, $name), $name . ' stays out of the product');
}

/**
 * Short names from a `use` line that never appear again in that file.
 *
 * @return array<string, int>
 */
$unusedImportCounts = static function () use ($root): array {
    $counts = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)
    );
    $files = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files, SORT_STRING);
    foreach ($files as $path) {
        $code = (string) file_get_contents($path);
        preg_match_all('/^use\s+([^;]+);/m', $code, $uses);
        $body = preg_replace('/^use\s+[^;]+;/m', '', $code);
        $body = is_string($body) ? preg_replace('#^\s*//.*$#m', '', $body) : '';
        $body = is_string($body) ? $body : '';
        $dead = 0;
        foreach ($uses[1] as $raw) {
            $raw = trim($raw);
            if (str_starts_with($raw, 'function ') || str_starts_with($raw, 'const ')) {
                continue;
            }
            if (preg_match('/^(.*?)\s+as\s+(\w+)$/', $raw, $alias) === 1) {
                $short = $alias[2];
            } else {
                $parts = explode('\\', $raw);
                $short = (string) end($parts);
            }
            if ($short === '') {
                continue;
            }
            if (preg_match('/(?<![A-Za-z0-9_\\\\])' . preg_quote($short, '/') . '(?![A-Za-z0-9_])/', $body) !== 1) {
                $dead++;
            }
        }
        if ($dead > 0) {
            $counts[substr($path, strlen($root) + 1)] = $dead;
        }
    }

    return $counts;
};

/** Scan ceiling. Lower a number in the same commit that removes imports. */
$importCeiling = [
    'src/Application/BlueprintCatalog.php' => 4,
    'src/Application/Rules.php' => 2,
    'src/Auth/AuthSession.php' => 1,
    'src/Http/RenderNavigation.php' => 2,
    'src/Screen/BuilderScreen.php' => 1,
    'src/Screen/ItemAccess.php' => 17,
    'src/Screen/ItemFiles.php' => 18,
    'src/Screen/ItemList.php' => 13,
    'src/Screen/ItemLog.php' => 18,
    'src/Screen/ItemScreen.php' => 16,
    'src/Screen/ItemSheet.php' => 9,
    'src/Screen/ItemWrite.php' => 15,
    'src/Screen/PrintScreen.php' => 1,
    'src/Screen/RecordCensus.php' => 13,
    'src/Screen/RecordPeople.php' => 19,
    'src/Screen/RecordPlan.php' => 18,
    'src/Screen/RecordRegister.php' => 7,
    'src/Screen/RecordScreens.php' => 12,
    'src/Screen/SearchForm.php' => 6,
    'src/Screen/SearchLibrary.php' => 6,
    'src/Screen/SearchResults.php' => 6,
    'src/Screen/SearchScreen.php' => 9,
    'src/Screen/WorkBoard.php' => 6,
    'src/Screen/WorkList.php' => 7,
    'src/Screen/WorkScreen.php' => 3,
];
$importCounts = $unusedImportCounts();
$importProblems = [];
foreach ($importCounts as $rel => $count) {
    $ceiling = $importCeiling[$rel] ?? 0;
    if ($count > $ceiling) {
        $importProblems[] = $rel . ' has ' . $count . ' (ceiling ' . $ceiling . ')';
    }
}
$check(
    $importProblems === [],
    'unused imports stayed within the scan ceiling of ' . array_sum($importCeiling)
    . ($importProblems === [] ? '' : ': ' . implode('; ', $importProblems))
);

$imageAllowed = [
    'actions.png',
    'advancedSearchBig.png',
    'announcements.png',
    'announcementsBig.png',
    'announcementsSmall.png',
    'clock.png',
    'complexSearch.png',
    'contentBackGround.png',
    'contentDivider.png',
    'costCenter.png',
    'createReport.png',
    'dateRange.png',
    'favicon.png',
    'heading.png',
    'headingBackground.png',
    'headingImage.png',
    'headingImagev2.png',
    'helpdesk.png',
    'helpdeskSettings.png',
    'item.png',
    'itemSettings.png',
    'kb.png',
    'key.png',
    'knowledgebase.png',
    'launch.png',
    'leftNavTopLeft.png',
    'leftNavTopMiddle.png',
    'leftNavTopRight.png',
    'manageActions.png',
    'manageCriteria.png',
    'manageItemTypes.png',
    'manageItems.png',
    'manageReports.png',
    'manageSecurity.png',
    'manual.png',
    'myItems.png',
    'newCriteria.png',
    'newItem.png',
    'newTicketBig.png',
    'ooz.gif',
    'pendingtime.png',
    'portal.png',
    'portalBig.png',
    'procedures.png',
    'profile.png',
    'projectReport.png',
    'relationship.png',
    'report.png',
    'reportManager.png',
    'save.png',
    'savedSearch.png',
    'savedSearchBig.png',
    'savedSearchSmall.png',
    'search.png',
    'searchBig.png',
    'settings.png',
    'timeCode.png',
    'timeManager.png',
    'topNavButton.png',
];
$hay = '';
foreach (['src', 'layouts/css', 'layouts/js', 'layouts/pages', 'layouts/partials', 'site', 'bootstrap', 'public', 'bin'] as $tree) {
    $dir = $root . '/' . $tree;
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'css', 'js', 'json', 'svg', 'html'], true)) {
            continue;
        }
        $chunk = file_get_contents($file->getPathname());
        if (is_string($chunk)) {
            $hay .= "\n" . $chunk;
        }
    }
}
$newImages = [];
foreach (glob($root . '/layouts/images/*') ?: [] as $image) {
    $base = basename($image);
    if (str_contains($hay, $base) || in_array($base, $imageAllowed, true)) {
        continue;
    }
    $newImages[] = $base;
}
$check(
    $newImages === [],
    'unreferenced layout images stayed within the scan'
    . ($newImages === [] ? '' : ': ' . implode(', ', $newImages))
);

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
