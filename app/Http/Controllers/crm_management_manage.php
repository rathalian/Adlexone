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


function showUser($userID = '', $itemID = ''): void
{
	$logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
	$attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
	$itemHref = 'index.php?controller=' . rawurlencode((string)($_GET['controller'] ?? ''))
		. '&subcontroller=item_management_manage&option=show_item&item_id=' . rawurlencode((string)$itemID)
		. $logEntry . $attachments;
	$itemURL = RenderViews::buildURL($itemHref, TXT_621 . ' - ' . TXT_398 . ' ' . $itemID, 'URL');
	if ($userID === '') {
		RenderViews::buildResponse(TXT_620, $itemURL);
		return;
	}

	$fieldValues = Database::firstResultParams('SELECT * FROM users WHERE user_id = ?', [$userID]);
	if ($fieldValues === null) {
		RenderViews::buildResponse(TXT_620, $itemURL);
		return;
	}

	$text = static function (mixed $value): string {
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	};
	$email = (string)($fieldValues['email'] ?? '');
	$userInformation = [
		TXT_167 => $text($fieldValues['first_name'] ?? ''),
		TXT_168 => $text($fieldValues['last_name'] ?? ''),
		TXT_169 => RenderViews::buildURL('mailto:' . $email, $email, 'URL'),
		TXT_171 => $text($fieldValues['phone'] ?? ''),
		TXT_172 => $text($fieldValues['address'] ?? ''),
		TXT_173 => $text($fieldValues['city'] ?? ''),
		TXT_174 => $text($fieldValues['state_province'] ?? ''),
		TXT_175 => $text($fieldValues['zip_postal'] ?? ''),
		TXT_176 => $text($fieldValues['country'] ?? ''),
		TXT_177 => $text($fieldValues['website'] ?? ''),
		TXT_178 => $text($fieldValues['other'] ?? ''),
	];
	$html = '';
	foreach ($userInformation as $name => $field) {
		$html .= '<div><strong>' . $text($name) . '</strong> ' . $field . '</div>';
	}
	$back = RenderViews::buildURL(
		$itemHref,
		TXT_621 . ' (' . (string)($fieldValues['user_name'] ?? '') . ') - ' . RenderViews::getLanguageConstant('LA_398', 'TXT_398') . ' ' . $itemID,
		'URL'
	);
	if (!defined('PAGE_TITLE')) {
		define('PAGE_TITLE', TXT_621);
	}
	define('BODY_CONTENT', '<p>' . $back . '</p>' . $html);
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
	case 'view_user' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
		showUser((string)($_GET['user_id'] ?? ''), (string)($_GET['item_id'] ?? ''));
		break;
	default :
		break;
}
?>