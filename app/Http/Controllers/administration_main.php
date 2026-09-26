<?php
declare(strict_types=1);
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

/**
 * Controller Template Wrapper Functions
 */
/**
 * Creates secured navigation buildSelectDropdown
 */
function showControllerMenu (): string
{
    // Profile options
    $tableRows = RenderViews::tableData('', '', 'center', '', array('tdTopLeft', 'tdLeftNavTopMiddle', 'tdTopRight'), array('', TXT_24, ''), 'row');
    $image = RenderViews::buildImage(SET_IMAGE_PATH . 'profile.png', SET_SHOW_IMAGES);
	$URL = RenderViews::buildURL('index.php?controller=administration_main&subcontroller=administration_security&option=modify_user', TXT_24, 'URLNav');
    $tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNavLast', array($image.$URL), 'row'), $_SESSION['access_role_id'], 5);
    // IMS options
    $tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'center', '', 'tdLeftNavShaded', array(TXT_380), 'row'), $_SESSION['access_role_id'], 1);
	$image = RenderViews::buildImage(SET_IMAGE_PATH . 'key.png', SET_SHOW_IMAGES);
	$URL = RenderViews::buildURL('index.php?controller=administration_main&subcontroller=administration_security', TXT_28, 'URLNav');
    $tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 1);
	$image = RenderViews::buildImage(SET_IMAGE_PATH . 'itemSettings.png', SET_SHOW_IMAGES);
    $URL = RenderViews::buildURL('index.php?controller=administration_main&subcontroller=administration_item_settings', TXT_49, 'URLNav');
    $tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 1);
	$image = RenderViews::buildImage(SET_IMAGE_PATH . 'actions.png', SET_SHOW_IMAGES);
    $URL = RenderViews::buildURL('index.php?controller=administration_main&subcontroller=administration_actions', TXT_128, 'URLNav');
    $tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNavLast', array($image.$URL), 'row'), $_SESSION['access_role_id'], 1);
    // System options
    $tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'center', '', 'tdLeftNavShaded', array(TXT_381), 'row'), $_SESSION['access_role_id'], 1);
    $image = RenderViews::buildImage(SET_IMAGE_PATH . 'settings.png', SET_SHOW_IMAGES);
	$URL = RenderViews::buildURL('index.php?controller=administration_main&subcontroller=administration_settings', TXT_55, 'URLNav');
    $tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNav', array($image.$URL), 'row'), $_SESSION['access_role_id'], 1);
	$image = RenderViews::buildImage(SET_IMAGE_PATH . 'procedures.png', SET_SHOW_IMAGES);
    $URL = RenderViews::buildURL('index.php?controller=administration_main&subcontroller=administration_procedures', TXT_230, 'URLNav');
    $tableRows .= RenderViews::outputIfRoleAllowed(RenderViews::tableData('3', '', 'left', '', 'tdLeftNavLast', array($image.$URL), 'row'), $_SESSION['access_role_id'], 0);
    // Bottom Cell
    $tableRows .= RenderViews::tableData('3', '', 'left', '', 'tdLeftNavBottom', array('&nbsp;'), 'row');
    $html = RenderViews::table('200', '0', '0', '0', '', $tableRows);

   return $html;
}
/**
 * SubController()
 *
 * @return
 */
function SubController()
{
    if ($_SESSION['access_role_id'] <= 1){
    	RenderViews::includeControllerFile(@$_GET['subcontroller'], 'administration_portal');
    }else{
    	RenderViews::includeControllerFile(@$_GET['subcontroller'], 'administration_security');
    }
    
	
}
/**
 * Page rendered from controller
 */
if ($_SESSION['access_role_id'] <= 1){
	RenderViews::includeControllerFile(@$_GET['subcontroller'], 'administration_portal');
}else{
	RenderViews::includeControllerFile(@$_GET['subcontroller'], 'administration_security');
}
?>
