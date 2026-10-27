<?php
declare(strict_types=1);

namespace Adlexone\Platform;

/**
 * A one-time value the person setting up the site reads from the server.
 *
 * The setup page can be opened by anyone who can reach a new install. The token
 * is written beside the database, which is outside the document root, and the
 * page asks for it. The file is removed once the site has an administrator.
 */
final class SetupToken
{
    public static function path(): string
    {
        $paths = Runtime::paths();
        $dir = rtrim((string) ($paths['database_dir'] ?? ''), '/\\');
        if ($dir === '') {
            $sqlite = (string) ($paths['sqlite'] ?? '');
            $dir = $sqlite === '' ? '' : dirname($sqlite);
        }

        return $dir . DIRECTORY_SEPARATOR . 'setup-token.txt';
    }

    public static function issue(?string $path = null): string
    {
        $path ??= self::path();
        if (is_file($path)) {
            $existing = trim((string) file_get_contents($path));
            if ($existing !== '') {
                return $existing;
            }
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('The database folder ' . $dir . ' could not be created, so the setup token was not written.');
        }
        $token = bin2hex(random_bytes(16));
        if (file_put_contents($path, $token . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('The setup token could not be written to ' . $path . '.');
        }
        @chmod($path, 0600);

        return $token;
    }

    public static function check(string $posted, ?string $path = null): bool
    {
        $path ??= self::path();
        if (!is_file($path)) {
            return false;
        }
        $expected = trim((string) file_get_contents($path));

        return $expected !== '' && hash_equals($expected, trim($posted));
    }

    public static function clear(?string $path = null): void
    {
        $path ??= self::path();
        if (is_file($path)) {
            unlink($path);
        }
    }
}
