<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Access;
use Adlexone\support\RenderNavigation;
use Adlexone\support\RenderViews;
use Adlexone\Http\Page;

/**
 * Screens an application nav link can open.
 *
 * Built-in screens are configured in Manage → Navigation. site/apps packs
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
            'origin' => ScreenCatalog::ORIGIN,
            'icon' => $icon,
            'config' => $config,
        ];

        return [
            ScreenCatalog::WORK => $frame('Work', 'ic-search', 'item_type'),
            ScreenCatalog::CREATE => $frame('New', 'ic-itemtype-add', 'item_type'),
            ScreenCatalog::SEARCH_QUICK => $frame('Quick search', 'ic-quick-search', 'none'),
            ScreenCatalog::SEARCH_ADVANCED => $frame('Search', 'ic-search', 'item_type'),
            ScreenCatalog::SEARCH_SAVED => $frame('Saved search', 'ic-search', 'saved_search'),
            ScreenCatalog::SEARCH_LIST => $frame('Searches', 'ic-search', 'none'),
            ScreenCatalog::ANNOUNCEMENTS => $frame('Announcements', 'ic-announcements', 'none'),
            ScreenCatalog::SETTINGS => $frame('Settings', 'ic-settings', 'none'),
        ];
    }

    public static function label(string $capability): string
    {
        $capability = ScreenCatalog::resolve($capability);
        return self::catalog()[$capability]['label'] ?? $capability;
    }

    public static function choiceLabel(string $capability): string
    {
        $capability = ScreenCatalog::resolve($capability);
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
        $capability = ScreenCatalog::resolve($capability);
        $icon = self::catalog()[$capability]['icon'] ?? 'ic-launch';
        return in_array($icon, self::icons(), true) ? $icon : 'ic-launch';
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $nav
     */
    public static function open(array $app, array $nav): void
    {
        $capability = ScreenCatalog::resolve((string) $nav['capability']);
        if (!isset(self::catalog()[$capability])) {
            RenderViews::buildResponse('This navigation link uses an unknown screen.');
            return;
        }

        $config = is_array($nav['config'] ?? null) ? $nav['config'] : [];
        self::defineContext($app, (int) $nav['nav_id']);
        ApplicationAccess::requireUse();
        self::applyDefaults($capability, $config, ApplicationStore::scopeKey($app));

        if ($capability === ScreenCatalog::SEARCH_SAVED && (string) ($_GET['id'] ?? '') === '' && (string) ($_GET['option'] ?? '') === 'saved_search') {
            RenderViews::buildResponse('Choose a saved search for this link in Manage.');
            return;
        }

        $shared = \Adlexone\Http\Router::engine();
        if ($shared !== null) {
            self::includeShared($shared);
            return;
        }

        if ($capability === ScreenCatalog::WORK) {
            self::openWork($config);
            return;
        }
        if ($capability === ScreenCatalog::ANNOUNCEMENTS) {
            \Adlexone\Screen\AnnouncementsScreen::open();
            return;
        }
        if ($capability === ScreenCatalog::SETTINGS) {
            \Adlexone\Screen\ApplicationSettingsScreen::open();
            return;
        }

        if (AppFunctions::has($capability)) {
            AppFunctions::open($capability);
            return;
        }

        match ($capability) {
            ScreenCatalog::CREATE => self::includeShared('item_management_manage'),
            ScreenCatalog::SEARCH_QUICK, ScreenCatalog::SEARCH_ADVANCED, ScreenCatalog::SEARCH_SAVED, ScreenCatalog::SEARCH_LIST => self::includeShared('search_management_manage'),
            default => RenderViews::buildResponse('This screen is not available.'),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function openWork(array $config): void
    {
        $itemType = trim((string) ($config['item_type_id'] ?? ''));
        if ($itemType === '' && Page::current()->inApplication()) {
            $itemType = ApplicationSettings::defaultItemTypeId(Page::current()->applicationSlug());
        }
        $typeId = $itemType !== '' && ctype_digit($itemType) ? (int) $itemType : null;
        \Adlexone\Screen\WorkScreen::showApplicationWork($typeId, 'Work');
    }

    /**
     * @param array<string, mixed> $app
     */
    public static function forwardShared(array $app, int $navId, string $subcontroller): void
    {
        self::defineContext($app, $navId);
        ApplicationAccess::requireUse();
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
                    ScreenCatalog::WORK => 'show_work',
                    ScreenCatalog::CREATE => 'show_item_types',
                    ScreenCatalog::SEARCH_QUICK => 'show_quick_search',
                    ScreenCatalog::SEARCH_ADVANCED => 'show_item_search',
                    ScreenCatalog::SEARCH_SAVED => 'saved_search',
                    ScreenCatalog::SEARCH_LIST => 'show_saved_searches',
                    ScreenCatalog::ANNOUNCEMENTS => 'show_announcements',
                    ScreenCatalog::SETTINGS => 'settings',
                    default => '',
                };
            }
        }

        $itemType = trim((string) ($config['item_type_id'] ?? ''));
        if ($itemType === '' && $scope !== '') {
            $itemType = ApplicationSettings::defaultItemTypeId($scope);
        }

        if ($capability === ScreenCatalog::CREATE) {
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
        if ($capability === ScreenCatalog::SEARCH_ADVANCED && (string) ($_GET['item_types'] ?? '') === '' && (string) ($_GET['option'] ?? '') === 'show_item_search') {
            if ($itemType !== '') {
                $_GET['item_types'] = $itemType;
            } elseif ($scope !== '') {
                $allowed = ApplicationSettings::allowedItemTypeIds($scope);
                if ($allowed !== []) {
                    $_GET['item_types'] = implode(',', $allowed);
                }
            }
        }
        if (
            in_array($capability, [ScreenCatalog::SEARCH_QUICK, ScreenCatalog::SEARCH_LIST, ScreenCatalog::SEARCH_SAVED], true)
            && (string) ($_GET['item_types'] ?? '') === ''
            && $itemType !== ''
        ) {
            $_GET['item_types'] = $itemType;
        }
        if ($capability === ScreenCatalog::SEARCH_SAVED && (string) ($_GET['id'] ?? '') === '' && (string) ($_GET['option'] ?? '') === 'saved_search') {
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
        Page::current()->enterApplication($slug, $navId);

        $base = \Adlexone\Http\Router::applicationUrl($slug, $navId);
        if (!defined('MAN_BASE_URL')) {
            define('MAN_BASE_URL', $base);
        }
        $_SESSION['application_slug'] = $slug;
    }

    private static function includeShared(string $controller): void
    {
        if ($controller === \Adlexone\Http\Router::ITEMS) {
            \Adlexone\Screen\ItemScreen::open();
            return;
        }
        if ($controller === \Adlexone\Http\Router::SEARCH) {
            \Adlexone\Screen\SearchScreen::open();
            return;
        }
        RenderViews::buildResponse('The shared screen is missing.');
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
