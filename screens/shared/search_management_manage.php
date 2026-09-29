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

use Adlexone\support\Database;
use Adlexone\support\RenderViews;


/**
 *
 * This routine will double check that the ID's are for knowledgebase items and if they are update
 * the database to say that they have been returned
 *
 * @param $knowledgeIds
 * @return none
 */
function updateKnoweldgeCount($knowledgeIds)
{

    foreach ($knowledgeIds as $values) {
        if (substr($values, -1) == '*') {
            $values = substr($values, 0, -1);
            $validId = false;
            if (Database::exists('items', 'WHERE item_id =' . $values . ' AND item_type_id = ' . APP_COUNT_ITEM_TYPE)) {
                $validId = true;
            }
        } else {
            $validId = True;
        }
        $event_id = (isset($_GET['event_id'])) ? $_GET['event_id'] : '';
        $selectionCriteria = 'WHERE item_id =' . $values . ' AND event_id = "' . $event_id . '"';
        if ($validId == True) {
            if (Database::exists('system_log', $selectionCriteria)) {
                $sql = 'UPDATE ' . 'system_log SET event_counter = event_counter + 1 ' . $selectionCriteria;
            } else {
                unset($columnArray);
                $columnArray['item_id'] = $values;
                $columnArray['create_date'] = time();
                $columnArray['event_counter'] = 1;
                $columnArray['event_id'] = $event_id;
                $columnArray['security_id'] = $_SESSION['access_user_id'];
                Database::insert('system_log', $columnArray);
                unset($columnArray);
            }

        }
    }
}

/**
 * showQuickSearch()
 */
function showQuickSearch()
{
    $jsFieldNameArray = "['search_value']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . TXT_206 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";
    $fields = [
        TXT_682 => RenderViews::buildSelectDropdown('search_type', array('item_id', 'item_title', 'log_entries'), array(TXT_102, TXT_84, TXT_601), ''),
        TXT_683 => RenderViews::buildTextInput('search_value', ''),
        '' => RenderViews::buildHiddenInput('search', 'Search'),
    ];
    define('BODY_CONTENT', RenderViews::buildForm(
        RenderViews::applicationText('TXT_62', TXT_376),
        \Adlexone\Http\Router::continueUrl('search') . '&option=quick_search',
        $fields,
        [RenderViews::buildFormButton('submit', 'search', TXT_3, $javascript)]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}



/**
 * Simplified item listing that renders items as vertical cards.
 *
 * Parameters mirror the original showItems for compatibility.
 *
 * - Accepts:
 *   - $itemIDArray: array of ids, array of rows (with item_id), or a SQL string that Database::buildArray can resolve
 *   - $itemID: optional single id filter
 *   - $orderSQL: optional ORDER BY clause (pass column(s) only)
 *   - $userID, $savedSearch: kept for signature compatibility (not used here)
 *
 * Notes:
 * - IDs are coerced to integers before inclusion in the IN() list to prevent injection through IDs.
 * - This function focuses on readable output and simple, maintainable structure.
 */
function showItems($itemIDArray, $itemID = '', $orderSQL = '', $userID = '', $savedSearch = ''): void
{
    // Normalize input: if a SQL string was passed, build an array using Database helper
    if (is_string($itemIDArray) && trim($itemIDArray) !== '') {
        $built = Database::rows($itemIDArray);
    } else {
        $built = (array)$itemIDArray;
    }

    // Normalize to a flat list of integer IDs
    $ids = [];
    if (!empty($built)) {
        $first = $built[0] ?? null;
        if (is_array($first) && array_key_exists('item_id', $first)) {
            // Array of rows
            foreach ($built as $row) {
                $ids[] = (int)$row['item_id'];
            }
        } else {
            // Flat list
            foreach ($built as $v) {
                if ((string)$v !== '') {
                    $ids[] = (int)$v;
                }
            }
        }
    }

    // If a specific item ID was requested, restrict to it (only if present in the provided set)
    if ($itemID !== '') {
        $requested = (int)$itemID;
        if (in_array($requested, $ids, true)) {
            $ids = [$requested];
        } else {
            // Not permitted or not present -> no results
            $ids = [];
        }
    }

    $heading = defined('SEARCH_NAME')
        ? (string)SEARCH_NAME
        : (string)RenderViews::applicationText('TXT_1', (string)RenderViews::getLanguageConstant('LA_44', 'TXT_44'));

    if (empty($ids)) {
        define('BODY_CONTENT', RenderViews::buildVerticalCards([
            [
                'title' => $heading,
                'html' => RenderViews::buildRecordList([
                    'column' => RenderViews::getLanguageConstant('LA_84', 'TXT_84'),
                    'empty' => TXT_115,
                    'groups' => [['rows' => []]],
                ]),
            ],
        ]));
        RenderViews::renderThemePage('main_page_content', SET_THEME);
        return;
    }

    $ids = array_values(array_unique(array_map('intval', $ids)));
    $condition = 'WHERE item_id IN (' . implode(',', $ids) . ')';
    $orderClause = ($orderSQL !== '') ? ' ORDER BY ' . $orderSQL : ' ORDER BY item_id DESC';
    $columns = ['item_id', 'item_title', 'create_date', 'item_type_id'];
    $result = Database::select('items', $columns, $condition . $orderClause);

    $base = \Adlexone\Http\Router::continueUrl('items');
    $rows = [];
    foreach ($result as $row) {
        $itemId = (int)$row['item_id'];
        $rows[] = RenderViews::itemRecord(
            $row,
            $base . '&item=' . $itemId,
            RenderViews::itemActions($itemId, $base)
        );
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        [
            'title' => $heading,
            'html' => RenderViews::buildRecordList([
                'column' => RenderViews::getLanguageConstant('LA_84', 'TXT_84'),
                'columns' => RenderViews::itemListColumns(),
                'searchLabel' => TXT_3,
                'empty' => TXT_115,
                'groups' => [['rows' => $rows]],
            ]),
        ],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}


/**
 * Shows a list of items defined by item id, filtered by specific item where applicable
 *
 * @param integer $itemIDArray Item ID array
 * @param integer $itemID Item ID to show specifically - must exist in array
 * @param string $orderSQL Order by clause
 * @param $userID User ID
 */
function showItems1($itemIDArray, $itemID = '', $orderSQL = '',  $userID = '', $savedSearch='')
{
	if ((@$_GET['option'] != 'show_search_results')){
		unset($_SESSION['item_search_sql']);
	}

	$knowledgeArticlesArray = array();

	if (is_array($itemIDArray)){
		if ($_SESSION['access_role_id'] >1){ //Everyone except admins and global admins
			// Get all items by user assignment
			$sql = "SELECT item_id FROM items WHERE (user_security = '" . $_SESSION['access_user_id'] . "' OR creator_security = '" . $_SESSION['access_user_id'] . "')";
			if (Database::rows($sql)) {
				$userItemArray = Database::rows($sql);
			}
			// Get all items by group assignment
			$sql = "SELECT groups FROM group_members WHERE user_id = '" . $_SESSION['access_user_id'] . "'";
						$result = Database::rows($sql);
			$row = $result[0] ?? null;
			$groupArray = explode('}-{', $row['groups']);
			$i=0;
			foreach($groupArray as $group) {
				$sql = "SELECT item_id FROM items WHERE group_security LIKE '%}-{" . $group . "}-{%'";
								$result = Database::rows($sql);
				if (count($result) > 0) {
					$itemArray = Database::rows($sql);
					$groupItemArray = ($i == 0) ? $itemArray : array_merge($groupItemArray,$itemArray);
					$i++;
				}
			}
			if (!is_array(@$userItemArray)){
				$userItemArray[] = '';
			}
			if (!is_array(@$groupItemArray)){
				$groupItemArray[] = '';
			}
			$userItemArray = array_unique(array_merge($groupItemArray,$userItemArray));
		}else{
			// Get all items is is administrator (for searching only)
			$sql = "SELECT item_id FROM items";
			if (Database::rows($sql)) {
				$userItemArray = Database::rows($sql);
			}
		}
		// Get data from database
		$columnArray = array();
		$headTitle = array();
		$headFields = array();
		if ($savedSearch != '') {
			$savedSearch2 = substr($savedSearch,6);
			$orderSQL = (stripos($savedSearch2,"order by")) ? substr($savedSearch2,strripos($savedSearch2,"order by") + 8) : "";
			$savedSearch2 = substr($savedSearch2,0,strripos($savedSearch2,"from"));
			$tmpArray = explode(',',$savedSearch2);
			//Build saved searches heading
			foreach($tmpArray as $key => $value) {
				$tmpvars1 = explode(" ", $value);
				$columnArray[] = $tmpvars1[1];
				$headFields[] = "disp_" . $tmpvars1[1];
				switch(trim($value)) {
					case 'item_id':
						$headTitle[] = RenderViews::outputIfRoleAllowed('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_id\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_102', 'TXT_102') . '</strong></a>', $_SESSION['access_role_id'], 4);
						break;
					case 'item_title':
						$headTitle[] = RenderViews::outputIfRoleAllowed('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_title\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_84', 'TXT_84') . '</strong></a>', $_SESSION['access_role_id'], 4);
						break;
					case 'create_date':
						$headTitle[] = RenderViews::outputIfRoleAllowed('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'create_date\'; document.repform.submit()" class="URL"><strong>' . TXT_225 . '</strong></a>', $_SESSION['access_role_id'], 4);
						break;
					case 'item_type_id':
						$headTitle[] = RenderViews::outputIfRoleAllowed('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_type_id\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_226', 'TXT_226') . '</strong></a>', $_SESSION['access_role_id'], 4);
						break;

					case 'creator_security':
						$headTitle[] = RenderViews::outputIfRoleAllowed('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'creator_security\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_551', 'TXT_551') . '</strong></a>', $_SESSION['access_role_id'], 4);
						break;
					case 'user_security':
						$headTitle[] = RenderViews::outputIfRoleAllowed('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'user_security\'; document.repform.submit()" class="URL"><strong>' . RenderViews::getLanguageConstant('LA_269', 'TXT_269') . '</strong></a>', $_SESSION['access_role_id'], 4);
						break;


					default:
						$tmpvars = explode("_",$tmpvars1[1]);
						$headsql = "SELECT custom_field_name FROM custom_fields WHERE custom_field_id = " . $tmpvars[count($tmpvars) - 1];
												$headresult = Database::rows($headsql);
						$headTitle[] = RenderViews::secureOutput('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'custom_field_' . $tmpvars[count($tmpvars) - 1] . ' \'; document.repform.submit()" class="URL"><strong>' . ($headresult[0]['custom_field_name'] ?? '') . '</strong></a>', $_SESSION['access_role_id'], 4);
						unset($tmpvars1);
						unset($tmpvars);
				}
			}
		} else {
			//build POSTed search hading
			if (is_array($_POST)) {
				foreach($_POST as $key => $value) {
					if (substr($key,0,4)== "disp") {
						$columnArray[] = substr($key,5);
						$headFields[] = $key;
						if (substr($key,5,6) == "custom") {
							$tmpvars = explode("_",$key);
							$headsql = "SELECT custom_field_name FROM custom_fields WHERE custom_field_id = " . $tmpvars[count($tmpvars) - 1];
														$headresult = Database::rows($headsql);
							$headTitle[] = RenderViews::secureOutput('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'custom_field_' . $tmpvars[count($tmpvars) - 1] . '\'; document.repform.submit()" class="URL"><strong>' . ($headresult[0]['custom_field_name'] ?? '') . '</strong></a>', $_SESSION['access_role_id'], 4);
							unset($tmpvars);
						} else {
							switch($key) {
								case 'disp_item_id':
									$headTitle[] = RenderViews::secureOutput('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_id\'; document.repform.submit()" class="URL"><strong>' . RenderViews::languageAlias('LA_102', 'TXT_102') . '</strong></a>', $_SESSION['access_role_id'], 4);
									break;
								case 'disp_item_title':
									$headTitle[] = RenderViews::secureOutput('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_title\'; document.repform.submit()" class="URL"><strong>' . RenderViews::languageAlias('LA_84', 'TXT_84') . '</strong></a>', $_SESSION['access_role_id'], 4);
									break;
								case 'disp_create_date':
									$headTitle[] = RenderViews::secureOutput('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'create_date\'; document.repform.submit()" class="URL"><strong>' . TXT_225 . '</strong></a>', $_SESSION['access_role_id'], 4);
									break;
								case 'disp_item_type_id':
									$headTitle[] = RenderViews::secureOutput('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'item_type_id\'; document.repform.submit()" class="URL"><strong>' . RenderViews::languageAlias('LA_226', 'TXT_226') . '</strong></a>', $_SESSION['access_role_id'], 4);
									break;
								case 'disp_creator_security':
									$headTitle[] = RenderViews::secureOutput('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'creator_security\'; document.repform.submit()" class="URL"><strong>' . RenderViews::languageAlias('LA_551', 'TXT_551') . '</strong></a>', $_SESSION['access_role_id'], 4);
									break;
								case 'disp_user_security':
									$headTitle[] = RenderViews::secureOutput('<a href ="javascript:var fieldArray = document.getElementsByName(\'currsort\');fieldArray[0].value=\'user_security\'; document.repform.submit()" class="URL"><strong>' . RenderViews::languageAlias('LA_269', 'TXT_269') . '</strong></a>', $_SESSION['access_role_id'], 4);
									break;

									break;
							}
						}
					}
				}
			}
		}
//		// Override item id list if a specific id has been requested
//		if ($itemID != '') {
//			if (in_array($itemID,$userItemArray)){
//				$condition = "WHERE item_id = '$itemID'";
//			}else{
//				$condition = "WHERE item_id = ''";//don't return any, we aren't allowed to see it
//			}
//		} else {
//			$i = 0;
//			// handle empty arrays (i.e. no result returned)
//			if (!is_array($itemIDArray)) {
//				$condition = "WHERE item_id = ''";
//			} else {
//				foreach ($itemIDArray as $a) {
//					if ($i == 0) {
//						if (in_array($a,$userItemArray)){
//							$condition = "WHERE item_id = '$a'";
//							$i++;
//						}
//					} else {
//						if (in_array($a,$userItemArray)){
//							$condition .= " OR item_id = '$a'";
//							$i++;
//						}
//					}
//				}
//			}
//		}

        // Replace the existing item-id handling with this robust logic
        if ($itemID != '') {
            if (in_array($itemID, (array)$userItemArray)) {
                $condition = "WHERE item_id = '" . (int)$itemID . "'";
            } else {
                $condition = "WHERE item_id = ''"; // not allowed to see it
            }
        } else {
            // Ensure we have an array of IDs
            if (!is_array($itemIDArray) || count($itemIDArray) === 0) {
                $condition = "WHERE item_id = ''";
            } else {
                // normalize incoming ID arrays to flat integer lists
                $itemIDArray = is_array($itemIDArray) ? array_values(array_map('intval', $itemIDArray)) : [];
// Normalize $userItemArray which may be an array of rows (e.g. ['item_id'=>...]) or a flat list
                $userItemIds = [];
                if (isset($userItemArray) && is_array($userItemArray)) {
                    $first = reset($userItemArray);
                    if (is_array($first) && array_key_exists('item_id', $first)) {
                        // array of rows from Database::buildArray
                        $userItemIds = array_values(array_map('intval', array_column($userItemArray, 'item_id')));
                    } else {
                        // already a flat list
                        $userItemIds = array_values(array_map('intval', $userItemArray));
                    }
                }

// perform permission-aware filtering using the normalized lists
                if (isset($userItemIds) && is_array($userItemIds)) {
                    $allowed = array_values(array_intersect($itemIDArray, $userItemIds));
                } else {
                    $allowed = array_values($itemIDArray);
                }

                // If nothing remains after filtering -> return no rows
                if (empty($allowed)) {
                    $condition = "WHERE item_id = ''";
                } else {
                    // Sanitize ids as integers and build an IN() clause
                    $ids = array_map('intval', $allowed);
                    $condition = "WHERE item_id IN (" . implode(',', $ids) . ")";
                }
            }
        }
//		//If no condition exists set one to return no items
//		if (!isset($condition)){
//			$condition = "WHERE item_id = ''";
//		}
		if (!isset($_POST['pageset'])) {
			// First off get number of records before doing sort
			$countrecs = Database::count('items', $condition);
		} else {
			$countrecs = $_POST['countrecs'];
		}

        // normalize $countrecs to an integer (handle array returns from DB helpers)
        if (is_array($countrecs)) {
            $first = reset($countrecs);
            if (is_array($first)) {
                // common shape: array(0 => array('count(*)' => '123')) or similar
                $countrecs = isset($first['count(*)']) ? (int)$first['count(*)'] : (int)reset($first);
            } else {
                $countrecs = (int)$first;
            }
        } else {
            $countrecs = (int)$countrecs;
        }
		$countBoolean = (($countrecs / SET_ITEMS_PAGE) > 1);
		if ($orderSQL != '') {
			$condition .= " ORDER BY $orderSQL";
		}else{
			$condition .= " ORDER BY item_id DESC";
			$orderSQL = "item_id DESC";
		}
		if ($countBoolean){
			$condition .= " LIMIT " . SET_ITEMS_PAGE;
		}
		$result = Database::select('items', $columnArray, $condition);
		if (isset($_SESSION['item_search_sql'])){
			$origSQL = $_SESSION['item_search_sql'];
			$origSQL = str_replace("\'","'",$origSQL);
			$condition = '';
			if (isset($_POST['pageset']) && $_POST['pageset'] !=1) {
				$condition = (($_POST['pageset'] - 1) * SET_ITEMS_PAGE) . ",";
			}
			$condition .= SET_ITEMS_PAGE;
			if (strpos($origSQL,"LIMIT") > 0) {
				$origSQL = substr($origSQL,0,strpos($origSQL,'LIMIT')) . ' LIMIT ' . $condition;
			}
			if (isset($_POST['pageset'])) {
				$currsort = substr($origSQL,strpos($origSQL,'ORDER BY') + 9,strpos($origSQL,'LIMIT') - strpos($origSQL,'ORDER BY') - 9);
			} else {
				$currsort = substr($origSQL,strpos($origSQL,'ORDER BY') + 9,strlen($origSQL));
			}
		} else {
			$origSQL = $sql;
		}

		if (isset($_POST['currsort']) && trim($_POST['currsort'] != "")) {
			if ($_POST['currsort'] == substr($currsort,0,strpos($currsort," "))) {
				$newsort = (strpos($currsort,"DESC") == 0) ? $currsort . " DESC " : $_POST['currsort'] . " ";
			} else {
				$newsort = (strpos($currsort,"DESC") == 0) ? $_POST['currsort'] . " DESC " : $_POST['currsort'] . " ";
			}
			$origSQL = str_replace("ORDER BY " . $currsort,"ORDER BY " . $newsort,$origSQL);
		}

		// Create Table and populate with data (and formatting)
		// Set required style info
		$headHtml = "";
		if ($countrecs >= 1) {
			$tableHeadings = array();
			foreach($headTitle as $key => $value) {
				$tableHeadings[] = $value;
				$headHtml .= RenderViews::buildHiddenInput($headFields[$key],$headFields[$key]);
			}
			$fields[] = TXT_388;
		} else {
            $fields = array(RenderViews::getLanguageConstant('LA_102', 'TXT_102'),RenderViews::getLanguageConstant('LA_84', 'TXT_84'),TXT_225,RenderViews::getLanguageConstant('LA_226', 'TXT_226'),TXT_388);
		}

		$html = RenderViews::buildFormFieldsGrid($fields);
		$i = 0;
		$countUpdate = (defined('APP_COUNT_ITEM_TYPE')) ? '&event_id=view_item' : '';
		if (count($result) > 0) {
			$itemTypeID = '';
			$tableRows = '';
			foreach ($result as $row) {
				foreach ($columnArray as $value) {
					switch ($value){
						case 'item_id':
							$cellData[] = $row['item_id'];
							break;
						case 'item_title':
							if ($row['item_title'] == '') {
								$title = TXT_357;
							} else {
								$title = $row['item_title'];
							}
							$logEntry = (SET_LOG_ENTRY == 'yes') ? '&log_entry=yes' : '';
							$attachments = (SET_ATTACHMENTS == 'yes') ? '&attachments=yes' : '';
							$cellData[] = '<a href ="index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_item&item_id=' . $row['item_id'] . $logEntry . $attachments. $countUpdate . '" class="URL">' . $title . '</a>';
							break;
						case 'create_date':
							$cellData[] = date(SET_DATE_FORMAT, $row['create_date']);
							break;
						case 'item_type_id':
							if ($itemTypeID != $row['item_type_id']){  // We don't need to recheck as the last check was for the same item type id
								$tmpcolumnArray = array ('item_type_name');
								$condition = "WHERE item_type_id = '" . $row['item_type_id'] . "'";
								$itemTypeRow = Database::first('item_types', $tmpcolumnArray, $condition);
								$itemTypeName = $itemTypeRow['item_type_name']; //We set this so we can use it later if the next check is the same item type
								$cellData[] = $itemTypeName;
								$itemTypeID = $row['item_type_id'];
							} else {
								$cellData[] = $itemTypeName;
							}
							break;

						case 'creator_security':
							$creatorColumnArray = array ('user_name');
							$condition = "WHERE user_id = '" . $row['creator_security'] . "'";
							$creatorRow = Database::first('users', $creatorColumnArray, $condition);
							$cellData[] = $creatorRow['user_name'];
							break;

						case 'user_security':
							$userColumnArray = array ('user_name');
							$condition = "WHERE user_id = '" . $row['user_security'] . "'";
							$userRow = Database::first('users', $userColumnArray, $condition);
							$cellData[] = $userRow['user_name'];
							break;

						default:
							$urlColumnArray = array ('field_type','data');
							$condition = "WHERE custom_field_id = '" .    str_replace('custom_field_','',$value) . "'";
							$urlRow = Database::first('custom_fields', $urlColumnArray, $condition);
							if ($urlRow['field_type'] == 'URL'){
								if ($row[$value] != ''){
									$http = (!stristr($value,'http') AND !stristr($value,'https')) ? 'http://' : '';
									$cellData[] = RenderViews::url($http.$row[$value] , $row[$value], 'URL','','','_blank');
								}else{
									$cellData[] = $row[$value];
								}
							}elseif ($urlRow['field_type'] == 'dynamicURL'){
								if ($row[$value] != ''){
									$cellData[] = RenderViews::url(str_replace('<?php echo INSERT; ?>',$row[$value],$urlRow['data']),$row[$value],'URL','','','_blank');
								}else{
									$cellData[] = $row[$value];
								}
							}else{
								$cellData[] = $row[$value];
							}
					}
				}
				// Record items returned if Knowledgebase or unknown
				if (defined('APP_COUNT_ITEM_TYPE')) {
					if (isset($row['item_type_id']) && $row['item_type_id'] == APP_COUNT_ITEM_TYPE) {
						$knowledgeArticlesArray[] = $row['item_id'];
					}
					if (! isset($row['item_type_id'])) {
						$knowledgeArticlesArray[] = $row['item_id'] . '*';
					}
				}
				$actions = (string) RenderViews::outputIfRoleAllowed(
				    RenderViews::buildURL(
				        'index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=log_entry&item_id=' . $row['item_id'],
				        TXT_246
				    ),
				    $_SESSION['access_role_id'],
				    4
				);

				$actions .= (string) RenderViews::outputIfRoleAllowed(
				    ' - ' . RenderViews::buildURL(
				        'index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=show_attachments&item_id=' . $row['item_id'],
				        TXT_389
				    ),
				    $_SESSION['access_role_id'],
				    4
				);

				$actions .= (string) RenderViews::outputIfRoleAllowed(
				    ' - ' . RenderViews::buildURL(
				        'index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=change_security&item_id=' . $row['item_id'],
				        TXT_28
				    ),
				    $_SESSION['access_role_id'],
				    3
				);

				$actions .= (string) RenderViews::outputIfRoleAllowed(
				    ' - ' . RenderViews::buildURL(
				        'index.php?controller=full_page_view&option=print_item&item_id=' . $row['item_id'],
				        TXT_625,
				        '',      // spriteName
				        'URL',   // class
				        '',      // javascript
				        '_blank' // target
				    ),
				    $_SESSION['access_role_id'],
				    5
				);

				$actions .= (string) RenderViews::outputIfRoleAllowed(
				    ' - ' . RenderViews::buildURL(
				        'index.php?controller=' . $_GET['controller'] . '&subcontroller=item_management_manage&option=delete_item&item_id=' . $row['item_id'],
				        TXT_315,
				        '', // spriteName
				        'URL',
				        'onClick="return confirm(\'' . TXT_400 . '\')"'
				    ),
				    $_SESSION['access_role_id'],
				    2
				);
				$cellData[] = $actions;
				$formHTML = RenderViews::buildFormFieldsGrid($cellData);
				$i++;
				unset($cellData, $actions);
			}
		} else {
			$html .= RenderViews::buildFormFieldsGrid(array(TXT_115));
		}
		$html = RenderViews::buildStartForm(\Adlexone\Http\Router::continueUrl('search') . '&option=show_search_results','POST','form-horizontal');

		//RenderViews search results
		//$html .= RenderViews::tbStartTable('table table-striped table-hover');
		//$html .= RenderViews::tbTableHeadings($tableHeadings);
		$html .= $formHTML;
//		$html .= RenderViews::endTable();
		unset($tableRows,$cellData);
		//Display next/prev menu
		if ($countBoolean) {
			$paginationHTML = '<div class="pagination pagination-centered "><ul>';
			$pageset = (isset($_POST['pageset'])) ? $_POST['pageset'] : 1;
			if ($pageset > 1) {
				$paginationHTML .= RenderViews::outputIfRoleAllowed('<li><a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value--; document.repform.submit()">Prev</a></li>', $_SESSION['access_role_id'], 5);
			} else {
				$paginationHTML .= "";
			}
			$istart = max(1,($pageset - 3));
			$iend = max(10,$pageset + 3);
			$iend = ($iend > (int)(($countrecs / SET_ITEMS_PAGE) + 1)) ? (int)($countrecs / SET_ITEMS_PAGE) + 1 : $iend;
			if ($istart > 1 && $iend - $istart < 10) {
				$istart = max($iend - 10,1);
			}
			for ($i = $istart; $i <= $iend; $i++) {
				if ($i == $pageset) {
					$paginationHTML .= '<li class="active"><a href ="#">'. $i .'</a></li>';
				} else {
					$paginationHTML .= RenderViews::outputIfRoleAllowed('<li><a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value=' . $i . '; document.repform.submit()">' . $i . ' </a></li>', $_SESSION['access_role_id'], 5);
				}
			}
			if ($pageset == $iend) {
				$paginationHTML .=  "";
			} else {
				$paginationHTML .= RenderViews::outputIfRoleAllowed('<li><a href ="javascript:var fieldArray = document.getElementsByName(\'pageset\');fieldArray[0].value++; document.repform.submit()">Next</a></li>', $_SESSION['access_role_id'], 5);
			}
			$paginationHTML .= '</ul></div>';
			$html .= $paginationHTML;
			$html .= RenderViews::buildHiddenInput('pageset',$pageset);
		}
		$html .= RenderViews::buildHiddenInput('currsort','');
		//$html .= RenderViews::hiddenField('sess',htmlentities($origSQL,ENT_COMPAT, 'UTF-8'));
		$_SESSION['item_search_sql'] = $origSQL;
		$html .= RenderViews::buildHiddenInput('search',@$pageset);
		$html .= RenderViews::buildHiddenInput('countrecs',$countrecs);
		$html .= RenderViews::buildHiddenInput('search_type',$_GET['option']);
		$html .= $headHtml;
		$html .= '</form>';
	}else{
		$html = RenderViews::buildFormFieldsGrid([
			RenderViews::getLanguageConstant('LA_102', 'TXT_102') => htmlspecialchars(TXT_115, ENT_QUOTES, 'UTF-8'),
		]);

	}
	if (defined('APP_COUNT_ITEM_TYPE') && count($knowledgeArticlesArray) > 0) {
		updateKnoweldgeCount($knowledgeArticlesArray);
	}
	$heading = (defined('SEARCH_NAME')) ? SEARCH_NAME : RenderViews::languageAlias('LA_44', 'TXT_44');
    $bodyBlock[] = ['title' => $heading, 'html' => $html];
    $bodyContent = RenderViews::buildVerticalCards($bodyBlock);
	define('BODY_CONTENT', $bodyContent);
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}
function quickSearch()
{
    // Takes criteria from quick search field and returns items
    $_POST['disp_item_id'] = "disp_item_id";
    $_POST['disp_item_title'] = "disp_item_title";
    $_POST['disp_create_date'] = "disp_create_date";
    $_POST['disp_item_type_id'] = "disp_item_type_id";
    $sql = "";
    switch ($_POST['search_type']) {
        case 'item_id':
            $_POST['item_id'] = $_POST['search_value'];
            $_POST['id_operator'] = '=';
            break;
        case 'item_title':
            $_POST['item_title'] = '%' . $_POST['search_value'] . '%';
            $_POST['item_title_operator'] = 'LIKE';
            break;
        case 'log_entries':
            $sql = 'SELECT ' . 'items.item_id, item_title, ' . 'items.create_date, item_type_id FROM ' .
                'items JOIN ' . 'core_log ON ' . 'items.item_id = ' .
                "core_log.item_id WHERE log_text LIKE '%%" . $_POST['search_value'] . "%%'";
    }
    searchResults($sql);
}

/**
 * Wrapper function for template: search_item_search.html
 *
 * Creates a table with results from a search
 */
function searchResults($sql = '')
{

    // Handling accessing the page from option=show_search_results with no POSTed data
    if (empty($_POST['search']) and empty($_POST['new_favourite']) and @$_POST['item_type_id'] == '' and empty($_POST['search_type'])) {
        // Error messagae
        if (($_POST['search_type'] == 'quick_search' || $_POST['search_type'] = 'show_search_results') && $_POST['sess'] != '') {
            $_POST['search'] = 'log_entries';
        } else {
            RenderViews::buildResponse(TXT_548);
            return;
        }
    }

    // Handle item id filtering
if ((isset($_POST['item_type_id']) && ($_POST['item_type_id'] !== '' || $_POST['item_type_id'] === '0')) && empty($_POST['search']) && empty($_POST['new_favourite'])) {
            showAdvancedItemSearch($_POST['item_type_id']);
            return;
        }
    // Generate from form based values or pre defined sql
    $andOr = '';

    if ($sql == '') {
        $condition = 'WHERE';
        // Item types
        if (!empty($_POST['item_type_id'])) {
            $condition .= " item_type_id = '" . $_POST['item_type_id'] . "' ";
            $andOr = 'AND';
        }
        // Item ID
        if (!empty($_POST['item_id'])) {
            if ($_POST['id_operator'] == '=') {
                $condition .= $andOr . " item_id = '" . $_POST['item_id'] . "' ";
            } else {
                $condition .= $andOr . " item_id LIKE '%" . $_POST['item_id'] . "%' ";
            }
            $andOr = @$_POST['id_andor'];
        }
        // Hour Range
        if (!empty($_POST['hour_range'])) {
          $condition .= $andOr . " " . $_POST['hour_type'] . " " . html_entity_decode($_POST['hour_range_operator'], ENT_COMPAT, 'UTF-8') . " " . (time() - intval($_POST['hour_range'] * 60 * 60)) . " ";
          $andOr = $_POST['hour_range_andor'];
        }
        // Date Created 1
        if (!empty($_POST['date_1'])) {
            $date1Array = date_parse($_POST['date_1']);
            $time = mktime($date1Array['hour'], $date1Array['minute'], $date1Array['second'], $date1Array['month'], $date1Array['day'], $date1Array['year']);
            $condition .= $andOr . " " . $_POST['date_type_1'] . " " . html_entity_decode($_POST['date_operator_1'], ENT_COMPAT, 'UTF-8') . " '" . $time . "' ";
            $andOr = $_POST['date_andor_1'];
        }
        // Date Created 2
        if (!empty($_POST['date_2'])) {
            $date2Array = date_parse($_POST['date_2']);
            $time = mktime($date2Array['hour'], $date2Array['minute'], $date2Array['second'], $date2Array['month'], $date2Array['day'], $date2Array['year']);
            $condition .= $andOr . " " . $_POST['date_type_2'] . " " . html_entity_decode($_POST['date_operator_2'], ENT_COMPAT, 'UTF-8') . " '" . $time . "' ";
            $andOr = $_POST['date_andor_2'];
        }
        // Creator User
        if (!empty($_POST['creator_security'])) {
            $condition .= $andOr . " creator_security " . html_entity_decode($_POST['security_creators_operator'], ENT_COMPAT, 'UTF-8') . " '" . $_POST['creator_security'] . "' ";
            $andOr = $_POST['security_creators_andor'];
        }
        // Security User
        if (!empty($_POST['user_security'])) {
            $condition .= $andOr . " user_security " . html_entity_decode($_POST['security_users_operator'], ENT_COMPAT, 'UTF-8') . " '" . $_POST['user_security'] . "' ";
            $andOr = $_POST['security_users_andor'];
        }
        // Security Group
        if (!empty($_POST['security_groups'])) {
            if (html_entity_decode($_POST['security_groups_operator'], ENT_COMPAT, 'UTF-8') == '=') {
                $condition .= $andOr . " group_security LIKE '%-{" . $_POST['security_groups'] . "}-%' ";
            } else {
                $condition .= $andOr . " group_security NOT LIKE '%-{" . $_POST['security_groups'] . "}-%' ";
            }
            $andOr = $_POST['security_groups_andor'];
        }
        // Item Title
        if (!empty($_POST['item_title'])) {
            if ($_POST['item_title_operator'] == '=') {
                $condition .= $andOr . " item_title = '" . $_POST['item_title'] . "' ";
            } else {
                $condition .= $andOr . " item_title LIKE '%" . $_POST['item_title'] . "%' ";
            }
            $andOr = @$_POST['title_andor'];
        }

        // Custom Fields
        $i = 0;
        while ($i < @$_POST['custom_field_count']) {
            if (!empty($_POST['custom_field_value_' . $i]) or $_POST['custom_field_value_' . $i] != '') {
                switch (html_entity_decode($_POST['custom_field_operator_' . $i], ENT_COMPAT, 'UTF-8')) {
                    case '=':
                        $condition .= $andOr . " custom_field_" . $_POST['custom_field_' . $i] . " = '" . $_POST['custom_field_value_' . $i] . "' ";
                        break;
                    case 'LIKE':
                        $condition .= $andOr . " custom_field_" . $_POST['custom_field_' . $i] . " LIKE '%" . $_POST['custom_field_value_' . $i] . "%' ";
                        break;
                    case '<>':
                        $condition .= $andOr . " custom_field_" . $_POST['custom_field_' . $i] . " <> '" . $_POST['custom_field_value_' . $i] . "' ";
                        break;
                    case '>':
                        $condition .= $andOr . " custom_field_" . $_POST['custom_field_' . $i] . " > '" . $_POST['custom_field_value_' . $i] . "' ";
                        break;
                    case '<':
                        $condition .= $andOr . " custom_field_" . $_POST['custom_field_' . $i] . " < '" . $_POST['custom_field_value_' . $i] . "' ";
                        break;
                    default:
                } // switch
                $andOr = $_POST['custom_field_andor_' . $i];
            }
            $i++;
        }
        $columnArray = array();
        foreach ($_POST as $key => $value) {
            if (substr($key, 0, 4) == "disp") {
                $columnArray[] = substr($key, 5);
            }
        }
        $condition = ($condition == 'WHERE') ? '' : $condition;  // If condition is equal to where it means we have set no field values
        $sql = Database::sqlSelect('items', $columnArray, $condition);
    }

    // Execute sql query only if user is admin and sql query field is used
    if ((isset($_POST['sql_query']) and $_POST['sql_query'] != '') and $_SESSION['access_role_id'] == 0) {
        $sql = html_entity_decode($_POST['sql_query'], ENT_COMPAT, 'UTF-8');
    } else {
        if (isset($_SESSION['item_search_sql']) and (@$_GET['option'] == 'show_search_results')) {
            $sql = $_SESSION['item_search_sql'];
            $sql = str_replace("\'", "'", $sql);
            if (isset($_POST['pageset']) && $_POST['pageset'] != 1) {
                $condition = (($_POST['pageset'] - 1) * SET_ITEMS_PAGE) . ",";
            } else {
                $condition = "";
            }
            $condition .= SET_ITEMS_PAGE;
            if (strpos($sql, "LIMIT") > 0) {
                $sql = substr($sql, 0, strpos($sql, 'LIMIT')) . ' LIMIT ' . $condition;
            }
            if (isset($_POST['pageset'])) {
                $currsort = substr($sql, strpos($sql, 'ORDER BY') + 9, strpos($sql, 'LIMIT') - strpos($sql, 'ORDER BY') - 9);
            } else {
                $currsort = substr($sql, strpos($sql, 'ORDER BY') + 9, strlen($sql));
            }
        }
    }

    if (isset($_POST['sortOrder'])) {
        $newsort = substr($_POST['sort_field'], 5) . " ";
        $newsort .= ($_POST['sortOrder'] == "descending") ? "DESC" : "";
        $sql .= " ORDER BY " . $newsort;
    } else {
        $newsort = "";
        if (isset($_POST['currsort']) && trim($_POST['currsort'] != "")) {
            if (trim($_POST['currsort']) == trim(substr($currsort, 0, strpos($currsort, " ")))) {
                $newsort = (strpos($currsort, "DESC") == 0) ? $currsort . " DESC " : $_POST['currsort'] . " ";
            } else {
                $newsort = (strpos($currsort, "DESC") == 0) ? $_POST['currsort'] . " DESC " : $_POST['currsort'] . " ";
            }
            $sql = str_replace("ORDER BY " . $currsort, "ORDER BY " . $newsort, $sql);
        } else {
            $newsort = (isset($currsort)) ? $currsort : "";
        }
    }
    // Show search results or move to save search page
    if (isset($_POST['search'])) {
        $sql = str_replace('session_user',(string) $_SESSION['access_user_id'], $sql);
        showItems(Database::rows($sql), '', $newsort, $_SESSION['access_user_id']);
    } elseif (isset($_POST['new_favourite'])) {
        $sql = addslashes($sql);
        addSavedSearch($_SESSION['access_user_id'], $sql, $_POST['search_name'], $_POST['search_description'], $_POST['application'], $_POST['security']);
    } else {
//		echo RenderViews::showInformation(TXT_375, 'tcBorder', 'tdcHeading', 'tdc2', 'Yes', '', '', false);
//
//        if ($buildImage != '') {
//            $imagesFolder = THEME_PATH . $theme . '/images/';
//            $imageHTML = '<img src="' . $imagesFolder . $buildImage . '"> ';
//        }
        RenderViews::buildResponse(TXT_354, TXT_375);
        // $html .= RenderViews::tableData('2', '', 'left', '', $titleClass, array($imageHTML . '&nbsp;' . TXT_354), 'row');
        //$html .= RenderViews::tableData('', '', 'left', '', $contentClass, array($text), 'row');
        //  $html .= RenderViews::endTable();
//        if ($lineBreak == true) {
//            $html .= '<br />';
//        }

    }
}

function showSavedSearches($userID, $application = '')
{
    if ($_SESSION['access_role_id'] <= '1') {
        $applicationClause = ($application != '') ? " WHERE application = '" . $application . "'" : "";
        $sql = "SELECT * FROM saved_searches $applicationClause ORDER BY search_name ASC";
    } else {
        $applicationClause = ($application != '') ? " AND application = '" . $application . "'" : "";
        $sql = "SELECT * FROM saved_searches WHERE (user = '$userID' OR user = 'all') $applicationClause ORDER BY search_name ASC";
    }
        $result = Database::rows($sql);
    $base = \Adlexone\Http\Router::continueUrl('search');
    $rows = [];
    if (count($result) > 0) {
        foreach ($result as $row) {
            $href = $base . '&search=' . rawurlencode((string)$row['search_id']);
            $name = (string)$row['search_name'];
            if ($row['user'] == 'all') {
                $href .= '&global=1';
                $name .= ' (' . TXT_408 . ')';
            }
            $actions = [];
            $canDeleteGlobal = ($row['user'] == 'all' || $row['user'] == 'system') && (int)$_SESSION['access_role_id'] <= 1;
            $canDeleteOwn = (string)$row['user'] === (string)$_SESSION['access_user_id'];
            if ($canDeleteGlobal || $canDeleteOwn) {
                $deleteHref = $base . '&search=' . rawurlencode((string)$row['search_id']) . '&option=delete_saved_search';
                if ($canDeleteGlobal) {
                    $deleteHref .= '&scope=global';
                }
                $actions[] = [
                    'href' => $deleteHref,
                    'label' => TXT_315,
                    'tone' => 'danger',
                    'confirm' => TXT_400,
                ];
            }
            if ((int)$_SESSION['access_role_id'] === 0) {
                $actions[] = [
                    'href' => $base . '&search=' . rawurlencode((string)$row['search_id']) . '&option=edit_saved_search',
                    'label' => TXT_626,
                ];
            }
            $rows[] = [
                'name' => $name,
                'href' => $href,
                'cells' => [
                    'description' => (string)($row['search_description'] ?? ''),
                ],
                'actions' => $actions,
            ];
        }
    }
    $toolbar = '';
    if (defined('APPLICATION_SLUG') && (string) APPLICATION_SLUG === 'service-centre') {
        $itemType = defined('SERVICECENTRE_SET_ITEM_TYPE') ? rawurlencode((string)SERVICECENTRE_SET_ITEM_TYPE) : '';
        $toolbar = '<div class="record-list__tools">'
            . RenderViews::buildURL($base . '&option=show_quick_search', APP_SC_TXT_62, '', 'btn btn--primary btn--sm')
            . RenderViews::buildURL($base . '&option=show_item_search&item_types=' . $itemType, APP_SC_TXT_61, '', 'btn btn--sm')
            . '</div>';
    }
    $list = RenderViews::buildRecordList([
        'column' => TXT_151,
        'columns' => [
            ['key' => 'description', 'label' => TXT_153, 'wrap' => true],
        ],
        'searchLabel' => TXT_3,
        'empty' => TXT_115,
        'toolbar' => $toolbar,
        'groups' => [['rows' => $rows]],
    ]);
    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        [
            'title' => RenderViews::applicationText('TXT_60', TXT_313),
            'html' => $list,
        ],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function addSavedSearch($userID, $savedSearchSQL, $searchName, $searchDescription, $application, $security)
{
    // Check for duplicate and respond with a return message if exists
    $sql = "SELECT user FROM saved_searches WHERE search_name = '$searchName' AND user = '$userID'";
        $result = Database::rows($sql);
    if (count($result) > 0) {
        RenderViews::buildResponse(TXT_311, RenderViews::buildURL('javascript: history.go(-1)', TXT_306, 'URL'));
        return;
    }
    if ($searchName == '') {
        RenderViews::buildResponse(TXT_367, RenderViews::buildURL('javascript: history.go(-1)', TXT_306, 'URL'));
        return;
    }
    $columnArray['search_id'] = Database::newID('saved_searches', 'search_id');
        if ($security == 'all') {
            $columnArray['user'] = 'all';
        } elseif ($security == 'mine') {
            $columnArray['user'] = $userID;
        } else {
            $columnArray['user'] = 'system';
        }
        $columnArray['search_name'] = $searchName;
        $columnArray['search_description'] = $searchDescription;
        $columnArray['saved_search_sql'] = $savedSearchSQL;
        $columnArray['application'] = $application;
        Database::insert('saved_searches', $columnArray);
        // Success messagae
    RenderViews::buildResponse(TXT_25, RenderViews::buildURL('javascript: history.go(-1)', TXT_404, 'URL'));
}

function savedSearch($searchID, $userID, $global = false, $rss = false)
{
    unset($_SESSION['item_search_sql']);

    // Show item search based based on stored sql
    if (@$_GET['global'] == true) {
        $sql = "SELECT * FROM saved_searches WHERE search_id = '$searchID' AND user = 'all'";
    } elseif ($_SESSION['access_role_id'] <= 2) {
        $sql = "SELECT * FROM saved_searches WHERE search_id = '$searchID'";
    } else {
        $sql = "SELECT * FROM saved_searches WHERE search_id = '$searchID' AND (user = 'system' OR user = 'all' OR user = '$userID')";
    }
        $result = Database::rows($sql);
    $row = $result[0] ?? null;

    if ($rss == false) {
        // Set title constant for language alias in showitems function
        define('SEARCH_NAME', $row['search_name']);

        // Replace session_user placeholder and build result array
        $savedSql = str_replace('session_user', (string)$_SESSION['access_user_id'], $row['saved_search_sql']);
        $built = Database::rows($savedSql);

        // Normalize to flat list of item IDs when buildArray returned rows
        if (is_array($built) && count($built) > 0 && is_array($built[0])) {
            $itemIDs = array_values(array_filter(array_column($built, 'item_id'), 'strlen'));
        } elseif (is_array($built)) {
            $itemIDs = $built;
        } else {
            $itemIDs = [];
        }

        showItems($itemIDs, '', 'item_id DESC', $_SESSION['access_user_id'], $row['saved_search_sql']);
    } else {
        //Prep for output of XML RSS feed
        ob_clean();
        $itemArray = Database::rows($row['saved_search_sql']);
        foreach ($itemArray as $id) {
            $sql = "SELECT item_id, item_title FROM items WHERE item_id = '$id'";
                        $result = Database::rows($sql);
            $row = $result[0] ?? null;
            $linkArray[] = RenderViews::buildURL('index.php?controller=' . $_GET['controller'] . '&subcontroller=search_management_manage&option=saved_search&id=' . $row['item_id'], '', 'URL');
            $titleArray[] = $row['item_title'];
        }
        XML::rssFeed(SET_DATE_FORMAT, $row['search_name'], $row['search_description'], 'www.oneorzero.com', $titleArray, $linkArray);
    }
}

function deleteSavedSearch($searchID, $userID, $scope)
{
    // Delete favourite search
    if ($scope == 'global' and $_SESSION['access_role_id'] <= 1) {
        $sql = "DELETE FROM saved_searches WHERE search_id = '$searchID'";
    } else {
        $sql = "DELETE FROM saved_searches WHERE search_id = '$searchID' AND user = '$userID'";
    }
    Database::run($sql);
    showSavedSearches($userID);
}

function showItemTypeMenu($filter = '')
{
    if (is_array($filter)) {
        $i = 0;
        foreach ($filter as $id) {
            // Remove special characters
            if ($i == 0) {
                $condition = "WHERE item_type_id = '$id'";
            } else {
                $condition .= " OR item_type_id = '$id'";
            }
            $i++;
        }
    } elseif ($filter != '' and $filter != '0') {
        $condition = "WHERE item_type_id = '$filter'";
    } else {
        $condition = '';
    }
    $columnArray = array('item_type_id', 'item_type_name');
    $result = Database::select('item_types', $columnArray, $condition);
    if ($filter != '' and $filter != '0') {
        $i = 0;
        foreach ($result as $row) {
            $listValues[$i] = $row['item_type_id'];
            $listDisplayValues[$i] = $row['item_type_name'];
            $i++;
        }
        $listValues[$i] = '0';
        $listDisplayValues[$i] = TXT_100;
    } else {
        // Set the selected option to 'All item definitions'
        $i = 0;
        $listValues[0] = '';
        $listDisplayValues[0] = TXT_100;
        $i = 1;
        // Display all item types or only a single item type
        foreach ($result as $row) {
            $listValues[$i] = $row['item_type_id'];
            $listDisplayValues[$i] = $row['item_type_name'];
            $i++;
        }
    }
    // Create the list box
    return RenderViews::buildSelectDropdown('item_type_id', $listValues, $listDisplayValues, @$itemTypeID, 'onChange="document.itemSearch.submit();"');
}

function showSecurityGroups()
{
    // Get security groups from database
    $columnArray = array('group_id', 'group_name');
    $condition = 'ORDER BY group_name';
    $result = Database::select('groups', $columnArray, $condition);
    // Set the selected option to 'All item definitions'
    $listValues[0] = '';
    $listDisplayValues[0] = TXT_104;
    $i = 1;
    foreach ($result as $row) {
        $listValues[$i] = $row['group_id'];
        $listDisplayValues[$i] = $row['group_name'];
        $i++;
    }
    // Create the list box
    return RenderViews::buildSelectDropdown('security_groups', $listValues, $listDisplayValues, '');
}

function showCustomFieldsAsList($itemTypeID = '', $fieldData = '', $dispRows = '')
{
    $condition = '';
    if ($itemTypeID != '' and $itemTypeID != '0') {
        $columnArray = array('custom_field_id');
        if (is_array($itemTypeID)) {
            $condition = "WHERE ";
            foreach ($itemTypeID as $value) {
                $condition .= "item_type_id = " . $value . " OR ";
            }
            $condition = substr($condition, 0, -4);
        } else {
            $condition = "WHERE item_type_id = $itemTypeID";
        }
        $result = Database::select('item_type_custom_fields', $columnArray, $condition);
        $i = 0;
        $condition = "";
        foreach ($result as $row) {
            if ($i == 0) {
                $condition = "WHERE custom_field_id = '" . $row['custom_field_id'] . "'";
            } else {
                $condition .= " OR custom_field_id = '" . $row['custom_field_id'] . "'";
            }
            $i++;
        }
    }
    // Get custom fields from database
    $columnArray = array('custom_field_id', 'custom_field_name');
    if ($condition != '') {
        $condition .= "AND field_type NOT LIKE 'worker%' AND field_type <> 'fieldSeparator' ORDER BY custom_field_name ASC";
    } else {
        $condition = "WHERE field_type NOT LIKE 'worker%' AND field_type <> 'fieldSeparator' ORDER BY custom_field_name ASC";
    }
    $result = Database::select('custom_fields', $columnArray, $condition);
    // Build list based on the defined amount of custom field lists to display
    $html = '';
    foreach ($result as $row) {
        $fieldData[] = $row[1];
        $fieldData[] = RenderViews::buildCheckBox('disp_custom_field_' . $row[0], false, true, 'form-control');
        $fieldData[] = RenderViews::buildRadioButton('sort_field', 'sort_custom_field_' . $row[0], '', '');
        if (count($fieldData) == 9) {
            $dispRows .= '<div class="group-security-list">' . implode('', $fieldData) . '</div>';
            unset($fieldData);
        }
    }
    if (isset($fieldData) && is_array($fieldData)) {
        $dispRows .= '<div class="group-security-list">' . implode('', $fieldData) . '</div>';
    }

    return $dispRows;
}

/**
 * Renders the custom fields selection and input controls for advanced search.
 *
 * Uses the new content rendering methods for a modern, grid-based layout.
 *
 * @param string|array $itemTypeID Optional item type filter(s).
 * @return string HTML for the custom fields section.
 */
function showCustomFields($itemTypeID = '')
{
        $advanced = Database::rows("SELECT user_id FROM users WHERE user_id = '" . (int)$_SESSION['access_user_id'] . "' AND settings LIKE '%{SHOW-HIDE=TRUE}%'");
    if (count($advanced) === 0) {
        return '';
    }

    $ids = [];
    $idList = is_array($itemTypeID) ? $itemTypeID : [$itemTypeID];
    $idList = array_values(array_filter(array_map('intval', $idList)));
    if ($idList !== [] && !in_array(0, $idList, true)) {
        foreach (Database::select('item_type_custom_fields', ['custom_field_id'], 'WHERE item_type_id IN (' . implode(',', $idList) . ')') as $row) {
            $ids[] = (int)$row['custom_field_id'];
        }
    }

    $condition = "WHERE field_type NOT LIKE 'worker%' AND field_type <> 'fieldSeparator'";
    if ($ids !== []) {
        $condition .= ' AND custom_field_id IN (' . implode(',', array_unique($ids)) . ')';
    }
    $condition .= ' ORDER BY custom_field_name ASC';
    $result = Database::select('custom_fields', ['custom_field_id', 'custom_field_name'], $condition);
    $fieldRows = [];
    foreach ($result as $row) {
        $fieldRows[] = $row;
    }
    if ($fieldRows === []) {
        return '';
    }

    $html = '';
    $count = (int)SET_CUSTOM_FIELDS_IN_SEARCH;
    for ($a = 0; $a < $count; $a++) {
        $listValues = [''];
        $listDisplayValues = [TXT_108];
        foreach ($fieldRows as $row) {
            $listValues[] = $row['custom_field_id'];
            $listDisplayValues[] = $row['custom_field_name'];
        }
        $html .= searchControlRow(
            RenderViews::buildSelectDropdown('custom_field_' . $a, $listValues, $listDisplayValues, ''),
            RenderViews::buildSelectDropdown('custom_field_operator_' . $a, ['=', 'LIKE', '<>', '>', '<'], [TXT_418, TXT_112, TXT_318, TXT_364, TXT_365], '='),
            RenderViews::buildTextInput('custom_field_value_' . $a, ''),
            RenderViews::buildSelectDropdown('custom_field_andor_' . $a, ['AND', 'OR'], [TXT_109, TXT_110], 'AND')
        );
    }
    $html .= RenderViews::buildHiddenInput('custom_field_count', (string)$count);

    return $html;
}

function searchControlRow(string ...$controls): string
{
    return '<div class="criteria-row">' . implode('', $controls) . '</div>';
}

function searchApplicationMenu(string $selected): string
{
    $root = SET_INSTALL_PATH . 'app/Http/Controllers/Applications/';
    $names = [];
    $urls = [];
    if (is_dir($root)) {
        foreach (scandir($root) ?: [] as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $xmlFile = $root . $dir . '/' . $dir . '.xml';
            if (!is_file($xmlFile)) {
                continue;
            }
            $xml = simplexml_load_file($xmlFile);
            if ($xml === false) {
                continue;
            }
            $name = trim((string)($xml->name ?? ''));
            $base = trim((string)($xml->subcontroller->base_url ?? ''));
            if ($name === '' || $base === '') {
                continue;
            }
            if (defined($name)) {
                $name = (string)constant($name);
            } elseif (str_starts_with($name, 'OOZ_') && defined(substr($name, 4))) {
                $name = (string)constant(substr($name, 4));
            }
            $names[] = $name;
            $urls[] = $base;
        }
    }
    foreach (\Adlexone\Application\ApplicationStore::searchScopes() as [$scope, $label]) {
        if (!in_array($scope, $urls, true)) {
            $names[] = $label;
            $urls[] = $scope;
        }
    }

    return RenderViews::buildSelectDropdown('application', $urls, $names, $selected);
}

/**
 * Displays the advanced item search form.
 *
 * This function generates an advanced search form for items, allowing users to filter
 * and sort items based on various criteria such as item types, system criteria, date ranges,
 * custom fields, and more. The form is divided into sections for better organization and
 * uses the `RenderViews` utility for rendering form elements.
 *
 * @param string|array $filter Optional filter criteria for item types.
 * @return void
 */
function showAdvancedItemSearch($filter = '')
{
    // Clear previous search SQL if not showing search results
    if ((@$_GET['option'] != 'show_search_results')) {
        unset($_SESSION['item_search_sql']);
    }

    // Define operator and logical values for dropdowns
    $operatorValues = array('=', 'LIKE', '<>');
    $operatorDisplayValues = array(TXT_81, TXT_112, TXT_318);
    $andOrValues = array('AND', 'OR');
    $andOrDisplayValues = array(TXT_109, TXT_110);
    $eventId = (isset($_GET['event_id'])) ? '&event_id=' . $_GET['event_id'] : '';

    $fields = [];
    $fields[TXT_99] = showItemTypeMenu($filter);
    $fields[RenderViews::getLanguageConstant('LA_102', 'TXT_102')] = searchControlRow(
        RenderViews::buildSelectDropdown('id_operator', $operatorValues, $operatorDisplayValues, ''),
        RenderViews::buildTextInput('item_id', ''),
        RenderViews::buildSelectDropdown('id_andor', $andOrValues, $andOrDisplayValues, '')
    );
    $fields[RenderViews::getLanguageConstant('LA_84', 'TXT_84')] = searchControlRow(
        RenderViews::buildSelectDropdown('item_title_operator', $operatorValues, $operatorDisplayValues, ''),
        RenderViews::buildTextInput('item_title', ''),
        RenderViews::buildSelectDropdown('title_andor', $andOrValues, $andOrDisplayValues, '')
    );

    // Populate users dropdown
    $sql = "select user_id, user_name FROM users ORDER BY user_name ASC";
        $result = Database::rows($sql);
    $listValues = ['' => TXT_105, 'session_user' => TXT_600];
    foreach ($result as $row) {
        $listValues[$row['user_id']] = $row['user_name'];
    }

    // Add user-related fields to system criteria
    $fields[RenderViews::getLanguageConstant('LA_551', 'TXT_551')] = searchControlRow(
        RenderViews::buildSelectDropdown('security_creators_operator', $operatorValues, $operatorDisplayValues, ''),
        RenderViews::buildSelectDropdown('creator_security', array_keys($listValues), array_values($listValues), ''),
        RenderViews::buildSelectDropdown('security_creators_andor', $andOrValues, $andOrDisplayValues, '')
    );
    $fields[RenderViews::getLanguageConstant('LA_269', 'TXT_269')] = searchControlRow(
        RenderViews::buildSelectDropdown('security_users_operator', $operatorValues, $operatorDisplayValues, ''),
        RenderViews::buildSelectDropdown('user_security', array_keys($listValues), array_values($listValues), ''),
        RenderViews::buildSelectDropdown('security_users_andor', $andOrValues, $andOrDisplayValues, '')
    );
    $fields[TXT_79] = searchControlRow(
        RenderViews::buildSelectDropdown('security_groups_operator', array('=', '<>'), array(TXT_81, TXT_318), ''),
        showSecurityGroups(),
        RenderViews::buildSelectDropdown('security_groups_andor', $andOrValues, $andOrDisplayValues, '')
    );

    $fields[TXT_406] = searchControlRow(
        RenderViews::buildSelectDropdown('hour_type', array('create_date', 'core_log_updated'), array(TXT_103, TXT_420), ''),
        RenderViews::buildSelectDropdown('hour_range_operator', array('<=', '=', '>='), array(TXT_405, TXT_81, TXT_111), '>='),
        RenderViews::buildTextInput('hour_range', '', '30'),
        RenderViews::buildSelectDropdown('hour_range_andor', $andOrValues, $andOrDisplayValues, '')
    );
    $fields[TXT_103 . ' 1'] = searchControlRow(
        RenderViews::buildSelectDropdown('date_type_1', array('create_date', 'core_log_updated'), array(TXT_103, TXT_420), ''),
        RenderViews::buildSelectDropdown('date_operator_1', array('<=', '=', '>='), array(TXT_405, TXT_81, TXT_111), '>='),
        RenderViews::buildTextInput('date_1', ''),
        RenderViews::buildSelectDropdown('date_andor_1', $andOrValues, $andOrDisplayValues, '')
    );
    $fields[TXT_103 . ' 2'] = searchControlRow(
        RenderViews::buildSelectDropdown('date_type_2', array('create_date', 'core_log_updated'), array(TXT_103, TXT_420), ''),
        RenderViews::buildSelectDropdown('date_operator_2', array('<=', '=', '>='), array(TXT_405, TXT_81, TXT_111), '<='),
        RenderViews::buildTextInput('date_2', ''),
        RenderViews::buildSelectDropdown('date_andor_2', $andOrValues, $andOrDisplayValues, '')
    );
    $fields[TXT_407] = '<p class="field-note">dd-mm-yyyy hh:mm:ss</p>';

    $postedItemTypeID = (isset($_POST['item_type_id'])) ? $_POST['item_type_id'] : $filter;
    $customFields = showCustomFields($postedItemTypeID);
    if ($customFields !== '') {
        $fields[TXT_106] = $customFields;
    }

    $fields[TXT_258] = '<div class="choice-list__sort">'
        . '<label class="checkbox">' . RenderViews::buildRadioButton('sortOrder', 'ascending', 'ascending', '') . ' ' . htmlspecialchars(TXT_533, ENT_QUOTES, 'UTF-8') . '</label>'
        . '<label class="checkbox">' . RenderViews::buildRadioButton('sortOrder', 'descending', '', '') . ' ' . htmlspecialchars(TXT_534, ENT_QUOTES, 'UTF-8') . '</label>'
        . '</div>';

    $displayChoices = [
        [RenderViews::getLanguageConstant('LA_102', 'TXT_102'), 'spec_item_id', 'disp_item_id', 'sort_item_id', true],
        [RenderViews::getLanguageConstant('LA_84', 'TXT_84'), 'spec_item_title', 'disp_item_title', 'sort_item_title', false],
        [TXT_225, 'disp_create_date', '', 'sort_create_date', false],
    ];
    $choiceHtml = '<div class="choice-list">';
    foreach ($displayChoices as [$label, $checkName, $hiddenName, $sortValue, $sortChecked]) {
        $choiceHtml .= '<div class="choice-list__row">'
            . RenderViews::buildCheckBox($checkName, 'true', 'true', 'checkbox', (string)$label)
            . ($hiddenName !== '' ? RenderViews::buildHiddenInput($hiddenName, $hiddenName) : '')
            . '<span class="choice-list__sort">' . RenderViews::buildRadioButton('sort_field', $sortValue, $sortChecked ? $sortValue : '', '', 'aria-label="' . htmlspecialchars(TXT_258, ENT_QUOTES, 'UTF-8') . '"')
            . htmlspecialchars(TXT_258, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</div>';
    }
    $choiceHtml .= '</div>';
    $fields[TXT_527] = $choiceHtml;

    $fields[RenderViews::getLanguageConstant('LA_310', 'TXT_310')] = RenderViews::buildTextInput('search_name', '');
    $fields[RenderViews::getLanguageConstant('LA_312', 'TXT_312')] = RenderViews::buildTextArea('search_description', '', SET_FORM_FIELD_HEIGHT);
    if ($_SESSION['access_role_id'] <= '1') {
        $fields[TXT_403] = RenderViews::buildSelectDropdown('security', array('mine', 'all', 'system'), array(TXT_402, TXT_401, TXT_482), '1');
    } else {
        $fields[''] = RenderViews::buildHiddenInput('security', 'mine');
    }

    $fields[TXT_350] = searchApplicationMenu((string)($_GET['controller'] ?? ''));

    if ($_SESSION['access_role_id'] == 0) {
        $fields[TXT_421] = RenderViews::buildTextArea('sql_query', '', SET_FORM_FIELD_HEIGHT);
    }

    $jsFieldNameArray = "['search_name']";
    $jsTestTypeArray = "['']";
    $jsErrorMsgArray = "['']";
    $jsRequiredMsgArray = "['" . TXT_367 . "']";
    $jsRequiredArray = "[true]";
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";

    define('BODY_CONTENT', RenderViews::buildForm(
        RenderViews::applicationText('TXT_61', (string)RenderViews::getLanguageConstant('LA_96', 'TXT_96')),
        \Adlexone\Http\Router::continueUrl('search') . '&option=show_search_results' . $eventId,
        $fields,
        [
            RenderViews::buildFormButton('submit', 'search', TXT_3),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
            RenderViews::buildFormButton('submit', 'new_favourite', TXT_309, $javascript),
        ],
        ['name' => 'itemSearch', 'id' => 'itemSearch']
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function editSavedSearch($id)
{
    $id = (int) $id;
    $columnArray = ['search_name', 'saved_search_sql'];
    $condition = "WHERE search_id = '" . $id . "'";
    $row = Database::first('saved_searches', $columnArray, $condition);

    if (!$row) {
        // No record found — show a friendly message
       RenderViews::buildResponse(TXT_628,TXT_115);
        return;
    }

    $action = \Adlexone\Http\Router::continueUrl('search') . '&option=update_saved_search&id=' . $id;
    $fields = [
        TXT_627 => RenderViews::buildTextInput('search_name', $row['search_name']),
        TXT_628 => RenderViews::buildTextArea('saved_search_sql', $row['saved_search_sql'], SET_FORM_FIELD_HEIGHT),
    ];
    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_628 . ' - ' . $row['search_name'],
        $action,
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_74),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}


function updateSavedSearch($id)
{
    $columnArray['saved_search_sql'] = html_entity_decode($_POST['saved_search_sql'], ENT_COMPAT, 'UTF-8');
    $columnArray['search_name'] = html_entity_decode($_POST['search_name'], ENT_COMPAT, 'UTF-8');
    $condition = "WHERE search_id = '" . $id . "'";
    Database::update('saved_searches', $columnArray, $condition);
    // Success messagae
    RenderViews::buildResponse(TXT_628,TXT_629);
}

/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
    case 'show_item_search' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        $itemTypes = $_GET['item_types'] ?? '';
        $filter = (stristr($itemTypes, ',') !== false) ? explode(',', $itemTypes) : $itemTypes;
        showAdvancedItemSearch($filter);
        break;
    case 'show_search_results' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        searchResults();
        break;
    case 'show_saved_searches' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        $application = (string)($_GET['application'] ?? '');
        if ($application === '' && str_starts_with((string)($_GET['controller'] ?? ''), 'app_')) {
            $application = (string)$_GET['controller'];
        }
        showSavedSearches($_SESSION['access_user_id'], $application);
        break;
    case 'saved_search' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        savedSearch($_GET['id'], $_SESSION['access_user_id'], @$_GET['global']);
        break;
    case 'delete_saved_search' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        deleteSavedSearch($_GET['id'], $_SESSION['access_user_id'], @$_GET['scope']);
        break;
    case 'quick_search' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        quickSearch();
        break;
    case 'show_quick_search' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        showQuickSearch();
        break;
    case 'edit_saved_search' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 0);
        editSavedSearch($_GET['id']);
        break;
    case 'update_saved_search' :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 0);
        updateSavedSearch($_GET['id']);
        break;
    default :
        RenderViews::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
        showQuickSearch();
        break;
}

?>