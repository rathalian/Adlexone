<?php
declare(strict_types=1);

/**
 * Adlexone FlowIQ License Agreement 1.0
 *
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where
 *  Adlexone FlowIQ files contain this license in the header of the file, the
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
use Adlexone\support\RenderNavigation;

function showQuickLaunch()
{
//    //Load application list
//    // Get all application xml files and create a application array
//    $directoryPath = 'app/http/controllers/applications/';
//    $applicationFileArray = [];
//
//    // Collect application XML files
//    foreach (scandir($directoryPath) as $entry) {
//        $appXMLFile = $directoryPath . $entry . '/' . $entry . '.xml';
//        if (is_file($appXMLFile)) {
//            $applicationFileArray[] = $appXMLFile;
//        }
//    }
//
//    // Parse XML files and extract data
//    $applicationName = $applicationArray = $applicationImageArray = [];
//    foreach ($applicationFileArray as $filename) {
//        $xml = file_get_contents($filename);
//        $xmlparser = xml_parser_create('UTF-8');
//        xml_parser_set_option($xmlparser, XML_OPTION_SKIP_WHITE, 1);
//        xml_parse_into_struct($xmlparser, $xml, $values);
//        xml_parser_free($xmlparser);
//
//        foreach ($values as $value) {
//            switch ($value['tag']) {
//                case 'NAME':
//                    $applicationName[] = $value['value'];
//                    break;
//                case 'BASE_URL':
//                    $applicationArray[] = $value['value'];
//                    break;
//                case 'IMAGE':
//                    $applicationImageArray[] = $value['value'];
//                    break;
//            }
//        }
//    }
//
//    // Generate page output
//    $quickLaunchArray = [];
//    foreach ($applicationArray as $i => $application) {
//        $imageURL = RenderViews::buildURL('index.php?controller=' . $application, '', 'launchURL', SET_IMAGE_PATH . $applicationImageArray[$i]);
//        $URL = RenderViews::buildURL('index.php?controller=' . $application, constant($applicationName[$i]));
//        $html =  RenderViews::outputIfRoleAllowed($URL . $imageURL, $_SESSION['access_role_id'], 5);
//        $bodyBlock[] = [
//            'title' => constant($applicationName[$i]),
//            'html' => $html,
//        ];
//    }
//    $horizontalList = RenderViews::buildHorizontalCards($bodyBlock);



    $imageURL = RenderViews::buildURL('index.php?controller=item_management_main', '', 'launchURL', SET_IMAGE_PATH . 'manageItems.png');
    $URL = RenderViews::buildURL('index.php?controller=item_management_main', TXT_559, 'launchURL');
    $html =  RenderViews::outputIfRoleAllowed($URL . $imageURL, $_SESSION['access_role_id'], 1);
    $bodyBlock[] = ['title' => TXT_559,'html' => $html];





    $html = RenderViews::outputIfRoleAllowed(RenderViews::buildURL('index.php?controller=administration_main&subcontroller=administration_security&option=new_user', TXT_33), $_SESSION['access_role_id'], 2);
    $html .= RenderViews::outputIfRoleAllowed('<br>'.RenderViews::buildURL( 'index.php?controller=administration_main&subcontroller=administration_security&option=new_group', TXT_34), $_SESSION['access_role_id'], 2);
    $html .= RenderViews::outputIfRoleAllowed('<br>'.RenderViews::buildURL('index.php?controller=administration_main&subcontroller=administration_security&option=manage_users_groups', TXT_73), $_SESSION['access_role_id'], $_SESSION['access_role_id'], 2);
    $bodyBlock[] = RenderViews::outputIfRoleAllowed(['title' => TXT_560,'html' => $html], $_SESSION['access_role_id'], 2);




   $html = RenderViews::outputIfRoleAllowed(RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=administration_item_settings&option=new_custom_field', TXT_88, 'URL'), $_SESSION['access_role_id'], 2);
    $html .= RenderViews::outputIfRoleAllowed('<br>'.RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=administration_item_settings&option=new_item_type', TXT_85, 'URL'),  $_SESSION['access_role_id'], $_SESSION['access_role_id'], 2);
    $html .= RenderViews::outputIfRoleAllowed('<br>'.RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=administration_item_settings&option=new_multilevel_menu_relationship', TXT_658, 'URL'), $_SESSION['access_role_id'], 2);
   $html .= RenderViews::outputIfRoleAllowed('<br>'.RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=administration_item_settings&option=manage_fields_types', TXT_52, 'URL'), $_SESSION['access_role_id'], 2);
//
////    $imageURL = RenderViews::buildURL('index.php?controller=administration_main&subcontroller=administration_item_settings', '', 'launchURL', SET_IMAGE_PATH . 'manageItemTypes.png');
////    $URL = RenderViews::buildURL('index.php?controller=administration_main&subcontroller=administration_item_settings', TXT_50, 'launchURL');
////    $html =  RenderViews::outputIfRoleAllowed($URL . $imageURL, $_SESION['access_role_id'], 1);
        $bodyBlock[] = RenderViews::outputIfRoleAllowed(['title' => TXT_50,'html' => $html], $_SESSION['access_role_id'], 2);


    $html = RenderViews::outputIfRoleAllowed(RenderViews::buildURL('index.php?controller=administration_actions&option=show_action_packages', TXT_255, 'URL'), $_SESSION['access_role_id'], 1);
    $html .= RenderViews::outputIfRoleAllowed('<br>'.RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=administration_actions&option=show_defined_actions', TXT_411, 'URL'), $_SESSION['access_role_id'], 1);
//    // Show page
//    $imageURL = RenderViews::buildURL('index . php ? controller = administration_main & subcontroller = administration_actions', '', 'launchURL', SET_IMAGE_PATH . 'manageActions . png');
//    $URL = RenderViews::buildURL('index . php ? controller = administration_main & subcontroller = administration_actions', TXT_411, 'launchURL');
//    $html =  RenderViews::outputIfRoleAllowed($URL . $imageURL, $_SESSION['access_role_id'], 1);
    $bodyBlock[] =  RenderViews::outputIfRoleAllowed(['title' => TXT_411,'html' => $html], $_SESSION['access_role_id'], 1);


//    /* Generate line content from array */
//    $html = RenderViews::renderHorizontalList($quickLaunchArray);
//    /* Generate full page output */
//    $bodyBlock[] = [
//        'title' => TXT_552,
//        'html' => $html,
//        'full' => true,
//    ];
    $content = RenderViews::buildHorizontalCards($bodyBlock,3);

    // Set page heading and full page content, note no left navigation so LEFT_NAVIGATION is not defined
   // define('BODY_HEADING', TXT_552);
	define('BODY_CONTENT', $content);
}

/**
 * Builds and renders the navigation for the Helpdesk and Knowledgebase sections.
 *
 * This code first constructs a map of controllers and their respective navigation URLs
 * using the `RenderNavigation::build` method. Each controller is associated with a set
 * of navigation links generated by `helpDeskNavigationURLS`.
 *
 * The `RenderNavigation::render` method is then used to render the navigation in a specific
 * style, with optional parameters for columns and the number of links to display inline.
 *
 * Finally, the rendered navigation content is included in the main page layout using
 * `RenderViews::renderThemePage`.
 */
// Build controllers only when the current role is allowed to see the navigation, sets minimum role to view as 5 by default
$controllers = [];
$roleId = $_SESSION['access_role_id'] ?? 5;

$helpdeskUrls = RenderNavigation::helpDeskNavigationURLS();
if (!empty(RenderViews::outputIfRoleAllowed($helpdeskUrls, $roleId, 5))) {
    $controllers['Helpdesk'] = $helpdeskUrls;
}

$knowledgebaseUrls = RenderNavigation::knowledgebaseNavigationURLS();
if (!empty(RenderViews::outputIfRoleAllowed($knowledgebaseUrls, $roleId, 5))) {
    $controllers['Knowledgebase'] = $knowledgebaseUrls;
}

$reportManagerURLS = RenderNavigation::reportManagerNavigationURLS();
if (!empty(RenderViews::outputIfRoleAllowed($reportManagerURLS, $roleId, 5))) {
    $controllers['Reports'] = $reportManagerURLS;
}

$searchURLS = RenderNavigation::itemSettingsURLs();
if (!empty(RenderViews::outputIfRoleAllowed($searchURLS, $roleId, 5))) {
    $controllers['Item Management'] = $searchURLS;
}

$securityURLS = RenderNavigation::securityManagementURLs();
if (!empty(RenderViews::outputIfRoleAllowed($securityURLS, $roleId, 5))) {
    $controllers['Security Management'] = $securityURLS;
}

$settingsURLS = RenderNavigation::systemSettingsURLs();
if (!empty(RenderViews::outputIfRoleAllowed($settingsURLS, $roleId, 0))) {
    $controllers['System Settings'] = $settingsURLS;
}

// Build navigation from the allowed controllers
$controllers = RenderNavigation::build($controllers);
// 2) Render whichever style you want:
define('BODY_CONTENT', RenderNavigation::render($controllers,null,3,false));
RenderViews::renderThemePage('main_page_content',  SET_THEME);
