<?php
declare(strict_types=1);

namespace Adlexone\Http;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Screen\ApplicationScreen;
use Adlexone\Screen\ApplicationsScreen;
use Adlexone\Screen\BuilderScreen;
use Adlexone\Screen\FieldsScreen;
use Adlexone\Screen\HomeScreen;
use Adlexone\Screen\ItemScreen;
use Adlexone\Screen\ItemTypesScreen;
use Adlexone\Screen\LoginScreen;
use Adlexone\Screen\PrintScreen;
use Adlexone\Screen\SearchScreen;
use Adlexone\Screen\SecurityScreen;
use Adlexone\Screen\RulesScreen;
use Adlexone\Screen\SiteSettingsScreen;

/**
 * One map from a request to a screen class.
 *
 * Home is the site root. Manage pages use manage. An application uses
 * application and nav. item and search name the record on that page.
 * Older controller and subcontroller names open the same screens.
 * A name that is not in the map is not opened.
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
     * Public or older name => canonical screen key.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'quick_launch' => 'home',
        'print' => 'full_page_view',
        'manage' => 'manage_main',
        'applications' => 'manage_applications',
        'builder' => 'manage_builder',
        'fields' => 'manage_fields',
        'item-types' => 'manage_item_types',
        'items' => 'manage_items',
        'rules' => 'manage_actions',
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

    public static function requested(): string
    {
        Request::prepare();
        $manage = self::queryName(Request::query('manage'));
        if ($manage !== '') {
            return $manage;
        }
        $screen = self::queryName(Request::query('screen'));
        if ($screen !== '') {
            return $screen;
        }
        $controller = self::queryName(Request::query('controller'));
        if ($controller !== '' && $controller !== 'application') {
            return $controller;
        }
        if ($controller === 'application' || Request::has('app')) {
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
        Request::prepare();
        $item = Request::query('item');
        if ($item !== '') {
            Request::fill('item_id', $item);
            Request::fill('option', 'show_item');

            return self::ITEMS;
        }
        $search = Request::query('search');
        if ($search !== '') {
            Request::fill('id', $search);
            Request::fill('option', 'saved_search');

            return self::SEARCH;
        }
        $engine = Request::query('engine');
        if ($engine === 'items') {
            return self::ITEMS;
        }
        if ($engine === 'search') {
            return self::SEARCH;
        }
        $sub = self::queryName(Request::query('subcontroller'));
        if ($sub === self::ITEMS || $sub === self::SEARCH) {
            return $sub;
        }
        if (Request::has('item_id')) {
            return self::ITEMS;
        }
        $option = Request::query('option');
        if (in_array($option, self::ITEM_OPTIONS, true)) {
            return self::ITEMS;
        }
        if (in_array($option, self::SEARCH_OPTIONS, true)) {
            return self::SEARCH;
        }

        return null;
    }

    /**
     * Relative address for the browser. Home is ./ so the script name stays out of the bar.
     */
    public static function page(string $query = ''): string
    {
        $query = ltrim($query, '?');

        return $query === '' ? './' : '?' . $query;
    }

    public static function absolute(string $page): string
    {
        $base = rtrim((string) BASE_URL, '/');
        if ($page === '' || $page === './' || $page === 'index.php') {
            return $base . '/';
        }
        if (str_starts_with($page, 'index.php?')) {
            $page = '?' . substr($page, strlen('index.php?'));
        }
        if (str_starts_with($page, '?')) {
            return $base . '/' . $page;
        }
        if (str_starts_with($page, 'http://') || str_starts_with($page, 'https://')) {
            return $page;
        }

        return $base . '/' . ltrim($page, '/');
    }

    public static function applicationUrl(string $slug, int $navId = 0, string $query = ''): string
    {
        $url = '?application=' . rawurlencode($slug);
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
            return $query === '' ? self::page() : self::page(ltrim($query, '&'));
        }
        $url = '?manage=' . rawurlencode($name);
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

            return '?controller=' . rawurlencode($stored);
        }
        $name = self::queryName($stored);
        $canonical = self::canonical($name);
        if ($name === '' || $canonical === 'home') {
            return self::page();
        }
        $manageKey = array_search($canonical, self::ALIASES, true);
        if (is_string($manageKey) && !str_starts_with($manageKey, 'administration_') && $manageKey !== 'manage' && $manageKey !== 'print' && $manageKey !== 'quick_launch') {
            return self::manageUrl($manageKey);
        }
        if (isset(self::ALIASES[$name]) && $name !== 'manage' && !str_starts_with($name, 'administration_')) {
            return self::manageUrl($name);
        }

        return '?controller=' . rawurlencode($canonical);
    }

    /**
     * Address for the next action on the screen that is already open.
     */
    public static function continueUrl(string $engine = ''): string
    {
        if (Page::current()->inApplication()) {
            return self::applicationUrl(Page::current()->applicationSlug(), Page::current()->navId());
        }

        $controller = self::canonical(self::queryName(Request::query('controller', 'home')));
        $url = '?controller=' . rawurlencode($controller);
        if ($engine === 'items') {
            $url .= '&subcontroller=' . self::ITEMS;
        } elseif ($engine === 'search') {
            $url .= '&subcontroller=' . self::SEARCH;
        }

        return $url;
    }

    public static function open(string $name): void
    {
        Request::prepare();
        $canonical = self::canonical(self::queryName($name));
        if ($canonical === 'manage_main') {
            $sub = self::queryName(Request::query('subcontroller'));
            $forward = [
                'manage_security',
                'manage_fields',
                'manage_item_types',
                'manage_items',
                'manage_actions',
                'manage_settings',
            ];
            if (in_array($sub, $forward, true)) {
                self::open($sub);
                return;
            }
            header('Location: ./');
            exit;
        }
        if ($canonical === 'manage_items') {
            $option = Request::query('option');
            $typeOptions = [
                'new_item_type',
                'add_item_type',
                'modify_item_type',
                'update_item_type',
                'manage_item_types',
                'delete_item_type',
            ];
            if (in_array($option, $typeOptions, true)) {
                Request::set('manage', 'item-types');
                self::open('manage_item_types');
                return;
            }
            Request::set('manage', 'fields');
            Request::fill('option', 'manage_fields');
            self::open('manage_fields');
            return;
        }
        $handler = self::handler($canonical);
        if ($handler === null) {
            View::respond('This screen is not available.');
            return;
        }
        if (
            ($canonical === self::ITEMS || $canonical === self::SEARCH)
            && (!Page::current()->inApplication())
            && !Access::can(Permission::ADMIN_ITEMS)
        ) {
            Access::deny();
        }
        self::absorbRecord($canonical);
        if (!Request::has('controller') || $canonical !== self::queryName($name)) {
            Request::set('controller', $canonical);
        }
        $handler();
    }

    private static function handler(string $canonical): ?callable
    {
        return match ($canonical) {
            'login' => [LoginScreen::class, 'open'],
            'home' => [HomeScreen::class, 'open'],
            'full_page_view' => [PrintScreen::class, 'open'],
            'application' => [ApplicationScreen::class, 'open'],
            'manage_applications' => [ApplicationsScreen::class, 'open'],
            'manage_builder' => [BuilderScreen::class, 'open'],
            'manage_fields' => [FieldsScreen::class, 'open'],
            'manage_item_types' => [ItemTypesScreen::class, 'open'],
            'manage_actions' => [RulesScreen::class, 'open'],
            'manage_security' => [SecurityScreen::class, 'open'],
            'manage_settings' => [SiteSettingsScreen::class, 'open'],
            self::ITEMS => [ItemScreen::class, 'open'],
            self::SEARCH => [SearchScreen::class, 'open'],
            default => null,
        };
    }

    private static function absorbRecord(string $canonical): void
    {
        if (Request::has('item')) {
            Request::fill('item_id', Request::query('item'));
        }
        if (Request::has('search')) {
            Request::fill('id', Request::query('search'));
        }
        if ($canonical === 'full_page_view' && Request::has('item_id')) {
            Request::fill('option', 'print_item');
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
