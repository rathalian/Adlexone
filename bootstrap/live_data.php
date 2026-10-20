<?php

/**
 * Directory that holds the live config, attachments, and SQLite database.
 *
 * Those files stay in the main checkout. A linked worktree — a branch checked
 * out beside main so it can be tested — reads and writes the same copies, so
 * the test keeps the data already on main.
 */
function adlexone_live_data_directory(string $checkoutDir): string
{
    $checkoutDir = rtrim($checkoutDir, '/\\');
    $gitPointer = $checkoutDir . DIRECTORY_SEPARATOR . '.git';
    if (!is_file($gitPointer)) {
        return $checkoutDir;
    }

    $contents = (string) file_get_contents($gitPointer);
    if (preg_match('/^gitdir:\s*(.+)$/mi', $contents, $match) !== 1) {
        return $checkoutDir;
    }

    $gitDir = adlexone_resolve_git_path($checkoutDir, trim($match[1]));
    $main = adlexone_main_checkout_from_gitdir($gitDir);
    if ($main === null) {
        return $checkoutDir;
    }

    $config = $main . DIRECTORY_SEPARATOR . 'config';
    $databaseDir = $main . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database';
    if (!is_dir($config) || !is_dir($databaseDir)) {
        return $checkoutDir;
    }

    return $main;
}

function adlexone_resolve_git_path(string $checkoutDir, string $gitDir): string
{
    $gitDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $gitDir);
    $absolute = preg_match('/^(?:[A-Za-z]:[\\\\\\/]|[\\\\\\/])/', $gitDir) === 1;
    if (!$absolute) {
        $gitDir = $checkoutDir . DIRECTORY_SEPARATOR . $gitDir;
    }
    $resolved = realpath($gitDir);

    return $resolved !== false ? $resolved : $gitDir;
}

function adlexone_main_checkout_from_gitdir(string $gitDir): ?string
{
    $commonFile = $gitDir . DIRECTORY_SEPARATOR . 'commondir';
    if (is_file($commonFile)) {
        $relative = trim((string) file_get_contents($commonFile));
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
        $common = realpath($gitDir . DIRECTORY_SEPARATOR . $relative);
        if ($common !== false && strcasecmp(basename($common), '.git') === 0) {
            $main = realpath(dirname($common));
            if ($main !== false) {
                return $main;
            }
        }
    }

    $main = realpath($gitDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
    if ($main === false) {
        return null;
    }

    return $main;
}
