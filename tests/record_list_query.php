<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Database\RecordListQuery;

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

check(RecordListQuery::ids([['item_id' => 4], ['item_id' => '4'], 9, '0', 'nope']) === [4, 9], 'ids come from rows or scalars and drop blanks');
[$where, $params] = RecordListQuery::idWhere([4, 9]);
check($where === 'item_id IN (?, ?)' && $params === [4, 9], 'an id list is a bound IN clause');
[$none, $noneParams] = RecordListQuery::idWhere([]);
check($none === 'item_id = ?' && $noneParams === [0], 'an empty id list matches nothing');
check(RecordListQuery::order('item_title DESC') === 'item_title DESC', 'a descending column is kept');
check(RecordListQuery::order('custom_field_12; DROP TABLE items') === 'item_id DESC', 'a sort that is not a column falls back');
check(RecordListQuery::order('item_id; DROP TABLE items', '') === '', 'an empty fallback stays empty when the sort is rejected');
check(RecordListQuery::column('custom_field_12') === 'custom_field_12', 'a custom field column is kept');
check(RecordListQuery::column('custom_field_12 DESC') === '', 'a direction is not a column name');
check(RecordListQuery::page('3') === 3, 'a page number is kept');
check(RecordListQuery::page('0') === 1, 'page zero becomes the first page');
check(RecordListQuery::page(['2']) === 1, 'a page that is not a single value becomes the first page');

$item = (string) file_get_contents(dirname(__DIR__) . '/src/Screen/ItemScreen.php');
$search = (string) file_get_contents(dirname(__DIR__) . '/src/Screen/SearchScreen.php');
check(!str_contains($item, "item_id = '\$a'"), 'the item list does not splice ids into the query text');
check(!str_contains($search, 'ORDER BY $orderSQL'), 'the search list does not splice the sort into the query text');
check(!str_contains($search, 'custom_field_id = "'), 'a custom field id is not spliced into the query text');
check(str_contains($item, 'RecordListQuery::page'), 'the item list reads the page as a number');
check(str_contains($search, 'RecordListQuery::order'), 'the search list checks the sort');

if ($failed > 0) {
    fwrite(STDERR, $failed . " checks failed\n");
    exit(1);
}
echo "all checks passed\n";
