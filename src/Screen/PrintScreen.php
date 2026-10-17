<?php
namespace Adlexone\Screen;

use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\RenderViews;
use Adlexone\support\SharedMethods;

/**
 * Screen formerly loaded from legacy/.
 */
final class PrintScreen
{
    public static function open(): void
    {
        /**
         * Logic to load the appropriate template
         */

        /**
         * Logic to render the appropriate template or call wrapper functions
         * Option is captured from the value selected via a hyperlink
         */
        switch (@$_GET['option']) {
        	case 'print_item' :
        		self::printItem($_GET['item_id']);
        		break;
        	default :
        		// Not allowed
        		define('FULL_PAGE_CONTENT', RenderViews::buildVerticalCards([['title' => TXT_623, 'html' => '']]));
        		RenderViews::renderThemePage('full_page_view',  SET_THEME);
        }
    }
    /**
     * Controller Constants
     */

    public static function printItem($itemID)
    {
    	$itemID = (int) $itemID;

    	$i = 0;
    	if ($_SESSION['access_role_id'] <=2){//Admin, Inlay Admin, Global Inlay Admin have access
    		$i++;
    	}else{
    		if (\Adlexone\Data\GroupMembership::userSharesItemGroup((int) $_SESSION['access_user_id'], (int) $itemID)
    			|| Database::exists('items', 'item_id = ? AND (user_security = ? OR creator_security = ?)', [(int) $itemID, (int) $_SESSION['access_user_id'], (int) $_SESSION['access_user_id']])
    		) {
    			$i++;
    		}
    	}
    	if ($i > 0){ //The user is allowed to access the task
    		// Setup item  information for display
    		$columnArray = array ('*');
            $itemFields = Database::first('items', $columnArray, 'item_id = ?', [$itemID]); // Build item table
    		$itemFields = \Adlexone\Data\ItemFields::hydrate($itemFields ?? []);
    		// Get item type name
    		$columnArray = array ('item_type_name');
    		$row = Database::first('item_types', $columnArray, 'item_type_id = ?', [$itemFields['item_type_id']]);
    		$itemTypeName = $row['item_type_name'];
    		// Set the creator and owner values
    		$columnArray = array('user_name');
    		$row = Database::first('users', $columnArray, 'user_id = ?', [$itemFields['creator_security']]);
    		$itemField[TXT_551] = $row['user_name'];
    		$columnArray = array('user_name');
    		$row = Database::first('users', $columnArray, 'user_id = ?', [$itemFields['user_security']]);
    		$itemField[TXT_269] = $row['user_name'];
    		// Create group membership list
    		$groupArray = \Adlexone\Data\GroupMembership::itemGroupIds((int) $itemFields['item_id']);
    		$columnArray = array ('group_id', 'group_name');
    		$result = [];
    		if ($groupArray !== []) {
    			$placeholders = implode(', ', array_fill(0, count($groupArray), '?'));
    			$result = Database::select('groups', $columnArray, 'group_id IN (' . $placeholders . ')', array_values($groupArray));
    		}
    		$i = 0;
    		$groupMembership = '';
    		foreach ($result as $row) {
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
    		foreach (Database::select('item_type_custom_fields', $columnArray, 'item_type_id = ?', [$itemFields['item_type_id']], 'custom_field_order ASC') as $customFields) {
    			$columnArray = array ('*');
    			$row = Database::first('custom_fields', $columnArray, 'custom_field_id = ?', [$customFields['custom_field_id']]);
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
    		// Time Manager removed from shared schema.
    		$logHtml = '';
    		// Get item log information from database
    		$columnArray = array ('*');
    		foreach (Database::select('core_log', $columnArray, 'item_id = ?', [$itemID], 'log_item_sequence DESC') as $row) {
    			//Restrict log viewing
    			if ($_SESSION['access_role_id'] <= $row['role_id']){
    				// Get user name if applicable
    				if ($row['security_id'] != '') {
    					$columnArray = array ('user_name', 'first_name', 'last_name');
    					$rowUser = Database::first('users', $columnArray, 'user_id = ?', [$row['security_id']]);
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
}
