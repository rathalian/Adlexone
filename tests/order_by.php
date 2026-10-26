<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

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

check(Database::orderBy('item_id') === 'item_id', 'a column name is kept');
check(Database::orderBy('item_title DESC') === 'item_title DESC', 'a descending column is kept');
check(Database::orderBy('  create_date   asc ') === 'create_date ASC', 'spacing and case are normalised');
check(Database::orderBy('custom_field_12') === 'custom_field_12', 'a numbered field column is kept');
check(Database::orderBy('') === '', 'an empty sort is dropped');
check(Database::orderBy('item_id; DROP TABLE items') === '', 'a second statement is dropped');
check(Database::orderBy('item_id DESC, (SELECT 1)') === '', 'a second expression is dropped');
check(Database::orderBy('(SELECT CASE WHEN 1=1 THEN 1 END)') === '', 'a function is dropped');
check(Database::orderBy('item_id DESC --') === '', 'a comment is dropped');

$search = (string) file_get_contents(dirname(__DIR__) . '/src/Screen/SearchScreen.php');
check(str_contains($search, "SET_ALLOW_RAW_SQL") && str_contains($search, 'Direct SQL search is turned off.'), 'direct SQL search stays off unless the site allows it');

if ($failed > 0) {
    fwrite(STDERR, $failed . " checks failed\n");
    exit(1);
}
echo "all checks passed\n";
