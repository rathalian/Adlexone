<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\RecordQuery;
use Adlexone\Application\Rules;
use Adlexone\Application\WorkflowFields;
use Adlexone\Auth\Access;
use Adlexone\Auth\Csrf;
use Adlexone\Auth\Role;
use Adlexone\Database\Database;
use Adlexone\Data\GroupMembership;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;
use Adlexone\Html\Stamp;
use Adlexone\Html\Timeline;
use Adlexone\Http\Page;
use Adlexone\Text\Wording;

/**
 * The column beside a record, and the owner and group controls the sheet shares with it.
 */
final class ItemSheetSide
{
    /**
     * @param array<string, string> $fields
     * @param array<string, mixed> $typeFields
     * @param array<string, mixed> $posted
     * @return array{fields: array<string, string>, hidden: string}
     */
    public static function newPeople(array $fields, array &$typeFields, array $posted, bool $inApplication, bool $canRevise): array
    {
        $hidden = '';
        if ($canRevise) {
            if (!isset($posted['user_security'])) {
                if ((string) ($typeFields['user_security'] ?? '') === '') {
                    $typeFields['user_security'] = $_SESSION['access_user_id'];
                }
                $typeFields['creator_security'] = $_SESSION['access_user_id'];
            }
            if ($inApplication) {
                $hidden = Form::hidden('creator_security', (string) $typeFields['creator_security']);
                $fields['Owner'] = self::ownerSelect((int) ($typeFields['user_security'] ?? 0), '', true);
            } else {
                $userIds = [];
                $userNames = [];
                foreach (Database::select('users', ['user_id', 'user_name'], "lastactive <> 'inactive'", [], 'user_name ASC') as $row) {
                    $userIds[] = $row['user_id'];
                    $userNames[] = $row['user_name'];
                }
                $fields[Wording::get('LA_551', 'TXT_551')] = Form::select('creator_security', $userIds, $userNames, $typeFields['creator_security']);
                $owners = Role::ownerMenu(true);
                $fields[Wording::get('LA_269', 'TXT_269')] = Form::select('user_security', $owners['ids'], $owners['names'], $typeFields['user_security']);
            }
        } elseif ($inApplication) {
            $ownerId = ($typeFields['user_security'] ?? '') === ''
                ? (string) $_SESSION['access_user_id']
                : (string) $typeFields['user_security'];
            $hidden = Form::hidden('creator_security', (string) $_SESSION['access_user_id'])
                . Form::hidden('user_security', $ownerId);
        } else {
            $creatorId = (string) ($_SESSION['access_user_id'] ?? '');
            $fields[Wording::get('LA_551', 'TXT_551')] = ItemSheetFields::lockedValue('creator_security', $creatorId, self::personName((int) $creatorId));
            if ($typeFields['user_security'] == '') {
                $fields[Wording::get('LA_269', 'TXT_269')] = ItemSheetFields::lockedValue('user_security', $creatorId, self::personName((int) $creatorId));
            } else {
                $ownerId = (string) $typeFields['user_security'];
                $fields[Wording::get('LA_269', 'TXT_269')] = ItemSheetFields::lockedValue('user_security', $ownerId, self::personName((int) $ownerId));
            }
        }

        return ['fields' => $fields, 'hidden' => $hidden];
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, mixed> $item
     * @return array{fields: array<string, string>, hidden: string}
     */
    public static function recordPeople(array $fields, array $item, bool $inApplication): array
    {
        if ($inApplication) {
            return [
                'fields' => $fields,
                'hidden' => Form::hidden('creator_security', (string) ($item['creator_security'] ?? ''))
                    . Form::hidden('user_security', (string) ($item['user_security'] ?? '')),
            ];
        }
        if (Access::canAssignRecords()) {
            $userIds = [];
            $userNames = [];
            foreach (Database::select('users', ['user_id', 'user_name'], '', [], 'user_name ASC') as $row) {
                $userIds[] = $row['user_id'];
                $userNames[] = $row['user_name'];
            }
            $fields[Wording::get('LA_551', 'TXT_551')] = Form::select('creator_security', $userIds, $userNames, $item['creator_security']);
            $owners = Role::ownerMenu(false);
            $fields[Wording::get('LA_269', 'TXT_269')] = Form::select('user_security', $owners['ids'], $owners['names'], $item['user_security']);

            return ['fields' => $fields, 'hidden' => ''];
        }
        $creatorValue = (string) ($item['creator_security'] ?? '');
        $ownerValue = (string) ($item['user_security'] ?? '');
        $fields[TXT_551] = ItemSheetFields::lockedValue('creator_security', $creatorValue, self::personName((int) $creatorValue));
        $fields[TXT_269] = ItemSheetFields::lockedValue('user_security', $ownerValue, self::personName((int) $ownerValue));

        return ['fields' => $fields, 'hidden' => ''];
    }

    /**
     * @param list<int> $groupIds
     */
    public static function groupField(int $itemId, array $groupIds): string
    {
        $groupArray = array_values(array_filter(array_map('intval', $groupIds)));
        $membership = '';
        if ($groupArray !== []) {
            $marks = implode(',', array_fill(0, count($groupArray), '?'));
            $names = [];
            foreach (Database::select('groups', ['group_id', 'group_name'], 'group_id IN (' . $marks . ')', $groupArray) as $row) {
                $names[] = (string) $row['group_name'];
            }
            $membership = implode(', ', $names);
        }
        $change = Blocks::link(
            ItemScreen::recordUrl('item=' . $itemId . '&option=change_security'),
            TXT_268,
            '',
            'btn btn--sm'
        );

        return '<div class="criteria-row"><div class="readonly-value">' . Html::e($membership) . '</div>' . $change . '</div>';
    }

    /**
     * @param array<string, mixed> $item
     * @param list<int> $groupIds
     */
    public static function recordSide(int $itemId, array $item, array $groupIds, bool $canWrite = false): string
    {
        $opened = (int) ($item['create_date'] ?? 0);
        $updated = (int) ($item['core_log_updated'] ?? 0);
        $ownerId = (int) ($item['user_security'] ?? 0);
        $creatorId = (int) ($item['creator_security'] ?? 0);
        $names = RecordQuery::userNames([$ownerId, $creatorId]);
        $status = WorkScreen::workStatusField((int) ($item['item_type_id'] ?? 0));
        $stand = $status !== null ? trim((string) ($item['custom_field_' . $status['id']] ?? '')) : '';
        $row = static fn (string $term, string $detail): string => $detail === ''
            ? ''
            : '<div class="facts__row"><dt>' . Html::e($term) . '</dt><dd>' . $detail . '</dd></div>';
        $person = static fn (string $name): string => $name === ''
            ? ''
            : Presence::mark($name) . '<span>' . Html::e($name) . '</span>';
        $when = static fn (int $stamp): string => $stamp > 0
            ? '<span>' . Html::e(Presence::ago($stamp)) . '</span><small>' . Html::e(Stamp::dayTime($stamp)) . '</small>'
            : '';
        $files = Database::count('item_attachments', 'item_id = ?', [$itemId]);
        $groups = self::groupNames($groupIds);
        $ownerRow = $canWrite
            ? '<div class="facts__row facts__row--assign"><dt>Owner</dt><dd>'
                . self::ownerAssign($itemId, $ownerId, $names[$ownerId] ?? '') . '</dd></div>'
            : $row('Owner', $person($names[$ownerId] ?? ''));
        $facts = '<dl class="facts">'
            . $row('Status', $stand !== '' ? Presence::pill($stand) : '')
            . $ownerRow
            . $row('Opened by', $creatorId !== $ownerId ? $person($names[$creatorId] ?? '') : '')
            . $row('Opened', $when($opened))
            . $row('Last change', $updated > $opened ? $when($updated) : '')
            . $row('Visible to', $groups !== '' ? Html::e($groups) : '')
            . $row('Files', $files > 0 ? Html::e(self::countLabel($files, 'file', '')) : '')
            . '</dl>';

        return '<aside class="workspace__side" aria-label="About this record">'
            . self::nextStepCard($item)
            . '<section class="sidecard"><h2 class="sidecard__title">About this record</h2>'
            . self::stageStrip($status['values'] ?? [], $stand) . $facts . '</section>'
            . '<section class="sidecard"><h2 class="sidecard__title">Timeline</h2>'
            . ($canWrite && defined('SET_LOG_ENTRY') && SET_LOG_ENTRY === 'yes' ? self::noteComposer($itemId) : '')
            . Timeline::render(self::events($itemId, $opened, $creatorId, $names), 'Nothing has been written on this record.')
            . '</section></aside>';
    }

    /**
     * @param array<int, string> $names
     * @return list<array{text: string, when: int, who: string, detail?: string, icon: string}>
     */
    private static function events(int $itemId, int $opened, int $creatorId, array $names): array
    {
        $lines = Database::rows(
            'SELECT log_text, CAST(create_date AS INTEGER) AS at, security_id, audience'
            . (Database::columnExists('core_log', 'group_id') ? ', group_id' : '')
            . ' FROM core_log WHERE item_id = ? ORDER BY CAST(create_date AS INTEGER) DESC LIMIT 30',
            [$itemId]
        );
        $authors = RecordQuery::userNames(array_map(static fn (array $line): int => (int) ($line['security_id'] ?? 0), $lines));
        $audiences = [];
        foreach ($lines as $line) {
            $audienceId = (int) ($line['group_id'] ?? 0);
            if ($audienceId > 0) {
                $audiences[$audienceId] = '';
            }
        }
        if ($audiences !== []) {
            $audienceIds = array_map('intval', array_keys($audiences));
            $marks = implode(',', array_fill(0, count($audienceIds), '?'));
            foreach (Database::select('groups', ['group_id', 'group_name'], 'group_id IN (' . $marks . ')', $audienceIds) as $row) {
                $audiences[(int) $row['group_id']] = (string) $row['group_name'];
            }
        }
        $events = [];
        foreach ($lines as $line) {
            if (!GroupMembership::canSeeLogLine((int) ($line['group_id'] ?? 0), (string) ($line['audience'] ?? ''))) {
                continue;
            }
            $text = trim((string) ($line['log_text'] ?? ''));
            if ($text === '' || (int) $line['at'] <= 0) {
                continue;
            }
            $audienceId = (int) ($line['group_id'] ?? 0);
            $events[] = [
                'text' => $text,
                'when' => (int) $line['at'],
                'who' => $authors[(int) ($line['security_id'] ?? 0)] ?? '',
                'detail' => $audienceId > 0 ? 'Visible to ' . ($audiences[$audienceId] ?? '') : '',
                'icon' => 'ic-note',
            ];
        }
        if ($opened > 0) {
            $events[] = [
                'text' => 'Record opened',
                'when' => $opened,
                'who' => $names[$creatorId] ?? '',
                'icon' => 'ic-file-plus',
            ];
        }

        return $events;
    }

    private static function ownerAssign(int $itemId, int $ownerId, string $ownerName): string
    {
        $scarce = count(Rules::people()) < 2;

        return '<form class="owner-assign" method="post" action="' . Html::e(ItemScreen::recordUrl('option=update_owner')) . '">'
            . Csrf::field()
            . Form::hidden('item_id', (string) $itemId)
            . '<label class="sr-only" for="record-owner">Owner</label>'
            . self::ownerSelect($ownerId, $ownerName, false, 'id="record-owner"')
            . '<button class="btn btn--primary btn--sm" type="submit">Assign</button>'
            . ($scarce ? '<p class="owner-assign__hint">Add a person under People and groups.</p>' : '')
            . '</form>';
    }

    public static function ownerSelect(int $ownerId, string $ownerName = '', bool $hint = false, string $attributes = ''): string
    {
        $people = Rules::people();
        $values = ['0'];
        $labels = ['No owner'];
        if ($ownerId > 0 && !isset($people[$ownerId])) {
            $people[$ownerId] = $ownerName !== '' ? $ownerName : ('Owner ' . $ownerId);
        }
        foreach ($people as $id => $name) {
            $values[] = (string) $id;
            $labels[] = $name;
        }
        $html = Form::select('user_security', $values, $labels, (string) $ownerId, $attributes);
        if ($hint && count($people) < 2) {
            $html .= '<p class="form-help">Add a person under People and groups.</p>';
        }

        return $html;
    }

    private static function noteComposer(int $itemId): string
    {
        $groupIds = ['0'];
        $groupLabels = ['Everyone'];
        foreach (Rules::groups() as $id => $name) {
            $groupIds[] = (string) $id;
            $groupLabels[] = $name;
        }

        return '<form class="composer" method="post" action="' . Html::e(ItemScreen::recordUrl('option=add_log_entry')) . '">'
            . Csrf::field()
            . Form::hidden('item_id', (string) $itemId) . Form::hidden('from_timeline', '1')
            . '<label class="sr-only" for="composer-note">Add a note to the timeline</label>'
            . '<textarea class="textarea composer__input" id="composer-note" name="log_entry" rows="2" placeholder="Add a note"></textarea>'
            . '<div class="composer__bar">'
            . '<label class="composer__audience">Visible to ' . Form::select('group_id', $groupIds, $groupLabels, '0') . '</label>'
            . '<button class="btn btn--primary btn--sm composer__send" type="submit" name="submit_button" value="Add">Add note</button>'
            . '</div></form>';
    }

    /**
     * @param list<string> $values
     */
    private static function stageStrip(array $values, string $current): string
    {
        $values = array_values(array_filter(array_map('trim', $values), static fn (string $value): bool => $value !== ''));
        $at = array_search($current, $values, true);
        if ($at === false || count($values) < 2 || count($values) > 8) {
            return '';
        }
        $html = '<ol class="stages" aria-label="Stage">';
        foreach ($values as $index => $value) {
            $state = $index < $at ? ' is-done' : ($index === $at ? ' is-current' : '');
            $html .= '<li class="stages__step' . $state . '"' . ($index === $at ? ' aria-current="step"' : '') . '>'
                . '<span class="stages__label">' . Html::e($value) . '</span></li>';
        }

        return $html . '</ol>';
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function nextStepCard(array $item): string
    {
        $typeId = (int) ($item['item_type_id'] ?? 0);
        if ($typeId <= 0) {
            return '';
        }
        $slug = Page::current()->inApplication() ? Page::current()->applicationSlug() : '';
        $field = WorkflowFields::resolve($slug, $typeId, WorkflowFields::STEP);
        if ($field === null) {
            return '';
        }
        $text = trim((string) ($item['custom_field_' . $field['id']] ?? ''));

        return '<section class="sidecard sidecard--next"><h2 class="sidecard__title">Next step</h2>'
            . ($text !== ''
                ? '<p class="nextstep">' . Html::e($text) . '</p>'
                : '<p class="nextstep nextstep--none">No next step. Enter one in the form.</p>')
            . '</section>';
    }

    /**
     * @param list<int> $ids
     */
    public static function groupNames(array $ids): string
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return '';
        }
        $names = [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        foreach (Database::select('groups', ['group_name'], 'group_id IN (' . $marks . ')', $ids, 'group_name ASC') as $row) {
            $names[] = (string) $row['group_name'];
        }

        return implode(', ', $names);
    }

    /**
     * @param list<int> $selectedIds
     */
    public static function groupPicker(array $selectedIds): string
    {
        $selected = array_map('strval', $selectedIds);
        $rows = Database::select('groups', ['group_id', 'group_name', 'description'], '', [], 'group_name ASC');
        $list = '<div class="group-security-list">';
        foreach ($rows as $row) {
            $groupId = (string) $row['group_id'];
            $checked = in_array($groupId, $selected, true) ? $groupId : '';
            $list .= '<div class="group-security-item">'
                . Form::checkbox('group_' . $groupId, $groupId, $checked, (string) $row['group_name']);
            $description = trim((string) ($row['description'] ?? ''));
            if ($description !== '') {
                $list .= '<div class="record-list__meta">' . Html::e($description) . '</div>';
            }
            $list .= '</div>';
        }
        if ($rows === []) {
            $list .= '<p class="record-list__empty">No groups yet.</p>';
        }
        $list .= Form::hidden('groups_posted', '1');
        $list .= '</div>';

        return $list;
    }

    private static function personName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        $row = Database::first('users', ['user_name'], 'user_id = ?', [$userId]);

        return trim((string) ($row['user_name'] ?? ''));
    }

    private static function countLabel(int $count, string $noun, string $none): string
    {
        if ($count <= 0) {
            return $none;
        }

        return $count === 1 ? '1 ' . $noun : $count . ' ' . $noun . 's';
    }
}
