<?php
declare(strict_types=1);

/**
 * A saved search that only has a stored statement loses that statement on upgrade.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inlay-saved-search-' . getmypid() . '.sqlite';
if (is_file($tmp)) {
    unlink($tmp);
}
define('DSN', 'sqlite:' . $tmp);

use Adlexone\Database\SchemaMigrator;
use Adlexone\support\Database;

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

Database::exec(
    'CREATE TABLE saved_searches (
        search_id INTEGER PRIMARY KEY,
        user TEXT NOT NULL,
        search_name TEXT NOT NULL,
        search_description TEXT,
        saved_search_sql TEXT,
        application TEXT,
        criteria_json TEXT NOT NULL DEFAULT \'{}\'
    )'
);
Database::exec('CREATE TABLE schema_migrations (version INTEGER NOT NULL PRIMARY KEY, applied_at INTEGER NOT NULL)');
Database::exec('INSERT INTO schema_migrations (version, applied_at) VALUES (13, 0)');
Database::exec(
    "INSERT INTO saved_searches (search_id, user, search_name, saved_search_sql, criteria_json) VALUES
        (1, '1', 'Old statement', 'SELECT item_id FROM items WHERE item_title = ''Reid''', '{\"version\":1,\"legacy_sql\":\"SELECT item_id FROM items\"}'),
        (2, '1', 'Filters', 'SELECT item_id FROM items', '{\"version\":1,\"filters\":[{\"field\":\"item_title\",\"op\":\"=\",\"value\":\"Walsh fence\",\"andor\":\"AND\"}]}')"
);

SchemaMigrator::migrate();

$columns = array_column(Database::rows('PRAGMA table_info(saved_searches)'), 'name');
check(!in_array('saved_search_sql', $columns, true), 'the stored statement column is dropped');

$old = json_decode((string) Database::row('SELECT criteria_json FROM saved_searches WHERE search_id = 1')['criteria_json'], true);
check(is_array($old) && !array_key_exists('legacy_sql', $old), 'a statement-only search no longer keeps the statement');
check(($old['filters'][0]['value'] ?? '') === '0', 'a statement-only search matches nothing');

$kept = json_decode((string) Database::row('SELECT criteria_json FROM saved_searches WHERE search_id = 2')['criteria_json'], true);
check(($kept['filters'][0]['value'] ?? '') === 'Walsh fence', 'a search with filters keeps them');

@unlink($tmp);
echo $failed === 0 ? "all checks passed\n" : "{$failed} checks failed\n";
exit($failed === 0 ? 0 : 1);
