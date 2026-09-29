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

    $itemType = defined('SERVICECENTRE_SET_ITEM_TYPE') ? (string) SERVICECENTRE_SET_ITEM_TYPE : '';
    define('BODY_CONTENT', RenderViews::buildForm(
        APP_SC_TXT_79,
        serviceCentreUrl('option=update_settings'),
        [
            APP_SC_TXT_32 => RenderViews::buildSelectDropdown('SERVICECENTRE_SET_ITEM_TYPE', $valueArray, $displayArray, $itemType),
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
        ]
    );
    header('Location: ' . serviceCentreUrl('option=settings'));
    exit;
}
