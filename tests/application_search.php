<?php
declare(strict_types=1);

/**
 * Checks the criteria behind the application Search tab: words, windows, and typed field filters.
 * Uses a temporary database so the live file stays untouched.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inlay-search-' . getmypid() . '.sqlite';
if (is_file($tmp)) {
    unlink($tmp);
}
define('DSN', 'sqlite:' . $tmp);
define('SET_DATE_FORMAT', 'd-m-Y, h:i A');

use Adlexone\Data\SavedSearch;
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

Database::exec('CREATE TABLE items (item_id INTEGER PRIMARY KEY, item_type_id INTEGER, item_title TEXT, create_date INTEGER, core_log_updated INTEGER, creator_security INTEGER, user_security INTEGER)');
Database::exec('CREATE TABLE item_field_values (item_id INTEGER, custom_field_id INTEGER, value TEXT, PRIMARY KEY (item_id, custom_field_id))');
Database::exec('CREATE TABLE custom_fields (custom_field_id INTEGER PRIMARY KEY, field_type TEXT)');
Database::exec('CREATE TABLE core_log (item_id INTEGER, log_item_sequence INTEGER, log_text TEXT)');
Database::exec('CREATE TABLE item_groups (item_id INTEGER, group_id INTEGER)');

$now = time();
$day = 86400;
Database::exec("INSERT INTO custom_fields VALUES (1, 'menu'), (2, 'number'), (3, 'date'), (4, 'checkBox'), (5, 'password'), (6, 'textBox')");
foreach ([
    [1, 'Reid water damage', $now - 2 * $day, $now - 1 * $day, 1],
    [2, 'Nguyen storm damage', $now - 40 * $day, $now - 35 * $day, 1],
    [3, '100% cover letter', $now - 3 * $day, $now - 3 * $day, 0],
    [4, 'Walsh fence', $now - 200 * $day, $now - 190 * $day, 1],
] as [$id, $title, $created, $updated, $owner]) {
    Database::exec("INSERT INTO items VALUES ({$id}, 1, '" . Database::escape($title) . "', {$created}, {$updated}, 1, {$owner})");
}
Database::exec("INSERT INTO item_field_values VALUES
    (1, 1, 'Notified'), (2, 1, 'Closed'), (3, 1, 'Notified'),
    (1, 2, '18000'), (2, 2, '950'), (4, 2, '120000'),
    (1, 3, '28-09-2026'), (2, 3, '2026-05-02'), (4, 3, '15/01/2026'),
    (1, 4, '1'), (2, 4, '0'),
    (1, 5, 'secret-token'),
    (1, 6, 'Braddon unit 4')");
Database::exec("INSERT INTO core_log VALUES (1, 1, 'Rang the insurer on Thursday'), (4, 1, 'Follow up the quote')");

/** @param list<array{field: string, op: string, value: string}> $filters */
function found(array $filters): array
{
    $criteria = ['filters' => array_map(static fn (array $f): array => $f + ['andor' => 'AND'], $filters)];
    $query = SavedSearch::compile($criteria, 'item_id');
    $ids = array_map(static fn (array $row): int => (int) $row['item_id'], Database::rows($query->sql . ' ORDER BY item_id', $query->params));

    return $ids;
}

function same(array $actual, array $expected, string $message): void
{
    check($actual === $expected, $message . ' (' . implode(',', $actual) . ')');
}

same(SavedSearch::terms('water "storm damage" 7'), ['water', 'storm damage', '7'], 'terms split on spaces and keep quoted phrases');

same(found([['field' => 'q', 'op' => 'CONTAINS', 'value' => 'damage']]), [1, 2], 'a word matches names');
same(found([['field' => 'q', 'op' => 'CONTAINS', 'value' => 'damage reid']]), [1], 'every word must match');
same(found([['field' => 'q', 'op' => 'CONTAINS', 'value' => 'braddon']]), [1], 'a word matches field values');
same(found([['field' => 'q', 'op' => 'CONTAINS', 'value' => 'thursday']]), [1], 'a word matches notes');
same(found([['field' => 'q', 'op' => 'CONTAINS', 'value' => '4']]), [1, 4], 'a number matches the record number and values containing it');
same(found([['field' => 'q', 'op' => 'CONTAINS', 'value' => '100%']]), [3], 'a percent sign is literal');
same(found([['field' => 'q', 'op' => 'CONTAINS', 'value' => 'secret']]), [], 'password fields are not searched');
same(found([['field' => 'q', 'op' => 'CONTAINS', 'value' => '   ']]), [1, 2, 3, 4], 'blank words add no filter');

same(found([['field' => 'create_date_days', 'op' => '>=', 'value' => '7']]), [1, 3], 'opened in the last 7 days');
same(found([['field' => 'core_log_updated_days', 'op' => '>=', 'value' => '90']]), [1, 2, 3], 'updated in the last 90 days');
Database::exec("INSERT INTO items VALUES (5, 1, 'Campbell never touched', " . ($now - 2 * $day) . ', NULL, 1, 1)');
same(found([['field' => 'core_log_updated_days', 'op' => '>=', 'value' => '7']]), [1, 3, 5], 'a record never changed counts from the day it was opened');
Database::exec('DELETE FROM items WHERE item_id = 5');
same(found([['field' => 'create_date_days', 'op' => '>=', 'value' => '0']]), [1, 2, 3, 4], 'zero days adds no filter');

same(found([['field' => 'custom_field_1', 'op' => '=', 'value' => 'Notified']]), [1, 3], 'menu is');
same(found([['field' => 'custom_field_1', 'op' => 'NOT', 'value' => 'Notified']]), [2, 4], 'menu is not also keeps records with no value');
same(found([['field' => 'custom_field_6', 'op' => 'CONTAINS', 'value' => 'unit']]), [1], 'text contains');
same(found([['field' => 'custom_field_2', 'op' => '>=', 'value' => '1000']]), [1, 4], 'number at least compares as a number');
same(found([['field' => 'custom_field_2', 'op' => '<=', 'value' => '1000']]), [2], 'number at most compares as a number');
same(found([['field' => 'custom_field_3', 'op' => 'DATE>=', 'value' => '2026-06-01']]), [1], 'date on or after, stored day first');
same(found([['field' => 'custom_field_3', 'op' => 'DATE<=', 'value' => '2026-06-01']]), [2, 4], 'date on or before, both stored forms');
same(found([['field' => 'custom_field_3', 'op' => 'DATE=', 'value' => '15-01-2026']]), [4], 'date on, with slashes stored');
same(found([['field' => 'custom_field_3', 'op' => 'DATE>=', 'value' => 'not a date']]), [], 'an unreadable date matches nothing');
same(found([['field' => 'custom_field_4', 'op' => 'TICKED', 'value' => '']]), [1], 'checkbox ticked');
same(found([['field' => 'custom_field_4', 'op' => 'UNTICKED', 'value' => '']]), [2, 3, 4], 'checkbox not ticked includes records with no value');

same(found([
    ['field' => 'q', 'op' => 'CONTAINS', 'value' => 'damage'],
    ['field' => 'custom_field_1', 'op' => '=', 'value' => 'Closed'],
]), [2], 'words and a field filter combine');
same(found([['field' => 'user_security', 'op' => '=', 'value' => '0']]), [3], 'not assigned');

same(
    array_map('intval', array_column((static function (): array {
        $query = SavedSearch::resolve([
            'criteria_json' => SavedSearch::encode(['version' => 1, 'filters' => [
                ['field' => 'custom_field_1', 'op' => 'LIKE', 'value' => 'otif', 'andor' => 'AND'],
            ]]),
            'saved_search_sql' => 'SELECT item_id FROM items',
        ]);

        return Database::rows($query->sql . ' ORDER BY item_id', $query->params);
    })(), 'item_id')),
    [1, 3],
    'the older contains operator still works'
);

$stored = SavedSearch::resolve([
    'criteria_json' => SavedSearch::encode(['version' => 1, 'legacy_sql' => 'SELECT item_id FROM items WHERE item_title = \'Reid water damage\'']),
    'saved_search_sql' => 'SELECT item_id FROM items',
]);
same(
    array_map('intval', array_column(Database::rows($stored->sql, $stored->params), 'item_id')),
    [],
    'a stored statement is not run'
);
check(!str_contains($stored->sql, 'Reid'), 'a stored statement is not copied into the query');
$kept = SavedSearch::withoutStoredSql([
    'version' => 1,
    'filters' => [['field' => 'item_title', 'op' => '=', 'value' => 'Walsh fence', 'andor' => 'AND']],
    'legacy_sql' => 'SELECT item_id FROM items',
], 'SELECT item_id FROM items');
check(!array_key_exists('legacy_sql', $kept), 'a stored statement is dropped when filters exist');
check($kept['filters'][0]['value'] === 'Walsh fence', 'filters are kept');

@unlink($tmp);
echo $failed === 0 ? "all checks passed\n" : "{$failed} checks failed\n";
exit($failed === 0 ? 0 : 1);
