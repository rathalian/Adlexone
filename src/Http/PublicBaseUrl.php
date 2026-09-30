<?php
declare(strict_types=1);

namespace Adlexone\Http;

/**
 * Canonical public origin for redirects, OAuth callbacks, and absolute links.
 *
 * Order:
 * 1. SET_PUBLIC_BASE_URL when set (preferred for production / OAuth)
 * 2. Else scheme + host from this request, with Host allowlisting and
 *    X-Forwarded-* only when REMOTE_ADDR is in SET_TRUSTED_PROXIES
 *
 * Bind addresses (0.0.0.0 / ::) are never emitted.
 */
final class PublicBaseUrl
{
    /**
     * @param array<string, mixed> $server
     * @return array{base: string, script: string}
     */
    public static function resolve(array $server): array
    {
        $scriptName = self::scriptName($server);
        $pathPrefix = self::pathPrefix($scriptName);
        $origin = self::configuredOrigin() ?? self::detectedOrigin($server);

        $base = $origin . $pathPrefix;
        $script = $origin . $scriptName;

        return [
            'base' => $base,
            'script' => $script,
        ];
    }

    /**
     * @param array<string, mixed>|null $server
     */
    public static function defineConstants(?array $server = null): void
    {
        $resolved = self::resolve($server ?? $_SERVER);
        if (!defined('BASE_URL')) {
            define('BASE_URL', $resolved['base']);
        }
        if (!defined('FULL_SCRIPT_PATH')) {
            define('FULL_SCRIPT_PATH', $resolved['script']);
        }
    }

    private static function configuredOrigin(): ?string
    {
        if (!defined('SET_PUBLIC_BASE_URL')) {
            return null;
        }
        $raw = trim((string) constant('SET_PUBLIC_BASE_URL'));
        if ($raw === '') {
            return null;
        }

        $origin = self::normalizeOrigin($raw);
        if ($origin === null) {
            return null;
        }

        return $origin;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function detectedOrigin(array $server): string
    {
        $trustForwarded = self::trustForwardedHeaders($server);

        $scheme = self::scheme($server, $trustForwarded);
        $hostPort = self::hostPort($server, $trustForwarded);

        return $scheme . '://' . $hostPort;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function trustForwardedHeaders(array $server): bool
    {
        $proxies = self::trustedProxies();
        if ($proxies === []) {
            return false;
        }
        $remote = trim((string) ($server['REMOTE_ADDR'] ?? ''));
        if ($remote === '') {
            return false;
        }

        return self::ipMatchesList($remote, $proxies);
    }

    /**
     * @return list<string>
     */
    private static function trustedProxies(): array
    {
        if (!defined('SET_TRUSTED_PROXIES')) {
            return [];
        }
        $raw = trim((string) constant('SET_TRUSTED_PROXIES'));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function allowedHosts(): array
    {
        if (!defined('SET_ALLOWED_HOSTS')) {
            return [];
        }
        $raw = trim((string) constant('SET_ALLOWED_HOSTS'));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = strtolower(trim($part));
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function scheme(array $server, bool $trustForwarded): string
    {
        if ($trustForwarded) {
            $forwarded = strtolower(trim((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '')));
            if ($forwarded !== '') {
                $forwarded = explode(',', $forwarded)[0];
                $forwarded = strtolower(trim($forwarded));
                if ($forwarded === 'https' || $forwarded === 'http') {
                    return $forwarded;
                }
            }
        }

        $https = $server['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string) $https) !== 'off') {
            return 'https';
        }
        $port = (string) ($server['SERVER_PORT'] ?? '');
        if ($port === '443') {
            return 'https';
        }

        return 'http';
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function hostPort(array $server, bool $trustForwarded): string
    {
        $candidate = '';
        if ($trustForwarded) {
            $forwardedHost = trim((string) ($server['HTTP_X_FORWARDED_HOST'] ?? ''));
            if ($forwardedHost !== '') {
                $candidate = trim(explode(',', $forwardedHost)[0]);
            }
        }
        if ($candidate === '') {
            $candidate = trim((string) ($server['HTTP_HOST'] ?? ''));
        }
        if ($candidate === '') {
            $name = self::replaceBindAddress((string) ($server['SERVER_NAME'] ?? ''));
            $port = (string) ($server['SERVER_PORT'] ?? '');
            $candidate = $name;
            if ($port !== '' && $port !== '80' && $port !== '443' && !str_contains($name, ':')) {
                $candidate .= ':' . $port;
            }
        }

        $normalized = self::normalizeHostPort($candidate);
        if ($normalized === null) {
            $normalized = '127.0.0.1';
            $port = (string) ($server['SERVER_PORT'] ?? '');
            if ($port !== '' && $port !== '80' && $port !== '443') {
                $normalized .= ':' . $port;
            }
        }

        $allowed = self::allowedHosts();
        if ($allowed !== []) {
            $hostOnly = strtolower(self::hostOnly($normalized));
            if (!in_array($hostOnly, $allowed, true)) {
                // Fall back to the first allowlisted host rather than reflecting a spoof.
                $fallback = $allowed[0];
                $port = self::portSuffix($normalized);
                $normalized = $fallback . $port;
            }
        }

        return $normalized;
    }

    private static function normalizeOrigin(string $raw): ?string
    {
        $raw = trim($raw);
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . $raw;
        }
        $parts = parse_url($raw);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        $host = self::replaceBindAddress((string) $parts['host']);
        $hostPort = self::normalizeHostPort(
            $host . (isset($parts['port']) ? ':' . $parts['port'] : '')
        );
        if ($hostPort === null) {
            return null;
        }

        return $scheme . '://' . $hostPort;
    }

    private static function normalizeHostPort(string $hostPort): ?string
    {
        $hostPort = trim($hostPort);
        if ($hostPort === '') {
            return null;
        }
        // Strip credentials if somehow present.
        if (str_contains($hostPort, '@')) {
            $hostPort = substr($hostPort, (int) strrpos($hostPort, '@') + 1);
        }

        $host = $hostPort;
        $port = '';
        if (str_starts_with($hostPort, '[')) {
            if (!preg_match('/^(\[[^\]]+\])(?::(\d+))?$/', $hostPort, $m)) {
                return null;
            }
            $host = $m[1];
            $port = $m[2] ?? '';
        } elseif (preg_match('/^(.+):(\d+)$/', $hostPort, $m) === 1 && substr_count($hostPort, ':') === 1) {
            $host = $m[1];
            $port = $m[2];
        }

        $host = self::replaceBindAddress($host);
        if ($host === '') {
            return null;
        }

        $unbracketed = $host;
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $unbracketed = substr($host, 1, -1);
        }

        $validIp = filter_var($unbracketed, FILTER_VALIDATE_IP) !== false;
        $validName = preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/i', $unbracketed) === 1;
        if (!$validIp && !$validName) {
            return null;
        }
        if (self::isBindOnlyAddress($unbracketed)) {
            return null;
        }
        if ($port !== '' && (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535)) {
            return null;
        }

        return $port === '' ? $host : $host . ':' . $port;
    }

    private static function replaceBindAddress(string $host): string
    {
        $host = trim($host);
        $plain = $host;
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $plain = substr($host, 1, -1);
        }
        if (self::isBindOnlyAddress($plain)) {
            return '127.0.0.1';
        }

        return $host;
    }

    private static function isBindOnlyAddress(string $host): bool
    {
        return $host === '0.0.0.0' || $host === '::' || $host === '0:0:0:0:0:0:0:0';
    }

    private static function hostOnly(string $hostPort): string
    {
        if (str_starts_with($hostPort, '[')) {
            if (preg_match('/^\[([^\]]+)\]/', $hostPort, $m) === 1) {
                return $m[1];
            }
        }
        if (preg_match('/^(.+):(\d+)$/', $hostPort, $m) === 1 && substr_count($hostPort, ':') === 1) {
            return $m[1];
        }

        return $hostPort;
    }

    private static function portSuffix(string $hostPort): string
    {
        if (str_starts_with($hostPort, '[')) {
            if (preg_match('/^\[[^\]]+\]:(\d+)$/', $hostPort, $m) === 1) {
                return ':' . $m[1];
            }

            return '';
        }
        if (preg_match('/^(.+):(\d+)$/', $hostPort, $m) === 1 && substr_count($hostPort, ':') === 1) {
            return ':' . $m[2];
        }

        return '';
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function scriptName(array $server): string
    {
        $script = (string) ($server['SCRIPT_NAME'] ?? $server['PHP_SELF'] ?? '/index.php');
        $script = str_replace('\\', '/', $script);
        if ($script === '' || !str_starts_with($script, '/')) {
            $script = '/' . ltrim($script, '/');
        }

        return $script;
    }

    private static function pathPrefix(string $scriptName): string
    {
        if (str_ends_with($scriptName, '/index.php')) {
            return substr($scriptName, 0, -strlen('/index.php')) ?: '';
        }
        $dir = str_replace('\\', '/', dirname($scriptName));

        return $dir === '/' ? '' : rtrim($dir, '/');
    }

    /**
     * @param list<string> $list
     */
    private static function ipMatchesList(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if ($entry === $ip) {
                return true;
            }
            if (str_contains($entry, '/') && self::ipInCidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$subnet, $maskBits] = $parts;
        if (!ctype_digit($maskBits)) {
            return false;
        }
        $maskBits = (int) $maskBits;
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $len = strlen($ipBin) * 8;
        if ($maskBits < 0 || $maskBits > $len) {
            return false;
        }
        $bytes = intdiv($maskBits, 8);
        $bits = $maskBits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }
        $mask = (~(0xff >> $bits)) & 0xff;

        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }
}
