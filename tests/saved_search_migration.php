<?php
declare(strict_types=1);

/**
 * A new database has no stored search statement and no retired application columns.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inlay-saved-search-' . getmypid() . '.sqlite';
if (is_file($tmp)) {
    unlink($tmp);
}
define('DSN', 'sqlite:' . $tmp);

use Adlexone\Database\Database;

$failed = 0;
function check(bool $ok, string $message): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

Database::ensure();

$columns = array_column(Database::rows('PRAGMA table_info(saved_searches)'), 'name');
check(!in_array('saved_search_sql', $columns, true), 'a saved search has no stored statement column');
check(in_array('criteria_json', $columns, true), 'a saved search stores criteria');

$appColumns = array_column(Database::rows('PRAGMA table_info(applications)'), 'name');
check(!in_array('legacy_controller', $appColumns, true), 'an application has no controller column');
check(!in_array('legacy_key', $appColumns, true), 'an application has no old key column');
check((int) Database::row('SELECT MAX(version) AS v FROM schema_migrations')['v'] === Database::SCHEMA_VERSION, 'a new database is the current version');

$library = (string) file_get_contents(dirname(__DIR__) . '/src/Screen/SearchLibrary.php');
$packs = (string) file_get_contents(dirname(__DIR__) . '/src/Application/AppFunctions.php');
$nav = (string) file_get_contents(dirname(__DIR__) . '/src/Http/RenderNavigation.php');
check(!str_contains($library, 'oneorzero.com'), 'a saved-search feed uses the site address');
check(!str_contains($packs, 'application_functions'), 'screen packs are read from site/apps only');
check(!str_contains($nav, 'knowledgebase_settings'), 'the menu no longer has a knowledge-base branch');

@unlink($tmp);
echo $failed === 0 ? "all checks passed\n" : "{$failed} checks failed\n";
exit($failed === 0 ? 0 : 1);
