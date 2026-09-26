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
define('ACT_BASE_URL', 'index.php?controller=' . $_GET['controller'] . '&subcontroller=administration_actions');
/**
 * Shows secured action options
 */
function showSettingsOptions (): void
{
	// Security Options
	$securityOption = RenderViews::buildURL(ACT_BASE_URL . '&option=show_action_packages', TXT_255, 'URL') . '<br>';
	// Secure for users for role 1 (Users) and above
	$tableRows = RenderViews::outputIfRoleAllowed(RenderViews::tableData('', '', '', '', 'tdc1', array($securityOption), 'row'), $_SESSION['access_role_id'], 1);
	$securityOption = RenderViews::buildURL(ACT_BASE_URL . '&option=show_defined_actions', TXT_411, 'URL') . '<br>';
	// Secure for users for role 0 (Viewers) and above
	$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('', '', '', '', 'tdc1', array($securityOption), 'row'), $_SESSION['access_role_id'], 1);
	$html = RenderViews::table('100%', '0', '5', '0', 'tcNavigationBorder', $tableRows);
	// Show page
	define('HEADING', TXT_128);
	define('BODY_CONTENT', $html);
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}
/**
 * Creates a form with all available action packages.
 *
 * - Collects `*.actions.php` files from the `actions` directory.
 * - Sorts packages naturally (case-insensitive) and builds label/value arrays.
 * - Uses RenderViews::buildForm to produce a modern, accessible div-based form.
 * - Defines BODY_CONTENT with the returned HTML and includes the main page.
 *
 * @return void
 */
function showactionPackages(): void
{
    // Collect action package files
    $files = glob('actions/*.actions.php') ?: [];

    // Extract package names and sort naturally (case-insensitive)
    $packages = array_map(fn(string $f): string => basename($f, '.actions.php'), $files);
    if (!empty($packages)) {
        sort($packages, SORT_NATURAL | SORT_FLAG_CASE);
    }

    // Build aligned value/display arrays
    $actionPackageArray = $packages;
    $displayNameArray = array_map(fn(string $p): string => str_replace('_', ' ', $p), $packages);

    // If no packages found, provide a single empty option with a friendly label
    if (empty($actionPackageArray)) {
        $actionPackageArray = [0 => ''];
        $displayNameArray   = [0 => TXT_366];
    }

    // Build fields and buttons for the form
    $fields = [
        TXT_250 => RenderViews::buildSelectDropdown('action_package', $actionPackageArray, $displayNameArray, '')
    ];

    $buttons = [
        RenderViews::buildFormButton('submit', 'submit_button', TXT_69)
    ];

    // Render the form using the modern helper and place it into the page
    $html = RenderViews::buildForm(TXT_253, ACT_BASE_URL . '&option=show_package_action_list', $fields, $buttons);

    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showPackageactionList($actionPackage): void
{
	// Setup a list of actions for the selected package
	require_once  'actions/' . $actionPackage . '.actions.php';
	// $actionName and $description are arrays set in the action package file
	$tableRows = '';
    $actionName = '';
    foreach($actionName as $descriptorName => $value) {
		// Create action URL and information for user
		$actionName = RenderViews::buildURL(ACT_BASE_URL . '&option=new_action&action_package=' . $actionPackage . '&descriptor_name=' . $descriptorName, $value, 'URL');
		$description = $actionDescription[$descriptorName];
		$tableRows .= RenderViews::tbTableRows(array($actionName));
		$tableRows .= RenderViews::tbTableRows(array($description));
	}
	$html = RenderViews::tbTable($tableRows, 'table table-bordered table-striped', '100%');
	define('HEADING', TXT_253 . ' - ' . str_replace('_', ' ', $actionPackage));
	define('BODY_CONTENT', $html);
	RenderViews::renderThemePage('main_page_content',  SET_THEME);
}
function showDefinedactions ()
{
	// Get defined actions from database and create a list
	$columnArray = array('action_id', 'action_name', 'package_file', 'package_function');
	$condition = 'ORDER BY package_file ASC';
	$sql = Database::sqlSelect('action_definitions', $columnArray,$condition);
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	if (Database::numRows($result) == 0){
		$tableRows = RenderViews::tableData('3', '', '', 'trc1', 'tdc1', array(TXT_412), 'row');
	}else{
		$i = 0;
		while ($row = Database::fetchArray($result)) {
			// Create action URL with ID to update the action
			$actionArray[] = RenderViews::buildURL(ACT_BASE_URL . '&option=defined_action&action_id=' . $row['action_id'], $row['action_name'], 'URL');
			$actionArray[] = str_replace('_',' ', str_replace('.actions.php','' , $row['package_file'])).'-'.$row['package_function'];
			if ($_SESSION['access_role_id'] <=1){
				$actionArray[] = RenderViews::buildURL(ACT_BASE_URL . '&option=delete_action&action_id=' . $row['action_id'], TXT_315, 'URL','','onClick="javascript:return confirm(\''.TXT_400.'\')"');
				if($i == 0){
					$tableRows = RenderViews::tableData('', '', '', '', 'tdcHeading', array(TXT_299,TXT_250,TXT_388), 'row');
				}
				$class = RenderViews::setOddEvenClass($i, 'trc1', 'trc2');
				$tableRows .= RenderViews::tableData('', '', '', '', $class, $actionArray, 'row');
			}else{
				if ($i == 0){
					$tableRows = RenderViews::tableData('', '', '', '', 'tdcHeading', array(TXT_299,TXT_250,TXT_388), 'row');
				}
				$class = RenderViews::setOddEvenClass($i, 'trc1', 'trc2');
				$tableRows .= RenderViews::tableData('', '', '', '', $class, $actionArray, 'row');
			}
			$i++;
			unset($actionArray);
		}
	}
	$html = RenderViews::table('100%', '0', '5', '0', 'tcBorder', $tableRows);
	define('HEADING', TXT_83);
	define('BODY_CONTENT', $html);
	RenderViews::renderThemePage('main_page_content',  SET_THEME);
}
function showDefinedaction ($actionID)
{
	// Get action details from database and open the update function
	$columnArray = array('package_function', 'action_name', 'package_file');
	$condition = "WHERE action_id = '$actionID'";
	$sql = Database::sqlSelect('action_definitions', $columnArray, $condition);
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$row = Database::fetchArray($result);
	$functionName = 'showSetup' . $row['package_function'];
	require_once  'actions/' . $row['package_file'];
	// Run the function to show the update page for actions
	$functionName($actionID);
}
function deleteAction($actionID)
{
	$sql = "DELETE FROM action_definitions WHERE action_id = '$actionID'";
	Database::query($sql, DSN, SET_SHOW_SQL);
	showDefinedactions();
}
/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
	case 'show_package_action_list' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		showPackageactionList($_POST['action_package']);
		break;
	case 'show_action_packages' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		showactionPackages();
		break;
	case 'new_action' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		require_once  'actions/' . $_GET['action_package'] . '.actions.php';
		$functionName = 'showSetup' . $_GET['descriptor_name'];
		$functionName(@$_GET['action_id']);
		break;
	case 'add_action' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		require_once  'actions/' . $_GET['action_package'] . '.actions.php';
		$functionName = 'addUpdate' . $_GET['descriptor_name'];
		$functionName('', true);
		break;
	case 'show_defined_actions' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		showDefinedactions();
		break;
	case 'defined_action' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		showDefinedaction($_GET['action_id']);
		break;
	case 'update_action' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		require_once  'actions/' . $_GET['action_package'] . '.actions.php';
		$functionName = 'addUpdate' . $_GET['descriptor_name'];
		$functionName($_GET['action_id'], false);
		break;
	case 'delete_action' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		deleteAction($_GET['action_id']);
		break;
	default :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		showSettingsOptions();
		break;
}
?>