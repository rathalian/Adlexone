<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Database\RecordListQuery;
use Adlexone\Auth\Permission;
use Adlexone\Application\ApplicationAccess;
use Adlexone\Application\ApplicationSettings;
use Adlexone\Database\Database;
use Adlexone\Http\RssFeed;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Http\Router;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Text\Wording;
use Adlexone\Html\Html;

final class SearchLibrary
{
    public static function showSavedSearches($userID, $application = '')
    {
        if (Access::allowsAdminister()) {
            $applicationClause = ($application != '') ? " WHERE application = ?" : "";
            $params = ($application != '') ? [$application] : [];
            $sql = "SELECT * FROM saved_searches $applicationClause ORDER BY search_name ASC";
        } else {
            $applicationClause = ($application != '') ? " AND application = ?" : "";
            $params = array_merge([(string) $userID], ($application != '') ? [$application] : []);
            $sql = "SELECT * FROM saved_searches WHERE (user = ? OR user = 'all') $applicationClause ORDER BY search_name ASC";
        }
        $result = Database::rows($sql, $params);
        $base = SearchScreen::address('search');
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
                $canDeleteGlobal = ($row['user'] == 'all' || $row['user'] == 'system') && Access::allowsAdminister();
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
                if (Access::allowsSystem()) {
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
        } elseif (Access::allowsControl()) {
            $result = Database::rows('SELECT * FROM saved_searches WHERE search_id = ?', [$searchID]);
        } else {
            $result = Database::rows("SELECT * FROM saved_searches WHERE search_id = ? AND (user = 'system' OR user = 'all' OR user = ?)", [$searchID, (string) $userID]);
        }
        $row = $result[0] ?? null;
        if ($row === null) {
            View::respond(TXT_115, Blocks::link(SearchScreen::address('search', 'option=show_saved_searches'), TXT_313));
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

            SearchResults::showItems($itemIDs, '', 'item_id DESC', $_SESSION['access_user_id']);
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
                $linkArray[] = Blocks::link(SearchScreen::address('records', 'item=' . (int) ($item['item_id'] ?? 0) . '&option=show_item'), '', 'URL');
                $titleArray[] = (string) ($item['item_title'] ?? '');
            }
            RssFeed::send(
                SET_DATE_FORMAT,
                $search['search_name'],
                $search['search_description'],
                \Adlexone\Http\PublicBaseUrl::resolve($_SERVER)['base'],
                $titleArray,
                $linkArray
            );
        }
    }

    public static function deleteSavedSearch($searchID, $userID, $scope)
    {
        // Delete favourite search
        $searchID = (int) $searchID;
        if ($scope == 'global' and Access::allowsAdminister()) {
            Database::delete('saved_searches', 'search_id = ?', [$searchID]);
        } else {
            Database::delete('saved_searches', 'search_id = ? AND user = ?', [$searchID, (string) $userID]);
        }
        self::showSavedSearches($userID);
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

        $action = SearchScreen::address('search', 'search=' . $id . '&option=update_saved_search');
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
        $name = trim(html_entity_decode(SearchForm::postedText('search_name'), ENT_COMPAT, 'UTF-8'));
        if ($name === '') {
            View::respond(TXT_367);
            return;
        }
        Database::update('saved_searches', ['search_name' => $name], 'search_id = ?', [$id]);
        // Success messagae
        View::respond(TXT_628,TXT_629);
    }
}
