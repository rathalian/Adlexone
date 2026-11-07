<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Csrf;
use Adlexone\Database\Database;
use Adlexone\Html\Form;
use Adlexone\Html\Html;

/**
 * Access, files, and notes as buttons. Each opens a dialog.
 * A new record keeps the controls in the create form. An existing record saves each one on its own.
 */
final class ItemSheetTools
{
    /**
     * @param list<int> $selectedGroups
     * @return array{lead: string, inside: string, after: string, multipart: bool}
     */
    public static function recordTools(int $itemId, int $itemTypeId, bool $canRevise, array $selectedGroups, string $draftNote = ''): array
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
            $accessBody = ItemSheetSide::groupPicker($selectedGroups);
        } else {
            $names = ItemSheetSide::groupNames($selectedGroups);
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

    public static function transformForm(string $itemId): string
    {
        $values = [];
        $labels = [];
        $result = Database::select('item_types', ['item_type_id', 'item_type_name']);
        if (count($result) > 0) {
            foreach ($result as $row) {
                $values[] = $row['item_type_id'];
                $labels[] = $row['item_type_name'];
            }
        } else {
            $values[] = '';
            $labels[] = TXT_341;
        }
        $typeMenu = Form::select('item_type_id', $values, $labels, '');
        $kindMenu = Form::select('transform_type', ['copy', 'transform'], [TXT_443, TXT_444], '');
        $inputs = [
            TXT_445 => $kindMenu,
            TXT_447 => $typeMenu . Form::hidden('item_id', $itemId),
        ];

        return "<br />\n    " . Form::form(
            TXT_446,
            ItemScreen::recordUrl('option=transform_item'),
            $inputs,
            [Form::button('submit', 'submit_button', TXT_446, Form::confirmData(TXT_448))]
        );
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
                . Csrf::field()
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

    private static function countLabel(int $count, string $noun, string $none): string
    {
        if ($count <= 0) {
            return $none;
        }

        return $count === 1 ? '1 ' . $noun : $count . ' ' . $noun . 's';
    }
}
