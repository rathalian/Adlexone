<?php /** @noinspection ALL */

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

function showLogoffAndStatus(): string
{
	return RenderViews::buildURL($_SERVER['PHP_SELF'] . '?action=logoff', TXT_314);
}

function showTime() {
	if (defined('SET_REMAINING_TIME')) {
		echo ' (' . SET_REMAINING_TIME . ' DAYS REMAINING IN TRIAL)';
	}
}

function showLoggedOnUser() {

	// Get users first and last name from database
	$columnArray = array('first_name', 'last_name');
	$condition = "WHERE user_id = '" . $_SESSION['access_user_id'] . "'";
	$fieldValues = Database::first('users', $columnArray, $condition);

	echo TXT_600 . ' : ' . $fieldValues['first_name'] . ' ' . $fieldValues['last_name'];

}

/**
 * showHeader()
 *
 * Show the main header graphic if enabled in the user profile
 */
function showHeader() {
	if ($_SESSION['access_show_header'] == 'Yes') {
		RenderViews::renderThemePage('main_header',  SET_THEME);
	}
}

/**
 * Creates secured navigation buildSelectDropdown
 */
//function showHeaderMenu(): string
//{
//
//	//Returns user to their profile set home screen
//	$baseURL = 'index.php?controller=social_management_main&subcontroller=social_management_manage';
//	$navButton = RenderViews::tbNavBarElements('<span class="glyphicon glyphicon-globe"></span> ' . TXT_1, $baseURL);
//	$html = RenderViews::outputIfRoleAllowed($navButton, $_SESSION['access_role_id'], 5);
//
//// 	//Application List
// 	// Get all application xml files and create a application array
// 	$directories = opendir( 'app/http/controllers/applications/');
// 	$i = 0;
// 	while ($a = readdir($directories)) {
// 		$appXMLFile = $a . '.xml';
// 		if (is_file( 'app/http/controllers/applications/' . $a . '/' . $appXMLFile)) {
// 			$applicationFileArray[$i++] =  'app/http/controllers/applications/' . $a . '/' . $appXMLFile;
// 		}
// 	}
// 	// If any applications are found read them and create the applications array to set a home page
// 	if (is_array($applicationFileArray)) {
// 		foreach ($applicationFileArray as $filename) {
// 			$fp = fopen($filename, "r") or die("Cannot open " . $filename);
// 			$xmlparser = xml_parser_create('UTF-8') or die("Cannot create parser");
// 			$xml = fread($fp, 4096);
// 			xml_parser_set_option($xmlparser, XML_OPTION_SKIP_WHITE, 1);
// 			xml_parse_into_struct($xmlparser, $xml, $values);
// 			xml_parser_free($xmlparser);
// 			// Get the required xml values for each of the tags and build an array for the buildSelectDropdown
// 			foreach ($values as $key => $value) {
// 				switch ($value['tag']) {
// 					// Name of application
// 					case 'NAME' :
// 						$name = constant($value['value']);
// 						break;
// 					case 'BASE_URL' :
// 						$baseURL = 'index.php?controller=' . $value['value'];
// 						break;
// 					default :
// 						;
// 				}// switch
// 			}
// 			$dropDown[$name] = $baseURL;
// 		}
// 	}
//
// 	if ($_GET['controller'] == '') {
// 		$selected = 'index.php?controller=' . $_SESSION['access_home_controller'];
// 	} elseif (stristr($_GET['controller'], 'app_')) {
// 		$selected = 'index.php?controller=' . $_GET['controller'];
// 	} else {
// 		$selected = 'index.php';
// 	}
//
// 	$html .= RenderViews::tbNavBarElements('<span class="glyphicon glyphicon-globe"></span> ' . TXT_4, '', $dropDown);
// 	unset($dropDown);
//
//	//Items
//	$allSecuredDropDownArray = array();
//	if (!isset($_GET['controller']) || ($_GET['controller'] != 'item_management_main')) {
//		// Handle instances where this is the first page after login
//		$baseURL = 'index.php?controller=item_management_main&subcontroller=item_management_manage&option=';
//	} else {
//		$baseURL = 'index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=';
//	}
//	$dropDown[TXT_44] = $baseURL . 'my_items';
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 4);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//	$dropDown[TXT_46] = $baseURL . 'show_item_types&default_item_type=' . SET_DEFAULT_ITEM_TYPE;
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 4);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//	$navButton = RenderViews::tbNavBarElements('<span class="glyphicon glyphicon-file"></span> '.TXT_2, 'index.php?controller=item_management_main', $allSecuredDropDownArray);
//	$html .= RenderViews::outputIfRoleAllowed($navButton, $_SESSION['access_role_id'], 2);
//	unset($dropDown, $securedDropDownArray, $allSecuredDropDownArray);
//
//	//Search
//	$allSecuredDropDownArray = array();
//	$baseURL = 'index.php?controller=search_management_main&subcontroller=search_management_manage&option=';
//	$dropDown[TXT_376] = $baseURL . 'show_quick_search';
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 4);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//	$dropDown[TXT_96] = $baseURL . 'show_item_search';
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 4);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//	$dropDown[TXT_313] = $baseURL . 'show_saved_searches';
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 4);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//	$navButton = RenderViews::tbNavBarElements('<span class="glyphicon glyphicon-search"></span> ' . TXT_3, 'index.php?controller=search_management_main', $allSecuredDropDownArray);
//	$html .= RenderViews::outputIfRoleAllowed($navButton, $_SESSION['access_role_id'], 2);
//	unset($dropDown, $securedDropDownArray, $allSecuredDropDownArray);
//
//	//ADMINISTRATION
//	$baseURL = 'index.php?controller=administration_main&subcontroller=';
//	$allSecuredDropDownArray = array();
//	//Security management and sub menus
//	$dropDown[TXT_28 ] = $baseURL. 'administration_security';
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 1);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//	//Item settings and sub menus
//	$dropDown[TXT_49] = $baseURL . 'administration_item_settings';
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 1);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//	//Action settings and sub menus
//	$dropDown[TXT_128] = $baseURL . 'administration_actions';
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 1);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//	//System administration and sub menus
//	$dropDown[TXT_55] = $baseURL . 'administration_settings';
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 0);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//	//User profile
//	$dropDown[TXT_24] = $baseURL . 'administration_security&option=modify_user';
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 4);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//	//Logoff
//	$dropDown[TXT_314] = $_SERVER['PHP_SELF'] . '?action=logoff';
//	$securedDropDownArray = RenderViews::outputIfRoleAllowed($dropDown, $_SESSION['access_role_id'], 5);
//	if ($securedDropDownArray != '') {//Merge the drop down array with the other drop down arrays if a secured resource is returned
//		$allSecuredDropDownArray = array_merge($securedDropDownArray, $allSecuredDropDownArray);
//	}
//
//	$navButton = RenderViews::tbNavBarElements('<span class="glyphicon glyphicon-dashboard"></span> '.TXT_5, 'index.php?controller=administration_main', $allSecuredDropDownArray);
//	unset($dropDown, $securedDropDownArray, $allSecuredDropDownArray);
//
//	$html .= RenderViews::outputIfRoleAllowed($navButton, $_SESSION['access_role_id'], 5);
//
//    return $html;
//}

function Controller(): void
{
	RenderViews::includeControllerFile(@$_GET['controller'], SET_DEFAULT_PAGE);
}

/**
 * Logic to load the appropriate template
 */
RenderViews::renderThemePage('main_page',  SET_THEME);
?>
