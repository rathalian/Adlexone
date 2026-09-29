<?php
declare(strict_types=1);

/**
 * Service Centre.
 *
 * Announcements, settings, and the work list live here.
 * The application shell opens search and items through the router.
 */

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\support\RenderViews;

if (defined('SERVICE_CENTRE_LOADED')) {
    return;
}
define('SERVICE_CENTRE_LOADED', true);

const SERVICE_CENTRE_SLUG = 'service-centre';

function serviceCentreUrl(string $query = ''): string
{
    $nav = defined('APPLICATION_NAV_ID') ? (int) APPLICATION_NAV_ID : 0;

    return \Adlexone\Http\Router::applicationUrl((string) APPLICATION_SLUG, $nav, $query);
}

require_once __DIR__ . '/functions/announcements.php';
require_once __DIR__ . '/functions/settings.php';
require_once __DIR__ . '/functions/work.php';

switch ((string) ($_GET['option'] ?? '')) {
    case 'show_announcements':
        Access::require(Permission::SERVICECENTRE_USE, Permission::SERVICECENTRE_SEARCH);
        showAnnouncements();
        break;
    case 'show_announcement_item':
        Access::require(Permission::SERVICECENTRE_USE, Permission::SERVICECENTRE_SEARCH);
        showAnnouncementItem((string) ($_GET['id'] ?? ''));
        break;
    case 'new_announcement':
        Access::require(Permission::SERVICECENTRE_ANNOUNCE);
        newAnnouncement();
        break;
    case 'add_announcement':
        Access::require(Permission::SERVICECENTRE_ANNOUNCE);
        addAnnouncement();
        break;
    case 'edit_announcement':
        Access::require(Permission::SERVICECENTRE_ANNOUNCE);
        editAnnouncement((string) ($_GET['id'] ?? ''));
        break;
    case 'update_announcement':
        Access::require(Permission::SERVICECENTRE_ANNOUNCE);
        updateAnnouncement();
        break;
    case 'delete_announcement':
        Access::require(Permission::SERVICECENTRE_ANNOUNCE);
        deleteAnnouncement((string) ($_GET['id'] ?? ''));
        break;
    case 'show_work':
        Access::require(Permission::SERVICECENTRE_SEARCH, Permission::SERVICECENTRE_USE);
        showServiceCentreWork();
        break;
    case 'settings':
        Access::require(Permission::SERVICECENTRE_SETTINGS);
        showServiceCentreSettings();
        break;
    case 'update_settings':
        Access::require(Permission::SERVICECENTRE_SETTINGS);
        updateServiceCentreSettings();
        break;
    default:
        RenderViews::buildResponse('This screen is not available.');
        break;
}
