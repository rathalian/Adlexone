<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Application\ApplicationAccess;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Http\Router;

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
            case 'find' :
            case 'results' :
                if (Page::current()->inApplication()) {
                    Access::require(Permission::APP_ACCESS);
                    ApplicationSearch::open();
                    break;
                }
                if ($option === 'results') {
                    Access::require(Permission::APP_ACCESS);
                    SearchResults::searchResults();
                    break;
                }
                Access::require(Permission::APP_ACCESS);
                $itemTypes = preg_replace('/[^0-9,]/', '', Request::query('item_types')) ?? '';
                $filter = (stristr($itemTypes, ',') !== false) ? explode(',', $itemTypes) : $itemTypes;
                SearchForm::showAdvancedItemSearch($filter);
                break;
            case 'saved' :
                Access::require(Permission::APP_ACCESS);
                $application = Request::query('application');
                if ($application === '' && Page::current()->inApplication()) {
                    $application = Page::current()->applicationSlug();
                }
                SearchLibrary::showSavedSearches($_SESSION['access_user_id'], $application);
                break;
            case 'open_saved' :
                Access::require(Permission::APP_ACCESS);
                SearchLibrary::savedSearch(Request::queryValue('id'), $_SESSION['access_user_id'], Request::query('global'));
                break;
            case 'delete_saved' :
                Access::require(Permission::APP_ACCESS);
                SearchLibrary::deleteSavedSearch(Request::queryValue('id'), $_SESSION['access_user_id'], Request::query('scope'));
                break;
            case 'run_quick' :
                Access::require(Permission::APP_ACCESS);
                SearchForm::quickSearch();
                break;
            case 'quick' :
                Access::require(Permission::APP_ACCESS);
                SearchForm::showQuickSearch();
                break;
            case 'edit_saved' :
                Access::require(Permission::ADMIN_SYSTEM);
                SearchLibrary::editSavedSearch(Request::queryValue('id'));
                break;
            case 'update_saved' :
                Access::require(Permission::ADMIN_SYSTEM);
                SearchLibrary::updateSavedSearch(Request::queryValue('id'));
                break;
            default :
                Access::require(Permission::APP_ACCESS);
                SearchForm::showQuickSearch();
                break;
        }
    }
    

    

    

    

    

    

    

    

    

    

    

    

    

    /**
     * The next address on this screen. Inside an application that is the application.
     * Outside, records and search are separate places.
     */
    public static function address(string $manage, string $query = ''): string
    {
        $page = Page::current();
        if ($page->inApplication()) {
            return Router::applicationUrl($page->applicationSlug(), $page->navId(), $query);
        }

        return Router::manageUrl($manage, $query);
    }
}
