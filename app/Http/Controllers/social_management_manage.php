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

if (!defined('SERVICECENTRE_SET_RESULT_COUNT')) {
    define('SERVICECENTRE_SET_RESULT_COUNT', '10');
}
if (!defined('SERVICECENTRE_SET_SAVED_SEARCHES')) {
    define('SERVICECENTRE_SET_SAVED_SEARCHES', 'Yes');
}
if (!defined('SERVICECENTRE_SET_ANNOUNCEMENTS')) {
    define('SERVICECENTRE_SET_ANNOUNCEMENTS', 'Yes');
}
if (!defined('SERVICECENTRE_SET_DEFAULT_SCREEN')) {
    define('SERVICECENTRE_SET_DEFAULT_SCREEN', 'quick_launch');
}

/**
 * /**
 * Controller Template Wrapper Functions
 */
function showPublicItems() {
	$html = '
  <div class="panel panel-default" id="panel1">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" data-target="#collapseOne" 
           href="#collapseOne">
          Collapsible Group Item #1
        </a>
      </h4>
    </div>
    <div id="collapseOne" class="panel-collapse collapse in">
      <div class="panel-body">
        Anim pariatur 
      </div>
    </div>
  </div>
  <div class="panel panel-default" id="panel2">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" data-target="#collapseTwo" 
           href="#collapseTwo" class="collapsed">
          Collapsible Group Item #2
        </a>
      </h4>
    </div>
    <div id="collapseTwo" class="panel-collapse collapse">
      <div class="panel-body">
        Anim pariatur cliche reprehenderit, enim eiusmod high life accusamus terry richardson ad squid..
      </div>
    </div>
  </div>
  <div class="panel panel-default" id="panel3">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" data-target="#collapseThree"
           href="#collapseThree" class="collapsed">
          Collapsible Group Item #3
        </a>
      </h4>
    </div>
    <div id="collapseThree" class="panel-collapse collapse">
      <div class="panel-body">
        Anim pariatur cliche reprehenderit
      </div>
    </div>
  </div>


';
	define('HEADING', 'test');
	define('BODY_CONTENT', $html);
	RenderViews::renderThemePage('main_page_content',  SET_THEME);
}

function showServiceCentreModules() {
	$html = showTopX();
	$html .= RenderViews::buildHorizontalCards([
		['title' => APP_TXT_5, 'html' => showSavedSearches()],
	], 2);
	define('BODY_CONTENT', $html);
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showServiceCentreQuickLaunch() {
	$blocks = [];
	if ($_SESSION['access_role_id'] < 5) {
		$blocks[] = [
			'title' => APP_TXT_59,
			'html' => RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=item_management_manage&option=show_item_types&default_item_type=' . SERVICECENTRE_SET_ITEM_TYPE, '', 'launchURL', SET_IMAGE_PATH . 'newTicketBig.png')
				. '<br>' . RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=item_management_manage&option=show_item_types&default_item_type=' . SERVICECENTRE_SET_ITEM_TYPE, APP_TXT_59, 'URLHeading')
				. '<br>' . APP_TXT_58,
		];
	}
	$blocks[] = [
		'title' => APP_TXT_60,
		'html' => RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=search_management_manage&option=show_saved_searches', '', 'launchURL', SET_IMAGE_PATH . 'savedSearchBig.png')
			. '<br>' . RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=search_management_manage&option=show_saved_searches', APP_TXT_60, 'URLHeading')
			. '<br>' . APP_TXT_66,
	];
	$blocks[] = [
		'title' => APP_TXT_62,
		'html' => RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=search_management_manage&option=show_quick_search', '', 'launchURL', SET_IMAGE_PATH . 'searchBig.png')
			. '<br>' . RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=search_management_manage&option=show_quick_search', APP_TXT_62, 'URLHeading')
			. '<br>' . APP_TXT_63,
	];
	if ($_SESSION['access_role_id'] < 5) {
		$blocks[] = [
			'title' => APP_TXT_61,
			'html' => RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=search_management_manage&option=show_item_search&item_types=' . SERVICECENTRE_SET_ITEM_TYPE, '', 'launchURL', SET_IMAGE_PATH . 'advancedSearchBig.png')
				. '<br>' . RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=search_management_manage&option=show_item_search&item_types=' . SERVICECENTRE_SET_ITEM_TYPE, APP_TXT_61, 'URLHeading')
				. '<br>' . APP_TXT_64,
		];
		$blocks[] = [
			'title' => APP_TXT_68,
			'html' => RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=app_servicecentre_main&option=show_announcements', '', 'launchURL', SET_IMAGE_PATH . 'announcementsBig.png')
				. '<br>' . RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=app_servicecentre_main&option=show_announcements', APP_TXT_68, 'URLHeading')
				. '<br>' . APP_TXT_69,
		];
	}
	$blocks[] = [
		'title' => APP_TXT_55,
		'html' => RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=app_servicecentre_main&option=show_portal', '', 'launchURL', SET_IMAGE_PATH . 'portalBig.png')
			. '<br>' . RenderViews::buildURL(HEL_SUB_URL . '&subcontroller=app_servicecentre_main&option=show_portal', APP_TXT_55, 'URLHeading')
			. '<br>' . APP_TXT_71,
	];

	$html = RenderViews::buildHorizontalCards($blocks, 3);
	if (is_numeric(SERVICECENTRE_SET_SAVED_SEARCH)) {
		$html .= showTopX();
	}
	define('BODY_CONTENT', $html);
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showAnnouncementItem($id = '') {
	$sql = "SELECT * FROM announcements WHERE id='" . $id . "'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$row = Database::fetchArray($result);
	$heading = date(SET_DATE_FORMAT, $row[1]) . ': ' . $row[4];
	define('BODY_CONTENT', RenderViews::buildVerticalCards([[
		'title' => $heading,
		'html' => RenderViews::buildFormFieldsGrid([
			'' => str_replace("\n", '<br />', (string)$row[2]),
		]),
	]]));
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showUserItems($userID = '') {
	$sql = "SELECT * FROM announcements";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$list = '';
	while ($row = Database::fetchArray($result)) {
		$actions = '';
		if ($_SESSION['access_role_id'] < 2) {
			$actions = RenderViews::buildURL(CONTROLLER_BASEURL . '&option=edit_announcement&id=' . $row['id'], APP_TXT_22, '', 'URL')
				. ' ' . RenderViews::buildURL(CONTROLLER_BASEURL . '&option=delete_announcement&id=' . $row['id'], TXT_47, '', 'URL', 'onClick="javascript:return confirm(\'' . TXT_400 . '\')"');
		}
		$fields = [
			TXT_346 => htmlspecialchars((string)$row['subject'], ENT_QUOTES, 'UTF-8') . ' (' . date(SET_DATE_FORMAT, $row['time']) . ')',
			TXT_347 => str_replace("\n", '<br />', (string)$row['message']),
		];
		if ($actions !== '') {
			$fields[TXT_388] = $actions;
		}
		$list .= RenderViews::buildFormFieldsGrid($fields);
		$list .= RenderViews::buildHorizontalSeparator();
	}
	$html = RenderViews::buildVerticalCards([['title' => TXT_344, 'html' => $list]]);
	$javascript = "";
	// Show add new announcement to managers (3) and above only
	if ($_SESSION['access_role_id'] <= 3) {
		$jsFieldNameArray = "['subject']";
		$jsTestTypeArray = "['']";
		$jsErrorMsgArray = "['']";
		$jsRequiredMsgArray = "['" . TXT_547 . "']";
		$jsRequiredArray = "[true]";
		$javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";
		$html .= RenderViews::buildForm(
			TXT_344,
			CONTROLLER_BASEURL . '&option=add_announcement',
			[
				TXT_346 => RenderViews::buildTextInput('subject', ''),
				TXT_347 => RenderViews::buildTextArea('message', ''),
			],
			[
				RenderViews::buildFormButton('submit', 'submit_button', TXT_345, $javascript),
				RenderViews::buildFormButton('reset', 'reset', TXT_75),
			]
		);
	}
	define('BODY_CONTENT', $html);
	RenderViews::renderThemePage('main_page_content',  SET_THEME);
}

/**
 * Deletes an announcement
 *
 * @param integer $id Announcement ID
 */
function deleteAnnouncement($id) {
	$sql = "DELETE FROM announcements WHERE id = '$id'";
	Database::query($sql, DSN, SET_SHOW_SQL);
	showUserItems();
}

function editAnnouncement($id) {
	$sql = "SELECT subject, message FROM announcements WHERE id = '$id'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$row = Database::fetchArray($result);
	$jsFieldNameArray = "['subject']";
	$jsTestTypeArray = "['']";
	$jsErrorMsgArray = "['']";
	$jsRequiredMsgArray = "['" . TXT_547 . "']";
	$jsRequiredArray = "[true]";
	$javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";
	define('BODY_CONTENT', RenderViews::buildForm(
		TXT_349,
		CONTROLLER_BASEURL . '&option=update_announcement',
		[
			TXT_346 => RenderViews::buildTextInput('subject', $row['subject']),
			TXT_347 => RenderViews::buildTextArea('message', $row['message'], SET_FORM_FIELD_HEIGHT),
			'' => RenderViews::buildHiddenInput('id', $id),
		],
		[
			RenderViews::buildFormButton('submit', 'submit_button', TXT_348, $javascript),
			RenderViews::buildFormButton('reset', 'reset', TXT_75),
		]
	));
	RenderViews::renderThemePage('main_page_content',  SET_THEME);
}

function updateAnnouncement() {
	// Update announcemnent and refresh announcements page
	$columnArray['message'] = $_POST['message'];
	$columnArray['subject'] = $_POST['subject'];
	$condition = "WHERE id ='{$_POST['id']}'";
	$sql = Database::sqlUpdate('announcements', $columnArray, $condition);
	Database::query($sql, DSN, SET_SHOW_SQL);
	showUserItems();
}

/**
 * Generates a saved searches table with items such as my open items etc
 *
 * @return Saved searches table
 */
function showSavedSearches() {
	$sql = "SELECT * FROM saved_searches WHERE (user ='" . $_SESSION['access_user_id'] . "' OR user = 'all' OR user = 'system') AND application = 'app_servicecentre_main' ORDER BY search_name ASC";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	if (Database::numRows($result) == 0) {
		return RenderViews::buildFormFieldsGrid(['' => APP_TXT_40]);
	}
	$html = '';
	while ($row = Database::fetchArray($result)) {
		if ($row['user'] == 'all') {
			$url = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=saved_search&global=1&id=' . $row['search_id'], $row['search_name'] . ' (' . TXT_408 . ')', '', 'URL');
		} else {
			$url = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=saved_search&id=' . $row['search_id'], $row['search_name'], '', 'URL');
		}
		$html .= RenderViews::buildFormFieldsGrid(['' => $url]);
	}
	return $html;
}

/**
 * Generates the top ten search results ordered by highest to lowest id
 *
 * @return Top Ten table
 */
function showTopX() {
	//Get saved search SQL
	$sql = "SELECT saved_search_sql, search_name FROM saved_searches WHERE search_id = '" . SERVICECENTRE_SET_SAVED_SEARCH . "'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$row = Database::fetchArray($result);
	//Set heading
	$columnArray = [];
	$headTitle = [];
	if (is_array($row) && ($row['saved_search_sql'] ?? '') != '') {
		$savedSearch2 = substr($row['saved_search_sql'], 6);
		$orderSQL = (stripos($savedSearch2, "order by")) ? substr($savedSearch2, strripos($savedSearch2, "order by") + 8) : "";
		$savedSearch2 = substr($savedSearch2, 0, strripos($savedSearch2, "from"));
		$tmpArray = explode(',', $savedSearch2);
		foreach ($tmpArray as $key => $value) {
			$tmpvars1 = explode(" ", $value);
			@$columnArray[] = $tmpvars1[1];
			switch(trim($value)) {
				case 'item_id' :
					@$headTitle[] = LA_102;
					break;
				case 'item_title' :
					@$headTitle[] = LA_84;
					break;
				case 'create_date' :
					@$headTitle[] = TXT_225;
					break;
				case 'item_type_id' :
					@$headTitle[] = LA_226;
					break;

				case 'creator_security' :
					@$headTitle[] = LA_551;
					break;
				case 'user_security' :
					@$headTitle[] = LA_269;
					break;

				default :
					$tmpvars = explode("_", $tmpvars1[1]);
					$headsql = "SELECT custom_field_name FROM custom_fields WHERE custom_field_id = " . $tmpvars[count($tmpvars) - 1];
					$headresult = Database::query($headsql, DSN, SET_SHOW_SQL);
					@$headTitle[] = Database::firstResult($headresult);
					unset($tmpvars1);
					unset($tmpvars);
			}
		}
	}
	$show = RenderViews::buildURL('#', TXT_373, 'URL', '', 'onclick="showRow(\'top_ten\');return false;"');
	$hide = RenderViews::buildURL('#', TXT_374, 'URL', '', 'onclick="hideRow(\'top_ten\');return false;"');

	$searchName = is_array($row) ? (string)($row['search_name'] ?? '') : '';
	$html = '';
	if (!is_array($row) || Database::numRows($result) == 0) {
		$html = RenderViews::buildFormFieldsGrid(['' => APP_TXT_54]);
	} else {
		//Execute saved search SQL
		$sql = str_replace('session_user', $_SESSION['access_user_id'], $row['saved_search_sql']);
		$result = Database::query($sql, DSN, SET_SHOW_SQL);
		$i = 0;
		// Get user group security
		$sql = "SELECT groups FROM group_members WHERE user_id = '" . $_SESSION['access_user_id'] . "'";
		$groupResult = Database::query($sql, DSN, SET_SHOW_SQL);
		$groupRow = Database::fetchArray($groupResult);
		$groupArray = explode('}-{', $groupRow['groups']);
		while ($row = Database::fetchArray($result)) {
			$logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
			$attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
			foreach ($columnArray as $value) {
				switch ($value) {
					case 'item_id' :
						$cellData[] = $row['item_id'];
						break;
					case 'item_title' :
						if ($row['item_title'] == '') {
							$title = TXT_357;
						} else {
							$title = $row['item_title'];
						}
						$logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
						$attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
						$cellData[] = '<a href ="index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&item_id=' . $row[0] . $logEntry . $attachments . '" class="URL">' . $title . '</a>';
						break;
					case 'create_date' :
						$cellData[] = date(SET_DATE_FORMAT, $row['create_date']);
						break;
					case 'item_type_id' :
						if (@$itemTypeID != $row['item_type_id']) {// We don't need to recheck as the last check was for the same item type id
							$tmpcolumnArray = array('item_type_name');
							$condition = "WHERE item_type_id = '" . $row['item_type_id'] . "'";
							$sql = Database::sqlSelect('item_types', $tmpcolumnArray, $condition);
							$itemTypeResult = Database::query($sql, DSN, SET_SHOW_SQL);
							$itemTypeRow = Database::fetchArray($itemTypeResult);
							$itemTypeName = $itemTypeRow['item_type_name'];
							//We set this so we can use it later if the next check is the same item type
							$cellData[] = $itemTypeName;
							$itemTypeID = $row['item_type_id'];
						} else {
							$cellData[] = $itemTypeName;
						}
						break;

					case 'creator_security' :
						$creatorColumnArray = array('user_name');
						$condition = "WHERE user_id = '" . $row['creator_security'] . "'";
						$sql = Database::sqlSelect('users', $creatorColumnArray, $condition);
						$creatorResult = Database::query($sql, DSN, SET_SHOW_SQL);
						$creatorRow = Database::fetchArray($creatorResult);
						$cellData[] = $creatorRow['user_name'];
						break;

					case 'user_security' :
						$userColumnArray = array('user_name');
						$condition = "WHERE user_id = '" . $row['user_security'] . "'";
						$sql = Database::sqlSelect('users', $userColumnArray, $condition);
						$userResult = Database::query($sql, DSN, SET_SHOW_SQL);
						$userRow = Database::fetchArray($userResult);
						$cellData[] = $userRow['user_name'];
						break;
					default :
						$cellData[] = $row[$value];
				}
			}
			//Do an item security check
			// Check to see if we have access to it
			$allowedAccess = false;
			$sql = "SELECT user_security,creator_security,group_security FROM items WHERE item_id = '" . $row['item_id'] . "'";
			$itemResult = Database::query($sql, DSN, SET_SHOW_SQL);
			$itemRow = Database::fetchArray($itemResult);
			if ($itemRow['user_security'] == $_SESSION['access_user_id'] OR $itemRow['creator_security'] == $_SESSION['access_user_id']) {
				$allowedAccess = true;
			} else {
				foreach ($groupArray as $a) {
					if (stristr($itemRow['group_security'], '}-{' . $a . '}-{')) {
						$allowedAccess = true;
					}
				}
			}
			if ($allowedAccess == true && $i < (int)SERVICECENTRE_SET_RESULT_COUNT) {
				$fields = [];
				foreach ($cellData as $index => $cell) {
					$label = (string)($headTitle[$index] ?? $index);
					if ($label === '' || array_key_exists($label, $fields)) {
						$label .= ' ' . $index;
					}
					$fields[$label] = (string)$cell;
				}
				$html .= RenderViews::buildFormFieldsGrid($fields);
				$html .= RenderViews::buildHorizontalSeparator();
				$i++;
			}
			unset($cellData);
		}
	}
	$title = APP_TXT_53 . ' ' . SERVICECENTRE_SET_RESULT_COUNT . ' - ' . $searchName;
	$controls = '<div>' . $show . ' \\ ' . $hide . ' ' . APP_TXT_67 . '</div>';
	if ($html === '') {
		$html = RenderViews::buildFormFieldsGrid(['' => APP_TXT_54]);
	}
	return RenderViews::buildVerticalCards([['title' => $title, 'html' => $controls . $html]]);
}

function showSearchItems($id, $userID, $rss = false) {
	// Do a check to see if the calling user has a matching search
	$sql = "SELECT saved_search_sql, search_name FROM saved_searches WHERE user = '$userID' OR user = 'all' AND search_id ='$id'";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$row = Database::fetchArray($result);
	if (Database::numRows($result) > 0) {
		$html = showItems(Database::buildArray($row['saved_search_sql'], DSN, SET_SHOW_SQL), 'create_date DESC', $rss, $row['search_name']);
		define('BODY_CONTENT', $html);
		RenderViews::renderThemePage('main_page_content',  SET_THEME);
	} else {
		// Set blank placeholder for template as there are no summary views configured
		define('BODY_CONTENT', '');
		RenderViews::renderThemePage('main_page_content',  SET_THEME);
	}
}

/**
 * showServiceCentreSettings()
 *
 * Service Centre settings page
 */
function showServiceCentreSettings() {
	$settings = @parse_ini_file(SET_WRITEABLE_DIRECTORY . 'applications/servicecentre/configuration/servicecentre_settings.php');
	// Get all item types
	$columnArray = array('item_type_id', 'item_type_name');
	$sql = Database::sqlSelect('item_types', $columnArray);
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	while ($row = Database::fetchArray($result)) {
		$valueArray[] = $row['item_type_id'];
		$displayArray[] = $row['item_type_name'];
	}
	$fields[APP_TXT_32] = RenderViews::buildSelectDropdown('SERVICECENTRE_SET_ITEM_TYPE', $valueArray, $displayArray, $settings['SERVICECENTRE_SET_ITEM_TYPE']);
	$columnArray = array('item_type_id', 'item_type_name');
	$sql = Database::sqlSelect('item_types', $columnArray);
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$i = 0;
	while ($row = Database::fetchArray($result)) {
		$listValues[$i] = $row[0];
		$listDisplayValues[$i] = $row[1];
		$i++;
	}
	$fields[APP_TXT_36] = RenderViews::buildSelectDropdown('SERVICECENTRE_SET_SAVED_SEARCHES', array('Yes', 'No'), array(APP_TXT_42, APP_TXT_43), $settings['SERVICECENTRE_SET_SAVED_SEARCHES']);
	$fields[APP_TXT_38] = RenderViews::buildSelectDropdown('SERVICECENTRE_SET_ANNOUNCEMENTS', array('Yes', 'No'), array(APP_TXT_42, APP_TXT_43), $settings['SERVICECENTRE_SET_ANNOUNCEMENTS']);
	$listValues[0] = '';
	$listDisplayValues[0] = APP_TXT_52;
	$sql = "SELECT search_id, search_name FROM saved_searches WHERE user = 'all' OR user = 'system' ORDER BY search_name ASC";
	$result = Database::query($sql, DSN, SET_SHOW_SQL);
	$i = 1;
	while ($row = Database::fetchArray($result)) {
		$listValues[$i] = $row['search_id'];
		$listDisplayValues[$i] = $row['search_name'];
		$i++;
	}
	$fields[APP_TXT_51] = RenderViews::buildSelectDropdown('SERVICECENTRE_SET_SAVED_SEARCH', $listValues, $listDisplayValues, $settings['SERVICECENTRE_SET_SAVED_SEARCH']);
	$fields[APP_TXT_72] = RenderViews::buildTextInput('SERVICECENTRE_SET_RESULT_COUNT', $settings['SERVICECENTRE_SET_RESULT_COUNT']);
	$fields[APP_TXT_57] = RenderViews::buildSelectDropdown('SERVICECENTRE_SET_DEFAULT_SCREEN', array('portal', 'quick_launch'), array(APP_TXT_55, APP_TXT_56), $settings['SERVICECENTRE_SET_DEFAULT_SCREEN']);
	define('BODY_CONTENT', RenderViews::buildForm(
		APP_TXT_31,
		CONTROLLER_BASEURL . '&option=update_settings',
		$fields,
		[
			RenderViews::buildFormButton('submit', 'submit_button', TXT_74),
			RenderViews::buildFormButton('reset', 'reset', TXT_75),
		]
	));
	RenderViews::renderThemePage('main_page_content',  SET_THEME);
}

function updateSettings() {
	// remove so is not added to config file
	unset($_POST['submit_button'], $_POST['reset']);
	// Write config file based on form created in showFlowIQSettings
	$header = 'Service Centre settings file.';
	$configurationDirectory = SET_WRITEABLE_DIRECTORY . 'applications/servicecentre/configuration/';
	if (!is_dir($configurationDirectory)) {
		if (!mkdir($configurationDirectory, 0755, 1)) {
			echo 'ERROR: Could not create directory: ' . $configurationDirectory;
		}
	}
	$i = 0;
	foreach ($_POST as $key => $value) {
		if (stristr($key, 'item_type_id_')) {
			@$_POST['SERVICECENTRE_SET_ITEM_TYPE'] .= ($i == 0) ? $value : ',' . $value;
			unset($_POST[$key]);
		}
		$i++;
	}
	if (File::writeFileFromArray($configurationDirectory . 'servicecentre_settings.php', $header, $_POST)) {
		$message = TXT_201;
	} else {
		$message = TXT_203;
	}
	$html = RenderViews::showResponse($message, RenderViews::buildURL(CONTROLLER_BASEURL . '&option=settings', TXT_202, 'URL'));
	define('BODY_CONTENT', $html);
	define('HEADING', TXT_139);
	RenderViews::renderThemePage('main_page_content',  SET_THEME);
}

/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
	case 'show_announcement_item' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
		showAnnouncementItem($_GET['id']);
		break;
	case 'show_announcements' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
		showUserItems();
		break;
	case 'add_announcement' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
		addAnnouncement();
		break;
	case 'edit_announcement' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
		editAnnouncement($_GET['id']);
		break;
	case 'update_announcement' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
		updateAnnouncement();
		break;
	case 'delete_announcement' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 2);
		deleteAnnouncement($_GET['id']);
		break;
	case 'show_search' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
		showSearchItems($_GET['id'], $_SESSION['access_user_id'], $_GET['rss']);
		break;
	case 'settings' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 1);
		showServiceCentreSettings();
		break;
	case 'update_settings' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 0);
		updateSettings();
		break;
	case 'show_quick_launch' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
		showServiceCentreQuickLaunch();
		break;
	case 'show_portal' :
		RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
		showServiceCentreModules();
		break;
	default :
		echo showPublicItems();
}
?>
