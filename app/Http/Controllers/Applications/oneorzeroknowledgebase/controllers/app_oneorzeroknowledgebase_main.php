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
use Adlexone\support\RenderNavigation;
use Adlexone\support\SharedMethods;

/**
 * Initiate Application Constants and variables
 */
SharedMethods::loadConstantFromIni(SET_INSTALL_PATH. 'translations/applications/knowledgemanager/' . SET_LANGUAGE . '.lang.php');


define('SUB_CONTROLLER_BASEURL', FULL_SCRIPT_PATH . '?controller=app_oneorzeroknowledgebase_main');
if (!defined('CONTROLLER_BASEURL')) {
    define('CONTROLLER_BASEURL', 'index.php?controller=app_oneorzeroknowledgebase_main');
}

/**
 * Knowledge Hub links sit in the top navigation card. There is no left sidebar.
 */
RenderNavigation::applySectionNav('Knowledge Hub', RenderNavigation::knowledgebaseNavigationURLS());

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
//$buildImage = RenderViews::buildImage(SET_IMAGE_PATH . 'settings.png', SET_SHOW_IMAGES);
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

    return Database::first('items', ['item_id', 'item_title'], $whereStatement);
}

function kbLinkCard(string $title, string $toggleId, array $urls, string $emptyText, string $moreLabel): string
{
    if ($urls === []) {
        $body = RenderViews::buildFormFieldsGrid(['' => $emptyText]);
    } else {
        $visible = '';
        $hidden = '';
        foreach ($urls as $index => $url) {
            $line = RenderViews::buildFormFieldsGrid(['' => $url]);
            if ($index < 10) {
                $visible .= $line;
            } else {
                $hidden .= $line;
            }
        }
        $show = RenderViews::buildURL('#', TXT_373, 'URL', '', 'onclick="showRow(\'' . $toggleId . '\');return false;"');
        $hide = RenderViews::buildURL('#', TXT_374, 'URL', '', 'onclick="hideRow(\'' . $toggleId . '\');return false;"');
        $body = '<div>' . $show . ' \\ ' . $hide . ' ' . $moreLabel . '</div>' . $visible;
        if ($hidden !== '') {
            $body .= '<div id="' . htmlspecialchars($toggleId, ENT_QUOTES, 'UTF-8') . '" style="display:none;">' . $hidden . '</div>';
        }
    }

    return RenderViews::buildVerticalCards([['title' => $title, 'html' => $body]]);
}

function showSubjects()
{
    $html = searchFrame();

    if (defined('KNOWLEDGEBASE_SET_SUBJECT')) {
        $subjectsArray = getSubjectTitles(KNOWLEDGEBASE_SET_SUBJECT);
        $fieldRow = Database::first('custom_fields', ['field_reference'], 'custom_field_id = ?', [KNOWLEDGEBASE_SET_SUBJECT]);
        $fieldReference = is_array($fieldRow) ? (string)($fieldRow['field_reference'] ?? '') : (string)$fieldRow;
        $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $leftHtml = '';
        $rightHtml = '';
        $column = 0;
        foreach ($subjectsArray as $value) {
            $subjectArticles = getSubjectArticles($value, $fieldReference, KNOWLEDGEBASE_SET_KB_ITEM_TYPE);
            if (!is_array($subjectArticles) || count($subjectArticles) === 0) {
                continue;
            }
            $urls = [];
            foreach ($subjectArticles as $itemsList) {
                $itemTitle = itemTitles($itemsList);
                $urls[] = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&event_id=view_item&item_id=' . $itemsList . $logEntry . $attachments, (string)($itemTitle['item_title'] ?? ''), 'URL');
            }
            $section = kbLinkCard((string)$value, 'show_' . $value, $urls, APP_KB_TXT_64, APP_KB_TXT_55);
            if ($column === 0) {
                $leftHtml .= $section;
                $column = 1;
            } else {
                $rightHtml .= $section;
                $column = 0;
            }
        }
        if ($leftHtml !== '' || $rightHtml !== '') {
            $html .= RenderViews::buildHorizontalCards([
                ['title' => '', 'html' => $leftHtml],
                ['title' => '', 'html' => $rightHtml],
            ], 2);
        } else {
            $html .= RenderViews::buildVerticalCards([[
                'title' => APP_KB_TXT_68,
                'html' => RenderViews::buildFormFieldsGrid(['' => APP_KB_TXT_64]),
            ]]);
        }
    }
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showKnowledgebaseModules()
{
    $html = searchFrame();
    $html .= RenderViews::buildHorizontalCards([
        ['title' => '', 'html' => showFeaturedArticles()],
        ['title' => '', 'html' => showNewestArticles()],
        ['title' => '', 'html' => showSavedArticles()],
        ['title' => '', 'html' => showTopTen()],
    ], 2);
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function searchFrame()
{
    $jsFieldNameArray = "['search_value']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . APP_KB_TXT_2 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";
    return RenderViews::buildForm(
        APP_KB_TXT_61,
        'index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=quick_search&event_id=returned_items',
        [
            APP_KB_TXT_59 => RenderViews::buildSelectDropdown('search_type', array('item_title', 'item_id'), array(APP_KB_TXT_59, APP_KB_TXT_1), ''),
            APP_KB_TXT_2 => RenderViews::buildTextInput('search_value', ''),
        ],
        [RenderViews::buildFormButton('submit', 'search', APP_KB_TXT_61, $javascript)]
    );
}

function mostPopularFrame()
{
    $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $row = Database::first('system_log', ['item_id'], 'event_id = ?', ['view_item'], 'event_counter');
    $titleArray = itemTitles($row);
    $html = APP_KB_TXT_62 . ": " . RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&event_id=view_item&item_id=' . $row['item_id'] . $logEntry . $attachments, $titleArray['item_title'], 'URL');
    return $html;
}

function showFeaturedArticles()
{
    $urls = [];
    if (defined('KNOWLEDGEBASE_SET_FEATURES')) {
        $row = Database::first('custom_fields', ['field_reference'], 'custom_field_id = ?', [KNOWLEDGEBASE_SET_FEATURES]);
        $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
        $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
        $subjectArticles = [];
        if (is_array($row) && isset($row['field_reference'])) {
            $subjectArticles = getSubjectArticles('yes', $row['field_reference'], KNOWLEDGEBASE_SET_KB_ITEM_TYPE);
        }
        if (is_array($subjectArticles)) {
            foreach ($subjectArticles as $itemsList) {
                $itemTitle = itemTitles($itemsList);
                $urls[] = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&event_id=view_item&item_id=' . $itemsList . $logEntry . $attachments, (string)($itemTitle['item_title'] ?? ''), 'URL');
            }
        }
    }

    return kbLinkCard(APP_KB_TXT_63, 'show_Featured', $urls, APP_KB_TXT_64, APP_KB_TXT_55);
}

function showSavedArticles()
{
    $sql = "SELECT * FROM saved_searches WHERE (user ='" . $_SESSION['access_user_id'] . "' OR user = 'all' or user = 'system') AND application = 'app_oneorzeroknowledgebase_main' ORDER BY search_name ASC";
        $result = Database::rows($sql);
    $urls = [];
    foreach ($result as $row) {
        if (!isset($row['search_name'])) {
            continue;
        }
        if ($row['user'] == 'all') {
            $urls[] = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=saved_search&global=1&id=' . $row['search_id'], $row['search_name'] . ' (' . TXT_408 . ')', 'URL');
        } else {
            $urls[] = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=saved_search&id=' . $row['search_id'], $row['search_name'], 'URL');
        }
    }

    return kbLinkCard(APP_KB_TXT_66, 'show_Saved', $urls, APP_KB_TXT_64, APP_KB_TXT_55);
}

function showNewestArticles()
{
    $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $result = Database::select('items', array('item_id', 'item_title'), 'WHERE item_type_id = ' . KNOWLEDGEBASE_SET_KB_ITEM_TYPE . ' ORDER BY create_date DESC LIMIT 10');
    $urls = [];
    foreach ($result as $row) {
        $urls[] = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&event_id=view_item&item_id=' . $row['item_id'] . $logEntry . $attachments, (string)$row['item_title'], 'URL');
    }

    return kbLinkCard(APP_KB_TXT_65, 'show_Newest', $urls, APP_KB_TXT_64, APP_KB_TXT_55);
}

function showTopTen()
{
    $logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
    $attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
    $result = Database::select('system_log', array('item_id'), 'WHERE event_id = "view_item" ORDER BY event_counter DESC LIMIT 10');
    $urls = [];
    foreach ($result as $row) {
        $itemTitle = itemTitles($row['item_id']);
        $urls[] = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&event_id=view_item&item_id=' . $row['item_id'] . $logEntry . $attachments, (string)($itemTitle['item_title'] ?? ''), 'URL');
    }

    return kbLinkCard(APP_KB_TXT_67, 'show_Topten', $urls, APP_KB_TXT_64, APP_KB_TXT_55);
}

function getSubjectTitles($passedID)
{

    $subjectArray = [];
    foreach (Database::select('custom_field_menu_values', ['menu_value'], 'custom_field_id = ?', [$passedID]) as $row) {
        $subjectArray[] = $row['menu_value'];
    }
    return $subjectArray;
}

function getSubjectArticles($subjectID, $subjectFieldNbr, $passedID)
{

    $subjectArticlesArray = [];
    $column = 'custom_field_' . (int) $subjectFieldNbr;
    foreach (Database::select('items', ['item_id'], 'item_type_id = ? AND ' . $column . ' = ?', [$passedID, $subjectID]) as $row) {
        $subjectArticlesArray[] = $row['item_id'];
    }
    return $subjectArticlesArray;
}

function showKnowledgebaseSettings()
{
    // Get Knowledge Hub settings from file
    $settings = @parse_ini_file(SET_WRITEABLE_DIRECTORY . 'applications/knowledgemanager/configuration/knowledgebase_settings.php');
    $result = Database::select('item_types',  ['item_type_id', 'item_type_name']);
    $listValues = [];
    $listDisplayValues = [];
    foreach ($result as $row) {
        $listValues[] = $row['item_type_id'];
        $listDisplayValues[] = $row['item_type_name'];
    }

$columnArray = ['custom_field_id', 'custom_field_name'];
    $result = Database::select('custom_fields', $columnArray, "ORDER BY custom_field_name ASC");

    $subjectValues = [];
    $subjectDisplayValues = [];
    foreach ($result as $row) {
        $subjectValues[] = $row['custom_field_id'];
        $subjectDisplayValues[] = $row['custom_field_name'];
    }

    $fields[APP_KB_TXT_57] = RenderViews::buildSelectDropdown('KNOWLEDGEBASE_SET_KB_ITEM_TYPE', $listValues, $listDisplayValues, $settings['KNOWLEDGEBASE_SET_KB_ITEM_TYPE']);
    $fields[APP_KB_TXT_69] = RenderViews::buildSelectDropdown('KNOWLEDGEBASE_SET_SUBJECT', $subjectValues, $subjectDisplayValues, @$settings['KNOWLEDGEBASE_SET_SUBJECT']);
    $fields[APP_KB_TXT_70] = RenderViews::buildSelectDropdown('KNOWLEDGEBASE_SET_FEATURES', $subjectValues, $subjectDisplayValues, @$settings['KNOWLEDGEBASE_SET_FEATURES']);
    define('BODY_CONTENT', RenderViews::buildForm(
        APP_KB_TXT_31,
        CONTROLLER_BASEURL . '&option=update_knowledgebase_settings',
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_74),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
        ]
    ));
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
        if (defined('KNOWLEDGEBASE_SET_KB_ITEM_TYPE')) {
            showKnowledgebaseModules();
        } else {
            RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
            showKnowledgebaseSettings();
        }
}
?>