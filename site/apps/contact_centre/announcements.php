<?php
declare(strict_types=1);

use Adlexone\Application\AppFunctions;
use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\support\Database;
use Adlexone\support\RenderViews;

AppFunctions::register('contact_centre.announcements', [
    'label' => 'Announcements',
    'icon' => 'ic-announcements',
    'config' => 'none',
    'default_option' => 'show_announcements',
    'open' => 'openContactCentreAnnouncements',
]);

function openContactCentreAnnouncements(): void
{
    switch ((string) ($_GET['option'] ?? 'show_announcements')) {
        case 'show_announcement_item':
            Access::require(Permission::SERVICECENTRE_USE, Permission::SERVICECENTRE_SEARCH);
            showAnnouncementItem((string) ($_GET['id'] ?? ''));
            break;
        case 'new_announcement':
            Access::require(Permission::SERVICECENTRE_ANNOUNCE);
            newAnnouncement();
            break;
        case 'add_announcement':
            Access::require(Permission::SERVICECENTRE_ANNOUNCE);
            addAnnouncement();
            break;
        case 'edit_announcement':
            Access::require(Permission::SERVICECENTRE_ANNOUNCE);
            editAnnouncement((string) ($_GET['id'] ?? ''));
            break;
        case 'update_announcement':
            Access::require(Permission::SERVICECENTRE_ANNOUNCE);
            updateAnnouncement();
            break;
        case 'delete_announcement':
            Access::require(Permission::SERVICECENTRE_ANNOUNCE);
            deleteAnnouncement((string) ($_GET['id'] ?? ''));
            break;
        case 'show_announcements':
        default:
            Access::require(Permission::SERVICECENTRE_USE, Permission::SERVICECENTRE_SEARCH);
            showAnnouncements();
            break;
    }
}

function showAnnouncementItem(string $id = ''): void
{
    $row = Database::first('announcements', ['subject', 'message', 'time'], 'id = ?', [$id]);
    if ($row === null) {
        RenderViews::buildResponse(TXT_115, RenderViews::buildURL(MAN_BASE_URL . '&option=show_announcements', APP_SC_TXT_26, 'URL'));
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

function newAnnouncement(): void
{
    $fields[TXT_346] = RenderViews::buildTextInput('subject', '');
    $fields[TXT_347] = RenderViews::buildTextArea('message', '');
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "',[''],['subject'],[''],['" . TXT_547 . "'],[true]);\"";
    define('BODY_CONTENT', RenderViews::buildForm(
        APP_SC_TXT_75,
        MAN_BASE_URL . '&option=add_announcement',
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_345, $javascript),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function addAnnouncement(): void
{
    Database::insert('announcements', [
        'time' => time(),
        'message' => (string) ($_POST['message'] ?? ''),
        'subject' => (string) ($_POST['subject'] ?? ''),
        'type' => 'user',
    ]);
    showAnnouncements();
}

function showAnnouncements(): void
{
    $result = Database::select('announcements', ['id', 'subject', 'message', 'time'], '', [], 'time DESC');
    $html = '';

    foreach ($result as $row) {
        $editURL = '';
        $deleteURL = '';
        if (Access::can(Permission::SERVICECENTRE_ANNOUNCE)) {
            $editURL = RenderViews::buildURL(
                MAN_BASE_URL . '&option=edit_announcement&id=' . $row['id'],
                APP_SC_TXT_22,
                '',
                'btn btn--sm btn--quiet'
            );
            $deleteURL = RenderViews::buildURL(
                MAN_BASE_URL . '&option=delete_announcement&id=' . $row['id'],
                TXT_47,
                '',
                'btn btn--sm btn--danger',
                'onClick="return confirm(\'' . TXT_400 . '\')"'
            );
        }
        $actions = ($editURL !== '' || $deleteURL !== '')
            ? '<div class="announcement__actions">' . $editURL . $deleteURL . '</div>'
            : '';
        $html .= '<article class="announcement">'
            . '<h2 class="announcement__subject">' . htmlspecialchars((string) $row['subject'], ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<div class="announcement__body">' . $row['message'] . '</div>'
            . '<div class="announcement__meta"><time>' . htmlspecialchars(date(SET_DATE_FORMAT, (int) $row['time']), ENT_QUOTES, 'UTF-8') . '</time>' . $actions . '</div>'
            . '</article>';
    }

    if ($html === '') {
        $html = '<p class="record-list__empty">' . htmlspecialchars(APP_SC_TXT_85, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    if (Access::can(Permission::SERVICECENTRE_ANNOUNCE)) {
        $html = '<div class="form-actions" style="margin-top:0">'
            . RenderViews::buildURL(MAN_BASE_URL . '&option=new_announcement', APP_SC_TXT_75, '', 'btn btn--primary btn--sm')
            . '</div>'
            . $html;
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        [
            'title' => APP_SC_TXT_6,
            'html' => $html,
        ],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function editAnnouncement(string $id): void
{
    $row = Database::first('announcements', ['subject', 'message'], 'id = ?', [(int) $id]);
    if ($row === null) {
        RenderViews::buildResponse(TXT_115, RenderViews::buildURL(MAN_BASE_URL . '&option=show_announcements', APP_SC_TXT_26, 'URL'));
        return;
    }

    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "',[''],['subject'],[''],['" . TXT_547 . "'],[true]);\"";
    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_349,
        MAN_BASE_URL . '&option=update_announcement',
        [
            TXT_346 => RenderViews::buildTextInput('subject', (string) $row['subject']),
            TXT_347 => RenderViews::buildTextArea('message', (string) $row['message'], SET_FORM_FIELD_HEIGHT),
            '' => RenderViews::buildHiddenInput('id', (string) (int) $id),
        ],
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_348, $javascript),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function updateAnnouncement(): void
{
    Database::update('announcements', [
        'message' => (string) ($_POST['message'] ?? ''),
        'subject' => (string) ($_POST['subject'] ?? ''),
    ], 'id = ?', [(int) ($_POST['id'] ?? 0)]);
    showAnnouncements();
}

function deleteAnnouncement(string $id): void
{
    Database::delete('announcements', 'id = ?', [(int) $id]);
    showAnnouncements();
}
