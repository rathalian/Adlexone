<?php
declare(strict_types=1);

namespace Adlexone\Database;

use Adlexone\support\Database;

/**
 * Ids, sort, and page for an item list. The sort is one column, optionally DESC.
 */
final class RecordListQuery
{
    /**
     * @param array<int|string, mixed> $rows
     * @return list<int>
     */
    public static function ids(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $ids[] = (int) ($row['item_id'] ?? 0);
            } elseif (is_scalar($row)) {
                $ids[] = (int) $row;
            }
        }
        $ids = array_values(array_unique($ids));

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }

    /**
     * @param list<int> $ids
     * @return array{0: string, 1: list<int>}
     */
    public static function idWhere(array $ids): array
    {
        if ($ids === []) {
            return ['item_id = ?', [0]];
        }
        $marks = implode(', ', array_fill(0, count($ids), '?'));

        return ['item_id IN (' . $marks . ')', array_values($ids)];
    }

    public static function order(string $posted, string $fallback = 'item_id DESC'): string
    {
        $order = Database::orderBy($posted);
        if ($order !== '') {
            return $order;
        }
        if ($fallback === '') {
            return '';
        }
        $fallbackOrder = Database::orderBy($fallback);

        return $fallbackOrder !== '' ? $fallbackOrder : 'item_id DESC';
    }

    public static function page(mixed $posted): int
    {
        $page = is_scalar($posted) ? (int) $posted : 1;

        return $page > 0 ? $page : 1;
    }

    public static function column(string $posted): string
    {
        $column = Database::orderBy(trim($posted));
        if ($column === '' || str_contains($column, ' ')) {
            return '';
        }

        return $column;
    }
}
