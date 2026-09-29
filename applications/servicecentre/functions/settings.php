<?php
declare(strict_types=1);

use Adlexone\support\Database;
use Adlexone\support\RenderViews;
use Adlexone\support\SharedMethods;

function showServiceCentreSettings(): void
{
    $valueArray = [];
    $displayArray = [];
    $result = Database::query(Database::sqlSelect('item_types', ['item_type_id', 'item_type_name'], 'ORDER BY item_type_name ASC'), DSN);
    while ($row = Database::fetchArray($result)) {
        $valueArray[] = $row['item_type_id'];
        $displayArray[] = $row['item_type_name'];
    }

    $listValues = [''];
    $listDisplayValues = [APP_SC_TXT_52];
    $searches = Database::queryParams(
        "SELECT search_id, search_name FROM saved_searches WHERE (user = 'all' OR user = 'system') AND application = ? ORDER BY search_name ASC",
        [SERVICE_CENTRE_SLUG]
    );
    while ($row = Database::fetchArray($searches)) {
        $listValues[] = $row['search_id'];
        $listDisplayValues[] = $row['search_name'];
    }

    $itemType = defined('SERVICECENTRE_SET_ITEM_TYPE') ? (string) SERVICECENTRE_SET_ITEM_TYPE : '';
    $savedSearch = defined('SERVICECENTRE_SET_SAVED_SEARCH') ? (string) SERVICECENTRE_SET_SAVED_SEARCH : '';
    define('BODY_CONTENT', RenderViews::buildForm(
        APP_SC_TXT_79,
        serviceCentreUrl('option=update_settings'),
        [
            APP_SC_TXT_32 => RenderViews::buildSelectDropdown('SERVICECENTRE_SET_ITEM_TYPE', $valueArray, $displayArray, $itemType),
            APP_SC_TXT_51 => RenderViews::buildSelectDropdown('SERVICECENTRE_SET_SAVED_SEARCH', $listValues, $listDisplayValues, $savedSearch),
        ],
        [
            RenderViews::buildFormButton('submit', 'submit_button', APP_SC_TXT_76),
            RenderViews::buildFormButton('reset', 'reset', APP_SC_TXT_77),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function updateServiceCentreSettings(): void
{
    SharedMethods::saveSettingsToJson(
        SET_CONFIGURATION_PATH . 'servicecentre' . DIRECTORY_SEPARATOR . 'servicecentre_settings.json',
        [
            'SERVICECENTRE_SET_ITEM_TYPE' => (string) ($_POST['SERVICECENTRE_SET_ITEM_TYPE'] ?? ''),
            'SERVICECENTRE_SET_SAVED_SEARCH' => (string) ($_POST['SERVICECENTRE_SET_SAVED_SEARCH'] ?? ''),
        ]
    );
    header('Location: ' . serviceCentreUrl('option=settings'));
    exit;
}
