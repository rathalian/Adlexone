<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Access;
use Adlexone\support\RenderNavigation;
use Adlexone\support\RenderViews;

/**
 * Fixed screens an application nav link can open.
 */
final class Capabilities
{
    /**
     * @return array<string, array{label: string, icon: string, config: string}>
     */
    public static function catalog(): array
    {
        return [
            'items.create' => ['label' => 'Create item', 'icon' => 'ic-create-ticket', 'config' => 'item_type'],
            'search.quick' => ['label' => 'Quick search', 'icon' => 'ic-quick-search', 'config' => 'none'],
            'search.advanced' => ['label' => 'Advanced search', 'icon' => 'ic-search', 'config' => 'item_type'],
            'search.saved' => ['label' => 'Saved search', 'icon' => 'ic-my-ticket-searches', 'config' => 'saved_search'],
            'search.saved_list' => ['label' => 'Saved search list', 'icon' => 'ic-my-ticket-searches', 'config' => 'none'],
            'announcements' => ['label' => 'Announcements', 'icon' => 'ic-announcements', 'config' => 'none'],
            'servicecentre.work' => ['label' => 'Service Centre work', 'icon' => 'ic-search', 'config' => 'none'],
            'servicecentre.settings' => ['label' => 'Service Centre settings', 'icon' => 'ic-settings', 'config' => 'none'],
            'knowledge.home' => ['label' => 'Knowledge home', 'icon' => 'ic-knowledgebase', 'config' => 'none'],
            'knowledge.settings' => ['label' => 'Knowledge settings', 'icon' => 'ic-kb-settings', 'config' => 'none'],
        ];
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
            'ic-quick-search',
            'ic-my-ticket-searches',
            'ic-new-article',
            'ic-article-search',
            'ic-settings',
            'ic-item-mgmt',
            'ic-manage-fields',
        ];
    }

    public static function label(string $capability): string
    {
        return self::catalog()[$capability]['label'] ?? $capability;
    }

    public static function icon(string $capability, string $chosen = ''): string
    {
        if ($chosen !== '' && in_array($chosen, self::icons(), true)) {
            return $chosen;
        }
        $icon = self::catalog()[$capability]['icon'] ?? 'ic-launch';
        return in_array($icon, self::icons(), true) ? $icon : 'ic-launch';
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $nav
     */
    public static function open(array $app, array $nav): void
    {
        $capability = (string) $nav['capability'];
        if (!isset(self::catalog()[$capability])) {
            RenderViews::buildResponse('This navigation link uses an unknown screen.');
            return;
        }

        $config = is_array($nav['config'] ?? null) ? $nav['config'] : [];
        self::defineContext($app, (int) $nav['nav_id']);
        self::applyDefaults($capability, $config, ApplicationStore::scopeKey($app));

        if ($capability === 'search.saved' && (string) ($_GET['id'] ?? '') === '' && (string) ($_GET['option'] ?? '') === 'saved_search') {
            RenderViews::buildResponse('Choose a saved search for this link in Manage.');
            return;
        }

        $shared = \Adlexone\Http\Router::engine();
        if ($shared !== null) {
            self::includeShared($shared);
            return;
        }

        match ($capability) {
            'items.create' => self::includeShared('item_management_manage'),
            'search.quick', 'search.advanced', 'search.saved', 'search.saved_list' => self::includeShared('search_management_manage'),
            'announcements', 'servicecentre.work', 'servicecentre.settings' => self::includeScreen(
                'applications/servicecentre/servicecentre.php'
            ),
            'knowledge.home', 'knowledge.settings' => self::includeKnowledge(),
            default => RenderViews::buildResponse('This screen is not available.'),
        };
    }

    /**
     * @param array<string, mixed> $app
     */
    public static function forwardShared(array $app, int $navId, string $subcontroller): void
    {
        self::defineContext($app, $navId);
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
            $_GET['option'] = match ($capability) {
                'items.create' => 'show_item_types',
                'search.quick' => 'show_quick_search',
                'search.advanced' => 'show_item_search',
                'search.saved' => 'saved_search',
                'search.saved_list' => 'show_saved_searches',
                'announcements' => 'show_announcements',
                'servicecentre.work' => 'show_work',
                'servicecentre.settings' => 'settings',
                'knowledge.home' => 'show_knowledge',
                'knowledge.settings' => 'knowledgebase_settings',
                default => '',
            };
        }

        if ($capability === 'items.create' && (string) ($_GET['default_item_type'] ?? '') === '' && (string) ($_GET['option'] ?? '') === 'show_item_types') {
            $itemType = trim((string) ($config['item_type_id'] ?? ''));
            if ($itemType !== '') {
                $_GET['default_item_type'] = $itemType;
            }
        }
        if ($capability === 'search.advanced' && (string) ($_GET['item_types'] ?? '') === '' && (string) ($_GET['option'] ?? '') === 'show_item_search') {
            $itemType = trim((string) ($config['item_type_id'] ?? ''));
            if ($itemType !== '') {
                $_GET['item_types'] = $itemType;
            }
        }
        if ($capability === 'search.saved' && (string) ($_GET['id'] ?? '') === '' && (string) ($_GET['option'] ?? '') === 'saved_search') {
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

    private static function includeScreen(string $relative): void
    {
        unset($_GET['subcontroller']);
        $file = SET_INSTALL_PATH . $relative;
        if (!is_file($file)) {
            RenderViews::buildResponse('This screen is missing.');
            return;
        }
        include $file;
    }

    private static function includeKnowledge(): void
    {
        if (!defined('KNOWLEDGEBASE_SET_KB_ITEM_TYPE')) {
            define('KNOWLEDGEBASE_SET_KB_ITEM_TYPE', (string) ($_GET['default_item_type'] ?? ''));
        }
        self::includeScreen('app/Http/Controllers/Applications/oneorzeroknowledgebase/controllers/app_oneorzeroknowledgebase_main.php');
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
