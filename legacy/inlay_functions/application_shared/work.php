<?php
declare(strict_types=1);

use Adlexone\Auth\Csrf;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
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
    if ((string) ($_GET['option'] ?? '') === 'move_card' && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        moveWorkCard($itemTypeId);
        return;
    }

    $ids = applicationAccessibleItemIds($itemTypeId);
    $base = defined('MAN_BASE_URL') ? (string) MAN_BASE_URL : 'index.php';
    $stand = workStand($ids, $itemTypeId, $base);
    $shown = workFocusIds($ids, $stand['field_id'], $stand['values']);
    $rows = [];
    if ($shown !== []) {
        $result = Database::select(
            'items',
            ['item_id', 'item_title', 'create_date', 'item_type_id'],
            'WHERE item_id IN (' . implode(',', $shown) . ') ORDER BY item_id DESC'
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

    $createHref = $base . '&option=show_item_types';
    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        [
            'title' => $title,
            'html' => $stand['html'] . workBoard($ids, $itemTypeId, $base, $stand) . RenderViews::buildRecordList([
                'column' => RenderViews::getLanguageConstant('LA_84', 'TXT_84'),
                'columns' => RenderViews::itemListColumns(),
                'searchLabel' => defined('TXT_3') ? TXT_3 : 'Search',
                'empty' => defined('TXT_115') ? TXT_115 : 'No items found.',
                'emptyIllustration' => 'empty-work',
                'emptyTitle' => 'No work yet',
                'emptyCopy' => 'Create the first record for this application.',
                'emptyAction' => '<a class="btn btn--primary btn--sm" href="' . htmlspecialchars($createHref, ENT_QUOTES, 'UTF-8') . '">New</a>',
                'groups' => [['rows' => $rows]],
            ]),
        ],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Counts for the records this person can open, grouped by the status menu when the record type has one.
 *
 * @param list<int> $ids
 * @return array{html: string, field_id: int, values: array<int, string>}
 */
function workStand(array $ids, ?int $itemTypeId, string $base): array
{
    $userId = (int) ($_SESSION['access_user_id'] ?? 0);
    $mine = 0;
    if ($ids !== [] && $userId > 0) {
        $owned = Database::select(
            'items',
            ['item_id'],
            'user_security = ? AND item_id IN (' . implode(',', $ids) . ')',
            [$userId]
        );
        $mine = count($owned);
    }

    $status = workStatusField($itemTypeId);
    $fieldId = (int) ($status['id'] ?? 0);
    $defined = $status['values'] ?? [];
    $counts = [];
    foreach ($defined as $value) {
        $counts[$value] = 0;
    }
    $valueByItem = [];
    if ($fieldId > 0 && $ids !== []) {
        $stored = Database::rows(
            'SELECT item_id, value FROM item_field_values WHERE custom_field_id = ? AND item_id IN (' . implode(',', $ids) . ')',
            [$fieldId]
        );
        foreach ($stored as $row) {
            $valueByItem[(int) $row['item_id']] = (string) ($row['value'] ?? '');
        }
        foreach ($ids as $id) {
            $value = $valueByItem[$id] ?? '';
            if ($value === '') {
                continue;
            }
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
    }

    $focus = (string) ($_GET['focus'] ?? '');
    if (!in_array($focus, ['', 'mine', 'status', 'empty'], true)) {
        $focus = '';
    }
    $selected = (string) ($_GET['status'] ?? '');

    $buckets = [
        workStandBucket($base, '', '', 'Records', count($ids), $focus === '', 'records'),
        workStandBucket($base, 'mine', '', 'Mine', $mine, $focus === 'mine', 'mine'),
    ];
    if ($fieldId > 0) {
        $empty = 0;
        foreach ($ids as $id) {
            if (($valueByItem[$id] ?? '') === '') {
                $empty++;
            }
        }
        foreach ($counts as $value => $count) {
            $buckets[] = workStandBucket($base, 'status', (string) $value, (string) $value, $count, $focus === 'status' && $selected === (string) $value, 'status:' . $value);
        }
        if ($empty > 0) {
            $buckets[] = workStandBucket($base, 'empty', '', 'Not set', $empty, $focus === 'empty', 'empty');
        }
    }

    $label = $fieldId > 0 ? 'Where the work stands, by ' . (string) $status['name'] : 'Where the work stands';

    return [
        'html' => '<nav class="work-stand" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . implode('', $buckets) . '</nav>',
        'field_id' => $fieldId,
        'values' => $valueByItem,
    ];
}

/**
 * @param array<int, string> $values item id => status value
 * @param list<int> $ids
 * @return list<int>
 */
function workFocusIds(array $ids, int $fieldId, array $values): array
{
    $focus = (string) ($_GET['focus'] ?? '');
    if ($focus === 'mine') {
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        if ($userId <= 0 || $ids === []) {
            return [];
        }
        $owned = Database::select(
            'items',
            ['item_id'],
            'user_security = ? AND item_id IN (' . implode(',', $ids) . ')',
            [$userId]
        );
        $keep = [];
        foreach ($owned as $row) {
            $keep[] = (int) $row['item_id'];
        }
        rsort($keep);
        return $keep;
    }
    if ($focus === 'empty' && $fieldId > 0) {
        $keep = [];
        foreach ($ids as $id) {
            if (($values[$id] ?? '') === '') {
                $keep[] = $id;
            }
        }
        return $keep;
    }
    if ($focus === 'status' && $fieldId > 0) {
        $want = (string) ($_GET['status'] ?? '');
        $keep = [];
        foreach ($ids as $id) {
            if (($values[$id] ?? '') === $want) {
                $keep[] = $id;
            }
        }
        return $keep;
    }

    return $ids;
}

/**
 * Menu field that says where a record stands. Prefers a field whose name is Status.
 *
 * @return array{id: int, name: string, values: list<string>}|null
 */
function workStatusField(?int $itemTypeId): ?array
{
    if ($itemTypeId === null || $itemTypeId <= 0) {
        return null;
    }
    $menus = [];
    $links = Database::select(
        'item_type_custom_fields',
        ['custom_field_id'],
        'item_type_id = ?',
        [$itemTypeId],
        'custom_field_order ASC'
    );
    foreach ($links as $link) {
        $field = Database::first(
            'custom_fields',
            ['custom_field_id', 'custom_field_name', 'field_type'],
            'custom_field_id = ?',
            [(int) $link['custom_field_id']]
        );
        if ($field === null || FieldTypes::normalise((string) ($field['field_type'] ?? '')) !== FieldTypes::MENU) {
            continue;
        }
        $menus[] = $field;
    }
    if ($menus === []) {
        return null;
    }
    $chosen = $menus[0];
    foreach ($menus as $field) {
        if (strcasecmp((string) $field['custom_field_name'], 'Status') === 0) {
            $chosen = $field;
            break;
        }
    }
    if (strcasecmp((string) $chosen['custom_field_name'], 'Status') !== 0) {
        foreach ($menus as $field) {
            if (str_contains(strtolower((string) $field['custom_field_name']), 'status')) {
                $chosen = $field;
                break;
            }
        }
    }
    $values = [];
    foreach (Database::select('custom_field_menu_values', ['menu_value'], 'custom_field_id = ?', [(int) $chosen['custom_field_id']], 'menu_value_id ASC') as $row) {
        $value = trim((string) ($row['menu_value'] ?? ''));
        if ($value !== '') {
            $values[] = $value;
        }
    }

    return [
        'id' => (int) $chosen['custom_field_id'],
        'name' => (string) $chosen['custom_field_name'],
        'values' => $values,
    ];
}

function workStandBucket(string $base, string $focus, string $status, string $label, int $count, bool $selected, string $key): string
{
    $href = $base;
    if ($focus !== '') {
        $href .= '&focus=' . rawurlencode($focus);
        if ($focus === 'status') {
            $href .= '&status=' . rawurlencode($status);
        }
    }
    $class = 'work-stand__bucket' . ($selected ? ' is-selected' : '');

    return '<a class="' . $class . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" data-stand="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">'
        . '<span class="work-stand__num">' . $count . '</span>'
        . '<span class="work-stand__label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</a>';
}

/**
 * Status columns for the same records as the list. Dragging a card sets that status and runs rules.
 *
 * @param list<int> $ids
 * @param array{html: string, field_id: int, values: array<int, string>} $stand
 */
function workBoard(array $ids, ?int $itemTypeId, string $base, array $stand): string
{
    $status = workStatusField($itemTypeId);
    if ($status === null || $status['values'] === []) {
        return '';
    }
    $fieldId = (int) $status['id'];
    $values = $stand['field_id'] === $fieldId ? $stand['values'] : workStatusValues($ids, $fieldId);
    $columns = [];
    foreach ($status['values'] as $value) {
        $columns[$value] = [];
    }
    $unset = [];
    $facts = workCardFacts($ids);
    foreach ($ids as $id) {
        $value = $values[$id] ?? '';
        $card = workCard($id, $facts[$id] ?? ['title' => '', 'owner' => '', 'log' => ''], $base, $value, $status['values']);
        if (isset($columns[$value])) {
            $columns[$value][] = $card;
        } else {
            $unset[] = $card;
        }
    }

    $html = '';
    foreach ($columns as $value => $cards) {
        $html .= workColumn((string) $value, $cards, true);
    }
    if ($unset !== []) {
        $html .= workColumn('Not set', $unset, false);
    }

    $move = htmlspecialchars($base . '&option=move_card', ENT_QUOTES, 'UTF-8');
    $token = htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8');

    return '<section class="work-board" aria-label="Board" data-move="' . $move . '" data-csrf="' . $token . '">'
        . $html
        . '</section>'
        . workBoardScript();
}

/**
 * @param list<string> $cards
 */
function workColumn(string $label, array $cards, bool $drop): string
{
    $dropAttr = $drop
        ? ' data-status="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"'
        : '';
    $countKey = $drop
        ? ' data-count="' . htmlspecialchars('status:' . $label, ENT_QUOTES, 'UTF-8') . '"'
        : '';

    return '<div class="work-board__col"' . $dropAttr . '>'
        . '<div class="work-board__head"><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
        . '<span class="work-board__count"' . $countKey . '>' . count($cards) . '</span></div>'
        . '<div class="work-board__cards">' . implode('', $cards) . '</div>'
        . '</div>';
}

/**
 * @param array{title: string, owner: string, log: string} $fact
 * @param list<string> $statuses
 */
function workCard(int $itemId, array $fact, string $base, string $status, array $statuses): string
{
    $title = $fact['title'] !== '' ? $fact['title'] : ('Record ' . $itemId);
    $href = htmlspecialchars($base . '&item=' . $itemId, ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $owner = $fact['owner'] !== '' ? '<p class="work-card__owner">' . htmlspecialchars($fact['owner'], ENT_QUOTES, 'UTF-8') . '</p>' : '<p class="work-card__owner" hidden></p>';
    $log = $fact['log'] !== '' ? '<p class="work-card__log">' . htmlspecialchars($fact['log'], ENT_QUOTES, 'UTF-8') . '</p>' : '<p class="work-card__log" hidden></p>';
    $options = '<option value="">Move to</option>';
    foreach ($statuses as $value) {
        $hidden = $value === $status ? ' hidden' : '';
        $options .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $hidden . '>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</option>';
    }

    return '<article class="work-card" draggable="true" data-item="' . $itemId . '" data-status="' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '">'
        . '<a class="work-card__title" draggable="false" href="' . $href . '">' . $titleEsc . '</a>'
        . $owner
        . $log
        . '<label class="work-card__move">Move <select aria-label="Move ' . $titleEsc . '">' . $options . '</select></label>'
        . '</article>';
}

/**
 * @param list<int> $ids
 * @return array<int, array{title: string, owner: string, log: string}>
 */
function workCardFacts(array $ids): array
{
    $facts = [];
    if ($ids === []) {
        return $facts;
    }
    $in = implode(',', $ids);
    $owners = [];
    foreach (Database::select('items', ['item_id', 'item_title', 'user_security'], 'item_id IN (' . $in . ')') as $row) {
        $facts[(int) $row['item_id']] = [
            'title' => trim((string) ($row['item_title'] ?? '')),
            'owner' => '',
            'log' => '',
            'user_id' => (int) ($row['user_security'] ?? 0),
        ];
        $owners[(int) ($row['user_security'] ?? 0)] = true;
    }
    unset($owners[0]);
    if ($owners !== []) {
        $names = [];
        foreach (Database::select('users', ['user_id', 'user_name', 'first_name', 'last_name'], 'user_id IN (' . implode(',', array_keys($owners)) . ')') as $user) {
            $name = trim((string) ($user['user_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
            }
            $names[(int) $user['user_id']] = $name;
        }
        foreach ($facts as $id => $fact) {
            $facts[$id]['owner'] = $names[$fact['user_id']] ?? '';
        }
    }
    $logs = Database::rows(
        'SELECT c.item_id, c.log_text FROM core_log c
         INNER JOIN (
            SELECT item_id, MAX(log_item_sequence) AS seq FROM core_log WHERE item_id IN (' . $in . ') GROUP BY item_id
         ) latest ON latest.item_id = c.item_id AND latest.seq = c.log_item_sequence'
    );
    foreach ($logs as $row) {
        $id = (int) $row['item_id'];
        if (isset($facts[$id])) {
            $facts[$id]['log'] = trim((string) ($row['log_text'] ?? ''));
        }
    }
    foreach ($facts as $id => $fact) {
        unset($facts[$id]['user_id']);
    }

    return $facts;
}

/**
 * @param list<int> $ids
 * @return array<int, string>
 */
function workStatusValues(array $ids, int $fieldId): array
{
    $values = [];
    if ($fieldId <= 0 || $ids === []) {
        return $values;
    }
    $stored = Database::rows(
        'SELECT item_id, value FROM item_field_values WHERE custom_field_id = ? AND item_id IN (' . implode(',', $ids) . ')',
        [$fieldId]
    );
    foreach ($stored as $row) {
        $values[(int) $row['item_id']] = (string) ($row['value'] ?? '');
    }

    return $values;
}

function moveWorkCard(?int $itemTypeId): void
{
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? ''));
    $ids = applicationAccessibleItemIds($itemTypeId);
    $field = workStatusField($itemTypeId);
    if ($itemId <= 0 || !in_array($itemId, $ids, true) || $field === null || !in_array($status, $field['values'], true)) {
        workJson(['ok' => false], 422);
    }

    $key = 'custom_field_' . (int) $field['id'];
    $_POST[$key] = $status;
    \Adlexone\Application\Rules::run($itemId, 'update_item');
    ItemFields::saveFromArray($itemId, [$key => $status]);

    $values = workStatusValues($ids, (int) $field['id']);
    $counts = [];
    foreach ($field['values'] as $value) {
        $counts[$value] = 0;
    }
    foreach ($ids as $id) {
        $value = $values[$id] ?? '';
        if ($value !== '' && array_key_exists($value, $counts)) {
            $counts[$value]++;
        }
    }
    $facts = workCardFacts([$itemId]);
    $fact = $facts[$itemId] ?? ['title' => '', 'owner' => '', 'log' => ''];
    $userId = (int) ($_SESSION['access_user_id'] ?? 0);
    $mine = 0;
    if ($userId > 0) {
        $mine = count(Database::select('items', ['item_id'], 'user_security = ? AND item_id IN (' . implode(',', $ids) . ')', [$userId]));
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    workJson([
        'ok' => true,
        'item_id' => $itemId,
        'status' => $status,
        'owner' => $fact['owner'],
        'log' => $fact['log'],
        'counts' => $counts,
        'records' => count($ids),
        'mine' => $mine,
    ]);
}

/**
 * @param array<string, mixed> $payload
 */
function workJson(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function workBoardScript(): string
{
    return '<script>
(function () {
    var board = document.querySelector(".work-board");
    if (!board) return;
    var url = board.getAttribute("data-move") || "";
    var token = board.getAttribute("data-csrf") || "";

    function setCount(key, count) {
        document.querySelectorAll("[data-stand=\"" + key.replace(/"/g, "") + "\"] .work-stand__num, [data-count=\"" + key.replace(/"/g, "") + "\"]").forEach(function (el) {
            el.textContent = String(count);
        });
    }

    function apply(card, data) {
        if (!data || !data.ok) return;
        var column = board.querySelector(".work-board__col[data-status=\"" + cssAttr(data.status) + "\"] .work-board__cards");
        if (column) column.appendChild(card);
        card.setAttribute("data-status", data.status);
        var owner = card.querySelector(".work-card__owner");
        if (owner) {
            owner.textContent = data.owner || "";
            owner.hidden = !data.owner;
        }
        var log = card.querySelector(".work-card__log");
        if (log) {
            log.textContent = data.log || "";
            log.hidden = !data.log;
        }
        var select = card.querySelector("select");
        if (select) {
            select.value = "";
            Array.prototype.forEach.call(select.options, function (option) {
                option.hidden = option.value !== "" && option.value === data.status;
            });
        }
        setCount("records", data.records);
        setCount("mine", data.mine);
        Object.keys(data.counts || {}).forEach(function (status) {
            setCount("status:" + status, data.counts[status]);
        });
    }

    function cssAttr(value) {
        return String(value).replace(/\\\\/g, "\\\\\\\\").replace(/"/g, "\\\\\\"");
    }

    function move(card, status) {
        if (!card || !status || card.getAttribute("data-status") === status) return;
        var body = new URLSearchParams();
        body.set("item_id", card.getAttribute("data-item") || "");
        body.set("status", status);
        body.set("_csrf", token);
        fetch(url, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded", "Accept": "application/json"},
            body: body.toString()
        }).then(function (response) { return response.json(); }).then(function (data) {
            apply(card, data);
        }).catch(function () {});
    }

    board.querySelectorAll(".work-card").forEach(function (card) {
        card.addEventListener("dragstart", function (event) {
            if (event.target.closest("select")) {
                event.preventDefault();
                return;
            }
            event.dataTransfer.setData("text/plain", card.getAttribute("data-item") || "");
            event.dataTransfer.effectAllowed = "move";
            card.classList.add("is-dragging");
        });
        card.addEventListener("dragend", function () {
            card.classList.remove("is-dragging");
        });
        var select = card.querySelector("select");
        if (select) {
            select.addEventListener("change", function () {
                if (select.value) move(card, select.value);
            });
        }
    });

    board.querySelectorAll(".work-board__col[data-status]").forEach(function (column) {
        column.addEventListener("dragover", function (event) {
            event.preventDefault();
            column.classList.add("is-over");
        });
        column.addEventListener("dragleave", function () {
            column.classList.remove("is-over");
        });
        column.addEventListener("drop", function (event) {
            event.preventDefault();
            column.classList.remove("is-over");
            var id = event.dataTransfer.getData("text/plain");
            var card = board.querySelector(".work-card[data-item=\"" + cssAttr(id) + "\"]");
            move(card, column.getAttribute("data-status") || "");
        });
    });
})();
</script>';
}
