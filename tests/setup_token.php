<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Http\Router;
use Adlexone\Platform\SetupToken;

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

$dir = sys_get_temp_dir() . '/adlexone-setup-token-' . getmypid();
@mkdir($dir, 0700, true);
$path = $dir . '/setup-token.txt';

$token = SetupToken::issue($path);
check(strlen($token) === 32 && ctype_xdigit($token), 'a setup token is 32 hex characters');
check(SetupToken::issue($path) === $token, 'opening the page again keeps the same token');
check(SetupToken::check('wrong', $path) === false, 'a wrong token is refused');
check(SetupToken::check($token, $path) === true, 'the file value is accepted');
check(is_file($path), 'the token file is on disk');
SetupToken::clear($path);
check(!is_file($path), 'the token file is removed after setup');
check(SetupToken::check($token, $path) === false, 'a removed token cannot be reused');

foreach ([
    'delete' => true,
    'move' => true,
    'delete_item' => true,
    'move_nav' => true,
    'toggle_rule' => true,
    'duplicate_rule' => true,
    'try_rule' => true,
    'run_rule' => true,
    'create' => true,
    'import' => true,
    'update_owner' => true,
    'show_item' => false,
    'edit' => false,
    'export' => false,
] as $option => $writes) {
    check(Router::writesData($option) === $writes, $option . ($writes ? ' changes data' : ' does not change data'));
}

$script = (string) file_get_contents(dirname(__DIR__) . '/layouts/js/app.js');
check(str_contains($script, 'toggle_rule|duplicate_rule|try_rule|run_rule'), 'the page posts the rule actions that are still links');

@rmdir($dir);

if ($failed > 0) {
    fwrite(STDERR, $failed . " checks failed\n");
    exit(1);
}
echo "all checks passed\n";
