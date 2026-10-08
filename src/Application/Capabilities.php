<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Access;
use Adlexone\FrameOne\AppAccess;
use Adlexone\FrameOne\AppSettings;
use Adlexone\FrameOne\Library;
use Adlexone\support\RenderNavigation;
use Adlexone\support\RenderViews;

/**
 * Screens an application nav link can open.
 *
 * FrameOne built-ins are configured in Manage → Navigation. site/apps packs
 * remain optional for rare custom screens.
 */
final class Capabilities
{
    /**
     * @return array<string, array{label: string, origin: string, icon: string, config: string}>
     */
    public static function catalog(): array
    {
        return array_merge(self::builtIn(), AppFunctions::catalog());
    }

    /**
     * @return array<string, array{label: string, origin: string, icon: string, config: string}>
     */
    private static function builtIn(): array
    {
        $frame = static fn (string $label, string $icon, string $config): array => [
            'label' => $label,
            'origin' => Library::ORIGIN,
            'icon' => $icon,
            'config' => $config,
        ];

        return [
            Library::WORK => $frame('Work list (my items)', 'ic-search', 'item_type'),
            Library::CREATE => $frame('Create item', 'ic-itemtype-add', 'item_type'),
            Library::SEARCH_QUICK => $frame('Quick search', 'ic-quick-search', 'none'),
            Library::SEARCH_ADVANCED => $frame('Advanced search', 'ic-search', 'item_type'),
            Library::SEARCH_SAVED => $frame('Saved search', 'ic-search', 'saved_search'),
            Library::SEARCH_LIST => $frame('Saved search list', 'ic-search', 'none'),
            Library::ANNOUNCEMENTS => $frame('Announcements', 'ic-announcements', 'none'),
            Library::SETTINGS => $frame('Application settings', 'ic-settings', 'none'),
        ];
    }

    public static function label(string $capability): string
    {
        $capability = Library::resolve($capability);
        return self::catalog()[$capability]['label'] ?? $capability;
    }

    public static function choiceLabel(string $capability): string
    {
        $capability = Library::resolve($capability);
        $meta = self::catalog()[$capability] ?? null;
        if ($meta === null) {
            return $capability;
        }
        return $meta['label'] . ' — ' . $meta['origin'];
    }

    /**
     * @return list<string>
     */
    public static function choiceKeys(): array
    {
        $catalog = self::catalog();
        $keys = array_keys($catalog);
        usort($keys, static function (string $left, string $right) use ($catalog): int {
            $origin = ($catalog[$left]['origin'] ?? '') <=> ($catalog[$right]['origin'] ?? '');
            if ($origin !== 0) {
                return $origin;
            }
            return ($catalog[$left]['label'] ?? $left) <=> ($catalog[$right]['label'] ?? $right);
        });
        return $keys;
    }

    /**
     * @return list<string>
     */
    public static function icons(): array
    {
        return [
            'ic-launch',
            'ic-servicecentre',
            'ic-knowledgebase',
            'ic-search',
            'ic-time',
            'ic-announcements',
            'ic-create-ticket',
            'ic-itemtype-add',
            'ic-quick-search',
            'ic-my-ticket-searches',
            'ic-new-article',
            'ic-article-search',
            'ic-settings',
            'ic-item-mgmt',
            'ic-manage-fields',
        ];
    }

    public static function icon(string $capability, string $chosen = ''): string
    {
        if ($chosen !== '' && in_array($chosen, self::icons(), true)) {
            return $chosen;
        }
        $capability = Library::resolve($capability);
        $icon = self::catalog()[$capability]['icon'] ?? 'ic-launch';
        return in_array($icon, self::icons(), true) ? $icon : 'ic-launch';
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $nav
     */
    public static function open(array $app, array $nav): void
    {
        $capability = Library::resolve((string) $nav['capability']);
        if (!isset(self::catalog()[$capability])) {
            RenderViews::buildResponse('This navigation link uses an unknown screen.');
            return;
        }

        $config = is_array($nav['config'] ?? null) ? $nav['config'] : [];
        self::defineContext($app, (int) $nav['nav_id']);
        AppAccess::requireUse();
        self::applyDefaults($capability, $config, ApplicationStore::scopeKey($app));

        if ($capability === Library::SEARCH_SAVED && (string) ($_GET['id'] ?? '') === '' && (string) ($_GET['option'] ?? '') === 'saved_search') {
            RenderViews::buildResponse('Choose a saved search for this link in Manage.');
            return;
        }

        $shared = \Adlexone\Http\Router::engine();
        if ($shared !== null) {
            self::includeShared($shared);
            return;
        }

        if ($capability === Library::WORK) {
            self::openWork($config);
            return;
        }
        if ($capability === Library::ANNOUNCEMENTS) {
            self::includeFrameOne('announcements.php', 'openFrameOneAnnouncements');
            return;
        }
        if ($capability === Library::SETTINGS) {
            self::includeFrameOne('settings.php', 'openFrameOneAppSettings');
            return;
        }

        if (AppFunctions::has($capability)) {
            AppFunctions::open($capability);
            return;
        }

        match ($capability) {
            Library::CREATE => self::includeShared('item_management_manage'),
            Library::SEARCH_QUICK, Library::SEARCH_ADVANCED, Library::SEARCH_SAVED, Library::SEARCH_LIST => self::includeShared('search_management_manage'),
            default => RenderViews::buildResponse('This screen is not available.'),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function openWork(array $config): void
    {
        $file = SET_INSTALL_PATH . 'inlay_functions/application_shared/work.php';
        if (!is_file($file)) {
            RenderViews::buildResponse('The work screen is missing.');
            return;
        }
        include_once $file;
        $itemType = trim((string) ($config['item_type_id'] ?? ''));
        if ($itemType === '' && defined('APPLICATION_SLUG')) {
            $itemType = AppSettings::defaultItemTypeId((string) APPLICATION_SLUG);
        }
        $typeId = $itemType !== '' && ctype_digit($itemType) ? (int) $itemType : null;
        showApplicationWork($typeId, 'Work');
    }

    private static function includeFrameOne(string $file, string $opener): void
    {
        $path = SET_INSTALL_PATH . 'inlay_functions/frameone/' . $file;
        if (!is_file($path)) {
            RenderViews::buildResponse('This FrameOne screen is missing.');
            return;
        }
        include_once $path;
        if (!function_exists($opener)) {
            RenderViews::buildResponse('This FrameOne screen is missing.');
            return;
        }
        $opener();
    }

    /**
     * @param array<string, mixed> $app
     */
    public static function forwardShared(array $app, int $navId, string $subcontroller): void
    {
        self::defineContext($app, $navId);
        AppAccess::requireUse();
        if (($_GET['application'] ?? '') === '') {
            $_GET['application'] = ApplicationStore::scopeKey($app);
        }
        self::includeShared($subcontroller);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function applyDefaults(string $capability, array $config, string $scope): void
    {
        $option = (string) ($_GET['option'] ?? '');
        if ($option === '') {
            $fromPack = AppFunctions::defaultOption($capability);
            if ($fromPack !== '') {
                $_GET['option'] = $fromPack;
            } else {
                $_GET['option'] = match ($capability) {
                    Library::WORK => 'show_work',
                    Library::CREATE => 'show_item_types',
                    Library::SEARCH_QUICK => 'show_quick_search',
                    Library::SEARCH_ADVANCED => 'show_item_search',
                    Library::SEARCH_SAVED => 'saved_search',
                    Library::SEARCH_LIST => 'show_saved_searches',
                    Library::ANNOUNCEMENTS => 'show_announcements',
                    Library::SETTINGS => 'settings',
                    default => '',
                };
            }
        }

        $itemType = trim((string) ($config['item_type_id'] ?? ''));
        if ($itemType === '' && $scope !== '') {
            $itemType = AppSettings::defaultItemTypeId($scope);
        }

        if ($capability === Library::CREATE) {
            if ($itemType !== '' && (string) ($_GET['default_item_type'] ?? '') === '') {
                $_GET['default_item_type'] = $itemType;
            }
            // Single configured type: skip the picker and open Create directly.
            if (
                $itemType !== ''
                && (string) ($_GET['option'] ?? '') === 'show_item_types'
                && (string) ($_GET['item_type_id'] ?? '') === ''
            ) {
                $_GET['option'] = 'new_item';
                $_GET['item_type_id'] = $itemType;
            }
        }
        if ($capability === Library::SEARCH_ADVANCED && (string) ($_GET['item_types'] ?? '') === '' && (string) ($_GET['option'] ?? '') === 'show_item_search') {
            if ($itemType !== '') {
                $_GET['item_types'] = $itemType;
            } elseif ($scope !== '') {
                $allowed = AppSettings::allowedItemTypeIds($scope);
                if ($allowed !== []) {
                    $_GET['item_types'] = implode(',', $allowed);
                }
            }
        }
        if (
            in_array($capability, [Library::SEARCH_QUICK, Library::SEARCH_LIST, Library::SEARCH_SAVED], true)
            && (string) ($_GET['item_types'] ?? '') === ''
            && $itemType !== ''
        ) {
            $_GET['item_types'] = $itemType;
        }
        if ($capability === Library::SEARCH_SAVED && (string) ($_GET['id'] ?? '') === '' && (string) ($_GET['option'] ?? '') === 'saved_search') {
            $_GET['id'] = trim((string) ($config['search_id'] ?? ''));
        }
        if (str_starts_with($capability, 'search.') && (string) ($_GET['application'] ?? '') === '') {
            $_GET['application'] = $scope;
        }
    }

    /**
     * @param array<string, mixed> $app
     */
    private static function defineContext(array $app, int $navId): void
    {
        $slug = (string) $app['slug'];
        if (!defined('APPLICATION_SLUG')) {
            define('APPLICATION_SLUG', $slug);
        }
        if (!defined('APPLICATION_NAV_ID')) {
            define('APPLICATION_NAV_ID', $navId);
        }

        $base = \Adlexone\Http\Router::applicationUrl($slug, $navId);
        if (!defined('MAN_BASE_URL')) {
            define('MAN_BASE_URL', $base);
        }
        if (!defined('CONTROLLER_BASEURL')) {
            define('CONTROLLER_BASEURL', $base);
        }
        $_SESSION['application_slug'] = $slug;
    }

    private static function includeShared(string $controller): void
    {
        $file = \Adlexone\Http\Router::path($controller) ?? '';
        if (!is_file($file)) {
            RenderViews::buildResponse('The shared screen is missing.');
            return;
        }
        include $file;
    }

    /**
     * @param array<string, mixed> $app
     * @param list<array<string, mixed>> $links
     */
    public static function sectionNavigation(array $app, array $links): void
    {
        $html = '';
        foreach ($links as $link) {
            if (!self::linkVisible($link)) {
                continue;
            }
            $href = \Adlexone\Http\Router::applicationUrl((string) $app['slug'], (int) $link['nav_id']);
            $icon = self::icon((string) $link['capability'], (string) $link['icon']);
            $html .= RenderViews::buildURL($href, (string) $link['label'], $icon);
        }
        RenderNavigation::applySectionNav((string) $app['name'], $html);
    }

    /**
     * @param array<string, mixed> $link
     */
    public static function linkVisible(array $link): bool
    {
        $permission = trim((string) ($link['permission'] ?? ''));
        return $permission === '' || Access::can($permission);
    }
}
