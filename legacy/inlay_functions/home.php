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
        . '<span class="launch-tile__icon" aria-hidden="true"><svg class="icon"><use href="' . htmlspecialchars(\Adlexone\Theme\Theme::spriteHref(), ENT_QUOTES, 'UTF-8') . '#' . $iconEsc . '"></use></svg></span>'
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
if (Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
    $manage[] = launchTile(
        'index.php?manage=builder',
        'Create application',
        'Guided builder for a new business app',
        'ic-itemtype-add'
    );
}
if (Access::can(Permission::ADMIN_SETTINGS)) {
    $manage[] = launchTile(
        'index.php?manage=applications',
        'Applications',
        'Arrange applications and navigation',
        'ic-launch'
    );
}
if (Access::can(Permission::ADMIN_ITEMS)) {
    $manage[] = launchTile(
        'index.php?manage=fields',
        'Fields',
        'Custom fields and menus',
        'ic-manage-fields'
    );
    $manage[] = launchTile(
        'index.php?manage=item-types',
        'Item types',
        'Types and their fields',
        'ic-manage-item-types'
    );
}
if (Access::can(Permission::ADMIN_ACTIONS)) {
    $manage[] = launchTile(
        'index.php?manage=workflow&option=show_defined_actions',
        'Rules',
        'What happens when a record changes',
        'ic-manage-actions'
    );
}
if (Access::can(Permission::ADMIN_SECURITY)) {
    $manage[] = launchTile(
        'index.php?manage=security&option=manage_users',
        'Security',
        'Users and groups',
        'ic-manage-users'
    );
}
if (Access::can(Permission::ADMIN_SETTINGS)) {
    $manage[] = launchTile(
        'index.php?manage=settings&option=adlexone_settings',
        'Settings',
        'Application setup',
        'ic-system-settings'
    );
} elseif (Access::can(Permission::ADMIN_SYSTEM)) {
    $manage[] = launchTile(
        'index.php?manage=settings&option=sign_in_settings',
        'Settings',
        'Sign-in and system setup',
        'ic-system-settings'
    );
}

$intro = '';
if ($applications === [] && Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
    $introCopy = defined('SET_HOME_LEAD') && trim((string) SET_HOME_LEAD) !== ''
        ? (string) SET_HOME_LEAD
        : 'Create the records, rules, and screens your organisation works with every day.';
    $art = \Adlexone\Theme\Theme::illustrationHref('empty-apps');
    $intro = '<section class="launchpad__intro launchpad__intro--illustrated">'
        . '<div class="empty-state empty-state--brand">'
        . ($art !== '' ? '<img class="empty-state__art" src="' . htmlspecialchars($art, ENT_QUOTES, 'UTF-8') . '" alt="" width="160" height="120" loading="lazy">' : '')
        . '<div class="brandwrap launchpad__brand">'
        . '<span class="logoA"><svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<rect width="32" height="32" rx="7" fill="var(--ink)"/>'
        . '<rect x="6.5" y="6.5" width="19" height="19" rx="1.5" fill="var(--stone)"/>'
        . '<rect x="6.5" y="13.25" width="19" height="5.5" fill="var(--brand)"/>'
        . '</svg></span>'
        . '<span class="brandtext"><span class="title-static">Inlay</span><span class="brandmaker">Adlexone</span></span>'
        . '</div>'
        . '<p class="empty-state__copy">' . htmlspecialchars($introCopy, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<div class="empty-state__actions"><a class="btn btn--primary" href="index.php?manage=builder">Create a business application</a></div>'
        . '</div></section>';
}

$html = '<div class="launchpad">'
    . $intro
    . launchGroup('Applications', $applications)
    . launchGroup('Manage', $manage)
    . '</div>';

if ($applications === [] && $manage === [] && $intro === '') {
    $html = RenderViews::buildEmptyState([
        'illustration' => 'empty-apps',
        'title' => 'Nothing is available for this account',
        'copy' => 'Ask an administrator to grant access to an application.',
    ]);
}

define('BODY_CONTENT', $html);
RenderViews::renderThemePage('main_page_content', SET_THEME);
