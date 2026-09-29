<?php
declare(strict_types=1);

/**
 * Service Centre controller.
 *
 * Tickets, searches, announcements, and settings. Child screens keep the
 * matching section link selected.
 */

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\support\RenderViews;
use Adlexone\support\SharedMethods;

const SERVICE_CENTRE_CONTROLLER = 'app_servicecentre_main';

function serviceCentreUrl(string $query = ''): string
{
    if (defined('APPLICATION_SLUG')) {
        $url = 'index.php?controller=application&app=' . rawurlencode((string) APPLICATION_SLUG);
        if (defined('APPLICATION_NAV_ID') && (int) APPLICATION_NAV_ID > 0) {
            $url .= '&nav=' . (int) APPLICATION_NAV_ID;
        }
    } else {
        $url = 'index.php?controller=' . SERVICE_CENTRE_CONTROLLER;
    }
    return $query === '' ? $url : $url . '&' . ltrim($query, '&');
}

$sectionOption = (string)($_GET['section'] ?? $_GET['option'] ?? '');
$sectionNav = [
    'new_item' => 'show_item_types',
    'add_item' => 'show_item_types',
    'show_quick_search' => 'show_saved_searches',
    'quick_search' => 'show_saved_searches',
    'show_saved_searches' => 'show_saved_searches',
    'saved_search' => 'show_saved_searches',
    'edit_saved_search' => 'show_saved_searches',
    'update_saved_search' => 'show_saved_searches',
    'delete_saved_search' => 'show_saved_searches',
    'show_item_search' => 'show_saved_searches',
    'show_search_results' => 'show_saved_searches',
    'new_announcement' => 'show_announcements',
    'add_announcement' => 'show_announcements',
    'edit_announcement' => 'show_announcements',
    'update_announcement' => 'show_announcements',
    'delete_announcement' => 'show_announcements',
    'show_announcement_item' => 'show_announcements',
    'show_search' => 'show_tickets',
    'show_tickets' => 'show_tickets',
    'helpdesk_settings' => 'settings',
    'update_helpdesk_settings' => 'settings',
    'settings' => 'settings',
    'update_settings' => 'settings',
];
if (!defined('SECTION_NAV_OPTION')) {
    define('SECTION_NAV_OPTION', $sectionNav[$sectionOption] ?? $sectionOption);
}
RenderNavigation::applySectionNav('Service Centre', RenderNavigation::serviceCentreNavigationURLS());

if (!empty($_GET['subcontroller'])) {
    $subcontrollerFile = SET_INSTALL_PATH . 'app/Http/Controllers/' . basename((string)$_GET['subcontroller']) . '.php';
    if (file_exists($subcontrollerFile)) {
        include_once $subcontrollerFile;
    }
} else {
    switch ($_GET['option'] ?? '') {
        case 'show_announcement_item':
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
            showAnnouncementItem($_GET['id'] ?? '');
            break;
        case 'show_announcements':
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
            showAnnouncements();
            break;
        case 'new_announcement':
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
            newAnnouncement();
            break;
        case 'add_announcement':
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
            addAnnouncement();
            break;
        case 'edit_announcement':
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
            editAnnouncement($_GET['id'] ?? '');
            break;
        case 'update_announcement':
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
            updateAnnouncement();
            break;
        case 'delete_announcement':
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
            deleteAnnouncement($_GET['id'] ?? '');
            break;
        case 'show_search':
        case 'show_tickets':
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
            showSearchItems($_GET['id'] ?? '', $_SESSION['access_user_id']);
            break;
        case 'helpdesk_settings':
        case 'settings':
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
            showServiceCentreSettings();
            break;
        case 'update_helpdesk_settings':
        case 'update_settings':
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 0);
            updateSettings();
            break;
        default:
            header('Location: ' . serviceCentreUrl('option=show_tickets'));
            break;
    }
}

function showAnnouncementItem($id = ''): void
{
    $sql = "SELECT * FROM announcements WHERE id='" . $id . "'";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);

    $heading = date(SET_DATE_FORMAT, $row['1']) . ': ' . $row[4];
    $bodyBlock = [
        [
            'title' => $heading,
            'html' => RenderViews::buildFormFieldsGrid([
                '' => RenderViews::buildTextArea('', $row['message'], '', true) . ' (' . date(SET_DATE_FORMAT, $row['time']) . ')'
            ])
        ]
    ];

    define('BODY_HEADING', $heading);
    define('BODY_CONTENT', RenderViews::buildVerticalCards($bodyBlock));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function addAnnouncement(): void
{
    $columnArray['id'] = Database::newID('announcements', 'id');
    $columnArray['time'] = time();
    $columnArray['message'] = $_POST['message'];
    $columnArray['subject'] = $_POST['subject'];
    $columnArray['type'] = 'user';
    $sql = Database::sqlInsert('announcements', $columnArray);
    Database::query($sql, DSN, SET_SHOW_SQL);
    showAnnouncements();
}

function newAnnouncement(): void
{
    $fields[TXT_346] = RenderViews::buildTextInput('subject', '');
    $fields[TXT_347] = RenderViews::buildTextArea('message', '');
    $jsFieldNameArray = "['subject']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . TXT_547 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_345, $javascript);
    $buttons[] = RenderViews::buildFormButton('reset', 'reset', TXT_75);
    define('BODY_CONTENT', RenderViews::buildForm(
        APP_SC_TXT_75,
        serviceCentreUrl('option=add_announcement'),
        $fields,
        $buttons
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showAnnouncements(): void
{
    $sql = "SELECT * FROM announcements";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $html = '';

    while ($row = Database::fetchArray($result)) {
        $editURL = $deleteURL = '';

        if ($_SESSION['access_role_id'] < 2) {
            $editURL = RenderViews::buildURL(
                serviceCentreUrl('option=edit_announcement&id=' . $row['id']),
                APP_SC_TXT_22,
                '',
                'btn btn--sm btn--quiet'
            );
            $deleteURL = RenderViews::buildURL(
                serviceCentreUrl('option=delete_announcement&id=' . $row['id']),
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
            . '<h2 class="announcement__subject">' . htmlspecialchars((string)$row['subject'], ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<div class="announcement__body">' . $row['message'] . '</div>'
            . '<div class="announcement__meta"><time>' . htmlspecialchars(date(SET_DATE_FORMAT, (int)$row['time']), ENT_QUOTES, 'UTF-8') . '</time>' . $actions . '</div>'
            . '</article>';
    }

    if ($html === '') {
        $html = '<p class="record-list__empty">' . htmlspecialchars(APP_SC_TXT_85, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    if (Access::can(Permission::SERVICECENTRE_ANNOUNCE)) {
        $html = '<div class="form-actions" style="margin-top:0">'
            . RenderViews::buildURL(serviceCentreUrl('option=new_announcement'), APP_SC_TXT_75, '', 'btn btn--primary btn--sm')
            . '</div>'
            . $html;
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        [
            'title' => APP_SC_TXT_6,
            'html' => $html,
        ]
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function deleteAnnouncement($id): void
{
    $sql = "DELETE FROM announcements WHERE id = '$id'";
    Database::query($sql, DSN, SET_SHOW_SQL);
    showAnnouncements();
}

function editAnnouncement($id): void
{
    $id = (int) $id;
    $sql = Database::sqlSelect('announcements', ['subject', 'message'], "WHERE id = '" . $id . "'");
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);

    if (!$row) {
        RenderViews::buildResponse(
            TXT_115,
            RenderViews::buildURL('javascript: history.go(-1)', TXT_404, 'URL')
        );
        return;
    }

    $fields = [
        TXT_346 => RenderViews::buildTextInput('subject', $row['subject'], ''),
        TXT_347 => RenderViews::buildTextArea('message', $row['message'], SET_FORM_FIELD_HEIGHT),
        '' => RenderViews::buildHiddenInput('id', (string) $id),
    ];
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "',[''],['subject'],[''],['" . TXT_547 . "'],[true]);\"";

    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_349,
        serviceCentreUrl('option=update_announcement'),
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_348, $javascript),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function updateAnnouncement(): void
{
    $columnArray['message'] = $_POST['message'];
    $columnArray['subject'] = $_POST['subject'];
    $sql = Database::sqlUpdate('announcements', $columnArray, "WHERE id ='{$_POST['id']}'");
    Database::query($sql, DSN, SET_SHOW_SQL);
    showAnnouncements();
}

function showSearchItems($id, $userID): void
{
    if ((string)$id === '' && defined('SERVICECENTRE_SET_SAVED_SEARCH') && ctype_digit((string)SERVICECENTRE_SET_SAVED_SEARCH)) {
        header('Location: ' . serviceCentreUrl(
            'subcontroller=search_management_manage&option=saved_search&id='
            . rawurlencode((string)SERVICECENTRE_SET_SAVED_SEARCH)
            . '&section=show_tickets'
        ));
        exit;
    }
    $id = (int)$id;
    $userID = (int)$userID;
    $sql = "SELECT saved_search_sql, search_name FROM saved_searches WHERE search_id = '$id' AND (user = '$userID' OR user = 'all' OR user = 'system')";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);
    if (Database::numRows($result) > 0) {
        $itemHTML = showItems(Database::buildArray($row['saved_search_sql'], DSN, SET_SHOW_SQL), 'create_date DESC', false, $row['search_name']);
        define('BODY_CONTENT', RenderViews::buildVerticalCards([
            [
                'title' => APP_SC_TXT_1,
                'html' => $itemHTML,
            ]
        ]));
        RenderViews::renderThemePage('main_page_content', SET_THEME);
        return;
    }

    $quickSearch = serviceCentreUrl('subcontroller=search_management_manage&option=show_quick_search');
    $savedSearches = serviceCentreUrl('subcontroller=search_management_manage&option=show_saved_searches');
    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        [
            'title' => APP_SC_TXT_1,
            'html' => '<p class="record-list__empty">' . htmlspecialchars(APP_SC_TXT_84, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<div class="form-actions">'
                . RenderViews::buildURL($quickSearch, APP_SC_TXT_62, '', 'btn btn--primary btn--sm')
                . RenderViews::buildURL($savedSearches, APP_SC_TXT_60, '', 'btn btn--sm')
                . '</div>',
        ]
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showServiceCentreSettings(): void
{
    $valueArray = [];
    $displayArray = [];
    $sql = Database::sqlSelect('item_types', ['item_type_id', 'item_type_name']);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    while ($row = Database::fetchArray($result)) {
        $valueArray[] = $row['item_type_id'];
        $displayArray[] = $row['item_type_name'];
    }

    $listValues = [''];
    $listDisplayValues = [APP_SC_TXT_52];
    $sql = "SELECT search_id, search_name FROM saved_searches WHERE (user = 'all' OR user = 'system') AND application = '" . SERVICE_CENTRE_CONTROLLER . "' ORDER BY search_name ASC";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    while ($row = Database::fetchArray($result)) {
        $listValues[] = $row['search_id'];
        $listDisplayValues[] = $row['search_name'];
    }

    $itemType = defined('SERVICECENTRE_SET_ITEM_TYPE') ? (string)SERVICECENTRE_SET_ITEM_TYPE : '';
    $savedSearch = defined('SERVICECENTRE_SET_SAVED_SEARCH') ? (string)SERVICECENTRE_SET_SAVED_SEARCH : '';
    $fields[APP_SC_TXT_32] = RenderViews::buildSelectDropdown('SERVICECENTRE_SET_ITEM_TYPE', $valueArray, $displayArray, $itemType);
    $fields[APP_SC_TXT_51] = RenderViews::buildSelectDropdown('SERVICECENTRE_SET_SAVED_SEARCH', $listValues, $listDisplayValues, $savedSearch);

    define('BODY_CONTENT', RenderViews::buildForm(
        APP_SC_TXT_79,
        serviceCentreUrl('option=update_settings'),
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', APP_SC_TXT_76),
            RenderViews::buildFormButton('reset', 'reset', APP_SC_TXT_77),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function updateSettings(): void
{
    unset($_POST['submit_button'], $_POST['reset']);
    $settings = [
        'SERVICECENTRE_SET_ITEM_TYPE' => (string)($_POST['SERVICECENTRE_SET_ITEM_TYPE'] ?? ''),
        'SERVICECENTRE_SET_SAVED_SEARCH' => (string)($_POST['SERVICECENTRE_SET_SAVED_SEARCH'] ?? ''),
    ];
    $path = SET_CONFIGURATION_PATH . 'servicecentre' . DIRECTORY_SEPARATOR . 'servicecentre_settings.json';
    SharedMethods::saveSettingsToJson($path, $settings);
}
