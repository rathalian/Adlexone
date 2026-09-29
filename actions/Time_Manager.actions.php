<?php
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
/**
 * Required Libraries
 */
use Adlexone\support\Database;
use Adlexone\support\RenderViews;
use Adlexone\support\Actions;

/**
 * action package specific constants
 */
if (!defined('NOT_BASE_URL')) {
	define('NOT_BASE_URL', 'index.php?controller=administration_actions');
}
function showSetupAddTime($actionID = '')
{
	// Get default field values if we use this form for updating the action
	if ($actionID != '') {
		// Get action information from database
		$columnArray = array('*');
		$condition = "WHERE action_id = '" . $actionID . "'";
		$fieldValues = Database::first('action_definitions', $columnArray, $condition);
		$action = NOT_BASE_URL . '&option=update_action&action_package=Time_Manager&descriptor_name=AddTime&action_id=' . $actionID;
	} else {
		$action = NOT_BASE_URL . '&option=add_action&action_package=Time_Manager&descriptor_name=AddTime';
	}
	$actionField = Actions::startNewAction($actionID, @$fieldValues,true,true);
	$columnArray = array('custom_field_id','custom_field_name');
	$condition = "WHERE field_type LIKE 'worker%'";
	foreach (Database::select('custom_fields', $columnArray,$condition) as $row) {
		$idArray[] = $row['custom_field_id'];
		$nameArray[] = $row['custom_field_name'];
	}
	$actionField[ACT_PAK_65] = RenderViews::buildSelectDropdown('custom_field_id', $idArray ?? [], $nameArray ?? [], @$fieldValues['action_parameters']).' * '.ACT_PAK_66;
	$actionField[''] = RenderViews::buildHiddenInput('action_id', @$fieldValues['action_id']);
	$jsFieldNameArray = "['action_name']";
	$jsTestTypeArray = "['']";
	$jsErrorMsgArray = "['']";
	$jsRequiredMsgArray = "['".ACT_PAK_36."']";
	$jsRequiredArray = "[true]";
	$javascript = "onClick=\"javascript:return fieldCheck('".TXT_468."',".$jsTestTypeArray.",".$jsFieldNameArray.",".$jsErrorMsgArray.",".$jsRequiredMsgArray.",".$jsRequiredArray.");\"";
	define('BODY_CONTENT', RenderViews::buildForm(
		ACT_PAK_63,
		$action,
		$actionField,
		[
			RenderViews::buildFormButton('submit','submit_button',TXT_74,$javascript),
			RenderViews::buildFormButton('reset','reset',TXT_75),
		]
	));
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}
function addUpdateAddTime($actionID = '', $add = false)
{
	// Get default field values if we use this form for updating the action
	if ($add == true) {
		// Check for duplicate name
		$columnArray = array('action_name');
		$condition = "WHERE action_name = '" . $_POST['action_name'] . "'";
		$result = Database::select('action_definitions', $columnArray, $condition);
		if (count($result) > 0) {
			$html = ACT_PAK_15;
			$html = RenderViews::showResponse(ACT_PAK_15,RenderViews::url('javascript: history.go(-1)', ACT_PAK_42, 'URL'));
			define('HEADING', TXT_352);
			define('BODY_CONTENT', $html);
		} else {
			// Add action to database
			unset($columnArray);
			$columnArray['action_id'] = Database::newID('action_definitions', 'action_id');
			$columnArray['action_name'] = $_POST['action_name'];
			$columnArray['enabled'] = $_POST['enabled'];
			$columnArray['item_type_id'] = $_POST['item_type_id'] ;
			$columnArray['action_type']  =  'main_page_content';
			$columnArray['action_parameters'] = $_POST['custom_field_id'];
			$columnArray['package_file'] = 'Time_Manager.actions.php';
			$columnArray['package_function'] = 'AddTime';
			Database::insert('action_definitions', $columnArray);
			$html = RenderViews::showResponse($_POST['action_name'] . ' ' . TXT_301,RenderViews::url(NOT_BASE_URL . '&option=&option=show_defined_actions', ACT_PAK_21, 'URL'));
			define('HEADING', TXT_352);
			define('BODY_CONTENT', $html);
		}
	} else {
		// Update action
		unset($columnArray);
		$columnArray['action_name'] = $_POST['action_name'];
		$columnArray['enabled'] = $_POST['enabled'];
		$columnArray['item_type_id'] = $_POST['item_type_id'] ;
		$columnArray['action_type']  =  'main_page_content';
		$columnArray['action_parameters'] = $_POST['custom_field_id'];
		$columnArray['package_file'] = 'Time_Manager.actions.php';
		$columnArray['package_function'] = 'AddTime';
		$condition = "WHERE action_id ='$actionID'";
		Database::update('action_definitions', $columnArray, $condition);
		$html = RenderViews::showResponse($_POST['action_name'] . ' ' . TXT_164, RenderViews::url(NOT_BASE_URL . '&option=&option=show_defined_actions', ACT_PAK_21, 'URL'));
		define('HEADING', TXT_352);
		define('BODY_CONTENT', $html);
	}
	RenderViews::renderPage('main_page_content',  SET_THEME);
}
function executeAddTime($itemID, $dataArray, $preCondition, $triggerCondition, $actionParameters, $actionData, $actionType = '', $requestingAction = '')
{
	if (!empty($dataArray['worker_field_'.$actionParameters])){
	$columnArray['time_entry_id'] = Database::newID('timemanager_time_table', 'time_entry_id');
	$time = time();
	$columnArray['start_date'] = $time;
	$columnArray['add_date'] = $time;
	$columnArray['ammended_add_date'] = '0';
	$columnArray['user_id'] =  $_SESSION['access_user_id'];
	$columnArray['item_id'] = $itemID;
	$columnArray['entry_identifier'] = '3';
	$condition = "WHERE item_id = '".$itemID."'";
	$columnArray['sequence'] = Database::newID('timemanager_time_table', 'sequence',$condition);
	$columnArray['minutes'] = intval($dataArray['worker_field_'.$actionParameters]);
	Database::insert('timemanager_time_table', $columnArray);
	}
	return true;
}
/**
 * action Descriptors provide the base action name and associated information to the
 * administration_actions controller file so it can create a package actions list
 */
$actionName['AddTime'] = ACT_PAK_63;
$actionDescription['AddTime'] = ACT_PAK_64;

?>