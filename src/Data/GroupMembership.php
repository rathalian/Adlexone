<?php
declare(strict_types=1);

namespace Adlexone\Data;

use Adlexone\Auth\Role;

use Adlexone\Database\Database;

/**
 * Shared group membership for users, items, and item types.
 *
 * Canonical storage is the junction tables. The delimiter string remains only
 * so an upgrade can read a column that has not been converted yet.
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
     * A note with a group is for that group. A note with no group is for everyone,
     * unless it still names the weakest role that may read it.
     */
    public static function canSeeLogLine(int $groupId, string $audience = ''): bool
    {
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        if ($groupId > 0) {
            return in_array($groupId, self::userGroupIds($userId), true);
        }
        if ($audience === '') {
            return true;
        }

        return in_array($audience, Role::visibleAudiences($userId), true);
    }

    /**
     * SQL that keeps notes the signed-in person may read.
     *
     * @return array{sql: string, params: list<int|string>}
     */
    public static function visibleNoteFilter(string $alias = ''): array
    {
        $prefix = $alias === '' ? '' : $alias . '.';
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $audiences = Role::visibleAudiences($userId);
        $groups = self::userGroupIds($userId);
        $marks = implode(',', array_fill(0, count($audiences), '?'));
        $sql = '(' . $prefix . 'group_id = 0 AND ' . $prefix . 'audience IN (' . $marks . ')';
        $params = $audiences;
        if ($groups !== []) {
            $groupMarks = implode(',', array_fill(0, count($groups), '?'));
            $sql .= ' OR ' . $prefix . 'group_id IN (' . $groupMarks . ')';
            foreach ($groups as $groupId) {
                $params[] = $groupId;
            }
        }
        $sql .= ')';

        return ['sql' => $sql, 'params' => $params];
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
