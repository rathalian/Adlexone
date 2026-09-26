<?php
declare(strict_types=1);

namespace Adlexone\support;
/**
 */
class Database
{
    /** @var array<string, \PDO> */
    private static array $pdoPool = [];

    /** Return a PDO connection, creating if needed. DSN can be array-like string "Database=/path/file.sqlite" */
private static function pdo(?string $dsn = null): \PDO
{
    if ($dsn === null && defined('DSN')) {
        $dsn = (string)constant('DSN');
    }
    if ($dsn === null || $dsn === '') {
        throw new \InvalidArgumentException('No DSN provided for SQLite connection.');
    }
    if (!isset(self::$pdoPool[$dsn])) {
        $pdo = new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA synchronous = NORMAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');
        self::$pdoPool[$dsn] = $pdo;
    }
    return self::$pdoPool[$dsn];
}
    /** Parse DSN-like string and return filesystem path for SQLite Database */
  private static function resolveDatabasePath(?string $dsn): string
  {
      // Preferred: constant SQLITE_DB_PATH (if present)
      if (defined('SQLITE_DB_PATH')) {
          $path = (string)constant('SQLITE_DB_PATH');
          if ($path !== '') { return $path; }
      }
      // If DSN looks like "sqlite:/path/file.sqlite"
      if ($dsn && stripos($dsn, 'sqlite:') === 0) {
          return substr($dsn, 7);
      }
      // If DSN "Database=...;..." or "database=..."
      if ($dsn && stripos($dsn, 'database=') !== false) {
          // Extract Database=... token
          $parts = preg_split('/[;\\s]+/i', $dsn);
          foreach ($parts as $p) {
              if (stripos($p, 'database=') === 0) {
                  $val = trim(substr($p, 9));
                  if ($val !== '') return $val;
              }
          }
      }
      // Default return value
      return '';
  }

    // --------------------- High-level helpers used by controllers ---------------------

    /** Execute a SELECT/INSERT/UPDATE/DELETE. Returns a DB_Result wrapper for SELECT, or effect wrapper for non-SELECT. */
    public static function query(string $sql, ?string $dsn = null)
    {
       $pdo = self::pdo($dsn);
       $sql2 = self::translateMysqlToSqlite($sql);
        $stmt = $pdo->prepare($sql2);
        echo '<!-- SQL: ' . htmlspecialchars($sql2, ENT_QUOTES|ENT_SUBSTITUTE) . ' -->' . "\n";
        $stmt->execute();
        // For SELECT, buffer rows so numRows() works reliably
        if (preg_match('/^\\s*SELECT\\b/i', $sql2)) {
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return new DB_Result($rows);
        }
        // Non-SELECT: return DB_Result with affected rows count
        $count = $stmt->rowCount();
        return new DB_Result([], $count);
    }

    /** Fetch next assoc row from a DB_Result */
    public static function fetchArray($result): ?array
    {
        if ($result instanceof DB_Result) {
            return $result->fetchArray();
        }
        return null;
    }

    /** Row count for last SELECT */
    public static function numRows($result): int
    {
        if ($result instanceof DB_Result) {
            return $result->numRows();
        }
        return 0;
    }

    /** Build a SELECT SQL string */
    public static function sqlSelect(string $table, $columns, string $condition = ''): string
    {
        $cols = $columns;
        if (is_array($columns)) {
            $cols = implode(', ', array_map([self::class, 'escapeIdentifierListItem'], $columns));
        } elseif ($columns === '' || $columns === '*') {
            $cols = '*';
        }
        $sql = 'SELECT ' . $cols . ' FROM ' . self::escapeIdentifier($table);
        $condition = trim($condition);
        if ($condition !== '') {
            // condition may already include WHERE/ORDER/GROUP/LIMIT
            if (preg_match('/^(WHERE|ORDER|GROUP|LIMIT)\\b/i', $condition) === 1) {
                $sql .= ' ' . $condition;
            } else {
                $sql .= ' WHERE ' . $condition;
            }
        }
        return $sql;
    }

    /** Build an INSERT SQL string from associative array */
    public static function sqlInsert(string $table, array $columnArray): string
    {
        $cols = [];
        $vals = [];
        foreach ($columnArray as $k => $v) {
            $cols[] = self::escapeIdentifier($k);
            if ($v === null) {
                $vals[] = 'NULL';
            } elseif (is_numeric($v) && !is_string($v)) {
                $vals[] = (string)$v;
            } else {
                $vals[] = "'" . self::escape((string)$v) . "'";
            }
        }
        return 'INSERT INTO ' . self::escapeIdentifier($table) .
               ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    }

    /** Build an UPDATE SQL string from associative array */
    public static function sqlUpdate(string $table, array $columnArray, string $condition = ''): string
    {
        $sets = [];
        foreach ($columnArray as $k => $v) {
            $id = self::escapeIdentifier($k);
            if ($v === null) {
                $sets[] = $id . ' = NULL';
            } elseif (is_numeric($v) && !is_string($v)) {
                $sets[] = $id . ' = ' . (string)$v;
            } else {
                $sets[] = $id . " = '" . self::escape((string)$v) . "'";
            }
        }
        $sql = 'UPDATE ' . self::escapeIdentifier($table) . ' SET ' . implode(', ', $sets);
        $condition = trim($condition);
        if ($condition !== '') {
            if (preg_match('/^(WHERE)\\b/i', $condition) === 1) {
                $sql .= ' ' . $condition;
            } else {
                $sql .= ' WHERE ' . $condition;
            }
        }
        return $sql;
    }

    /** Build a DELETE SQL string */
    public static function sqlDelete(string $table, string $condition = ''): string
    {
        $sql = 'DELETE FROM ' . self::escapeIdentifier($table);
        $condition = trim($condition);
        if ($condition !== '') {
            if (preg_match('/^(WHERE)\\b/i', $condition) === 1) {
                $sql .= ' ' . $condition;
            } else {
                $sql .= ' WHERE ' . $condition;
            }
        }
        return $sql;
    }

    /** Convenience: SELECT * with given condition */
    public static function sqlLookup(string $table, string $condition = ''): string
    {
        return self::sqlSelect($table, '*', $condition);
    }

    /** Run a query and return array of rows */
    public static function buildArray(string $sql, ?string $dsn = null, ...$ignore): array
    {
             $result = self::query($sql, $dsn);
        $rows = [];
        while ($row = self::fetchArray($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** Return first row (assoc) or null */
    public static function firstResult($sql, ?string $dsn = null): ?array
    {
        $result = self::query($sql, $dsn);
        return self::fetchArray($result);
    }

    /** Escape scalar for embedding in SQL string literals */
    public static function escape(string $value): string
    {
        // Normalize newlines and null bytes
        $value = str_replace(["\0"], '', $value);
        // Standard SQL escape: single-quote by doubling
        $value = str_replace("'", "''", $value);
        return $value;
    }

    /** Quote identifier safely (very conservative) */
    public static function escapeIdentifier(string $name): string
    {
        // If already quoted, return
        if ($name === '*') return '*';
        // Allow schema.table or prefix concatenations, quote parts that look like identifiers
        $parts = preg_split('/\\./', $name);
        $parts = array_map(function($p) {
            $p = trim($p, "\"` \t\n\r\0\x0B");
            // Preserve functions or expressions (has non-word chars other than underscore/digit)
            if ($p === '' || preg_match('/[^A-Za-z0-9_]/', $p)) {
                return $p;
            }
            return '"' . $p . '"';
        }, $parts);
        return implode('.', $parts);
    }

    /** Escape a single identifier list item used in SELECT columns */
    private static function escapeIdentifierListItem(string $name): string
    {
        // support "col AS alias" or "col alias"
        if (preg_match('/\\bas\\b/i', $name)) {
            [$left, $right] = preg_split('/\\bas\\b/i', $name, 2);
            return self::escapeIdentifier(trim($left)) . ' AS ' . self::escapeIdentifier(trim($right));
        }
        // Simple alias without AS
        if (preg_match('/\\s+([A-Za-z_][A-Za-z0-9_]*)$/', $name, $m)) {
            $left = substr($name, 0, -strlen($m[0]));
            $alias = $m[1];
            return self::escapeIdentifier(trim($left)) . ' AS ' . self::escapeIdentifier($alias);
        }
        return self::escapeIdentifier($name);
    }

    /** Escape for LIKE (escape % and _ with backslash) */
    public static function escapeLike(string $value): string
    {
        $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
        return $value;
    }

    /**
     * Generate a new integer ID by taking MAX(idColumn)+1 with optional condition.
     * This matches legacy pattern and avoids changing controllers.
     */
    public static function newID(string $table, string $idColumn, string $condition = ''): int
    {
        $sql = 'SELECT COALESCE(MAX(' . self::escapeIdentifier($idColumn) . '), 0) + 1 AS next_id FROM ' . self::escapeIdentifier($table);
        $condition = trim($condition);
        if ($condition !== '') {
            if (preg_match('/^(WHERE)\\b/i', $condition) === 1) {
                $sql .= ' ' . $condition;
            } else {
                $sql .= ' WHERE ' . $condition;
            }
        }
        $row = self::firstResult($sql);
        return isset($row['next_id']) ? (int)$row['next_id'] : 1;
    }

    // --------------------- Minimal MySQL→SQLite translator ---------------------

    private static function translateMysqlToSqlite(string $sql): string
    {
        $s = $sql;
        // Remove backticks
        $s = str_replace('`', '"', $s);
        // MySQL functions -> SQLite
        $s = preg_replace('~\\bNOW\\(\\s*\\)~i', 'CURRENT_TIMESTAMP', $s);
        $s = preg_replace('~\\bRAND\\(\\s*\\)~i', 'RANDOM()', $s);
        $s = preg_replace('~\\bSUBSTRING\\s*\\(~i', 'SUBSTR(', $s);
        $s = preg_replace('~\\bCHAR_LENGTH\\s*\\(~i', 'LENGTH(', $s);
        // UNIX_TIMESTAMP(x) -> strftime('%s', x)
        $s = preg_replace('~\bUNIX_TIMESTAMP\s*\(~i', "strftime('%s', ", $s);
        // FROM_UNIXTIME(x) -> datetime(x, 'unixepoch')
        $s = preg_replace('~\bFROM_UNIXTIME\s*\(~i', "datetime(", $s);

        // LIMIT offset,count -> LIMIT count OFFSET offset
        $s = preg_replace_callback('~\\bLIMIT\\s+(\\d+)\\s*,\\s*(\\d+)\\b~i', function($m){
            return 'LIMIT ' . (int)$m[2] . ' OFFSET ' . (int)$m[1];
        }, $s);

        // COLLATE clauses (not generally needed in SQLite default builds)
        $s = preg_replace('~\\s+COLLATE\\s+\\w+~i', '', $s);

        // ENGINE / CHARSET from DDL
        $s = preg_replace('~ENGINE\\s*=\\s*\\w+~i', '', $s);
        $s = preg_replace('~DEFAULT\\s+CHARSET\\s*=\\s*\\w+~i', '', $s);

        return $s;
    }
}

/**
 * Lightweight buffered result wrapper so that Database::numRows() works reliably with SQLite.
 */
final class DB_Result
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;
    private int $pos = 0;
    private int $affected;

    public function __construct(array $rows = [], int $affected = 0)
    {
        $this->rows = array_values($rows);
        $this->affected = $affected;
    }

    public function fetchArray(): ?array
    {
        if ($this->pos >= count($this->rows)) return null;
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
}
