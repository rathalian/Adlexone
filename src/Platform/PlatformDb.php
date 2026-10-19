<?php
declare(strict_types=1);

namespace Adlexone\Platform;

/**
 * Operator database. Customer records never live here.
 */
final class PlatformDb
{
    public static function pdo(string $path): \PDO
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create the folder for the operator database: ' . $dir);
        }
        $pdo = new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        self::ensureSchema($pdo);
        if (is_file($path)) {
            @chmod($path, 0600);
            @chmod($dir, 0700);
        }

        return $pdo;
    }

    public static function ensureSchema(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS operators (
                operator_id INTEGER PRIMARY KEY,
                user_name TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS stores (
                store_id INTEGER PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                label TEXT NOT NULL,
                host TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT "active",
                admin_email TEXT NOT NULL DEFAULT "",
                created_at INTEGER NOT NULL
            )'
        );
    }

    public static function operatorCount(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        $count = self::pdo($path)->query('SELECT COUNT(*) FROM operators')->fetchColumn();

        return (int) $count;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function operatorByName(string $path, string $username): ?array
    {
        $statement = self::pdo($path)->prepare('SELECT * FROM operators WHERE user_name = ?');
        $statement->execute([$username]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public static function addOperator(string $path, string $username, string $password): void
    {
        $statement = self::pdo($path)->prepare(
            'INSERT INTO operators (user_name, password, created_at) VALUES (?, ?, ?)'
        );
        $statement->execute([$username, password_hash($password, PASSWORD_DEFAULT), time()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function stores(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $rows = self::pdo($path)->query('SELECT * FROM stores ORDER BY label COLLATE NOCASE')->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function storeByHost(string $path, string $host): ?array
    {
        $statement = self::pdo($path)->prepare('SELECT * FROM stores WHERE host = ?');
        $statement->execute([strtolower($host)]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function storeById(string $path, int $id): ?array
    {
        $statement = self::pdo($path)->prepare('SELECT * FROM stores WHERE store_id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public static function addStore(string $path, string $slug, string $label, string $host, string $email): void
    {
        $statement = self::pdo($path)->prepare(
            'INSERT INTO stores (slug, label, host, status, admin_email, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$slug, $label, strtolower($host), 'active', $email, time()]);
    }

    public static function setStatus(string $path, int $id, string $status): void
    {
        $statement = self::pdo($path)->prepare('UPDATE stores SET status = ? WHERE store_id = ?');
        $statement->execute([$status, $id]);
    }

    public static function deleteStore(string $path, int $id): void
    {
        $statement = self::pdo($path)->prepare('DELETE FROM stores WHERE store_id = ?');
        $statement->execute([$id]);
    }
}
