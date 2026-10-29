<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Http\PublicBaseUrl;

/**
 * Decides which database this request uses.
 *
 * One install keeps its SQLite file, settings, and uploads in a storage directory
 * chosen at setup (INLAY_STORAGE), or in the original folders beside the code.
 * INLAY_DATA still selects the older layout. A SaaS host sets a stores directory
 * and an operator host. Each customer then has their own folder.
 */
final class Runtime
{
    public const APP = 'app';
    public const SETUP = 'setup';
    public const OPERATOR = 'operator';
    public const SUSPENDED = 'suspended';
    public const UNKNOWN = 'unknown';

    private static string $mode = self::APP;

    private static string $notice = '';

    /** @var array<string, string> */
    private static array $paths = [];

    public static function mode(): string
    {
        return self::$mode;
    }

    public static function notice(): string
    {
        return self::$notice;
    }

    /**
     * @return array<string, string>
     */
    public static function paths(): array
    {
        return self::$paths;
    }

    public static function define(string $install): void
    {
        $install = rtrim($install, '/\\') . DIRECTORY_SEPARATOR;
        $platform = self::platformSettings($install);
        $stores = $platform['stores'];
        $operatorHost = $platform['operator_host'];
        $platformDb = $platform['platform_db'];
        $host = self::requestHost();

        if ($stores !== '') {
            self::definePlatform($install, $stores, $operatorHost, $platformDb, $host);
            return;
        }

        $chosen = InstallLayout::resolve($install);
        self::constants($install, $chosen['config'], $chosen['attachments'], $chosen['sqlite']);
        self::$paths = self::pathSet($install, $chosen['config'], $chosen['attachments'], $chosen['sqlite'], '', '', '');
        self::$mode = self::needsSetup($chosen['sqlite']) ? self::SETUP : self::APP;
    }

    public static function loadConfig(): void
    {
        $configDir = (string) SET_CONFIGURATION_PATH;
        if (!is_dir($configDir)) {
            return;
        }
        foreach (glob($configDir . '*.json') ?: [] as $jsonFile) {
            if (strtolower(basename($jsonFile)) === 'platform.json') {
                continue;
            }
            \Adlexone\support\SharedMethods::loadJsonSettingsToConstants($jsonFile);
        }
        foreach (glob($configDir . '*', GLOB_ONLYDIR) ?: [] as $subDir) {
            foreach (glob($subDir . '/*.json') ?: [] as $jsonFile) {
                \Adlexone\support\SharedMethods::loadJsonSettingsToConstants($jsonFile);
            }
        }
    }

    /**
     * Sets the time zone dates are shown in. Timestamps are stored as Unix
     * time, so changing the zone never changes stored values.
     */
    public static function applyTimezone(): void
    {
        $zone = defined('SET_TIMEZONE') ? trim((string) SET_TIMEZONE) : '';
        if ($zone !== '' && in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            date_default_timezone_set($zone);
        }
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = PublicBaseUrl::https($_SERVER);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function needsSetup(string $sqlite): bool
    {
        if (!is_file($sqlite) || filesize($sqlite) === 0) {
            return true;
        }
        try {
            $pdo = new \PDO('sqlite:' . $sqlite, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $table = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
            if ($table === false) {
                return true;
            }
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            return $count === 0;
        } catch (\Throwable) {
            return true;
        }
    }

    public static function phpUser(): string
    {
        if (function_exists('posix_geteuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (is_array($info) && trim((string) ($info['name'] ?? '')) !== '') {
                return (string) $info['name'];
            }
        }
        $user = get_current_user();

        return $user !== '' ? $user : 'the account that runs PHP';
    }

    public static function directoryWritable(string $dir): bool
    {
        $dir = rtrim($dir, '/\\');
        if ($dir === '') {
            return false;
        }
        if (is_dir($dir)) {
            return is_writable($dir);
        }
        $parent = $dir;
        while (!is_dir($parent)) {
            $next = dirname($parent);
            if ($next === $parent) {
                return false;
            }
            $parent = $next;
        }

        return is_writable($parent);
    }

    public static function requestHost(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost')));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        if (preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
            return 'localhost';
        }

        return $host;
    }

    /**
     * @return array{stores: string, operator_host: string, platform_db: string}
     */
    private static function platformSettings(string $install): array
    {
        $file = $install . 'config' . DIRECTORY_SEPARATOR . 'platform.json';
        $json = [];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }
        $stores = self::env('INLAY_STORES');
        if ($stores === '') {
            $stores = trim((string) ($json['INLAY_STORES'] ?? ''));
        }
        $host = self::env('INLAY_OPERATOR_HOST');
        if ($host === '') {
            $host = trim((string) ($json['INLAY_OPERATOR_HOST'] ?? ''));
        }
        $db = self::env('INLAY_PLATFORM_DB');
        if ($db === '') {
            $db = trim((string) ($json['INLAY_PLATFORM_DB'] ?? ''));
        }
        if ($stores !== '' && !self::isAbsolute($stores)) {
            $stores = $install . $stores;
        }
        if ($db !== '' && !self::isAbsolute($db)) {
            $db = $install . $db;
        }
        $stores = $stores === '' ? '' : rtrim($stores, '/\\');
        if ($stores !== '' && $db === '') {
            $db = dirname($stores) . DIRECTORY_SEPARATOR . 'platform.sqlite';
        }

        return [
            'stores' => $stores,
            'operator_host' => strtolower($host),
            'platform_db' => $db,
        ];
    }

    private static function definePlatform(
        string $install,
        string $stores,
        string $operatorHost,
        string $platformDb,
        string $host
    ): void {
        $installConfig = $install . 'config' . DIRECTORY_SEPARATOR;
        $installAttachments = $install . 'storage' . DIRECTORY_SEPARATOR . 'attachments' . DIRECTORY_SEPARATOR;

        if ($operatorHost === '') {
            self::$notice = 'SaaS stores are configured, and the operator host is empty. Set INLAY_OPERATOR_HOST to the hostname of the management site, in the environment or in config/platform.json next to the code.';
            self::constants($install, $installConfig, $installAttachments, $platformDb);
            self::$paths = self::pathSet($install, $installConfig, $installAttachments, $platformDb, $stores, $operatorHost, $platformDb);
            self::$mode = self::UNKNOWN;
            return;
        }

        if (strcasecmp($host, $operatorHost) === 0) {
            self::constants($install, $installConfig, $installAttachments, $platformDb);
            self::$paths = self::pathSet($install, $installConfig, $installAttachments, $platformDb, $stores, $operatorHost, $platformDb);
            self::$mode = self::OPERATOR;
            return;
        }

        $store = is_file($platformDb) ? PlatformDb::storeByHost($platformDb, $host) : null;
        if ($store === null) {
            self::$notice = 'No customer site uses this address.';
            self::constants($install, $installConfig, $installAttachments, $platformDb);
            self::$paths = self::pathSet($install, $installConfig, $installAttachments, $platformDb, $stores, $operatorHost, $platformDb);
            self::$mode = self::UNKNOWN;
            return;
        }

        $folder = $stores . DIRECTORY_SEPARATOR . $store['slug'] . DIRECTORY_SEPARATOR;
        $config = $folder . 'config' . DIRECTORY_SEPARATOR;
        $attachments = $folder . 'attachments' . DIRECTORY_SEPARATOR;
        $sqlite = $folder . 'adlexone.sqlite';
        self::constants($install, $config, $attachments, $sqlite);
        self::$paths = self::pathSet($install, $config, $attachments, $sqlite, $stores, $operatorHost, $platformDb);
        self::$paths['store_label'] = $store['label'];
        self::$paths['store_slug'] = $store['slug'];
        self::$paths['store_host'] = $store['host'];

        if (($store['status'] ?? '') !== 'active') {
            self::$notice = 'This site is suspended. The database, settings, and files are still on disk. The operator can turn the site back on.';
            self::$mode = self::SUSPENDED;
            return;
        }

        self::$mode = self::needsSetup($sqlite) ? self::SETUP : self::APP;
    }

    /**
     * @return array<string, string>
     */
    private static function pathSet(
        string $install,
        string $config,
        string $attachments,
        string $sqlite,
        string $stores,
        string $operatorHost,
        string $platformDb
    ): array {
        return [
            'install' => $install,
            'public' => $install . 'public' . DIRECTORY_SEPARATOR,
            'config' => $config,
            'attachments' => $attachments,
            'sqlite' => $sqlite,
            'database_dir' => dirname($sqlite) . DIRECTORY_SEPARATOR,
            'stores' => $stores,
            'operator_host' => $operatorHost,
            'platform_db' => $platformDb,
            'php_user' => self::phpUser(),
        ];
    }

    private static function constants(string $install, string $config, string $attachments, string $sqliteFile): void
    {
        if (!defined('SET_INSTALL_PATH')) {
            define('SET_INSTALL_PATH', $install);
        }
        if (!defined('SET_CONFIGURATION_PATH')) {
            define('SET_CONFIGURATION_PATH', $config);
        }
        if (!defined('SET_ATTACHMENTS_PATH')) {
            define('SET_ATTACHMENTS_PATH', $attachments);
        }
        if (!defined('DSN')) {
            define('DSN', 'sqlite:' . $sqliteFile);
        }
    }

    private static function env(string $name): string
    {
        $value = getenv($name);
        if ($value === false) {
            return '';
        }

        return trim($value);
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
