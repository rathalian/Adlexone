<?php
declare(strict_types=1);

namespace Adlexone\Platform;

/**
 * Disk and database figures for one hosted site, and the cost of the host rate.
 *
 * The operator pages read the stored measurement. They do not open a customer
 * database on a normal view. Measure usage is the path that reads the folder
 * and the customer SQLite file.
 */
final class SiteUsage
{
    public const GIGABYTE = 1073741824;

    private const MAX_CENTS = 100000000;

    /**
     * @return array{database: int, wal: int, shm: int, settings: int, uploads: int, other: int, total: int}
     */
    public static function disk(string $folder): array
    {
        $parts = [
            'database' => 0,
            'wal' => 0,
            'shm' => 0,
            'settings' => 0,
            'uploads' => 0,
            'other' => 0,
            'total' => 0,
        ];
        $root = realpath($folder);
        if ($root === false || !is_dir($root)) {
            return $parts;
        }
        $prefix = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (!str_starts_with($path, $prefix)) {
                continue;
            }
            $real = realpath($path);
            if ($real === false || (!str_starts_with($real, $prefix) && $real !== $root)) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($path, strlen($prefix)));
            $size = (int) $file->getSize();
            if ($relative === 'adlexone.sqlite') {
                $parts['database'] += $size;
            } elseif ($relative === 'adlexone.sqlite-wal') {
                $parts['wal'] += $size;
            } elseif ($relative === 'adlexone.sqlite-shm') {
                $parts['shm'] += $size;
            } elseif ($relative === 'config' || str_starts_with($relative, 'config/')) {
                $parts['settings'] += $size;
            } elseif ($relative === 'attachments' || str_starts_with($relative, 'attachments/')) {
                $parts['uploads'] += $size;
            } else {
                $parts['other'] += $size;
            }
            $parts['total'] += $size;
        }

        return $parts;
    }

    public static function costCents(int $people, int $bytes, int $monthlyCents, int $personCents, int $gigabyteCents): int
    {
        if ($people < 0 || $bytes < 0 || $monthlyCents < 0 || $personCents < 0 || $gigabyteCents < 0) {
            throw new \InvalidArgumentException('Cost amounts cannot be negative.');
        }
        if ($personCents !== 0 && $people > intdiv(PHP_INT_MAX, $personCents)) {
            throw new \RuntimeException('The cost is too large to calculate.');
        }
        $peopleCharge = $people * $personCents;
        $half = intdiv(self::GIGABYTE, 2);
        if ($gigabyteCents !== 0 && $bytes > intdiv(PHP_INT_MAX - $half, $gigabyteCents)) {
            throw new \RuntimeException('The storage charge is too large to calculate.');
        }
        $storage = intdiv($bytes * $gigabyteCents + $half, self::GIGABYTE);
        if ($monthlyCents > PHP_INT_MAX - $peopleCharge || $monthlyCents + $peopleCharge > PHP_INT_MAX - $storage) {
            throw new \RuntimeException('The cost is too large to calculate.');
        }

        return $monthlyCents + $peopleCharge + $storage;
    }

    public static function parseAmount(string $posted): int
    {
        $posted = trim($posted);
        if (preg_match('/^\d{1,7}(\.\d{1,2})?$/', $posted) !== 1) {
            throw new \RuntimeException('Enter an amount from 0.00 to 1000000.00, such as 12.50.');
        }
        $pieces = explode('.', $posted, 2);
        $dollars = (int) $pieces[0];
        $cents = str_pad($pieces[1] ?? '0', 2, '0');
        $value = $dollars * 100 + (int) $cents;
        if ($value > self::MAX_CENTS) {
            throw new \RuntimeException('Enter an amount from 0.00 to 1000000.00, such as 12.50.');
        }

        return $value;
    }

    public static function parseCurrency(string $posted): string
    {
        $code = strtoupper(trim($posted));
        if (preg_match('/^[A-Z]{3}$/', $code) !== 1) {
            throw new \RuntimeException('Enter a three-letter currency code, such as AUD.');
        }

        return $code;
    }

    public static function formatMoney(int $cents, string $currency): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $major = intdiv($cents, 100);
        $minor = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . number_format($major, 0, '.', ',') . '.' . $minor . ' ' . $currency;
    }

    public static function formatAmount(int $cents): string
    {
        $cents = abs($cents);
        $major = intdiv($cents, 100);
        $minor = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return $major . '.' . $minor;
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 1) . ' GB';
    }

    public static function formatMeasured(int $unix): string
    {
        return date('j M Y, H:i', $unix) . ' (' . date_default_timezone_get() . ')';
    }

    public static function folder(string $stores, string $slug): string
    {
        return rtrim($stores, '/\\') . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR;
    }

    public static function assertInside(string $stores, string $folder): void
    {
        $root = realpath($stores);
        if ($root === false) {
            if (!is_dir($stores) && !mkdir($stores, 0700, true) && !is_dir($stores)) {
                throw new \RuntimeException('Cannot create the stores folder: ' . $stores);
            }
            $root = realpath($stores);
        }
        if ($root === false) {
            throw new \RuntimeException('The store folder must stay inside the stores directory.');
        }
        $rootPrefix = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $candidate = rtrim($folder, '/\\');
        if ($candidate === '' || is_link($candidate)) {
            throw new \RuntimeException('The store folder must stay inside the stores directory.');
        }
        $real = realpath($candidate);
        if ($real !== false) {
            if ($real === $root || !str_starts_with($real . DIRECTORY_SEPARATOR, $rootPrefix)) {
                throw new \RuntimeException('The store folder must stay inside the stores directory.');
            }

            return;
        }
        $parent = realpath(dirname($candidate));
        if ($parent === false || (!str_starts_with($parent . DIRECTORY_SEPARATOR, $rootPrefix) && $parent !== $root)) {
            throw new \RuntimeException('The store folder must stay inside the stores directory.');
        }
    }

    /**
     * The operator address. A public hostname still needs a dot. localhost is the name this machine uses.
     */
    public static function assertOperatorHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        if ($host === 'localhost') {
            return $host;
        }
        if (strlen($host) > 253 || preg_match('/^[a-z0-9.-]+$/', $host) !== 1 || !str_contains($host, '.')) {
            throw new \RuntimeException('Enter the full hostname for the operator site, such as operate.example.com. localhost is also accepted.');
        }

        return $host;
    }

    public static function assertHostname(string $host, string $operatorHost): string
    {
        $host = strtolower(trim($host));
        if (strlen($host) > 253 || preg_match('/^[a-z0-9.-]+$/', $host) !== 1 || !str_contains($host, '.')) {
            throw new \RuntimeException('Enter the full hostname customers will use, such as health.example.com.');
        }
        if (strcasecmp($host, $operatorHost) === 0) {
            throw new \RuntimeException('That hostname is the operator site. Use a different hostname for the customer.');
        }

        return $host;
    }

    /**
     * @param array<string, mixed> $store
     */
    public static function updateSite(
        string $platformDb,
        string $stores,
        array $store,
        string $label,
        string $host,
        string $email,
        string $operatorHost
    ): void {
        $label = trim($label);
        $host = self::assertHostname($host, $operatorHost);
        $email = trim($email);
        if ($label === '' || strlen($label) > 80) {
            throw new \RuntimeException('Enter a display name (80 characters or fewer).');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('The admin email does not look valid.');
        }
        $id = (int) $store['store_id'];
        $taken = PlatformDb::storeByHost($platformDb, $host);
        if ($taken !== null && (int) $taken['store_id'] !== $id) {
            throw new \RuntimeException('A customer site already uses that hostname.');
        }
        $folder = self::folder($stores, (string) $store['slug']);
        self::assertInside($stores, $folder);
        $settings = $folder . 'config' . DIRECTORY_SEPARATOR . 'adlexone_settings.json';
        if (!is_file($settings)) {
            throw new \RuntimeException('The settings file is missing: ' . $settings);
        }
        $publicUrl = strcasecmp($host, (string) $store['host']) === 0 ? null : 'https://' . $host;
        self::writeSiteSettings($settings, $label, $publicUrl);
        PlatformDb::updateStore($platformDb, $id, $label, $host, $email);
    }

    public static function writeSiteSettings(string $file, string $label, ?string $publicUrl): void
    {
        if (!is_file($file)) {
            throw new \RuntimeException('The settings file is missing: ' . $file);
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException($file . ' is not valid JSON.');
        }
        $decoded['SET_SITE_NAME'] = $label;
        if ($publicUrl !== null) {
            $decoded['SET_PUBLIC_BASE_URL'] = $publicUrl;
        }
        $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($file, $json . "\n") === false) {
            throw new \RuntimeException('Cannot write ' . $file);
        }
        @chmod($file, 0600);
    }

    /**
     * @param array<string, mixed> $store
     */
    public static function deleteSite(string $platformDb, string $stores, array $store, string $typed): void
    {
        $slug = (string) $store['slug'];
        if (!hash_equals($slug, $typed)) {
            throw new \RuntimeException('Type the slug ' . $slug . ' to delete that store.');
        }
        $folder = self::folder($stores, $slug);
        self::assertInside($stores, $folder);
        Provisioner::removeTree($folder);
        PlatformDb::deleteStore($platformDb, (int) $store['store_id']);
    }

    /**
     * @param array<string, mixed> $store
     * @return array{note: string, problem: string}
     */
    public static function measure(string $platformDb, array $store, string $folder): array
    {
        $disk = self::disk($folder);
        $sqlite = rtrim($folder, '/\\') . DIRECTORY_SEPARATOR . 'adlexone.sqlite';
        $previous = PlatformDb::usage($platformDb, (int) $store['store_id']);
        $counts = self::readCounts($sqlite);
        $problem = '';
        if ($counts['fresh']) {
            $people = $counts['people'];
            $applications = $counts['applications'];
            $records = $counts['records'];
        } elseif ($previous !== null) {
            $people = self::nullableInt($previous['people'] ?? null);
            $applications = self::nullableInt($previous['applications'] ?? null);
            $records = self::nullableInt($previous['records'] ?? null);
            $problem = (string) $store['label'] . ' could not be opened, so its previous figures were kept.';
        } else {
            $people = null;
            $applications = null;
            $records = null;
            $problem = (string) $store['label'] . ' could not be opened.';
        }
        $rate = PlatformDb::rate($platformDb);
        $cost = null;
        if ($rate !== null) {
            $cost = self::costCents(
                $people ?? 0,
                $disk['total'],
                (int) $rate['monthly_cents'],
                (int) $rate['person_cents'],
                (int) $rate['gigabyte_cents']
            );
        }
        PlatformDb::saveUsage(
            $platformDb,
            (int) $store['store_id'],
            time(),
            $disk['database'],
            $disk['wal'],
            $disk['shm'],
            $disk['settings'],
            $disk['uploads'],
            $disk['other'],
            $people,
            $applications,
            $records,
            $cost,
            $counts['fresh'] ? $counts['note'] : (string) ($previous['counts_note'] ?? '')
        );

        return ['note' => $counts['note'], 'problem' => $problem];
    }

    /**
     * @param list<array<string, mixed>> $stores
     */
    public static function measureAll(string $platformDb, string $storesDir, array $stores): string
    {
        $problems = [];
        $when = time();
        foreach ($stores as $store) {
            $result = self::measure($platformDb, $store, self::folder($storesDir, (string) $store['slug']));
            if ($result['problem'] !== '') {
                $problems[] = $result['problem'];
            }
        }
        $count = count($stores);
        $text = 'Measured ' . $count . ' ' . ($count === 1 ? 'site' : 'sites') . ' at ' . self::formatMeasured($when) . '.';
        foreach ($problems as $problem) {
            $text .= ' ' . $problem;
        }

        return $text;
    }

    public static function applyRate(string $platformDb): void
    {
        $rate = PlatformDb::rate($platformDb);
        if ($rate === null) {
            return;
        }
        foreach (PlatformDb::usageRows($platformDb) as $row) {
            $bytes = (int) $row['database_bytes']
                + (int) $row['wal_bytes']
                + (int) $row['shm_bytes']
                + (int) $row['settings_bytes']
                + (int) $row['uploads_bytes']
                + (int) $row['other_bytes'];
            $people = self::nullableInt($row['people'] ?? null);
            PlatformDb::setCost(
                $platformDb,
                (int) $row['store_id'],
                self::costCents(
                    $people ?? 0,
                    $bytes,
                    (int) $rate['monthly_cents'],
                    (int) $rate['person_cents'],
                    (int) $rate['gigabyte_cents']
                )
            );
        }
    }

    /**
     * Figures for the host page. Reads the operator database only.
     *
     * @return array{
     *   sites: int,
     *   active: int,
     *   suspended: int,
     *   bytes: int,
     *   people: int,
     *   applications: int,
     *   records: int,
     *   cost: int|null,
     *   measured: int,
     *   unmeasured: int,
     *   unset_counts: int
     * }
     */
    public static function hostFigures(string $platformDb): array
    {
        $stores = PlatformDb::stores($platformDb);
        $byId = [];
        foreach (PlatformDb::usageRows($platformDb) as $row) {
            $byId[(int) $row['store_id']] = $row;
        }
        $figures = [
            'sites' => count($stores),
            'active' => 0,
            'suspended' => 0,
            'bytes' => 0,
            'people' => 0,
            'applications' => 0,
            'records' => 0,
            'cost' => PlatformDb::rate($platformDb) === null ? null : 0,
            'measured' => 0,
            'unmeasured' => 0,
            'unset_counts' => 0,
        ];
        foreach ($stores as $store) {
            if ((string) $store['status'] === 'active') {
                $figures['active']++;
            } else {
                $figures['suspended']++;
            }
            $usage = $byId[(int) $store['store_id']] ?? null;
            if ($usage === null) {
                $figures['unmeasured']++;
                continue;
            }
            $figures['measured']++;
            $figures['bytes'] += (int) $usage['database_bytes']
                + (int) $usage['wal_bytes']
                + (int) $usage['shm_bytes']
                + (int) $usage['settings_bytes']
                + (int) $usage['uploads_bytes']
                + (int) $usage['other_bytes'];
            $people = self::nullableInt($usage['people'] ?? null);
            $applications = self::nullableInt($usage['applications'] ?? null);
            $records = self::nullableInt($usage['records'] ?? null);
            if ($people === null || $applications === null || $records === null) {
                $figures['unset_counts']++;
            }
            $figures['people'] += $people ?? 0;
            $figures['applications'] += $applications ?? 0;
            $figures['records'] += $records ?? 0;
            if ($figures['cost'] !== null && $usage['cost_cents'] !== null) {
                $figures['cost'] += (int) $usage['cost_cents'];
            }
        }

        return $figures;
    }

    /**
     * @return array{people: int|null, applications: int|null, records: int|null, note: string, fresh: bool}
     */
    private static function readCounts(string $sqlite): array
    {
        $unset = ['people' => null, 'applications' => null, 'records' => null, 'note' => '', 'fresh' => true];
        if (!is_file($sqlite) || filesize($sqlite) === 0) {
            return $unset;
        }
        try {
            $pdo = new \PDO(self::readOnlyDsn($sqlite), null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA query_only = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        } catch (\Throwable) {
            return [
                'people' => null,
                'applications' => null,
                'records' => null,
                'note' => 'The database could not be opened.',
                'fresh' => false,
            ];
        }
        $users = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
        if ($users === false) {
            return $unset;
        }
        try {
            $peopleCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        } catch (\Throwable) {
            return [
                'people' => null,
                'applications' => null,
                'records' => null,
                'note' => 'The users table is missing.',
                'fresh' => true,
            ];
        }
        if ($peopleCount === 0) {
            return $unset;
        }
        $notes = [];
        $applications = self::countQuery($pdo, 'SELECT COUNT(*) FROM applications WHERE enabled = 1', 'applications', $notes);
        $records = self::countQuery($pdo, 'SELECT COUNT(*) FROM items', 'items', $notes);

        return [
            'people' => $peopleCount,
            'applications' => $applications,
            'records' => $records,
            'note' => implode(' ', $notes),
            'fresh' => true,
        ];
    }

    /**
     * @param list<string> $notes
     */
    private static function countQuery(\PDO $pdo, string $sql, string $table, array &$notes): ?int
    {
        try {
            return (int) $pdo->query($sql)->fetchColumn();
        } catch (\Throwable) {
            $notes[] = 'The ' . $table . ' table is missing.';

            return null;
        }
    }

    private static function readOnlyDsn(string $sqlite): string
    {
        $path = str_replace('\\', '/', $sqlite);
        $path = str_replace(['?', '#'], ['%3F', '%23'], $path);

        return 'sqlite:file:' . $path . '?mode=ro';
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
