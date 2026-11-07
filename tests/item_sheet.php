<?php
declare(strict_types=1);

/**
 * The record sheet binds its lookups and escapes text the person cannot edit.
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

$directory = dirname(__DIR__) . '/src/Screen/';
$names = ['ItemSheet.php', 'ItemSheetFields.php', 'ItemSheetTools.php', 'ItemSheetSide.php'];
$source = '';
foreach ($names as $name) {
    $chunk = file_get_contents($directory . $name);
    $source .= is_string($chunk) ? $chunk : '';
}
$fields = file_get_contents($directory . 'ItemSheetFields.php');
$fields = is_string($fields) ? $fields : '';

$check(!str_contains($source, "user_id ='"), 'session id is not quoted into SQL');
$check(!preg_match("/\\.\\s*\\\$row\\['user_name'\\]/", $source), 'a user name is not appended as raw HTML');
$check(!preg_match("/\\.\\s*\\\$itemFields\\['item_title'\\]/", $source), 'a record title is not appended as raw HTML');
$check(str_contains($source, 'user_id = ?'), 'a user lookup uses a bound parameter');
$check(str_contains($source, 'Html::e($text)'), 'read-only text is escaped');
$check(str_contains($fields, 'nl2br(Html::e($field->value), false)'), 'a read-only note is escaped before line breaks');
$check(str_contains($fields, 'Html::e(FieldValidation::display('), 'a read-only field value is escaped');
$check(!str_contains($fields, 'str_replace("\\n", "<br />"'), 'a read-only field is not printed with raw breaks');
$check(str_contains($fields, 'public readonly int $id'), 'a sheet field keeps its id readonly');
$check(str_contains($fields, 'return match ($field->type)'), 'a field is drawn with match');
$check(!preg_match('/(?<![\w])array\s*\(/', $source), 'short arrays replace array()');

exit($failed === 0 ? 0 : 1);
