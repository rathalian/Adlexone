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

use Adlexone\support\RenderViews;
use Adlexone\support\SharedMethods;
/**
 * Controller specific constants
 */
define('KB_SUB_URL', 'index.php?controller=app_oneorzeroknowledgebase_main');


/**
 * Initiate Application Constants and variables
 */

SharedMethods::loadConstantFromIni(SET_INSTALL_PATH. 'translations/applications/oneorzeroknowledgebase/' . SET_LANGUAGE . '.lang.php');


/**
 * Load sub controller based on passed in GET information
 */
function subController()
{
	RenderViews::includeControllerFile(@$_GET['subcontroller'], 'app_oneorzeroknowledgebase_manage');
}
/**
 * Creates secured navigation buildSelectDropdown
 */
function showControllerMenu ()
{
	// KB Options
	$tableRows = RenderViews::tableData('', '', 'center', '', array('tdTopLeft', 'tdLeftNavTopMiddle', 'tdTopRight'), array('', APP_TXT_56, ''), 'row');	$image = RenderViews::buildImage(SET_IMAGE_PATH . 'knowledgebase.png', SET_SHOW_IMAGES);
	$URL = RenderViews::buildURL(KB_SUB_URL . '&subcontroller=app_oneorzeroknowledgebase_manage&option=show_knowledge', APP_TXT_68, 'URLNav');
	$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNav', array($image . $URL), 'row'), $_SESSION['access_role_id'], 4);
	$image = RenderViews::buildImage(SET_IMAGE_PATH . 'newItem.png', SET_SHOW_IMAGES);
	$URL = RenderViews::buildURL(KB_SUB_URL . '&subcontroller=item_management_manage&option=show_item_types&default_item_type=' . KNOWLEDGEBASE_SET_KB_ITEM_TYPE, APP_TXT_49, 'URLNav');
	$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNav', array($image . $URL), 'row'), $_SESSION['access_role_id'], 4);
	$image = RenderViews::buildImage(SET_IMAGE_PATH . 'complexSearch.png', SET_SHOW_IMAGES);
	$URL = RenderViews::buildURL(KB_SUB_URL . '&subcontroller=search_management_manage&option=show_item_search&event_id=returned_items&item_types=' . KNOWLEDGEBASE_SET_KB_ITEM_TYPE, APP_TXT_47, 'URLNav');
	$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNavLast', array($image . $URL), 'row'), $_SESSION['access_role_id'], 5);
	
	// Application Administrative Options
	$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'center', '', 'tdLeftNavShaded', array(APP_TXT_30), 'row'), $_SESSION['access_role_id'], 1);
	$image = RenderViews::buildImage(SET_IMAGE_PATH . 'helpdeskSettings.png', SET_SHOW_IMAGES);
	$URL = RenderViews::buildURL(KB_SUB_URL . '&subcontroller=app_oneorzeroknowledgebase_manage&option=knowledgebase_settings', APP_TXT_31, 'URLNav');
	$tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNavLast', array($image . $URL), 'row'), $_SESSION['access_role_id'], 1);
	// Bottom Cell
	$tableRows .= RenderViews::tableData('3', '', 'left', '', 'tdLeftNavBottom', array('&nbsp;'), 'row');
	$html = RenderViews::table('200', '0', '0', '0', '', $tableRows);

	return $html;
}
/**
 * Page rendered from controller
 */
RenderViews::includeControllerFile($_GET['subcontroller'] ?? null, 'app_oneorzeroknowledgebase_manage');
?>
