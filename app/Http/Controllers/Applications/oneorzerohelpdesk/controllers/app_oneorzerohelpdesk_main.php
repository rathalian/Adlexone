<?php
declare(strict_types=1);

/**
 * Helpdesk Main Controller
 *
 * This controller handles the main operations for the Helpdesk application,
 * including routing based on subcontrollers and options, as well as rendering
 * various Helpdesk-related views and functionalities.
 *
 * @package Adlexone\applications\helpdesk\controllers
 */

use Adlexone\support\Database;
use Adlexone\support\RenderViews;
use Adlexone\support\SharedMethods;
use Adlexone\support\RenderNavigation;

/**
 * Service Centre links sit in the top navigation card. There is no left sidebar.
 */
RenderNavigation::applySectionNav('Service Centre', RenderNavigation::helpDeskNavigationURLS());

/**
 * Handles the routing logic for the Helpdesk application based on the `subcontroller` or `option` parameters.
 *
 * This code determines the appropriate action to take based on the `$_GET` parameters.
 * If a `subcontroller` is specified, it attempts to include the corresponding file.
 * Otherwise, it uses the `option` parameter to execute specific Helpdesk-related functions.
 */

// Check if a subcontroller is specified in the request
if (!empty($_GET['subcontroller'])) {
    // Construct the file path for the subcontroller
    $subcontrollerFile = SET_INSTALL_PATH . 'app/Http/Controllers/' . basename((string)$_GET['subcontroller']) . '.php';

    // Include the subcontroller file if it exists
    if (file_exists($subcontrollerFile)) {
        include_once $subcontrollerFile;
    }
} else {
    // Handle the request based on the `option` parameter
    switch ($_GET['option'] ?? '') {
        case 'show_announcement_item':
            // Ensure the user has the required role and display a specific announcement item
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
            showAnnouncementItem($_GET['id'] ?? '');
            break;
        case 'show_announcements':
            // Ensure the user has the required role and display all announcements
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
            showAnnouncements();
            break;
        case 'new_announcement':
            // Ensure the user has the required role and add a new announcement
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
            newAnnouncement();
            break;
        case 'add_announcement':
            // Ensure the user has the required role and add a new announcement
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
            addAnnouncement();
            break;
        case 'edit_announcement':
            // Ensure the user has the required role and edit an existing announcement
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
            editAnnouncement($_GET['id'] ?? '');
            break;
        case 'update_announcement':
            // Ensure the user has the required role and update an announcement
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
            updateAnnouncement();
            break;
        case 'delete_announcement':
            // Ensure the user has the required role and delete an announcement
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
            deleteAnnouncement($_GET['id'] ?? '');
            break;
        case 'show_helpdesk_saved_searches':
            // Ensure the user has the required role and display saved searches
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
            showHelpdeskSavedSearches();
            break;
        case 'show_search':
            // Ensure the user has the required role and display search results
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
            showSearchItems($_GET['id'] ?? '', $_SESSION['access_user_id'], $_GET['rss'] ?? '');
            break;
        case 'helpdesk_settings':
            // Ensure the user has the required role and display Helpdesk settings
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
            showHelpdeskSettings();
            break;
        case 'update_helpdesk_settings':
            // Ensure the user has the required role and update Helpdesk settings
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 0);
            updateSettings();
            break;
//        case 'show_portal':
//            // Ensure the user has the required role and display the Helpdesk portal
//            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
//            showHelpdeskModules();
//            break;
        case 'show_topx':
            // Ensure the user has the required role and display the top X items
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
            showTopX();
            break;
        default:
            // Default action: display the Helpdesk modules
            header('Location: index.php?controller=app_oneorzerohelpdesk_main&option=show_announcements');
            //showAnnouncements();
            break;
    }
}

/**
 * Generates a summary of announcements for the Helpdesk interface.
 *
 * This function retrieves announcements from the database and formats them
 * into clickable links. If no announcements are found, a placeholder message
 * is displayed. The generated content is returned as a vertical card layout.
 *
 * @return string Rendered HTML for the announcements summary.
 */
//function showAnnouncementSummary()
//{
//    $modules = [];
//
//    // Define the columns to fetch from the announcements table
//    $columnArray = ['id', 'subject', 'message'];
//    $sql = Database::sqlSelect('announcements', $columnArray);
//    $result = Database::query($sql, DSN, SET_SHOW_SQL);
//
//    // Check if there are any announcements
//    if (Database::numRows($result) == 0) {
//        // Add a placeholder message if no announcements are found
//        $modules[] = APP_HDSK_TXT_41;
//    } else {
//        // Generate clickable links for each announcement
//        while ($row = Database::fetchArray($result)) {
//            $modules[] = '<a href="' . '&option=show_announcement_item&id=' . $row['id'] . '" class="URL">' . $row['subject'] . '</a>';
//        }
//    }
//
//    // Create a content block for the announcements
//    $bodyBlock = [
//        [
//            'title' => APP_HDSK_TXT_6, // Title for the announcements section
//            'html' => RenderViews::buildHorizontalCards($modules), // Render announcements as horizontal cards
//            'full' => true, // Indicates the block should occupy full width
//        ]
//    ];
//
//    // Render the content block as vertical cards and return the HTML
//    return RenderViews::buildVerticalCards($bodyBlock);
//}
//
//function showHelpdeskModules()
//{
//    $modules = [];
//
//    if (is_numeric(HELPDESK_SET_SAVED_SEARCH)) {
//        $html = showTopX(true);
//        $bodyBlock = [
//            [
//                'title' => APP_HDSK_TXT_53,
//                'html' =>$html,
//                'full' => true,
//            ]
//        ];
//        $bodyContent = RenderViews::buildHorizontalCards($bodyBlock);
//    }
//    if (HELPDESK_SET_SAVED_SEARCHES == 'Yes') {
//
//        $html = showHelpdeskSavedSearches();
//
//        $bodyBlock = [
//            [
//                'title' => APP_HDSK_TXT_5,
//                'html' =>$html,
//                'full' => true,
//            ]
//        ];
//        $bodyContent .= RenderViews::buildHorizontalCards($bodyBlock);
//
//    }
//    if (HELPDESK_SET_ANNOUNCEMENTS == 'Yes') {
//       $html = showAnnouncementSummary();
//        $bodyBlock = [
//            [
//                'title' => APP_HDSK_TXT_65,
//                'html' =>$html,
//                'full' => true,
//            ]
//        ];
//        $bodyContent .= RenderViews::buildHorizontalCards($bodyBlock);
//
//    }
//
////    $bodyBlock = [
////        [
////            'title' => APP_HDSK_TXT_65,
////            'html' => RenderViews::buildHorizontalCards($modules),
////            'full' => true,
////        ]
//   // ];
//
//    define('BODY_HEADING', APP_HDSK_TXT_65);
//    define('BODY_CONTENT', $bodyContent);
//    RenderViews::renderThemePage('main_page_content', SET_THEME);
//}


//function showHelpdeskQuickLaunch()
//{
//
//    $imageURL = RenderViews::buildURL('&subcontroller=item_management_manage&option=show_item_types&default_item_type=' . HELPDESK_SET_ITEM_TYPE, '', 'launchURL', SET_IMAGE_PATH . 'newTicketBig.png');
//    $url = RenderViews::buildURL('&subcontroller=item_management_manage&option=show_item_types&default_item_type=' . HELPDESK_SET_ITEM_TYPE, APP_HDSK_TXT_59, 'URLHeading') . '<br />' . APP_HDSK_TXT_58;
//    $quickLaunchArray[] = RenderViews::outputIfRoleAllowed($url . $imageURL, $_SESSION['access_role_id'], 4);
//
//
//    $imageURL = RenderViews::buildURL('&subcontroller=search_management_manage&option=show_saved_searches', '', 'launchURL', SET_IMAGE_PATH . 'savedSearchBig.png');
//    $url = RenderViews::buildURL('&subcontroller=search_management_manage&option=show_saved_searches', APP_HDSK_TXT_60, 'URLHeading') . '<br />' . APP_HDSK_TXT_66;
//    $quickLaunchArray[] = RenderViews::outputIfRoleAllowed($url . $imageURL, $_SESSION['access_role_id'], 5);
//
//
//    $imageURL = RenderViews::buildURL('&subcontroller=search_management_manage&option=show_quick_search', '', 'launchURL', SET_IMAGE_PATH . 'searchBig.png');
//    $url = RenderViews::buildURL('&subcontroller=search_management_manage&option=show_quick_search', APP_HDSK_TXT_62, 'URLHeading') . '<br />' . APP_HDSK_TXT_63;
//    $quickLaunchArray[] = RenderViews::outputIfRoleAllowed($url . $imageURL, $_SESSION['access_role_id'], 5);
//
//
//    $imageURL = RenderViews::buildURL('&subcontroller=search_management_manage&option=show_item_search&item_types=' . HELPDESK_SET_ITEM_TYPE, '', 'launchURL', SET_IMAGE_PATH . 'advancedSearchBig.png');
//    $url = RenderViews::buildURL('&subcontroller=search_management_manage&option=show_item_search&item_types=' . HELPDESK_SET_ITEM_TYPE, APP_HDSK_TXT_61, 'URLHeading') . '<br />' . APP_HDSK_TXT_64;
//    $quickLaunchArray[] = RenderViews::outputIfRoleAllowed($url . $imageURL, $_SESSION['access_role_id'], 4);
//
//
//    $imageURL = RenderViews::buildURL('&subcontroller=app_oneorzerohelpdesk_manage&option=show_announcements', '', 'launchURL', SET_IMAGE_PATH . 'announcementsBig.png');
//    $url = RenderViews::buildURL('&subcontroller=app_oneorzerohelpdesk_manage&option=show_announcements', APP_HDSK_TXT_68, 'URLHeading') . '<br />' . APP_HDSK_TXT_69;
//    $quickLaunchArray[] = RenderViews::outputIfRoleAllowed($url . $imageURL, $_SESSION['access_role_id'], 4);
//
//
//    $imageURL = RenderViews::buildURL('&subcontroller=app_oneorzerohelpdesk_manage&option=show_portal', '', 'launchURL', SET_IMAGE_PATH . 'portalBig.png');
//    $url = RenderViews::buildURL('&subcontroller=app_oneorzerohelpdesk_manage&option=show_portal', APP_HDSK_TXT_55, 'URLHeading') . '<br />' . APP_HDSK_TXT_71;
//    $quickLaunchArray[] = RenderViews::outputIfRoleAllowed($url . $imageURL, $_SESSION['access_role_id'], 5);
//
//    $bodyBlock[] = [
//        'title' => APP_HDSK_TXT_70,
//        'html' => RenderViews::renderHorizontalList($quickLaunchArray),
//        'full' => true,
//    ];
//
//    $html = RenderViews::buildHorizontalCards($bodyBlock);
//
//    // Add saved searches if configured
//
////    if (is_numeric(HELPDESK_SET_SAVED_SEARCH)) {
////        $savedSearch = showTopX();
////        $tableRows = RenderViews::tableData('2', array('95%'), array('center'), '', '', array($savedSearch), 'row');
////        $html .= '<br>';
////        $html .= RenderViews::table('100%', '0', '0', '0', '', $tableRows);
////    }
//
//    // $test = RenderViews::leftNavButton(SUB_'&option=show_portal','tesdt','Test', false);
//
//
////    RenderViews::renderThemePage('main_page_content',  SET_THEME);
//
//    define('BODY_HEADING', APP_HDSK_TXT_70);
//    define('BODY_CONTENT', $html);
//    RenderViews::renderThemePage('main_page_content',  SET_THEME);
//
//}

function showAnnouncementItem($id = '')
{
    // Grab data from database
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

/**
 * Show announcements page
 *
 * @return string
 */
function addAnnouncement()
{
    // Add announcemnent and refresh announcements page
    $columnArray['id'] = Database::newID('announcements', 'id');
    $columnArray['time'] = time();
    $columnArray['message'] = $_POST['message'];
    $columnArray['subject'] = $_POST['subject'];
    $columnArray['type'] = 'user';
    $sql = Database::sqlInsert('announcements', $columnArray);
    Database::query($sql, DSN, SET_SHOW_SQL);
    showAnnouncements();
}

/**
 * Display new announcement form
 *
 * @return void
 */
function newAnnouncement()
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
    $bodyContent = RenderViews::buildForm(APP_HDSK_TXT_75,'index.php?controller=app_oneorzerohelpdesk_main&option=add_announcement',$fields,$buttons);
    define('BODY_CONTENT', $bodyContent);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Show announcements page
 *
 * @param string $userID User ID
 * @return void
 */
function showAnnouncements($userID = '')
{
    $html = '';
    $sql = "SELECT * FROM announcements";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $html = '';

    while ($row = Database::fetchArray($result)) {
        $editURL = $deleteURL = '';

        if ($_SESSION['access_role_id'] < 2) {
            $editURL = RenderViews::buildURL(
                'index.php?controller=app_oneorzerohelpdesk_main&option=edit_announcement&id=' . $row['id'],
                APP_HDSK_TXT_22,
                '',
                'btn btn--sm btn--quiet'
            );
            $deleteURL = RenderViews::buildURL(
                'index.php?controller=app_oneorzerohelpdesk_main&option=delete_announcement&id=' . $row['id'],
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
        $html = '<p class="record-list__empty">' . htmlspecialchars(APP_HDSK_TXT_85, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $bodyBlock = [
        [
            'title' => APP_HDSK_TXT_6,
            'html' => $html,
        ]
    ];

    //define('BODY_HEADING', APP_HDSK_TXT_6);
    define('BODY_CONTENT', RenderViews::buildVerticalCards($bodyBlock));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Deletes an announcement
 *
 * @param integer $id Announcement ID
 */
function deleteAnnouncement($id)
{
    $sql = "DELETE FROM announcements WHERE id = '$id'";
    Database::query($sql, DSN, SET_SHOW_SQL);
    showAnnouncements();
}


function editAnnouncement($id)
{
    $id = (int) $id;

    // Fetch the announcement row safely
    $columnArray = ['subject', 'message'];
    $condition = "WHERE id = '" . $id . "'";
    $sql = Database::sqlSelect('announcements', $columnArray, $condition);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);

    // If not found, show friendly message with back link
    if (!$row) {
        RenderViews::buildResponse(
            TXT_115,
            RenderViews::buildURL('javascript: history.go(-1)', TXT_404, 'URL')
        );
        return;
    }

    // Build form using new render pattern
    $fields = [
        TXT_346 => RenderViews::buildTextInput('subject', $row['subject'], ''),
        TXT_347 => RenderViews::buildTextArea('message', $row['message'], SET_FORM_FIELD_HEIGHT),
        '' => RenderViews::buildHiddenInput('id', (string) $id),
    ];
    $jsFieldNameArray = "['subject']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . TXT_547 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";

    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_349,
        'index.php?controller=app_oneorzerohelpdesk_main&option=update_announcement',
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_348, $javascript),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}
function updateAnnouncement()
{
    // Update announcemnent and refresh announcements page
    $columnArray['message'] = $_POST['message'];
    $columnArray['subject'] = $_POST['subject'];
    $condition = "WHERE id ='{$_POST['id']}'";
    $sql = Database::sqlUpdate('announcements', $columnArray, $condition);
    Database::query($sql, DSN, SET_SHOW_SQL);
    showAnnouncements();
}

/**
 * Generates a saved searches table with items such as my open items etc
 *
 * @return Saved searches table
 */
function showHelpdeskSavedSearches()
{
    $modules = [];

    $sql = "SELECT * FROM saved_searches WHERE (user ='" . $_SESSION['access_user_id'] . "' OR user = 'all' OR user = 'system') AND application = 'app_oneorzerohelpdesk_main' ORDER BY search_name ASC";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    if (Database::numRows($result) == 0) {
        $modules[] = APP_HDSK_TXT_40;
    } else {
        while ($row = Database::fetchArray($result)) {
            if ($row['user'] == 'all') {
                $url = RenderViews::buildURL(
                    'index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=saved_search&global=1&id=' . $row['search_id'],
                    $row['search_name'] . ' (' . TXT_408 . ')',
                    'URL'
                );
            } else {
                $url = RenderViews::buildURL(
                    'index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=saved_search&id=' . $row['search_id'],
                    $row['search_name'],
                    'URL'
                );
            }
            $modules[] = $url;
        }
    }

    $bodyBlock = [
        [
            'title' => APP_HDSK_TXT_5,
            'html' => RenderViews::buildHorizontalCards($modules),
            'full' => true,
        ]
    ];

    return RenderViews::buildVerticalCards($bodyBlock);
}

///**
// * Generates the top ten search results ordered by highest to lowest id
// *
// * @return string Ten table
// */
//function showTopX($returnHtml = false)
//{
//    $sql = "SELECT saved_search_sql, search_name FROM saved_searches WHERE search_id = '" . HELPDESK_SET_SAVED_SEARCH . "'";
//    $result = Database::query($sql, DSN, SET_SHOW_SQL);
//    $row = Database::fetchArray($result);
//
//    $headTitle = [];
//    $columnArray = [];
//    if (!empty($row) && is_array($row) && !empty($row['saved_search_sql'])) {
//        $savedSearch2 = substr($row['saved_search_sql'], 6);
//        $orderSQL = (stripos($savedSearch2, "order by")) ? substr($savedSearch2, strripos($savedSearch2, "order by") + 8) : "";
//        $savedSearch2 = substr($savedSearch2, 0, strripos($savedSearch2, "from"));
//        $tmpArray = explode(',', $savedSearch2);
//        foreach ($tmpArray as $value) {
//            $tmpvars1 = explode(" ", $value);
//            $columnArray[] = $tmpvars1[1];
//            switch (trim($value)) {
//                case 'item_id':
//                    $headTitle[] = LA_102;
//                    break;
//                case 'item_title':
//                    $headTitle[] = LA_84;
//                    break;
//                case 'create_date':
//                    $headTitle[] = TXT_225;
//                    break;
//                case 'item_type_id':
//                    $headTitle[] = LA_226;
//                    break;
//                case 'creator_security':
//                    $headTitle[] = LA_551;
//                    break;
//                case 'user_security':
//                    $headTitle[] = LA_269;
//                    break;
//                default:
//                    $tmpvars = explode("_", $tmpvars1[1]);
//                    $customFieldId = end($tmpvars);
//                    $headsql = "SELECT custom_field_name FROM custom_fields WHERE custom_field_id = $customFieldId";
//                    $headTitle[] = Database::firstResult($headsql);
//            }
//        }
//    }
//
//    $show = RenderViews::buildURL('#', TXT_373, 'URL', '', 'onclick="showRow(\'top_ten\');return false;"');
//    $hide = RenderViews::buildURL('#', TXT_374, 'URL', '', 'onclick="hideRow(\'top_ten\');return false;"');
//
//    $bodyBlocks = [];
//    if (empty($row) || !is_array($row) || Database::numRows($result) == 0) {
//        $searchName = !empty($row['search_name']) ? $row['search_name'] : '';
//        $bodyBlocks[] = [
//            'title' => APP_HDSK_TXT_53 . ' ' . HELPDESK_SET_RESULT_COUNT . ' - ' . $searchName . ' (' . $show . '\\' . $hide . ' ' . APP_HDSK_TXT_67 . ')',
//            'html' => APP_HDSK_TXT_54,
//        ];
//    } else {
//        $sql = str_replace('session_user', (string)$_SESSION['access_user_id'], $row['saved_search_sql']);
//        $result = Database::query($sql, DSN, SET_SHOW_SQL);
//
//        $sql = "SELECT groups FROM group_members WHERE user_id = '" . $_SESSION['access_user_id'] . "'";
//        $groupResult = Database::query($sql, DSN, SET_SHOW_SQL);
//        $groupRow = Database::fetchArray($groupResult);
//        $groupArray = explode('}-{', $groupRow['groups']);
//
//        $i = 0;
//        while ($row = Database::fetchArray($result)) {
//            $cellData = [];
//            foreach ($columnArray as $value) {
//                switch ($value) {
//                    case 'item_id':
//                        $cellData[] = $row['item_id'];
//                        break;
//                    case 'item_title':
//                        $title = $row['item_title'] ?: TXT_357;
//                        $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
//                        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
//                        $cellData[] = '<a href ="index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&item_id=' . $row['item_id'] . $logEntry . $attachments . '" class="URL">' . $title . '</a>';
//                        break;
//                    case 'create_date':
//                        $cellData[] = date(SET_DATE_FORMAT, $row['create_date']);
//                        break;
//                    case 'item_type_id':
//                        if (!isset($itemTypeID) || $itemTypeID != $row['item_type_id']) {
//                            $tmpcolumnArray = ['item_type_name'];
//                            $condition = "WHERE item_type_id = '" . $row['item_type_id'] . "'";
//                            $sql = Database::sqlSelect('item_types', $tmpcolumnArray, $condition);
//                            $itemTypeResult = Database::query($sql, DSN, SET_SHOW_SQL);
//                            $itemTypeRow = Database::fetchArray($itemTypeResult);
//                            $itemTypeName = $itemTypeRow['item_type_name'];
//                            $itemTypeID = $row['item_type_id'];
//                        }
//                        $cellData[] = $itemTypeName;
//                        break;
//                    case 'creator_security':
//                        $creatorColumnArray = ['user_name'];
//                        $condition = "WHERE user_id = '" . $row['creator_security'] . "'";
//                        $sql = Database::sqlSelect('users', $creatorColumnArray, $condition);
//                        $creatorResult = Database::query($sql, DSN, SET_SHOW_SQL);
//                        $creatorRow = Database::fetchArray($creatorResult);
//                        $cellData[] = $creatorRow['user_name'];
//                        break;
//                    case 'user_security':
//                        $userColumnArray = ['user_name'];
//                        $condition = "WHERE user_id = '" . $row['user_security'] . "'";
//                        $sql = Database::sqlSelect('users', $userColumnArray, $condition);
//                        $userResult = Database::query($sql, DSN, SET_SHOW_SQL);
//                        $userRow = Database::fetchArray($userResult);
//                        $cellData[] = $userRow['user_name'];
//                        break;
//                    default:
//                        $cellData[] = $row[$value];
//                }
//            }
//            $allowedAccess = false;
//            $sql = "SELECT user_security,creator_security,group_security FROM items WHERE item_id = '" . $row['item_id'] . "'";
//            $itemResult = Database::query($sql, DSN, SET_SHOW_SQL);
//            $itemRow = Database::fetchArray($itemResult);
//            if ($itemRow['user_security'] == $_SESSION['access_user_id'] || $itemRow['creator_security'] == $_SESSION['access_user_id']) {
//                $allowedAccess = true;
//            } else {
//                foreach ($groupArray as $a) {
//                    if (stristr($itemRow['group_security'], '}-{' . $a . '}-{')) {
//                        $allowedAccess = true;
//                    }
//                }
//            }
//            if ($allowedAccess && $i < HELPDESK_SET_RESULT_COUNT) {
//                $bodyBlocks[] = [
//                    'title' => null,
//                    'html' => implode(' | ', $cellData),
//                ];
//                $i++;
//            }
//        }
//    }
//
//    $searchName = (!empty($row) && is_array($row) && !empty($row['search_name'])) ? $row['search_name'] : '';
//    $bodyBlock = [
//        [
//            'title' => APP_HDSK_TXT_53 . ' ' . HELPDESK_SET_RESULT_COUNT . ' - ' . $searchName . ' (' . $show . '\\' . $hide . ' ' . APP_HDSK_TXT_67 . ')',
//            'html' => RenderViews::buildHorizontalCards($bodyBlocks),
//            'full' => true,
//        ]
//    ];
//
//    $html = RenderViews::buildHorizontalCards($bodyBlock);
//
//    if ($returnHtml) {
//        return $html;
//    }else {
//        define('BODY_CONTENT', $html);
//        RenderViews::renderThemePage('main_page_content', SET_THEME);
//    }
//
//
//}

function showSearchItems($id, $userID, $rss = false)
{
    $html = '';
    // Do a check to see if the calling user has a matching search
    $sql = "SELECT saved_search_sql, search_name FROM saved_searches WHERE user = '$userID' OR user = 'all' AND search_id ='$id'";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);
    if (Database::numRows($result) > 0) {
        $itemHTML = showItems(Database::buildArray($row['saved_search_sql'], DSN, SET_SHOW_SQL), 'create_date DESC', $rss, $row['search_name']);
        $bodyBlock = [
            [
                'title' => APP_HDSK_TXT_78,
                'html' => $itemHTML,
            ]
        ];
        $bodyContent = RenderViews::buildVerticalCards($bodyBlock);
        define('BODY_CONTENT', $bodyContent);
        RenderViews::renderThemePage('main_page_content', SET_THEME);
    } else {
        $quickSearch = 'index.php?controller=app_oneorzerohelpdesk_main&subcontroller=search_management_manage&option=show_quick_search';
        $savedSearches = 'index.php?controller=app_oneorzerohelpdesk_main&subcontroller=search_management_manage&option=show_saved_searches';
        $bodyBlock = [
            [
                'title' => APP_HDSK_TXT_78,
                'html' => '<p class="record-list__empty">' . htmlspecialchars(APP_HDSK_TXT_84, ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<div class="form-actions">'
                    . RenderViews::buildURL($quickSearch, APP_HDSK_TXT_62, '', 'btn btn--primary btn--sm')
                    . RenderViews::buildURL($savedSearches, APP_HDSK_TXT_60, '', 'btn btn--sm')
                    . '</div>',
            ]
        ];
        $bodyContent = RenderViews::buildVerticalCards($bodyBlock);
        define('BODY_CONTENT', $bodyContent);
        RenderViews::renderThemePage('main_page_content', SET_THEME);
    }
}

/**
 * Renders the Helpdesk settings page.
 *
 * This function generates a settings page for the Helpdesk application, allowing users
 * to configure various options such as item types, saved searches, announcements,
 * result count, and default screen. The settings are displayed in a form with dropdowns
 * and input fields, and the form is rendered using the `RenderViews` utility.
 *
 * @return void
 */
function showHelpdeskSettings()
{
    // Define the columns to fetch from the item_types table
    $columnArray = array('item_type_id', 'item_type_name');
    $sql = Database::sqlSelect('item_types', $columnArray);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    // Populate dropdown options for item types
    while ($row = Database::fetchArray($result)) {
        $valueArray[] = $row['item_type_id'];
        $displayArray[] = $row['item_type_name'];
    }

    // Render dropdowns for various settings
    $fields[APP_HDSK_TXT_32] = RenderViews::buildSelectDropdown('HELPDESK_SET_ITEM_TYPE', $valueArray, $displayArray, HELPDESK_SET_ITEM_TYPE);
    $fields[APP_HDSK_TXT_36] = RenderViews::buildSelectDropdown('HELPDESK_SET_SAVED_SEARCHES', array('Yes', 'No'), array(APP_HDSK_TXT_42, APP_HDSK_TXT_43), HELPDESK_SET_SAVED_SEARCHES);
    $fields[APP_HDSK_TXT_38] = RenderViews::buildSelectDropdown('HELPDESK_SET_ANNOUNCEMENTS', array('Yes', 'No'), array(APP_HDSK_TXT_42, APP_HDSK_TXT_43), HELPDESK_SET_ANNOUNCEMENTS);

    // Initialize dropdown options for saved searches
    $listValues[0] = '';
    $listDisplayValues[0] = APP_HDSK_TXT_52;
    $sql = "SELECT search_id, search_name FROM saved_searches WHERE user = 'all' OR user = 'system' ORDER BY search_name ASC";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $i = 1;

    // Populate dropdown options for saved searches
    while ($row = Database::fetchArray($result)) {
        $listValues[$i] = $row['search_id'];
        $listDisplayValues[$i] = $row['search_name'];
        $i++;
    }

    // Render dropdown for saved searches
    $fields[APP_HDSK_TXT_51] = RenderViews::buildSelectDropdown('HELPDESK_SET_SAVED_SEARCH', $listValues, $listDisplayValues, HELPDESK_SET_SAVED_SEARCH);

    // Render input fields and buttons for other settings
    $fields[APP_HDSK_TXT_74] = RenderViews::buildTextInput('HELPDESK_SET_RESULT_COUNT', HELPDESK_SET_RESULT_COUNT);
    $fields[APP_HDSK_TXT_57] = RenderViews::buildSelectDropdown('HELPDESK_SET_DEFAULT_SCREEN', array('portal', 'quick_launch'), array(APP_HDSK_TXT_55, APP_HDSK_TXT_56), HELPDESK_SET_DEFAULT_SCREEN);

    define('BODY_CONTENT', RenderViews::buildForm(
        APP_HDSK_TXT_31,
        'index.php?controller=app_oneorzerohelpdesk_main&option=update_helpdesk_settings',
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', APP_HDSK_TXT_76),
            RenderViews::buildFormButton('reset', 'reset', APP_HDSK_TXT_77),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}


/**
 * Updates the Helpdesk settings based on the submitted form data.
 *
 * This function processes the `$_POST` data to update the Helpdesk settings.
 * It removes unnecessary fields, consolidates item type IDs into a single string,
 * and saves the updated settings to a JSON file. Finally, it displays a success
 * or failure message and includes the main page content.
 *
 * @return void
 */
function updateSettings()
{
    // Remove unnecessary fields from the POST data
    unset($_POST['submit_button'], $_POST['reset']);
    $i = 0;

    // Process item type IDs and consolidate them into a single string
    foreach ($_POST as $key => $value) {
        if (stristr($key, 'item_type_id_')) {
            @$_POST['HELPDESK_SET_ITEM_TYPE'] .= ($i == 0) ? $value : ',' . $value;
            unset($_POST[$key]);
        }
        $i++;
    }

    // Save the updated settings to a JSON file
    if (SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'helpdesk/helpdesk_settings.json', $_POST)) {
        $message = TXT_201; // Success message
    } else {
        $message = TXT_203; // Failure message
    }

  // Render the success or failure message and include the main page
  RenderViews::buildResponse($message);
}
