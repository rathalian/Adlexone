<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Auth\Role;
use Adlexone\Database\RecordListQuery;
use Adlexone\Auth\Permission;
use Adlexone\Application\ApplicationAccess;
use Adlexone\Application\ApplicationSettings;
use Adlexone\Application\RecordQuery;
use Adlexone\Application\Rules;
use Adlexone\Application\WorkflowFields;
use Adlexone\Auth\Csrf;
use Adlexone\Database\Database;
use Adlexone\Application\Actions;
use Adlexone\Data\AttachmentUpload;
use Adlexone\Html\FieldControl;
use Adlexone\Data\FieldCheck;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\FieldValidation;
use Adlexone\Data\MenuOptions;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Http\Router;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Text\Wording;
use Adlexone\Html\Html;

final class ItemSheet
{
    /** @var array<string, string> */
    public static array $formErrors = [];

    private static function sheetFieldLabel(string $name): string
    {
        $plain = WorkScreen::plainFieldName($name);
        if ($plain === '') {
            return $name;
        }

        return ucfirst($plain);
    }

    private static function menuHtml(int $typeId, int $fieldId, string $stored, string $childStored, array $request): string
    {
        $choices = WorkflowFields::scopedStatusValues($typeId, $fieldId);
        if ($choices !== null) {
            if ($stored !== '' && !in_array($stored, $choices, true)) {
                $choices[] = $stored;
            }
            $html = Form::select('custom_field_' . $fieldId, $choices, $choices, $stored);
        } else {
            $html = MenuOptions::controls($fieldId, $stored, $childStored, $request);
        }

        return self::markWait($typeId, $fieldId, $html);
    }

    private static function markWait(int $typeId, int $fieldId, string $html): string
    {
        if ($html === '' || $typeId <= 0 || !Page::current()->inApplication()) {
            return $html;
        }
        $slug = Page::current()->applicationSlug();
        $status = WorkflowFields::resolve($slug, $typeId, WorkflowFields::STATUS);
        $due = WorkflowFields::resolve($slug, $typeId, WorkflowFields::DUE);
        if ($status === null || $due === null) {
            return $html;
        }
        $waits = WorkflowFields::waitStatuses($slug, $typeId);
        if ($waits === []) {
            return $html;
        }
        if ($fieldId === (int) $status['id']) {
            $html = preg_replace(
                '/<select\b/',
                '<select data-waits="' . Html::e(implode('|', $waits)) . '" data-due-name="custom_field_' . (int) $due['id'] . '"',
                $html,
                1
            ) ?? $html;
            Page::current()->addScript('work-board');
        }
        if ($fieldId === (int) $due['id']) {
            $html = preg_replace('/<input\b/', '<input data-due-field="1"', $html, 1) ?? $html;
            Page::current()->addScript('work-board');
        }

        return $html;
    }

/**
     * @param array<string, mixed> $formOptions
     * @return array<string, mixed>
     */
    private static function withFormErrors(array $formOptions): array
    {
        if (self::$formErrors === []) {
            return $formOptions;
        }
        $formOptions['errors'] = self::$formErrors;
        $formOptions['error_title'] = defined('TXT_468') ? (string) TXT_468 : 'Check the form';
        return $formOptions;
    }

/**
     * Access, files, and notes as buttons. Each opens a dialog.
     * A new record keeps the controls in the create form. An existing record saves each one on its own.
     *
     * @param list<int> $selectedGroups
     * @return array{lead: string, inside: string, after: string, multipart: bool}
     */
    private static function recordTools(int $itemId, int $itemTypeId, bool $canRevise, array $selectedGroups, string $draftNote = ''): array
    {
        $existing = $itemId > 0;
        $canEdit = $canRevise;
        $buttons = '';
        $inside = '';
        $after = '';
        $multipart = false;
        $place = static function (string $dialog, bool $embed) use (&$inside, &$after): void {
            if ($embed) {
                $inside .= $dialog;
            } else {
                $after .= $dialog;
            }
        };

        $shared = count($selectedGroups);
        $accessMeta = self::countLabel($shared, 'group', 'Not shared');
        if ($canEdit) {
            $accessBody = self::itemGroupPicker($selectedGroups);
        } else {
            $names = self::groupNames($selectedGroups);
            $accessBody = '<div class="readonly-value">' . Html::e($names === '' ? 'Not shared.' : $names) . '</div>';
            if ($names === '') {
                $accessMeta = 'Not shared';
            }
        }
        $buttons .= self::recordToolButton('access', 'Access', $accessMeta);
        $embedAccess = !$existing && $canEdit;
        if ($existing && $canEdit) {
            $accessDialog = self::recordDialog('access', 'Access', $accessBody, [
                'action' => ItemScreen::recordUrl('option=update_item'),
                'submit' => 'Save access',
                'hidden' => Form::hidden('item_id', (string) $itemId)
                    . Form::hidden('item_type_id', (string) $itemTypeId)
                    . Form::hidden('groups_only', '1'),
            ]);
        } else {
            $accessDialog = self::recordDialog(
                'access',
                'Access',
                $accessBody,
                null,
                $embedAccess ? 'Saved when you create the record.' : '',
                $embedAccess
            );
        }
        $place($accessDialog, $embedAccess);

        if ($canEdit && ADD_ATTACHMENTS == 'yes') {
            $fileCount = ($existing && SET_ATTACHMENTS === 'yes')
                ? Database::count('item_attachments', 'item_id = ?', [$itemId])
                : 0;
            $list = '';
            if ($existing) {
                $list = $fileCount > 0
                    ? ItemFiles::attachmentLines((string) $itemId)
                    : '<p class="record-list__empty">No files yet.</p>';
            }
            $buttons .= self::recordToolButton('files', 'Files', self::countLabel($fileCount, 'file', 'None'));
            $sizeNote = 'Maximum size is ' . (int) SET_MAX_ATTACHMENT . ' MB.';
            if ($existing) {
                $filesDialog = self::recordDialog('files', 'Files', self::filesBody($list, true), [
                    'action' => ItemScreen::recordUrl('item=' . $itemId . '&option=add_attachment'),
                    'enctype' => 'multipart/form-data',
                    'submit' => 'Add file',
                ], $sizeNote);
                $place($filesDialog, false);
            } else {
                $multipart = true;
                $filesDialog = self::recordDialog(
                    'files',
                    'Files',
                    self::filesBody($list, true),
                    null,
                    'Saved when you create the record. ' . $sizeNote,
                    true
                );
                $place($filesDialog, true);
            }
        } elseif ($existing && SET_ATTACHMENTS === 'yes') {
            $fileCount = Database::count('item_attachments', 'item_id = ?', [$itemId]);
            if ($fileCount > 0) {
                $buttons .= self::recordToolButton('files', 'Files', self::countLabel($fileCount, 'file', 'None'));
                $place(self::recordDialog('files', 'Files', ItemFiles::attachmentLines((string) $itemId), null), false);
            }
        }

        if (SET_LOG_ENTRY === 'yes') {
            $entries = '';
            $noteMeta = 'None';
            $visible = false;
            if ($existing) {
                $entries = ItemLog::logEntriesHtml((string) $itemId, '', false);
                $visible = !str_contains($entries, 'class="record-list__empty"');
                if (!$visible) {
                    $entries = '<p class="record-list__empty">No notes yet.</p>';
                }
                $noteCount = Database::count('core_log', 'item_id = ?', [$itemId]);
                if ($noteCount === 1) {
                    $latest = Database::first(
                        'core_log',
                        ['log_text'],
                        'item_id = ? ORDER BY log_item_sequence DESC',
                        [$itemId]
                    );
                    $text = trim((string) preg_replace('/\s+/', ' ', (string) ($latest['log_text'] ?? '')));
                    $noteMeta = ($text !== '' && mb_strlen($text) <= 42) ? $text : '1 note';
                } elseif ($noteCount > 1) {
                    $noteMeta = $noteCount . ' notes';
                }
            } else {
                $noteText = trim((string) preg_replace('/\s+/', ' ', $draftNote));
                $noteMeta = $noteText === '' ? 'None' : (mb_strlen($noteText) <= 42 ? $noteText : '1 note');
            }
            if ($canEdit || ($existing && $visible)) {
                $buttons .= self::recordToolButton('notes', 'Notes', $noteMeta);
                $embedNotes = !$existing && $canEdit;
                if ($existing && $canEdit) {
                    $notesDialog = self::recordDialog('notes', 'Notes', self::notesBody($entries, '', true), [
                        'action' => ItemScreen::recordUrl('option=add_log_entry'),
                        'submit' => 'Add note',
                        'hidden' => Form::hidden('item_id', (string) $itemId),
                    ]);
                } else {
                    $notesDialog = self::recordDialog(
                        'notes',
                        'Notes',
                        self::notesBody($existing ? $entries : '', $draftNote, $canEdit),
                        null,
                        $embedNotes ? 'Saved when you create the record.' : '',
                        $embedNotes
                    );
                }
                $place($notesDialog, $embedNotes);
            }
        }

        $lead = $buttons === ''
            ? ''
            : '<div class="record-tools" role="group" aria-label="Access, files, and notes">' . $buttons . '</div>';

        return [
            'lead' => $lead,
            'inside' => $inside,
            'after' => $after,
            'multipart' => $multipart,
        ];
    }

    private static function recordToolButton(string $id, string $title, string $meta): string
    {
        $dialogId = 'sheet-pop-' . $id;

        return '<button type="button" class="record-tool" data-sheet-pop aria-haspopup="dialog" aria-controls="' . Html::e($dialogId) . '">'
            . '<span class="record-tool__title">' . Html::e($title) . '</span>'
            . '<span class="record-tool__meta" data-meta="' . Html::e($meta) . '">' . Html::e($meta) . '</span>'
            . '</button>';
    }

/**
     * @param array{action: string, submit: string, hidden?: string, enctype?: string}|null $form
     */
    private static function recordDialog(string $id, string $title, string $body, ?array $form, string $note = '', bool $done = false): string
    {
        $dialogId = 'sheet-pop-' . $id;
        $titleId = $dialogId . '-title';
        $close = '<button type="button" class="btn btn--quiet btn--sm sheet-pop__close" data-sheet-pop-close>Close</button>';
        $head = '<div class="sheet-pop__head"><h2 class="sheet-pop__title" id="' . Html::e($titleId) . '">'
            . Html::e($title) . '</h2>' . $close . '</div>';
        $noteHtml = $note !== '' ? '<p class="field-note sheet-pop__note">' . Html::e($note) . '</p>' : '';
        $inner = $head . '<div class="sheet-pop__body">' . $body . $noteHtml . '</div>';
        if ($form !== null) {
            $enctype = (string) ($form['enctype'] ?? '');
            $inner .= '<div class="sheet-pop__actions">' . Form::button('submit', 'submit_button', (string) $form['submit']) . '</div>';
            $inner = '<form class="sheet-pop__panel" method="post" action="' . Html::e((string) $form['action']) . '"'
                . ($enctype !== '' ? ' enctype="' . Html::e($enctype) . '"' : '')
                . '>'
                . \Adlexone\Auth\Csrf::field()
                . (string) ($form['hidden'] ?? '')
                . $inner
                . '</form>';
        } else {
            if ($done) {
                $inner .= '<div class="sheet-pop__actions"><button type="button" class="btn btn--primary" data-sheet-pop-close>Done</button></div>';
            }
            $inner = '<div class="sheet-pop__panel">' . $inner . '</div>';
        }

        return '<dialog class="sheet-pop" id="' . Html::e($dialogId) . '" aria-labelledby="' . Html::e($titleId) . '">'
            . $inner
            . '</dialog>';
    }

    private static function filesBody(string $list, bool $canWrite): string
    {
        $html = $list;
        if ($canWrite) {
            $file = Form::file('attachment', (string) ((int) SET_MAX_ATTACHMENT * 1000000));
            $file = preg_replace('/<input name="attachment"/', '<input id="sheet-file" name="attachment"', $file, 1) ?? $file;
            $html .= '<label class="label" for="sheet-file">File</label>' . $file;
        }

        return $html;
    }

    private static function notesBody(string $entries, string $draft, bool $canWrite): string
    {
        $html = $entries;
        if ($canWrite) {
            $area = Form::textarea('log_entry', $draft, '4');
            $area = preg_replace('/<textarea\b/', '<textarea id="sheet-note"', $area, 1) ?? $area;
            $html .= '<label class="label" for="sheet-note">Add a note</label>' . $area;
        }

        return $html;
    }

/**
     * The column beside a record: who and when at a glance, then what has happened to it.
     *
     * @param array<string, mixed> $item
     * @param list<int> $groupIds
     */
    private static function recordSide(int $itemId, array $item, array $groupIds, bool $canWrite = false): string
    {
        $opened = (int) ($item['create_date'] ?? 0);
        $updated = (int) ($item['core_log_updated'] ?? 0);
        $ownerId = (int) ($item['user_security'] ?? 0);
        $creatorId = (int) ($item['creator_security'] ?? 0);
        $names = \Adlexone\Application\RecordQuery::userNames([$ownerId, $creatorId]);
        $status = WorkScreen::workStatusField((int) ($item['item_type_id'] ?? 0));
        $stand = $status !== null ? trim((string) ($item['custom_field_' . $status['id']] ?? '')) : '';

        $row = static fn (string $term, string $detail): string => $detail === ''
            ? ''
            : '<div class="facts__row"><dt>' . Html::e($term) . '</dt><dd>' . $detail . '</dd></div>';
        $person = static fn (string $name): string => $name === ''
            ? ''
            : \Adlexone\Html\Presence::mark($name) . '<span>' . Html::e($name) . '</span>';
        $when = static fn (int $stamp): string => $stamp > 0
            ? '<span>' . Html::e(\Adlexone\Html\Presence::ago($stamp)) . '</span><small>' . Html::e(\Adlexone\Html\Stamp::dayTime($stamp)) . '</small>'
            : '';
        $files = Database::count('item_attachments', 'item_id = ?', [$itemId]);
        $groups = self::groupNames($groupIds);

        $ownerRow = $canWrite
            ? '<div class="facts__row facts__row--assign"><dt>Owner</dt><dd>'
                . self::ownerAssign($itemId, $ownerId, $names[$ownerId] ?? '') . '</dd></div>'
            : $row('Owner', $person($names[$ownerId] ?? ''));
        $facts = '<dl class="facts">'
            . $row('Status', $stand !== '' ? \Adlexone\Html\Presence::pill($stand) : '')
            . $ownerRow
            . $row('Opened by', $creatorId !== $ownerId ? $person($names[$creatorId] ?? '') : '')
            . $row('Opened', $when($opened))
            . $row('Last change', $updated > $opened ? $when($updated) : '')
            . $row('Visible to', $groups !== '' ? Html::e($groups) : '')
            . $row('Files', $files > 0 ? Html::e(self::countLabel($files, 'file', '')) : '')
            . '</dl>';

        $events = [];
        $lines = Database::rows(
            'SELECT log_text, CAST(create_date AS INTEGER) AS at, security_id, audience'
            . (Database::columnExists('core_log', 'group_id') ? ', group_id' : '')
            . ' FROM core_log WHERE item_id = ? ORDER BY CAST(create_date AS INTEGER) DESC LIMIT 30',
            [$itemId]
        );
        $authors = \Adlexone\Application\RecordQuery::userNames(array_map(static fn (array $line): int => (int) ($line['security_id'] ?? 0), $lines));
        $audiences = [];
        foreach ($lines as $line) {
            $audienceId = (int) ($line['group_id'] ?? 0);
            if ($audienceId > 0) {
                $audiences[$audienceId] = '';
            }
        }
        if ($audiences !== []) {
            foreach (Database::select('groups', ['group_id', 'group_name'], 'group_id IN (' . implode(',', array_map('intval', array_keys($audiences))) . ')') as $row) {
                $audiences[(int) $row['group_id']] = (string) $row['group_name'];
            }
        }
        foreach ($lines as $line) {
            if (!\Adlexone\Data\GroupMembership::canSeeLogLine((int) ($line['group_id'] ?? 0), (string) ($line['audience'] ?? ''))) {
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

        return '<aside class="workspace__side" aria-label="About this record">'
            . self::nextStepCard($item)
            . '<section class="sidecard"><h2 class="sidecard__title">About this record</h2>'
            . self::stageStrip($status['values'] ?? [], $stand) . $facts . '</section>'
            . '<section class="sidecard"><h2 class="sidecard__title">Timeline</h2>'
            . ($canWrite && defined('SET_LOG_ENTRY') && SET_LOG_ENTRY === 'yes' ? self::noteComposer($itemId) : '')
            . \Adlexone\Html\Timeline::render($events, 'Nothing has been written on this record.') . '</section>'
            . '</aside>';
    }

/**
     * A short form that sets the owner and runs the update rules.
     */
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

/**
     * People who can hold a record, with no owner as the first choice.
     */
    private static function ownerSelect(int $ownerId, string $ownerName = '', bool $hint = false, string $attributes = ''): string
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
        foreach (\Adlexone\Application\Rules::groups() as $id => $name) {
            $groupIds[] = (string) $id;
            $groupLabels[] = $name;
        }

        return '<form class="composer" method="post" action="' . Html::e(ItemScreen::recordUrl('option=add_log_entry')) . '">'
            . \Adlexone\Auth\Csrf::field()
            . Form::hidden('item_id', (string) $itemId) . Form::hidden('from_timeline', '1')
            . '<label class="sr-only" for="composer-note">Add a note to the timeline</label>'
            . '<textarea class="textarea composer__input" id="composer-note" name="log_entry" rows="2" placeholder="Add a note"></textarea>'
            . '<div class="composer__bar">'
            . '<label class="composer__audience">Visible to ' . Form::select('group_id', $groupIds, $groupLabels, '0') . '</label>'
            . '<button class="btn btn--primary btn--sm composer__send" type="submit" name="submit_button" value="Add">Add note</button>'
            . '</div></form>';
    }

/**
     * The status values in order, with the record's own marked.
     *
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
     * What happens next on the record, taken from its Next step field.
     *
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

    private static function countLabel(int $count, string $noun, string $none): string
    {
        if ($count <= 0) {
            return $none;
        }

        return $count === 1 ? '1 ' . $noun : $count . ' ' . $noun . 's';
    }

/**
     * @param list<int> $ids
     */
    private static function groupNames(array $ids): string
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return '';
        }
        $names = [];
        foreach (Database::select('groups', ['group_name'], 'group_id IN (' . implode(',', $ids) . ')', [], 'group_name ASC') as $row) {
            $names[] = (string) $row['group_name'];
        }

        return implode(', ', $names);
    }

/**
     * Checkbox list of groups. Checked ids are the item type's groups, or the boxes the person already ticked.
     *
     * @param list<int> $selectedIds
     */
    public static function itemGroupPicker(array $selectedIds): string
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


/**
     * Displays the "Add Item" form for a given item type and populates values.
     *
     * This function is responsible for rendering a form to add a new item of a specific type.
     * - On the first load (when `$values['user_security']` is not set), it retrieves default values
     *   from the database for the specified item type.
     * - If the form is being reloaded (e.g., after a validation error), it uses the `$values` array
     *   (typically `$_POST` or a session-stored array) to pre-populate the form fields.
     * - The form is built using modern, semantic HTML with `Form` helpers for inputs, dropdowns,
     *   and buttons, ensuring accessibility and user-friendliness.
     *
     * **Security Considerations:**
     * - The function ensures that only authorized users can access and modify certain fields based on
     *   their role.
     * - Group membership and user-specific security settings are applied to restrict access to sensitive fields.
     *
     * **Notes:**
     * - `$itemTypeID` is expected to be a valid identifier for the item type (integer or string).
     * - `$values` should be an array-like structure containing form field keys and their respective values.
     * - The function uses prepared statements for database queries to prevent SQL injection.
     *
     * @param mixed $itemTypeID Identifier for the item type (int|string).
     * @param array $values Array of submitted or default values for the form fields.
     * @return void Renders the form and exits by rendering the theme page later in the function.
     */
    public static function showItemAdd($itemTypeID, $values)
    {
        if (!ItemScreen::applicationAllowsItemType((int) $itemTypeID)) {
            Access::deny();
        }
        // Show values from database only if they have not been set via a form refresh (we use user_security as it is always set)
        if (!isset($values['user_security'])) {
            // Setup item type information for display and later use when the item is first created
            $columnArray = array('*');
            $condition = 'WHERE item_type_id = ' . (int) $itemTypeID;
            $itemTypeFields = Database::first('item_types', $columnArray, $condition);
            if (!is_array($itemTypeFields)) {
                $itemTypeFields = [];
            }
        } else {
            // Use passed in values.  Only occurs when user comes back to incomplete form
            // as original item type is pass in from item type selection page is no more
            foreach ($values as $key => $value) {
                $itemTypeFields[$key] = stripslashes($value);
            }
        }

        // Get item type name
        $columnArray = array('item_type_name');
        $condition = 'WHERE item_type_id = ' . (int) $itemTypeID;
        $row = Database::first('item_types', $columnArray, $condition);
        if (!is_array($row) || ($row['item_type_name'] ?? '') === '' || ($itemTypeFields['item_type_id'] ?? '') === '') {
            $picker = ItemScreen::recordUrl('option=show_item_types');
            Page::current()->setBody(Blocks::vertical([
                [
                    'title' => TXT_68,
                    'html' => '<p class="record-list__empty">' . Html::e(TXT_68) . '</p>'
                        . '<div class="form-actions">'
                        . Blocks::link($picker, TXT_69, '', 'btn btn--primary btn--sm')
                        . '</div>',
                ],
            ]));
            return;
        }
        $itemTypeName = $row['item_type_name'];
        $inApplication = Page::current()->inApplication();
        $canRevise = self::canReviseType((int) ($itemTypeFields['item_type_id'] ?? 0));
        $peopleHidden = '';
        if ($canRevise) {
            // If the item type does not specify a default user use the logged in user
            if (!isset($values['user_security'])) {
                if ((string) ($itemTypeFields['user_security'] ?? '') === '') {
                    $itemTypeFields['user_security'] = $_SESSION['access_user_id'];
                }
                $itemTypeFields['creator_security'] = $_SESSION['access_user_id'];
            }
            if ($inApplication) {
                $peopleHidden = Form::hidden('creator_security', (string) $itemTypeFields['creator_security']);
                $fields['Owner'] = self::ownerSelect((int) ($itemTypeFields['user_security'] ?? 0), '', true);
            } else {
                // Security is setup by default from the item
                $columnArray = array('user_id', 'user_name');
                $condition = "WHERE lastactive <> 'inactive' ORDER BY user_name ASC";
                $userIDArray = [];
                $userArray = [];
                foreach (Database::select('users', $columnArray, $condition) as $row) {
                    $userIDArray[] = $row['user_id'];
                    $userArray[] = $row['user_name'];
                }// while
                $fields[Wording::get('LA_551', 'TXT_551')] = Form::select('creator_security', $userIDArray, $userArray, $itemTypeFields['creator_security']);
                $owners = Role::ownerMenu(true);
                $filterdUserIDArray = $owners['ids'];
                $filteredUserArray = $owners['names'];
                $fields[Wording::get('LA_269', 'TXT_269')] = Form::select('user_security', $filterdUserIDArray, $filteredUserArray, $itemTypeFields['user_security']);
            }
        } elseif ($inApplication) {
            $ownerId = ($itemTypeFields['user_security'] ?? '') === ''
                ? (string) $_SESSION['access_user_id']
                : (string) $itemTypeFields['user_security'];
            $peopleHidden = Form::hidden('creator_security', (string) $_SESSION['access_user_id'])
                . Form::hidden('user_security', $ownerId);
        } else {
            $columnArray = array('user_name');
            $condition = "WHERE user_id ='" . $_SESSION['access_user_id'] . "'";
            $row = Database::first('users', $columnArray, $condition);
            $fields[Wording::get('LA_551', 'TXT_551')] = Form::hidden('creator_security', $_SESSION['access_user_id']) . $row['user_name'];
            if ($itemTypeFields['user_security'] == '') {
                $columnArray = array('user_name');
                $condition = "WHERE user_id ='" . $_SESSION['access_user_id'] . "'";
                $row = Database::first('users', $columnArray, $condition);
                $fields[Wording::get('LA_269', 'TXT_269')] = Form::hidden('user_security', $_SESSION['access_user_id']) . $row['user_name'];
            } else {
                $columnArray = array('user_name');
                $condition = "WHERE user_id ='" . $itemTypeFields['user_security'] . "'";
                $row = Database::first('users', $columnArray, $condition);
                $fields[Wording::get('LA_269', 'TXT_269')] = Form::hidden('user_security', $itemTypeFields['user_security']) . $row['user_name'];
            }
        }
        // Groups the record belongs to. The item type's groups start checked.
        $selectedGroups = isset($values['user_security'])
            ? GroupMembership::idsFromPost($values)
            : GroupMembership::itemTypeGroupIds((int) $itemTypeID);
        if (!$inApplication) {
            $fields[TXT_270] = self::itemGroupPicker($selectedGroups);
        }
        $fields[$inApplication ? 'Name' : Wording::get('LA_84', 'TXT_84')] = Form::text('item_title', $itemTypeFields['item_title'] ?? '');    // Setup custom field display
        $columnArray = array('custom_field_id');
        $condition = 'WHERE item_type_id = ' . (int) $itemTypeID . ' ORDER BY custom_field_order ASC';
        $customFieldResult = Database::select('item_type_custom_fields', $columnArray, $condition);
        $fieldCheck = new FieldCheck();
        foreach ($customFieldResult as $customFields) {
            $columnArray = array('*');
            $condition = "WHERE custom_field_id = '" . $customFields['custom_field_id'] . "'";
            $row = Database::first('custom_fields', $columnArray, $condition);
            $row['field_type'] = FieldTypes::normalise($row['field_type']);
            if ($row['enabled'] != 'Yes') {
                continue;
            }
            if (($row['field_type'] == 'workerField' or $row['field_type'] == 'workerFieldMenu') and !Access::canAssignRecords()) {
                continue;
            }
            if (MenuOptions::coveredByParentOnItemType((int)$row['custom_field_id'], (int)$itemTypeID)) {
                continue;
            }
            if (stristr($row['field_type'], 'worker')) {
                $prepend = 'worker_field_';
            } else {
                $prepend = 'custom_field_';
            }
            if ($inApplication) {
                $row['custom_field_name'] = self::sheetFieldLabel((string) $row['custom_field_name']);
            }
            // Override default value if previous values have already been selected.  user_security is a POST variable and is only set when the page is submitted
            if (!isset($values['user_security'])) {
                $defaultValue = $row['default_value'];
            } else {
                $defaultValue = (string) ($itemTypeFields['custom_field_' . $row['custom_field_id']] ?? '');
            }

            $fieldCheck->add($row, $prepend . $row['custom_field_id']);
            switch ($row['field_type']) {
                case FieldTypes::TEXT_BOX:
                case 'password':
                case 'hidden':
                case FieldTypes::TEXT_AREA:
                case 'URL':
                case 'dynamicURL':
                case 'workerField':
                    $control = FieldControl::render(
                        (string) $row['field_type'],
                        ($row['field_type'] === 'workerField' ? 'worker_field_' : 'custom_field_') . $row['custom_field_id'],
                        $defaultValue,
                        $row
                    );
                    if ($control !== null) {
                        $fields[$row['custom_field_name']] = $control;
                    }
                    break;
                case FieldTypes::MENU :
                    $childId = (int)($row['sub_menu'] ?? 0);
                    $childStored = ($childId > 0) ? (string)($itemTypeFields['custom_field_' . $childId] ?? '') : '';
                    $fields[$row['custom_field_name']] = self::menuHtml(
                        (int) $itemTypeID,
                        (int) $row['custom_field_id'],
                        (string) $defaultValue,
                        $childStored,
                        (is_array($values) && isset($values['user_security'])) ? $values : []
                    );
                    break;
                case FieldTypes::CHECK_BOX :
                    $fields[$row['custom_field_name']] = Form::checkbox('custom_field_' . $row['custom_field_id'], $row['custom_field_id'], '');
                    break;
                case 'workerFieldMenu' :
                    $columnArray = array('default_value');
                    $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                    $defaultValueArray = Database::first('custom_fields', $columnArray, $condition);
                    // Get any menus values
                    $columnArray = array('menu_value');
                    $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                    $result = Database::select('custom_field_menu_values', $columnArray, $condition);
                    $menuArray = [];
                    foreach ($result as $menuRow) {
                        $menuArray[] = $menuRow['menu_value'];
                    }
                    $fields[$row['custom_field_name']] = Form::select('worker_field_menu_' . $row['custom_field_id'], $menuArray, $menuArray, $defaultValueArray['default_value']);
                    break;
                default :
                    $control = FieldControl::render(
                        (string) $row['field_type'],
                        'custom_field_' . $row['custom_field_id'],
                        $defaultValue,
                        $row
                    );
                    if ($control !== null) {
                        $fields[$row['custom_field_name']] = self::markWait((int) $itemTypeID, (int) $row['custom_field_id'], $control);
                    }
                    break;
            }
        }

        $fields[''] = Form::hidden('item_type_id', (string)$itemTypeFields['item_type_id']) . $peopleHidden;
        $formOptions = [];
        if ($inApplication) {
            $redisplay = isset($values['user_security']);
            $draftNote = $redisplay ? (string) ($values['log_entry'] ?? '') : '';
            $tools = self::recordTools(0, (int) $itemTypeID, $canRevise, $selectedGroups, $draftNote);
            if ($tools['multipart']) {
                $formOptions['enctype'] = 'multipart/form-data';
            }
            $formOptions['lead'] = $tools['lead'];
            $formOptions['inside'] = $tools['inside'];
            $formOptions['after'] = $tools['after'];
            $formOptions['record'] = true;
            $formOptions['kicker'] = $itemTypeName;
            $status = WorkScreen::workStatusField((int) $itemTypeID);
            if ($status !== null) {
                $badge = trim((string) ($itemTypeFields['custom_field_' . $status['id']] ?? ''));
                if ($badge === '') {
                    $default = Database::first('custom_fields', ['default_value'], 'custom_field_id = ?', [$status['id']]);
                    $badge = trim((string) ($default['default_value'] ?? ''));
                }
                if ($badge === '') {
                    $first = Database::first(
                        'custom_field_menu_values',
                        ['menu_value'],
                        'custom_field_id = ? ORDER BY menu_value_id ASC',
                        [$status['id']]
                    );
                    $badge = trim((string) ($first['menu_value'] ?? ''));
                }
                if ($badge !== '') {
                    $formOptions['badge'] = $badge;
                }
            }
        } elseif (ADD_ATTACHMENTS == 'yes') {
            $fields[TXT_393] = Form::file('attachment', (string) ((int) SET_MAX_ATTACHMENT * 1000000));
            $formOptions['enctype'] = 'multipart/form-data';
        }
        $javascript = $fieldCheck->withTitle()->onclick();
        $formOptions = self::withFormErrors($formOptions);
        $buttons[] = Form::button('submit', 'submit_button', TXT_57, $javascript);
        $createHeading = $inApplication
            ? 'New ' . $itemTypeName
            : Wording::get('LA_46', 'TXT_67') . ' — ' . $itemTypeName;
        $bodyContent = Form::form($createHeading,ItemScreen::recordUrl('option=add_item'),$fields,$buttons,$formOptions);
        Page::current()->setBody($bodyContent);
    }

/**
     * Shows the item for modification.
     *
     * @param string $itemID Item ID
     */
    public static function showItem($itemID, $values = '', $addLogEntry = 'no', $attachments = 'no')
    {
        //Check to see if the item exists
        $sql = 'SELECT item_id FROM items WHERE item_id = ' . (int) $itemID;
            $result = Database::rows($sql);
        if (count($result) == 0) {
           View::respond(TXT_616);
        } else {
            $i = ItemScreen::userCanAccessItem((int) $itemID) ? 1 : 0;
            if ($i > 0) {//The user is allowed to access the task
                $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
                $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
                // Load the stored item, then lay posted values over it when the form is reloaded part-way
                $itemFields = ItemFields::hydrate(Database::first('items', ['*'], 'item_id = ?', [(int) $itemID]) ?? []);
                if (isset($values['item_id'])) {
                    foreach ($values as $key => $value) {
                        $itemFields[$key] = is_string($value) ? stripslashes($value) : $value;
                    }
                }
                // Get item type name
                $columnArray = array('item_type_name');
                $condition = 'WHERE item_type_id = ' . (int) $itemFields['item_type_id'];
                $row = Database::first('item_types', $columnArray, $condition);
                $itemTypeName = $row['item_type_name'];
                $inApplication = Page::current()->inApplication();
                $itemField = [];
                $peopleHidden = '';
                if ($inApplication) {
                    $peopleHidden = Form::hidden('creator_security', (string) ($itemFields['creator_security'] ?? ''))
                        . Form::hidden('user_security', (string) ($itemFields['user_security'] ?? ''));
                } elseif (Access::canAssignRecords()) {//Managers, admins and oneorzero admins and global admins
                    // Build table array
                    // Security is setup by default from the item
                    $columnArray = array('user_id', 'user_name');
                    $condition = "ORDER BY user_name ASC";
                    $userIDArray = [];
                    $userArray = [];
                    foreach (Database::select('users', $columnArray, $condition) as $row) {
                        $userIDArray[] = $row['user_id'];
                        $userArray[] = $row['user_name'];
                    }// while
                    $itemField[Wording::get('LA_551', 'TXT_551')] = Form::select('creator_security', $userIDArray, $userArray, $itemFields['creator_security']);
                    $owners = Role::ownerMenu(false);
                    $filterdUserIDArray = $owners['ids'];
                    $filteredUserArray = $owners['names'];
                    $itemField[Wording::get('LA_269', 'TXT_269')] = Form::select('user_security', $filterdUserIDArray, $filteredUserArray, $itemFields['user_security']);
                } else {
                    $columnArray = array('user_name');
                    $condition = 'WHERE user_id = ' . (int) $itemFields['creator_security'];
                    $row = Database::first('users', $columnArray, $condition);
                    $itemField[TXT_551] = Form::hidden('creator_security', $itemFields['creator_security']) . $row['user_name'];
                    $columnArray = array('user_name');
                    $condition = 'WHERE user_id = ' . (int) $itemFields['user_security'];
                    $row = Database::first('users', $columnArray, $condition);
                    $itemField[TXT_269] = Form::hidden('user_security', $itemFields['user_security']) . $row['user_name'];
                }
                $itemGroupIds = GroupMembership::itemGroupIds((int) $itemID);
                $canRevise = ItemScreen::canReviseItem((int) $itemID);
                $html = '';
                if (!$inApplication && $canRevise) {
                    // Create group membership list
                    $groupArray = array_values(array_filter(array_map('intval', $itemGroupIds)));
                    $groupMembership = '';
                    if ($groupArray !== []) {
                        $result = Database::select(
                            'groups',
                            ['group_id', 'group_name'],
                            'group_id IN (' . implode(',', $groupArray) . ')'
                        );
                        $names = [];
                        foreach ($result as $row) {
                            $names[] = (string) $row['group_name'];
                        }
                        $groupMembership = implode(', ', $names);
                    }
                    $changeSecurityURL = Blocks::link(
                        ItemScreen::recordUrl('item=' . (int) $itemID . '&option=change_security'),
                        TXT_268,
                        '',
                        'btn btn--sm'
                    );
                    $itemField[TXT_270] = '<div class="criteria-row"><div class="readonly-value">' . Html::e($groupMembership) . '</div>'
                        . $changeSecurityURL . '</div>';
                }
                $titleLabel = $inApplication ? 'Name' : Wording::get('LA_84', 'TXT_84');
                if (!$canRevise) {
                    $itemField[$titleLabel] = Form::hidden('item_title', $itemFields['item_title']) . $itemFields['item_title'];
                } else {
                    $itemField[$titleLabel] = Form::text('item_title', $itemFields['item_title']);
                }
                // Setup custom field display
                $columnArray = array('custom_field_id');
                $condition = 'WHERE item_type_id = ' . (int) $itemFields['item_type_id'] . ' ORDER BY custom_field_order ASC';
                $customFieldResult = Database::select('item_type_custom_fields', $columnArray, $condition);
                $fieldCheck = new FieldCheck();
                foreach ($customFieldResult as $customFields) {
                    $columnArray = array('*');
                    $condition = "WHERE custom_field_id = '" . $customFields['custom_field_id'] . "'";
                    $row = Database::first('custom_fields', $columnArray, $condition);
                    $row['field_type'] = FieldTypes::normalise($row['field_type']);
                    if ($row['enabled'] != 'Yes') {
                        continue;
                    }
                    if (($row['field_type'] == 'workerField' or $row['field_type'] == 'workerFieldMenu') and !Access::canAssignRecords()) {
                        continue;
                    }
                    if ($canRevise && MenuOptions::coveredByParentOnItemType((int)$row['custom_field_id'], (int)($itemFields['item_type_id'] ?? 0))) {
                        continue;
                    }
                    if (stristr($row['field_type'], 'worker')) {
                        $prepend = 'worker_field_';
                    } else {
                        $prepend = 'custom_field_';
                    }
                    if ($inApplication) {
                        $row['custom_field_name'] = self::sheetFieldLabel((string) $row['custom_field_name']);
                    }
                    // Override database value if values have already been selected
                    if (!isset($values['item_id'])) {
                        $value = (string) ($itemFields['custom_field_' . $row['custom_field_id']] ?? '');
                        $menuValue = $value;
                    } else {
                        $value = stripslashes((string) ($values['custom_field_' . $row['custom_field_id']] ?? ''));
                        $menuValue = (string) ($values['custom_field_' . $row['custom_field_id']] ?? '');
                    }

                    $fieldCheck->add($row, $prepend . $row['custom_field_id']);

                    $hiddenValue = '';
                    if (!$canRevise && ($row['field_type'] != 'fieldSeparator')) {
                        $hiddenValue = $value;
                        if ($row['field_type'] != FieldTypes::TEXT_AREA) {
                            $value = FieldValidation::display($row, $value);
                            $fieldType = 'text';
                        } else {
                            $value = str_replace("\n", "<br />", $value);
                            $value = '<br />' . $value . '<br /><br />';
                            $fieldType = 'text';
                        }
                    } else {
                        $fieldType = $row['field_type'];
                    }
                    switch ($fieldType) {
                        case 'text' :
                            $itemField[$row['custom_field_name']] = $value . Form::hidden('custom_field_' . $row['custom_field_id'], $hiddenValue);
                            break;
                        case FieldTypes::TEXT_BOX:
                        case 'password':
                        case 'hidden':
                        case FieldTypes::TEXT_AREA:
                        case 'workerField':
                            $control = FieldControl::render(
                                (string) $fieldType,
                                ($fieldType === 'workerField' ? 'worker_field_' : 'custom_field_') . $row['custom_field_id'],
                                $value,
                                $row
                            );
                            if ($control !== null) {
                                $itemField[$row['custom_field_name']] = $control;
                            }
                            break;
                        case FieldTypes::MENU :
                            $childId = (int)($row['sub_menu'] ?? 0);
                            $childStored = $childId > 0
                                ? (string)((is_array($values) && isset($values['item_id']))
                                    ? ($values['custom_field_' . $childId] ?? '')
                                    : ($itemFields['custom_field_' . $childId] ?? ''))
                                : '';
                            $itemField[$row['custom_field_name']] = self::menuHtml(
                                (int) ($itemFields['item_type_id'] ?? 0),
                                (int) $row['custom_field_id'],
                                (string) $menuValue,
                                $childStored,
                                (is_array($values) && isset($values['item_id'])) ? $values : []
                            );
                            break;

                        case FieldTypes::CHECK_BOX :
                            $itemField[$row['custom_field_name']] = Form::checkbox(
                                'custom_field_' . $row['custom_field_id'],
                                (string) $row['custom_field_id'],
                                ((string) $value !== '' && (string) $value !== '0') ? (string) $row['custom_field_id'] : ''
                            );
                            break;
                        case 'URL' :
                            $url = '';
                            if ($value != '') {
                                $http = (!stristr($value, 'http') and !stristr($value, 'https')) ? 'http://' : '';
                                $url = ' ' . Blocks::link($http . $value, TXT_480, 'URL', SET_IMAGE_PATH . 'hyperlink.png', '', '_blank');
                            }
                            $itemField[$row['custom_field_name']] = Form::url('custom_field_' . $row['custom_field_id'], $value) . $url;
                            break;
                        case 'dynamicURL' :
                            $url = '';
                            if ($value != '') {
                                $url = ' ' . Blocks::link(str_replace('<?php echo INSERT; ?>', $value, $row['data']), TXT_480, 'URL', SET_IMAGE_PATH . 'hyperlink.png', '', '_blank');
                            }
                            $itemField[$row['custom_field_name']] = Form::text('custom_field_' . $row['custom_field_id'], $value) . $url;
                            break;
                        case 'workerFieldMenu' :
                            $columnArray = array('default_value');
                            $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                            $defaultValueArray = Database::first('custom_fields', $columnArray, $condition);
                            // Get any menus values
                            $columnArray = array('menu_value');
                            $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
                            $result = Database::select('custom_field_menu_values', $columnArray, $condition);
                            $menuArray = [];
                            foreach ($result as $menuRow) {
                                $menuArray[] = $menuRow['menu_value'];
                            }
                            $itemField[$row['custom_field_name']] = Form::select('worker_field_menu_' . $row['custom_field_id'], $menuArray, $menuArray, $defaultValueArray['default_value']);
                            break;
                        default :
                            $control = FieldControl::render(
                                (string) $fieldType,
                                'custom_field_' . $row['custom_field_id'],
                                $value,
                                $row
                            );
                            if ($control !== null) {
                                $itemField[$row['custom_field_name']] = self::markWait(
                                    (int) ($itemFields['item_type_id'] ?? 0),
                                    (int) $row['custom_field_id'],
                                    $control
                                );
                            }
                            break;
                    }
                }

                $formOptions = [];
                if ($inApplication) {
                    $selectedGroups = (is_array($values) && isset($values['groups_posted']))
                        ? GroupMembership::idsFromPost($values)
                        : $itemGroupIds;
                    $tools = self::recordTools(
                        (int) $itemID,
                        (int) ($itemFields['item_type_id'] ?? 0),
                        $canRevise,
                        $selectedGroups
                    );
                    $formOptions['lead'] = $tools['lead'];
                    $formOptions['inside'] = $tools['inside'];
                    $formOptions['after'] = $tools['after'];
                    $formOptions['record'] = true;
                    $formOptions['kicker'] = $itemTypeName;
                    $ownerId = (int) ($itemFields['user_security'] ?? 0);
                    if ($ownerId > 0) {
                        $owner = Database::first('users', ['user_name', 'first_name', 'last_name'], 'user_id = ?', [$ownerId]);
                        if ($owner !== null) {
                            $ownerName = trim((string) ($owner['first_name'] ?? '') . ' ' . (string) ($owner['last_name'] ?? ''));
                            if ($ownerName === '') {
                                $ownerName = trim((string) ($owner['user_name'] ?? ''));
                            }
                            if ($ownerName !== '') {
                                $formOptions['mark'] = $ownerName;
                            }
                        }
                    }
                    $facts = [];
                    $openedAt = (int) ($itemFields['create_date'] ?? 0);
                    $updatedAt = (int) ($itemFields['core_log_updated'] ?? 0);
                    if ($openedAt > 0) {
                        $facts[] = 'Opened ' . \Adlexone\Html\Presence::ago($openedAt);
                    }
                    if ($updatedAt > $openedAt) {
                        $facts[] = 'Updated ' . \Adlexone\Html\Presence::ago($updatedAt);
                    }
                    if ($facts !== []) {
                        $formOptions['facts'] = $facts;
                    }
                    $status = WorkScreen::workStatusField((int) ($itemFields['item_type_id'] ?? 0));
                    if ($status !== null) {
                        $badge = trim((string) ($itemFields['custom_field_' . $status['id']] ?? ''));
                        if ($badge !== '') {
                            $formOptions['badge'] = $badge;
                        }
                    }
                }

                $javascript = $fieldCheck->withTitle()->onclick();
                $formOptions = self::withFormErrors($formOptions);
                $buttons = [];
                if (Access::canEditRecords()) {
                    $buttons[] = Form::button('submit', 'submit_button', 'Save', $javascript);
                }

                $itemField[''] = Form::hidden('item_type_id', $itemFields['item_type_id']) . Form::hidden('item_id', $itemFields['item_id']) . $peopleHidden;
                $recordTitle = trim((string) ($itemFields['item_title'] ?? ''));
                $formTitle = $inApplication
                    ? ($recordTitle !== '' ? $recordTitle : $itemTypeName)
                    : Wording::get('LA_102', 'TXT_102') . ': ' . $itemFields['item_id'] . ' - ' . $itemTypeName;
                if ($inApplication) {
                    Page::current()->setTitle($formTitle);
                    $backLabel = trim(str_replace("\u{00A0}", ' ', Page::current()->navLabel()));
                    if ($backLabel === '' || strcasecmp($backLabel, $formTitle) === 0) {
                        $backLabel = 'Work';
                    }
                    $html .= '<p class="record-back"><a href="' . Html::e(ItemScreen::recordUrl()) . '">Back to '
                        . Html::e($backLabel) . '</a></p>';
                } else {
                    Page::current()->setCrumbs(\Adlexone\Html\Breadcrumb::trail([['label' => $formTitle]]));
                }
                $html .= Form::form($formTitle, ItemScreen::recordUrl('option=update_item'), $itemField, $buttons, $formOptions);

                //Show transform option
                if (!$inApplication && Access::canControlRecords()) {
                    // Administrative functions
                    $columnArray = array('item_type_id', 'item_type_name');
                    $result = Database::select('item_types', $columnArray);
                    if (count($result) > 0) {
                        foreach ($result as $row) {
                            $valueArray[] = $row['item_type_id'];
                            $displayArray[] = $row['item_type_name'];
                        }
                    } else {
                        $valueArray[] = '';
                        $displayArray[] = TXT_341;
                    }
                    $html .= '<br />
    ';
                    $itemTypeMenu = Form::select('item_type_id', $valueArray, $displayArray, '');
                    $transformationTypeMenu = Form::select('transform_type', array('copy', 'transform'), array(TXT_443, TXT_444), '');
                    $hiddenItemID = Form::hidden('item_id', $itemID);
                    $transformButton[] = Form::button('submit', 'submit_button', TXT_446, Form::confirmData(TXT_448));

                    $transinputArray[TXT_445] = $transformationTypeMenu;
                    $transinputArray[TXT_447] = $itemTypeMenu . $hiddenItemID;
                    $html .= Form::form(TXT_446,ItemScreen::recordUrl('option=transform_item'),$transinputArray,$transformButton);
                }

                if ($itemID != '' && defined('APP_COUNT_ITEM_TYPE') && $itemFields['item_type_id'] == APP_COUNT_ITEM_TYPE) {
                    SearchResults::updateKnoweldgeCount(array($itemID));
                }

                if ($inApplication) {
                    $html = '<div class="workspace"><div class="workspace__main">' . $html . '</div>'
                        . self::recordSide((int) $itemID, $itemFields, array_map('intval', $itemGroupIds), $canRevise) . '</div>';
                }
                Page::current()->setBody($html);
            } else {
                // Not allowed
                View::respond(TXT_452);
            }
        }
    }

    /**
     * True when the signed-in person can set the owner and groups on a new record of this type.
     */
    public static function canReviseType(int $typeId): bool
    {
        if (Access::canControlRecords()) {
            return true;
        }
        $level = 'view';
        $userGroupIds = GroupMembership::userGroupIds((int) ($_SESSION['access_user_id'] ?? 0));
        foreach (array_intersect($userGroupIds, GroupMembership::itemTypeGroupIds($typeId)) as $groupId) {
            $level = Role::strongerLevel($level, Role::recordLevelForGroup((int) $groupId));
        }

        return Role::levelAtLeast($level, 'assign');
    }
    public static function addItem()
    {
        ItemWrite::addItem();
    }

    public static function transformItem($itemID, $transformType, $targetItemTypeID)
    {
        ItemWrite::transformItem($itemID, $transformType, $targetItemTypeID);
    }

    public static function updateItem($itemID)
    {
        ItemWrite::updateItem($itemID);
    }

    public static function deleteItem($itemID)
    {
        ItemWrite::deleteItem($itemID);
    }

}
