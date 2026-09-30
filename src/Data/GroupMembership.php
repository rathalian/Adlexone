<?php
declare(strict_types=1);

namespace Adlexone\Data;

use Adlexone\support\Database;

/**
 * Shared group membership for users, items, and item types.
 *
 * Canonical storage is junction tables. Delimiter strings (`}-{id}-{`) remain
 * only as a transitional encode/decode format for legacy forms and columns.
 */
final class GroupMembership
{
    /**
     * @return list<int>
     */
    public static function parseDelimited(?string $encoded): array
    {
        if ($encoded === null || trim($encoded) === '') {
            return [];
        }
        $ids = [];
        foreach (preg_split('/\}-\{/', $encoded) ?: [] as $part) {
            $id = trim($part, " \t\n\r\0\x0B{}-");
            if ($id !== '' && ctype_digit($id)) {
                $ids[] = (int) $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param list<int|string> $ids
     */
    public static function toDelimited(array $ids): string
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }
        if ($clean === []) {
            return '}-{';
        }
        return '}-{' . implode('}-{', array_values($clean)) . '}-{';
    }

    /**
     * Group ids posted as group_{id} checkboxes/fields.
     *
     * @param array<string, mixed> $post
     * @return list<int>
     */
    public static function idsFromPost(array $post): array
    {
        $ids = [];
        foreach ($post as $key => $value) {
            if (!str_starts_with((string) $key, 'group_') || $value === '' || !is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if (ctype_digit($value)) {
                $ids[] = (int) $value;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    public static function userGroupIds(int $userId): array
    {
        $rows = Database::select('user_groups', ['group_id'], 'user_id = ?', [$userId], 'group_id ASC');
        return array_map(static fn(array $row): int => (int) $row['group_id'], $rows);
    }

    /**
     * @param list<int> $groupIds
     */
    public static function setUserGroups(int $userId, array $groupIds): void
    {
        Database::delete('user_groups', 'user_id = ?', [$userId]);
        foreach (array_unique(array_map('intval', $groupIds)) as $groupId) {
            if ($groupId <= 0) {
                continue;
            }
            Database::insertIgnore('user_groups', [
                'user_id' => $userId,
                'group_id' => $groupId,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    public static function itemGroupIds(int $itemId): array
    {
        $rows = Database::select('item_groups', ['group_id'], 'item_id = ?', [$itemId], 'group_id ASC');
        return array_map(static fn(array $row): int => (int) $row['group_id'], $rows);
    }

    /**
     * @param list<int> $groupIds
     */
    public static function setItemGroups(int $itemId, array $groupIds): void
    {
        Database::delete('item_groups', 'item_id = ?', [$itemId]);
        foreach (array_unique(array_map('intval', $groupIds)) as $groupId) {
            if ($groupId <= 0) {
                continue;
            }
            Database::insertIgnore('item_groups', [
                'item_id' => $itemId,
                'group_id' => $groupId,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    public static function itemTypeGroupIds(int $itemTypeId): array
    {
        $rows = Database::select('item_type_groups', ['group_id'], 'item_type_id = ?', [$itemTypeId], 'group_id ASC');
        return array_map(static fn(array $row): int => (int) $row['group_id'], $rows);
    }

    /**
     * @param list<int> $groupIds
     */
    public static function setItemTypeGroups(int $itemTypeId, array $groupIds): void
    {
        Database::delete('item_type_groups', 'item_type_id = ?', [$itemTypeId]);
        foreach (array_unique(array_map('intval', $groupIds)) as $groupId) {
            if ($groupId <= 0) {
                continue;
            }
            Database::insertIgnore('item_type_groups', [
                'item_type_id' => $itemTypeId,
                'group_id' => $groupId,
            ]);
        }
    }

    public static function userSharesItemGroup(int $userId, int $itemId): bool
    {
        $row = Database::row(
            'SELECT 1 AS ok
             FROM item_groups ig
             INNER JOIN user_groups ug ON ug.group_id = ig.group_id
             WHERE ig.item_id = ? AND ug.user_id = ?
             LIMIT 1',
            [$itemId, $userId]
        );
        return $row !== null;
    }

    /**
     * Item ids visible via group membership for a user.
     *
     * @return list<int>
     */
    public static function itemIdsForUser(int $userId): array
    {
        $rows = Database::rows(
            'SELECT DISTINCT ig.item_id
             FROM item_groups ig
             INNER JOIN user_groups ug ON ug.group_id = ig.group_id
             WHERE ug.user_id = ?',
            [$userId]
        );
        return array_map(static fn(array $row): int => (int) $row['item_id'], $rows);
    }

    public static function userInItemType(int $userId, int $itemTypeId): bool
    {
        $row = Database::row(
            'SELECT 1 AS ok
             FROM item_type_groups itg
             INNER JOIN user_groups ug ON ug.group_id = itg.group_id
             WHERE itg.item_type_id = ? AND ug.user_id = ?
             LIMIT 1',
            [$itemTypeId, $userId]
        );
        return $row !== null;
    }

    /**
     * SQL fragment: items reachable by the user's groups (parameterized ids inlined as ints).
     */
    public static function itemIdInUserGroupsSql(int $userId): string
    {
        $userId = (int) $userId;
        return 'item_id IN (
            SELECT ig.item_id FROM item_groups ig
            INNER JOIN user_groups ug ON ug.group_id = ig.group_id
            WHERE ug.user_id = ' . $userId . '
        )';
    }
}
