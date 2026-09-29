<?php
declare(strict_types=1);

/**
 * Adlexone FlowIQ License Agreement 1.0
 *
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where
 *  Adlexone FlowIQ files contain this license in the header of the file, the
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

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\support\RenderViews;

/**
 * Homepage. One tile per destination. Section menus stay inside each app.
 */
function launchTile(string $href, string $title, string $hint, string $icon): string
{
    $hrefEsc = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $hintEsc = htmlspecialchars($hint, ENT_QUOTES, 'UTF-8');
    $iconEsc = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');

    return '<a class="launch-tile" href="' . $hrefEsc . '">'
        . '<span class="launch-tile__icon" aria-hidden="true"><svg class="icon"><use href="themes/new/assets/adlexone.sprite.svg#' . $iconEsc . '"></use></svg></span>'
        . '<span class="launch-tile__copy">'
        . '<span class="launch-tile__title">' . $titleEsc . '</span>'
        . '<span class="launch-tile__hint">' . $hintEsc . '</span>'
        . '</span></a>';
}

/**
 * @param list<string> $tiles
 */
function launchGroup(string $label, array $tiles): string
{
    if ($tiles === []) {
        return '';
    }
    $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    return '<section class="launchpad__group" aria-label="' . $labelEsc . '">'
        . '<h2 class="launchpad__heading">' . $labelEsc . '</h2>'
        . '<div class="launchpad__grid">' . implode('', $tiles) . '</div>'
        . '</section>';
}

$applications = [];
foreach (\Adlexone\Application\ApplicationStore::menuItems() as $item) {
    $hint = trim((string) $item['hint']);
    $applications[] = launchTile(
        (string) $item['href'],
        (string) $item['label'],
        $hint !== '' ? $hint : (string) $item['label'],
        (string) $item['icon']
    );
}

$manage = [];
if (Access::can(Permission::ADMIN_SETTINGS)) {
    $manage[] = launchTile(
        'index.php?controller=administration_applications',
        'Applications',
        'Create and arrange applications',
        'ic-launch'
    );
}
if (Access::can(Permission::ADMIN_ITEMS)) {
    $manage[] = launchTile(
        'index.php?controller=administration_item_settings&option=manage_fields',
        'Items and Fields',
        'Fields and item types',
        'ic-manage-fields'
    );
}
if (Access::can(Permission::ADMIN_ACTIONS)) {
    $manage[] = launchTile(
        'index.php?controller=administration_actions&option=show_defined_actions',
        'Workflow',
        'Actions and packages',
        'ic-manage-actions'
    );
}
if (Access::can(Permission::ADMIN_SECURITY)) {
    $manage[] = launchTile(
        'index.php?controller=administration_security&option=manage_users',
        'Security',
        'Users and groups',
        'ic-manage-users'
    );
}
if (Access::can(Permission::ADMIN_SETTINGS)) {
    $manage[] = launchTile(
        'index.php?controller=administration_settings&option=adlexone_settings',
        'Settings',
        'Application setup',
        'ic-system-settings'
    );
} elseif (Access::can(Permission::ADMIN_SYSTEM)) {
    $manage[] = launchTile(
        'index.php?controller=administration_settings&option=sign_in_settings',
        'Settings',
        'Sign-in and system setup',
        'ic-system-settings'
    );
}

$html = '<div class="launchpad">'
    . launchGroup('Applications', $applications)
    . launchGroup('Manage', $manage)
    . '</div>';

if ($applications === [] && $manage === []) {
    $html = '<p class="launchpad__empty">Nothing is available for this account.</p>';
}

if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Home');
}
define('BODY_CONTENT', $html);
RenderViews::renderThemePage('main_page_content', SET_THEME);
