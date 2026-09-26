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
use Adlexone\support\RenderViews;

/**
 * Controller specific constants
 */
define('CRM_BASE_URL', 'index.php?controller=' . $_GET['controller'] . '&subcontroller=crm_management_manage');


function showUser($userID = '',$itemID = '')
{
	$logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
	$attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
	$itemURL = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&item_id='.$itemID.$logEntry . $attachments,TXT_621.' - '.TXT_398.' '.$itemID,'URL');
	if ($userID == '') {
		$html = RenderViews::showResponse(TXT_620);
		define('BODY_CONTENT', $html);
		define('HEADING', $itemURL);
		RenderViews::renderThemePage('main_page_content',  SET_THEME);
	} else {
		// Get custom fields from database
		$columnArray = array ('*');
		$condition = "WHERE user_id = '$userID'";
		$sql = Database::sqlSelect('users', $columnArray, $condition);
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		$fieldValues = Database::fetchArray($result);
		$userInformation[TXT_167] = $fieldValues['first_name'];
		$userInformation[TXT_168] = $fieldValues['last_name'];
		$userInformation[TXT_169] = RenderViews::buildURL('mailto:'.$fieldValues['email'], $fieldValues['email'],'URL');
		$userInformation[TXT_171] = $fieldValues['phone'];
		$userInformation[TXT_172] = $fieldValues['address'];
		$userInformation[TXT_173] = $fieldValues['city'];
		$userInformation[TXT_174] = $fieldValues['state_province'];
		$userInformation[TXT_175] = $fieldValues['zip_postal'];
		$userInformation[TXT_176] = $fieldValues['country'];
		$userInformation[TXT_177] = $fieldValues['website'];
		$userInformation[TXT_178] = $fieldValues['other'];
		//$tableRows = '';
		foreach ($userInformation as $name => $field) {
			$html .= '<div><strong>' . $name . '</strong>  '. $field. '</div>';
			//$tableRows .= RenderViews::tableData('', array('20%', '70%'), '', '' , 'tdc1BottomBorder', $cellData, 'row');
		}
		//$html = RenderViews::table('95%', '0', '0', '0', 'tableIndent', $tableRows);
		$itemURL = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&item_id='.$itemID.$logEntry . $attachments,TXT_621.' ('.$fieldValues['user_name'].') - '.RenderViews::getLanguageConstant('LA_398','TXT_398').' '.$itemID,'URL');
		define('HEADING', $itemURL);
		define('BODY_CONTENT', $html);
		RenderViews::renderThemePage('main_page_content',  SET_THEME);
	}
}

/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
	case 'view_user' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
		showUser($_GET['user_id'], $_GET['item_id']);
		break;
	default :
		break;
}
?>