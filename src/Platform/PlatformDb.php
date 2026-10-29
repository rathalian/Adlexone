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
            if (self::mayRestrictDirectory($dir)) {
                @chmod($dir, 0700);
            }
        }

        return $pdo;
    }

    /**
     * Mode 700 is for a folder this account owns. A shared directory such as /tmp stays as it is.
     */
    public static function mayRestrictDirectory(string $dir): bool
    {
        $normalized = rtrim(str_replace('\\', '/', $dir), '/');
        $real = realpath($dir);
        if ($real !== false) {
            $normalized = rtrim(str_replace('\\', '/', $real), '/');
        }
        if ($normalized === '') {
            return false;
        }
        $blocked = ['/', '/tmp', '/var', '/var/tmp', '/var/lib', '/home', '/usr', '/usr/local', '/opt', '/root', '/etc'];
        $temp = realpath(sys_get_temp_dir());
        if (is_string($temp) && $temp !== '') {
            $blocked[] = rtrim(str_replace('\\', '/', $temp), '/');
        }

        return !in_array($normalized, $blocked, true);
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
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS host_rate (
                rate_id INTEGER PRIMARY KEY,
                currency TEXT NOT NULL,
                monthly_cents INTEGER NOT NULL,
                person_cents INTEGER NOT NULL,
                gigabyte_cents INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS store_usage (
                store_id INTEGER PRIMARY KEY,
                measured_at INTEGER NOT NULL,
                database_bytes INTEGER NOT NULL,
                wal_bytes INTEGER NOT NULL,
                shm_bytes INTEGER NOT NULL,
                settings_bytes INTEGER NOT NULL,
                uploads_bytes INTEGER NOT NULL,
                other_bytes INTEGER NOT NULL,
                people INTEGER,
                applications INTEGER,
                records INTEGER,
                cost_cents INTEGER,
                counts_note TEXT NOT NULL DEFAULT "",
                FOREIGN KEY (store_id) REFERENCES stores(store_id) ON DELETE CASCADE
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
    public static function storeBySlug(string $path, string $slug): ?array
    {
        $statement = self::pdo($path)->prepare('SELECT * FROM stores WHERE slug = ?');
        $statement->execute([$slug]);
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
        $pdo = self::pdo($path);
        $pdo->prepare('DELETE FROM store_usage WHERE store_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM stores WHERE store_id = ?')->execute([$id]);
    }

    public static function updateStore(string $path, int $id, string $label, string $host, string $email): void
    {
        $statement = self::pdo($path)->prepare(
            'UPDATE stores SET label = ?, host = ?, admin_email = ? WHERE store_id = ?'
        );
        $statement->execute([$label, strtolower($host), $email, $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function rate(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $row = self::pdo($path)->query('SELECT * FROM host_rate WHERE rate_id = 1')->fetch();

        return is_array($row) ? $row : null;
    }

    public static function saveRate(string $path, string $currency, int $monthly, int $person, int $gigabyte): void
    {
        $pdo = self::pdo($path);
        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM host_rate');
            $pdo->prepare(
                'INSERT INTO host_rate (rate_id, currency, monthly_cents, person_cents, gigabyte_cents, updated_at)
                 VALUES (1, ?, ?, ?, ?, ?)'
            )->execute([$currency, $monthly, $person, $gigabyte, time()]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function deleteRate(string $path): void
    {
        $pdo = self::pdo($path);
        $pdo->exec('DELETE FROM host_rate');
        $pdo->exec('UPDATE store_usage SET cost_cents = NULL');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function usage(string $path, int $id): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $statement = self::pdo($path)->prepare('SELECT * FROM store_usage WHERE store_id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function usageRows(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $rows = self::pdo($path)->query('SELECT * FROM store_usage')->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function saveUsage(
        string $path,
        int $storeId,
        int $measuredAt,
        int $database,
        int $wal,
        int $shm,
        int $settings,
        int $uploads,
        int $other,
        ?int $people,
        ?int $applications,
        ?int $records,
        ?int $cost,
        string $note
    ): void {
        $statement = self::pdo($path)->prepare(
            'INSERT INTO store_usage (
                store_id, measured_at, database_bytes, wal_bytes, shm_bytes, settings_bytes,
                uploads_bytes, other_bytes, people, applications, records, cost_cents, counts_note
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(store_id) DO UPDATE SET
                measured_at = excluded.measured_at,
                database_bytes = excluded.database_bytes,
                wal_bytes = excluded.wal_bytes,
                shm_bytes = excluded.shm_bytes,
                settings_bytes = excluded.settings_bytes,
                uploads_bytes = excluded.uploads_bytes,
                other_bytes = excluded.other_bytes,
                people = excluded.people,
                applications = excluded.applications,
                records = excluded.records,
                cost_cents = excluded.cost_cents,
                counts_note = excluded.counts_note'
        );
        $statement->execute([
            $storeId,
            $measuredAt,
            $database,
            $wal,
            $shm,
            $settings,
            $uploads,
            $other,
            $people,
            $applications,
            $records,
            $cost,
            $note,
        ]);
    }

    public static function setCost(string $path, int $storeId, int $cents): void
    {
        $statement = self::pdo($path)->prepare('UPDATE store_usage SET cost_cents = ? WHERE store_id = ?');
        $statement->execute([$cents, $storeId]);
    }
}
