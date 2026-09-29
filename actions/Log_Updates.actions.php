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
function showSetupUpdateLog($actionID = '')
{
	// Get default field values if we use this form for updating the action
	if ($actionID != '') {
		// Get action information from database
		$columnArray = array('*');
		$condition = "WHERE action_id = '" . $actionID . "'";
		$sql = Database::sqlSelect('action_definitions', $columnArray, $condition);
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		$fieldValues = Database::fetchArray($result);
		$action = NOT_BASE_URL . '&option=update_action&action_package=Log_Updates&descriptor_name=UpdateLog&action_id=' . $actionID;
	} else {
		$action = NOT_BASE_URL . '&option=add_action&action_package=Log_Updates&descriptor_name=UpdateLog';
	}
	$actionField = Actions::startNewAction($actionID, @$fieldValues,false,false);
	$roleIDs = array(0, 1, 2, 3, 4, 5);
	$roleNames = array(TXT_190, TXT_191, TXT_192, TXT_193, TXT_194, TXT_303);
	$actionField[ACT_PAK_51] = RenderViews::buildSelectDropdown('role', $roleIDs, $roleNames, @$fieldValues['action_parameters']).' * '.ACT_PAK_52;
	$fieldValueArray = explode('}-{',@$fieldValues['action_data']);
	if ($fieldValueArray[0] == ''){
		$fieldValueArray[0] = substr((uniqid(time())), 3, 9);
	}
	$actionField[ACT_PAK_53] = RenderViews::buildTextInput('item_identifier', $fieldValueArray[0]).' * '.ACT_PAK_54;
	$actionField[ACT_PAK_55] = RenderViews::buildTextArea('log_text', @$fieldValueArray[1], '15');
	$actionField[''] = RenderViews::buildHiddenInput('action_id', @$fieldValues['action_id']);
	$excludeArray = array('create_date','core_log_updated','item_type_id','creator_security','user_security','group_security');
	$dynamicValues = 'LOG_ENTRY, ITEM_CREATOR, ITEM_OWNER';
	$sql = "SHOW COLUMNS FROM items";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	while ($row = Database::fetchArray($result)){
		if (!in_array($row[0],$excludeArray)){
			@$dynamicValues .= ', '.strtoupper($row[0]);
		}
	}
	$actionField[ACT_PAK_60] = $dynamicValues;
	$jsFieldNameArray = "['action_name','item_identifier','log_text']";
	$jsTestTypeArray = "['','','']";
	$jsErrorMsgArray = "['','','']";
	$jsRequiredMsgArray = "['".ACT_PAK_36."','".ACT_PAK_56."','".ACT_PAK_57."']";
	$jsRequiredArray = "[true,true,true]";
	$javascript = "onClick=\"javascript:return fieldCheck('".TXT_468."',".$jsTestTypeArray.",".$jsFieldNameArray.",".$jsErrorMsgArray.",".$jsRequiredMsgArray.",".$jsRequiredArray.");\"";
	define('BODY_CONTENT', RenderViews::buildForm(
		ACT_PAK_49,
		$action,
		$actionField,
		[
			RenderViews::buildFormButton('submit','submit_button',TXT_74,$javascript),
			RenderViews::buildFormButton('reset','reset',TXT_75),
		]
	));
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}
/**
 * addUpdateUpdateLog()
 *
 * Add or updates UpdateLog based actions
 *
 * @param string $actionID
 * @param mixed $add
 * @return
 */
function addUpdateUpdateLog($actionID = '', $add = false)
{
	// Get default field values if we use this form for updating the action
	if ($add == true) {
		// Check for duplicate name
		$columnArray = array('action_name');
		$condition = "WHERE action_name = '" . $_POST['action_name'] . "'";
		$sql = Database::sqlSelect('action_definitions', $columnArray, $condition);
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		if (Database::numRows($result) > 0) {
			$html = ACT_PAK_15;
			$html = RenderViews::showResponse(ACT_PAK_15,RenderViews::url('javascript: history.go(-1)', ACT_PAK_42, 'URL'));
			define('HEADING', TXT_352);
			define('BODY_CONTENT', $html);
		} else {
			// Check for duplicate name
			$columnArray = array('item_identifier');
			$condition = "WHERE item_identifier = '" . $_POST['item_identifier'] . "'";
			$sql = Database::sqlSelect('core_log', $columnArray, $condition);
			$result = Database::query($sql, DSN, SET_SHOW_SQL);
			if (Database::numRows($result) > 0) {
				$html = ACT_PAK_15;
				$html = RenderViews::showResponse(ACT_PAK_58,RenderViews::url('javascript: history.go(-1)', ACT_PAK_42, 'URL'));
				define('HEADING', TXT_352);
				define('BODY_CONTENT', $html);
			}else{
				// Add action to database
				unset($columnArray);
				$columnArray['action_id'] = Database::newID('action_definitions', 'action_id');
				$columnArray['action_name'] = $_POST['action_name'];
				// Reverse the stripScripts function by decoding html entities as emails will appear scrambled
				$columnArray['action_data'] = $_POST['item_identifier'] . '}-{' . html_entity_decode($_POST['log_text'], ENT_COMPAT, 'UTF-8');
				$columnArray['action_parameters'] = $_POST['role'];
				$columnArray['package_file'] = 'Log_Updates.actions.php';
				$columnArray['package_function'] = 'UpdateLog';
				$columnArray['enabled'] = 'Yes';
				$sql = Database::sqlInsert('action_definitions', $columnArray);
				Database::query($sql, DSN, SET_SHOW_SQL);
				$html = RenderViews::showResponse($_POST['action_name'] . ' ' . TXT_301,RenderViews::url(NOT_BASE_URL . '&option=&option=show_defined_actions', ACT_PAK_21, 'URL'));
				define('HEADING', TXT_352);
				define('BODY_CONTENT', $html);
			}
		}
	} else {
		// Update action
		unset($columnArray);
		$columnArray['action_name'] = $_POST['action_name'];
		// Reverse the stripScripts function by decoding html entities as emails will appear scrambled
		$columnArray['action_data'] = $_POST['item_identifier'] . '}-{' . html_entity_decode($_POST['log_text'], ENT_COMPAT, 'UTF-8');
		$columnArray['action_parameters'] = $_POST['role'];
		$columnArray['package_file'] = 'Log_Updates.actions.php';
		$columnArray['package_function'] = 'UpdateLog';
		$columnArray['enabled'] = 'Yes';
		$condition = "WHERE action_id ='$actionID'";
		$sql = Database::sqlUpdate('action_definitions', $columnArray, $condition);
		Database::query($sql, DSN, SET_SHOW_SQL);
		$html = RenderViews::showResponse($_POST['action_name'] . ' ' . TXT_164, RenderViews::url(NOT_BASE_URL . '&option=&option=show_defined_actions', ACT_PAK_21, 'URL'));
		define('HEADING', TXT_352);
		define('BODY_CONTENT', $html);
	}
	RenderViews::renderPage('main_page_content',  SET_THEME);
}
/**
 * Updates log
 *
 * @param integer $ Item Id
 * @param array $preCondition Pre action condition array
 * @param array $triggerCondition Pre action condition array
 * @param string $actionParameters action action parameters
 * @param array $actionData action data
 * @return Boolean Return boolean vale to indicate action execution success
 */
function executeUpdateLog($itemID, $dataArray, $preCondition, $triggerCondition, $actionParameters, $actionData, $actionType = '', $requestingAction = '')
{
	// Get new log id and sequence
	$LogID = Database::newID('core_log', 'id');
	$condition = "WHERE item_id='$itemID'";
	$SequenceID = Database::newID('core_log', 'log_item_sequence', $condition);
	// Add log entry
	$columnArray['id'] = $LogID;
	$columnArray['item_id'] = $itemID;
	$columnArray['create_date'] = time();
	$logEntryArray = explode('}-{',$actionData);
	$columnArray['item_identifier'] = $logEntryArray[0];
	$columnArray['log_item_sequence'] = $SequenceID;
	$logEntry = $logEntryArray[1];
	// Add dynamic variables
	foreach($dataArray as $key => $value) {
		if (!is_int($key)){
			if (preg_match("/\b".strtoupper($key)."\b/",$logEntry)) {
				$logEntry = preg_replace("/\b".strtoupper($key)."\b/", $value, $logEntry);
			}
		}
	}
	//Match creator
	$sql = "SELECT user_name from "."users WHERE user_id = '".$dataArray['creator_security']."'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$row = Database::fetchArray($result);
	if (preg_match("/\bITEM_CREATOR\b/",$logEntry)) {
		$logEntry = preg_replace("/\bITEM_CREATOR\b/", $row['user_name'], $logEntry);
	}
	//Match owner
	$sql = "SELECT user_name from "."users WHERE user_id = '".$dataArray['user_security']."'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$row = Database::fetchArray($result);
	if (preg_match("/\bITEM_OWNER\b/",$logEntry)) {
		$logEntry = preg_replace("/\bITEM_OWNER\b/", $row['user_name'], $logEntry);
	}
	$columnArray['log_text'] = addslashes($logEntry);
	$columnArray['security_id'] = $_SESSION['access_user_id'];
	$columnArray['role_id'] = $actionParameters;
	// Insert into log table
	$sql = Database::sqlInsert('core_log', $columnArray);
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	// Update entry in item table
	$condition = "WHERE item_id = '$itemID'";
	$itemColumnArray['core_log_updated'] = $columnArray['create_date'];
	$sql = Database::sqlUpdate('items', $itemColumnArray, $condition);
	$result = Database::query($sql, DSN, SET_SHOW_SQL);

	return true;
}
/**
 * action Descriptors provide the base action name and associated information to the
 * administration_actions controller file so it can create a package actions list
 */
$actionName['UpdateLog'] = ACT_PAK_49;
$actionDescription['UpdateLog'] = ACT_PAK_50;