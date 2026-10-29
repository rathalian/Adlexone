<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Http\PublicBaseUrl;

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

if (($argv[1] ?? '') === 'trusted') {
    define('SET_TRUSTED_PROXIES', '203.0.113.9, 10.0.0.0/8');
    $https = ['HTTP_X_FORWARDED_PROTO' => 'https', 'REMOTE_ADDR' => '203.0.113.9', 'HTTPS' => 'off'];
    $http = ['HTTP_X_FORWARDED_PROTO' => 'http', 'REMOTE_ADDR' => '10.1.2.3', 'HTTPS' => 'off'];
    $spoof = ['HTTP_X_FORWARDED_PROTO' => 'https', 'REMOTE_ADDR' => '198.51.100.4', 'HTTPS' => 'off'];
    check(PublicBaseUrl::https($https) === true, 'a trusted proxy reporting https marks the cookie Secure');
    check(PublicBaseUrl::https($http) === false, 'a trusted proxy reporting http leaves the cookie unset for Secure');
    check(PublicBaseUrl::https($spoof) === false, 'an unlisted address cannot set the cookie Secure with a forwarded protocol');
    exit($failed > 0 ? 1 : 0);
}

if (($argv[1] ?? '') === 'public-https') {
    define('SET_PUBLIC_BASE_URL', 'https://health.example.com');
    $plain = ['HTTPS' => 'off', 'REMOTE_ADDR' => '198.51.100.4', 'SERVER_PORT' => '80'];
    check(PublicBaseUrl::https($plain) === true, 'an https public address marks the cookie Secure');
    exit($failed > 0 ? 1 : 0);
}

check(PublicBaseUrl::https(['HTTPS' => 'on', 'REMOTE_ADDR' => '198.51.100.4']) === true, 'HTTPS seen by PHP marks the cookie Secure');
check(PublicBaseUrl::https(['HTTPS' => 'off', 'REMOTE_ADDR' => '198.51.100.4', 'SERVER_PORT' => '80']) === false, 'plain HTTP leaves the cookie unset for Secure');
check(PublicBaseUrl::https(['SERVER_PORT' => '443', 'REMOTE_ADDR' => '198.51.100.4']) === true, 'port 443 marks the cookie Secure');
check(PublicBaseUrl::https([
    'HTTP_X_FORWARDED_PROTO' => 'https',
    'REMOTE_ADDR' => '198.51.100.4',
    'HTTPS' => 'off',
]) === false, 'a forwarded protocol from an untrusted address is ignored');
check(PublicBaseUrl::https([
    'HTTP_X_FORWARDED_PROTO' => 'https',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTPS' => 'off',
]) === true, 'loopback reporting https marks the cookie Secure');
check(PublicBaseUrl::https([
    'HTTP_X_FORWARDED_PROTO' => 'http',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTPS' => 'off',
]) === false, 'loopback reporting http leaves the cookie unset for Secure');
check(PublicBaseUrl::https([
    'HTTP_X_FORWARDED_PROTO' => 'https, http',
    'REMOTE_ADDR' => '::1',
    'HTTPS' => 'off',
]) === true, 'the first forwarded protocol from loopback is used');

$php = PHP_BINARY;
$script = escapeshellarg(__FILE__);
passthru($php . ' ' . $script . ' trusted', $trusted);
passthru($php . ' ' . $script . ' public-https', $public);
if ($trusted !== 0 || $public !== 0) {
    $failed++;
}

if ($failed > 0) {
    fwrite(STDERR, $failed . " checks failed\n");
    exit(1);
}
echo "all checks passed\n";
