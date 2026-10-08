<?php
declare(strict_types=1);

use Adlexone\Data\GroupMembership;
use Adlexone\support\Database;
use Adlexone\support\RenderViews;

/**
 * Items this person can open: owned, created, or shared through a group.
 * Optional item_type_id scopes the list to one record type.
 *
 * @return list<int>
 */
function applicationAccessibleItemIds(?int $itemTypeId = null): array
{
    $userId = (int) ($_SESSION['access_user_id'] ?? 0);
    if ($userId <= 0) {
        return [];
    }

    $ids = [];
    $owned = Database::select('items', ['item_id', 'item_type_id'], 'user_security = ? OR creator_security = ?', [$userId, $userId]);
    foreach ($owned as $row) {
        if ($itemTypeId !== null && (int) $row['item_type_id'] !== $itemTypeId) {
            continue;
        }
        $ids[] = (int) $row['item_id'];
    }
    foreach (GroupMembership::itemIdsForUser($userId) as $itemId) {
        if ($itemTypeId !== null) {
            $item = Database::first('items', ['item_type_id'], 'item_id = ?', [$itemId]);
            if ($item === null || (int) $item['item_type_id'] !== $itemTypeId) {
                continue;
            }
        }
        $ids[] = $itemId;
    }

    $ids = array_values(array_unique(array_filter($ids)));
    rsort($ids);
    return $ids;
}

/**
 * Work list for an application (or the legacy Contact Centre pack).
 */
function showApplicationWork(?int $itemTypeId = null, string $title = 'Work'): void
{
    $ids = applicationAccessibleItemIds($itemTypeId);
    $base = defined('MAN_BASE_URL') ? (string) MAN_BASE_URL : 'index.php';
    $rows = [];
    if ($ids !== []) {
        $result = Database::select(
            'items',
            ['item_id', 'item_title', 'create_date', 'item_type_id'],
            'WHERE item_id IN (' . implode(',', $ids) . ') ORDER BY item_id DESC'
        );
        foreach ($result as $row) {
            $itemId = (int) $row['item_id'];
            $rows[] = RenderViews::itemRecord(
                $row,
                $base . '&item=' . $itemId,
                RenderViews::itemActions($itemId, $base)
            );
        }
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        [
            'title' => $title,
            'html' => RenderViews::buildRecordList([
                'column' => RenderViews::getLanguageConstant('LA_84', 'TXT_84'),
                'columns' => RenderViews::itemListColumns(),
                'searchLabel' => defined('TXT_3') ? TXT_3 : 'Search',
                'empty' => defined('TXT_115') ? TXT_115 : 'No items found.',
                'groups' => [['rows' => $rows]],
            ]),
        ],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}
