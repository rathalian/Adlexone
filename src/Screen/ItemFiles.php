<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
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

final class ItemFiles
{
    private static function attachmentDisplayName(string $stored): string
    {
        $label = preg_replace('/^[A-Za-z0-9]+_/', '', $stored, 1);
        if (!is_string($label) || $label === '') {
            return $stored;
        }

        return $label;
    }

    public static function attachmentLines(string $itemID): string
    {
        $canDelete = Access::allowsControl();
        $rows = Database::select('item_attachments', ['*'], 'item_id = ?', [(int) $itemID], 'create_date DESC');
        if ($rows === []) {
            return '<p class="record-list__empty">No files yet.</p>';
        }
        $html = '<ul class="file-lines">';
        foreach ($rows as $row) {
            $stored = (string) ($row['file_name'] ?? '');
            $label = self::attachmentDisplayName($stored);
            $href = ItemScreen::recordUrl('option=download_attachment&id=' . (int) ($row['id'] ?? 0));
            $bytes = (int) ($row['file_size'] ?? 0);
            $size = $bytes < 1000 ? $bytes . ' bytes' : (string) round($bytes / 1000) . ' KB';
            $meta = trim((string) ($row['file_type'] ?? ''))
                . ' · ' . $size
                . ' · ' . date((string) SET_DATE_FORMAT, (int) ($row['create_date'] ?? 0));
            $html .= '<li class="file-lines__item"><a class="record-link" href="' . Html::e($href) . '">' . Html::e($label) . '</a>'
                . '<span class="file-lines__meta">' . Html::e($meta) . '</span>';
            if ($canDelete) {
                $delete = ItemScreen::recordUrl('item=' . rawurlencode($itemID) . '&option=delete_attachment&id=' . (int) ($row['id'] ?? 0));
                $html .= '<a class="btn btn--sm btn--danger" href="' . Html::e($delete) . '"' . Form::confirmData((string) TXT_400) . '>Remove</a>';
            }
            $html .= '</li>';
        }

        return $html . '</ul>';
    }

    public static function addAttachment($itemID, $showAttachments = true, $executeAction = true)
    {
        // Upload the attachment
        $fileArray = AttachmentUpload::uploadItemAttachment(SET_MAX_ATTACHMENT, 'attachment', time(), SET_ATTACHMENTS_PATH);
        // Add attachment
        $columnArray['item_id'] = $itemID;
        $columnArray['create_date'] = time();
        $columnArray['file_name'] = $fileArray['time_name'];
        $columnArray['file_type'] = $fileArray['type'];
        $columnArray['file_size'] = $fileArray['size'];
        $columnArray['added_by'] = $_SESSION['access_user_id'];
        // Insert into attachment table
        Database::insert('item_attachments', $columnArray);
        // Execute actions

        if (($columnArray['file_size'] > 0) && ($executeAction == true)) {
            if (!Request::hasPostedKey('creator_security')) {
                $sql = 'SELECT * FROM items WHERE item_id = ' . (int) $itemID;
                            $result = Database::rows($sql);
                $row = $result[0] ?? null;
                foreach ($row as $key => $value) {
                    if (!is_int($key)) {
                        Request::setPosted($key, $value);
                    }
                }
            }
            Actions::executeAction($itemID, 'item_attachment', false);
        }

        if ($showAttachments) {
            if (ItemScreen::returnToItem($itemID, 'File added', 'files')) {
                return;
            }
            self::showAttachments($itemID);
        }
    }

    private static function attachmentListHtml(string $itemID, string $empty): string
    {
        $canDelete = Access::allowsControl();
        $sql = 'SELECT * FROM item_attachments WHERE item_id = ' . (int) $itemID;
        $result = Database::rows($sql);
        $rows = [];
        foreach ($result as $row) {
            $actions = [];
            if ($canDelete) {
                $actions[] = [
                    'href' => ItemScreen::recordUrl('item=' . $itemID . '&option=delete_attachment&id=' . $row['id']),
                    'label' => TXT_47,
                    'tone' => 'danger',
                    'confirm' => TXT_400,
                ];
            }
            $rows[] = [
                'name' => self::attachmentDisplayName((string) $row['file_name']),
                'href' => ItemScreen::recordUrl('option=download_attachment&id=' . $row['id']),
                'cells' => [
                    'type' => (string)$row['file_type'],
                    'size' => (string)round(((int)$row['file_size']) / 1000),
                    'opened' => date(SET_DATE_FORMAT, (int)$row['create_date']),
                ],
                'actions' => $actions,
            ];
        }

        return Blocks::recordList([
            'column' => TXT_394,
            'columns' => [
                ['key' => 'type', 'label' => TXT_395],
                ['key' => 'size', 'label' => TXT_396],
                ['key' => 'opened', 'label' => TXT_397],
            ],
            'empty' => $empty,
            'groups' => [['rows' => $rows]],
        ]);
    }

    public static function showAttachments($itemID)
    {
        $list = self::attachmentListHtml((string) $itemID, (string) TXT_399);

        $showLog = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $backHref = ItemScreen::recordUrl('item=' . $itemID . '&option=show_item' . $showLog . $attachments);
        $fields = [
            '' => $list,
            TXT_393 => Form::file('attachment', (string) ((int) SET_MAX_ATTACHMENT * 1000000)),
        ];
        Page::current()->setBody(Form::form(
            TXT_389,
            ItemScreen::recordUrl('item=' . $itemID . '&option=add_attachment'),
            $fields,
            [
                Form::button('submit', 'submit_button', TXT_74),
                '<a class="btn" href="' . Html::e($backHref) . '">' . Html::e(TXT_385) . '</a>',
            ],
            ['enctype' => 'multipart/form-data']
        ));
    }

    public static function downloadAttachment($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            Access::deny();
        }
        $row = Database::first('item_attachments', ['*'], 'id = ?', [$id]);
        if ($row === null || !ItemScreen::userCanAccessItem((int) ($row['item_id'] ?? 0))) {
            Access::deny();
        }
        $stored = basename((string) ($row['file_name'] ?? ''));
        $root = realpath((string) SET_ATTACHMENTS_PATH);
        $real = ($root !== false && $stored !== '') ? realpath($root . DIRECTORY_SEPARATOR . $stored) : false;
        if ($root === false || $real === false || !is_file($real) || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            Access::deny();
        }
        $type = (string) ($row['file_type'] ?? '');
        if (preg_match('#^[A-Za-z0-9.+-]+/[A-Za-z0-9.+-]+$#', $type) !== 1) {
            $type = 'application/octet-stream';
        }
        $label = self::attachmentDisplayName($stored);
        $label = str_replace(["\r", "\n", '"'], '', $label);
        if ($label === '') {
            $label = 'download';
        }
        ob_clean();
        header('Expires: 0');
        header('Pragma: cache');
        header('Cache-Control: private');
        header('Content-Type: ' . $type);
        header('Content-Length: ' . (string) filesize($real));
        header('Content-Disposition: attachment; filename="' . $label . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($real);
        exit;
    }

    public static function deleteAttachment($id, $itemID)
    {
        $id = (int) $id;
        $row = Database::first('item_attachments', ['file_name', 'item_id'], 'id = ?', [$id]);
        if ($row === null || !ItemScreen::userCanAccessItem((int) ($row['item_id'] ?? 0))) {
            Access::deny();
        }
        $stored = basename((string) ($row['file_name'] ?? ''));
        $result = $stored === ''
            ? []
            : Database::select('item_attachments', ['file_name'], 'file_name = ?', [$stored]);
        if (count($result) === 1) {
            $root = realpath((string) SET_ATTACHMENTS_PATH);
            $real = ($root !== false) ? realpath($root . DIRECTORY_SEPARATOR . $stored) : false;
            if ($root !== false && $real !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                unlink($real);
            }
        }
        Database::delete('item_attachments', 'id = ?', [$id]);
        if (ItemScreen::returnToItem($itemID, 'File removed', 'files')) {
            return;
        }
        self::showAttachments($itemID);
    }
}
