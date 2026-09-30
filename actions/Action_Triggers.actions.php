<?php

use Adlexone\support\Database;
use Adlexone\support\RenderViews;
use Adlexone\support\Actions;

/**
 * action package specific constants
 */
if (!defined('NOT_BASE_URL')) {
	define('NOT_BASE_URL', 'index.php?manage=workflow');
}
/**
 * * Shows the setup page for the trigger action for custom fields
 *
 * @param string $actionID
 * @return
 */
function showSetupTriggerActionCustomField($actionID = '')
{
	// Get default field values if we use this form for updating the action
	if ($actionID != '') {
		// Get action information from database
		$columnArray = array('*');
		$condition = "WHERE action_id = '" . $actionID . "'";
		$fieldValues = Database::first('action_definitions', $columnArray, $condition);
		$action = NOT_BASE_URL . '&option=update_action&action_package=Action_Triggers&descriptor_name=TriggerActionCustomField&action_id=' . $actionID;
	} else {
		$action = NOT_BASE_URL . '&option=add_action&action_package=Action_Triggers&descriptor_name=TriggerActionCustomField';
	}
	// Get custom field buildSelectDropdown
	$columnArray = array('custom_field_id', 'custom_field_name', 'field_type');
	$result = Database::select('custom_fields', $columnArray);
	$i = 0;
	foreach ($result as $row) {
		if ($row['field_type'] == 'workerField'){
			$customFieldIDArray[$i] = 'worker_field_' . $row['custom_field_id'];
		}elseif ($row['field_type'] == 'workerFieldMenu'){
			$customFieldIDArray[$i] = 'worker_field_menu_' . $row['custom_field_id'];
		}else{
			$customFieldIDArray[$i] = 'custom_field_' . $row['custom_field_id'];
		}
		$customFieldNameArray[$i] = $row['custom_field_name'];
		$i++;
	} //
	// Create the setup form
	$actionField = Actions::startNewAction($actionID, @$fieldValues,true,true);
	// Get list of existing actions defined
	$columnArray = array('action_id', 'action_name');
	$condition = "WHERE package_function <> 'TriggerActionCustomField' AND package_function <> 'TriggerActionSystemField'";//exclude these action types as they are this action package
	foreach (Database::select('action_definitions', $columnArray,$condition) as $row) {
		$actionIDArray[] = $row['action_id'];
		$actionNameArray[] = $row['action_name'];
	}
	$actionField[ACT_PAK_14] = RenderViews::buildSelectDropdown('action_data', $actionIDArray, $actionNameArray, @$fieldValues['action_data']);
	$actionField[ACT_PAK_19] = RenderViews::buildSelectDropdown('action_type', array('create_item_trigger_met', 'create_item_every_item', 'update_item_log_entry', 'update_item_trigger_met', 'update_item_any_trigger', 'update_item_all_met', 'item_attachment'), array(ACT_PAK_23, ACT_PAK_24, ACT_PAK_25, ACT_PAK_26, ACT_PAK_27, ACT_PAK_28, TXT_671), @$fieldValues['action_type']);
	$conditionArrayPre = explode('}-{', @$fieldValues['action_condition_pre']);
	$conditionArrayPost = explode('}-{', @$fieldValues['action_condition_post']);
	$roleIDs = array(2, 3, 4, 5);
	$roleNames = array(TXT_192, TXT_193, TXT_194, TXT_303);
	$actionField[ACT_PAK_72] = RenderViews::buildSelectDropdown('log_role', $roleIDs, $roleNames,@$conditionArrayPost[3]);
	$operatorValues = array('=', '<>');
	$operatorDisplayValues = array(TXT_81, TXT_318);
	$actionField[ACT_PAK_2] = RenderViews::buildSelectDropdown('custom_field_pre', $customFieldIDArray, $customFieldNameArray, @$conditionArrayPre[0])
		. ' ' . RenderViews::buildSelectDropdown('operator_pre', $operatorValues, $operatorDisplayValues, @$conditionArrayPre[1])
		. ' ' . RenderViews::buildTextInput('condition_pre', @$conditionArrayPre[2]);
	$operatorValues = array('==', '!=');
	$actionField[ACT_PAK_10] = RenderViews::buildSelectDropdown('custom_field_post', $customFieldIDArray, $customFieldNameArray, @$conditionArrayPost[0])
		. ' ' . RenderViews::buildSelectDropdown('operator_post', $operatorValues, $operatorDisplayValues, @$conditionArrayPost[1])
		. ' ' . RenderViews::buildTextInput('condition_post', @$conditionArrayPost[2]);
	$actionField[''] = RenderViews::buildHiddenInput('action_id', @$fieldValues['action_id']);
	$jsFieldNameArray = "['action_name']";
	$jsTestTypeArray = "['']";
	$jsErrorMsgArray = "['']";
	$jsRequiredMsgArray = "['".ACT_PAK_36."']";
	$jsRequiredArray = "[true]";
	$javascript = "onClick=\"javascript:return fieldCheck('".TXT_468."',".$jsTestTypeArray.",".$jsFieldNameArray.",".$jsErrorMsgArray.",".$jsRequiredMsgArray.",".$jsRequiredArray.");\"";
	define('BODY_CONTENT', RenderViews::buildForm(
		ACT_PAK_13,
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
 * Add or update triggered action definition
 *
 * @param string $actionID
 * @param mixed $add
 * @return
 */
function addUpdateTriggerActionCustomField($actionID = '', $add = false)
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
			$columnArray['action_name'] = $_POST['action_name'];
			$columnArray['enabled'] = $_POST['enabled'];
			$columnArray['item_type_id'] = $_POST['item_type_id'] ;
			$columnArray['action_condition_pre'] = $_POST['custom_field_pre'] . '}-{' . html_entity_decode($_POST['operator_pre'], ENT_COMPAT, 'UTF-8') . '}-{' . $_POST['condition_pre'];
			$columnArray['action_condition_post'] = $_POST['custom_field_post'] . '}-{' . html_entity_decode($_POST['operator_post'], ENT_COMPAT, 'UTF-8') . '}-{' . $_POST['condition_post'].'}-{' . $_POST['log_role'];
			$columnArray['action_type'] = $_POST['action_type'];
			$columnArray['action_data'] = $_POST['action_data'];
			$columnArray['package_file'] = 'Action_Triggers.actions.php';
			$columnArray['package_function'] = 'TriggerActionCustomField';
			Database::insert('action_definitions', $columnArray);
			$html = RenderViews::showResponse($_POST['action_name'] . ' ' . TXT_301,RenderViews::url(NOT_BASE_URL . '&option=&option=show_defined_actions', ACT_PAK_21, 'URL'));
			define('HEADING', TXT_352);
			define('BODY_CONTENT', $html);
		}
	} else {
		$columnArray['action_name'] = $_POST['action_name'];
		$columnArray['enabled'] = $_POST['enabled'];
		$columnArray['item_type_id'] = $_POST['item_type_id'];
		$columnArray['action_condition_pre'] = $_POST['custom_field_pre'] . '}-{' . html_entity_decode($_POST['operator_pre'], ENT_COMPAT, 'UTF-8') . '}-{' . $_POST['condition_pre'];
		$columnArray['action_condition_post'] = $_POST['custom_field_post'] . '}-{' . html_entity_decode($_POST['operator_post'], ENT_COMPAT, 'UTF-8') . '}-{' . $_POST['condition_post'].'}-{' . $_POST['log_role'];
		$columnArray['action_type'] = $_POST['action_type'];
		$columnArray['action_data'] = $_POST['action_data'];
		$columnArray['package_file'] = 'Action_Triggers.actions.php';
		$columnArray['package_function'] = 'TriggerActionCustomField';
		$condition = "WHERE action_id ='$actionID'";
		Database::update('action_definitions', $columnArray, $condition);
		$html = RenderViews::showResponse($_POST['action_name'] . ' ' . TXT_164, RenderViews::url(NOT_BASE_URL . '&option=&option=show_defined_actions', ACT_PAK_21, 'URL'));
		define('HEADING', TXT_352);
		define('BODY_CONTENT', $html);
	}
	RenderViews::renderPage('main_page_content',  SET_THEME);
}
/**
 * Executes an action based on the saved trigger action id
 *
 * @param integer $ Item Id
 * @param array $preCondition Pre action condition array
 * @param array $triggerCondition Pre action condition array
 * @param string $actionParameters action action parameters
 * @param array $actionData action data
 * @param string $requestingAction Requesting Action allows the input of a specific action point for triggers etc (i.e. update_item, create_item)
 * @return Boolean Return boolean vale to indicate action execution success
 */
function executeTriggerActionCustomField($itemID, $dataArray, $preCondition, $triggerCondition, $actionParameters, $actionData, $actionType,$requestingAction)
{
	// Set the conditionTrue variable to false and get our arguments ot prove otherwise
	$conditionTrue = false;
	// Get our pre and post condition arrays
	$preConditionArray = explode ('}-{', $preCondition);
	$triggerConditionArray = explode ('}-{', $triggerCondition);
	// Start working with the logic
	switch ($requestingAction) {
		case 'create_item':
			// Options called from the create item function
			switch ($actionType) {
				case 'create_item_every_item':
					// Will always return true
					$conditionTrue = true;
					break;
				case 'create_item_trigger_met':
					// We evaluate using == and != and require the evaluation result
					$conditionTrue = ($triggerConditionArray[1] == '==') ? @$dataArray[$triggerConditionArray[0]] == $triggerConditionArray[2] : @$dataArray[$triggerConditionArray[0]] != $triggerConditionArray[2];
					break;
			}
			break;
		case 'update_item':
			// Options called from the update item function
			switch ($actionType) {
				case 'update_item_trigger_met':
					// We evaluate trigger condition using == and != and require the evaluation result
					$conditionTrue = ($triggerConditionArray[1] == '==') ? @$dataArray[$triggerConditionArray[0]] == $triggerConditionArray[2] : @$dataArray[$triggerConditionArray[0]] != $triggerConditionArray[2];
					break;
				case 'update_item_all_met':
					// Get existing item data and evaluate pre condition via EAV-aware SQL
					$preField = (string) ($preConditionArray[0] ?? '');
					$preOp = (string) ($preConditionArray[1] ?? '=');
					$preVal = (string) ($preConditionArray[2] ?? '');
					if (preg_match('/^custom_field_(\d+)$/', $preField, $m)) {
						$preSql = 'SELECT item_id FROM items WHERE item_id = ' . (int) $itemID
							. ' AND ' . \Adlexone\Data\ItemFields::matchSql((int) $m[1], $preOp, $preVal);
						$preCheck = count(Database::rows($preSql)) > 0;
					} else {
						$condition = "WHERE ($preField $preOp '$preVal') AND item_id='$itemID'";
						$result = Database::select('items', ['item_id'], $condition);
						$preCheck = (count($result) > 0) ? true : false;
					}
					// We evaluate trigger condition using == and != and require the evaluation result
					$postCheck = ($triggerConditionArray[1] == '==') ? @$dataArray[$triggerConditionArray[0]] == $triggerConditionArray[2] : @$dataArray[$triggerConditionArray[0]] != $triggerConditionArray[2];
					// Both must evaluate as true or we return false
					$conditionTrue = ($preCheck == true AND $postCheck == true) ? true : false;
					break;
				case 'update_item_any_trigger':
					$columnArray = array ($triggerConditionArray[0]);
					// Get existing item data and evaluate pre condition
					$condition = "WHERE item_id='$itemID'";
					$row = Database::first('items', '*', $condition);
					$row = \Adlexone\Data\ItemFields::hydrate($row ?? []);
					$conditionTrue = (@$dataArray[$triggerConditionArray[0]] != $row[$triggerConditionArray[0]]) ? true : false;
					break;
				case 'update_item_log_entry':
						
					if (($dataArray['role_id'] >= $triggerConditionArray[3]) AND $dataArray['log_entry'] != ''){
						// returns true if the item log entry base viewer role id is equal or less than the action setting
						$conditionTrue = true ;
					}
					break;
			}
			break;
		case 'update_item_log_entry':
			if (($dataArray['role_id'] >= $triggerConditionArray[3]) AND $dataArray['log_entry'] != ''){
				// returns true if the item log entry base viewer role id is equal or less than the action setting
				$conditionTrue = true ;
			}
			break;
		case 'item_attachment':
			$conditionTrue = true ;
			break;
				
	}
	// We've done our evaluation - now execute the reqired action
	if ($conditionTrue == true) {
		$columnArray = array ('*');
		// Get all action information from actions triggered by this action
		$condition = "WHERE action_id = '$actionData' AND enabled = 'Yes'";
		foreach (Database::select('action_definitions', $columnArray, $condition) as $row) {
			// Execute actions
			require_once 'actions/' . $row['package_file'];
			// This functions name is set from the package_function column value and returns a boolean value if the condition is met
			$functionName = 'execute' . $row['package_function'];
			$functionName($itemID, $_POST, $row['action_condition_pre'], $row['action_condition_post'], $row['action_parameters'], $row['action_data']);
		}
		return true;
	} else {
		return false;
	}
}
/**
 * Shows the setup page for the trigger action for system fields
 *
 * @param string $actionID
 * @return
 */
function showSetupTriggerActionSystemField($actionID = '')
{
	// Get default field values if we use this form for updating the action
	if ($actionID != '') {
		// Get action information from database
		$columnArray = array('*');
		$condition = "WHERE action_id = '" . $actionID . "'";
		$fieldValues = Database::first('action_definitions', $columnArray, $condition);
		$action = NOT_BASE_URL . '&option=update_action&action_package=Action_Triggers&descriptor_name=TriggerActionSystemField&action_id=' . $actionID;
	} else {
		$action = NOT_BASE_URL . '&option=add_action&action_package=Action_Triggers&descriptor_name=TriggerActionSystemField';
	}
	$actionField = Actions::startNewAction($actionID, @$fieldValues,true,true);
	// Get list of existing actions defined
	$columnArray = array('action_id', 'action_name');
	$condition = "WHERE package_function <> 'TriggerActionCustomField' AND package_function <> 'TriggerActionSystemField'";//exclude these action types as they are this action package
	foreach (Database::select('action_definitions', $columnArray,$condition) as $row) {
		$actionIDArray[] = $row['action_id'];
		$actionNameArray[] = $row['action_name'];
	}
	$actionField[ACT_PAK_14] = RenderViews::buildSelectDropdown('action_data', $actionIDArray, $actionNameArray, @$fieldValues['action_data']);
	$systemFieldValueArray = ['item_title', 'creator_security', 'user_security', 'group_security'];
	$systemFieldNameArray = [ACT_PAK_20, ACT_PAK_67, ACT_PAK_44, ACT_PAK_45];
	$actionField[ACT_PAK_46] = RenderViews::buildSelectDropdown('system_field_pre', $systemFieldValueArray, $systemFieldNameArray, @$fieldValues['action_condition_pre']);
	$actionField[''] = RenderViews::buildHiddenInput('action_id', @$fieldValues['action_id']);
	$jsFieldNameArray = "['action_name']";
	$jsTestTypeArray = "['']";
	$jsErrorMsgArray = "['']";
	$jsRequiredMsgArray = "['".ACT_PAK_36."']";
	$jsRequiredArray = "[true]";
	$javascript = "onClick=\"javascript:return fieldCheck('".TXT_468."',".$jsTestTypeArray.",".$jsFieldNameArray.",".$jsErrorMsgArray.",".$jsRequiredMsgArray.",".$jsRequiredArray.");\"";
	define('BODY_CONTENT', RenderViews::buildForm(
		ACT_PAK_47,
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
 * Add or update triggered action definition
 *
 * @param string $actionID
 * @param mixed $add
 * @return
 */
function addUpdateTriggerActionSystemField($actionID = '', $add = false)
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
			$columnArray['action_name'] = $_POST['action_name'] ;
			$columnArray['item_type_id'] = $_POST['item_type_id'] ;
			$columnArray['action_condition_pre'] = $_POST['system_field_pre'];
			$columnArray['action_data'] = $_POST['action_data'];
			$columnArray['enabled'] = $_POST['enabled'];
			$columnArray['action_type'] = 'update_item';
			$columnArray['package_file'] = 'Action_Triggers.actions.php';
			$columnArray['package_function'] = 'TriggerActionSystemField';
			Database::insert('action_definitions', $columnArray);
			$html = RenderViews::showResponse($_POST['action_name'] . ' ' . TXT_301,RenderViews::url(NOT_BASE_URL . '&option=&option=show_defined_actions', ACT_PAK_21, 'URL'));
			define('HEADING', TXT_352);
			define('BODY_CONTENT', $html);
		}
	} else {
		$columnArray['action_name'] = $_POST['action_name'] ;
		$columnArray['item_type_id'] = $_POST['item_type_id'] ;
		$columnArray['action_condition_pre'] = $_POST['system_field_pre'];
		$columnArray['enabled'] = $_POST['enabled'];
		$columnArray['action_data'] = $_POST['action_data'];
		$columnArray['action_type'] = 'update_item';
		$columnArray['package_file'] = 'Action_Triggers.actions.php';
		$columnArray['package_function'] = 'TriggerActionSystemField';
		$condition = "WHERE action_id ='$actionID'";
		Database::update('action_definitions', $columnArray, $condition);
		$html = RenderViews::showResponse($_POST['action_name'] . ' ' . TXT_164, RenderViews::url(NOT_BASE_URL . '&option=&option=show_defined_actions', ACT_PAK_21, 'URL'));
		define('HEADING', TXT_352);
		define('BODY_CONTENT', $html);
	}
	RenderViews::renderPage('main_page_content',  SET_THEME);
}
/**
 * Executes an action based on the saved trigger action id
 *
 * @param integer $ Item Id
 * @param array $preCondition Pre action condition array
 * @param array $triggerCondition Pre action condition array
 * @param string $actionParameters action action parameters
 * @param array $actionData action data
 * @param string $requestingAction Requesting Action allows the input of a specific action point for triggers etc (i.e. update_item, create_item)
 * @return Boolean Return boolean vale to indicate action execution success
 */
function executeTriggerActionSystemField($itemID, $dataArray, $preCondition, $triggerCondition = '', $actionParameters = '', $actionData = '', $actionType = '',$requestingAction = '')
{
	// Set the conditionTrue variable to false and get our arguments ot prove otherwise
	$conditionTrue = false;
	// Get existing item information - the update
	$columnArray = array('item_title','creator_security','user_security');
	$condition = "WHERE item_id = '$itemID'";
	$row = Database::first('items', $columnArray, $condition);
	switch ($preCondition) {
		case 'item_title':
			if ($row['item_title'] != $dataArray['item_title']){
				$conditionTrue = true;
			}
			break;
		case 'creator_security':
			if ($row['creator_security'] != $dataArray['creator_security']){
				$conditionTrue = true;
			}
			break;
		case 'user_security':
			if ($row['user_security'] != $dataArray['user_security']){
				$conditionTrue = true;
			}
			break;
		case 'group_security':
			$before = \Adlexone\Data\GroupMembership::itemGroupIds((int) $itemID);
			$after = \Adlexone\Data\GroupMembership::parseDelimited((string) ($dataArray['group_security'] ?? ''));
			sort($before);
			sort($after);
			if ($before !== $after) {
				$conditionTrue = true;
			}
			break;
	}
	// We've done our evaluation - now execute the reqired action
	if ($conditionTrue == true) {
		$columnArray = array ('*');
		// Get all action information from actions triggered by this action
		$condition = "WHERE action_id = '$actionData'";
		foreach (Database::select('action_definitions', $columnArray, $condition) as $row) {
			// Execute actions
			require_once 'actions/' . $row['package_file'];
			// This functions name is set from the package_function column value and returns a boolean value if the condition is met
			$functionName = 'execute' . $row['package_function'];
			$functionName($itemID, $_POST, $row['action_condition_pre'], $row['action_condition_post'], $row['action_parameters'], $row['action_data']);
		}
		return true;
	} else {
		return false;
	}
}
/**
 * action Descriptors provide the base action name and associated information to the
 * administration_actions controller file so it can create a package actions list
 */
//Custom Field Trigger Action
$actionName['TriggerActionCustomField'] = ACT_PAK_13;
$actionDescription['TriggerActionCustomField'] = ACT_PAK_16;
//System Field Trigger Action
$actionName['TriggerActionSystemField'] = ACT_PAK_34;
$actionDescription['TriggerActionSystemField'] = ACT_PAK_48;

?>