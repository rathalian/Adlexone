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
define('POR_BASE_URL', 'index.php?controller='.$_GET['controller'].'&subcontroller=administration_portal');
/**
 * Shows administrative portal information
 *
 */
function showAdminPortal (){

	// User Information
	$oozGlobalAdminCount = (string) Database::count('users', 'role = ?', ['0']);
	$oozAdminCount = (string) Database::count('users', 'role = ?', ['1']);
	$adminCount = (string) Database::count('users', 'role = ?', ['2']);
	$managerCount = (string) Database::count('users', 'role = ?', ['3']);
	$userCount = (string) Database::count('users', 'role = ?', ['4']);
	$viewerCount = (string) Database::count('users', 'role = ?', ['5']);
	$users = RenderViews::buildFormFieldsGrid([
		TXT_430 => htmlspecialchars((string)$oozGlobalAdminCount, ENT_QUOTES, 'UTF-8'),
		TXT_431 => htmlspecialchars((string)$oozAdminCount, ENT_QUOTES, 'UTF-8'),
		TXT_432 => htmlspecialchars((string)$adminCount, ENT_QUOTES, 'UTF-8'),
		TXT_433 => htmlspecialchars((string)$managerCount, ENT_QUOTES, 'UTF-8'),
		TXT_434 => htmlspecialchars((string)$userCount, ENT_QUOTES, 'UTF-8'),
		TXT_435 => htmlspecialchars((string)$viewerCount, ENT_QUOTES, 'UTF-8'),
	]);
	$sqliteFile = substr(DSN, strlen('sqlite:'));
	$databaseSize = is_file($sqliteFile) ? round(filesize($sqliteFile) / 1024, 2) : 0;
	$rowCount = (string) Database::count('items');
	$data = RenderViews::buildFormFieldsGrid([
		TXT_439 => htmlspecialchars((string)$databaseSize, ENT_QUOTES, 'UTF-8'),
		TXT_440 => htmlspecialchars((string)$rowCount, ENT_QUOTES, 'UTF-8'),
		TXT_441 => htmlspecialchars((string)count(glob(SET_ATTACHMENTS_PATH . '*')), ENT_QUOTES, 'UTF-8'),
	]);
	define('BODY_CONTENT', RenderViews::buildVerticalCards([
		['title' => TXT_428, 'html' => $users],
		['title' => TXT_437, 'html' => $data],
	]));
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}
/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
	default :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		showAdminPortal();
		break;
}
?>