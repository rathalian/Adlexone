<?php
/**
 * OneOrZero AIMS License Agreement 1.0
 *
 * 1. Copying the OneOrZero AIMS software and distributing as your own software
 *  without the written permission of OneOrZero is forbidden under the terms of
 *  the OneOrZero AIMS License.
 * 2. You may modify your copy of the OneOrZero AIMS software, however where
 *  OneOrZero AIMS files contain the OneOrZero AIMS license in the header of the file, the
 *  OneOrZero AIMS License header must remain.
 * 3. OneOrZero, and the copyright holders of the OneOrZero AIMS, provide no
 *  warranty for the data created or managed by your OneOrZero AIMS installation.
 * 4. OneOrZero, and the copyright holders of the OneOrZero AIMS, provide no
 *  warranty for your OneOrZero AIMS configuration or the hosting environment
 *  your OneOrZero AIMS installation operates in.
 * 5. OneOrZero, and the copyright holders of the OneOrZero AIMS, provide no
 *  warranty for the OneOrZero AIMS where the software has been modified by
 *  third parties (i.e. other than OneOrZero), unless an agreement has been
 *  reached with OneOrZero.
 * 6. By using the OneOrZero AIMS, you are indicating your acceptance of the
 *  stated OneOrZero AIMS License terms and conditions.
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */
/**
 * /*******************************************************************************
 * Required Libraries
 */
require_once OOZ_SET_FRAMEWORK_PATH . "/lib/ooz_render.class.php";
require_once OOZ_SET_FRAMEWORK_PATH . "/lib/ooz_file.class.php";
require_once OOZ_SET_FRAMEWORK_PATH . '/abstract/ooz_' . OOZ_SET_DB_TYPE . '_wrap.class.php';
/**
 * Controller specific constants
 */
define('OOZ_TIM_SUB_URL', 'index.php?controller=app_oneorzerotimemanager_main&subcontroller=app_oneorzerotimemanager_manage');
function timeInString($timeInSecs) {
	$numDays = floor($timeInSecs / 86400);
	$numHours = floor(($timeInSecs - ($numDays * 86400)) / 3600);
	$numMins = floor(($timeInSecs - ($numDays * 86400) - ($numHours * 3600)) / 60);
	//$numSecs = round($timeInSecs - ($numDays * 86400) - ($numHours * 3600) - ($numMins * 60));
	return $numDays . ':' . $numHours . ':' . $numMins;
}
function javaScriptAdd() {

	$htmlScript = '<script language="JavaScript" type="text/javascript">';
	$htmlScript .= "var timeSpent = 0; var fieldmins = document.getElementsByName('minutes');
		var fieldover = document.getElementsByName('override');
		function timer() { setTimeout('timer()', 1000); if(!document.forms['appform']) return;	
		if (!fieldover) return; if (fieldover[0].checked) return;
		if (timeSpent == 0 && fieldmins[0].value > 0) { timeSpent = fieldmins[0].value * 60; }
	    timeSpent++; var seconds = timeSpent%60; if(seconds < 10) seconds = '0' + seconds.toString();
	    var minutes = (timeSpent-seconds)/60;fieldmins[0].value = minutes; 
	    } window.onload = timer;</script>";
	return $htmlScript;
}
function showAddUpdateTime($timeEntryID = '')
{
	$html = "";

	if ($timeEntryID != ''){
		$columnArray = array ('*');
		$condition = "WHERE time_entry_id = '".$timeEntryID."'";
		$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_temp_time_table', $columnArray, $condition);
		$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		$valuesArray = DB::fetchArray($result);
		if (@$valuesArray['entry_identifier'] == '1') {
			$html = javaScriptAdd();
		}
	}

	$html .= Render::startForm(OOZ_TIM_SUB_URL . '&option=add_update_time', 'POST', 'addUpdateTime','');
	//Time Code
	$columnArray = array ('menu_value');
	$condition = "WHERE custom_field_id = '".TIME_SET_JOB_CODE_MAP."'";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'custom_field_menu_values', $columnArray, $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$menuArray[] = APP_TXT_32;
	while ($menuRow = DB::fetchArray($result)) {
		$menuArray[] = $menuRow['menu_value'];
	} // while
	$timeFields[APP_TXT_8] = Render::menu('job_code', $menuArray, $menuArray, @$valuesArray['job_code'], 'formField', 'onChange="document.addTime.submit();"');
	unset($menuArray);
	//Project field
	$columnArray = array ('menu_value');
	$condition = "WHERE custom_field_id = '".TIME_SET_PROJECT_MAP."'";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'custom_field_menu_values', $columnArray, $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$menuArray[] = APP_TXT_27;
	while ($menuRow = DB::fetchArray($result)) {
		$menuArray[] = $menuRow['menu_value'];
	} // while
	$timeFields[APP_TXT_7] = Render::menu('project', $menuArray, $menuArray, @$valuesArray['project'], 'formField', 'onChange="document.addTime.submit();"');
	unset($menuArray);
	//Cost Center
	$columnArray = array ('menu_value');
	$condition = "WHERE custom_field_id = '".TIME_SET_COST_CENTER_MAP."'";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'custom_field_menu_values', $columnArray, $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$menuArray[] = APP_TXT_27;
	while ($menuRow = DB::fetchArray($result)) {
		$menuArray[] = $menuRow['menu_value'];
	} // while
	$timeFields[APP_TXT_30] = Render::menu('cost_center', $menuArray, $menuArray, @$valuesArray['cost_center'], 'formField');
	unset($menuArray);
	//Fixed Cost
	$columnArray = array ('menu_value');
	$condition = "WHERE custom_field_id = '".TIME_SET_FIXED_COST_MAP."'";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'custom_field_menu_values', $columnArray, $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$menuArray[] = APP_TXT_27;
	while ($menuRow = DB::fetchArray($result)) {
		$menuArray[] = $menuRow['menu_value'];
	} // while
	$timeFields[APP_TXT_31] = Render::menu('fixed_cost', $menuArray, $menuArray,@$valuesArray['fixed_cost'], 'formField');
	unset($menuArray);
	if($timeEntryID != ''){
		$timeFields[APP_TXT_9] = Render::textBox('minutes', @$valuesArray['minutes'], OOZ_SET_FORM_FIELD_WIDTH, 'formField');
		$timeFields[APP_TXT_11] = Render::checkBox('override','override','','formField');
	}
	if($timeEntryID != ''){
		$timeFields[APP_TXT_34] = Render::textBox('ammended_add_date', @$valuesArray['ammended_add_date'], OOZ_SET_FORM_FIELD_WIDTH, 'formField');
	}
	foreach ($timeFields as $name => $field) {
		$cellData = array ('<strong>' . $name . '</strong>', $field);
		@$tableRows .= Render::tableData('', array('1%', '99%'), array('left', 'left'), '', array('tdform', 'tdformIndent'), $cellData, 'row');
	}
	$endForm = Render::hiddenField('time_entry_id',$timeEntryID);
	if(@$valuesArray['entry_identifier'] != '1'){
		$buttonArray[] = Render::formButton('submit','start_time',APP_TXT_5,'greenFormButton');
	}
	//Javascript field validation
	$jsFieldNameArray = "['ammended_add_date','minutes']";
	$jsDateFormat = (OOZ_SET_DATE_FORMAT == "d-m-Y, h:i A") ? "DMY" : "MDY";
	$jsTestTypeArray = "['datetime,".$jsDateFormat.",HHMM','numeric']";
	$dateFormat = (OOZ_SET_DATE_FORMAT == "d-m-Y, h:i A") ? "d/m/y" : "m/d/y";
	$jsErrorMsgArray = "['".APP_TXT_33." ".$dateFormat." hh:mm','".APP_TXT_36."']";
	$jsRequiredMsgArray = "['','']";
	$jsRequiredArray = "[false,false]";
	$javascript = "onClick=\"javascript:return fieldCheck('".OOZ_TXT_468."',".$jsTestTypeArray.",".$jsFieldNameArray.",".$jsErrorMsgArray.",".$jsRequiredMsgArray.",".$jsRequiredArray.");\"";
	if(@$valuesArray['entry_identifier'] != '2' AND isset($valuesArray['entry_identifier'])){
		$buttonArray[] = Render::formButton('submit','stop_time',APP_TXT_6,'redFormButton',$javascript);
	}
	if($timeEntryID != ''){
		$buttonArray[] = Render::formButton('submit','add_time',APP_TXT_15,'blueFormButton',$javascript);
		$buttonArray[] = Render::formButton('submit','delete',APP_TXT_18,'formButton','onClick="javascript:return confirm(\''.OOZ_TXT_400.'\')"');
	}
	$endForm .= Render::endFormButtons($buttonArray);
	$tableRows .= Render::tableData('2', '', '', '' , 'tdc1', array($endForm), 'row');
	$html .= Render::table('95%', '0', '5', '0', 'tableIndent', $tableRows);
	switch (@$valuesArray['entry_identifier']) {
		case '1':
			$heading = APP_TXT_10.' - '.APP_TXT_28;
			break;
		case '2':
			$heading = APP_TXT_10.' - '.APP_TXT_29;
			break;
		default:
			$heading = APP_TXT_10;
	}
	define('OOZ_HEADING', $heading);
	define('OOZ_BODY', $html);
	Render::renderPage('all_actions', OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
}
function addUpdateTime(){
	//Initial start
	if (@$_POST['start_time'] != '' AND @$_POST['time_entry_id'] == ''){
		// Add initial entry to temp time table
		$timeEntryID = DB::newID(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_temp_time_table', 'time_entry_id');
		$columnArray['time_entry_id'] = $timeEntryID;
		$columnArray['start_date'] = time();
		$columnArray['user_id'] = $_SESSION['access_user_id'];
		$columnArray['entry_identifier'] = '1';
		$columnArray['sequence'] = '1';
		$columnArray['project'] = $_POST['project'];
		$columnArray['job_code'] = $_POST['job_code'];
		$columnArray['cost_center'] = $_POST['cost_center'];
		$columnArray['fixed_cost'] = $_POST['fixed_cost'];
		$sql = DB::sqlInsert(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_temp_time_table', $columnArray);
		DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		showAddUpdateTime($timeEntryID);
		//Stop and subsequent starts
	}elseif(@$_POST['time_entry_id'] != '' AND (@$_POST['start_time'] != '' OR @$_POST['stop_time'] != '')){
		// Check for intial entry in temp time table and update but leave as a pending temp entry
		$columnArray = array('*');
		$condition = "WHERE time_entry_id = '".$_POST['time_entry_id']."' AND user_id = '".$_SESSION['access_user_id']."'";
		$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_temp_time_table', $columnArray, $condition);
		$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		$row = DB::fetchArray($result);
		unset($columnArray);
		$identifier = (isset($_POST['start_time']) AND $_POST['start_time'] != '') ? '1' : '2';
		$columnArray['entry_identifier'] = $identifier;
		$columnArray['minutes'] = $_POST['minutes'];
		$columnArray['project'] = $_POST['project'];
		$columnArray['job_code'] = $_POST['job_code'];
		$columnArray['cost_center'] = $_POST['cost_center'];
		$columnArray['fixed_cost'] = $_POST['fixed_cost'];
		$condition = "WHERE time_entry_id = '".$_POST['time_entry_id']."' AND user_id = '".$_SESSION['access_user_id']."'";
		$sql = DB::sqlUpdate(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_temp_time_table', $columnArray,$condition);
		DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		showAddUpdateTime($_POST['time_entry_id']);
	}elseif(@$_POST['add_time'] != ''){
		// Get temporary entry
		$columnArray = array('*');
		$condition = "WHERE time_entry_id = '".$_POST['time_entry_id']."' AND user_id = '".$_SESSION['access_user_id']."'";
		$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_temp_time_table', $columnArray, $condition);
		$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		$row = DB::fetchArray($result);
		unset($columnArray);
		// Add permanent entry
		$timeEntryID = DB::newID(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_time_table', 'time_entry_id');
		$columnArray['time_entry_id'] = $timeEntryID;
		$columnArray['start_date'] = $row['start_date'];
		$columnArray['user_id'] = $_SESSION['access_user_id'];
		$columnArray['entry_identifier'] = '3';
		$columnArray['sequence'] = '1';
		$columnArray['project'] = $_POST['project'];
		$columnArray['job_code'] = $_POST['job_code'];
		$columnArray['cost_center'] = $_POST['cost_center'];
		$columnArray['minutes'] = $_POST['minutes'];
		$columnArray['fixed_cost'] = $_POST['fixed_cost'];
		$columnArray['add_date'] = time();
		if (@$_POST['ammended_add_date'] != ''){
			$splitDateTime = explode(' ',$_POST['ammended_add_date']);
			$dateArray = explode('/',$splitDateTime[0]);
			$timeArray = explode(':',$splitDateTime[1]);
			if(OOZ_SET_DATE_FORMAT == "d-m-Y, h:i A"){
				$unixTime = mktime($timeArray[0],$timeArray[1],0,$dateArray[1],$dateArray[0],$dateArray[2],0);
			}else{
				$unixTime =  mktime($timeArray[0],$timeArray[1],0,$dateArray[0],$dateArray[1],$dateArray[2],0);
			}
		}else{
			$unixTime = '';
		}
		$columnArray['ammended_add_date'] = $unixTime;
		$sql = DB::sqlInsert(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_time_table', $columnArray);
		DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		// Remove
		$condition = "WHERE time_entry_id = '".$_POST['time_entry_id']."' AND user_id = '".$_SESSION['access_user_id']."'";
		$sql = DB::sqlDelete(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_temp_time_table', $condition);
		DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		$html = Render::showResponse(APP_TXT_35);
		define('OOZ_HEADING', APP_TXT_10);
		define('OOZ_BODY', $html);
		Render::renderPage('all_actions', OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
	}elseif(@$_POST['delete'] != ''){
		// Remove
		$condition = "WHERE time_entry_id = '".$_POST['time_entry_id']."' AND user_id = '".$_SESSION['access_user_id']."'";
		$sql = DB::sqlDelete(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_temp_time_table', $condition);
		DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		$html = Render::showResponse(APP_TXT_37);
		define('OOZ_HEADING', APP_TXT_10);
		define('OOZ_BODY', $html);
		Render::renderPage('all_actions', OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
			
	}else{
		showAddUpdateTime($timeEntryID);
	}
}
function showTimeReports($userID) {
	$menuSortArray = array(APP_TXT_8,APP_TXT_46,APP_TXT_7,APP_TXT_30,APP_TXT_25);
	$menuSortIdArray = array('job_code','user_id','project','cost_center','item_id');
	$menuSumDetArray = array(APP_TXT_49,APP_TXT_50);
	$menuSumDetIdArray = array('sum','det');
	$menuTypeIdArray = array('csv','rep');
	$menuTypeArray = array(APP_TXT_40,APP_TXT_52);
	//	Javascript field validation
	$jsFieldNameArray = "'repX_from','repX_to'";
	$jsDateFormat = (OOZ_SET_DATE_FORMAT == "d-m-Y, h:i A") ? "DMY" : "MDY";
	$jsFormatErrorMsg = (OOZ_SET_DATE_FORMAT == "d-m-Y, h:i A") ? "DD/MM/YY" : "MM/DD/YY";
	$jsTestTypeArray = "'date," . $jsDateFormat . "','date," . $jsDateFormat . "'";
	$jsErrorMsgArray = "'" . str_replace('XX/XX/XX',$jsFormatErrorMsg,APP_TXT_56) . "','" . str_replace('XX/XX/XX',$jsFormatErrorMsg,APP_TXT_57) . "'";
	$jsRequiredMsgArray = "'',''";
	$jsRequiredArray = "false,false";
	$javascript = "onClick=\"javascript:return fieldCheck(' ',[".$jsTestTypeArray."],[".$jsFieldNameArray."],[".$jsErrorMsgArray."],[".$jsRequiredMsgArray."],[".$jsRequiredArray."]);\"";
	$tmpMonth = (OOZ_SET_DATE_FORMAT == "d-m-Y, h:i A") ? 1 : 0;
	$tmpArray = getdate();
	$fromDate = (OOZ_SET_DATE_FORMAT == "d-m-Y, h:i A") ? $tmpArray['mday'] . '/' . $tmpArray['mon'] . '/' . substr($tmpArray['year'],2,2) : $tmpArray['mon'] . '/' . $tmpArray['mday'] . '/' . substr($tmpArray['year'],2,2);
	$toDate = $fromDate;

	// Time Code/User/Project/Cost Centre Summary/Detail
	$reportCounter = 1;
	$sortField = Render::menu('rep' . $reportCounter . '_sort_by', $menuSortIdArray, $menuSortArray,'job_code', 'formField');
	$sumDetField = Render::menu('rep' . $reportCounter . '_sum_det', $menuSumDetIdArray, $menuSumDetArray,'det', 'formField');
	$fromDateField = Render::textBox('rep' . $reportCounter . '_from', $fromDate, 10, 'formField');
	$toDateField = Render::textBox('rep' . $reportCounter . '_to', $toDate, 10, 'formField');
	$typeField = Render::menu('rep' . $reportCounter . '_type', $menuTypeIdArray, $menuTypeArray,'rep','formField');
	$repJavascript = str_replace('repX','rep' . $reportCounter, $javascript);
	$goButton = Render::formButton('submit','rep' . $reportCounter,APP_TXT_55,'formButton',$repJavascript);
	$nameArray[$reportCounter++] = $typeField . ' <strong>' . APP_TXT_43 . ': ' . $sortField . '&nbsp;&nbsp;' . APP_TXT_51 . ': ' . $sumDetField . '&nbsp;&nbsp;' . APP_TXT_53 . ': ' . $fromDateField . '&nbsp;&nbsp;' . APP_TXT_54 . ': ' . $toDateField . '</strong>&nbsp;&nbsp;' . $goButton;

	// By Specific Project
	$reportCounter = 2;
	$sortField = Render::menu('rep' . $reportCounter . '_sort_by', array_values(array_diff($menuSortIdArray,array('item_id','project'))), array_values(array_diff($menuSortArray,array(APP_TXT_25,APP_TXT_7))),'job_code', 'formField');
	$sumDetField = Render::menu('rep' . $reportCounter . '_sum_det', $menuSumDetIdArray, $menuSumDetArray,'det', 'formField');
	//Project field
	$columnArray = array ('menu_value');
	$condition = "WHERE custom_field_id = '".TIME_SET_PROJECT_MAP."'";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'custom_field_menu_values', $columnArray, $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	while ($menuRow = DB::fetchArray($result)) {
		$menuArray[] = $menuRow['menu_value'];
	} // while
	$projectField = Render::menu('rep' . $reportCounter . '_project', $menuArray, $menuArray, '', 'formField');
	unset($menuArray);

	$typeField = Render::menu('rep' . $reportCounter . '_type', $menuTypeIdArray, $menuTypeArray,'rep','formField');
	$goButton = Render::formButton('submit','rep' . $reportCounter,APP_TXT_55,'formButton','');
	$nameArray[$reportCounter++] = $typeField . ' <strong>' . APP_TXT_43 . ': ' . $sortField . '&nbsp;&nbsp;' . APP_TXT_51 . ': ' . $sumDetField . '&nbsp;&nbsp;' . APP_TXT_7 . ': ' . $projectField . '</strong>&nbsp;&nbsp;' . $goButton;
	unset($projectField);

	// By Specific Time Code
	$reportCounter = 3;
	$sortField = Render::menu('rep' . $reportCounter . '_sort_by', array_values(array_diff($menuSortIdArray,array('item_id','job_code'))), array_values(array_diff($menuSortArray,array(APP_TXT_25,APP_TXT_8))),'job_code', 'formField');
	$sumDetField = Render::menu('rep' . $reportCounter . '_sum_det', $menuSumDetIdArray, $menuSumDetArray,'det', 'formField');

	//Project field
	$columnArray = array ('menu_value');
	$condition = "WHERE custom_field_id = '".TIME_SET_JOB_CODE_MAP."'";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'custom_field_menu_values', $columnArray, $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	while ($menuRow = DB::fetchArray($result)) {
		$menuArray[] = $menuRow['menu_value'];
	} // while
	$timecodeField = Render::menu('rep' . $reportCounter . '_job_code', @$menuArray, @$menuArray, '', 'formField');
	unset($menuArray);

	$typeField = Render::menu('rep' . $reportCounter . '_type', $menuTypeIdArray, $menuTypeArray,'rep','formField');
	$goButton = Render::formButton('submit','rep' . $reportCounter,APP_TXT_55,'formButton','');
	$nameArray[$reportCounter++] = $typeField . ' <strong>' . APP_TXT_43 . ': ' . $sortField . '&nbsp;&nbsp;' . APP_TXT_51 . ': ' . $sumDetField . '&nbsp;&nbsp;' . APP_TXT_8 . ': ' . $timecodeField . '</strong>&nbsp;&nbsp;' . $goButton;
	unset($timecodeField);

	// By Specific Cost Centre
	$reportCounter = 4;
	$sortField = Render::menu('rep' . $reportCounter . '_sort_by', array_values(array_diff($menuSortIdArray,array('item_id','cost_center'))), array_values(array_diff($menuSortArray,array(APP_TXT_25,APP_TXT_30))),'cost_center', 'formField');
	$sumDetField = Render::menu('rep' . $reportCounter . '_sum_det', $menuSumDetIdArray, $menuSumDetArray,'det', 'formField');

	//Project field
	$columnArray = array ('menu_value');
	$condition = "WHERE custom_field_id = '".TIME_SET_COST_CENTER_MAP."'";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'custom_field_menu_values', $columnArray, $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	while ($menuRow = DB::fetchArray($result)) {
		$menuArray[] = $menuRow['menu_value'];
	} // while
	$costcenterField = Render::menu('rep' . $reportCounter . '_cost_center', @$menuArray, @$menuArray, '', 'formField');
	unset($menuArray);

	$typeField = Render::menu('rep' . $reportCounter . '_type', $menuTypeIdArray, $menuTypeArray,'rep','formField');
	$goButton = Render::formButton('submit','rep' . $reportCounter,APP_TXT_55,'formButton','');
	$nameArray[$reportCounter++] = $typeField . ' <strong>' . APP_TXT_43 . ': ' . $sortField . '&nbsp;&nbsp;' . APP_TXT_51 . ': ' . $sumDetField . '&nbsp;&nbsp;' . APP_TXT_30 . ': ' . $costcenterField . '</strong>&nbsp;&nbsp;' . $goButton;
	unset($costcenterField);

	// Time Code valid Item Id's Only
	$reportCounter = 5;
	$sortField = Render::menu('rep' . $reportCounter . '_sort_by', array('user_id','item_id'), array(APP_TXT_46,APP_TXT_25),'item_id', 'formField');
	$sumDetField = Render::menu('rep' . $reportCounter . '_sum_det', $menuSumDetIdArray, $menuSumDetArray,'det', 'formField');
	$fromDateField = Render::textBox('rep' . $reportCounter . '_from', $fromDate, 10, 'formField');
	$toDateField = Render::textBox('rep' . $reportCounter . '_to', $toDate, 10, 'formField');
	$typeField = Render::menu('rep' . $reportCounter . '_type', $menuTypeIdArray, $menuTypeArray,'rep','formField');
	$repJavascript = str_replace('repX','rep' . $reportCounter, $javascript);
	$goButton = Render::formButton('submit','rep' . $reportCounter,APP_TXT_55,'formButton',$repJavascript);
	$nameArray[$reportCounter++] = $typeField . ' <strong>' . '&nbsp;&nbsp;' . APP_TXT_51 . ': ' . $sumDetField . '&nbsp;&nbsp;' . APP_TXT_53 . ': ' . $fromDateField . '&nbsp;&nbsp;' . APP_TXT_54 . ': ' . $toDateField . '</strong>&nbsp;&nbsp;' . $goButton;

	// Time Code Time Manager only
	$reportCounter = 6;
	$sortField = Render::menu('rep' . $reportCounter . '_sort_by', array_values(array_diff($menuSortIdArray,array('item_id'))), array_values(array_diff($menuSortArray,array(APP_TXT_25))),'job_code', 'formField');
	$sumDetField = Render::menu('rep' . $reportCounter . '_sum_det', $menuSumDetIdArray, $menuSumDetArray,'det', 'formField');
	$fromDateField = Render::textBox('rep' . $reportCounter . '_from', $fromDate, 10, 'formField');
	$toDateField = Render::textBox('rep' . $reportCounter . '_to', $toDate, 10, 'formField');
	$typeField = Render::menu('rep' . $reportCounter . '_type', $menuTypeIdArray, $menuTypeArray,'rep','formField');
	$repJavascript = str_replace('repX','rep' . $reportCounter, $javascript);
	$goButton = Render::formButton('submit','rep' . $reportCounter,APP_TXT_55,'formButton',$repJavascript);
	$nameArray[$reportCounter++] = $typeField . ' <strong>' . APP_TXT_43 . ': ' . $sortField . '&nbsp;&nbsp;' . APP_TXT_51 . ': ' . $sumDetField . '&nbsp;&nbsp;' . APP_TXT_53 . ': ' . $fromDateField . '&nbsp;&nbsp;' . APP_TXT_54 . ': ' . $toDateField . '</strong>&nbsp;&nbsp;' . $goButton;

	// Item Id report
	$reportCounter = 7;
	$sortField = "";
	$sumDetField = Render::menu('rep' . $reportCounter . '_sum_det', $menuSumDetIdArray, $menuSumDetArray,'det', 'formField');
	$typeField = Render::menu('rep' . $reportCounter . '_type', $menuTypeIdArray, $menuTypeArray,'rep','formField');
	$jsFieldNameArray = "['rep'" . $reportCounter . "_item_id]";
	$jsTestTypeArray = "['']";
	$jsErrorMsgArray = "['']";
	$jsRequiredMsgArray = "['". OOZ_TXT_206."']";
	$jsRequiredArray = "[true]";
	$javascript = "onClick=\"javascript:return fieldCheck('".OOZ_TXT_468."',".$jsTestTypeArray.",".$jsFieldNameArray.",".$jsErrorMsgArray.",".$jsRequiredMsgArray.",".$jsRequiredArray.");\"";
	$itemIdField = Render::textBox('rep' . $reportCounter . '_item_id', '', 10, 'formField');
	$goButton = Render::formButton('submit','rep' . $reportCounter,APP_TXT_55,'formButton',$javascript);
	$nameArray[$reportCounter++] = $typeField . ' <strong>' . APP_TXT_51 . ': ' . $sumDetField . '&nbsp;&nbsp;' . APP_TXT_25 . '&nbsp;&nbsp;' . $itemIdField . '&nbsp;&nbsp;' . $goButton;

	// Saved searches report
	$reportCounter = 99;
	$sortField = "";
	$sumDetField = Render::menu('rep' . $reportCounter . '_sum_det', $menuSumDetIdArray, $menuSumDetArray,'det', 'formField');
	$typeField = Render::menu('rep' . $reportCounter . '_type', $menuTypeIdArray, $menuTypeArray,'rep','formField');
	$goButton = Render::formButton('submit','rep' . $reportCounter,APP_TXT_55,'formButton',$javascript);
	if ($_SESSION['access_role_id'] <= '1'){
		$sql = "SELECT * FROM " . OOZ_SET_TABLE_PREFIX . "saved_searches WHERE application = 'app_oneorzerotimemanager_main'";
	}else{
		$sql = "SELECT * FROM " . OOZ_SET_TABLE_PREFIX . "saved_searches WHERE (user = '" . $_SESSION['access_user_id'] . "' OR user = 'all')  AND application = 'app_oneorzerotimemanager_main'";
	}
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$i = 0;
	$savedIdArray = array();
	$savedArray = array();
	if (DB::numRows($result) > 0) {
		while ($row = DB::fetchArray($result)) {
			$savedIdArray[] = $row['search_id'];
			$savedArray[] = $row['search_name'];
		}
		$savedField = Render::menu('rep' . $reportCounter . '_saved',$savedIdArray,$savedArray,'det', 'formField');
		$nameArray[$reportCounter++] = $typeField . ' <strong>' .  APP_TXT_81 . ': ' . $savedField . '&nbsp;&nbsp;' . APP_TXT_51 . ': ' . $sumDetField . '&nbsp;&nbsp;</strong>' . $goButton;
	} else {
		$nameArray[$reportCounter++] = $typeField . ' <strong>' .  APP_TXT_51 . ': ' . $sumDetField . '&nbsp;&nbsp;</strong>' . $goButton;
	}

	// Show report based on type GET option
	$html = Render::startForm(OOZ_TIM_SUB_URL . '&option=show_report', 'POST', 'showReports','');
	switch(@$_GET['type']) {
		case 'date_range':
			$heading = APP_TXT_62;
			$tableRows = Render::tableData('', array('100%'), array('left'), '', '', array($nameArray[1]), 'row');
			break;
		case 'project':
			$heading = APP_TXT_63;
			$tableRows = Render::tableData('', array('100%'), array('left'), '', '', array($nameArray[2]), 'row');
			break;
		case 'job_code':
			$heading = APP_TXT_64;
			$tableRows = Render::tableData('', array('100%'), array('left'), '', '', array($nameArray[3]), 'row');
			break;
		case 'cost_center':
			$heading = APP_TXT_65;
			$tableRows = Render::tableData('', array('100%'), array('left'), '', '', array($nameArray[4]), 'row');
			break;
		case 'date_range_item':
			$heading = APP_TXT_62;
			$tableRows = Render::tableData('', array('100%'), array('left'), '', '', array($nameArray[5]), 'row');
			break;
		case 'date_range_no_item':
			$heading = APP_TXT_62;
			$tableRows = Render::tableData('', array('100%'), array('left'), '', '', array($nameArray[6]), 'row');
			break;
		case 'item_id':
			$heading = APP_TXT_62;
			$tableRows = Render::tableData('', array('100%'), array('left'), '', '', array($nameArray[7]), 'row');
			break;
		case 'saved':
			$heading = APP_TXT_80;
			$tableRows = Render::tableData('', array('100%'), array('left'), '', '', array($nameArray[99]), 'row');
			break;
		default:
			// Date range
			$heading = APP_TXT_62;
			$tableRows = Render::tableData('', array('100%'), array('left'), '', '', array($nameArray[1]), 'row');
	}
	//	for ($i = 1; $i < $reportCounter; $i++) {
	//		$class = Render::setOddEvenClass($i, 'tdc2', 'tdc1');
	//		$tableRows .= Render::tableData('', array('100%'), array('left'), '', $class, array($nameArray[$i]), 'row');
	//	}
	$html .= Render::table('98%', '0', '0', '0', 'tableIndent', $tableRows);
	$html .= Render::hiddenField('sortDirection','ASC');
	$html .= Render::hiddenField('currentSort','');
	$html .= '</form>';

	define ('OOZ_HEADING', $heading);
	define ('OOZ_BODY', $html);
	Render::renderPage('all_actions', OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
}
function createHeads($repNbr,$condition,$ignoreField = '',$ignoreHead = '', $repTitle = '',$savedSQL = ''){

	//Handle deletes
	$action = false;
	if (is_array($_POST)){
		foreach ($_POST as $key=>$value){
			if (stristr($key,'action_')){
				$actionArray = explode('_',$key);
				if ($value == 'delete'){
					if ($_SESSION['access_role_id'] >1){
						// Not allowed
						$html = Render::showResponse(APP_TXT_87);
						define('OOZ_BODY', $html);
						define('OOZ_HEADING', OOZ_TXT_356);
						Render::renderPage('all_actions', OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
						die;
					}
					$action = true;
					$sql = "DELETE FROM " . OOZ_SET_TABLE_PREFIX . "ooz_timemanager_time_table WHERE time_entry_id='" . $actionArray[1] . "'";
					DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
				}
			}
		}
	}
	$repSort = isset($_POST[$repNbr . '_sort_by']) ? $_POST[$repNbr . '_sort_by'] : '';
	if (isset($_POST[$repNbr . '_type'])){
		$repType = $_POST[$repNbr . '_type'];
	}else{
		$repType = $_GET[$repNbr . '_type'];
	}


	if (isset($_POST[$repNbr . '_sum_det'])){
		$repSumDet = $_POST[$repNbr . '_sum_det'];
	}else{
		$repSumDet = $_GET[$repNbr . '_sum_det'];
	}
	$tmpSort = ($repSumDet == "sum") ? 'item_id' : 'start_date';
	$repSort = ($repSort == "") ? $tmpSort : $repSort;
	$repFrom = (isset($_POST[$repNbr . '_from'])) ? $_POST[$repNbr . '_from'] : '';
	$repTo = (isset($_POST[$repNbr . '_to'])) ? $_POST[$repNbr . '_to'] : '';
	$repProject = (isset($_POST[$repNbr . '_project'])) ? $_POST[$repNbr . '_project'] : '';
	$repCostCenter = (isset($_POST[$repNbr . '_cost_center'])) ? $_POST[$repNbr . '_cost_center'] : '';
	$repTimeCode = (isset($_POST[$repNbr . '_job_code'])) ? $_POST[$repNbr . '_job_code'] : '';
	if (isset($_POST[$repNbr . '_item_id'])){
		$repItemId = (isset($_POST[$repNbr . '_item_id'])) ? $_POST[$repNbr . '_item_id'] : '';
	}else{
		$repItemId = (isset($_GET[$repNbr . '_item_id'])) ? intval($_GET[$repNbr . '_item_id']) : '';
	}

	$repSortDirection = (isset($_POST['sortDirection'])) ? $_POST['sortDirection'] : 'ASC';
	$repCurrentSort = (isset($_POST['currentSort'])) ? $_POST['currentSort'] : 'ASC';
	$logEntry = (OOZ_SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
	$attachments = (OOZ_SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
	$itemOnlyInformation = ($repNbr == 'rep5' || $repNbr == 'rep7' || $repNbr == 'rep99');

	$repSortDirection = ($repSortDirection == "ASC" && $repSort == $repCurrentSort AND $action == false) ? 'DESC' : 'ASC';
	$repCurrentSort = $repSort;

	$dateFormat = (OOZ_SET_DATE_FORMAT == "d-m-Y, h:i A") ? 'd/m/Y h:i' : 'm/d/Y h:i';

	// If summary is of users or detailed display need to get users
	if (($repSumDet == "sum" && $repSort == "user_id") || ($repSumDet == "det")) {
		$sqlUsers = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'users', array("user_id","first_name","last_name"), "");
		$resultUsers = DB::query($sqlUsers, DSN, OOZ_SET_SHOW_SQL);
		while ($rowUsers = DB::fetchArray($resultUsers)) {
			$usersArray[$rowUsers['user_id']] = $rowUsers['first_name'] . ' ' . $rowUsers['last_name'];
		}
	}
	if ($repSort != "calc_add_date" && $repSort != "time_diff") {
		$columnArray[] = $repSort;
	}

	//Append time entry id to column array
	$columnArray[] = 'time_entry_id';


	if ($repSumDet == "det") {
		if ($ignoreField != 'job_code') {
			$columnArray[] = 'job_code';
		}
		if ($ignoreField != 'user_id') {
			$columnArray[] = 'user_id';
		}
		if ($ignoreField != 'project') {
			$columnArray[] = 'project';
		}
		if ($ignoreField != 'cost_center') {
			$columnArray[] = 'cost_center';
		}
		if ($ignoreField != 'item_id') {
			$columnArray[] = 'item_id';
		}
		$columnArray[] = 'start_date';
		$columnArray[] = 'if(ammended_add_date = 0,add_date,ammended_add_date) - start_date AS time_diff';
		$columnArray[] = 'minutes';
		$columnArray[] = 'if(ammended_add_date = 0,add_date,ammended_add_date) AS calc_add_date';
		$columnArray[] = 'fixed_cost';
		$condition .= ' ORDER BY ' . $repSort . ' ' . $repSortDirection;
	} else {
		$columnArray[] = 'SUM(if(ammended_add_date = 0,add_date,ammended_add_date) - start_date) AS time_diff';
		$columnArray[] = 'SUM(minutes) AS minutes';
		$columnArray[] = 'SUM(fixed_cost) AS fixed_cost';
		$columnArray[] = 'count(1) AS num_recs';
		$condition .= ' GROUP BY ' . $repSort  . ' ' . $repSortDirection;
	}

	if (!isset($_POST['countrecs']) OR $action == true) {
		$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_time_table', $columnArray, $condition);

		if ($savedSQL != '' && $repNbr == 'rep99') {
			$sql = $savedSQL . $condition;
		}

		$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
		$countrecs = DB::numrows($result);
		$totalTime = 0;
		$totalMinutes = 0;
		$totalFixed = 0;
		while ($row = DB::fetchArray($result)) {
			$totalTime += $row['time_diff'];
			$totalMinutes += $row['minutes'];
			$totalFixed += $row['fixed_cost'];
		}
		$totalAvgTime = ($countrecs > 0) ? $totalTime / $countrecs : 0;
	} else {
		$countrecs = $_POST['countrecs'];
		$totalTime = $_POST['totalTime'];
		$totalMinutes = $_POST['totalMinutes'];
		$totalFixed = $_POST['totalFixed'];
		$totalAvgTime = $_POST['totalAvgTime'];
	}
	$countBoolean = (($countrecs / OOZ_SET_ITEMS_PAGE) > 1);
	unset($result,$row);
	if ($countBoolean) {
		$condition .= ' LIMIT ';
		if (isset($_POST['pageset']) && $_POST['pageset'] != 1) {
			$condition .= (($_POST['pageset'] - 1) * OOZ_SET_ITEMS_PAGE) . ",";
		}
		$condition .= OOZ_SET_ITEMS_PAGE;
	}

	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_time_table', $columnArray, $condition);
	if ($savedSQL != '' && $repNbr == 'rep99') {
		$sql = $savedSQL . $condition;
	}

	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	if ($repSumDet == "det") {
		if ($_SESSION['access_role_id'] <= 1 AND $repType != 'csv'){
			$tableHeadings[] = APP_TXT_85;
		}
		if ($ignoreHead != APP_TXT_8 && ! $itemOnlyInformation) {
			if ($repType == "csv") {
				$tableHeadings[] = APP_TXT_8;
			} else {
				$tableHeadings[] = Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'' . $repNbr . '_sort_by\');fieldArray[0].value=\'job_code\'; document.repform.submit()" class="URL"><strong>' . APP_TXT_8 . '</strong></a>', $_SESSION['access_role_id'], 4);
			}
		}

		if ($repNbr == 'rep99') {
			if ($repType == "csv") {
				$tableHeadings[] = APP_TXT_25;
			} else {
				$tableHeadings[] = Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'' . $repNbr . '_sort_by\');fieldArray[0].value=\'item_id\'; document.repform.submit()" class="URL"><strong>' . APP_TXT_25 . '</strong></a>', $_SESSION['access_role_id'], 4);
			}
		}

		if ($ignoreHead != APP_TXT_46) {
			if ($repType == "csv") {
				$tableHeadings[] = APP_TXT_46;
			} else {
				$tableHeadings[] = Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'' . $repNbr . '_sort_by\');fieldArray[0].value=\'user_id\'; document.repform.submit()" class="URL"><strong>' . APP_TXT_46 . '</strong></a>', $_SESSION['access_role_id'], 4);
			}
		}
		if ($ignoreHead != APP_TXT_7 && ! $itemOnlyInformation) {
			if ($repType == "csv") {
				$tableHeadings[] = APP_TXT_7;
			} else {
				$tableHeadings[] = Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'' . $repNbr . '_sort_by\');fieldArray[0].value=\'project\'; document.repform.submit()" class="URL"><strong>' . APP_TXT_7 . '</strong></a>', $_SESSION['access_role_id'], 4);
			}
		}
		if ($ignoreHead != APP_TXT_30 && ! $itemOnlyInformation) {
			if ($repType == "csv") {
				$tableHeadings[] = APP_TXT_30;
			} else {
				$tableHeadings[] = Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'' . $repNbr . '_sort_by\');fieldArray[0].value=\'cost_center\'; document.repform.submit()" class="URL"><strong>' . APP_TXT_30 . '</strong></a>', $_SESSION['access_role_id'], 4);
			}
		}
		if ($ignoreHead != APP_TXT_25  && $repNbr != 'rep6') {
			if ($repType == "csv") {
				$tableHeadings[] = APP_TXT_25;
			} else {
				$tableHeadings[] = Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'' . $repNbr . '_sort_by\');fieldArray[0].value=\'item_id\'; document.repform.submit()" class="URL"><strong>' . APP_TXT_25 . '</strong></a>', $_SESSION['access_role_id'], 4);
			}
		}
		if ($ignoreHead != APP_TXT_16 && $repNbr != 'rep5' && $repNbr != 'rep99') {
			if ($repType == "csv") {
				$tableHeadings[] = APP_TXT_16;
			} else {
				$tableHeadings[] = Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'' . $repNbr . '_sort_by\');fieldArray[0].value=\'start_date\'; document.repform.submit()" class="URL"><strong>' . APP_TXT_16 . '</strong></a>', $_SESSION['access_role_id'], 4);
			}
		}
		if ($ignoreHead != APP_TXT_48) {
			if ($repType == "csv") {
				$tableHeadings[] = APP_TXT_48;
			} else {
				$tableHeadings[] = Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'' . $repNbr . '_sort_by\');fieldArray[0].value=\'calc_add_date\'; document.repform.submit()" class="URL"><strong>' . APP_TXT_48 . '</strong></a>', $_SESSION['access_role_id'], 4);
			}
		}
		if ($repNbr == 'rep7' || $repNbr == 'rep99') {
			$tableHeadings[] = OOZ_TXT_84;
			$tableHeadings[] = OOZ_TXT_269;
		}

	} else {
		switch($repSort) {
			case 'job_code':
				$tableHeadings[] = APP_TXT_8;
				break;
			case 'user_id':
				$tableHeadings[] = APP_TXT_46;
				break;
			case 'project':
				$tableHeadings[] = APP_TXT_7;
				break;
			case 'cost_center':
				$tableHeadings[] = APP_TXT_30;
				break;
			case 'item_id':
				$tableHeadings[] = APP_TXT_25;
				break;
		}
	}
	if ($repNbr != 'rep5' && $repNbr != 'rep99') {
		$tableHeadings[] = APP_TXT_59;
		if ($repType == 'csv') {
			$tableHeadings[] = APP_TXT_59;
		}
	}
	if ($repSumDet == 'sum' && $repNbr != 'rep5' && $repNbr != 'rep99') {
		$tableHeadings[] = APP_TXT_61;
	}
	if ($repSumDet == 'sum' && ($repNbr == 'rep5' || $repNbr == 'rep99')) {
		$tableHeadings[] = APP_TXT_79;
	}
	if ($repType != 'csv') {
		$tableHeadings[] = APP_TXT_70;
	} else {
		if ($repNbr == 'rep99') {
			$tableHeadings[] = APP_TXT_82;
			$tableHeadings[] = APP_TXT_70;
			$tableHeadings[] = APP_TXT_82;
		} else {
			$tableHeadings[] = APP_TXT_70;
			$tableHeadings[] = APP_TXT_82;
		}
	}


	if (! $itemOnlyInformation) {
		$tableHeadings[] = APP_TXT_31;
	}

	if ($repSumDet == "det") {
		if (($repNbr > 'rep1' && $repNbr < 'rep5')) {
			if ($_SESSION['access_role_id'] <= 1){
				$tableRows = Render::tableData('', '', array('left','left','left','left','left','left','left','right','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
			}else{
				$tableRows = Render::tableData('', '', array('left','left','left','left','left','left','right','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
			}
		} else {
			if ($repNbr == 'rep5') {
				if ($_SESSION['access_role_id'] <= 1){
					$tableRows = Render::tableData('', '', array('left','left','left','left','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
				}else{
					$tableRows = Render::tableData('', '', array('left','left','left','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
				}
			} else {
				if ($repNbr == 'rep6') {
					if ($_SESSION['access_role_id'] <= 1){
						$tableRows = Render::tableData('', '', array('left', 'left','left','left','left','left','left','right','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
					}else{
						$tableRows = Render::tableData('', '', array('left','left','left','left','left','left','right','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
					}
				} else {
					if ($repNbr == 'rep7') {
						if ($_SESSION['access_role_id'] <= 1){
							$tableRows = Render::tableData('', '', array('left','left','left','left','left','left','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
						}else{
							$tableRows = Render::tableData('', '', array('left','left','left','left','left','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
						}
					} else {
						if ($repNbr == 'rep99') {
							if ($_SESSION['access_role_id'] <= 1){
								$tableRows = Render::tableData('', '', array('left','left','left','left','left','left','right'), 'tdcHeading', '', $tableHeadings, 'row');
							}else{
								$tableRows = Render::tableData('', '', array('left','left','left','left','left','right'), 'tdcHeading', '', $tableHeadings, 'row');
							}
						} else {
							if ($_SESSION['access_role_id'] <= 1){
								$tableRows = Render::tableData('', '', array('left','left','left','left','left','left','left','left','right','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
							}else{
								$tableRows = Render::tableData('', '', array('left','left','left','left','left','left','left','right','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
							}
						}
					}
				}
			}
		}
	} else {
		if ($repNbr != 'rep5' && $repNbr != 'rep99') {
			$tableRows = Render::tableData('', '', array('left','right','right','right','right'), 'tdcHeading', '', $tableHeadings, 'row');
		} else {
			$tableRows = Render::tableData('', '', array('left','left','right'), 'tdcHeading', '', $tableHeadings, 'row');
		}
	}

	$pageTime = 0;
	$pageAvgTime = 0;
	$pageMinutes = 0;
	$pageFixed = 0;
	$i = 0;
	$itemColumnArray = array('item_title','user_security','item_id');

	if (DB::numRows($result) > 0) {
		while ($row = DB::fetchArray($result)) {
			$fieldsArray = array();
			if ($_SESSION['access_role_id'] <= 1 AND $repType != 'csv' AND $repSumDet == 'det'){
				$fieldsArray[] = Render::checkBox('action_'.$row['time_entry_id'],'delete','','formField');
			}
			if ($repSumDet == "det") {
				if ($ignoreField != 'job_code' && ! $itemOnlyInformation) {
					$fieldsArray[] = $row['job_code'];
				}
				if ($repNbr == 'rep99') {
					$fieldsArray[] = $row['item_id'];
				}
				if ($ignoreField != 'user_id') {
					$fieldsArray[] = $usersArray[$row['user_id']];
				}
				if ($ignoreField != 'project' && ! $itemOnlyInformation) {
					$fieldsArray[] = $row['project'];
				}
				if ($ignoreField != 'cost_center' && ! $itemOnlyInformation) {
					$fieldsArray[] = $row['cost_center'];
				}
				if ($ignoreField != 'item_id' && $repNbr != 'rep6') {
					if ($repType == "csv") {
						$fieldsArray[] = $row['item_id'];
					} else {
						$fieldsArray[] = Render::url('index.php?'.$_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&item_id='.$row['item_id']. $logEntry . $attachments,$row['item_id'],'URL');
					}
				}
				if ($ignoreField != 'start_date' && $repNbr != 'rep5' && $repNbr != 'rep99') {
					$fieldsArray[] = date($dateFormat,$row['start_date']);
				}
				if ($ignoreField != 'add_date') {
					$fieldsArray[] = date($dateFormat,$row['calc_add_date']);
				}

				if ($repNbr == 'rep7' || $repNbr == 'rep99') {
					$condition = ($repNbr == 'rep7') ? 'WHERE item_id = ' . $repItemId : 'WHERE item_id = ' . $row['item_id'];
					$itemsql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'items', $itemColumnArray, $condition);
					$itemresult = DB::query($itemsql, DSN, OOZ_SET_SHOW_SQL);
					$itemrows = DB::fetchArray($itemresult);
					$fieldsArray[] = $itemrows['item_title'];
					$fieldsArray[] = $usersArray[$itemrows['user_security']];
				}

			} else {
				if (isset($usersArray)) {
					$fieldsArray[] = $usersArray[$row[$repSort]];
				} else {
					$fieldsArray[] = $row[$repSort];
				}
			}

			if ($repSumDet == 'sum' && ($repNbr == 'rep5' || $repNbr == 'rep99')) {
				$condition = 'WHERE item_id = ' . $row['item_id'];
				$itemsql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'items', array('item_title'), $condition);
				$itemresult = DB::query($itemsql, DSN, OOZ_SET_SHOW_SQL);
				$itemrows = DB::fetchArray($itemresult);
				if ($repType != 'csv') {
					$fieldsArray[] = Render::url('index.php?'.$_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&item_id='.$row['item_id']. $logEntry . $attachments,$itemrows['item_title'],'URL');
				} else {
					$fieldsArray[] = $itemrows['item_title'];
				}
			}

			if ($repNbr != 'rep5' && $repNbr != 'rep99' && $repType != 'csv') {
				$fieldsArray[] = timeInString($row['time_diff']);
			}
			if ($repType == 'csv') {
				$fieldsArray[] = $row['time_diff'];
			}
			if ($repSumDet == "sum" && $repNbr != 'rep5' && $repNbr != 'rep99') {
				$fieldsArray[] = timeInString($row['time_diff'] / $row['num_recs']);
				if ($repType == 'csv') {
					$fieldsArray[] = $row['time_diff'] / $row['num_recs'];
				}
			}

			if ($repType == 'csv') {
				$fieldsArray[] = ($row['minutes'] > 0) ? timeInString($row['minutes'] * 60) : OOZ_TXT_588;
				$fieldsArray[] = ($row['minutes'] > 0) ? $row['minutes'] * 60 : '';
			} else {
				$fieldsArray[] = ($row['minutes'] > 0) ? '<strong>'.timeInString($row['minutes'] * 60).'</strong>' : OOZ_TXT_588;
			}
			if (! $itemOnlyInformation) {
				$fieldsArray[] = $row['fixed_cost'];
			}
			$pageTime += $row['time_diff'];
			$pageMinutes += $row['minutes'];
			$pageFixed += $row['fixed_cost'];
			switch($repType) {
				case 'csv':
					$i = 0;
					foreach($fieldsArray as $key) {
						($i == 0) ? $data .= $key : $data .= ',' . $key;
						$i++;
					}
					$data .= "\n";
					break;
				case 'rep':
					$i++;
					if ($repNbr != 'rep5' && $repNbr != 'rep99') {
						$class = Render::setOddEvenClass($i, 'trc2', 'trc1');
					} else {
						$class = Render::setOddEvenClass($i, 'trc2', 'trc2');
					}
					if ($repSumDet == "det") {
						if (($repNbr > "rep1" && $repNbr < "rep5")) {
							if ($_SESSION['access_role_id'] <= 1){
								$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','left','right','right','right'), $class, '', $fieldsArray, 'row');
							}else{
								$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','right','right','right'), $class, '', $fieldsArray, 'row');
							}
						} else {
							if ($repNbr == "rep5") {
								if ($_SESSION['access_role_id'] <= 1){
									$tableRows .= Render::tableData('', '', array('left','left','left','left','right','right'), $class, '', $fieldsArray, 'row');
								}else{
									$tableRows .= Render::tableData('', '', array('left','left','left','right','right'), $class, '', $fieldsArray, 'row');
								}
							} else {
								if ($repNbr == 'rep6') {
									if ($_SESSION['access_role_id'] <= 1){
										$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','left','right','right','right'), $class, '', $fieldsArray, 'row');
									}else{
										$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','right','right','right'), $class, '', $fieldsArray, 'row');
									}
								} else {
									if ($repNbr == 'rep7') {
										if ($_SESSION['access_role_id'] <= 1){
											$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','right','right'), $class, '', $fieldsArray, 'row');
										}else{
											$tableRows .= Render::tableData('', '', array('left','left','left','left','left','right','right'), $class, '', $fieldsArray, 'row');
										}
									} else {
										if ($repNbr == 'rep99') {
											if ($_SESSION['access_role_id'] <= 1){
												$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','right'), $class, '', $fieldsArray, 'row');
											}else{
												$tableRows .= Render::tableData('', '', array('left','left','left','left','left','right'), $class, '', $fieldsArray, 'row');
											}
										} else {
											if ($_SESSION['access_role_id'] <= 1){
												$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','left','left','right','right','right'), $class, '', $fieldsArray, 'row');
											}else{
												$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','left','right','right','right'), $class, '', $fieldsArray, 'row');
											}
										}
									}
								}
							}
						}
					} else {
						if ($repNbr != 'rep5' && $repNbr != 'rep99') {
							$tableRows .= Render::tableData('', '', array('left','right','right','right','right'),$class , '', $fieldsArray, 'row');
						} else {
							$tableRows .= Render::tableData('', '', array('left','left','right'),$class , 'tdc1BottomBorder', $fieldsArray, 'row');
						}
					}
					break;
				case 'graph':
					break;
			}

			if ($repSumDet == 'sum' && ($repNbr == 'rep5' || $repNbr == 'rep99')) {
				$condition = 'WHERE item_id = ' . $row['item_id'];
				$itemsql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'items', array('*'), $condition);
				$itemresult = DB::query($itemsql, DSN, OOZ_SET_SHOW_SQL);
				$itemFields = DB::fetchArray($itemresult);
				$columnArray = array('user_name');
				$condition = "WHERE user_id = '".$itemFields['creator_security']."'";
				$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'users', $columnArray,$condition);
				$resultItems = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
				$rowItems = DB::fetchArray($resultItems);
				$fieldsDisplay = '<strong>' . OOZ_TXT_551 . ':</strong> ' . $rowItems['user_name'].'<br>';
				$columnArray = array('user_name');
				$condition = "WHERE user_id = '".$itemFields['user_security']."'";
				$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'users', $columnArray,$condition);
				$resultItems = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
				$rowItems = DB::fetchArray($resultItems);
				$fieldsDisplay .= '<strong>' . OOZ_TXT_269 . ':</strong> ' . $rowItems['user_name'].'<br>';
				$columnArray = array ('custom_field_id');
				$condition = "WHERE item_type_id = '" . $itemFields['item_type_id'] . "' ORDER BY custom_field_order ASC";
				$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'item_type_custom_fields', $columnArray, $condition);
				$customFieldResult = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
				$customFieldsDisplay = '';
				while ($customFields = DB::fetchArray($customFieldResult)) {
					$columnArray = array ('*');
					$condition = "WHERE custom_field_id = '" . $customFields['custom_field_id'] . "' AND field_type NOT LIKE 'worker%'";
					$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'custom_fields', $columnArray, $condition);
					$resultItems = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
					$rowItems = DB::fetchArray($resultItems);
					if (@$itemFields['custom_field_' . $rowItems['custom_field_id']] != ''){
						$customFieldsDisplay .= '<strong>' . $rowItems['custom_field_name'] . ':</strong> ' . str_replace("\n","<br>",@$itemFields['custom_field_' . $rowItems['custom_field_id']]).'<br>';
					}
				}
				$fieldsDisplay .= $customFieldsDisplay;
				$columnArray = array ('log_text');
				$condition = "WHERE item_id = '" . $row['item_id'] . "' ORDER BY create_date DESC LIMIT 1";
				$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'core_log', $columnArray, $condition);
				$resultItems = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
				$rowItems = DB::fetchArray($resultItems);
				if ($rowItems['log_text'] != ''){
					$fieldsDisplay .= '<strong>'.OOZ_TXT_586 . ':</strong> ' . str_replace("\n","<br>",$rowItems['log_text']);
				}
				$tableRows .= Render::tableData(3, '', array('left'),'trc1' , '', array($fieldsDisplay), 'row','style="display:none;"','show_details');
			}
		}
	}
	if ($repType == "csv") {
		$i = 0;
		foreach($tableHeadings as $key){
			($i == 0) ? $headings = $key : $headings .= ',' . $key;
			$i++;
		}
		$csv = $headings . "\n";
		$csv .= $data;
		$fileName = 'Time Report.csv';
		// Output to browser after emptying the output buffer
		ob_clean();
		header('Content-type: text/csv');
		header('Content-disposition:  attachment; filename="' . $fileName . '"');
		print $csv;
		exit;
	} else {
		$html = Render::startForm('index.php?controller=' . $_GET['controller'] . '&subcontroller=app_oneorzerotimemanager_manage&option=show_report','POST','repform');
		$deleteButton = Render::formButton('submit','manage',APP_TXT_85,'formButton','onClick="javascript:return confirm(\''.APP_TXT_86.'\')"');


		// Add last line which is the report totals
		if ($countrecs > 0) {
			if ($repSumDet == "det") {
				if (($repNbr > 'rep1'  && $repNbr < 'rep5')) {
					if ($_SESSION['access_role_id'] <= 1){
						$tableRows .= Render::tableData('10', '', 'left', '', '', array($deleteButton), 'row');
						$tableRows .= Render::tableData('10', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
						$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','left','right','right','right'), 'trc1', '', array('','','','','','','',timeInString($totalTime * 60),timeInString($totalMinutes *60),$totalFixed), 'row');
					}else{
						$tableRows .= Render::tableData('9', '', 'left', '', '', array('&nbsp;'), 'row');
						$tableRows .= Render::tableData('9', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
						$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','right','right','right'), 'trc1', '', array('','','','','','',timeInString($totalTime * 60),timeInString($totalMinutes *60),$totalFixed), 'row');
					}
				} else {
					if ($repNbr == "rep5") {
						if ($_SESSION['access_role_id'] <= 1){
							$tableRows .= Render::tableData('5', '', 'left', '', '', array($deleteButton), 'row');
							$tableRows .= Render::tableData('5', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
							$tableRows .= Render::tableData('', '', array('left','left','left','left','right'), 'trc1', '', array('','','','',timeInString($totalMinutes *60)), 'row');
						}else{
							$tableRows .= Render::tableData('4', '', 'left', '', '', array('&nbsp;'), 'row');
							$tableRows .= Render::tableData('4', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
							$tableRows .= Render::tableData('', '', array('left','left','left','right'), 'trc1', '', array('','','',timeInString($totalMinutes *60)), 'row');
						}
					} else {
						if ($repNbr == "rep6") {
							if ($_SESSION['access_role_id'] <= 1){
								$tableRows .= Render::tableData('10', '', 'left', '', '', array($deleteButton), 'row');
								$tableRows .= Render::tableData('10', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
								$tableRows .= Render::tableData('', '', array('left', 'left','left','left','left','left','left','right','right','right'), 'trc1', '', array('','','','','','','',timeInString($totalTime * 60),timeInString($totalMinutes *60),$totalFixed), 'row');
							}else{
								$tableRows .= Render::tableData('9', '', 'left', '', '', array('&nbsp;'), 'row');
								$tableRows .= Render::tableData('9', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
								$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','right','right','right'), 'trc1', '', array('','','','','','',timeInString($totalTime * 60),timeInString($totalMinutes *60),$totalFixed), 'row');
							}
						} else {
							if ($repNbr == 'rep7') {
								if ($_SESSION['access_role_id'] <= 1){
									$tableRows .= Render::tableData('8', '', 'left', '', '', array($deleteButton), 'row');
									$tableRows .= Render::tableData('8', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
									$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','right','right','right'), 'trc1', '', array('','','','','','',timeInString($totalTime * 60),timeInString($totalMinutes *60)), 'row');
								}else{
									$tableRows .= Render::tableData('7', '', 'left', '', '', array('&nbsp;'), 'row');
									$tableRows .= Render::tableData('7', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
									$tableRows .= Render::tableData('', '', array('left','left','left','left','left','right','right','right'), 'trc1', '', array('','','','','',timeInString($totalTime * 60),timeInString($totalMinutes *60)), 'row');
								}
							} else {
								if ($repNbr == 'rep99') {
									if ($_SESSION['access_role_id'] <= 1){
										$tableRows .= Render::tableData('7', '', 'left', '', '', array($deleteButton), 'row');
										$tableRows .= Render::tableData('7', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
										$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','right'), 'trc1', '', array('','','','','','',timeInString($totalMinutes * 60)), 'row');
									}else{
										$tableRows .= Render::tableData('6', '', 'left', '', '', array('&nbsp;'), 'row');
										$tableRows .= Render::tableData('6', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
										$tableRows .= Render::tableData('', '', array('left','left','left','left','left','right'), 'trc1', '', array('','','','','',timeInString($totalMinutes * 60)), 'row');
									}
								}  else {
									if ($_SESSION['access_role_id'] <= 1){
										$tableRows .= Render::tableData('11', '', 'left', '', '', array($deleteButton), 'row');
										$tableRows .= Render::tableData('11', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
										$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','left','left','right','right','right'), 'trc1', '', array('','','','','','','','',timeInString($totalTime * 60),timeInString($totalMinutes * 60),$totalFixed), 'row');
									}else{
										$tableRows .= Render::tableData('10', '', 'left', '', '', array('&nbsp;'), 'row');
										$tableRows .= Render::tableData('10', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
										$tableRows .= Render::tableData('', '', array('left','left','left','left','left','left','left','right','right','right'), 'trc1', '', array('','','','','','','',timeInString($totalTime * 60),timeInString($totalMinutes * 60),$totalFixed), 'row');
									}
								}
							}
						}
					}
				}
			} else {
				if ($itemOnlyInformation) {
					if ($repNbr == 'rep5'  || $repNbr == 'rep99') {
						$tableRows .= Render::tableData('3', '', 'left', '', '', array('&nbsp;'), 'row');
						$tableRows .= Render::tableData('3', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
						$tableRows .= Render::tableData('', '', array('left','left','right'), 'trc1', '', array('','',timeInString($totalMinutes * 60)), 'row');
					} else {
						$tableRows .= Render::tableData('4', '', 'left', '', '', array('&nbsp;'), 'row');
						$tableRows .= Render::tableData('4', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
						$tableRows .= Render::tableData('', '', array('left','right','right','right'), 'trc1', '', array('',timeInString($totalTime),timeInString($totalAvgTime),timeInString($totalMinutes * 60)), 'row');
					}
				} else {
					$tableRows .= Render::tableData('5', '', 'left', '', '', array('&nbsp;'), 'row');
					$tableRows .= Render::tableData('5', '', 'left', '', 'tdcHeadingBottomBorder', array(APP_TXT_45), 'row');
					$tableRows .= Render::tableData('', '', array('left','right','right','right','right'), 'trc1', '', array('',timeInString($totalTime),timeInString($totalAvgTime),timeInString($totalMinutes * 60),$totalFixed), 'row');
				}
			}
		}
		$html .= Render::table('100%', '0', '5', '0', '', $tableRows);
		if ($countBoolean) {
			$pageset = (isset($_POST['pageset'])) ? $_POST['pageset'] : 1;
			if ($pageset > 1) {
				$cellData[0] = Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value--; document.repform.submit()" class="URL"><strong><< Prev </strong></a>', $_SESSION['access_role_id'], 5);
			} else {
				$cellData[0] = "";
			}
			$istart = max(1,($pageset - 3));
			$iend = max(6,$pageset + 3);
			$iend = ($iend > (int)(($countrecs / OOZ_SET_ITEMS_PAGE) + 1)) ? (int)($countrecs / OOZ_SET_ITEMS_PAGE) + 1 : $iend;
			if ($istart > 1 && $iend - $istart < 6) {
				$istart = max($iend - 6,1);
			}
			for ($i = $istart; $i <= $iend; $i++) {
				if ($i == $pageset) {
					$cellData[0] .= "<strong> $i </strong>";
				} else {
					$cellData[0] .= Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value=' . $i . '; document.repform.submit()" class="URL"><strong> ' . $i . ' </strong></a>', $_SESSION['access_role_id'], 5);
				}
			}
			if ($pageset == $iend) {
				$cellData[0] .=  "";
			} else {
				$cellData[0] .= Render::secureReturn('<a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value++; document.repform.submit()" class="URL"><strong> Next >></strong></a>', $_SESSION['access_role_id'], 5);
			}
			$tableRows = Render::tableData('','','center','','',$cellData,'row');
			$html .= Render::table('100%','0','1','0','',$tableRows);
		}
		$html .= Render::hiddenField('countrecs',$countrecs);
		$html .= Render::hiddenField('pageset',@$pageset);
		$html .= Render::hiddenField('repNbr',$repNbr);
		$html .= Render::hiddenField($repNbr . '_type',$repType);
		$html .= Render::hiddenField($repNbr . '_sort_by',$repSort);
		$html .= Render::hiddenField($repNbr . '_sum_det',$repSumDet);
		$html .= Render::hiddenField($repNbr . '_from',$repFrom);
		$html .= Render::hiddenField($repNbr . '_to',$repTo);
		$html .= Render::hiddenField($repNbr . '_project',$repProject);
		$html .= Render::hiddenField($repNbr . '_cost_center',$repCostCenter);
		$html .= Render::hiddenField($repNbr . '_job_code',$repTimeCode);
		$html .= Render::hiddenField($repNbr . '_item_id',$repItemId);
		if ($repNbr == 'rep99') {
			$html .= Render::hiddenField($repNbr . '_saved',$_POST['rep99_saved']);
		}
		$html .= Render::hiddenField('sortDirection',$repSortDirection);
		$html .= Render::hiddenField('currentSort', $repCurrentSort);
		$html .= Render::hiddenField('totalTime',$totalTime);
		$html .= Render::hiddenField('totalAvgTime',$totalAvgTime);
		$html .= Render::hiddenField('totalMinutes',$totalMinutes);
		$html .= Render::hiddenField('totalFixed',$totalFixed);

		if ($repNbr != 'rep7'  && $repNbr != 'rep99') {
			switch ($repSort) {
				case 'job_code':
					$sortDesc = APP_TXT_8;
					break;
				case 'user_id':
					$sortDesc = APP_TXT_46;
					break;
				case 'project':
					$sortDesc = APP_TXT_7;
					break;
				case 'cost_center':
					$sortDesc = APP_TXT_30;
					break;
				case 'item_id':
					$sortDesc = APP_TXT_25;
					break;
				case 'calc_add_date':
					$sortDesc = APP_TXT_48;
					break;
				case 'start_date':
					$sortDesc = APP_TXT_16;
					break;
			}
		} else {
			$sortDesc = "";
		}
		if (($repNbr == 'rep5' || $repNbr == 'rep99') && $repSumDet == 'sum') {
			$show = Render::url('#', OOZ_TXT_373, 'URL', '', 'onclick="showRow(\'show_details\');return false;"');
			$hide = Render::url('#', OOZ_TXT_374.' '.OOZ_TXT_587, 'URL', '', 'onclick="hideRow(\'show_details\');return false;"');
			$sortDesc .=  ' ' . $show . '/' . $hide;
		}
		define('OOZ_BODY', $html);
		define('OOZ_HEADING', $repTitle . ' ' . $sortDesc);
		Render::renderPage('all_actions', OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
	}
}
function showReport($userID){

	if (isset($_GET['report_number'])){
		$repNbr = $_GET['report_number'];
	}else{
		$repNbr = array_search('Go',$_POST);
	}
	if ($repNbr == "" AND isset($_POST['repNbr'])) {
		$repNbr = $_POST['repNbr'];
	}
	if (isset($_POST[$repNbr . '_type'])){
		$repType = $_POST[$repNbr . '_type'];
	}else{
		$repType = $_GET[$repNbr . '_type'];
	}
	$repSort = (isset($_POST[$repNbr . '_sort_by'])) ? $_POST[$repNbr . '_sort_by'] : '';

	if (isset($_POST[$repNbr . '_sum_det'])){
		$repSumDet = $_POST[$repNbr . '_sum_det'];
	}else{
		$repSumDet = $_GET[$repNbr . '_sum_det'];
	}
	$repFrom = (isset($_POST[$repNbr . '_from'])) ? $_POST[$repNbr . '_from'] : '';
	$repTo = (isset($_POST[$repNbr . '_to'])) ? $_POST[$repNbr . '_to'] : '';
	$repProject = (isset($_POST[$repNbr . '_project'])) ? $_POST[$repNbr . '_project'] : '';
	$repCostCenter = (isset($_POST[$repNbr . '_cost_center'])) ? $_POST[$repNbr . '_cost_center'] : '';
	$repTimeCode = (isset($_POST[$repNbr . '_job_code'])) ? $_POST[$repNbr . '_job_code'] : '';
	if (isset($_POST[$repNbr . '_item_id'])){
		$repItemId = (isset($_POST[$repNbr . '_item_id'])) ? intval($_POST[$repNbr . '_item_id']) : '';
	}elseif(isset($_GET[$repNbr . '_item_id'])){
		$repItemId = intval($_GET[$repNbr . '_item_id']);
	}
	$tmpMonth = (OOZ_SET_DATE_FORMAT == "d-m-Y, h:i A") ? 1 : 0;
	$tmpDay = ($tmpMonth - 1 == 0) ? 0 : 1;

	if ($repFrom != '') {
		$tmpArray = explode('/', $repFrom);
		$repFromInt = mktime(0,0,0,$tmpArray[$tmpMonth],$tmpArray[$tmpDay],$tmpArray[2]);
	} else {
		$tmpArray = getdate();
		$repFromInt = mktime(0,0,0,$tmpArray['mon'],$tmpArray['mday'],$tmpArray['year']);
	}

	if ($repTo != '') {
		$tmpArray = explode('/', $repTo);
		$repToInt = mktime(0,0,0,$tmpArray[$tmpMonth],$tmpArray[$tmpDay],$tmpArray[2]) + 86399;
	} else {
		$tmpArray = getdate();
		$repToInt = mktime(0,0,0,$tmpArray['mon'],$tmpArray['mday'],$tmpArray['year']) + 86399;
	}
	// Set user condition
	$userCondition = ($_SESSION['access_role_id'] > 2) ? " user_id = '".$userID."' AND" : ""; //Administrators see all entries
	// Build the SQL statement

	switch($repNbr) {
		case 'rep1':
			$condition = "WHERE".$userCondition." start_date <= " . $repToInt . " AND start_date >= " . $repFromInt;
			$repTitle = ($repSumDet == "det") ? 'Detail' : 'Summary';
			$repTitle .= "&nbsp;" . APP_TXT_68 . '&nbsp;-&nbsp;' . APP_TXT_76 . '&nbsp;' . APP_TXT_66 . '&nbsp;' . $repFrom . '&nbsp;' . APP_TXT_67 .'&nbsp;' . $repTo . '&nbsp;' . APP_TXT_69;
			createHeads($repNbr,$condition,'','', $repTitle);
			break;
		case 'rep2':
			$condition = "WHERE".$userCondition." project = '" . $repProject . "'";
			$repTitle = ($repSumDet == "det") ? 'Detail' : 'Summary';
			$repTitle .= "&nbsp;" . APP_TXT_20 . '&nbsp;-&nbsp;' . $repProject . '&nbsp;' . APP_TXT_69;
			createHeads($repNbr,$condition,'project',APP_TXT_7,$repTitle);
			break;
		case 'rep3':
			$condition = "WHERE".$userCondition." job_code = '" . $repTimeCode . "'";
			$repTitle = ($repSumDet == "det") ? 'Detail' : 'Summary';
			$repTitle .= "&nbsp;" . APP_TXT_21 . '&nbsp;-&nbsp;' . $repTimeCode . '&nbsp;' . APP_TXT_69;
			createHeads($repNbr,$condition,'job_code',APP_TXT_8,$repTitle);
			break;
		case 'rep4':
			$condition = "WHERE".$userCondition." cost_center = '" . $repCostCenter . "'";
			$repTitle = ($repSumDet == "det") ? 'Detail' : 'Summary';
			$repTitle .= "&nbsp;" . APP_TXT_30 . '&nbsp;-&nbsp;' . $repCostCenter . '&nbsp;' . APP_TXT_69;
			createHeads($repNbr,$condition,'cost_center',APP_TXT_30, $repTitle);
			break;
		case 'rep5':
			$condition = "WHERE".$userCondition." start_date <= " . $repToInt . " AND start_date >= " . $repFromInt;
			$condition .= " AND item_id IS NOT NULL";
			$repTitle = ($repSumDet == "det") ? 'Detail' : 'Summary';
			$repTitle .= "&nbsp;" . APP_TXT_68 . '&nbsp;-&nbsp;' . APP_TXT_25 . '&nbsp;' . APP_TXT_66 . '&nbsp;' . $repFrom . '&nbsp;' . APP_TXT_67 .'&nbsp;' . $repTo . '&nbsp;' . APP_TXT_69;
			createHeads($repNbr,$condition,'','', $repTitle);
			break;
		case 'rep6':
			$condition = "WHERE".$userCondition." start_date <= " . $repToInt . " AND start_date >= " . $repFromInt;
			$condition .= " AND item_id IS NULL";
			$repTitle = ($repSumDet == "det") ? 'Detail' : 'Summary';
			$repTitle .= "&nbsp;" . APP_TXT_68 . '&nbsp;-&nbsp;' . APP_TXT_77 . '&nbsp;' . APP_TXT_66 . '&nbsp;' . $repFrom . '&nbsp;' . APP_TXT_67 .'&nbsp;' . $repTo . '&nbsp;' . APP_TXT_69;
			createHeads($repNbr,$condition,'','', $repTitle);
			break;
		case 'rep7':
			$condition = "WHERE".$userCondition." item_id = '" . $repItemId."'";
			$repTitle = ($repSumDet == "det") ? 'Detail' : 'Summary';
			$repTitle .= "&nbsp;" . APP_TXT_25 . '&nbsp;-&nbsp;' . $repItemId;
			createHeads($repNbr,$condition,'item_id',APP_TXT_25, $repTitle);
			break;
		case 'rep99':
			$sql = "SELECT saved_search_sql, search_name FROM " . OOZ_SET_TABLE_PREFIX . "saved_searches WHERE search_id = " . $_POST['rep99_saved'];
			$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
			if ($repSumDet == 'det') {
				$columnFields = 'time_entry_id, start_date, if(ammended_add_date = 0,add_date,ammended_add_date) - start_date AS time_diff,';
				$columnFields .= 'minutes, if(ammended_add_date = 0,add_date,ammended_add_date) AS calc_add_date, fixed_cost, ';
				$columnFields .= 'user_id,';
			} else {
				$columnFields = 'time_entry_id, SUM(if(ammended_add_date = 0,add_date,ammended_add_date) - start_date) AS time_diff, ';
				$columnFields .= 'SUM(minutes) AS minutes, SUM(fixed_cost) AS fixed_cost, count(1) AS num_recs, ';
			}
			$columnFields .= OOZ_SET_TABLE_PREFIX . 'items.item_id';
			if (DB::numRows($result) > 0) {
				while ($row = DB::fetchArray($result)) {
					$newSQL = str_replace('session_user',$_SESSION['access_user_id'],$row['saved_search_sql']);
					$savedTitle = $row['search_name'];
				}

				$intOrder = stripos($newSQL,"order by");
				if ($intOrder > 0) {
					$newSQL = substr($newSQL,0,$intOrder);
				}

				$intOrder = stripos($newSQL,"group by");
				if ($intOrder > 0) {
					$newSQL = substr($newSQL,0,$intOrder);
				}

				$intFrom = stripos($newSQL," FROM ");
				$intItemId = stripos($newSQL,"item_id");
				if ($intItemId == "") {
					$intItemId = 99;
				}
				if ($intItemId > $intFrom) {
					$newSQL = str_replace("SELECT ","SELECT " . $columnFields . ", ",$newSQL);
				} else {
					$newSQL = str_replace("item_id",$columnFields,substr($newSQL,0,$intFrom)) . substr($newSQL,$intFrom);
				}
				$intWhere = stripos($newSQL," WHERE ");
				$newSQL = str_replace(" item_id "," " . OOZ_SET_TABLE_PREFIX . "items.item_id ", $newSQL);
				$newSQL = str_replace(" item_id "," " . OOZ_SET_TABLE_PREFIX . "items.item_id ", $newSQL);
				if ($intWhere == "") {
					$intWhere = strlen($newSQL);
				}
				$newSQL = substr($newSQL,0,$intWhere) . ' JOIN ' . OOZ_SET_TABLE_PREFIX .
								'ooz_timemanager_time_table ON ' . OOZ_SET_TABLE_PREFIX . 'items.item_id = ' .
				OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_time_table.item_id ' . substr($newSQL,$intWhere);
			}
			$condition = "";
			$repTitle = ($repSumDet == "det") ? 'Detail' : 'Summary';
			$repTitle .= "&nbsp;" . APP_TXT_81 . '&nbsp;-&nbsp;' . $savedTitle;
			createHeads($repNbr,$condition,'item_id',APP_TXT_25, $repTitle,$newSQL);
			break;
	}
}
function showPendingEntries($userID){
	//Show URL list of time entries in the temp table for the user.
	//Each URL will open the time entry in the showAddTime method
	$tableRows = Render::tableData('', '', array('left','left','left','left','left','left','left','left'), '', 'tdcHeading', array('',APP_TXT_20,APP_TXT_21,APP_TXT_30,APP_TXT_22,APP_TXT_23,APP_TXT_25), 'row');
	$columnArray = array('*');
	$condition = "WHERE user_id = '".$_SESSION['access_user_id']."' ORDER BY start_date ASC, entry_identifier ASC";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'ooz_timemanager_temp_time_table', $columnArray, $condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	$currentDate = '';
	if (DB::numRows($result) > 0){
		$i = 0;
		while($row = DB::fetchArray($result)){
			$manageUrl = Render::url(OOZ_TIM_SUB_URL.'&option=show_add_update_time&time_entry_id='.$row['time_entry_id'],APP_TXT_19,'URL');
			$timeArray[] = $manageUrl;
			//			$timeArray[] = date(OOZ_SET_DATE_FORMAT, $row['start_date']);
			$timeArray[] = ($row['project'] == '') ? APP_TXT_27 : $row['project'];
			$timeArray[] = ($row['job_code'] == '') ? APP_TXT_27 : $row['job_code'];
			$timeArray[] = ($row['cost_center'] == '') ? APP_TXT_30 : $row['cost_center'];
			$timeArray[] = ($row['minutes'] == '') ? APP_TXT_27 : $row['minutes'];
			switch ($row['entry_identifier']){
				case '1':
					$status = APP_TXT_28;
					break;
				case '2':
					$status = APP_TXT_29;
					break;
				default:
					$status = APP_TXT_27;
			}
			$timeArray[] = $status;
			if ($row['item_id'] == ''){
				$timeArray[] = APP_TXT_27;
			}else{
				$logEntry = (OOZ_SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
				$attachments = (OOZ_SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
				$timeArray[] = Render::url('index.php?'.$_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&item_id='.$row['item_id']. $logEntry . $attachments,$row['item_id'],'URL');
			}
			$class = Render::setOddEvenClass($i, 'trc1', 'trc2');
			$tmpDate = date(substr(OOZ_SET_DATE_FORMAT,0,5),$row['start_date']);
			if ($currentDate != $tmpDate){
				$tableRows .= Render::tableData('8','','','','tdcHeadingBottomBorder',array(APP_TXT_16 . ' ' . $tmpDate . ' (' . date("D",$row['start_date']) . ')'),'row');
				$currentDate = $tmpDate;
			}
			$tableRows .= Render::tableData('', '', '', $class , '', $timeArray, 'row');
			unset($timeArray);
			$i++;
		}
	}else{
		$tableRows .= Render::tableData('2', '', '', '' , 'tdc1', array(APP_TXT_26), 'row');
	}
	$html = Render::table('100%', '0', '5', '0', 'tcBorder', $tableRows);
	define('OOZ_HEADING', APP_TXT_17);
	define('OOZ_BODY', $html);
	Render::renderPage('all_actions', OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);

}
function showTimeManagerSettings()
{
	$settings = @parse_ini_file(OOZ_SET_WRITEABLE_DIRECTORY . 'applications/oneorzerotimemanager/configuration/time_settings.php');
	// Setup form fields with values from settings file
	$columnArray = array ('custom_field_id', 'custom_field_name');
	$condition = "WHERE enabled = 'Yes' ORDER BY custom_field_name";
	$sql = DB::sqlSelect(OOZ_SET_TABLE_PREFIX . 'custom_fields', $columnArray,$condition);
	$result = DB::query($sql, DSN, OOZ_SET_SHOW_SQL);
	while ($row = DB::fetchArray($result)) {
		$idArray[] = $row['custom_field_id'];
		$nameArray[] = $row['custom_field_name'];
	}
	$fields[APP_TXT_71] = Render::menu('TIME_SET_PROJECT_MAP', $idArray, $nameArray, $settings['TIME_SET_PROJECT_MAP'], 'formField');
	$fields[APP_TXT_72] = Render::menu('TIME_SET_COST_CENTER_MAP', $idArray, $nameArray, $settings['TIME_SET_COST_CENTER_MAP'], 'formField');
	$fields[APP_TXT_73] = Render::menu('TIME_SET_JOB_CODE_MAP', $idArray, $nameArray, $settings['TIME_SET_JOB_CODE_MAP'], 'formField');
	$fields[APP_TXT_74] = Render::menu('TIME_SET_FIXED_COST_MAP', $idArray, $nameArray, $settings['TIME_SET_FIXED_COST_MAP'], 'formField');
	// Create two column table with heading
	$html = Render::startForm(OOZ_TIM_SUB_URL . '&option=update_time_settings', 'POST', 'update_time_settings');
	$tableRows = '';
	foreach ($fields as $name => $field) {
		// $a in this case represents the defined variables above
		$cellData = array ('<strong>' . $name . '</strong>', $field);
		$tableRows .= Render::tableData('', array('1%', '99%'), array('left', 'left'), '', array('tdform', 'tdformIndent'), $cellData, 'row');
	}
	$buttonArray[] = Render::formButton('submit','submit_button',OOZ_TXT_74,'formButton');
	$buttonArray[] = Render::formButton('reset','reset',OOZ_TXT_75,'formButton');
	$endFormButtons = Render::endFormButtons($buttonArray, '1');
	$tableRows .= Render::tableData('2', '', array('left'), '', 'tdc1', array($endFormButtons), 'row');
	$html .= Render::table('95%', '0', '0', '0', 'tableIndent', $tableRows);
	define('OOZ_HEADING', APP_TXT_75);
	define('OOZ_BODY', $html);
	Render::renderPage('all_actions', OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
}
function updateSettings()
{
	// remove so is not added to config file
	unset ($_POST['submit_button'], $_POST['reset']);
	// Write config file based on form created in showAIMSSettings
	$header = 'OneOrZero Time Manager Settings File - this file is generated by the OneOrZero Time Manager.  You can update manually if desired.';
	$configurationDirectory = OOZ_SET_WRITEABLE_DIRECTORY . 'applications/oneorzerotimemanager/configuration/';
	if (!is_dir($configurationDirectory)) {
		if (!mkdir($configurationDirectory, 0755, 1)) {
			echo 'ERROR: Could not create directory: ' . $configurationDirectory;
		}
	}
	if (File::writeFileFromArray($configurationDirectory . 'time_settings.php', $header, $_POST)) {
		$message = OOZ_TXT_201;
	} else {
		$message = OOZ_TXT_203;
	}
	$html = Render::showResponse($message, Render::url(OOZ_TIM_BASE_URL . '&option=time_settings', OOZ_TXT_202, 'URL'));
	define('OOZ_BODY', $html);
	define('OOZ_HEADING', OOZ_TXT_139);
	Render::renderPage('all_actions', OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);
}


/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
	case 'show_add_update_time' :
		Render::secureEnd($_SESSION['access_role_id'], 5);
		if (isset($_POST['time_entry_id'])){
			$timeEntryID = $_POST['time_entry_id'];
		}elseif(isset($_GET['time_entry_id'])){
			$timeEntryID = $_GET['time_entry_id'];
		}
		showAddUpdateTime(@$timeEntryID);
		break;
	case 'add_update_time' :
		Render::secureEnd($_SESSION['access_role_id'], 5);
		addUpdateTime();
		break;
	case 'show_pending_time' :
		Render::secureEnd($_SESSION['access_role_id'], 5);
		showPendingEntries($_SESSION['access_user_id']);
		break;
	case 'show_time_reports' :
		Render::secureEnd($_SESSION['access_role_id'], 5);
		showTimeReports($_SESSION['access_user_id']);
		break;
	case 'show_report'	:
		Render::secureEnd($_SESSION['access_role_id'], 5);
		showReport($_SESSION['access_user_id']);
		break;
	case 'time_settings'	:
		Render::secureEnd($_SESSION['access_role_id'], 1);
		showTimeManagerSettings();
		break;
	case 'update_time_settings' :
		Render::secureEnd($_SESSION['access_role_id'], 1);
		updateSettings();
		break;
	default;
	if (OOZ_TIM_CONFIGURED == true){
		showAddUpdateTime();
	}else{
		Render::secureEnd($_SESSION['access_role_id'], 1);
		showTimeManagerSettings();
	}
}
?>