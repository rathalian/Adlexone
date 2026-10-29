<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Database\RecordListQuery;
use Adlexone\Auth\Permission;
use Adlexone\Application\ApplicationAccess;
use Adlexone\Application\ApplicationSettings;
use Adlexone\support\Database;
use Adlexone\support\RssFeed;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Http\Router;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Text\Wording;
use Adlexone\Html\Html;

/**
 * Screen formerly loaded from legacy/.
 */
final class SearchScreen
{
    public static function open(): void
    {
        if (Page::current()->inApplication()) {
            ApplicationAccess::requireUse();
        } elseif (!Access::can(Permission::ADMIN_ITEMS)) {
            Access::deny();
        }
        if (!Page::current()->inApplication()) {
            Page::current()->setCrumbs(\Adlexone\Html\Breadcrumb::trail([['label' => 'Search']]));
        }














































        /**
         * Logic to render the appropriate template or call wrapper functions
         * Option is captured from the value selected via a hyperlink
         */
        $option = Request::query('option');
        switch ($option) {
            case 'show_item_search' :
            case 'show_search_results' :
                if (Page::current()->inApplication()) {
                    Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                    ApplicationSearch::open();
                    break;
                }
                if ($option === 'show_search_results') {
                    Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                    self::searchResults();
                    break;
                }
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                $itemTypes = preg_replace('/[^0-9,]/', '', Request::query('item_types')) ?? '';
                $filter = (stristr($itemTypes, ',') !== false) ? explode(',', $itemTypes) : $itemTypes;
                self::showAdvancedItemSearch($filter);
                break;
            case 'show_saved_searches' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                $application = Request::query('application');
                $controller = Request::query('controller');
                if ($application === '' && str_starts_with($controller, 'app_')) {
                    $application = $controller;
                }
                self::showSavedSearches($_SESSION['access_user_id'], $application);
                break;
            case 'saved_search' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                self::savedSearch($_GET['id'], $_SESSION['access_user_id'], Request::query('global'));
                break;
            case 'delete_saved_search' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                self::deleteSavedSearch($_GET['id'], $_SESSION['access_user_id'], Request::query('scope'));
                break;
            case 'quick_search' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                self::quickSearch();
                break;
            case 'show_quick_search' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                self::showQuickSearch();
                break;
            case 'edit_saved_search' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 0);
                self::editSavedSearch($_GET['id']);
                break;
            case 'update_saved_search' :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 0);
                self::updateSavedSearch($_GET['id']);
                break;
            default :
                Access::terminateIfRoleNotAllowed($_SESSION['access_role_id'], 5);
                self::showQuickSearch();
                break;
        }
    }
    /**
     * @return list<int>
     */
    public static function applicationSearchTypeIds(): array
    {
        if (!Page::current()->inApplication()) {
            return [];
        }
        return ApplicationSettings::allowedItemTypeIds(Page::current()->applicationSlug());
    }

    /**
     * Force a type filter for the current app when the caller did not supply one.
     *
     * @param mixed $filter
     * @return mixed
     */
    public static function constrainSearchTypeFilter($filter)
    {
        $allowed = self::applicationSearchTypeIds();
        if ($allowed === []) {
            if (is_array($filter)) {
                return array_values(array_map('intval', $filter));
            }

            return preg_replace('/[^0-9]/', '', (string) $filter) ?? '';
        }
        if (is_array($filter)) {
            $ids = array_values(array_filter(array_map('intval', $filter), static fn (int $id): bool => in_array($id, $allowed, true)));
            return $ids !== [] ? $ids : $allowed;
        }
        $single = trim((string) $filter);
        if ($single !== '' && $single !== '0' && ctype_digit($single) && in_array((int) $single, $allowed, true)) {
            return $single;
        }
        return count($allowed) === 1 ? (string) $allowed[0] : $allowed;
    }

    /**
     *
     * This routine will double check that the ID's are for knowledgebase items and if they are update
     * the database to say that they have been returned
     *
     * @param $knowledgeIds
     * @return void
     */
    public static function updateKnoweldgeCount($knowledgeIds)
    {

        foreach ($knowledgeIds as $values) {
            if (substr($values, -1) == '*') {
                $values = substr($values, 0, -1);
                $validId = false;
                if (defined('APP_COUNT_ITEM_TYPE') && Database::exists('items', 'item_id = ? AND item_type_id = ?', [(int) $values, (int) APP_COUNT_ITEM_TYPE])) {
                    $validId = true;
                }
            } else {
                $validId = True;
            }
            $values = (int) $values;
            $event_id = isset($_GET['event_id']) && is_scalar($_GET['event_id']) ? (string) $_GET['event_id'] : '';
            if ($validId == True) {
                if (Database::exists('system_log', 'item_id = ? AND event_id = ?', [$values, $event_id])) {
                    Database::run('UPDATE system_log SET event_counter = event_counter + 1 WHERE item_id = ? AND event_id = ?', [$values, $event_id]);
                } else {
                    Database::insert('system_log', [
                        'item_id' => $values,
                        'create_date' => time(),
                        'event_counter' => 1,
                        'event_id' => $event_id,
                        'security_id' => $_SESSION['access_user_id'],
                    ]);
                }

            }
        }
    }

    /**
     * showQuickSearch()
     */
    public static function showQuickSearch()
    {
        $javascript = Form::fieldCheck(TXT_468, [''], ['search_value'], [''], [TXT_206], [true]);
        $inApplication = Page::current()->inApplication();
        $typeLabels = $inApplication ? ['Name', 'ID', 'Notes'] : [TXT_84, TXT_102, TXT_601];
        $fields = [
            TXT_682 => Form::select(
                'search_type',
                ['item_title', 'item_id', 'log_entries'],
                $typeLabels,
                'item_title'
            ),
            ($inApplication ? 'Name' : TXT_683) => Form::text('search_value', ''),
            '' => Form::hidden('search', 'Search'),
        ];
        Page::current()->setBody(Form::form(
            Wording::application('TXT_62', TXT_376),
            self::address('search', 'option=quick_search'),
            $fields,
            [Form::button('submit', 'search', TXT_3, $javascript)]
        ));
    }

    /**
     * Simplified item listing that renders items as vertical cards.
     *
     * Parameters mirror the original showItems for compatibility.
     *
     * - Accepts:
     *   - $itemIDArray: array of ids, or array of rows with item_id
     *   - $itemID: optional single id filter
     *   - $orderSQL: optional ORDER BY clause (pass column(s) only)
     *   - $userID, $savedSearch: kept for signature compatibility (not used here)
     *
     * Notes:
     * - IDs are coerced to integers before inclusion in the IN() list to prevent injection through IDs.
     * - This function focuses on readable output and simple, maintainable structure.
     */
    public static function showItems($itemIDArray, $itemID = '', $orderSQL = '', $userID = '', $savedSearch = ''): void
    {
        // Normalize input: if a SQL string was passed, build an array using Database helper
        $built = is_array($itemIDArray) ? $itemIDArray : [];

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
            : (string)Wording::application('TXT_1', (string)Wording::get('LA_44', 'TXT_44'));

        if (empty($ids)) {
            $again = self::address('search', Page::current()->inApplication() ? 'option=show_item_search' : 'option=show_quick_search');
            Page::current()->setBody(Blocks::vertical([
                [
                    'title' => $heading,
                    'html' => Blocks::emptyState([
                        'illustration' => 'empty-search',
                        'title' => 'No records matched',
                        'copy' => 'Change the search and run it again.',
                        'action' => $again !== ''
                            ? '<a class="btn btn--primary" href="' . Html::e($again) . '">Search again</a>'
                            : '',
                    ]),
                ],
            ]));
            return;
        }

        $ids = RecordListQuery::ids($ids);
        [$where, $params] = RecordListQuery::idWhere($ids);
        $columns = ['item_id', 'item_title', 'create_date', 'item_type_id'];
        $result = Database::select('items', $columns, $where, $params, RecordListQuery::order((string) $orderSQL));

        $base = self::address('records');
        $rows = [];
        foreach ($result as $row) {
            $itemId = (int)$row['item_id'];
            $rows[] = Blocks::itemRecord(
                $row,
                $base . '&item=' . $itemId,
                Blocks::itemActions($itemId, $base)
            );
        }

        Page::current()->setBody(Blocks::vertical([
            [
                'title' => $heading,
                'html' => Blocks::recordList([
                    'column' => Wording::get('LA_84', 'TXT_84'),
                    'columns' => Blocks::itemListColumns(),
                    'searchLabel' => TXT_3,
                    'empty' => TXT_115,
                    'groups' => [['rows' => $rows]],
                ]),
            ],
        ]));
    }

    /**
     * Shows a list of items defined by item id, filtered by specific item where applicable
     *
     * @param array<mixed>|int|string $itemIDArray Item ids, or one id
     * @param int|string $itemID Item id to show on its own, or an empty string for the whole list
     * @param string $orderSQL Order by clause
     * @param mixed $userID User ID
     * @param mixed $savedSearch Saved search text
     */
    public static function showItems1($itemIDArray, $itemID = '', $orderSQL = '',  $userID = '', $savedSearch='')
    {
    	unset($_SESSION['item_search_sql'], $_SESSION['item_search_params']);

    	$knowledgeArticlesArray = array();

    	$condition = 'item_id = ?';
    	$listParams = [0];
    	if (is_array($itemIDArray)){
    		if ($_SESSION['access_role_id'] >1){ //Everyone except admins and global admins
    			$uid = (int) $_SESSION['access_user_id'];
    			$userItemArray = Database::select('items', ['item_id'], 'user_security = ? OR creator_security = ?', [$uid, $uid]);
    			$groupItemArray = array_map(
    				static fn(int $id): array => ['item_id' => $id],
    				\Adlexone\Data\GroupMembership::itemIdsForUser($uid)
    			);
    			$userItemArray = array_values(array_unique(array_merge($groupItemArray, $userItemArray), SORT_REGULAR));
    		}else{
    			// Get all items is is administrator (for searching only)
    			$userItemArray = Database::select('items', ['item_id']);
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
    				$safeColumn = RecordListQuery::column((string) ($tmpvars1[1] ?? ''));
    				if ($safeColumn === '') {
    					continue;
    				}
    				$tmpvars1[1] = $safeColumn;
    				$columnArray[] = $safeColumn;
    				$headFields[] = 'disp_' . $safeColumn;
    				switch(trim($value)) {
    					case 'item_id':
    						$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('item_id', Wording::get('LA_102', 'TXT_102')), $_SESSION['access_role_id'], 4);
    						break;
    					case 'item_title':
    						$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('item_title', Wording::get('LA_84', 'TXT_84')), $_SESSION['access_role_id'], 4);
    						break;
    					case 'create_date':
    						$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('create_date', TXT_225), $_SESSION['access_role_id'], 4);
    						break;
    					case 'item_type_id':
    						$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('item_type_id', Wording::get('LA_226', 'TXT_226')), $_SESSION['access_role_id'], 4);
    						break;

    					case 'creator_security':
    						$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('creator_security', Wording::get('LA_551', 'TXT_551')), $_SESSION['access_role_id'], 4);
    						break;
    					case 'user_security':
    						$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('user_security', Wording::get('LA_269', 'TXT_269')), $_SESSION['access_role_id'], 4);
    						break;


    					default:
    						$tmpvars = explode("_",$tmpvars1[1]);
    						$fieldId = (int) ($tmpvars[count($tmpvars) - 1] ?? 0);
    						$headresult = Database::rows('SELECT custom_field_name FROM custom_fields WHERE custom_field_id = ?', [$fieldId]);
    						$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('custom_field_' . (int) $tmpvars[count($tmpvars) - 1], (string) ($headresult[0]['custom_field_name'] ?? '')), $_SESSION['access_role_id'], 4);
    						unset($tmpvars1);
    						unset($tmpvars);
    				}
    			}
    		} else {
    			//build POSTed search hading
    				foreach($_POST as $key => $value) {
    					if (substr($key,0,4)== "disp") {
    						$safeColumn = RecordListQuery::column(substr((string) $key, 5));
    						if ($safeColumn === '') {
    							continue;
    						}
    						$columnArray[] = $safeColumn;
    						$headFields[] = 'disp_' . $safeColumn;
    						if (substr($key,5,6) == "custom") {
    							$tmpvars = explode('_', $safeColumn);
    							$fieldId = (int) ($tmpvars[count($tmpvars) - 1] ?? 0);
    							$headresult = Database::rows('SELECT custom_field_name FROM custom_fields WHERE custom_field_id = ?', [$fieldId]);
    							$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('custom_field_' . (int) $tmpvars[count($tmpvars) - 1], (string) ($headresult[0]['custom_field_name'] ?? '')), $_SESSION['access_role_id'], 4);
    							unset($tmpvars);
    						} else {
    							switch($key) {
    								case 'disp_item_id':
    									$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('item_id', Wording::get('LA_102', 'TXT_102')), $_SESSION['access_role_id'], 4);
    									break;
    								case 'disp_item_title':
    									$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('item_title', Wording::get('LA_84', 'TXT_84')), $_SESSION['access_role_id'], 4);
    									break;
    								case 'disp_create_date':
    									$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('create_date', TXT_225), $_SESSION['access_role_id'], 4);
    									break;
    								case 'disp_item_type_id':
    									$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('item_type_id', Wording::get('LA_226', 'TXT_226')), $_SESSION['access_role_id'], 4);
    									break;
    								case 'disp_creator_security':
    									$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('creator_security', Wording::get('LA_551', 'TXT_551')), $_SESSION['access_role_id'], 4);
    									break;
    								case 'disp_user_security':
    									$headTitle[] = Access::outputIfRoleAllowed(Form::sortButton('user_security', Wording::get('LA_269', 'TXT_269')), $_SESSION['access_role_id'], 4);
    									break;
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
            $listParams = [];
            if ($itemID != '') {
                if (in_array($itemID, (array)$userItemArray)) {
                    $condition = 'item_id = ?';
                    $listParams = [(int) $itemID];
                } else {
                    $condition = 'item_id = ?';
                    $listParams = [0];
                }
            } else {
                // Ensure we have an array of IDs
                if (count($itemIDArray) === 0) {
                    $condition = 'item_id = ?';
                    $listParams = [0];
                } else {
                    // normalize incoming ID arrays to flat integer lists
                    $itemIDArray = array_values(array_map('intval', $itemIDArray));
                    $userItemIds = [];
                    $first = $userItemArray === [] ? null : reset($userItemArray);
                    if (is_array($first) && array_key_exists('item_id', $first)) {
                        $userItemIds = array_values(array_map('intval', array_column($userItemArray, 'item_id')));
                    } else {
                        $userItemIds = array_values(array_map('intval', $userItemArray));
                    }

    // perform permission-aware filtering using the normalized lists
                    if ($userItemIds !== []) {
                        $allowed = array_values(array_intersect($itemIDArray, $userItemIds));
                    } else {
                        $allowed = $itemIDArray;
                    }

                    [$condition, $listParams] = RecordListQuery::idWhere(array_map('intval', $allowed));
                }
            }
    		if (!isset($_POST['pageset'])) {
    			// First off get number of records before doing sort
    			$countrecs = Database::count('items', $condition, $listParams);
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
    		$countBoolean = (($countrecs / (int) SET_ITEMS_PAGE) > 1);
    		$orderSQL = RecordListQuery::order((string) $orderSQL);
    		$pageSize = max(1, (int) SET_ITEMS_PAGE);
    		if ($countBoolean) {
    			$page = RecordListQuery::page($_POST['pageset'] ?? 1);
    			$result = Database::select('items', $columnArray, $condition, $listParams, $orderSQL, $pageSize, ($page - 1) * $pageSize);
    		} else {
    			$result = Database::select('items', $columnArray, $condition, $listParams, $orderSQL);
    		}

    		// Create Table and populate with data (and formatting)
    		// Set required style info
    		$headHtml = "";
    		if ($countrecs >= 1) {
    			$tableHeadings = array();
    			foreach($headTitle as $key => $value) {
    				$tableHeadings[] = $value;
    				$headHtml .= Form::hidden($headFields[$key],$headFields[$key]);
    			}
    			$fields[] = TXT_388;
    		} else {
                $fields = array(Wording::get('LA_102', 'TXT_102'),Wording::get('LA_84', 'TXT_84'),TXT_225,Wording::get('LA_226', 'TXT_226'),TXT_388);
    		}

    		$html = Form::fields($fields);
    		$i = 0;
    		$countUpdate = (defined('APP_COUNT_ITEM_TYPE')) ? '&event_id=view_item' : '';
    		$formHTML = '';
        		if (count($result) > 0) {
    			$itemTypeID = '';
    			$itemTypeName = '';
    			$tableRows = '';
    			foreach ($result as $row) {
    				$cellData = [];
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
    							$showHref = self::address('records', 'item=' . (int) $row['item_id'] . '&option=show_item' . $logEntry . $attachments . $countUpdate);
    							$cellData[] = '<a href="' . Html::e($showHref) . '" class="URL">' . Html::e((string) $title) . '</a>';
    							break;
    						case 'create_date':
    							$cellData[] = date(SET_DATE_FORMAT, $row['create_date']);
    							break;
    						case 'item_type_id':
    							if ($itemTypeID != $row['item_type_id']){  // We don't need to recheck as the last check was for the same item type id
    								$itemTypeRow = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [(int) $row['item_type_id']]);
    								$itemTypeName = (string) ($itemTypeRow['item_type_name'] ?? '');
    								$cellData[] = $itemTypeName;
    								$itemTypeID = $row['item_type_id'];
    							} else {
    								$cellData[] = $itemTypeName;
    							}
    							break;

    						case 'creator_security':
    							$creatorRow = Database::first('users', ['user_name'], 'user_id = ?', [(int) $row['creator_security']]);
    							$cellData[] = (string) ($creatorRow['user_name'] ?? '');
    							break;

    						case 'user_security':
    							$userRow = Database::first('users', ['user_name'], 'user_id = ?', [(int) $row['user_security']]);
    							$cellData[] = (string) ($userRow['user_name'] ?? '');
    							break;

    						default:
    							$fieldId = (int) str_replace('custom_field_', '', (string) $value);
    							$urlRow = Database::first('custom_fields', ['field_type', 'data'], 'custom_field_id = ?', [$fieldId]);
    							if (!is_array($urlRow)) {
    								$cellData[] = $row[$value] ?? '';
    								break;
    							}
    							if ($urlRow['field_type'] == 'URL'){
    								if ($row[$value] != ''){
    									$http = (!stristr($value,'http') AND !stristr($value,'https')) ? 'http://' : '';
    									$cellData[] = Blocks::link($http.$row[$value], $row[$value], '', 'URL', '', '_blank');
    								}else{
    									$cellData[] = $row[$value];
    								}
    							}elseif ($urlRow['field_type'] == 'dynamicURL'){
    								if ($row[$value] != ''){
    									$cellData[] = Blocks::link(str_replace('<?php echo INSERT; ?>',$row[$value],$urlRow['data']), $row[$value], '', 'URL', '', '_blank');
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
    				$actions = (string) Access::outputIfRoleAllowed(
    				    Blocks::link(
    				        self::address('records', 'item=' . (int) $row['item_id'] . '&option=log_entry'),
    				        TXT_246
    				    ),
    				    $_SESSION['access_role_id'],
    				    4
    				);

    				$actions .= (string) Access::outputIfRoleAllowed(
    				    ' - ' . Blocks::link(
    				        self::address('records', 'item=' . (int) $row['item_id'] . '&option=show_attachments'),
    				        TXT_389
    				    ),
    				    $_SESSION['access_role_id'],
    				    4
    				);

    				$actions .= (string) Access::outputIfRoleAllowed(
    				    ' - ' . Blocks::link(
    				        self::address('records', 'item=' . (int) $row['item_id'] . '&option=change_security'),
    				        TXT_28
    				    ),
    				    $_SESSION['access_role_id'],
    				    3
    				);

    				$actions .= (string) Access::outputIfRoleAllowed(
    				    ' - ' . Blocks::link(
    				        Router::manageUrl('print', 'item=' . (int) $row['item_id']),
    				        TXT_625,
    				        '',      // spriteName
    				        'URL',   // class
    				        '',      // javascript
    				        '_blank' // target
    				    ),
    				    $_SESSION['access_role_id'],
    				    5
    				);

    				$actions .= (string) Access::outputIfRoleAllowed(
    				    ' - ' . Blocks::link(
    				        self::address('records', 'item=' . (int) $row['item_id'] . '&option=delete_item'),
    				        TXT_315,
    				        '', // spriteName
    				        'URL',
    				        '',
    				        '_parent',
    				        TXT_400
    				    ),
    				    $_SESSION['access_role_id'],
    				    2
    				);
    				$cellData[] = $actions;
    				$formHTML = Form::fields($cellData);
    				$i++;
    				unset($cellData, $actions);
    			}
    		} else {
    			$html .= Form::fields(array(TXT_115));
    		}
    		$html = Form::open(self::address('search', 'option=show_search_results'),'POST','form-horizontal');

    		$html .= $formHTML;
    		unset($tableRows);
    		//Display next/prev menu
    		$pageset = '';
    		if ($countBoolean) {
    			$paginationHTML = '<div class="pagination pagination-centered "><ul>';
    			$pageset = (isset($_POST['pageset'])) ? $_POST['pageset'] : 1;
    			if ($pageset > 1) {
    				$paginationHTML .= Access::outputIfRoleAllowed('<li>' . Form::pageButton('prev', 'Prev') . '</li>', $_SESSION['access_role_id'], 5);
    			} else {
    				$paginationHTML .= "";
    			}
    			$istart = max(1,($pageset - 3));
    			$iend = max(10,$pageset + 3);
    			$iend = ($iend > (int)(($countrecs / (int) SET_ITEMS_PAGE) + 1)) ? (int)($countrecs / (int) SET_ITEMS_PAGE) + 1 : $iend;
    			if ($istart > 1 && $iend - $istart < 10) {
    				$istart = max($iend - 10,1);
    			}
    			for ($i = $istart; $i <= $iend; $i++) {
    				if ($i == $pageset) {
    					$paginationHTML .= '<li class="active"><a href ="#">'. $i .'</a></li>';
    				} else {
    					$paginationHTML .= Access::outputIfRoleAllowed('<li>' . Form::pageButton((string) $i, (string) $i) . '</li>', $_SESSION['access_role_id'], 5);
    				}
    			}
    			if ($pageset == $iend) {
    				$paginationHTML .=  "";
    			} else {
    				$paginationHTML .= Access::outputIfRoleAllowed('<li>' . Form::pageButton('next', 'Next') . '</li>', $_SESSION['access_role_id'], 5);
    			}
    			$paginationHTML .= '</ul></div>';
    			$html .= $paginationHTML;
    			$html .= Form::hidden('pageset',$pageset);
    		}
    		$html .= Form::hidden('currsort','');
    		unset($_SESSION['item_search_sql'], $_SESSION['item_search_params']);
    		$html .= Form::hidden('search', (string) $pageset);
    		$html .= Form::hidden('countrecs',$countrecs);
    		$html .= Form::hidden('search_type', Request::query('option'));
    		$html .= $headHtml;
    		$html .= '</form>';
    	}else{
    		$html = Form::fields([
    			Wording::get('LA_102', 'TXT_102') => Html::e(TXT_115),
    		]);

    	}
    	if (defined('APP_COUNT_ITEM_TYPE') && count($knowledgeArticlesArray) > 0) {
    		self::updateKnoweldgeCount($knowledgeArticlesArray);
    	}
    	$heading = (defined('SEARCH_NAME')) ? SEARCH_NAME : Wording::get('LA_44', 'TXT_44');
        $bodyBlock[] = ['title' => $heading, 'html' => $html];
        $bodyContent = Blocks::vertical($bodyBlock);
    	Page::current()->setBody($bodyContent);
    }

    public static function quickSearch()
    {
        // Takes criteria from quick search field and returns items
        $_POST['disp_item_id'] = "disp_item_id";
        $_POST['disp_item_title'] = "disp_item_title";
        $_POST['disp_create_date'] = "disp_create_date";
        $_POST['disp_item_type_id'] = "disp_item_type_id";
        $sql = "";
        $value = is_scalar($_POST['search_value'] ?? null) ? (string) $_POST['search_value'] : '';
        switch ((string) ($_POST['search_type'] ?? '')) {
            case 'item_id':
                $_POST['item_id'] = ctype_digit($value) ? $value : '0';
                $_POST['id_operator'] = '=';
                break;
            case 'item_title':
                $_POST['item_title'] = $value;
                $_POST['item_title_operator'] = 'LIKE';
                break;
            case 'log_entries':
                $sql = 'SELECT items.item_id, item_title, items.create_date, item_type_id FROM '
                    . 'items JOIN core_log ON items.item_id = core_log.item_id WHERE log_text LIKE ? ESCAPE \'\\\'';
                self::searchResults($sql, ['%' . Database::escapeLike($value) . '%']);
                return;
            default:
                View::respond(TXT_548);
                return;
        }
        $_POST['search'] = $_POST['search'] ?? 'quick_search';
        self::searchResults($sql);
    }

    /**
     * A comparison operator from a posted value, limited to the ones the search form offers.
     */
    private static function operator(mixed $posted, string $fallback = '='): string
    {
        $op = strtoupper(trim(html_entity_decode(is_scalar($posted) ? (string) $posted : '', ENT_COMPAT, 'UTF-8')));

        return in_array($op, ['=', '<>', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'], true) ? $op : $fallback;
    }

    /**
     * AND or OR between two search conditions.
     */
    private static function joiner(mixed $posted): string
    {
        return strtoupper(trim(is_scalar($posted) ? (string) $posted : '')) === 'OR' ? 'OR' : 'AND';
    }

    /**
     * A user id, or the signed-in user's id for the "me" choice in the search form.
     */
    private static function personId(string $posted): int
    {
        return $posted === 'session_user' ? (int) ($_SESSION['access_user_id'] ?? 0) : (int) $posted;
    }

    private static function dateColumn(mixed $posted): string
    {
        return in_array($posted, ['create_date', 'core_log_updated'], true) ? (string) $posted : 'create_date';
    }

    private static function postedText(string $key): string
    {
        return is_scalar($_POST[$key] ?? null) ? (string) $_POST[$key] : '';
    }

    /**
     * Seconds since the epoch for a posted date, or null when it is not a date.
     */
    private static function postedTime(string $key): ?int
    {
        $text = trim(self::postedText($key));
        if ($text === '') {
            return null;
        }
        $parsed = date_parse($text);
        if (($parsed['error_count'] ?? 1) > 0 || !is_int($parsed['year'] ?? false) || !is_int($parsed['month'] ?? false) || !is_int($parsed['day'] ?? false)) {
            return null;
        }

        return mktime(
            (int) ($parsed['hour'] ?? 0),
            (int) ($parsed['minute'] ?? 0),
            (int) ($parsed['second'] ?? 0),
            $parsed['month'],
            $parsed['day'],
            $parsed['year']
        );
    }

    /**
     * An ORDER BY column from a posted value, or an empty string when it is not a plain column name.
     */
    private static function sortColumn(string $posted): string
    {
        $posted = trim($posted);

        return preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $posted) === 1 ? $posted : '';
    }

    /**
     * Wrapper function for template: search_item_search.html
     *
     * Creates a table with results from a search
     */
    public static function searchResults($sql = '', array $params = [])
    {

        // Handling accessing the page from option=show_search_results with no POSTed data
        if (empty($_POST['search']) and empty($_POST['new_favourite']) and Request::posted('item_type_id') == '' and empty($_POST['search_type'])) {
            View::respond(TXT_548);
            return;
        }

        // Handle item id filtering
    if (isset($_POST['item_type_id']) && $_POST['item_type_id'] !== '' && empty($_POST['search']) && empty($_POST['new_favourite'])) {
                self::showAdvancedItemSearch($_POST['item_type_id']);
                return;
            }
        // Generate from form based values or pre defined sql
        $andOr = '';

        if ($sql == '') {
            $condition = 'WHERE';
            $allowedTypes = self::applicationSearchTypeIds();
            // Item types
            if (!empty($_POST['item_type_id'])) {
                $postedType = (int) $_POST['item_type_id'];
                if ($allowedTypes !== [] && !in_array($postedType, $allowedTypes, true)) {
                    Access::deny();
                }
                $condition .= " item_type_id = '" . $postedType . "' ";
                $andOr = 'AND';
            } elseif ($allowedTypes !== []) {
                $condition .= ' item_type_id IN (' . implode(',', array_map('intval', $allowedTypes)) . ') ';
                $andOr = 'AND';
            }
            // Item ID
            if (!empty($_POST['item_id'])) {
                $itemIdText = self::postedText('item_id');
                if (self::operator($_POST['id_operator'] ?? '=') === '=') {
                    $condition .= $andOr . " item_id = " . (ctype_digit($itemIdText) ? $itemIdText : '0') . " ";
                } else {
                    $condition .= $andOr . " item_id LIKE ? ESCAPE '\\' ";
                    $params[] = '%' . Database::escapeLike($itemIdText) . '%';
                }
                $andOr = self::joiner($_POST['id_andor'] ?? '');
            }
            // Hour Range
            if (!empty($_POST['hour_range'])) {
                $hours = (int) self::postedText('hour_range');
                $condition .= $andOr . " " . self::dateColumn($_POST['hour_type'] ?? '') . " " . self::operator($_POST['hour_range_operator'] ?? '', '<=') . " " . (time() - $hours * 60 * 60) . " ";
                $andOr = self::joiner($_POST['hour_range_andor'] ?? '');
            }
            // Date Created 1 and 2
            foreach (['1', '2'] as $n) {
                $time = self::postedTime('date_' . $n);
                if ($time !== null) {
                    $condition .= $andOr . " " . self::dateColumn($_POST['date_type_' . $n] ?? '') . " " . self::operator($_POST['date_operator_' . $n] ?? '', '>=') . " " . $time . " ";
                    $andOr = self::joiner($_POST['date_andor_' . $n] ?? '');
                }
            }
            // Creator User
            if (!empty($_POST['creator_security'])) {
                $condition .= $andOr . " creator_security " . self::operator($_POST['security_creators_operator'] ?? '') . " " . self::personId(self::postedText('creator_security')) . " ";
                $andOr = self::joiner($_POST['security_creators_andor'] ?? '');
            }
            // Security User
            if (!empty($_POST['user_security'])) {
                $condition .= $andOr . " user_security " . self::operator($_POST['security_users_operator'] ?? '') . " " . self::personId(self::postedText('user_security')) . " ";
                $andOr = self::joiner($_POST['security_users_andor'] ?? '');
            }
            // Security Group
            if (!empty($_POST['security_groups'])) {
                $groupId = (int) self::postedText('security_groups');
                if (self::operator($_POST['security_groups_operator'] ?? '') === '=') {
                    $condition .= $andOr . " item_id IN (SELECT item_id FROM item_groups WHERE group_id = " . $groupId . ") ";
                } else {
                    $condition .= $andOr . " item_id NOT IN (SELECT item_id FROM item_groups WHERE group_id = " . $groupId . ") ";
                }
                $andOr = self::joiner($_POST['security_groups_andor'] ?? '');
            }
            // Item Title
            if (!empty($_POST['item_title'])) {
                $titleText = self::postedText('item_title');
                if (self::operator($_POST['item_title_operator'] ?? 'LIKE', 'LIKE') === '=') {
                    $condition .= $andOr . ' item_title = ? ';
                    $params[] = $titleText;
                } else {
                    $condition .= $andOr . " item_title LIKE ? ESCAPE '\\' ";
                    $params[] = '%' . Database::escapeLike($titleText) . '%';
                }
                $andOr = self::joiner($_POST['title_andor'] ?? '');
            }

            // Custom Fields
            $i = 0;
            $customFieldCount = min(50, max(0, (int) self::postedText('custom_field_count')));
            while ($i < $customFieldCount) {
                if (self::postedText('custom_field_value_' . $i) !== '') {
                    $fieldId = (int) self::postedText('custom_field_' . $i);
                    $op = self::operator($_POST['custom_field_operator_' . $i] ?? '');
                    $val = self::postedText('custom_field_value_' . $i);
                    $part = \Adlexone\Data\ItemFields::matchSql($fieldId, $op, $val);
                    $condition .= $andOr . ' ' . $part->sql . ' ';
                    foreach ($part->params as $param) {
                        $params[] = $param;
                    }
                    $andOr = self::joiner($_POST['custom_field_andor_' . $i] ?? '');
                }
                $i++;
            }
            $columnArray = array('item_id', 'item_type_id', 'item_title');
            $itemColumns = Database::columns('items');
            foreach ($_POST as $key => $value) {
                if (substr((string) $key, 0, 4) == "disp" && $value !== '') {
                    $col = substr((string) $key, 5);
                    if ($col !== '' && in_array($col, $itemColumns, true) && !in_array($col, $columnArray, true)) {
                        $columnArray[] = $col;
                    }
                }
            }
            $condition = ($condition == 'WHERE') ? '' : $condition;  // If condition is equal to where it means we have set no field values
            $sql = Database::sqlSelect('items', $columnArray, $condition);
        }

        unset($_SESSION['item_search_sql'], $_SESSION['item_search_params']);

        $sortColumn = self::sortColumn(substr(self::postedText('sort_field'), 5));
        $newsort = '';
        if (isset($_POST['sortOrder']) && $sortColumn !== '') {
            $direction = ($_POST['sortOrder'] == 'descending') ? 'DESC' : '';
            $newsort = RecordListQuery::order(trim($sortColumn . ' ' . $direction), '');
            if ($newsort !== '') {
                $sql .= ' ORDER BY ' . $newsort;
            }
        }
        // Show search results or move to save search page
        if (isset($_POST['search'])) {
            self::showItems(Database::rows($sql, $params), '', $newsort, $_SESSION['access_user_id']);
        } elseif (isset($_POST['new_favourite'])) {
            self::addSavedSearch(
                $_SESSION['access_user_id'],
                $_POST['search_name'],
                $_POST['search_description'],
                $_POST['application'],
                $_POST['security'],
                \Adlexone\Data\SavedSearch::criteriaFromPost($_POST)
            );
        } else {
    //        if ($buildImage != '') {
    //            $imagesFolder = THEME_PATH . $theme . '/images/';
    //            $imageHTML = '<img src="' . $imagesFolder . $buildImage . '"> ';
    //        }
            View::respond(TXT_354, TXT_375);
    //        if ($lineBreak == true) {
    //            $html .= '<br />';
    //        }

        }
    }

    public static function showSavedSearches($userID, $application = '')
    {
        if ($_SESSION['access_role_id'] <= '1') {
            $applicationClause = ($application != '') ? " WHERE application = ?" : "";
            $params = ($application != '') ? [$application] : [];
            $sql = "SELECT * FROM saved_searches $applicationClause ORDER BY search_name ASC";
        } else {
            $applicationClause = ($application != '') ? " AND application = ?" : "";
            $params = array_merge([(string) $userID], ($application != '') ? [$application] : []);
            $sql = "SELECT * FROM saved_searches WHERE (user = ? OR user = 'all') $applicationClause ORDER BY search_name ASC";
        }
        $result = Database::rows($sql, $params);
        $base = self::address('search');
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
        $emptyAction = '';
        if (Page::current()->inApplication()) {
            $searchButton = Blocks::link($base . '&option=show_item_search', 'Search', '', 'btn btn--primary');
            if ($rows === []) {
                $emptyAction = $searchButton;
            } else {
                $toolbar = '<div class="record-list__tools">'
                    . Blocks::link($base . '&option=show_item_search', 'Search', '', 'btn btn--primary btn--sm')
                    . '</div>';
            }
        }
        $list = Blocks::recordList([
            'column' => TXT_151,
            'columns' => [
                ['key' => 'description', 'label' => TXT_153, 'wrap' => true],
            ],
            'searchLabel' => TXT_3,
            'empty' => 'No saved searches',
            'emptyIllustration' => 'empty-saved',
            'emptyTitle' => 'No saved searches',
            'emptyCopy' => 'Run a search, then save it to this list.',
            'emptyAction' => $emptyAction,
            'toolbar' => $toolbar,
            'groups' => [['rows' => $rows]],
        ]);
        Page::current()->setBody(Blocks::vertical([
            [
                'title' => Wording::application('TXT_60', TXT_313),
                'html' => $list,
            ],
        ]));
    }

    public static function addSavedSearch($userID, $searchName, $searchDescription, $application, $security, array $criteria = [])
    {
        // Check for duplicate and respond with a return message if exists
        $searchName = trim((string) $searchName);
        $result = Database::rows('SELECT user FROM saved_searches WHERE search_name = ? AND user = ?', [$searchName, (string) $userID]);
        if (count($result) > 0) {
            View::respond(TXT_311, Form::historyBack(TXT_306));
            return;
        }
        if ($searchName === '') {
            View::respond(TXT_367, Form::historyBack(TXT_306));
            return;
        }
            if ($security == 'all') {
                $columnArray['user'] = 'all';
            } elseif ($security == 'mine') {
                $columnArray['user'] = $userID;
            } else {
                $columnArray['user'] = 'system';
            }
            $columnArray['search_name'] = $searchName;
            $columnArray['search_description'] = $searchDescription;
            $columnArray['application'] = $application;
            $columnArray['criteria_json'] = \Adlexone\Data\SavedSearch::encode(
                \Adlexone\Data\SavedSearch::withoutStoredSql($criteria)
            );
            Database::insert('saved_searches', $columnArray);
            // Success messagae
        View::respond(TXT_25, Form::historyBack(TXT_404));
    }

    public static function savedSearch($searchID, $userID, $global = false, $rss = false)
    {
        unset($_SESSION['item_search_sql'], $_SESSION['item_search_params']);

        // Show the records that match the saved filters.
        $searchID = (int) $searchID;
        if ($global == true) {
            $result = Database::rows("SELECT * FROM saved_searches WHERE search_id = ? AND user = 'all'", [$searchID]);
        } elseif ($_SESSION['access_role_id'] <= 2) {
            $result = Database::rows('SELECT * FROM saved_searches WHERE search_id = ?', [$searchID]);
        } else {
            $result = Database::rows("SELECT * FROM saved_searches WHERE search_id = ? AND (user = 'system' OR user = 'all' OR user = ?)", [$searchID, (string) $userID]);
        }
        $row = $result[0] ?? null;
        if ($row === null) {
            View::respond(TXT_115, Blocks::link(self::address('search', 'option=show_saved_searches'), TXT_313));
            return;
        }

        if ($rss == false) {
            // Set title constant for language alias in showitems function
            define('SEARCH_NAME', $row['search_name']);

            $finder = json_decode((string) ($row['criteria_json'] ?? ''), true);
            if (is_array($finder) && Page::current()->inApplication()) {
                $target = ApplicationSearch::savedHref($finder);
                if ($target !== '') {
                    header('Location: ' . \Adlexone\Http\Router::absolute($target));
                    exit;
                }
            }

            $savedQuery = \Adlexone\Data\SavedSearch::resolve($row);
            $built = Database::rows($savedQuery->sql, $savedQuery->params);
            $itemIDs = [];
            if ($built !== []) {
                $itemIDs = array_values(array_filter(
                    array_column($built, 'item_id'),
                    static fn ($id): bool => is_scalar($id) && (string) $id !== ''
                ));
            }

            self::showItems($itemIDs, '', 'item_id DESC', $_SESSION['access_user_id']);
        } else {
            //Prep for output of XML RSS feed
            ob_clean();
            $search = $row;
            $savedQuery = \Adlexone\Data\SavedSearch::resolve($search);
            $itemArray = Database::rows($savedQuery->sql, $savedQuery->params);
            $linkArray = [];
            $titleArray = [];
            foreach ($itemArray as $id) {
                $result = Database::rows('SELECT item_id, item_title FROM items WHERE item_id = ?', [(int) ($id['item_id'] ?? $id)]);
                $item = $result[0] ?? null;
                $linkArray[] = Blocks::link(self::address('records', 'item=' . (int) ($item['item_id'] ?? 0) . '&option=show_item'), '', 'URL');
                $titleArray[] = (string) ($item['item_title'] ?? '');
            }
            RssFeed::send(SET_DATE_FORMAT, $search['search_name'], $search['search_description'], 'www.oneorzero.com', $titleArray, $linkArray);
        }
    }

    public static function deleteSavedSearch($searchID, $userID, $scope)
    {
        // Delete favourite search
        $searchID = (int) $searchID;
        if ($scope == 'global' and $_SESSION['access_role_id'] <= 1) {
            Database::delete('saved_searches', 'search_id = ?', [$searchID]);
        } else {
            Database::delete('saved_searches', 'search_id = ? AND user = ?', [$searchID, (string) $userID]);
        }
        self::showSavedSearches($userID);
    }

    public static function showItemTypeMenu($filter = '')
    {
        $filter = self::constrainSearchTypeFilter($filter);
        $condition = '';
        if (is_array($filter)) {
            $i = 0;
            foreach ($filter as $id) {
                $id = (int) $id;
                // Remove special characters
                if ($i == 0) {
                    $condition = "WHERE item_type_id = '$id'";
                } else {
                    $condition .= " OR item_type_id = '$id'";
                }
                $i++;
            }
        } elseif ($filter != '' and $filter != '0') {
            $condition = 'WHERE item_type_id = ' . (int) $filter;
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
        return Form::select('item_type_id', $listValues, $listDisplayValues, '', 'onChange="document.itemSearch.submit();"');
    }

    public static function showSecurityGroups()
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
        return Form::select('security_groups', $listValues, $listDisplayValues, '');
    }

    public static function showCustomFieldsAsList($itemTypeID = '', $fieldData = '', $dispRows = '')
    {
        $condition = '';
        $fieldIds = [];
        if ($itemTypeID != '' and $itemTypeID != '0') {
            $columnArray = array('custom_field_id');
            if (is_array($itemTypeID)) {
                $condition = "WHERE ";
                foreach ($itemTypeID as $value) {
                    $condition .= "item_type_id = " . (int) $value . " OR ";
                }
                $condition = substr($condition, 0, -4);
            } else {
                $condition = "WHERE item_type_id = " . (int) $itemTypeID;
            }
            $result = Database::select('item_type_custom_fields', $columnArray, $condition);
            $fieldIds = [];
            foreach ($result as $row) {
                $id = (int) ($row['custom_field_id'] ?? 0);
                if ($id > 0) {
                    $fieldIds[] = $id;
                }
            }
        }
        $columnArray = array('custom_field_id', 'custom_field_name');
        $typeLimit = "field_type NOT LIKE ? AND field_type <> ?";
        $typeParams = ['worker%', 'fieldSeparator'];
        if (!empty($fieldIds)) {
            $marks = implode(', ', array_fill(0, count($fieldIds), '?'));
            $result = Database::select(
                'custom_fields',
                $columnArray,
                'custom_field_id IN (' . $marks . ') AND ' . $typeLimit,
                array_merge($fieldIds, $typeParams),
                'custom_field_name ASC'
            );
        } else {
            $result = Database::select('custom_fields', $columnArray, $typeLimit, $typeParams, 'custom_field_name ASC');
        }
        // Build list based on the defined amount of custom field lists to display
        $html = '';
        foreach ($result as $row) {
            $fieldId = (int) ($row['custom_field_id'] ?? 0);
            $fieldData[] = Html::e((string) ($row['custom_field_name'] ?? ''));
            $fieldData[] = Form::checkbox('disp_custom_field_' . $fieldId, 'true', '');
            $fieldData[] = Form::radio('sort_field', 'sort_custom_field_' . $fieldId, '');
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
    public static function showCustomFields($itemTypeID = '')
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
            $html .= self::searchControlRow(
                Form::select('custom_field_' . $a, $listValues, $listDisplayValues, ''),
                Form::select('custom_field_operator_' . $a, ['=', 'LIKE', '<>', '>', '<'], [TXT_418, TXT_112, TXT_318, TXT_364, TXT_365], '='),
                Form::text('custom_field_value_' . $a, ''),
                Form::select('custom_field_andor_' . $a, ['AND', 'OR'], [TXT_109, TXT_110], 'AND')
            );
        }
        $html .= Form::hidden('custom_field_count', (string)$count);

        return $html;
    }

    public static function searchControlRow(string ...$controls): string
    {
        return '<div class="criteria-row">' . implode('', $controls) . '</div>';
    }

    public static function searchApplicationMenu(string $selected): string
    {
        $names = [];
        $urls = [];
        foreach (\Adlexone\Application\ApplicationStore::searchScopes() as [$scope, $label]) {
            $names[] = $label;
            $urls[] = $scope;
        }

        return Form::select('application', $urls, $names, $selected);
    }

    /**
     * Displays the advanced item search form.
     *
     * This function generates an advanced search form for items, allowing users to filter
     * and sort items based on various criteria such as item types, system criteria, date ranges,
     * custom fields, and more. The form is divided into sections for better organization and
     * uses the `Form` builders for form elements.
     *
     * @param string|array $filter Optional filter criteria for item types.
     * @return void
     */
    public static function showAdvancedItemSearch($filter = '')
    {
        $filter = self::constrainSearchTypeFilter($filter);
        // Clear previous search SQL if not showing search results
        unset($_SESSION['item_search_sql'], $_SESSION['item_search_params']);

        // Define operator and logical values for dropdowns
        $operatorValues = array('=', 'LIKE', '<>');
        $operatorDisplayValues = array(TXT_81, TXT_112, TXT_318);
        $andOrValues = array('AND', 'OR');
        $andOrDisplayValues = array(TXT_109, TXT_110);
        $eventId = (isset($_GET['event_id']) && is_scalar($_GET['event_id'])) ? '&event_id=' . rawurlencode((string) $_GET['event_id']) : '';

        $fields = [];
        $allowedTypes = self::applicationSearchTypeIds();
        $inApplication = Page::current()->inApplication();
        if (!$inApplication || count($allowedTypes) > 1) {
            $fields[$inApplication ? 'Kind' : TXT_99] = self::showItemTypeMenu($filter);
        }
        $fields[Wording::get('LA_102', 'TXT_102')] = self::searchControlRow(
            Form::select('id_operator', $operatorValues, $operatorDisplayValues, ''),
            Form::text('item_id', ''),
            Form::select('id_andor', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[$inApplication ? 'Name' : Wording::get('LA_84', 'TXT_84')] = self::searchControlRow(
            Form::select('item_title_operator', $operatorValues, $operatorDisplayValues, ''),
            Form::text('item_title', ''),
            Form::select('title_andor', $andOrValues, $andOrDisplayValues, '')
        );

        // Populate users dropdown
        $sql = "select user_id, user_name FROM users ORDER BY user_name ASC";
            $result = Database::rows($sql);
        $listValues = ['' => TXT_105, 'session_user' => TXT_600];
        foreach ($result as $row) {
            $listValues[$row['user_id']] = $row['user_name'];
        }

        // Add user-related fields to system criteria
        $fields[Wording::get('LA_551', 'TXT_551')] = self::searchControlRow(
            Form::select('security_creators_operator', $operatorValues, $operatorDisplayValues, ''),
            Form::select('creator_security', array_keys($listValues), array_values($listValues), ''),
            Form::select('security_creators_andor', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[Wording::get('LA_269', 'TXT_269')] = self::searchControlRow(
            Form::select('security_users_operator', $operatorValues, $operatorDisplayValues, ''),
            Form::select('user_security', array_keys($listValues), array_values($listValues), ''),
            Form::select('security_users_andor', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[TXT_79] = self::searchControlRow(
            Form::select('security_groups_operator', array('=', '<>'), array(TXT_81, TXT_318), ''),
            self::showSecurityGroups(),
            Form::select('security_groups_andor', $andOrValues, $andOrDisplayValues, '')
        );

        $fields[TXT_406] = self::searchControlRow(
            Form::select('hour_type', array('create_date', 'core_log_updated'), array(TXT_103, TXT_420), ''),
            Form::select('hour_range_operator', array('<=', '=', '>='), array(TXT_405, TXT_81, TXT_111), '>='),
            Form::text('hour_range', '', '30'),
            Form::select('hour_range_andor', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[TXT_103 . ' 1'] = self::searchControlRow(
            Form::select('date_type_1', array('create_date', 'core_log_updated'), array(TXT_103, TXT_420), ''),
            Form::select('date_operator_1', array('<=', '=', '>='), array(TXT_405, TXT_81, TXT_111), '>='),
            Form::text('date_1', ''),
            Form::select('date_andor_1', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[TXT_103 . ' 2'] = self::searchControlRow(
            Form::select('date_type_2', array('create_date', 'core_log_updated'), array(TXT_103, TXT_420), ''),
            Form::select('date_operator_2', array('<=', '=', '>='), array(TXT_405, TXT_81, TXT_111), '<='),
            Form::text('date_2', ''),
            Form::select('date_andor_2', $andOrValues, $andOrDisplayValues, '')
        );
        $fields[TXT_407] = '<p class="field-note">dd-mm-yyyy hh:mm:ss</p>';

        $postedItemTypeID = (isset($_POST['item_type_id'])) ? $_POST['item_type_id'] : $filter;
        $customFields = self::showCustomFields($postedItemTypeID);
        if ($customFields !== '') {
            $fields[TXT_106] = $customFields;
        }

        $fields[TXT_258] = '<div class="choice-list__sort">'
            . '<label class="checkbox">' . Form::radio('sortOrder', 'ascending', 'ascending') . ' ' . Html::e(TXT_533) . '</label>'
            . '<label class="checkbox">' . Form::radio('sortOrder', 'descending', '') . ' ' . Html::e(TXT_534) . '</label>'
            . '</div>';

        $displayChoices = [
            [Wording::get('LA_102', 'TXT_102'), 'spec_item_id', 'disp_item_id', 'sort_item_id', true],
            [Wording::get('LA_84', 'TXT_84'), 'spec_item_title', 'disp_item_title', 'sort_item_title', false],
            [TXT_225, 'disp_create_date', '', 'sort_create_date', false],
        ];
        $choiceHtml = '<div class="choice-list">';
        foreach ($displayChoices as [$label, $checkName, $hiddenName, $sortValue, $sortChecked]) {
            $choiceHtml .= '<div class="choice-list__row">'
                . Form::checkbox($checkName, 'true', 'true', (string)$label)
                . ($hiddenName !== '' ? Form::hidden($hiddenName, $hiddenName) : '')
                . '<span class="choice-list__sort">' . Form::radio('sort_field', $sortValue, $sortChecked ? $sortValue : '', 'aria-label="' . Html::e(TXT_258) . '"')
                . Html::e(TXT_258) . '</span>'
                . '</div>';
        }
        $choiceHtml .= '</div>';
        $fields[TXT_527] = $choiceHtml;

        $fields[Wording::get('LA_310', 'TXT_310')] = Form::text('search_name', '');
        $fields[Wording::get('LA_312', 'TXT_312')] = Form::textarea('search_description', '', SET_FORM_FIELD_HEIGHT);
        if ($_SESSION['access_role_id'] <= '1') {
            $fields[TXT_403] = Form::select('security', array('mine', 'all', 'system'), array(TXT_402, TXT_401, TXT_482), '1');
        } else {
            $fields[''] = Form::hidden('security', 'mine');
        }

        $fields[TXT_350] = self::searchApplicationMenu(Request::query('controller'));

        $javascript = Form::fieldCheck(TXT_468, [''], ['search_name'], [''], [TXT_367], [true]);

        Page::current()->setBody(Form::form(
            Wording::application('TXT_61', (string)Wording::get('LA_96', 'TXT_96')),
            self::address('search', 'option=show_search_results' . $eventId),
            $fields,
            [
                Form::button('submit', 'search', TXT_3),
                Form::button('reset', 'reset', TXT_75),
                Form::button('submit', 'new_favourite', TXT_309, $javascript),
            ],
            ['name' => 'itemSearch', 'id' => 'itemSearch']
        ));
    }

    public static function editSavedSearch($id)
    {
        $id = (int) $id;
        $columnArray = ['search_name'];
        $row = Database::first('saved_searches', $columnArray, 'search_id = ?', [$id]);

        if (!$row) {
            // No record found — show a friendly message
           View::respond(TXT_628,TXT_115);
            return;
        }

        $action = self::address('search', 'search=' . $id . '&option=update_saved_search');
        $fields = [
            TXT_627 => Form::text('search_name', $row['search_name']),
        ];
        Page::current()->setBody(Form::form(
            TXT_628 . ' - ' . $row['search_name'],
            $action,
            $fields,
            [
                Form::button('submit', 'submit_button', TXT_74),
                Form::button('reset', 'reset', TXT_75),
            ]
        ));
    }

    public static function updateSavedSearch($id)
    {
        $id = (int) $id;
        if (!Database::exists('saved_searches', 'search_id = ?', [$id])) {
            View::respond(TXT_115);
            return;
        }
        $name = trim(html_entity_decode(self::postedText('search_name'), ENT_COMPAT, 'UTF-8'));
        if ($name === '') {
            View::respond(TXT_367);
            return;
        }
        Database::update('saved_searches', ['search_name' => $name], 'search_id = ?', [$id]);
        // Success messagae
        View::respond(TXT_628,TXT_629);
    }

    /**
     * The next address on this screen. Inside an application that is the application.
     * Outside, records and search are separate places.
     */
    private static function address(string $manage, string $query = ''): string
    {
        $page = Page::current();
        if ($page->inApplication()) {
            return Router::applicationUrl($page->applicationSlug(), $page->navId(), $query);
        }

        return Router::manageUrl($manage, $query);
    }
}
