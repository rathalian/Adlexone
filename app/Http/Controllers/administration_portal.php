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
	$sql = "SELECT COUNT(*) FROM users WHERE role = '0'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$oozGlobalAdminCount = Database::firstResult($result);
	$sql = "SELECT COUNT(*) FROM users WHERE role = '1'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$oozAdminCount = Database::firstResult($result);
	$sql = "SELECT COUNT(*) FROM users WHERE role = '2'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$adminCount = Database::firstResult($result);
	$sql = "SELECT COUNT(*) FROM users WHERE role = '3'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$managerCount = Database::firstResult($result);
	$sql = "SELECT COUNT(*) FROM users WHERE role = '4'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$userCount = Database::firstResult($result);
	$sql = "SELECT COUNT(*) FROM users WHERE role = '5'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$viewerCount = Database::firstResult($result);
	$userRow = RenderViews::tableData('2', '', 'left', '', array('tdcHeadingBottomBorder'), array (TXT_428), 'row');
	$userRow .= RenderViews::tableData('', array('50%','50%'), array('left','left'), '', array('tdc2','tdc2'), array (TXT_430.': <strong>' .$oozGlobalAdminCount.'</strong>',TXT_431.': <strong>' .$oozAdminCount.'</strong>'), 'row');
	$userRow .= RenderViews::tableData('', array('50%','50%'), array('left','left'), '', array('tdc2','tdc2'), array (TXT_432.': <strong>' .$adminCount.'</strong>',TXT_433.': <strong>' .$managerCount.'</strong>'), 'row');
	$userRow .= RenderViews::tableData('', array('50%','50%'), array('left','left'), '', array('tdc2','tdc2'), array (TXT_434.': <strong>' .$userCount.'</strong>',TXT_435.': <strong>' .$viewerCount.'</strong>'), 'row');
	$html = RenderViews::table('100%', '0', '0', '0', '', $userRow);
	$sql = "SHOW TABLE STATUS";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$fragmentated = 'No';
	$databaseSize = '';
	while($row = mysql_fetch_array($result)){
		$databaseSize = $databaseSize + ($row['Data_length']+$row['Index_length']);
		if ($row['Data_free'] > 0 AND ($row['Data_free'] > ($row['Data_length'] * 0.2))){//20% fragmentation
			$fragmentated = 'Yes';//Override
		}
	}
	$dataInfoRow = RenderViews::tableData('2', '', 'left', '', array('tdcHeadingBottomBorder'), array (TXT_437), 'row');
	$dataInfoRow .= RenderViews::tableData('', array('50%','50%'), array('left','left'), '', array('tdc2','tdc2'), array (TXT_439.': <strong>'.round($databaseSize/1024,2).'</strong>',TXT_438.': <strong>'.$fragmentated.'</strong>'), 'row');
	$sql = "SELECT COUNT(*) FROM items";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$rowCount = Database::firstResult($result);
	$dataInfoRow .= RenderViews::tableData('', array('50%','50%'), array('left','left'), '', array('tdc2','tdc2'), array (TXT_440.': <strong>'.$rowCount.'</strong>',TXT_441.': <strong>'.count(glob(SET_ATTACHMENTS_PATH.'*')).'</strong>'), 'row');
	$html .= RenderViews::table('100%', '0', '0', '0', '', $dataInfoRow);
	$portalRow = RenderViews::tableData('', array('90%'), array('center'), '', array('moduleContainer'), array($html), 'row');
	$portalHTML = RenderViews::table('100%', '0', '0', '0', '', $portalRow);
	// Show page
	define('HEADING', TXT_429);
	define('BODY_CONTENT', $portalHTML);
	RenderViews::renderThemePage('main_page_content',  SET_LANGUAGE, SET_THEME);
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