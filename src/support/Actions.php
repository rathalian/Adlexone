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

namespace Adlexone\support;

use Adlexone\support\Database;

class Actions {
	/*
	 * @param $itemID Item ID
	 * @param $actionType Action type allows the input of a specific action point for triggers etc (i.e. update_item, create_item)
	 * @param $showResponse Shows the action execution response page
	 * @return Return visual response if set
	 */
	public static function executeAction($itemID, $requestingAction, $showResponse = true){
		// Get the action(s) associated with the items
		$sql = "SELECT item_type_id FROM items WHERE item_id = '" . $itemID . "'";
				$result = Database::rows($sql);
		// Get the action package information and execute
		$i = 0;
		$rowaction = $result[0] ?? null;
		// We specify the item type id to fetch the valid defintion and to exclude those Actions that are not bound to item type id's such as Actions
		// triggered from another action
		// SQL gets all action types like ActionTyps% as we use subset values i.e. update_item has update_item_criteria met etc
		$sql = "SELECT * FROM action_definitions WHERE (item_type_id = '" . $rowaction['item_type_id'] . "' OR item_type_id = '0') AND (action_type LIKE '" . $requestingAction . "%' OR action_type = 'main_page_content') AND enabled = 'Yes'";
				$resultactionPackage = Database::rows($sql);
		foreach ($resultactionPackage as $rowActionPackage) {
			require_once 'actions/' . $rowActionPackage['package_file'];
			// This functions name is set from the package_function column value and returns a boolean value if the condition is met
			$functionName = 'execute' . $rowActionPackage['package_function'];
			$actionExecuted = $functionName($itemID, $_POST, $rowActionPackage['action_condition_pre'], $rowActionPackage['action_condition_post'], $rowActionPackage['action_parameters'], $rowActionPackage['action_data'],$rowActionPackage['action_type'], $requestingAction);
			// Setup response message
			if ($i == 0) {
				$html = '<strong>' . $rowActionPackage['action_name'];
			} else {
				$html .= ', ' . $rowActionPackage['action_name'];
			}
			$i++;
		}
		// If no Actions executed show an appropriate message or end the response message
		if (!isset($html)) {
			$html = TXT_277 . '<br><br>';
		} else {
			if ($actionExecuted == true) {
				$html .= '</strong> ' . TXT_275 . '<br><br>';
			} else {
				$html = TXT_278 . '<br><br>';
			}
		}
		$html .= '<a href="javascript: history.go(-1)" class="bodyNavigation">' . TXT_306 . '</a>';
		if ($showResponse == true) {
			define('HEADING', TXT_276);
			define('BODY_CONTENT', $html);
			RenderViews::renderPage('main_page_content',  SET_THEME);
		}
	}


	/**
	 * @param $actionID Action ID
	 * @param $fieldValues Action field values for defined Actions
	 * @param $showItemTypes Show item type buildSelectDropdown
	 * @param $showEnabled Show enabled buildSelectDropdown
	 * @return Opening action header
	 */
	public static function startNewAction($actionID = '', $fieldValues = '', $showItemTypes = true, $showEnabled = true){
		$fieldValues = is_array($fieldValues) ? $fieldValues : [];
		$actionField[TXT_299] = RenderViews::buildTextInput('action_name', $fieldValues['action_name'] ?? '');
		if($showEnabled){
			$actionField[TXT_451] = RenderViews::buildSelectDropdown('enabled', array('Yes','No'), array(TXT_93,TXT_94), $fieldValues['enabled'] ?? '');
		}
		if ($showItemTypes) {
			$columnArray = array('item_type_id', 'item_type_name');
			$result = Database::select('item_types', $columnArray);
			$typeIDArray[] = '0';
			$typeValueArray[] = ACT_PAK_74;
			foreach ($result as $row) {
				$typeIDArray[] = $row['item_type_id'];
				$typeValueArray[] = $row['item_type_name'];
			}
			$actionField[TXT_300] = RenderViews::buildSelectDropdown('item_type_id', $typeIDArray, $typeValueArray, $fieldValues['item_type_id'] ?? '') . ' * ' . TXT_95;
		}
		return $actionField;
	}
}
