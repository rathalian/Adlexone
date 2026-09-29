<?php
declare(strict_types=1);

namespace Adlexone\support;

/**
 * PDO access shared by the whole site.
 *
 * Single-table work uses select(), first(), insert(), update(), delete(), and exists().
 * Joins, calculations, and other SQL that is not a straight row write use rows(), row(),
 * and run(). Schema changes use exec().
 *
 * The connection is the DSN constant (sqlite:… today). MySQL and PostgreSQL use the
 * same calls when DSN is a mysql: or pgsql: string. Optional DB_USER and DB_PASSWORD
 * constants are sent for those drivers.
 */
class Database
{
    /** @var array<string, \PDO> */
    private static array $pdoPool = [];

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function select(
        string $table,
        array|string $columns = '*',
        string $where = '',
        array $params = [],
        string $orderBy = '',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $sql = 'SELECT ' . self::columnList($columns) . ' FROM ' . self::tableRef($table);
        $sql .= self::clause($where);
        if (trim($orderBy) !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
            if ($offset !== null) {
                $sql .= ' OFFSET ' . $offset;
            }
        }
        return self::rows($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function first(
        string $table,
        array|string $columns = '*',
        string $where = '',
        array $params = [],
        string $orderBy = ''
    ): ?array {
        $rows = self::select($table, $columns, $where, $params, $orderBy, self::clauseHasLimit($where) ? null : 1);
        return $rows[0] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function insert(string $table, array $data): int
    {
        self::writeRow('INSERT', $table, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Insert a row and skip the write when a unique key already exists.
     *
     * @param array<string, mixed> $data
     */
    public static function insertIgnore(string $table, array $data): void
    {
        $driver = self::driver();
        if ($driver === 'mysql') {
            self::writeRow('INSERT IGNORE', $table, $data);
            return;
        }
        if ($driver === 'pgsql') {
            self::writeRow('INSERT', $table, $data, ' ON CONFLICT DO NOTHING');
            return;
        }
        self::writeRow('INSERT OR IGNORE', $table, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $params
     */
    public static function update(string $table, array $data, string $where = '', array $params = []): int
    {
        if ($data === []) {
            throw new \InvalidArgumentException('update() requires at least one column.');
        }
        $sets = [];
        $values = [];
        foreach ($data as $column => $value) {
            $sets[] = self::columnName((string) $column) . ' = ?';
            $values[] = $value;
        }
        $sql = 'UPDATE ' . self::tableRef($table) . ' SET ' . implode(', ', $sets) . self::clause($where);
        return self::run($sql, array_merge($values, $params));
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public static function delete(string $table, string $where = '', array $params = []): int
    {
        return self::run('DELETE FROM ' . self::tableRef($table) . self::clause($where), $params);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public static function exists(string $table, string $where = '', array $params = []): bool
    {
        $sql = 'SELECT 1 AS present FROM ' . self::tableRef($table) . self::clause($where);
        if (!self::clauseHasLimit($where)) {
            $sql .= ' LIMIT 1';
        }
        return self::row($sql, $params) !== null;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public static function count(string $table, string $where = '', array $params = []): int
    {
        $row = self::first($table, 'COUNT(*) AS c', $where, $params);
        return (int) ($row['c'] ?? 0);
    }

    public static function tableExists(string $table): bool
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            return false;
        }
        $driver = self::driver();
        if ($driver === 'mysql') {
            $row = self::row(
                'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
            return $row !== null;
        }
        if ($driver === 'pgsql') {
            $row = self::row(
                'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?',
                [$table]
            );
            return $row !== null;
        }
        $row = self::row("SELECT 1 AS present FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
        return $row !== null;
    }

    /**
     * @return list<string>
     */
    public static function columns(string $table): array
    {
        self::columnName($table);
        $driver = self::driver();
        if ($driver === 'mysql') {
            $rows = self::rows(
                'SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                [$table]
            );
        } elseif ($driver === 'pgsql') {
            $rows = self::rows(
                'SELECT column_name AS name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position',
                [$table]
            );
        } else {
            $rows = self::rows('PRAGMA table_info(' . self::escapeIdentifier($table) . ')');
        }
        $names = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? $row['NAME'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return $names;
    }

    public static function columnExists(string $table, string $column): bool
    {
        return in_array($column, self::columns($table), true);
    }

    /**
     * Read every row from a SELECT, WITH, or PRAGMA statement.
     *
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function rows(string $sql, array $params = [], ?string $dsn = null): array
    {
        $statement = self::statement($sql, $params, $dsn);
        if (!self::isRead($sql)) {
            return [];
        }
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return $rows === false ? [] : $rows;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function row(string $sql, array $params = [], ?string $dsn = null): ?array
    {
        $rows = self::rows($sql, $params, $dsn);
        return $rows[0] ?? null;
    }

    /**
     * Run INSERT, UPDATE, DELETE, or other SQL that is not a row read.
     *
     * @param array<int|string, mixed> $params
     */
    public static function run(string $sql, array $params = [], ?string $dsn = null): int
    {
        return self::statement($sql, $params, $dsn)->rowCount();
    }

    public static function exec(string $sql, ?string $dsn = null): void
    {
        $pdo = self::pdo($dsn);
        $sql = self::adapt($sql);
        self::trace($sql);
        $pdo->exec($sql);
    }

    /**
     * Next integer id. Matches the existing MAX(id)+1 keys used across the tables.
     */
    public static function newID(string $table, string $idColumn, string $condition = ''): int
    {
        $sql = 'SELECT COALESCE(MAX(' . self::columnName($idColumn) . '), 0) + 1 AS next_id FROM ' . self::tableRef($table);
        $sql .= self::clause($condition);
        $row = self::row($sql);
        return isset($row['next_id']) ? (int) $row['next_id'] : 1;
    }

    public static function escape(string $value): string
    {
        $value = str_replace(["\0"], '', $value);
        return str_replace("'", "''", $value);
    }

    public static function escapeIdentifier(string $name): string
    {
        if ($name === '*') {
            return '*';
        }
        $quote = self::quoteChar();
        $parts = preg_split('/\./', $name) ?: [];
        $parts = array_map(static function (string $part) use ($quote): string {
            $part = trim($part, "\"'` \t\n\r\0\x0B");
            if ($part === '' || preg_match('/[^A-Za-z0-9_]/', $part)) {
                return $part;
            }
            return $quote . str_replace($quote, $quote . $quote, $part) . $quote;
        }, $parts);
        return implode('.', $parts);
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    // --- Older call shapes. New code uses the methods above. ---

    public static function query(string $sql, ?string $dsn = null, mixed ...$extra): DB_Result
    {
        $params = [];
        foreach ($extra as $value) {
            if (is_array($value)) {
                $params = $value;
            }
        }
        if (self::isRead($sql)) {
            return new DB_Result(self::rows($sql, $params, $dsn));
        }
        return new DB_Result([], self::run($sql, $params, $dsn));
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public static function queryParams(string $sql, array $params = [], ?string $dsn = null): DB_Result
    {
        if (self::isRead($sql)) {
            return new DB_Result(self::rows($sql, $params, $dsn));
        }
        return new DB_Result([], self::run($sql, $params, $dsn));
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function firstResultParams(string $sql, array $params = [], ?string $dsn = null): ?array
    {
        return self::row($sql, $params, $dsn);
    }

    public static function fetchArray($result): ?array
    {
        if ($result instanceof DB_Result) {
            return $result->fetchArray();
        }
        return null;
    }

    public static function numRows($result): int
    {
        if ($result instanceof DB_Result) {
            return $result->numRows();
        }
        return 0;
    }

    public static function sqlSelect(string $table, $columns, string $condition = ''): string
    {
        $cols = $columns;
        if (is_array($columns)) {
            $cols = implode(', ', array_map([self::class, 'escapeIdentifierListItem'], $columns));
        } elseif ($columns === '' || $columns === '*') {
            $cols = '*';
        }
        $sql = 'SELECT ' . $cols . ' FROM ' . self::escapeIdentifier($table);
        return $sql . self::clause($condition);
    }

    /**
     * @param array<string, mixed> $columnArray
     */
    public static function sqlInsert(string $table, array $columnArray): string
    {
        $cols = [];
        $vals = [];
        foreach ($columnArray as $column => $value) {
            $cols[] = self::escapeIdentifier((string) $column);
            $vals[] = self::literal($value);
        }
        return 'INSERT INTO ' . self::escapeIdentifier($table)
            . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    }

    /**
     * @param array<string, mixed> $columnArray
     */
    public static function sqlUpdate(string $table, array $columnArray, string $condition = ''): string
    {
        $sets = [];
        foreach ($columnArray as $column => $value) {
            $sets[] = self::escapeIdentifier((string) $column) . ' = ' . self::literal($value);
        }
        return 'UPDATE ' . self::escapeIdentifier($table) . ' SET ' . implode(', ', $sets) . self::clause($condition);
    }

    public static function sqlDelete(string $table, string $condition = ''): string
    {
        return 'DELETE FROM ' . self::escapeIdentifier($table) . self::clause($condition);
    }

    public static function sqlLookup(string $table, string $condition = '', ?string $dsn = null, mixed $show = null): string
    {
        unset($dsn, $show);
        return self::sqlSelect($table, '*', $condition);
    }

    public static function buildArray(string $sql, ?string $dsn = null, mixed ...$ignore): array
    {
        unset($ignore);
        return self::rows($sql, [], $dsn);
    }

    public static function firstResult($sql, ?string $dsn = null): ?array
    {
        return self::row((string) $sql, [], $dsn);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function writeRow(string $verb, string $table, array $data, string $suffix = ''): void
    {
        if ($data === []) {
            throw new \InvalidArgumentException('insert() requires at least one column.');
        }
        $cols = [];
        $holders = [];
        $params = [];
        foreach ($data as $column => $value) {
            $cols[] = self::columnName((string) $column);
            $holders[] = '?';
            $params[] = $value;
        }
        $sql = $verb . ' INTO ' . self::tableRef($table)
            . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $holders) . ')' . $suffix;
        self::run($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private static function statement(string $sql, array $params, ?string $dsn): \PDOStatement
    {
        $pdo = self::pdo($dsn);
        $sql = self::adapt($sql);
        self::trace($sql);
        $statement = $pdo->prepare($sql);
        $statement->execute(self::bindable($params));
        return $statement;
    }

    private static function pdo(?string $dsn = null): \PDO
    {
        $dsn = self::resolveDsn($dsn);
        if (!isset(self::$pdoPool[$dsn])) {
            $driver = self::driverFromDsn($dsn);
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            if ($driver === 'sqlite') {
                $pdo = new \PDO($dsn, null, null, $options);
                $pdo->exec('PRAGMA journal_mode = WAL;');
                $pdo->exec('PRAGMA synchronous = NORMAL;');
                $pdo->exec('PRAGMA foreign_keys = ON;');
                $pdo->exec('PRAGMA busy_timeout = 5000;');
            } else {
                $user = defined('DB_USER') ? (string) constant('DB_USER') : null;
                $password = defined('DB_PASSWORD') ? (string) constant('DB_PASSWORD') : null;
                $pdo = new \PDO($dsn, $user !== '' ? $user : null, $password, $options);
                if ($driver === 'mysql') {
                    $pdo->exec("SET NAMES utf8mb4");
                }
            }
            self::$pdoPool[$dsn] = $pdo;
        }
        return self::$pdoPool[$dsn];
    }

    private static function resolveDsn(?string $dsn): string
    {
        if ($dsn !== null && self::looksLikeDsn($dsn)) {
            return $dsn;
        }
        if (defined('DSN') && (string) constant('DSN') !== '') {
            return (string) constant('DSN');
        }
        throw new \InvalidArgumentException('No database DSN is configured.');
    }

    private static function looksLikeDsn(string $value): bool
    {
        return preg_match('/^(sqlite|mysql|pgsql|sqlsrv):/i', $value) === 1;
    }

    private static function driver(?string $dsn = null): string
    {
        return self::driverFromDsn(self::resolveDsn($dsn));
    }

    private static function driverFromDsn(string $dsn): string
    {
        $name = strtolower((string) strtok($dsn, ':'));
        return $name !== '' ? $name : 'sqlite';
    }

    private static function adapt(string $sql): string
    {
        $driver = self::driver();
        if ($driver === 'sqlite') {
            return self::mysqlToSqlite($sql);
        }
        if ($driver === 'mysql') {
            $sql = preg_replace('/\bINSERT\s+OR\s+IGNORE\b/i', 'INSERT IGNORE', $sql) ?? $sql;
            return preg_replace('/\bAUTOINCREMENT\b/i', 'AUTO_INCREMENT', $sql) ?? $sql;
        }
        if ($driver === 'pgsql' && preg_match('/\bINSERT\s+OR\s+IGNORE\b/i', $sql)) {
            $sql = preg_replace('/\bINSERT\s+OR\s+IGNORE\b/i', 'INSERT', $sql) ?? $sql;
            if (!preg_match('/\bON\s+CONFLICT\b/i', $sql)) {
                $sql = rtrim($sql, " \t;") . ' ON CONFLICT DO NOTHING';
            }
        }
        return $sql;
    }

    private static function mysqlToSqlite(string $sql): string
    {
        $sql = str_replace('`', '"', $sql);
        $sql = preg_replace('~\bNOW\(\s*\)~i', 'CURRENT_TIMESTAMP', $sql) ?? $sql;
        $sql = preg_replace('~\bRAND\(\s*\)~i', 'RANDOM()', $sql) ?? $sql;
        $sql = preg_replace('~\bSUBSTRING\s*\(~i', 'SUBSTR(', $sql) ?? $sql;
        $sql = preg_replace('~\bCHAR_LENGTH\s*\(~i', 'LENGTH(', $sql) ?? $sql;
        $sql = preg_replace('~\bUNIX_TIMESTAMP\s*\(~i', "strftime('%s', ", $sql) ?? $sql;
        $sql = preg_replace('~\bFROM_UNIXTIME\s*\(~i', 'datetime(', $sql) ?? $sql;
        $sql = preg_replace_callback('~\bLIMIT\s+(\d+)\s*,\s*(\d+)\b~i', static function (array $match): string {
            return 'LIMIT ' . (int) $match[2] . ' OFFSET ' . (int) $match[1];
        }, $sql) ?? $sql;
        $sql = preg_replace('~\s+COLLATE\s+\w+~i', '', $sql) ?? $sql;
        $sql = preg_replace('~ENGINE\s*=\s*\w+~i', '', $sql) ?? $sql;
        return preg_replace('~DEFAULT\s+CHARSET\s*=\s*\w+~i', '', $sql) ?? $sql;
    }

    private static function clause(string $where): string
    {
        $where = trim($where);
        if ($where === '') {
            return '';
        }
        if (preg_match('/^(WHERE|ORDER|GROUP|LIMIT|HAVING)\b/i', $where) === 1) {
            return ' ' . $where;
        }
        return ' WHERE ' . $where;
    }

    private static function clauseHasLimit(string $where): bool
    {
        return preg_match('/\bLIMIT\b/i', $where) === 1;
    }

    private static function isRead(string $sql): bool
    {
        return preg_match('/^\s*(SELECT|WITH|PRAGMA|EXPLAIN)\b/i', $sql) === 1;
    }

    private static function tableRef(string $table): string
    {
        if (preg_match('/[^A-Za-z0-9_.]/', $table)) {
            return $table;
        }
        return self::escapeIdentifier($table);
    }

    private static function columnName(string $name): string
    {
        $name = trim($name);
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid column name: ' . $name);
        }
        return self::escapeIdentifier($name);
    }

    /**
     * @param array<int|string>|string $columns
     */
    private static function columnList(array|string $columns): string
    {
        if ($columns === '*' || $columns === '' || $columns === []) {
            return '*';
        }
        if (is_string($columns)) {
            return $columns;
        }
        $items = [];
        foreach ($columns as $column) {
            $items[] = self::escapeIdentifierListItem((string) $column);
        }
        return implode(', ', $items);
    }

    private static function escapeIdentifierListItem(string $name): string
    {
        if (preg_match('/\bas\b/i', $name)) {
            [$left, $right] = preg_split('/\bas\b/i', $name, 2);
            return self::escapeIdentifier(trim((string) $left)) . ' AS ' . self::escapeIdentifier(trim((string) $right));
        }
        if (preg_match('/\s+([A-Za-z_][A-Za-z0-9_]*)$/', $name, $match)) {
            $left = substr($name, 0, -strlen($match[0]));
            return self::escapeIdentifier(trim($left)) . ' AS ' . self::escapeIdentifier($match[1]);
        }
        return self::escapeIdentifier($name);
    }

    private static function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_numeric($value) && !is_string($value)) {
            return (string) $value;
        }
        return "'" . self::escape((string) $value) . "'";
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int|string, mixed>
     */
    private static function bindable(array $params): array
    {
        $bound = [];
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }
            $bound[$key] = $value;
        }
        return $bound;
    }

    private static function quoteChar(): string
    {
        try {
            $driver = self::driver();
        } catch (\InvalidArgumentException) {
            return '"';
        }
        return $driver === 'mysql' ? '`' : '"';
    }

    private static function trace(string $sql): void
    {
        if (defined('SET_SHOW_SQL') && SET_SHOW_SQL === 'Yes') {
            echo '<!-- SQL: ' . htmlspecialchars($sql, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
        }
    }
}

/**
 * Buffered result for older query() callers.
 */
final class DB_Result implements \IteratorAggregate
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;
    private int $pos = 0;
    private int $affected;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(array $rows = [], int $affected = 0)
    {
        $this->rows = array_values($rows);
        $this->affected = $affected;
    }

    public function fetchArray(): ?array
    {
        if ($this->pos >= count($this->rows)) {
            return null;
        }
        return $this->rows[$this->pos++] ?? null;
    }

    public function numRows(): int
    {
        return count($this->rows);
    }

    public function affectedRows(): int
    {
        return $this->affected;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->rows);
    }
}
