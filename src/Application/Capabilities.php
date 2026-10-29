<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Auth\Access;
use Adlexone\Screen\RecordScreens;
use Adlexone\support\RenderNavigation;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
use Adlexone\Html\Blocks;
use Adlexone\Http\View;

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
            ScreenCatalog::WORK => $frame('Work', 'ic-work', 'item_type'),
            ScreenCatalog::CREATE => $frame('New', 'ic-plus', 'item_type'),
            ScreenCatalog::SEARCH_QUICK => $frame('Quick search', 'ic-zoom', 'none'),
            ScreenCatalog::SEARCH_ADVANCED => $frame('Search', 'ic-search', 'item_type'),
            ScreenCatalog::SEARCH_SAVED => $frame('Saved search', 'ic-saved', 'saved_search'),
            ScreenCatalog::SEARCH_LIST => $frame('Saved', 'ic-saved', 'none'),
            ScreenCatalog::ANNOUNCEMENTS => $frame('Announcements', 'ic-announce', 'none'),
            ScreenCatalog::SETTINGS => $frame('Settings', 'ic-sliders', 'none'),
            ScreenCatalog::COUNTS => $frame('Counts', 'ic-counts', 'field'),
            ScreenCatalog::OWNERS => $frame('Owners', 'ic-owners', 'item_type'),
            ScreenCatalog::STEPS => $frame('Next steps', 'ic-steps', 'field'),
            ScreenCatalog::DIARY => $frame('Diary', 'ic-diary', 'field'),
            ScreenCatalog::PARTIES => $frame('Parties', 'ic-parties', 'field'),
            ScreenCatalog::ACTIVITY => $frame('Activity', 'ic-activity', 'activity'),
            ScreenCatalog::FILES => $frame('Files', 'ic-files', 'item_type'),
            ScreenCatalog::AGE => $frame('Ageing', 'ic-ageing', 'field'),
            ScreenCatalog::REGISTER => $frame('Register', 'ic-register', 'register'),
            ScreenCatalog::INTAKE => $frame('Intake', 'ic-intake', 'intake'),
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
     * Icons offered when choosing one for an application or a screen link.
     *
     * @return array<string, string> icon id => label
     */
    public static function iconChoices(): array
    {
        return [
            'ic-grid' => 'General',
            'ic-briefcase' => 'Matters',
            'ic-home' => 'Property',
            'ic-shield' => 'Cover',
            'ic-wrench' => 'Repairs',
            'ic-complaint' => 'Complaints',
            'ic-landmark' => 'Committee',
            'ic-audit' => 'Audit',
            'ic-alert' => 'Incidents',
            'ic-user' => 'People',
            'ic-contract' => 'Contracts',
            'ic-headset' => 'Service',
            'ic-book' => 'Knowledge',
            'ic-scale' => 'Legal',
            'ic-building' => 'Premises',
            'ic-heart' => 'Care',
            'ic-truck' => 'Transport',
            'ic-graduation' => 'Training',
            'ic-wallet' => 'Finance',
            'ic-flag' => 'Projects',
            'ic-globe' => 'Web',
            'ic-bell' => 'Alerts',
            'ic-work' => 'Work',
            'ic-plus' => 'New',
            'ic-counts' => 'Counts',
            'ic-owners' => 'Owners',
            'ic-steps' => 'Next steps',
            'ic-diary' => 'Diary',
            'ic-parties' => 'Parties',
            'ic-activity' => 'Activity',
            'ic-files' => 'Files',
            'ic-ageing' => 'Ageing',
            'ic-register' => 'Register',
            'ic-intake' => 'Intake',
            'ic-saved' => 'Saved',
            'ic-announce' => 'News',
            'ic-search' => 'Search',
            'ic-zoom' => 'Quick find',
            'ic-sliders' => 'Settings',
            'ic-clock' => 'Time',
        ];
    }

    /**
     * Older icon ids still stored on applications and links. Each one draws the
     * nearest current icon, so a stored value keeps working.
     *
     * @return array<string, string> older id => current id
     */
    public static function renamedIcons(): array
    {
        return [
            'ic-launch' => 'ic-grid',
            'ic-servicecentre' => 'ic-headset',
            'ic-knowledgebase' => 'ic-book',
            'ic-time' => 'ic-clock',
            'ic-announcements' => 'ic-announce',
            'ic-create-ticket' => 'ic-plus',
            'ic-itemtype-add' => 'ic-plus',
            'ic-quick-search' => 'ic-zoom',
            'ic-my-ticket-searches' => 'ic-owners',
            'ic-new-article' => 'ic-files',
            'ic-article-search' => 'ic-search',
            'ic-settings' => 'ic-sliders',
            'ic-item-mgmt' => 'ic-register',
            'ic-subjects' => 'ic-work',
            'ic-manage-fields' => 'ic-register',
        ];
    }

    /**
     * @return list<string>
     */
    public static function icons(): array
    {
        return array_keys(self::iconChoices());
    }

    public static function iconLabel(string $icon): string
    {
        $icon = self::renamedIcons()[$icon] ?? $icon;

        return self::iconChoices()[$icon] ?? $icon;
    }

    public static function icon(string $capability, string $chosen = ''): string
    {
        $chosen = self::renamedIcons()[$chosen] ?? $chosen;
        if ($chosen !== '' && in_array($chosen, self::icons(), true)) {
            return $chosen;
        }
        $capability = ScreenCatalog::resolve($capability);
        $icon = self::catalog()[$capability]['icon'] ?? 'ic-grid';
        $icon = self::renamedIcons()[$icon] ?? $icon;

        return in_array($icon, self::icons(), true) ? $icon : 'ic-grid';
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $nav
     */
    public static function open(array $app, array $nav): void
    {
        $capability = ScreenCatalog::resolve((string) $nav['capability']);
        if (!isset(self::catalog()[$capability])) {
            View::respond('This navigation link uses an unknown screen.');
            return;
        }

        $config = is_array($nav['config'] ?? null) ? $nav['config'] : [];
        self::defineContext($app, (int) $nav['nav_id']);
        ApplicationAccess::requireUse();
        self::applyDefaults($capability, $config, ApplicationStore::scopeKey($app));

        if ($capability === ScreenCatalog::SEARCH_SAVED && !Request::has('id') && Request::query('option') === 'saved_search') {
            View::respond('Choose a saved search for this link under Configure, Applications.');
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
        if (in_array($capability, [
            ScreenCatalog::COUNTS,
            ScreenCatalog::OWNERS,
            ScreenCatalog::STEPS,
            ScreenCatalog::DIARY,
            ScreenCatalog::PARTIES,
            ScreenCatalog::ACTIVITY,
            ScreenCatalog::FILES,
            ScreenCatalog::AGE,
            ScreenCatalog::REGISTER,
            ScreenCatalog::INTAKE,
        ], true)) {
            RecordScreens::open($capability, $config);
            return;
        }

        if (AppFunctions::has($capability)) {
            AppFunctions::open($capability);
            return;
        }

        match ($capability) {
            ScreenCatalog::CREATE => self::includeShared('item_management_manage'),
            ScreenCatalog::SEARCH_QUICK, ScreenCatalog::SEARCH_ADVANCED, ScreenCatalog::SEARCH_SAVED, ScreenCatalog::SEARCH_LIST => self::includeShared('search_management_manage'),
            default => View::respond('This screen is not available.'),
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
        if (!Request::has('application')) {
            Request::set('application', ApplicationStore::scopeKey($app));
        }
        self::includeShared($subcontroller);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function applyDefaults(string $capability, array $config, string $scope): void
    {
        $option = Request::query('option');
        if ($option === '') {
            $fromPack = AppFunctions::defaultOption($capability);
            if ($fromPack !== '') {
                Request::set('option', $fromPack);
            } else {
                Request::set('option', match ($capability) {
                    ScreenCatalog::WORK => 'show_work',
                    ScreenCatalog::CREATE => 'show_item_types',
                    ScreenCatalog::SEARCH_QUICK => 'show_quick_search',
                    ScreenCatalog::SEARCH_ADVANCED => 'show_item_search',
                    ScreenCatalog::SEARCH_SAVED => 'saved_search',
                    ScreenCatalog::SEARCH_LIST => 'show_saved_searches',
                    ScreenCatalog::ANNOUNCEMENTS => 'show_announcements',
                    ScreenCatalog::SETTINGS => 'settings',
                    default => '',
                });
            }
        }

        $itemType = trim((string) ($config['item_type_id'] ?? ''));
        if ($itemType === '' && $scope !== '') {
            $itemType = ApplicationSettings::defaultItemTypeId($scope);
        }

        if ($capability === ScreenCatalog::CREATE) {
            if ($itemType !== '' && !Request::has('default_item_type')) {
                Request::set('default_item_type', $itemType);
            }
            // Single configured type: skip the picker and open Create directly.
            if (
                $itemType !== ''
                && Request::query('option') === 'show_item_types'
                && !Request::has('item_type_id')
            ) {
                Request::set('option', 'new_item');
                Request::set('item_type_id', $itemType);
            }
        }
        if ($capability === ScreenCatalog::SEARCH_ADVANCED && !Request::has('item_types') && Request::query('option') === 'show_item_search') {
            if ($itemType !== '') {
                Request::set('item_types', $itemType);
            } elseif ($scope !== '') {
                $allowed = ApplicationSettings::allowedItemTypeIds($scope);
                if ($allowed !== []) {
                    Request::set('item_types', implode(',', $allowed));
                }
            }
        }
        if (
            in_array($capability, [ScreenCatalog::SEARCH_QUICK, ScreenCatalog::SEARCH_LIST, ScreenCatalog::SEARCH_SAVED], true)
            && !Request::has('item_types')
            && $itemType !== ''
        ) {
            Request::set('item_types', $itemType);
        }
        if ($capability === ScreenCatalog::SEARCH_SAVED && !Request::has('id') && Request::query('option') === 'saved_search') {
            Request::set('id', trim((string) ($config['search_id'] ?? '')));
        }
        if (str_starts_with($capability, 'search.') && !Request::has('application')) {
            Request::set('application', $scope);
        }
    }

    /**
     * @param array<string, mixed> $app
     */
    private static function defineContext(array $app, int $navId): void
    {
        $slug = (string) $app['slug'];
        Page::current()->enterApplication($slug, $navId);
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
        View::respond('The shared screen is missing.');
    }

    /**
     * @param array<string, mixed> $app
     * @param list<array<string, mixed>> $links
     */
    public static function sectionNavigation(array $app, array $links): void
    {
        $html = '';
        $createHref = '';
        $section = '';
        foreach ($links as $link) {
            if (!self::linkVisible($link)) {
                continue;
            }
            $href = \Adlexone\Http\Router::applicationUrl((string) $app['slug'], (int) $link['nav_id']);
            $icon = self::icon((string) $link['capability'], (string) $link['icon']);
            $html .= Blocks::link($href, (string) $link['label'], $icon);
            if ($createHref === '' && ScreenCatalog::resolve((string) $link['capability']) === ScreenCatalog::CREATE) {
                $createHref = $href;
            }
            if ((int) $link['nav_id'] === \Adlexone\Http\Page::current()->navId()) {
                $section = (string) $link['label'];
            }
        }
        $hero = \Adlexone\Html\AppHero::render($app, $createHref, $section, (int) ($_GET['item'] ?? 0));
        RenderNavigation::applySectionNav(
            (string) $app['name'],
            $html,
            $hero,
            \Adlexone\Html\Breadcrumb::forApplication($app, $section, (int) ($_GET['item'] ?? 0))
        );
    }

    /**
     * @param array<string, mixed> $link
     */
    public static function linkVisible(array $link): bool
    {
        if (ScreenCatalog::resolve((string) ($link['capability'] ?? '')) === ScreenCatalog::SETTINGS
            && !ApplicationAccess::canDesign()
        ) {
            return false;
        }
        $permission = trim((string) ($link['permission'] ?? ''));
        return $permission === '' || Access::can($permission);
    }
}
