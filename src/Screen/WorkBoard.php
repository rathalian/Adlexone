<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Http\Request;

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\RecordQuery;
use Adlexone\Application\ScreenCatalog;
use Adlexone\Application\WorkflowFields;
use Adlexone\Auth\Csrf;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Database\Database;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\FieldValidation;
use Adlexone\Http\Page;
use Adlexone\Http\Router;
use Adlexone\Html\Blocks;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;


final class WorkBoard
{


    /**
     * Status columns for the same records as the list. Dragging a card sets that status and runs rules.
     *
     * @param list<int> $ids
     * @param array{html: string, field_id: int, values: array<int, string>} $stand
     */
    public static function workBoard(array $ids, ?int $itemTypeId, string $base, array $stand): string
    {
        $status = WorkScreen::workStatusField($itemTypeId);
        if ($status === null || $status['values'] === [] || $ids === []) {
            return '';
        }
        $fieldId = (int) $status['id'];
        $values = $stand['field_id'] === $fieldId ? $stand['values'] : WorkScreen::workStatusValues($ids, $fieldId);
        $columns = [];
        foreach ($status['values'] as $value) {
            $columns[$value] = [];
        }
        $unset = [];
        $facts = WorkScreen::workCardFacts($ids);
        $wait = WorkScreen::waitContext($itemTypeId);
        $dueValues = $wait['field_id'] > 0 ? RecordQuery::values($ids, $wait['field_id']) : [];
        foreach ($ids as $id) {
            $value = $values[$id] ?? '';
            $fact = $facts[$id] ?? ['title' => '', 'owner' => '', 'log' => '', 'detail' => '', 'next' => ''];
            $rawDue = trim((string) ($dueValues[$id] ?? ''));
            $card = self::workCard($id, $fact, $base, $value, $status['values'], $rawDue === '' ? '' : FieldValidation::formatDate($rawDue));
            if (isset($columns[$value])) {
                $columns[$value][] = $card;
            } else {
                $unset[] = $card;
            }
        }

        $html = '';
        foreach ($columns as $value => $cards) {
            $html .= self::workColumn((string) $value, $cards, true);
        }
        if ($unset !== []) {
            $html .= self::workColumn('Not set', $unset, false);
        }

        $move = Html::e($base . '&option=move_card');
        $token = Html::e(Csrf::token());
        $waitAttr = $wait['waits'] === []
            ? ''
            : ' data-waits="' . Html::e(implode('|', $wait['waits'])) . '" data-due-label="' . Html::e($wait['label'])
                . '" data-due-example="' . Html::e($wait['example']) . '"';
        $dialog = $wait['waits'] === []
            ? ''
            : '<dialog class="due-ask" data-due-ask><form method="dialog" class="due-ask__form">'
                . '<h2 class="due-ask__title">' . Html::e($wait['label']) . '</h2>'
                . '<p class="due-ask__copy">Enter the date this is due.</p>'
                . '<label class="label" for="work-due-date">' . Html::e($wait['label']) . '</label>'
                . '<input class="input" id="work-due-date" type="text" name="due" inputmode="numeric" autocomplete="off" spellcheck="false" placeholder="'
                . Html::e($wait['example']) . '" data-due-input>'
                . '<p class="form-help">Site format: ' . Html::e($wait['example']) . '.</p>'
                . '<div class="due-ask__actions"><button class="btn btn--quiet" type="button" value="cancel" data-due-cancel>Cancel</button>'
                . '<button class="btn btn--primary" type="submit" value="ok" data-due-ok>Move</button></div>'
                . '</form></dialog>';

        Page::current()->addScript('work-board');

        return '<section class="work-board" aria-label="Board" data-move="' . $move . '" data-csrf="' . $token . '"' . $waitAttr . '>'
            . $html
            . $dialog
            . '</section>';
    }


    /**
     * @param list<string> $cards
     */
    private static function workColumn(string $label, array $cards, bool $drop): string
    {
        $dropAttr = $drop ? ' data-status="' . Html::e($label) . '"' : '';

        $tone = Presence::tone($drop ? $label : '');
        $state = Presence::stateClass($label);

        return '<div class="work-board__col" data-tone="' . Html::e($tone) . '"'
            . ($state !== '' ? ' data-state="' . Html::e($state) . '"' : '') . $dropAttr . '>'
            . '<div class="work-board__head"><span class="work-board__label"><span class="work-board__dot" aria-hidden="true"></span>'
            . Html::e($label) . '</span>'
            . '<span class="work-board__count">' . count($cards) . '</span></div>'
            . '<div class="work-board__cards">' . implode('', $cards) . '</div>'
            . '</div>';
    }


    /**
     * @param array{title: string, owner: string, log: string, detail?: string, next?: string} $fact
     * @param list<string> $statuses
     */
    private static function workCard(int $itemId, array $fact, string $base, string $status, array $statuses, string $due = ''): string
    {
        $title = $fact['title'] !== '' ? $fact['title'] : ('Record ' . $itemId);
        $href = Html::e($base . '&item=' . $itemId);
        $titleEsc = Html::e($title);
        $detail = trim((string) ($fact['detail'] ?? ''));
        $next = trim((string) ($fact['next'] ?? ''));
        if ($next === '') {
            $next = trim((string) $fact['log']);
        }
        $owner = trim((string) $fact['owner']);
        $detailHtml = $detail !== ''
            ? '<p class="work-card__detail">' . Html::e($detail) . '</p>'
            : '';
        $nextHtml = $next !== ''
            ? '<p class="work-card__log">' . Html::e($next) . '</p>'
            : '<p class="work-card__log" hidden></p>';
        $ownerHtml = '<p class="work-card__owner"' . ($owner === '' ? ' hidden' : '') . '>'
            . ($owner !== '' ? Presence::mark($owner) : '')
            . '<span class="work-card__owner-name">' . Html::e($owner) . '</span></p>';
        $options = '<option value="">Move to</option>';
        foreach ($statuses as $value) {
            if ($value === $status) {
                continue;
            }
            $options .= '<option value="' . Html::e($value) . '">' . Html::e($value) . '</option>';
        }

        $dueAttr = $due !== '' ? ' data-due="' . Html::e($due) . '"' : '';

        return '<article class="work-card" draggable="true" data-item="' . $itemId . '" data-status="' . Html::e($status) . '"' . $dueAttr . '>'
            . '<a class="work-card__title" draggable="false" href="' . $href . '">' . $titleEsc . '</a>'
            . $detailHtml
            . $nextHtml
            . $ownerHtml
            . '<label class="work-card__move">Move <select aria-label="Move ' . $titleEsc . '">' . $options . '</select></label>'
            . '</article>';
    }


    public static function moveWorkCard(?int $itemTypeId): void
    {
        $itemId = (int) (Request::postedValue('item_id') ?? 0);
        $status = trim((string) (Request::postedValue('status') ?? ''));
        $ids = WorkScreen::applicationAccessibleItemIds($itemTypeId);
        $field = WorkScreen::workStatusField($itemTypeId);
        if ($itemId <= 0 || !in_array($itemId, $ids, true) || $field === null || !in_array($status, $field['values'], true)) {
            WorkScreen::workJson(['ok' => false], 422);
        }

        $key = 'custom_field_' . (int) $field['id'];
        $dueWrite = self::dueForMove($itemTypeId, $status);
        if ($dueWrite['error'] !== '') {
            WorkScreen::workJson(['ok' => false, 'error' => $dueWrite['error']], 422);
        }
        Request::setPosted($key, $status);
        $payload = [$key => $status];
        if ($dueWrite['key'] !== '') {
            Request::setPosted($dueWrite['key'], $dueWrite['value']);
            $payload[$dueWrite['key']] = $dueWrite['value'];
        }
        \Adlexone\Application\Rules::run($itemId, 'update_item');
        ItemFields::saveFromArray($itemId, $payload);

        $values = WorkScreen::workStatusValues($ids, (int) $field['id']);
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
        $facts = WorkScreen::workCardFacts([$itemId]);
        $fact = $facts[$itemId] ?? ['title' => '', 'owner' => '', 'log' => '', 'detail' => '', 'next' => ''];
        $line = trim((string) ($fact['next'] ?? ''));
        if ($line === '') {
            $line = (string) ($fact['log'] ?? '');
        }
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $mine = 0;
        if ($userId > 0) {
            $mine = count(Database::select('items', ['item_id'], 'user_security = ? AND item_id IN (' . implode(',', $ids) . ')', [$userId]));
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        WorkScreen::workJson([
            'ok' => true,
            'item_id' => $itemId,
            'status' => $status,
            'owner' => $fact['owner'],
            'log' => $line,
            'counts' => $counts,
            'records' => count($ids),
            'mine' => $mine,
        ]);
    }


    public static function moveWorkCards(?int $itemTypeId): void
    {
        $posted = RecordQuery::ints(Request::postedValue('item_ids') ?? []);
        $accessible = WorkScreen::applicationAccessibleItemIds($itemTypeId);
        $ids = array_values(array_intersect($posted, $accessible));
        $status = trim((string) (Request::postedValue('status') ?? ''));
        $field = WorkScreen::workStatusField($itemTypeId);
        if ($ids === [] || $field === null || !in_array($status, $field['values'], true)) {
            header('Location: ' . WorkScreen::place());
            exit;
        }
        $dueWrite = self::dueForMove($itemTypeId, $status);
        if ($dueWrite['error'] !== '') {
            \Adlexone\Http\View::flash($dueWrite['error'], 'danger');
            header('Location: ' . WorkScreen::place());
            exit;
        }
        $key = 'custom_field_' . (int) $field['id'];
        foreach ($ids as $itemId) {
            Request::setPosted($key, $status);
            $payload = [$key => $status];
            if ($dueWrite['key'] !== '') {
                Request::setPosted($dueWrite['key'], $dueWrite['value']);
                $payload[$dueWrite['key']] = $dueWrite['value'];
            }
            \Adlexone\Application\Rules::run($itemId, 'update_item');
            ItemFields::saveFromArray($itemId, $payload);
        }

        header('Location: ' . WorkScreen::place());
        exit;
    }


    /**
     * @return array{key: string, value: string, error: string}
     */
    private static function dueForMove(?int $itemTypeId, string $status): array
    {
        $none = ['key' => '', 'value' => '', 'error' => ''];
        $typeId = (int) $itemTypeId;
        $slug = WorkScreen::applicationSlug();
        $waits = WorkflowFields::waitStatuses($slug, $typeId);
        if (!in_array($status, $waits, true)) {
            return $none;
        }
        $due = WorkflowFields::resolve($slug, $typeId, WorkflowFields::DUE);
        if ($due === null) {
            return $none;
        }
        $raw = trim((string) (Request::postedValue('due') ?? ''));
        if (FieldValidation::parseDate($raw, false) === null) {
            return ['key' => '', 'value' => '', 'error' => 'Enter ' . $due['label'] . '.'];
        }

        return [
            'key' => 'custom_field_' . (int) $due['id'],
            'value' => FieldValidation::formatDate($raw),
            'error' => '',
        ];
    }
}
