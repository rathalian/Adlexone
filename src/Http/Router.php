<?php
declare(strict_types=1);

namespace Adlexone\Http;

use Adlexone\support\RenderViews;

/**
 * One map from a request to an inlay function file.
 *
 * Home is index.php. Manage pages use manage. An application uses
 * application and nav. item and search name the record on that page.
 * Older controller and subcontroller names open the same screens.
 * A name that is not in the map is not included.
 */
final class Router
{
    public const ITEMS = 'item_management_manage';
    public const SEARCH = 'search_management_manage';

    /** @var list<string> */
    private const ITEM_OPTIONS = [
        'my_items',
        'show_item_types',
        'new_item',
        'add_item',
        'show_item',
        'log_entry',
        'add_log_entry',
        'delete_log',
        'update_item',
        'change_security',
        'update_security',
        'show_attachments',
        'add_attachment',
        'download_attachment',
        'delete_attachment',
        'delete_item',
        'transform_item',
    ];

    /** @var list<string> */
    private const SEARCH_OPTIONS = [
        'show_item_search',
        'show_search_results',
        'show_saved_searches',
        'saved_search',
        'delete_saved_search',
        'quick_search',
        'show_quick_search',
        'edit_saved_search',
        'update_saved_search',
    ];

    /**
     * Public or legacy name => canonical file key.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'quick_launch' => 'home',
        'print' => 'full_page_view',
        'manage' => 'manage_main',
        'applications' => 'manage_applications',
        'fields' => 'manage_fields',
        'item-types' => 'manage_item_types',
        'items' => 'manage_items',
        'workflow' => 'manage_actions',
        'security' => 'manage_security',
        'settings' => 'manage_settings',
        'portal' => 'manage_portal',
        'administration_main' => 'manage_main',
        'administration_applications' => 'manage_applications',
        'administration_item_settings' => 'manage_items',
        'administration_actions' => 'manage_actions',
        'administration_security' => 'manage_security',
        'administration_settings' => 'manage_settings',
        'administration_portal' => 'manage_portal',
    ];

    /**
     * Canonical name => path under the install root.
     *
     * @var array<string, string>
     */
    private const FILES = [
        'login' => 'inlay_functions/login.php',
        'home' => 'inlay_functions/home.php',
        'full_page_view' => 'inlay_functions/full_page_view.php',
        'application' => 'inlay_functions/application.php',
        'manage_main' => 'inlay_functions/manage/main.php',
        'manage_applications' => 'inlay_functions/manage/applications.php',
        'manage_fields' => 'inlay_functions/manage/fields.php',
        'manage_item_types' => 'inlay_functions/manage/item_types.php',
        'manage_items' => 'inlay_functions/manage/items.php',
        'manage_actions' => 'inlay_functions/manage/actions.php',
        'manage_security' => 'inlay_functions/manage/security.php',
        'manage_settings' => 'inlay_functions/manage/settings.php',
        self::ITEMS => 'inlay_functions/application_shared/items.php',
        self::SEARCH => 'inlay_functions/application_shared/search.php',
        'manage_portal' => 'inlay_functions/legacy/manage_portal.php',
        'all_actions' => 'inlay_functions/legacy/all_actions.php',
        'crm_management_manage' => 'inlay_functions/legacy/crm_management_manage.php',
        'dummy' => 'inlay_functions/legacy/dummy.php',
        'item_management_main' => 'inlay_functions/legacy/item_management_main.php',
        'search_management_main' => 'inlay_functions/legacy/search_management_main.php',
        'social_management_main' => 'inlay_functions/legacy/social_management_main.php',
        'social_management_manage' => 'inlay_functions/legacy/social_management_manage.php',
    ];

    public static function requested(): string
    {
        self::prepare();
        $manage = self::queryName((string) ($_GET['manage'] ?? ''));
        if ($manage !== '') {
            return $manage;
        }
        $screen = self::queryName((string) ($_GET['screen'] ?? ''));
        if ($screen !== '') {
            return $screen;
        }
        $controller = self::queryName((string) ($_GET['controller'] ?? ''));
        if ($controller !== '' && $controller !== 'application') {
            return $controller;
        }
        if ($controller === 'application' || trim((string) ($_GET['app'] ?? '')) !== '') {
            return 'application';
        }

        return 'home';
    }

    public static function matches(string $name): bool
    {
        return self::canonical(self::requested()) === self::canonical($name);
    }

    public static function bare(string $name): bool
    {
        return self::canonical($name) === 'full_page_view';
    }

    /**
     * Item or search file when the request names a record or an older engine parameter.
     */
    public static function engine(): ?string
    {
        self::prepare();
        $item = trim((string) ($_GET['item'] ?? ''));
        if ($item !== '') {
            if (trim((string) ($_GET['item_id'] ?? '')) === '') {
                $_GET['item_id'] = $item;
            }
            if (trim((string) ($_GET['option'] ?? '')) === '') {
                $_GET['option'] = 'show_item';
            }

            return self::ITEMS;
        }
        $search = trim((string) ($_GET['search'] ?? ''));
        if ($search !== '') {
            if (trim((string) ($_GET['id'] ?? '')) === '') {
                $_GET['id'] = $search;
            }
            if (trim((string) ($_GET['option'] ?? '')) === '') {
                $_GET['option'] = 'saved_search';
            }

            return self::SEARCH;
        }
        $engine = (string) ($_GET['engine'] ?? '');
        if ($engine === 'items') {
            return self::ITEMS;
        }
        if ($engine === 'search') {
            return self::SEARCH;
        }
        $sub = self::queryName((string) ($_GET['subcontroller'] ?? ''));
        if ($sub === self::ITEMS || $sub === self::SEARCH) {
            return $sub;
        }
        if (trim((string) ($_GET['item_id'] ?? '')) !== '') {
            return self::ITEMS;
        }
        $option = (string) ($_GET['option'] ?? '');
        if (in_array($option, self::ITEM_OPTIONS, true)) {
            return self::ITEMS;
        }
        if (in_array($option, self::SEARCH_OPTIONS, true)) {
            return self::SEARCH;
        }

        return null;
    }

    public static function applicationUrl(string $slug, int $navId = 0, string $query = ''): string
    {
        $url = 'index.php?application=' . rawurlencode($slug);
        if ($navId > 0) {
            $url .= '&nav=' . $navId;
        }
        if ($query !== '') {
            $url .= '&' . ltrim($query, '&');
        }

        return $url;
    }

    public static function manageUrl(string $name, string $query = ''): string
    {
        if ($name === '' || $name === 'home') {
            return $query === '' ? 'index.php' : 'index.php?' . ltrim($query, '&');
        }
        $url = 'index.php?manage=' . rawurlencode($name);
        if ($query !== '') {
            $url .= '&' . ltrim($query, '&');
        }

        return $url;
    }

    /**
     * Where a stored home controller should send the person after sign-in.
     */
    public static function homeTarget(string $stored): string
    {
        if (str_contains($stored, ':')) {
            [$name, $app] = explode(':', $stored, 2);
            if ($name === 'application') {
                return self::applicationUrl($app);
            }

            return 'index.php?controller=' . rawurlencode($stored);
        }
        $name = self::queryName($stored);
        $canonical = self::canonical($name);
        if ($name === '' || $canonical === 'home') {
            return 'index.php';
        }
        $manageKey = array_search($canonical, self::ALIASES, true);
        if (is_string($manageKey) && !str_starts_with($manageKey, 'administration_') && $manageKey !== 'manage' && $manageKey !== 'print' && $manageKey !== 'quick_launch') {
            return self::manageUrl($manageKey);
        }
        if (isset(self::ALIASES[$name]) && $name !== 'manage' && !str_starts_with($name, 'administration_')) {
            return self::manageUrl($name);
        }

        return 'index.php?controller=' . rawurlencode($canonical);
    }

    /**
     * Address for the next action on the screen that is already open.
     */
    public static function continueUrl(string $engine = ''): string
    {
        if (defined('APPLICATION_SLUG') && (string) APPLICATION_SLUG !== '') {
            $nav = defined('APPLICATION_NAV_ID') ? (int) APPLICATION_NAV_ID : 0;

            return self::applicationUrl((string) APPLICATION_SLUG, $nav);
        }

        $controller = self::canonical(self::queryName((string) ($_GET['controller'] ?? 'home')));
        $url = 'index.php?controller=' . rawurlencode($controller);
        if ($engine === 'items') {
            $url .= '&subcontroller=' . self::ITEMS;
        } elseif ($engine === 'search') {
            $url .= '&subcontroller=' . self::SEARCH;
        }

        return $url;
    }

    public static function open(string $name): void
    {
        self::prepare();
        $canonical = self::canonical(self::queryName($name));
        $file = self::path($canonical);
        if ($file === null) {
            RenderViews::buildResponse('This screen is not available.');
            return;
        }
        self::absorbRecord($canonical);
        if ((string) ($_GET['controller'] ?? '') === '' || $canonical !== self::queryName($name)) {
            $_GET['controller'] = $canonical;
        }
        include $file;
    }

    public static function path(string $name): ?string
    {
        $canonical = self::canonical(self::queryName($name));
        if ($canonical === '') {
            return null;
        }
        if (isset(self::FILES[$canonical])) {
            $file = SET_INSTALL_PATH . self::FILES[$canonical];

            return is_file($file) ? $file : null;
        }
        if (str_starts_with($canonical, 'app_')) {
            $app = explode('_', $canonical)[1] ?? '';
            $file = SET_INSTALL_PATH . 'app/Http/Controllers/Applications/' . $app . '/controllers/' . $canonical . '.php';

            return is_file($file) ? $file : null;
        }

        return null;
    }

    private static function prepare(): void
    {
        $application = trim((string) ($_GET['application'] ?? ''));
        if ($application !== '' && trim((string) ($_GET['app'] ?? '')) === '') {
            $_GET['app'] = $application;
        }
    }

    private static function absorbRecord(string $canonical): void
    {
        $item = trim((string) ($_GET['item'] ?? ''));
        if ($item !== '' && trim((string) ($_GET['item_id'] ?? '')) === '') {
            $_GET['item_id'] = $item;
        }
        $search = trim((string) ($_GET['search'] ?? ''));
        if ($search !== '' && trim((string) ($_GET['id'] ?? '')) === '') {
            $_GET['id'] = $search;
        }
        if ($canonical === 'full_page_view' && trim((string) ($_GET['option'] ?? '')) === '' && trim((string) ($_GET['item_id'] ?? '')) !== '') {
            $_GET['option'] = 'print_item';
        }
    }

    private static function canonical(string $name): string
    {
        return self::ALIASES[$name] ?? $name;
    }

    private static function queryName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        if ($name === '' || preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
            return '';
        }

        return $name;
    }
}
