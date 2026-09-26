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

use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\RenderViews;
use Adlexone\support\SharedMethods;

/**
 * Controller Constants
 */

function printItem($itemID)
{

	$i = 0;
	if ($_SESSION['access_role_id'] <=2){//Admin, FlowIQ Admin, Global FlowIQ Admin have access
		$i++;
	}else{
		// Check to see if we have access to it
		$sql = "SELECT item_id FROM items WHERE (user_security = '" . $_SESSION['access_user_id'] . "' OR creator_security  = '" . $_SESSION['access_user_id'] . "') and item_id='".$itemID."'";
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		if(Database::numRows($result) > 0){
			$i++;
			//No need to go any further
		}else{
			// Get all items by group assignment
			$sql = "SELECT groups FROM group_members WHERE user_id = '" . $_SESSION['access_user_id'] . "'";
			$result = Database::query($sql, DSN, SET_SHOW_SQL);
			$row = Database::fetchArray($result);
			$groupArray = explode('}-{', $row['groups']);
			$sql = "SELECT group_security FROM items WHERE item_id='".$itemID."'";
			$result = Database::query($sql, DSN, SET_SHOW_SQL);
			$row = Database::fetchArray($result);
			foreach ($groupArray as $a){
				if(stristr($row['group_security'],'}-{'.$a.'}-{')){
					$i++;
				}
			}
		}
	}
	if ($i > 0){ //The user is allowed to access the task
		// Setup item  information for display
		$columnArray = array ('*');
		$condition = "WHERE item_id = '" . $itemID . "'";
		$sql = Database::sqlSelect('items', $columnArray, $condition);
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		$itemFields = Database::fetchArray($result); // Build item table
		// Get item type name
		$columnArray = array ('item_type_name');
		$condition = "WHERE item_type_id = '" . $itemFields['item_type_id'] . "'";
		$sql = Database::sqlSelect('item_types', $columnArray, $condition);
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		$row = Database::fetchArray($result);
		$itemTypeName = $row['item_type_name'];
		// Set the creator and owner values
		$columnArray = array('user_name');
		$condition = "WHERE user_id = '".$itemFields['creator_security']."'";
		$sql = Database::sqlSelect('users', $columnArray,$condition);
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		$row = Database::fetchArray($result);
		$itemField[TXT_551] = $row['user_name'];
		$columnArray = array('user_name');
		$condition = "WHERE user_id = '".$itemFields['user_security']."'";
		$sql = Database::sqlSelect('users', $columnArray,$condition);
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		$row = Database::fetchArray($result);
		$itemField[TXT_269] = $row['user_name'];
		// Create group membership list
		$groupArray = explode('}-{', $itemFields['group_security']);
		$i = 0;
		foreach($groupArray as $a) {
			if ($i == 0) {
				$condition = "WHERE group_id='" . $a . "'";
			} else {
				$condition .= "OR group_id='" . $a . "'";
			}
			$i++;
		}
		$columnArray = array ('group_id', 'group_name');
		$sql = Database::sqlSelect('groups', $columnArray, $condition);
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		$i = 0;
		$groupMembership = '';
		while ($row = Database::fetchArray($result)) {
			if ($i == 0) {
				$groupMembership = $row['group_name'];
			} else {
				$groupMembership .= ', ' . $row['group_name'];
			}
			$i++;
		} // while
		$itemField[TXT_270] = $groupMembership;
		$itemField[TXT_84] = $itemFields['item_title'];
		// Setup custom field display
		$columnArray = array ('custom_field_id');
		$condition = "WHERE item_type_id = '" . $itemFields['item_type_id'] . "' ORDER BY custom_field_order ASC";
		$sql = Database::sqlSelect('item_type_custom_fields', $columnArray, $condition);
		$customFieldResult = Database::query($sql, DSN, SET_SHOW_SQL);
		while ($customFields = Database::fetchArray($customFieldResult)) {
			$columnArray = array ('*');
			$condition = "WHERE custom_field_id = '" . $customFields['custom_field_id'] . "'";
			$sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
			$result = Database::query($sql, DSN, SET_SHOW_SQL);
			$row = Database::fetchArray($result);
			$row['field_type'] = FieldTypes::normalise($row['field_type']);
			if ($row['enabled'] != 'Yes'){
				continue;
			}
			if (($row['field_type'] == 'workerField' OR $row['field_type'] == 'workerFieldMenu' OR $row['field_type'] == 'fieldSeparator')){
				continue;
			}
			// Override database value if values have already been selected
			$value = @$itemFields['custom_field_' . $row['custom_field_id']];
			if ($row['field_type'] == FieldTypes::TEXT_AREA){
				$value = str_replace("\n", "<br />", $value);
				$value = '<br />'.$value.'<br /><br />';
			}
			$itemField[$row['custom_field_name']] = $value;

		}
		$displayFields = [];
		foreach ($itemField as $name => $field) {
			$displayFields[$name] = '<div>' . $field . '</div>';
		}
		$html = RenderViews::buildFormFieldsGrid($displayFields);
		$sql = "SELECT sum(minutes) AS total_minutes FROM timemanager_time_table WHERE item_id = '$itemID'";
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		$timeRow = Database::fetchArray($result);
		if ($timeRow && $timeRow['total_minutes'] > 0){
			$html .= RenderViews::buildFormFieldsGrid([TXT_639 => htmlspecialchars((string)$timeRow['total_minutes'], ENT_QUOTES, 'UTF-8')]);
		}
		$logHtml = '';
		// Get item log information from database
		$columnArray = array ('*');
		$condition = "WHERE item_id = '" . $itemID . "' ORDER BY log_item_sequence DESC";
		$sql = Database::sqlSelect('core_log', $columnArray, $condition);
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		while ($row = Database::fetchArray($result)) {
			//Restrict log viewing
			if ($_SESSION['access_role_id'] <= $row['role_id']){
				// Get user name if applicable
				if ($row['security_id'] != '') {
					$columnArray = array ('user_name', 'first_name', 'last_name');
					$condition = "WHERE user_id = '" . $row['security_id'] . "'";
					$sql = Database::sqlSelect('users', $columnArray, $condition);
					$resultUser = Database::query($sql, DSN, SET_SHOW_SQL);
					$rowUser = Database::fetchArray($resultUser);
				}
				$role = '';
				switch ($row['role_id']) {
					case 0:
						$role = TXT_190;
						break;
					case 1:
						$role = TXT_191;
						break;
					case 2:
						$role = TXT_192;
						break;
					case 3:
						$role = TXT_193;
						break;
					case 4:
						$role = TXT_194;
						break;
					case 5:
						$role = TXT_303;
						break;
				}
				$userName = htmlspecialchars((string)($rowUser['user_name'] ?? ''), ENT_QUOTES, 'UTF-8');
				$firstName = htmlspecialchars((string)($rowUser['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
				$lastName = htmlspecialchars((string)($rowUser['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
				$heading = '<strong><i>' . date(SET_DATE_FORMAT, $row['create_date']) . ' ' . TXT_260 . ' ' . $userName . ' (' . $firstName . ' ' . $lastName . ' - ' . htmlspecialchars((string)$role, ENT_QUOTES, 'UTF-8') . ')</i></strong>';
				$text = nl2br(htmlspecialchars((string)$row['log_text'], ENT_QUOTES, 'UTF-8'));
				$logHtml .= '<div class="log-item"><div class="log-heading">' . $heading . '</div><div class="log-text">' . $text . '</div></div>';
			}
		}
		define('FULL_PAGE_CONTENT', RenderViews::buildVerticalCards([
			['title' => TXT_624 . ': ' . $itemFields['item_id'] . ' - ' . $itemTypeName, 'html' => $html],
			['title' => TXT_601, 'html' => $logHtml],
		]));
		RenderViews::renderThemePage('full_page_view',  SET_THEME);
	}else{
		define('FULL_PAGE_CONTENT', RenderViews::buildVerticalCards([['title' => TXT_452, 'html' => '']]));
		RenderViews::renderThemePage('full_page_view',  SET_THEME);
	}
}
/**
 * Logic to load the appropriate template
 */

/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
	case 'print_item' :
		printItem($_GET['item_id']);
		break;
	default :
		// Not allowed
		define('FULL_PAGE_CONTENT', RenderViews::buildVerticalCards([['title' => TXT_623, 'html' => '']]));
		RenderViews::renderThemePage('full_page_view',  SET_THEME);
}
?>
