<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Http\Request;
use Adlexone\support\Database;

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
};

$_GET = ['item_id' => '42', 'user_id' => "1' OR '1'='1", 'group_id' => '', 'controller' => 'items', 'nav_id' => '7abc'];
$_POST = ['item_type_id' => '10', 'custom_field_id' => '-1', 'item_title' => "O'Reilly", 'id' => '12345678901234567890'];
Request::normaliseNumbers();

$check($_GET['item_id'] === '42', 'a whole number in the query string is kept');
$check($_GET['user_id'] === '', 'an injected user id is cleared');
$check($_GET['nav_id'] === '', 'a number followed by letters is cleared');
$check($_GET['controller'] === 'items', 'a key that is not an identifier is left alone');
$check($_POST['item_type_id'] === '10', 'a whole number in the form is kept');
$check($_POST['custom_field_id'] === '', 'a negative number is cleared');
$check($_POST['item_title'] === "O'Reilly", 'free text is left alone');
$check($_POST['id'] === '', 'a number too long for an identifier is cleared');
$check(!array_key_exists('group_id', $_POST), 'a key that was not sent is not added');

$check(Database::escapeLike('50%_off\\') === '50\\%\\_off\\\\', 'LIKE wildcards and the escape character are escaped');
$check(Database::escape("it's") === "it''s", 'single quotes are doubled');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
