<?php
declare(strict_types=1);

use Adlexone\FrameOne\AppAccess;
use Adlexone\FrameOne\AppSettings;
use Adlexone\support\Database;
use Adlexone\support\RenderViews;

/**
 * FrameOne per-app settings (default item type for Create / Advanced search shortcuts).
 */
function openFrameOneAppSettings(): void
{
    $slug = AppAccess::slug();
    if ($slug === '') {
        RenderViews::buildResponse('Open settings from inside an application.');
        return;
    }
    if (!AppAccess::canSettings()) {
        \Adlexone\Auth\Access::deny();
    }

    switch ((string) ($_GET['option'] ?? 'settings')) {
        case 'update_settings':
            updateFrameOneAppSettings($slug);
            break;
        case 'settings':
        default:
            showFrameOneAppSettings($slug);
            break;
    }
}

function showFrameOneAppSettings(string $slug): void
{
    $valueArray = [''];
    $displayArray = ['— none —'];
    foreach (Database::select('item_types', ['item_type_id', 'item_type_name'], '', [], 'item_type_name ASC') as $row) {
        $valueArray[] = (string) $row['item_type_id'];
        $displayArray[] = (string) $row['item_type_name'];
    }
    $current = AppSettings::defaultItemTypeId($slug);

    define('BODY_CONTENT', RenderViews::buildForm(
        'Application settings',
        MAN_BASE_URL . '&option=update_settings',
        [
            'Default item type' => RenderViews::buildSelectDropdown('default_item_type_id', $valueArray, $displayArray, $current),
        ],
        [
            RenderViews::buildFormButton('submit', 'submit_button', 'Save'),
            RenderViews::buildFormButton('reset', 'reset', 'Reset'),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function updateFrameOneAppSettings(string $slug): void
{
    $settings = AppSettings::get($slug);
    $settings['default_item_type_id'] = trim((string) ($_POST['default_item_type_id'] ?? ''));
    AppSettings::put($slug, $settings);
    header('Location: ' . MAN_BASE_URL . '&option=settings');
    exit;
}
