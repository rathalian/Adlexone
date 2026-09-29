<?php
declare(strict_types=1);

use Adlexone\support\Database;
use Adlexone\support\RenderViews;

/**
 * Work is the items this person can open: ones they own, created, or share
 * through a group.
 */
function showServiceCentreWork(): void
{
    $ids = serviceCentreAccessibleItemIds();
    $base = defined('MAN_BASE_URL')
        ? (string) MAN_BASE_URL
        : serviceCentreUrl();
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
            'title' => APP_SC_TXT_1,
            'html' => RenderViews::buildRecordList([
                'column' => RenderViews::getLanguageConstant('LA_84', 'TXT_84'),
                'columns' => RenderViews::itemListColumns(),
                'searchLabel' => TXT_3,
                'empty' => TXT_115,
                'groups' => [['rows' => $rows]],
            ]),
        ],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * @return list<int>
 */
function serviceCentreAccessibleItemIds(): array
{
    $userId = (string) ($_SESSION['access_user_id'] ?? '');
    if ($userId === '') {
        return [];
    }

    $ids = [];
    $owned = Database::select('items', ['item_id'], 'user_security = ? OR creator_security = ?', [$userId, $userId]);
    foreach ($owned as $row) {
        $ids[] = (int) $row['item_id'];
    }

    $membership = Database::first('group_members', ['groups'], 'user_id = ?', [$userId]);
    foreach (preg_split('/\}-\{/', (string) ($membership['groups'] ?? '')) ?: [] as $group) {
        $group = trim($group, " \t\n\r\0\x0B{}-");
        if ($group === '' || !ctype_digit($group)) {
            continue;
        }
        $groupItems = Database::select('items', ['item_id'], 'group_security LIKE ?', ['%}-{' . $group . '}-{%']);
        foreach ($groupItems as $row) {
            $ids[] = (int) $row['item_id'];
        }
    }

    $ids = array_values(array_unique(array_filter($ids)));
    rsort($ids);
    return $ids;
}
