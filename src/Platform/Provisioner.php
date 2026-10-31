<?php
declare(strict_types=1);

namespace Adlexone\Platform;

/**
 * Creates a customer folder: SQLite file, config JSON, attachments directory, and optional admin.
 */
final class Provisioner
{
    /**
     * @param array{site_name: string, public_url: string, username: string, password: string, first_name: string, last_name: string, email: string, starting_applications?: string} $admin
     */
    public static function install(
        string $install,
        string $configDir,
        string $sqlite,
        string $attachments,
        array $admin
    ): void {
        self::assertWritable($configDir, $sqlite, $attachments);
        self::makePrivateDir($configDir);
        self::makePrivateDir($attachments);
        self::makePrivateDir(dirname($sqlite));
        self::copyConfig(
            $install,
            $configDir,
            $admin['site_name'],
            $admin['public_url'],
            trim((string) ($admin['starting_applications'] ?? ''))
        );
        $seedAdmin = trim($admin['username']) !== '' && $admin['password'] !== '';
        self::prepareDatabase($install, $sqlite, $seedAdmin ? $admin : null);
        self::lockDown($configDir, dirname($sqlite), $attachments, $sqlite);
    }

    /**
     * @param array{site_name: string, public_url: string, username: string, password: string, first_name: string, last_name: string, email: string, starting_applications?: string}|null $admin
     */
    public static function prepareDatabase(string $install, string $sqlite, ?array $admin): void
    {
        $dir = dirname($sqlite);
        self::makePrivateDir($dir);
        $pdo = new \PDO('sqlite:' . $sqlite, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $exists = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
        if ($exists === false) {
            self::applySharedSchema($pdo, $install);
        }
        $version = (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
        if ($version < 9) {
            $pdo->prepare('INSERT OR IGNORE INTO schema_migrations (version, applied_at) VALUES (9, ?)')->execute([time()]);
        }
        if ($admin !== null) {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($count === 0) {
                self::seedAdmin($pdo, $admin);
            }
        }
        $pdo = null;
        @chmod($sqlite, 0600);
    }

    public static function checkpoint(string $sqlite): void
    {
        if (!is_file($sqlite)) {
            return;
        }
        $pdo = new \PDO('sqlite:' . $sqlite, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    }

    public static function removeTree(string $dir): void
    {
        $trimmed = rtrim($dir, '/\\');
        if ($trimmed !== '' && is_link($trimmed)) {
            @unlink($trimmed);

            return;
        }
        if (!is_dir($trimmed)) {
            return;
        }
        $dir = $trimmed;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                @unlink($file->getPathname());
            } elseif ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }

    public static function folderBytes(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $total = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total += (int) $file->getSize();
            }
        }

        return $total;
    }

    private static function assertWritable(string $configDir, string $sqlite, string $attachments): void
    {
        foreach ([$configDir, dirname($sqlite), $attachments] as $dir) {
            if (!Runtime::directoryWritable($dir)) {
                throw new \RuntimeException(
                    'PHP cannot create ' . $dir . '. The account that runs PHP (' . Runtime::phpUser()
                    . ') needs to own the parent folder. Create the folder and set it to mode 700, then reload.'
                );
            }
        }
    }

    private static function makePrivateDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create ' . $dir);
        }
        @chmod($dir, 0700);
    }

    private static function copyConfig(string $install, string $configDir, string $siteName, string $publicUrl, string $starting = ''): void
    {
        $example = rtrim($install, '/\\') . DIRECTORY_SEPARATOR . 'config.example';
        foreach (glob($example . DIRECTORY_SEPARATOR . '*.json') ?: [] as $src) {
            if (strtolower(basename($src)) === 'platform.json') {
                continue;
            }
            $dest = $configDir . basename($src);
            if (!is_file($dest)) {
                if (!copy($src, $dest)) {
                    throw new \RuntimeException('Cannot copy settings template to ' . $dest);
                }
            }
            @chmod($dest, 0600);
        }
        $settings = [
            'SET_SITE_NAME' => $siteName,
            'SET_PUBLIC_BASE_URL' => $publicUrl,
        ];
        if (in_array($starting, ['samples', 'exemplar', 'none'], true)) {
            $settings['SET_STARTING_APPLICATIONS'] = $starting;
        }
        self::patchJson($configDir . 'adlexone_settings.json', $settings);
        self::patchJson($configDir . 'advanced_settings.json', [
            'SET_DEBUG_MODE' => 'No',
            'SET_SHOW_SQL' => 'No',
        ]);
    }

    /**
     * @param array<string, string> $values
     */
    private static function patchJson(string $path, array $values): void
    {
        $existing = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        foreach ($values as $key => $value) {
            if ($key === 'SET_PUBLIC_BASE_URL' && $value === '' && isset($existing[$key])) {
                continue;
            }
            $existing[$key] = $value;
        }
        $json = json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($path, $json . "\n") === false) {
            throw new \RuntimeException('Cannot write ' . $path);
        }
        @chmod($path, 0600);
    }

    private static function applySharedSchema(\PDO $pdo, string $install): void
    {
        $file = rtrim($install, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'shared_schema.sql';
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*(?:\R|$)/', $sql) ?: [];
        foreach ($parts as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $upper = strtoupper($statement);
            if ($upper === 'BEGIN TRANSACTION' || $upper === 'COMMIT' || str_starts_with($upper, 'BEGIN')) {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    /**
     * @param array{site_name: string, public_url: string, username: string, password: string, first_name: string, last_name: string, email: string, starting_applications?: string} $admin
     */
    private static function seedAdmin(\PDO $pdo, array $admin): void
    {
        $pdo->prepare(
            'INSERT OR IGNORE INTO groups (group_id, group_name, description, role) VALUES (1, ?, ?, ?)'
        )->execute(['Administrator', 'Full access to this site.', '0']);
        $pdo->prepare(
            'INSERT INTO users (
                user_name, password, first_name, last_name, email, theme, lastactive, language,
                role, home_controller, home_controller_name, settings
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $admin['username'],
            password_hash($admin['password'], PASSWORD_DEFAULT),
            $admin['first_name'],
            $admin['last_name'],
            $admin['email'],
            '',
            'active',
            'English',
            0,
            'home',
            'Home',
            '',
        ]);
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT OR IGNORE INTO user_groups (user_id, group_id) VALUES (?, 1)')->execute([$userId]);
    }

    private static function lockDown(string $configDir, string $databaseDir, string $attachments, string $sqlite): void
    {
        foreach ([$configDir, $databaseDir, $attachments] as $dir) {
            @chmod($dir, 0700);
        }
        foreach (glob(rtrim($databaseDir, '/\\') . DIRECTORY_SEPARATOR . '*.sqlite*') ?: [] as $file) {
            if (is_file($file)) {
                @chmod($file, 0600);
            }
        }
        foreach (glob($configDir . '*.json') ?: [] as $file) {
            @chmod($file, 0600);
        }
    }
}
