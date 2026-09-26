<?php
/**
 * License Pending: All distribution and use is forbidden until the final license
 * has been applied.
 *
 * Contact info@oneorzero.com for more information
 */
/**
 * /*******************************************************************************
 * Required Libraries
 */
require_once OOZ_SET_FRAMEWORK_PATH . "/lib/ooz_render.class.php";
require_once OOZ_SET_FRAMEWORK_PATH . '/abstract/ooz_' . OOZ_SET_DB_TYPE . '_wrap.class.php';
use Adlexone\support\RenderViews;
use Adlexone\support\Database;

/**
 * Controller specific constants
 */
define('OOZ_REP_SUB_URL', 'index.php?controller=app_oneorzeroreportmanager_main&subcontroller=app_oneorzeroreportmanager_manage');
/**
 * Controller functions called from templates and controller logic via hyperlink vlaues
 */
/**
 * showMyReports()
 *
 * @return My report html
 */
function showMyReports($manage = '')
{
	// Get data from database and create statistics table
	// Get favourite searches
	$sql = "SELECT * FROM " . OOZ_SET_TABLE_PREFIX . "ooz_reportmanager_reports";
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$headingRow = RenderViews::tableData('', '', 'left', '', array('tdcHeadingBottomBorder'), array (APP_TXT_10), 'row');
	$html = RenderViews::table('98%', '0', '0', '0', '', $headingRow);
	if (DB::numRows($result) == 0) {
		$tableRows = RenderViews::tableData('2', '', 'left', '', 'tdNavigationInsetShaded', array(APP_TXT_11), 'row', '', 'saved_searches');
	} else {
		$i = 0;
		while ($row = DB::fetchArray($result)) {
			$condition = "WHERE user_id='" . $_SESSION['access_user_id'] . "'";
			$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'group_members', array('groups'), $condition);
			$groupResult = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
			$groupRow = DB::fetchArray($groupResult);
			if (in_array($row['security_group'], explode('}-{', $groupRow['groups']))) {
				if ($manage == '') {
					@$nameURL .= RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage&option=get_csv&id=' . $row['report_id'], APP_TXT_20 . ' - ', 'URL');
					$nameURL .= RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage&option=show_graph&id=' . $row['report_id'], APP_TXT_21 , 'URL');
				}
				if ($manage == 'Yes') {
					@$nameURL .= RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage&option=edit_report&id=' . $row['report_id'], APP_TXT_32 . ' - ', 'URL');
					$nameURL .= RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage&option=delete_report&id=' . $row['report_id'], APP_TXT_25, 'URL','','onClick="javascript:return confirm(\''.OOZ_TXT_400.'\')"');
				}
				$urlArray[$i] = $nameURL;
				$nameArray[$i] = '<strong>' . $row['report_name'] . '</strong>';
				unset($nameURL);
				$i++;
			}
		}
		$cellCount = count(@$nameArray);
		if ($cellCount == 1) {
			$tableRows = RenderViews::tableData('', array('20%','80%'), array('left','left'), '', 'tdNavigationInsetShaded',array($urlArray[0],$nameArray[0]), 'row');
		} else {
			$i = 0;
			$tableRows = '';
			if (is_array(@$nameArray)){
				foreach($nameArray as $name) {
					$class = RenderViews::setOddEvenClass($i, 'tdc2', 'tdc1');
					$tableRows .= RenderViews::tableData('', array('20%','80%'), array('left','left'), '', $class, array($urlArray[$i],$name), 'row');
					$i++;
				}
			}else{
				$tableRows .= RenderViews::tableData('', '', array('left'), '', 'tdc2', array(APP_TXT_36), 'row');
			}
		}
	}

	$html .= RenderViews::table('98%', '0', '0', '0', '', $tableRows);
	if ($manage == 'Yes') {
		define ('OOZ_HEADING', APP_TXT_29);
		define ('OOZ_BODY', $html);
		RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
	} else {
		return $html;
	}
}
function showMyMultiReports($manage = '')
{
	$sql = "SELECT * FROM " . OOZ_SET_TABLE_PREFIX . "ooz_reportmanager_multi";
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$headingRow = RenderViews::tableData('', '', 'left', '', array('tdcHeadingBottomBorder'), array (APP_TXT_41), 'row');
	$html = RenderViews::table('98%', '0', '0', '0', '', $headingRow);
	if (DB::numRows($result) == 0) {
		$tableRows = RenderViews::tableData('2', '', 'left', '', 'tdNavigationInsetShaded', array(APP_TXT_11), 'row');
	} else {
		$i = 0;
		while ($row = DB::fetchArray($result)) {
			$condition = "WHERE user_id='" . $_SESSION['access_user_id'] . "'";
			$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'group_members', array('groups'), $condition);
			$groupResult = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
			$groupRow = DB::fetchArray($groupResult);
			if (in_array($row['security_group'], explode('}-{', $groupRow['groups']))) {
				if ($manage == '') {
					//$nameURL .= RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage&option=get_csv&id=' . $row['report_id'], APP_TXT_20 . ' - ', 'URL');
					@$nameURL .= RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage&option=show_multi_graph&id=' . $row['report_id'], APP_TXT_40 , 'URL');
				}
				if ($manage == 'Yes') {
					@$nameURL .= RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage&option=edit_multi_report&id=' . $row['report_id'], APP_TXT_32 . ' - ', 'URL');
					$nameURL .= RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage&option=delete_multi_report&id=' . $row['report_id'], APP_TXT_25, 'URL','','onClick="javascript:return confirm(\''.OOZ_TXT_400.'\')"');
				}
				$urlArray[$i] = $nameURL;
				$nameArray[$i] = '<strong>' . $row['report_name'] . '</strong>';
				unset($nameURL);
				$i++;
			}
		}
		$cellCount = count(@$nameArray);
		if ($cellCount == 1) {
			$tableRows = RenderViews::tableData('', array('20%','80%'), array('left','left'), '', 'tdNavigationInsetShaded',array($urlArray[0],$nameArray[0]), 'row');
		} else {
			$i = 0;
			$tableRows = '';
			if (is_array(@$nameArray)){
				foreach($nameArray as $name) {
					$class = RenderViews::setOddEvenClass($i, 'tdc2', 'tdc1');
					$tableRows .= RenderViews::tableData('', array('20%','80%'), array('left','left'), '', $class, array($urlArray[$i],$name), 'row');
					$i++;
				}
			}else{
				$tableRows .= RenderViews::tableData('', '', array('left'), '', 'tdc2', array(APP_TXT_36), 'row');
			}
		}
	}
	$html .= RenderViews::table('98%', '0', '0', '0', '', $tableRows);
	if ($manage == 'Yes') {
		define ('OOZ_HEADING', APP_TXT_38);
		define ('OOZ_BODY', $html);
		RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
	} else {
		define ('OOZ_HEADING', APP_TXT_39);
		define ('OOZ_BODY', $html);
		RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
	}
}
function deleteReport($id)
{
	$sql = DB::sqlDelete(OOZ_SET_TABLE_PREFIX . "ooz_reportmanager_reports", "WHERE report_id = '$id'");
	DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	showMyReports('Yes');
}
function deleteMultiReport($id)
{
	$sql = DB::sqlDelete(OOZ_SET_TABLE_PREFIX . "ooz_reportmanager_multi", "WHERE report_id = '$id'");
	DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	showMyMultiReports();
}
/**
 * showModules()
 *
 * @return Module html
 */
function showModules(): void
{
    // Collect module HTML
    $modules = [];
    $modules[] = showMyReports();

    // Build blocks for the renderer
    $blocks = [];
    $isSingle = count($modules) === 1;

    foreach ($modules as $moduleHtml) {
        $blocks[] = [
            'title' => '',           // No per-card title here; overall heading is set below
            'html'  => $moduleHtml,
            'full'  => $isSingle,    // Span full width when only one module
        ];
    }

    // Render layout: vertical for one, 2-column grid for many
    $html = $isSingle
        ? RenderViews::buildVerticalCards($blocks)
        : RenderViews::buildHorizontalCards($blocks, 2);

    // Page heading/content and layout include (modern pattern)
    define('HEADING', APP_TXT_13);
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}
/**
 * showReport()
 *
 * @param string $reportID
 * @param string $values
 * @return
 */
function showReport($id = '')
{
	if ($id == '') {
		$html = RenderViews::buildStartForm(OOZ_REP_SUB_URL . '&option=add_report');
	} else {
		$columnArray = array ('*');
		$condition = "WHERE report_id = '$id'";
		$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_reports', $columnArray, $condition);
		$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		$fieldValues = DB::fetchArray($result);
		$html = RenderViews::buildStartForm(OOZ_REP_SUB_URL . '&option=update_report&id=' . $id);
	}
	$tableRows = RenderViews::tableData('2', '', array('center'), '', 'tdcHeading', array(APP_TXT_9), 'row');
	$reportFields[APP_TXT_3] = RenderViews::textBox('report_name', @$fieldValues['report_name'], OOZ_SET_FORM_FIELD_WIDTH, 'formField');
	// Build group menu
	$columnArray = array('group_id', 'group_name');
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'groups', $columnArray);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	// Create group array then render menu
	while ($row = DB::fetchArray($result)) {
		$groupValue[] = $row['group_id'];
		$groupName[] = $row['group_name'];
	}
	$reportFields[APP_TXT_4] = RenderViews::menu('security_group' , $groupValue, $groupName, @$fieldValues['security_group'], 'formField');
	$columnArray = array('search_id', 'search_name');
	$condition = "WHERE application = 'app_oneorzeroreportmanager_main'";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'saved_searches', $columnArray, $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$searchCount = DB::numRows($result);
	$savedSearches = '';
	if ($searchCount > 0) {
		while ($row = DB::fetchArray($result)) {
			$savedSearches .= RenderViews::checkBox('search_' . $row['search_id'], $row['search_id'], in_array($row['search_id'], explode('}-{', @$fieldValues['saved_searches'])), 'formField') . ' ' . $row['search_name'] . '<br>';
		}
	} else {
		$savedSearches = APP_TXT_6;
	}
	$reportFields[APP_TXT_5] = $savedSearches;
	$i = 0;
	foreach ($reportFields as $name => $field) {
		$cellData = array ('<strong>' . $name . '</strong>', $field);
		$tableRows .= RenderViews::tableData('', array('30%', '70%'), '', '' , 'tdc1', $cellData, 'row');
		$i++;
	}
	$jsFieldNameArray = "['report_name']";
	$jsTestTypeArray = "['']";
	$jsErrorMsgArray = "['']";
	$jsRequiredMsgArray = "['". APP_TXT_17."']";
	$jsRequiredArray = "[true]";
	$javascript = "onClick=\"javascript:return fieldCheck('".OOZ_TXT_468."',".$jsTestTypeArray.",".$jsFieldNameArray.",".$jsErrorMsgArray.",".$jsRequiredMsgArray.",".$jsRequiredArray.");\"";	
	$endTable = RenderViews::hiddenField('report_id', $id);
	$buttonArray[] = RenderViews::formButton('submit','submit_button',OOZ_TXT_74,'formButton',$javascript);
	$buttonArray[] = RenderViews::formButton('reset','reset',OOZ_TXT_75,'formButton');
	$endTable .= RenderViews::endFormButtons($buttonArray);
	$tableRows .= RenderViews::tableData('2', '', '', '' , 'tdc1', array($endTable), 'row');
	$html .= RenderViews::table('95%', '0', '5', '0', 'tableIndent', $tableRows);
	if ($id == '') {
		define('OOZ_HEADING', APP_TXT_7);
	} else {
		define('OOZ_HEADING', APP_TXT_8);
	}
	define('OOZ_BODY', $html);
	RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
}
function showMultiReport($id = '')
{
	if ($id == '') {
		$html = RenderViews::buildStartForm(OOZ_REP_SUB_URL . '&option=add_multi_report');
	} else {
		$columnArray = array ('*');
		$condition = "WHERE report_id = '$id'";
		$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_multi', $columnArray, $condition);
		$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		$fieldValues = DB::fetchArray($result);
		$html = RenderViews::buildStartForm(OOZ_REP_SUB_URL . '&option=update_multi_report&id=' . $id);
	}
	$tableRows = RenderViews::tableData('2', '', array('center'), '', 'tdcHeading', array(APP_TXT_9), 'row');
	$reportFields[APP_TXT_3] = RenderViews::textBox('report_name', @$fieldValues['report_name'], OOZ_SET_FORM_FIELD_WIDTH, 'formField');
	// Build group menu
	$columnArray = array('group_id', 'group_name');
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'groups', $columnArray);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	// Create group array then render menu
	while ($row = DB::fetchArray($result)) {
		$groupValue[] = $row['group_id'];
		$groupName[] = $row['group_name'];
	}
	$reportFields[APP_TXT_4] = RenderViews::menu('security_group' , $groupValue, $groupName, @$fieldValues['security_group'], 'formField');
	$columnArray = array('report_id', 'report_name');
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_reports', $columnArray);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$searchCount = DB::numRows($result);
	$reports = '';
	if ($searchCount > 0) {
		$reportArray = explode('}-{',@$fieldValues['bound_reports']);
		while ($row = DB::fetchArray($result)) {
			$value = (in_array($row['report_id'], $reportArray)) ? $row['report_id'] : '' ;
			@$reports .= RenderViews::checkBox('bind_report_' . $row['report_id'], $row['report_id'], $value, 'formField') . ' ' . $row['report_name'] . '<br>';
		}
	} else {
		$reports = APP_TXT_6;
	}
	$reportFields[APP_TXT_5] = $reports;
	$i = 0;
	foreach ($reportFields as $name => $field) {
		$cellData = array ('<strong>' . $name . '</strong>', $field);
		$tableRows .= RenderViews::tableData('', array('30%', '70%'), '', '' , 'tdc1', $cellData, 'row');
		$i++;
	}
	$endTable = RenderViews::hiddenField('report_id', $id);
	$jsFieldNameArray = "['report_name']";
	$jsTestTypeArray = "['']";
	$jsErrorMsgArray = "['']";
	$jsRequiredMsgArray = "['". APP_TXT_17."']";
	$jsRequiredArray = "[true]";
	$javascript = "onClick=\"javascript:return fieldCheck('".OOZ_TXT_468."',".$jsTestTypeArray.",".$jsFieldNameArray.",".$jsErrorMsgArray.",".$jsRequiredMsgArray.",".$jsRequiredArray.");\"";	
	$buttonArray[] = RenderViews::formButton('submit','submit_button',OOZ_TXT_74,'formButton',$javascript);
	$buttonArray[] = RenderViews::formButton('reset','reset',OOZ_TXT_75,'formButton');
	$endTable .= RenderViews::endFormButtons($buttonArray);
	$tableRows .= RenderViews::tableData('2', '', '', '' , 'tdc1', array($endTable), 'row');
	$html .= RenderViews::table('95%', '0', '5', '0', 'tableIndent', $tableRows);
	if ($id == '') {
		define('OOZ_HEADING', APP_TXT_7);
	} else {
		define('OOZ_HEADING', APP_TXT_8);
	}
	define('OOZ_BODY', $html);
	RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
}
/**
 * addUpdateReport()
 *
 * @return Succss message html
 */
function addUpdateReport($id = '')
{
	if ($id == '') {
		// Check for duplicate and respond with a return message if exists
		$sql = "SELECT report_name FROM " . OOZ_SET_TABLE_PREFIX . "ooz_reportmanager_reports WHERE report_name = '" . $_POST['report_name'] . "'";
		$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		if (DB::numRows($result) > 0) {
			$html = RenderViews::showResponse(APP_TXT_16, RenderViews::url('javascript: history.go(-1)', OOZ_TXT_306, 'URL'));
			define('OOZ_BODY', $html);
			define('OOZ_HEADING', APP_TXT_34);
			RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
			exit;
		}
	}
	// Add or update
	$columnArray['report_id'] = DB::newID(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_reports','report_id');
	$columnArray['report_name'] = $_POST['report_name'];
	$i = 0;
	foreach($_POST as $key => $value) {
		if (stristr($key, 'search_')) {
			($i == 0) ? $savedSearchString = $value : $savedSearchString .= '}-{' . $value;
			$i++;
		}
	}
	$columnArray['saved_searches'] = $savedSearchString;
	$columnArray['security_group'] = $_POST['security_group'];
	if ($id == '') {
		$sql = DB::sqlInsert(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_reports', $columnArray);
		DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		// Success messagae
		$url = RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage', APP_TXT_43, 'URL');
		$html = RenderViews::showResponse(APP_TXT_18, $url);
	} else {
		$condition = "WHERE report_id = '$id'";
		$sql = DB::sqlUpdate(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_reports', $columnArray, $condition);
		DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		// Success messagae
		$url = RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage', APP_TXT_43, 'URL');
		$html = RenderViews::showResponse(APP_TXT_33, $url);
	}
	define('OOZ_BODY', $html);
	define('OOZ_HEADING', APP_TXT_34);
	RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
}
function addUpdateMultiReport($id = '')
{
	if ($id == '') {
		// Check for duplicate and respond with a return message if exists
		$sql = "SELECT report_name FROM " . OOZ_SET_TABLE_PREFIX . "ooz_reportmanager_multi WHERE report_name = '" . $_POST['report_name'] . "'";
		$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		if (DB::numRows($result) > 0) {
			$html = RenderViews::showResponse(APP_TXT_16, RenderViews::url('javascript: history.go(-1)', OOZ_TXT_306, 'URL'));
			define('OOZ_BODY', $html);
			define('OOZ_HEADING', APP_TXT_34);
			RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
			exit;
		}
	}
	// Add or update
	$columnArray['report_name'] = $_POST['report_name'];
	$i = 0;
	foreach($_POST as $key => $value) {
		if (stristr($key, 'bind_report_')) {
			($i == 0) ? $reportString = $value : $reportString .= '}-{' . $value;
			$i++;
		}
	}
	$columnArray['bound_reports'] = $reportString;
	$columnArray['security_group'] = $_POST['security_group'];
	if ($id == '') {
		$sql = DB::sqlInsert(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_multi', $columnArray);
		DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		// Success messagae
		$url = RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage', APP_TXT_43, 'URL');
		$html = RenderViews::showResponse(APP_TXT_18, $url);
	} else {
		$condition = "WHERE report_id = '$id'";
		$sql = DB::sqlUpdate(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_multi', $columnArray, $condition);
		DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		// Success messagae
		$url = RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzeroreportmanager_manage', APP_TXT_43, 'URL');
		$html = RenderViews::showResponse(APP_TXT_33, $url);
	}
	define('OOZ_BODY', $html);
	define('OOZ_HEADING', APP_TXT_34);
	RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
}
/**
 * showSavedSearches()
 *
 * @param mixed $userID Sesssion ID
 * @return Saved Search html
 */
function showReportCriteria($userID)
{
	// Start table

	$headingRow = RenderViews::tableData('', '', 'left', '', array('tdcHeadingBottomBorder'), array (APP_TXT_28), 'row');
	$html = RenderViews::table('98%', '0', '0', '0', '', $headingRow);
	$sql = "SELECT * FROM " . OOZ_SET_TABLE_PREFIX . "saved_searches WHERE user = '$userID' OR user = 'all' or user = 'system' AND application='app_oneorzeroreportmanager_main'";
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$tableRows = '';
	if (DB::numRows($result) > 0) {
		while ($row = DB::fetchArray($result)) {
			$searchUrl = $row['search_name'];
			$deleteUrl = RenderViews::url('index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=delete_saved_search&id=' . $row['search_id'], APP_TXT_25, 'URL');
			$savedSearch = $searchUrl . ' (' . $deleteUrl . ')<br>' . $row['search_description'];
			$tableRows .= RenderViews::tableData('', '', '', '', 'tdNavigationInsetShaded', array($savedSearch), 'row');
		}
	} else {
		$tableRows = RenderViews::tableData('', '', '', '', 'tdNavigationInsetShaded', array(APP_TXT_6), 'row');
	}
	$html .= RenderViews::table('100%', '0', '5', '0', 'tcBorder', $tableRows);
	define('OOZ_BODY', $html);
	define('OOZ_HEADING', APP_TXT_5);
	RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
}
/**
 * buildReport()
 *
 * @param mixed $reportCriteriaArray Saved criteria ID array
 * @return Multidimensional array containing criteria name and count
 */
function buildReport($reportCriteriaArray)
{
	// Get saved reports list and return an array containing each report criteria with count
	foreach($reportCriteriaArray as $searchID) {
		if ($searchID != '') {
			// Get stored search sql
			$condition = "WHERE search_id='$searchID'";
			$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'saved_searches', array('search_name', 'saved_search_sql'), $condition);
			$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
			$row = DB::fetchArray($result);
			if ($row['saved_search_sql'] != '') {
				// Execute the stored sql and return count
				$storedSearchResult = DB::query($row['saved_search_sql'], DSN, OOZ_SET_SHOW_SQL);
				// Create our report criteria array
				$criteriaCountArray[$row['search_name']] = DB::numRows($storedSearchResult);
			}
		} else {
			$criteriaCountArray[APP_TXT_31] = '';
		}
	}
	return $criteriaCountArray;
}
function horizontalGraph($criteriaCountArray, $reportName)
{
	// Sort array and reverse to get highest value
	$hightestCount = max($criteriaCountArray);
	// Create horizontal graph using criteria Attay
	$tableRows = '';
	$totalCount = 0;
	foreach($criteriaCountArray as $key => $value) {
		$totalCount = $totalCount + $value;
	}
	foreach($criteriaCountArray as $key => $value) {
		if ($hightestCount == 0) {
			$percent = 0;
		} else {
			$percent = round(($value / $hightestCount) * 100);
			// if less than one percent but there is a value to show then set to one percent so that the link will display
			if ($percent <= 1 && $value >= 1) $percent = 1;
		} // if total
		$width = $percent * 0.95; // so graph does not fill entire cell
		$graphRow = RenderViews::tableData('', '', '', '', 'tdHorizontalGraph', array(''), 'row');
		$reportPercentage = round($value / $totalCount * 100);
		$countRow = '<strong>' . $reportPercentage.'% ('.$value . ')</strong>';
		$graphTable = RenderViews::table($width . '%', '0', '0', '0', '', $graphRow) ;
		$tableRows .= RenderViews::tableData('', array('30%', '10%', '60%'), '', '', 'tdc1', array($key, $countRow, $graphTable), 'row');
	}
	$html = RenderViews::table('100%', '0', '5', '0', 'tcBorder', $tableRows);

	return $html;
}
/**
 * getReport()
 *
 * @param mixed $type Report output type (csv or graph)
 * @param mixed $id Report id
 * @return Graph or CSV
 */
function getReport($type, $id)
{
	// Get report information ready for generation of criteria count
	$condition = "WHERE report_id='$id'";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_reports', array('report_name', 'saved_searches'), $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$row = DB::fetchArray($result);
	$reportName = $row['report_name'];
	$reportCriteriaArray = explode('}-{', $row['saved_searches']);
	$criteriaCountArray = buildReport($reportCriteriaArray);
	// Return the appropriate report
	switch ($type) {
		case 'csv': ;
		// Generate csv file with the same name as the report, containing each report criteria with counts
		$i = 0;
		foreach($criteriaCountArray as $key => $value) {
			($i == 0) ? $headings = $key : $headings .= ',' . $key;
			($i == 0) ? $counts = $value : $counts .= ',' . $value;
			$i++;
		}
		$csv = $headings . "\n";
		$csv .= $counts;
		$fileName = str_replace(' ', '_' , $reportName) . '.csv';
		// Output to browser after emptying the output buffer
		ob_clean();
		header('Cache-control: private');
		header('Content-type: text/csv');
		header('Content-disposition:  attachment; filename="' . $fileName . '"');
		print $csv;
		exit;
		break;
		case 'graph': ;
		$html = horizontalGraph($criteriaCountArray, $reportName);
		define ('OOZ_HEADING', APP_TXT_22 . ' - ' . $reportName);
		define ('OOZ_BODY', $html);
		RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);

		break;
		default: ;
	} // switch
}
function showMultiReportGraph($id, $type = '')
{
	// Get report information ready for generation of criteria count
	$condition = "WHERE report_id='$id'";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_multi', array('bound_reports'), $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$row = DB::fetchArray($result);
	$boundReportArray = explode('}-{', $row['bound_reports']);
	$i = 0;
	$condition = '';
	foreach ($boundReportArray as $reportID){
		$condition .= ($i == 0) ? "WHERE report_id = '$reportID'" : " OR report_id = '$reportID'";
		$i++;
	}
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_reportmanager_reports', array('report_id','report_name', 'saved_searches'), $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$tableRows = '';
	while($row = DB::fetchArray($result)){
		$reportCriteriaArray = explode('}-{', $row['saved_searches']);
		$criteriaCountArray = buildReport($reportCriteriaArray);
		$graph = horizontalGraph($criteriaCountArray,'');
		$csvURL = RenderViews::url(OOZ_REP_BASE_URL.'&subcontroller=app_oneorzeroreportmanager_manage&option=get_csv&id='.$row['report_id'],APP_TXT_20,'URL');
		$tableRows .= RenderViews::tableData('', '', '', '', 'tdcHeadingBottomBorder', array($row['report_name'].' - '.$csvURL), 'row');
		$tableRows .= RenderViews::tableData('', '', '', '', '', array($graph), 'row');
	}
	$html = RenderViews::table('100%', '0', '0', '0', '', $tableRows) ;
	define ('OOZ_HEADING', APP_TXT_42);
	define ('OOZ_BODY', $html);
	RenderViews::renderPage('all_actions', OOZ_SET_INSTALL_PATH, OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
}
/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
	case 'create_report' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		showReport();
		break;
	case 'create_multi_report' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		showMultiReport();
		break;
	case 'edit_report' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		showReport($_GET['id']);
		break;
	case 'edit_multi_report' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		showMultiReport($_GET['id']);
		break;
	case 'add_report' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		addUpdateReport();
		break;
	case 'update_report' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		addUpdateReport($_GET['id']);
		break;
	case 'add_multi_report' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		addUpdateMultiReport();
		break;
	case 'update_multi_report' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		addUpdateMultiReport($_GET['id']);
		break;
	case 'manage_reports' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		showMyReports('Yes');
		break;
	case 'manage_multi_reports' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		showMyMultiReports('Yes');
		break;
	case 'show_report_criteria' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		showReportCriteria($_SESSION['access_user_id']);
		break;
	case 'get_csv' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 5);
		getReport('csv', $_GET['id']);
		break;
	case 'show_graph' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 5);
		getReport('graph', $_GET['id']);
		break;
	case 'show_multi_graph' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 5);
		showMultiReportGraph($_GET['id']);
		break;
	case 'delete_report' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		deleteReport($_GET['id']);
		break;
	case 'delete_multi_report' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 3);
		deleteMultiReport($_GET['id']);
		break;
	case 'view_multi_reports' :
		RenderViews::secureEnd($_SESSION['access_role_id'], 5);
		showMyMultiReports();
		break;
	default;
	showModules();
}

?>