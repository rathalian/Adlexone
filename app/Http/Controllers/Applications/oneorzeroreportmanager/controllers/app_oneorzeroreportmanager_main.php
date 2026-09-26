<?php
/**
 * OneOrZero AIMS License Agreement 1.0
 * 
 * 1. Copying the OneOrZero AIMS software and distributing as your own software
 *  without the written permission of OneOrZero is forbidden under the terms of
 *  the OneOrZero AIMS License.
 * 2. You may modify your copy of the OneOrZero AIMS software, however where 
 *  OneOrZero AIMS files contain the OneOrZero AIMS license in the header of the file, the
 *  OneOrZero AIMS License header must remain.
 * 3. OneOrZero, and the copyright holders of the OneOrZero AIMS, provide no
 *  warranty for the data created or managed by your OneOrZero AIMS installation.
 * 4. OneOrZero, and the copyright holders of the OneOrZero AIMS, provide no
 *  warranty for your OneOrZero AIMS configuration or the hosting environment
 *  your OneOrZero AIMS installation operates in. 
 * 5. OneOrZero, and the copyright holders of the OneOrZero AIMS, provide no
 *  warranty for the OneOrZero AIMS where the software has been modified by
 *  third parties (i.e. other than OneOrZero), unless an agreement has been
 *  reached with OneOrZero.
 * 6. By using the OneOrZero AIMS, you are indicating your acceptance of the
 *  stated OneOrZero AIMS License terms and conditions. 
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */
/**
 * /*******************************************************************************
 * Required Libraries
 */
require_once OOZ_SET_FRAMEWORK_PATH . "/lib/ooz_render.class.php";
/**
 * Controller specific constants
 */
define('OOZ_REP_BASE_URL', 'index.php?controller=app_oneorzeroreportmanager_main');
/**
 * Initiate Application Constants
 */
Render::setConstantsFromFile(OOZ_SET_INSTALL_PATH . 'applications/oneorzeroreportmanager/language/' . OOZ_SET_LANGUAGE . '.lang.php');
/**
 * Load sub controller based on passed in GET information
 */
function subController()
{
	Render::setController(@$_GET['subcontroller'], 'app_oneorzeroreportmanager_manage', OOZ_SET_INSTALL_PATH);
}
/**
 * Creates secured navigation menu
 */
function showControllerMenu ()
{
	// Reports Options
	$tableRows = Render::tableData('', '', 'center', '', array('tdTopLeft', 'tdLeftNavTopMiddle', 'tdTopRight'), array('', APP_TXT_1, ''), 'row');
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'report.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_REP_BASE_URL . '&subcontroller=app_oneorzeroreportmanager_manage', APP_TXT_10, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'report.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_REP_BASE_URL . '&subcontroller=app_oneorzeroreportmanager_manage&option=view_multi_reports', APP_TXT_39, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNavLast', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'center', '', 'tdLeftNavShaded', array(APP_TXT_35), 'row'), $_SESSION['access_role_id'], 3);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'newCriteria.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_REP_BASE_URL . '&subcontroller=search_management_manage&option=show_item_search', APP_TXT_15, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 3);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'manageCriteria.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_REP_BASE_URL . '&subcontroller=search_management_manage&option=show_saved_searches', APP_TXT_30, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 3);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'createReport.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_REP_BASE_URL . '&subcontroller=app_oneorzeroreportmanager_manage&option=create_report', APP_TXT_2, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 3);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'manageReports.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_REP_BASE_URL . '&option=manage_reports', APP_TXT_29, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 3);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'relationship.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_REP_BASE_URL . '&subcontroller=app_oneorzeroreportmanager_manage&option=create_multi_report', APP_TXT_37, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 3);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'manageCriteria.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_REP_BASE_URL . '&subcontroller=app_oneorzeroreportmanager_manage&option=manage_multi_reports', APP_TXT_38, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNavLast', array($image.$URL), 'row'), $_SESSION['access_role_id'], 3);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'manageCriteria.png', OOZ_SET_SHOW_IMAGES);

	// Bottom Cell
	$tableRows .= Render::tableData('3', '', 'left', '', 'tdLeftNavBottom', array('&nbsp;'), 'row');
	$html = Render::table('200', '0', '0', '0', '', $tableRows);

	return $html;
}
/**
 * Page rendered from controller
 */
Render::renderPage('all_controllers', OOZ_SET_LANGUAGE, OOZ_SET_THEME, OOZ_SET_CACHED);

?>
