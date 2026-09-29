<?php
declare(strict_types=1);

use Adlexone\support\RenderViews;

/**
 * The ticket list is a saved search. This screen sends that request to the
 * shared search controller, or shows the empty state when none is configured.
 */
function showServiceCentreTickets(): void
{
    $id = (string) ($_GET['id'] ?? '');
    if ($id === '' && defined('SERVICECENTRE_SET_SAVED_SEARCH') && ctype_digit((string) SERVICECENTRE_SET_SAVED_SEARCH)) {
        $id = (string) SERVICECENTRE_SET_SAVED_SEARCH;
    }

    if ($id !== '' && ctype_digit($id)) {
        header('Location: ' . serviceCentreUrl(
            'subcontroller=search_management_manage&option=saved_search&id=' . rawurlencode($id)
        ));
        exit;
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        [
            'title' => APP_SC_TXT_1,
            'html' => '<p class="record-list__empty">' . htmlspecialchars(APP_SC_TXT_84, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<div class="form-actions">'
                . RenderViews::buildURL(serviceCentreUrl('subcontroller=search_management_manage&option=show_quick_search'), APP_SC_TXT_62, '', 'btn btn--primary btn--sm')
                . RenderViews::buildURL(serviceCentreUrl('subcontroller=search_management_manage&option=show_saved_searches'), APP_SC_TXT_60, '', 'btn btn--sm')
                . '</div>',
        ],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}
