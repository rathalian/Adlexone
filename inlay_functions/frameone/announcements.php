<?php
declare(strict_types=1);

use Adlexone\FrameOne\AppAccess;
use Adlexone\support\Database;
use Adlexone\support\RenderViews;

/**
 * FrameOne announcements board — scoped by APPLICATION_SLUG when set.
 */
function openFrameOneAnnouncements(): void
{
    $slug = AppAccess::slug();

    switch ((string) ($_GET['option'] ?? 'show_announcements')) {
        case 'show_announcement_item':
            AppAccess::requireUse();
            showFrameOneAnnouncementItem((string) ($_GET['id'] ?? ''), $slug);
            break;
        case 'new_announcement':
            if (!AppAccess::canAnnounce()) {
                \Adlexone\Auth\Access::deny();
            }
            newFrameOneAnnouncement();
            break;
        case 'add_announcement':
            if (!AppAccess::canAnnounce()) {
                \Adlexone\Auth\Access::deny();
            }
            addFrameOneAnnouncement($slug);
            break;
        case 'edit_announcement':
            if (!AppAccess::canAnnounce()) {
                \Adlexone\Auth\Access::deny();
            }
            editFrameOneAnnouncement((string) ($_GET['id'] ?? ''), $slug);
            break;
        case 'update_announcement':
            if (!AppAccess::canAnnounce()) {
                \Adlexone\Auth\Access::deny();
            }
            updateFrameOneAnnouncement($slug);
            break;
        case 'delete_announcement':
            if (!AppAccess::canAnnounce()) {
                \Adlexone\Auth\Access::deny();
            }
            deleteFrameOneAnnouncement((string) ($_GET['id'] ?? ''), $slug);
            break;
        case 'show_announcements':
        default:
            AppAccess::requireUse();
            showFrameOneAnnouncements($slug);
            break;
    }
}

/**
 * @return array<string, mixed>|null
 */
function frameOneAnnouncement(int $id, string $slug): ?array
{
    if ($slug !== '' && Database::columnExists('announcements', 'application')) {
        return Database::first('announcements', '*', 'id = ? AND application = ?', [$id, $slug]);
    }
    return Database::first('announcements', '*', 'id = ?', [$id]);
}

function showFrameOneAnnouncementItem(string $id, string $slug): void
{
    $row = frameOneAnnouncement((int) $id, $slug);
    if ($row === null) {
        RenderViews::buildResponse('Announcement not found.', RenderViews::buildURL(MAN_BASE_URL . '&option=show_announcements', 'Announcements', 'URL'));
        return;
    }

    $heading = date(SET_DATE_FORMAT, (int) $row['time']) . ': ' . (string) $row['subject'];
    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        [
            'title' => $heading,
            'html' => RenderViews::buildFormFieldsGrid([
                '' => RenderViews::buildTextArea('', (string) $row['message'], '', true),
            ]),
        ],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function newFrameOneAnnouncement(): void
{
    define('BODY_CONTENT', RenderViews::buildForm(
        'New announcement',
        MAN_BASE_URL . '&option=add_announcement',
        [
            'Subject' => RenderViews::buildTextInput('subject', ''),
            'Message' => RenderViews::buildTextArea('message', ''),
        ],
        [
            RenderViews::buildFormButton('submit', 'submit_button', 'Save'),
            RenderViews::buildFormButton('reset', 'reset', 'Reset'),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function addFrameOneAnnouncement(string $slug): void
{
    $data = [
        'time' => time(),
        'message' => (string) ($_POST['message'] ?? ''),
        'subject' => (string) ($_POST['subject'] ?? ''),
        'type' => 'user',
    ];
    if (Database::columnExists('announcements', 'application')) {
        $data['application'] = $slug;
    }
    Database::insert('announcements', $data);
    showFrameOneAnnouncements($slug);
}

function showFrameOneAnnouncements(string $slug): void
{
    if ($slug !== '' && Database::columnExists('announcements', 'application')) {
        $result = Database::select('announcements', ['id', 'subject', 'message', 'time'], 'application = ?', [$slug], 'time DESC');
    } else {
        $result = Database::select('announcements', ['id', 'subject', 'message', 'time'], '', [], 'time DESC');
    }

    $canManage = AppAccess::canAnnounce();
    $html = '';
    foreach ($result as $row) {
        $actions = '';
        if ($canManage) {
            $actions = '<div class="announcement__actions">'
                . RenderViews::buildURL(MAN_BASE_URL . '&option=edit_announcement&id=' . $row['id'], 'Edit', '', 'btn btn--sm btn--quiet')
                . RenderViews::buildURL(MAN_BASE_URL . '&option=delete_announcement&id=' . $row['id'], 'Delete', '', 'btn btn--sm btn--danger', 'onClick="return confirm(\'Delete this announcement?\')"')
                . '</div>';
        }
        $html .= '<article class="announcement">'
            . '<h2 class="announcement__subject">' . htmlspecialchars((string) $row['subject'], ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<div class="announcement__body">' . $row['message'] . '</div>'
            . '<div class="announcement__meta"><time>' . htmlspecialchars(date(SET_DATE_FORMAT, (int) $row['time']), ENT_QUOTES, 'UTF-8') . '</time>' . $actions . '</div>'
            . '</article>';
    }
    if ($html === '') {
        $html = '<p class="record-list__empty">No announcements yet.</p>';
    }
    if ($canManage) {
        $html = '<div class="form-actions" style="margin-top:0">'
            . RenderViews::buildURL(MAN_BASE_URL . '&option=new_announcement', 'New announcement', '', 'btn btn--primary btn--sm')
            . '</div>' . $html;
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        ['title' => 'Announcements', 'html' => $html],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function editFrameOneAnnouncement(string $id, string $slug): void
{
    $row = frameOneAnnouncement((int) $id, $slug);
    if ($row === null) {
        RenderViews::buildResponse('Announcement not found.', RenderViews::buildURL(MAN_BASE_URL . '&option=show_announcements', 'Announcements', 'URL'));
        return;
    }
    define('BODY_CONTENT', RenderViews::buildForm(
        'Edit announcement',
        MAN_BASE_URL . '&option=update_announcement',
        [
            'Subject' => RenderViews::buildTextInput('subject', (string) $row['subject']),
            'Message' => RenderViews::buildTextArea('message', (string) $row['message'], SET_FORM_FIELD_HEIGHT),
            '' => RenderViews::buildHiddenInput('id', (string) (int) $id),
        ],
        [
            RenderViews::buildFormButton('submit', 'submit_button', 'Save'),
            RenderViews::buildFormButton('reset', 'reset', 'Reset'),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function updateFrameOneAnnouncement(string $slug): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (frameOneAnnouncement($id, $slug) === null) {
        RenderViews::buildResponse('Announcement not found.', RenderViews::buildURL(MAN_BASE_URL . '&option=show_announcements', 'Announcements', 'URL'));
        return;
    }
    Database::update('announcements', [
        'message' => (string) ($_POST['message'] ?? ''),
        'subject' => (string) ($_POST['subject'] ?? ''),
    ], 'id = ?', [$id]);
    showFrameOneAnnouncements($slug);
}

function deleteFrameOneAnnouncement(string $id, string $slug): void
{
    $row = frameOneAnnouncement((int) $id, $slug);
    if ($row !== null) {
        Database::delete('announcements', 'id = ?', [(int) $id]);
    }
    showFrameOneAnnouncements($slug);
}
