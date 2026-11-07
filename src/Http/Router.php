<?php
declare(strict_types=1);

namespace Adlexone\Http;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Screen\ApplicationScreen;
use Adlexone\Screen\ApplicationsScreen;
use Adlexone\Screen\BuilderScreen;
use Adlexone\Screen\FieldsScreen;
use Adlexone\Screen\HomeDashboard;
use Adlexone\Screen\HomeScreen;
use Adlexone\Screen\ItemScreen;
use Adlexone\Screen\ItemTypesScreen;
use Adlexone\Screen\LoginScreen;
use Adlexone\Screen\PaletteScreen;
use Adlexone\Screen\PrintScreen;
use Adlexone\Screen\SearchScreen;
use Adlexone\Screen\SecurityScreen;
use Adlexone\Screen\RulesScreen;
use Adlexone\Screen\SiteSettingsScreen;

/**
 * One map from a request to a screen class.
 *
 * Home is the site root. Manage pages use a public manage name. An application
 * uses application and nav. item and search name the record on that page.
 * A manage value that is not a public name opens home.
 */
final class Router
{
    public const ITEMS = 'records';
    public const SEARCH = 'search';

    /** @var list<string> */
    private const ITEM_OPTIONS = [
        'mine',
        'choose_type',
        'new_record',
        'add_record',
        'view',
        'note',
        'add_note',
        'delete_note',
        'update_record',
        'update_owner',
        'access',
        'update_access',
        'files',
        'add_file',
        'download_file',
        'delete_file',
        'delete_record',
        'transform',
    ];

    /** @var list<string> */
    private const SEARCH_OPTIONS = [
        'find',
        'results',
        'saved',
        'open_saved',
        'delete_saved',
        'run_quick',
        'quick',
        'edit_saved',
        'update_saved',
    ];

    /** @var list<string> */
    private const MANAGE = [
        'print',
        'records',
        'search',
        'activity',
        'assigned',
        'applications',
        'builder',
        'fields',
        'record-types',
        'rules',
        'security',
        'settings',
    ];

    public static function requested(): string
    {
        Request::prepare();
        $manage = self::queryName(Request::query('manage'));
        if ($manage !== '') {
            return self::isPublicManage($manage) ? $manage : 'home';
        }
        if (Request::has('application')) {
            return 'application';
        }

        return 'home';
    }

    public static function matches(string $name): bool
    {
        return self::requested() === self::queryName($name);
    }

    public static function bare(string $name): bool
    {
        return self::queryName($name) === 'print';
    }

    /**
     * Item or search screen when the request names a record or one of those actions.
     */
    public static function engine(): ?string
    {
        Request::prepare();
        $item = Request::query('item');
        if ($item !== '') {
            Request::fill('item_id', $item);
            Request::fill('option', 'view');

            return self::ITEMS;
        }
        $search = Request::query('search');
        if ($search !== '') {
            Request::fill('id', $search);
            Request::fill('option', 'open_saved');

            return self::SEARCH;
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
     * Where a stored home should send the person after sign-in.
     */
    public static function homeTarget(string $stored): string
    {
        if (str_contains($stored, ':')) {
            [$name, $app] = explode(':', $stored, 2);
            if ($name === 'application' && $app !== '') {
                return self::applicationUrl($app);
            }

            return self::page();
        }
        $name = self::queryName($stored);
        if ($name !== '' && self::isPublicManage($name)) {
            return self::manageUrl($name);
        }

        return self::page();
    }

    public static function open(string $name): void
    {
        Request::prepare();
        $canonical = self::queryName($name);
        $handler = self::handler($canonical);
        if ($handler === null) {
            View::respond('This page is not here.');
            return;
        }
        if (self::changesData() && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            View::respond('This change did not run because it was not sent from a page form. Go back and use the button on the page.');
            return;
        }
        if (
            ($canonical === self::ITEMS || $canonical === self::SEARCH)
            && (!Page::current()->inApplication())
            && !Access::can(Permission::ADMIN_ITEMS)
        ) {
            Access::deny();
        }
        self::fillRecord($canonical);
        $handler();
    }

    /**
     * Options that write data are accepted only on a form submission, so a link or an image cannot trigger them.
     * The same names are posted by layouts/js/app.js when the control is still a link.
     */
    public static function writesData(string $option): bool
    {
        if (preg_match('/^(add|update|delete|move)_[a-z_]+$/', $option) === 1) {
            return true;
        }

        return in_array($option, [
            'add', 'update', 'delete', 'move', 'create', 'import', 'transform',
            'toggle_rule', 'duplicate_rule', 'try_rule', 'run_rule',
        ], true);
    }

    private static function changesData(): bool
    {
        return self::writesData(Request::query('option'));
    }

    private static function handler(string $canonical): ?callable
    {
        return match ($canonical) {
            'login' => [LoginScreen::class, 'open'],
            'home' => [HomeScreen::class, 'open'],
            HomeDashboard::ACTIVITY => [HomeScreen::class, 'activity'],
            HomeDashboard::ASSIGNED => [HomeScreen::class, 'assigned'],
            'palette' => [PaletteScreen::class, 'open'],
            'print' => [PrintScreen::class, 'open'],
            'application' => [ApplicationScreen::class, 'open'],
            'applications' => [ApplicationsScreen::class, 'open'],
            'builder' => [BuilderScreen::class, 'open'],
            'fields' => [FieldsScreen::class, 'open'],
            'record-types' => [ItemTypesScreen::class, 'open'],
            'rules' => [RulesScreen::class, 'open'],
            'security' => [SecurityScreen::class, 'open'],
            'settings' => [SiteSettingsScreen::class, 'open'],
            self::ITEMS => [ItemScreen::class, 'open'],
            self::SEARCH => [SearchScreen::class, 'open'],
            default => null,
        };
    }

    private static function fillRecord(string $canonical): void
    {
        if (Request::has('item')) {
            Request::fill('item_id', Request::query('item'));
        }
        if (Request::has('search')) {
            Request::fill('id', Request::query('search'));
        }
        if ($canonical === 'print' && Request::has('item_id')) {
            Request::fill('option', 'print');
        }
    }

    private static function isPublicManage(string $name): bool
    {
        return $name === 'palette' || in_array($name, self::MANAGE, true);
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
