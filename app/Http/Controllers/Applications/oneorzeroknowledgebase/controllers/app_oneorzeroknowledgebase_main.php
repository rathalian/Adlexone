<?php /** @noinspection ALL */
/** @noinspection ALL */

/**
 * Adlexone FlowIQ License Agreement 1.0
 *
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where
 *  Adlexone FlowIQ files contain the Adlexone FlowIQ license in the header of the file, the
 *  Adlexone FlowIQ License header must remain.
 * 3. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the data created or managed by your Adlexone FlowIQ installation.
 * 4. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for your Adlexone FlowIQ configuration or the hosting environment
 *  your Adlexone FlowIQ installation operates in.
 * 5. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the Adlexone FlowIQ where the software has been modified by
 *  third parties (i.e. other than Adlexone), unless an agreement has been
 *  reached with Adlexone.
 * 6. By using the Adlexone FlowIQ, you are indicating your acceptance of the
 *  stated Adlexone FlowIQ License terms and conditions.
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */

use Adlexone\support\RenderViews;
use Adlexone\support\Database;
use Adlexone\support\SharedMethods;

/**
 * Initiate Application Constants and variables
 */
SharedMethods::loadConstantFromIni(SET_INSTALL_PATH. 'translations/applications/knowledgemanager/' . SET_LANGUAGE . '.lang.php');


define('SUB_CONTROLLER_BASEURL', FULL_SCRIPT_PATH . '?controller=app_oneorzeroknowledgebase_main');

//$buildLeftNavigationButton = RenderViews::outputIfRoleAllowed(RenderViews::leftNavButton( SUB_CONTROLLER_BASEURL. '&option=show_announcement_item',APP_KB_TXT_72,APP_KB_TXT_73,false), $_SESSION['access_role_id'], 5);
$leftNav = RenderViews::outputIfRoleAllowed(RenderViews::buildLeftNavigationButton(CONTROLLER_BASEURL . '&option=option=show_knowledge', APP_KB_TXT_68, false, false), $_SESSION['access_role_id'], 5);
$leftNav .= RenderViews::outputIfRoleAllowed(RenderViews::buildLeftNavigationButton(CONTROLLER_BASEURL . '&option=show_item_types&default_item_type=' . KNOWLEDGEBASE_SET_KB_ITEM_TYPE, APP_KB_TXT_49, false, false), $_SESSION['access_role_id'], 5);
$leftNav .= RenderViews::outputIfRoleAllowed(RenderViews::buildLeftNavigationButton(CONTROLLER_BASEURL . '&option=show_item_search&event_id=returned_items&item_types=' . KNOWLEDGEBASE_SET_KB_ITEM_TYPE, APP_KB_TXT_47, false, false), $_SESSION['access_role_id'], 1);
//$buildLeftNavigationButton .= RenderViews::outputIfRoleAllowed(RenderViews::leftNavButton(SUB_CONTROLLER_BASEURL . '&option=update_helpdesk_settings',APP_KB_TXT_80,false, false), $_SESSION['access_role_id'], 1);
$leftNav .= RenderViews::outputIfRoleAllowed(RenderViews::buildLeftNavigationButton(CONTROLLER_BASEURL . '&option=knowledgebase_settings', APP_KB_TXT_31, false, false), $_SESSION['access_role_id'], 5);
define('LEFT_NAVIGATION', $leftNav);

//$tableRows = RenderViews::tableData('', '', 'center', '', array('tdTopLeft', 'tdLeftNavTopMiddle', 'tdTopRight'), array('', APP_KB_TXT_56, ''), 'row');	$buildImage = RenderViews::buildImage(SET_IMAGE_PATH . 'knowledgebase.png', SET_SHOW_IMAGES);
//$URL = RenderViews::buildURL(KB_SUB_URL . '&subcontroller=app_oneorzeroknowledgebase_manage&option=show_knowledge', APP_KB_TXT_68, 'URLNav');
//$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNav', array($buildImage . $URL), 'row'), $_SESSION['access_role_id'], 4);
//$buildImage = RenderViews::buildImage(SET_IMAGE_PATH . 'newItem.png', SET_SHOW_IMAGES);
//$URL = RenderViews::buildURL(KB_SUB_URL . '&subcontroller=item_management_manage&option=show_item_types&default_item_type=' . KNOWLEDGEBASE_SET_KB_ITEM_TYPE, APP_KB_TXT_49, 'URLNav');
//$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNav', array($buildImage . $URL), 'row'), $_SESSION['access_role_id'], 4);
//$buildImage = RenderViews::buildImage(SET_IMAGE_PATH . 'complexSearch.png', SET_SHOW_IMAGES);
//$URL = RenderViews::buildURL(KB_SUB_URL . '&subcontroller=search_management_manage&option=show_item_search&event_id=returned_items&item_types=' . KNOWLEDGEBASE_SET_KB_ITEM_TYPE, APP_KB_TXT_47, 'URLNav');
//$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNavLast', array($buildImage . $URL), 'row'), $_SESSION['access_role_id'], 5);
//
//// Application Administrative Options
//$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'center', '', 'tdLeftNavShaded', array(APP_KB_TXT_30), 'row'), $_SESSION['access_role_id'], 1);
//$buildImage = RenderViews::buildImage(SET_IMAGE_PATH . 'helpdeskSettings.png', SET_SHOW_IMAGES);
//$URL = RenderViews::buildURL(KB_SUB_URL . '&subcontroller=app_oneorzeroknowledgebase_manage&option=knowledgebase_settings', APP_KB_TXT_31, 'URLNav');
//$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNavLast', array($buildImage . $URL), 'row'), $_SESSION['access_role_id'], 1);
//// Bottom Cell
//$tableRows .= RenderViews::tableData('3', '', 'left', '', 'tdLeftNavBottom', array('&nbsp;'), 'row');
//$html = RenderViews::table('200', '0', '0', '0', '', $tableRows);

function itemTitles($ids)
{
    $whereStatement = 'WHERE';

    if (!is_array($ids)) {
        $whereStatement .= ' item_id = ' . $ids;
    } else {
        foreach ($ids as $items) {
            $whereStatement .= ' item_id = ' . $items . ' OR ';
        }
        $whereStatement = substr($whereStatement, 0, -4);
    }

    $sql = Database::sqlSelect('items', array('item_id', 'item_title'), $whereStatement); // Grab data from database
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::fetchArray($result);
    return $row;
}

function showSubjects()
{
    // Top Row

    $tableRows = RenderViews::tableData('', array('100%'), array('left'), '', array(''), array(searchFrame()), 'row');
    $html = RenderViews::table('100%', '0', '0', '0', 'tableIndent', $tableRows);

    if (defined('KNOWLEDGEBASE_SET_SUBJECT')) {
        //		$headerRow = RenderViews::tableData('', '', 'left', '', array('tdBodyTopMiddle'), array (APP_KB_TXT_68), 'row');
        //		$html .= RenderViews::table('98%', '0', '0', '0', '', $headerRow);
        $subjectsArray = getSubjectTitles(KNOWLEDGEBASE_SET_SUBJECT);
        $subjectArticlesArray = array();
        $sql = Database::sqlSelect('custom_fields', array('field_reference'), 'WHERE custom_field_id = ' . KNOWLEDGEBASE_SET_SUBJECT); // Grab data from database
        //$result = Database::query($sql, DSN, SET_SHOW_SQL);
        $row = Database::firstResult($sql);
        $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $i = 0;
        $tableRows = "";
        $leftHtml = "";
        $rightHtml = "";
        foreach ($subjectsArray as $value) {
            $tableRows = "";
            $subjectArticles = getSubjectArticles($value, $row, KNOWLEDGEBASE_SET_KB_ITEM_TYPE);
            if (is_array($subjectArticles) && count($subjectArticles) > 0) {
                $lineCounter = 0;
                $divValue = '';
                $styleValue = '';
                foreach ($subjectArticles as $itemsList) {
                    $itemTitle = itemTitles($itemsList);
                    $url = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&event_id=view_item&item_id=' . $itemsList . $logEntry . $attachments, $itemTitle['item_title'], 'URL');
                    $tableRows .= RenderViews::tableData('3', '', 'left', '', 'tdc1BottomBorder', array($url), 'row', $styleValue, $divValue);
                    $lineCounter++;
                    if ($lineCounter > 9) {
                        $divValue = 'show_' . $value;
                        $styleValue = 'style="display:none;"';
                    }
                }
                $show = RenderViews::buildURL('#', TXT_373, 'URL', '', 'onclick="showRow(\'show_' . $value . '\');return false;"');
                $hide = RenderViews::buildURL('#', TXT_374, 'URL', '', 'onclick="hideRow(\'show_' . $value . '\');return false;"');
                if ($i == 0) {
                    $tableRows = app_oneorzeroknowledgebase_manage . phpRenderViews::tableData('', '', 'left', '', array('tdcHeadingBottomBorder'), array($value . ' (' . $show . '\\' . $hide . ' ' . APP_KB_TXT_55 . ')'), 'row') . $tableRows;
                    $leftHtml .= $tableRows;
                    $i++;
                } else {
                    $tableRows = app_oneorzeroknowledgebase_manage . phpRenderViews::tableData('', '', 'left', '', array('tdcHeadingBottomBorder'), array($value . ' (' . $show . '\\' . $hide . ' ' . APP_KB_TXT_55 . ')'), 'row') . $tableRows;
                    $rightHtml .= $tableRows;
                    $i = 0;
                }
            }
            unset($subjectArticles);
        }
//		if (strlen($rightHtml) == 0 && strlen($leftHtml) > 0) {
//			$rightHtml = RenderViews::tableData('3', '', 'left', '', 'tdNavigationInsetShaded', array(APP_KB_TXT_64), 'row', '', 'featuredArticles');
//		}
        if (strlen($leftHtml) > 0) {
            $modules[] = RenderViews::table('98%', '0', '0', '0', '', $leftHtml);
            $modules[] = RenderViews::table('98%', '0', '0', '0', '', $rightHtml);
            $i = 0;
            foreach ($modules as $cell) {
                $cellData[] = $cell;
                $i++;
                if ($i == 2) {
                    $tableRows = RenderViews::tableData('', array('50%', '50%'), array('center', 'center'), '', array('moduleContainer', 'moduleContainer'), $cellData, 'row');
                    $html .= RenderViews::table('100%', '0', '0', '0', 'moduleTable', $tableRows);
                    $i = 0;
                    unset($cellData);
                }
            }
        } else {
            $html .= RenderViews::tableData('3', '', 'left', '', 'tdNavigationInsetShaded', array(APP_KB_TXT_64), 'row', '', 'featuredArticles');
        }
    }
    define('HEADING', APP_KB_TXT_68);
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content',  SET_THEME);
}

function showKnowledgebaseModules()
{
    // Top Row

    $tableRows = RenderViews::tableData('', array('100%'), array('left'), '', array(''), array(searchFrame()), 'row');
    $html = RenderViews::table('100%', '0', '0', '0', 'tableIndent', $tableRows);
    // First Main Block

    $leftSide = showFeaturedArticles();
    $tableRowsLeft = RenderViews::tableData('', array('100%'), array('left'), '', array('moduleContainer'), array($leftSide), 'row');
    $modules[] = RenderViews::table('100%', '0', '0', '0', '', $tableRowsLeft);

    $rightSide = showNewestArticles();
    $tableRowsRight = RenderViews::tableData('', array('100%'), array('left'), '', array('moduleContainer'), array($rightSide), 'row');
    $modules[] = RenderViews::table('100%', '0', '0', '0', '', $tableRowsRight);

    // Second block

    $leftSide = showSavedArticles(true);
    $tableRowsLeft = RenderViews::tableData('', array('100%'), array('left'), '', array('moduleContainer'), array($leftSide), 'row');
    $modules[] = RenderViews::table('100%', '0', '0', '0', '', $tableRowsLeft);

    $rightSide = showTopTen(true);
    $tableRowsRight = RenderViews::tableData('', array('100%'), array('left'), '', array('moduleContainer'), array($rightSide), 'row');
    $modules[] = RenderViews::table('100%', '0', '0', '0', '', $tableRowsRight);

    if (count($modules) == 1) {
        $tableRows = RenderViews::tableData('', array('100%'), array('center'), '', array('moduleContainer'), $modules, 'row');
        $html .= RenderViews::table('100%', '0', '0', '0', 'moduleTable', $tableRows);
    } else {
        $i = 0;
        foreach ($modules as $cell) {
            $cellData[] = $cell;
            $i++;
            if ($i == 2) {
                $tableRows = RenderViews::tableData('', array('50%', '50%'), array('center', 'center'), '', array('moduleContainer', 'moduleContainer'), $cellData, 'row');
                $html .= RenderViews::table('100%', 0, 0, 0, 'moduleTable', $tableRows);
                $i = 0;
                unset($cellData);
            }
        }
    }
    unset($modules);
    define('HEADING', APP_KB_TXT_58);
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content',  SET_THEME);
}

function searchFrame()
{
    $html = RenderViews::buildStartForm('index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=quick_search&event_id=returned_items', 'POST', 'form-horizontal');
    $html .= RenderViews::buildSelectDropdown('search_type', array('item_title', 'item_id'), array(APP_KB_TXT_59, APP_KB_TXT_1), '') . ' app_oneorzeroknowledgebase_manage.php';
    $html .= RenderViews::buildTextInput('search_value', '') . ' app_oneorzeroknowledgebase_manage.php';
    $jsFieldNameArray = "['search_value']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . APP_KB_TXT_2 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";
    $buttonArray[] = RenderViews::buildFormButton('submit', 'search', APP_KB_TXT_61, $javascript);
    $html .= RenderViews::buildEndFormWithButtons($buttonArray);
    return $html;
}

function mostPopularFrame()
{
    $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $sql = Database::sqlSelect('system_log', array('item_id'), 'WHERE event_id = "view_item" ORDER BY event_counter LIMIT 1'); // Grab data from database
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $row = Database::firstResult($result);
    $titleArray = itemTitles($row);
    $html = APP_KB_TXT_62 . ": " . RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&event_id=view_item&item_id=' . $row['item_id'] . $logEntry . $attachments, $titleArray['item_title'], 'URL');
    return $html;
}

function showFeaturedArticles()
{
    if (defined('KNOWLEDGEBASE_SET_FEATURES')) {
        $show = RenderViews::buildURL('#', TXT_373, 'URL', '', 'onclick="showRow(\'show_Featured\');return false;"');
        $hide = RenderViews::buildURL('#', TXT_374, 'URL', '', 'onclick="hideRow(\'show_Featured\');return false;"');
        $headerRow = RenderViews::tableData('', '', 'left', '', array('tdcHeadingBottomBorder'), array(APP_KB_TXT_63 . ' (' . $show . '\\' . $hide . ' ' . APP_KB_TXT_55 . ')'), 'row');
        $html = RenderViews::table('98%', '0', '0', '0', '', $headerRow);
        $subjectArticlesArray = array();
        $sql = Database::sqlSelect('custom_fields', array('field_reference'), 'WHERE custom_field_id = ' . KNOWLEDGEBASE_SET_FEATURES); // Grab data from database
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $row = Database::firstResult($sql);
        $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $i = 0;
        $tableRows = "";
        $leftHtml = "";
        $subjectArticles = [];
        if (is_array($row) && isset($row['field_reference'])) {
            $subjectArticles = getSubjectArticles('yes', $row['field_reference'], KNOWLEDGEBASE_SET_KB_ITEM_TYPE);
        }
        if (is_array($subjectArticles) && count($subjectArticles) > 0) {
            $lineCounter = 0;
            $divValue = '';
            $styleValue = '';
            foreach ($subjectArticles as $itemsList) {
                $itemTitle = itemTitles($itemsList);
                $url = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&event_id=view_item&item_id=' . $itemsList . $logEntry . $attachments, $itemTitle['item_title'], 'URL');
                $tableRows .= RenderViews::tableData('', '', 'left', '', 'tdc1BottomBorder', array($url), 'row', $styleValue, $divValue);
                $lineCounter++;
                if ($lineCounter > 9) {
                    $divValue = 'show_Featured';
                    $styleValue = 'style="display:none;"';
                }
            }
        }
        unset($subjectArticles);
        if (strlen($tableRows) == 0) {
            $tableRows = RenderViews::tableData('', '', 'left', '', 'tdc1BottomBorder', array(APP_KB_TXT_64), 'row', '', '');
        }
    }
    $html .= RenderViews::table('98%', '0', '0', '0', '', $tableRows);
    return $html;
}

function showSavedArticles()
{
    // Get data from database and create statistics table
    // Get favourite searches
    $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $sql = "SELECT * FROM saved_searches WHERE (user ='" . $_SESSION['access_user_id'] . "' OR user = 'all' or user = 'system') AND application = 'app_oneorzeroknowledgebase_main' ORDER BY search_name ASC";
    $show = RenderViews::buildURL('#', TXT_373, 'URL', '', 'onclick="showRow(\'show_Saved\');return false;"');
    $hide = RenderViews::buildURL('#', TXT_374, 'URL', '', 'onclick="hideRow(\'show_Saved\');return false;"');
    $headerRow = RenderViews::tableData('', '', 'left', '', array('tdcHeadingBottomBorder'), array(APP_KB_TXT_66 . ' (' . $show . '\\' . $hide . ' ' . APP_KB_TXT_55 . ')'), 'row');
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $html = RenderViews::table('98%', '0', '0', '0', '', $headerRow);
    if (Database::numRows($result) == 0) {
        $tableRows = RenderViews::tableData('', '', 'left', '', 'tdc1BottomBorder', array(APP_KB_TXT_64), 'row', '', '');
    } else {
        $tableRows = '';
        $divValue = '';
        $styleValue = '';
        $lineCounter = 0;
        while ($row = Database::fetchArray($result)) {
            unset($title);
            if (isset($row['search_name'])) {
                $title = $row['search_name'];
            }
            if (isset($title)) {
                if ($row['user'] == 'all') {
                    $url = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=saved_search&global=1&id=' . $row['search_id'], $row['search_name'] . ' (' . TXT_408 . ')', 'URL');
                } else {
                    $url = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=saved_search&id=' . $row['search_id'], $row['search_name'], 'URL');
                }
                $tableRows .= RenderViews::tableData('', '', 'left', '', 'tdc1BottomBorder', array($url), 'row', $styleValue, $divValue);
                $lineCounter++;
                if ($lineCounter > 9) {
                    $divValue = 'show_Saved';
                    $styleValue = 'style="display:none;"';
                }
            }
        }
    }
    $html .= RenderViews::table('98%', '0', '0', '0', '', $tableRows);

    return $html;
}

function showNewestArticles()
{
    // Get data from database and create statistics table
    // Get favourite searches
    $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $sql = Database::sqlSelect('items', array('item_id', 'item_title'), 'WHERE item_type_id = ' . KNOWLEDGEBASE_SET_KB_ITEM_TYPE . ' ORDER BY create_date DESC LIMIT 10'); // Grab data from database
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $show = RenderViews::buildURL('#', TXT_373, 'URL', '', 'onclick="showRow(\'show_Newest\');return false;"');
    $hide = RenderViews::buildURL('#', TXT_374, 'URL', '', 'onclick="hideRow(\'show_Newest\');return false;"');
    $headerRow = RenderViews::tableData('', '', 'left', '', array('tdcHeadingBottomBorder'), array(APP_KB_TXT_65 . ' (' . $show . '\\' . $hide . ' ' . APP_KB_TXT_55 . ')'), 'row');
    $html = RenderViews::table('98%', '0', '0', '0', '', $headerRow);
    if (Database::numRows($result) == 0) {
        $tableRows = RenderViews::tableData('', '', 'left', '', 'tdc1BottomBorder', array(APP_KB_TXT_64), 'row', '', '');
    } else {
        $tableRows = '';
        $lineCounter = 0;
        $divValue = '';
        $styleValue = '';
        while ($row = Database::fetchArray($result)) {
            $url = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&event_id=view_item&item_id=' . $row['item_id'] . $logEntry . $attachments, $row['item_title'], 'URL');
            $tableRows .= RenderViews::tableData('', '', 'left', '', 'tdc1BottomBorder', array($url), 'row', $styleValue, $divValue);
            $lineCounter++;
            if ($lineCounter > 9) {
                $divValue = 'show_Newest';
                $styleValue = 'style="display:none;"';
            }
        }
    }
    $html .= RenderViews::table('98%', '0', '0', '0', '', $tableRows);

    return $html;
}

function showTopTen()
{
    // Get data from database and create statistics table
    // Get favourite searches
    $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $sql = Database::sqlSelect('system_log', array('item_id'), 'WHERE event_id = "view_item" ORDER BY event_counter DESC LIMIT 10'); // Grab data from database
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $show = RenderViews::buildURL('#', TXT_373, 'URL', '', 'onclick="showRow(\'show_Topten\');return false;"');
    $hide = RenderViews::buildURL('#', TXT_374, 'URL', '', 'onclick="hideRow(\'show_Topten\');return false;"');
    $headerRow = RenderViews::tableData('', '', 'left', '', array('tdcHeadingBottomBorder'), array(APP_KB_TXT_67 . ' (' . $show . '\\' . $hide . ' ' . APP_KB_TXT_55 . ')'), 'row');
    $html = RenderViews::table('98%', '0', '0', '0', '', $headerRow);
    if (Database::numRows($result) == 0) {
        $tableRows = RenderViews::tableData('', '', 'left', '', 'tdc1BottomBorder', array(APP_KB_TXT_64), 'row', '', '');
    } else {
        $tableRows = '';
        $divValue = '';
        $styleValue = '';
        $lineCounter = 0;
        while ($row = Database::fetchArray($result)) {
            $itemTitle = itemTitles($row['item_id']);
            $url = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&event_id=view_item&item_id=' . $row['item_id'] . $logEntry . $attachments, $itemTitle['item_title'], 'URL');
            $tableRows .= RenderViews::tableData('', '', 'left', '', 'tdc1BottomBorder', array($url), 'row', $styleValue, $divValue);
            $lineCounter++;
            if ($lineCounter > 9) {
                $divValue = 'show_Topten';
                $styleValue = 'style="display:none;"';
            }
        }
    }
    $html .= RenderViews::table('98%', '0', '0', '0', '', $tableRows);

    return $html;
}

function getSubjectTitles($passedID)
{

    $sql = Database::sqlSelect('custom_field_menu_values', array('menu_value'), 'WHERE custom_field_id = ' . $passedID); // Grab data from database
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $subjectArray = array();
    while ($row = Database::fetchArray($result)) {
        $subjectArray[] = $row[0];
    }
    return $subjectArray;
}

function getSubjectArticles($subjectID, $subjectFieldNbr, $passedID)
{

    $sql = Database::sqlSelect('items', 'item_id', 'WHERE item_type_id = ' . $passedID . ' AND custom_field_' . $subjectFieldNbr . ' = "' . $subjectID . '"'); // Grab data from database
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $subjectArticlesArray = array();
    while ($row = Database::fetchArray($result)) {
        $subjectArticlesArray[] = $row[0];
    }
    return $subjectArticlesArray;
}

function showKnowledgebaseSettings()
{
    // Get Helpdesk Settings from file
    $settings = @parse_ini_file(SET_WRITEABLE_DIRECTORY . 'applications/knowledgemanager/configuration/knowledgebase_settings.php');
    $sql = Database::sqlSelect('item_types',  ['item_type_id', 'item_type_name']);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $listValues = [];
    $listDisplayValues = [];
    while ($row = Database::fetchArray($result)) {
        $listValues[] = $row['item_type_id'];
        $listDisplayValues[] = $row['item_type_name'];
    }

$columnArray = ['custom_field_id', 'custom_field_name'];
    $sql = Database::sqlSelect('custom_fields', $columnArray, "ORDER BY custom_field_name ASC");
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    $subjectValues = [];
    $subjectDisplayValues = [];
    while ($row = Database::fetchArray($result)) {
        $subjectValues[] = $row['custom_field_id'];
        $subjectDisplayValues[] = $row['custom_field_name'];
    }

    $fields[APP_KB_TXT_57] = RenderViews::buildSelectDropdown('KNOWLEDGEBASE_SET_KB_ITEM_TYPE', $listValues, $listDisplayValues, $settings['KNOWLEDGEBASE_SET_KB_ITEM_TYPE']);
    $fields[APP_KB_TXT_69] = RenderViews::buildSelectDropdown('KNOWLEDGEBASE_SET_SUBJECT', $subjectValues, $subjectDisplayValues, @$settings['KNOWLEDGEBASE_SET_SUBJECT']);
    $fields[APP_KB_TXT_70] = RenderViews::buildSelectDropdown('KNOWLEDGEBASE_SET_FEATURES', $subjectValues, $subjectDisplayValues, @$settings['KNOWLEDGEBASE_SET_FEATURES']);
    // Create two column table with heading
    $html = RenderViews::buildStartForm(CONTROLLER_BASEURL . '&option=update_knowledgebase_settings', 'POST', 'form-horizontal');
//    $tableRows = '';
//    foreach ($fields as $name => $field) {
//        // $a in this case represents the defined variables above
//        $cellData = array('<strong>' . $name . '</strong>', $field);
//        $tableRows .= RenderViews::tableData('', array('1%', '99%'), array('left', 'left'), '', array('tdform', 'tdformIndent'), $cellData, 'row');
//    }
    $fields[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_74).' '. RenderViews::buildFormButton('reset', 'reset', TXT_75);
$html .= RenderViews::buildFormFieldsGrid($fields);
//    $endFormButtons = RenderViews::endFormButtons($buttonArray, '1');
  //  $tableRows .= RenderViews::tableData('2', '', array('left'), '', 'tdc1', array($endFormButtons), 'row');
   // $html .= RenderViews::table('95%', '0', '0', '0', 'tableIndent', $tableRows);

    define('HEADING', APP_KB_TXT_31);
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content',  SET_THEME);
}

function updateSettings()
{
    // remove so is not added to config file
    unset ($_POST['submit_button'], $_POST['reset']);
    // Write config file based on form created in showFlowIQSettings
    $header = 'Adlexone Knowledgebase Settings File - this file is generated by the Adlexone Knowledgebase.  You can update manually if desired.';
    $configurationDirectory = SET_WRITEABLE_DIRECTORY . 'applications/knowledgemanager/configuration/';
    if (!is_dir($configurationDirectory)) {
        if (!mkdir($configurationDirectory, 0755, 1)) {
            echo 'ERROR: Could not create directory: ' . $configurationDirectory;
        }
    }
    $i = 0;
    foreach ($_POST as $key => $value) {
        if (stristr($key, 'item_type_id_')) {
            @$_POST['KNOWLEDGEBASE_SET_ITEM_TYPE'] .= ($i == 0) ? $value : ',' . $value;
            unset($_POST[$key]);
        }
        $i++;
    }
    if (File::writeFileFromArray($configurationDirectory . 'knowledgebase_settings.php', $header, $_POST)) {
        $message = TXT_201;
    } else {
        $message = TXT_203;
    }
    $html = RenderViews::showResponse($message, RenderViews::buildURL(CONTROLLER_BASEURL . '&option=knowledgebase_settings', TXT_202, 'URL'));
    define('BODY_CONTENT', $html);
    define('HEADING', TXT_139);
    RenderViews::renderThemePage('main_page_content',  SET_THEME);
}

/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
    case 'knowledgebase_settings' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        showKnowledgebaseSettings();
        break;
    case 'update_knowledgebase_settings' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
        updateSettings();
        break;
    case 'show_knowledge':
        showSubjects();
        break;
    default;
        if (KB_CONFIGURED == true) {
            showKnowledgebaseModules();
        } else {
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
            showKnowledgebaseSettings();
        }
}
?>