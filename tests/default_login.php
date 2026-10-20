<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Platform\DefaultAccount;

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
};

$check(DefaultAccount::USERNAME === 'admin', 'default username is admin');
$check(DefaultAccount::PASSWORD === 'admin', 'default password is admin');
$check(DefaultAccount::postedUsername('') === 'admin', 'empty posted username becomes admin');
$check(DefaultAccount::postedPassword('') === 'admin', 'empty posted password becomes admin');

try {
    DefaultAccount::assertPassword('admin');
    $check(true, 'password admin is accepted');
} catch (Throwable $e) {
    $check(false, 'password admin is accepted');
}

try {
    DefaultAccount::assertPassword('short');
    $check(false, 'other short passwords are rejected');
} catch (Throwable $e) {
    $check(str_contains($e->getMessage(), '8 characters'), 'other short passwords are rejected');
}

$login = (string) file_get_contents(dirname(__DIR__) . '/layouts/pages/login.php');
$check(str_contains($login, 'DefaultAccount::USERNAME'), 'application login uses the default username');
$check(str_contains($login, 'DefaultAccount::PASSWORD'), 'application login uses the default password');

if ($failed > 0) {
    fwrite(STDERR, $failed . " checks failed\n");
    exit(1);
}
echo "all checks passed\n";
