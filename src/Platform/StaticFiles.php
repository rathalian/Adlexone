<?php
declare(strict_types=1);

namespace Adlexone\Platform;

/**
 * Serves CSS, JS, fonts, and images that live outside the public document root.
 */
final class StaticFiles
{
    /** @var array<string, string> */
    private const TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public static function serve(string $install, string $uriPath): bool
    {
        $path = rawurldecode($uriPath);
        $path = str_replace('\\', '/', $path);
        $cut = strpos($path, '?');
        if ($cut !== false) {
            $path = substr($path, 0, $cut);
        }
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) {
            return false;
        }
        if (preg_match('#^(layouts|site/themes)/#', $path) !== 1) {
            return false;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$ext])) {
            return false;
        }
        $installReal = realpath($install);
        if ($installReal === false) {
            return false;
        }
        $candidate = $installReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            return false;
        }
        $prefix = $installReal . DIRECTORY_SEPARATOR;
        if (!str_starts_with($real, $prefix)) {
            return false;
        }
        $relative = str_replace('\\', '/', substr($real, strlen($prefix)));
        if (preg_match('#^(layouts|site/themes)/#', $relative) !== 1) {
            return false;
        }
        header('Content-Type: ' . self::TYPES[$ext]);
        header('Content-Length: ' . (string) filesize($real));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=300');
        readfile($real);

        return true;
    }
}
