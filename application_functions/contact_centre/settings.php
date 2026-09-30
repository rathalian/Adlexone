<?php
declare(strict_types=1);

use Adlexone\Application\AppFunctions;
use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\support\Database;
use Adlexone\support\RenderViews;
use Adlexone\support\SharedMethods;

AppFunctions::register('contact_centre.settings', [
    'label' => 'Settings',
    'icon' => 'ic-settings',
    'config' => 'none',
    'default_option' => 'settings',
    'open' => 'openContactCentreSettings',
]);

function openContactCentreSettings(): void
{
    Access::require(Permission::SERVICECENTRE_SETTINGS);
    switch ((string) ($_GET['option'] ?? 'settings')) {
        case 'update_settings':
            updateContactCentreSettings();
            break;
        case 'settings':
        default:
            showContactCentreSettings();
            break;
    }
}

function showContactCentreSettings(): void
{
    $valueArray = [];
    $displayArray = [];
    $result = Database::select('item_types', ['item_type_id', 'item_type_name'], 'ORDER BY item_type_name ASC');
    foreach ($result as $row) {
        $valueArray[] = $row['item_type_id'];
        $displayArray[] = $row['item_type_name'];
    }

    $itemType = defined('SERVICECENTRE_SET_ITEM_TYPE') ? (string) SERVICECENTRE_SET_ITEM_TYPE : '';
    define('BODY_CONTENT', RenderViews::buildForm(
        APP_SC_TXT_79,
        MAN_BASE_URL . '&option=update_settings',
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

function updateContactCentreSettings(): void
{
    SharedMethods::saveSettingsToJson(
        SET_CONFIGURATION_PATH . 'servicecentre' . DIRECTORY_SEPARATOR . 'servicecentre_settings.json',
        [
            'SERVICECENTRE_SET_ITEM_TYPE' => (string) ($_POST['SERVICECENTRE_SET_ITEM_TYPE'] ?? ''),
        ]
    );
    header('Location: ' . MAN_BASE_URL . '&option=settings');
    exit;
}
