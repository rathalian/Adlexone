<?php
declare(strict_types=1);

/**
 * The record sheet binds its lookups and escapes a name or title the person cannot edit.
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

$source = file_get_contents(dirname(__DIR__) . '/src/Screen/ItemSheet.php');
$source = is_string($source) ? $source : '';

$check(!str_contains($source, "user_id ='"), 'session id is not quoted into SQL');
$check(!preg_match("/\\.\\s*\\\$row\\['user_name'\\]/", $source), 'a user name is not appended as raw HTML');
$check(!preg_match("/\\.\\s*\\\$itemFields\\['item_title'\\]/", $source), 'a record title is not appended as raw HTML');
$check(str_contains($source, 'user_id = ?'), 'a user lookup uses a bound parameter');
$check(str_contains($source, 'Html::e($text)'), 'read-only text is escaped');

exit($failed === 0 ? 0 : 1);
