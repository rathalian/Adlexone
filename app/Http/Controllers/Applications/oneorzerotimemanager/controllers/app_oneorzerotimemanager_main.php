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
define('OOZ_TIM_BASE_URL', 'index.php?controller=app_oneorzerotimemanager_main');
/**
 * Initiate Application Constants
 */
Render::setConstantsFromFile(OOZ_SET_INSTALL_PATH . 'applications/oneorzerotimemanager/language/' . OOZ_SET_LANGUAGE . '.lang.php');
if (file_exists(OOZ_SET_WRITEABLE_DIRECTORY. 'applications/oneorzerotimemanager/configuration/time_settings.php')){
	Render::setConstantsFromFile(OOZ_SET_WRITEABLE_DIRECTORY. 'applications/oneorzerotimemanager/configuration/time_settings.php');
	define('OOZ_TIM_CONFIGURED',true);
}else{
	define('OOZ_TIM_CONFIGURED',false);
}
/**
 * Load sub controller based on passed in GET information
 */
function subController()
{
	Render::setController(@$_GET['subcontroller'], 'app_oneorzerotimemanager_manage', OOZ_SET_INSTALL_PATH);
}
/**
 * Creates secured navigation menu
 */
function showControllerMenu ()
{
	// Reports Options
	$tableRows = Render::tableData('', '', 'center', '', array('tdTopLeft', 'tdLeftNavTopMiddle', 'tdTopRight'), array('', APP_TXT_1, ''), 'row');
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'clock.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=show_add_time', APP_TXT_2, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'pendingtime.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=show_pending_time', APP_TXT_3, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'complexSearch.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=search_management_manage&option=show_item_search', APP_TXT_83, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image . $URL), 'row'), $_SESSION['access_role_id'], 5);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'manageCriteria.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=search_management_manage&option=show_saved_searches&application=app_oneorzerotimemanager_main', APP_TXT_84, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNavLast', array($image.$URL), 'row'), $_SESSION['access_role_id'], 3);
	
	//Reports
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'center', '', 'tdLeftNavShaded', array(APP_TXT_58), 'row'), $_SESSION['access_role_id'], 5);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'dateRange.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=show_time_reports&type=date_range', APP_TXT_76, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'dateRange.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=show_time_reports&type=date_range_no_item', APP_TXT_77, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'dateRange.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=show_time_reports&type=date_range_item', APP_TXT_78, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	// Saved searches
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'search.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=show_time_reports&type=saved', APP_TXT_80, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image . $URL), 'row'), $_SESSION['access_role_id'], 2);	
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'item.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=show_time_reports&type=item_id', APP_TXT_25, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'projectReport.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=show_time_reports&type=project', APP_TXT_20, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'timeCode.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=show_time_reports&type=job_code', APP_TXT_21, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'costCenter.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=show_time_reports&type=cost_center', APP_TXT_30, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNavLast', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
	// Application Administrative Options
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'center', '', 'tdLeftNavShaded', array(APP_TXT_13), 'row'), $_SESSION['access_role_id'], 1);
	$image = Render::image(OOZ_SET_IMAGE_PATH . 'helpdeskSettings.png', OOZ_SET_SHOW_IMAGES);
	$URL = Render::url(OOZ_TIM_BASE_URL . '&subcontroller=app_oneorzerotimemanager_manage&option=time_settings', APP_TXT_14, 'URLNav');
	$tableRows .= Render::secureReturn(Render::tableData('3', '', 'left', '', 'tdLeftNavLast', array($image . $URL), 'row'), $_SESSION['access_role_id'], 1);
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
