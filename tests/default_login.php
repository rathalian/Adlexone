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
$check(str_contains($login, 'DefaultAccount::stillInUse()'), 'application login pre-fills only while the default account is unchanged');
$check(str_contains($login, '$defaultsInUse ? Html::e(\\Adlexone\\Platform\\DefaultAccount::USERNAME)'), 'application login pre-fills the default username conditionally');
$check(str_contains($login, '$defaultsInUse ? Html::e(\\Adlexone\\Platform\\DefaultAccount::PASSWORD)'), 'application login pre-fills the default password conditionally');
$check(!str_contains($login, 'Business tools to get your work sorted.'), 'sign-in does not carry a slogan');
$applicationsPosition = strpos($login, '>Applications</span>');
$recordsPosition = strpos($login, '>Records</span>');
$workPosition = strpos($login, '>Work</span>');
$check(
    $applicationsPosition !== false
        && $recordsPosition !== false
        && $workPosition !== false
        && $applicationsPosition < $recordsPosition
        && $recordsPosition < $workPosition,
    'login board orders applications, records, and work'
);
$check(!str_contains($login, '>Information</span>'), 'login board does not use the broader information label');
$check(!str_contains($login, 'Use your username and password.'), 'username sign-in does not repeat the field labels');

$baseCss = (string) file_get_contents(dirname(__DIR__) . '/layouts/css/base.css');
$upliftCss = (string) file_get_contents(dirname(__DIR__) . '/layouts/css/uplift.css');
$check(str_contains($baseCss, '.login-atmosphere__brand .brand-descriptor{')
    && str_contains($baseCss, 'white-space:normal;'), 'login descriptor can wrap without being clipped');
$check(str_contains($baseCss, 'color:var(--logo-piece-ink, var(--ink));'), 'light Records tile follows its theme text colour');
$check(str_contains($upliftCss, '--logo-piece-ink:#16243a;'), 'dark Records tile has contrasting text');

unset($_SERVER['REMOTE_ADDR']);
foreach (['localhost:8000' => true, '127.0.0.1' => true, '192.168.1.20' => true, 'inlay.local' => true, 'health.example.com' => false, '203.0.113.9' => false] as $host => $expected) {
    $_SERVER['HTTP_HOST'] = $host;
    $check(DefaultAccount::allowed() === $expected, 'default account on ' . $host . ' is ' . ($expected ? 'allowed' : 'refused'));
}
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$_SERVER['HTTP_HOST'] = 'localhost';
$check(DefaultAccount::allowed() === false, 'a public address cannot enable the default password by naming localhost');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'health.example.com';
$check(DefaultAccount::allowed() === false, 'a local proxy serving a public hostname does not keep the default password');
unset($_SERVER['REMOTE_ADDR']);
$_SERVER['HTTP_HOST'] = 'health.example.com';
$check(DefaultAccount::postedPassword('') === '', 'a public host gets no default password');
foreach (['admin', 'short-pass'] as $weak) {
    try {
        DefaultAccount::assertPassword($weak);
        $check(false, 'public host refuses ' . $weak);
    } catch (RuntimeException) {
        $check(true, 'public host refuses ' . $weak);
    }
}
DefaultAccount::assertPassword('a-long-enough-pass');
$check(true, 'public host accepts a 12 character password');
unset($_SERVER['HTTP_HOST']);

$check(\Adlexone\Application\RecordQuery::displayName(['user_name' => 'admin', 'first_name' => 'Reid', 'last_name' => '']) === 'Reid', 'a person is shown by first name before username');
$check(\Adlexone\Application\RecordQuery::displayName(['user_name' => 'admin', 'first_name' => 'Reid', 'last_name' => 'Walsh']) === 'Reid Walsh', 'a person is shown by full name');
$check(\Adlexone\Application\RecordQuery::displayName(['user_name' => 'admin']) === 'admin', 'username is the fallback');

if ($failed > 0) {
    fwrite(STDERR, $failed . " checks failed\n");
    exit(1);
}
echo "all checks passed\n";
