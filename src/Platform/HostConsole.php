<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Auth\Csrf;
use Adlexone\Html\Accent;
use Adlexone\Html\Blocks;
use Adlexone\Html\Breadcrumb;
use Adlexone\Html\Charts;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;
use Adlexone\Http\Page;
use Adlexone\Http\View;

/**
 * Signed-in site manager. It uses the same shell, section bar, and dashboard
 * pieces as a customer site. Sign-in stays on the installer layout.
 */
final class HostConsole
{
    /**
     * @param array<string, string> $paths
     */
    public static function dashboard(array $paths, string $error, string $notice): void
    {
        $db = $paths['platform_db'];
        $stores = PlatformDb::stores($db);
        $figures = SiteUsage::hostFigures($db);
        $rate = PlatformDb::rate($db);
        $usages = [];
        foreach ($stores as $store) {
            $usages[(int) $store['store_id']] = PlatformDb::usage($db, (int) $store['store_id']);
        }

        $body = self::alert($error)
            . '<div class="home home--host">'
            . self::hero($stores, $usages, $figures)
            . self::banners($figures)
            . self::kpis($figures, $rate)
            . self::siteSection($paths, $stores, $usages, $rate)
            . '</div>';

        self::present('Customer sites', '', $body, 'sites', $notice);
    }

    /**
     * @param array<string, string> $paths
     */
    public static function rate(array $paths, string $error, string $notice): void
    {
        $saved = PlatformDb::rate($paths['platform_db']);
        $action = Runtime::operatorAddress('view=rate');
        $html = '<p>One rate for every site. There is no invoice. The cost is this rate applied to the last measurement.</p>';
        $html .= '<form method="post" action="' . Html::e($action) . '" class="form-grid" autocomplete="off">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="save_rate">';
        $html .= self::field('Currency', 'currency', 'text', $saved === null ? 'AUD' : (string) $saved['currency'], true, 'Three letters. For example, AUD.');
        $html .= self::field(
            'Monthly amount',
            'monthly',
            'text',
            $saved === null ? '' : SiteUsage::formatAmount((int) $saved['monthly_cents']),
            true,
            'Applied once to each site. For example, 12.50.'
        );
        $html .= self::field(
            'Amount per person',
            'person',
            'text',
            $saved === null ? '' : SiteUsage::formatAmount((int) $saved['person_cents']),
            true,
            'Multiplied by the number of people on the site.'
        );
        $html .= self::field(
            'Amount per gigabyte',
            'gigabyte',
            'text',
            $saved === null ? '' : SiteUsage::formatAmount((int) $saved['gigabyte_cents']),
            true,
            'A gigabyte is 1,024 MB. The figure uses the folder total.'
        );
        $html .= '<div class="form-actions">';
        $html .= '<button type="submit" class="btn btn--primary">Save the rate</button>';
        $html .= '</div></form>';
        if ($saved !== null) {
            $html .= '<form method="post" action="' . Html::e($action) . '" class="host-actions">';
            $html .= Csrf::field();
            $html .= '<input type="hidden" name="operator_action" value="clear_rate">';
            $html .= '<button type="submit" class="btn">Clear the rate</button>';
            $html .= '</form>';
        }

        self::present(
            'Rate',
            'Rate',
            self::alert($error) . Blocks::vertical([['html' => $html]]),
            'rate',
            $notice
        );
    }

    /**
     * @param array<string, string> $paths
     */
    public static function add(array $paths, string $error, string $notice): void
    {
        $action = Runtime::operatorAddress('view=add');
        $folder = rtrim($paths['stores'], '/\\') . DIRECTORY_SEPARATOR . '{slug}' . DIRECTORY_SEPARATOR;
        $html = '<p>The folder is created under the stores directory. Leave the admin username and password blank and they finish setup on their hostname.</p>';
        $html .= self::facts([
            'Stores directory' => rtrim($paths['stores'], '/\\') . DIRECTORY_SEPARATOR,
            'This site' => $folder,
            'PHP is running as' => $paths['php_user'],
        ], true);
        $html .= '<form method="post" action="' . Html::e($action) . '" class="form-grid" autocomplete="off">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="create_store">';
        $html .= self::field('Display name', 'label', 'text', '', true, 'Shown on their setup page. For example, Health.');
        $html .= self::field('Slug', 'slug', 'text', '', true, 'Folder name. Lowercase letters, numbers, and hyphens. For example, health.');
        $html .= self::field('Hostname', 'host', 'text', '', true, 'The address their browser uses. For example, health.example.com. Point DNS at this server. Do not use the operator hostname.');
        $html .= self::field('Admin email', 'admin_email', 'email', '', false, 'Stored on the operator list so you know who to contact. Optional.');
        $html .= self::field(
            'Admin username',
            'admin_user',
            'text',
            DefaultAccount::allowed() ? DefaultAccount::USERNAME : '',
            false,
            'The customer application login. Leave username and password blank and they choose the first account on their hostname.'
        );
        $html .= self::field(
            'Admin password',
            'admin_password',
            'password',
            DefaultAccount::allowed() ? DefaultAccount::PASSWORD : '',
            false,
            DefaultAccount::allowed()
                ? 'The default is ' . DefaultAccount::PASSWORD . '. Stored as a hash in their SQLite file.'
                : 'At least 12 characters, or leave blank to let them choose on first visit. Stored as a hash in their SQLite file.'
        );
        $html .= '<div class="form-actions">';
        $html .= '<a class="btn" href="' . Html::e(Runtime::operatorAddress()) . '">Sites</a>';
        $html .= '<button type="submit" class="btn btn--primary">Create the store</button>';
        $html .= '</div></form>';

        self::present(
            'Add a site',
            'Add a site',
            self::alert($error) . Blocks::vertical([['html' => $html]]),
            'add',
            $notice
        );
    }

    /**
     * @param array<string, string> $paths
     * @param array<string, mixed> $store
     */
    public static function site(array $paths, array $store, string $error, string $notice): void
    {
        $slug = (string) $store['slug'];
        $label = (string) $store['label'];
        $folder = SiteUsage::folder($paths['stores'], $slug);
        $id = (int) $store['store_id'];
        $usage = PlatformDb::usage($paths['platform_db'], $id);
        $rate = PlatformDb::rate($paths['platform_db']);
        $action = Runtime::operatorAddress('site=' . rawurlencode($slug));
        $page = Page::current();
        $page->setCrumbs([
            ['label' => 'Sites', 'href' => Runtime::operatorAddress()],
            ['label' => $label],
        ]);

        $body = self::alert($error) . Blocks::vertical([
            [
                'kicker' => 'Measured',
                'title' => 'Usage',
                'html' => self::usage($usage, $rate, $id, $action),
            ],
            [
                'kicker' => 'Slug',
                'title' => 'Folder',
                'html' => self::folderFacts($folder, $slug),
            ],
            [
                'kicker' => 'Details',
                'title' => 'Update this site',
                'html' => self::updateForm($store, $id, $slug, $action),
            ],
            [
                'kicker' => $slug,
                'title' => 'Suspend, backup, delete',
                'html' => self::actions($store, $id, $slug, $action),
            ],
        ]);

        self::present($label, $label, $body, 'site', $notice, self::siteHero($store, $usage, $rate, $id, $action));
    }

    public static function missing(string $error, string $notice): void
    {
        $copy = $error !== '' ? $error : 'That customer site was not found.';
        self::present(
            'Customer site',
            'Customer site',
            Blocks::emptyState([
                'illustration' => 'empty-apps',
                'title' => 'That customer site was not found',
                'copy' => $copy,
                'action' => '<a class="btn btn--primary" href="' . Html::e(Runtime::operatorAddress()) . '">Sites</a>',
            ]),
            'sites',
            $notice
        );
    }

    private static function present(
        string $documentLabel,
        string $title,
        string $body,
        string $place,
        string $notice,
        string $hero = ''
    ): void {
        if ($notice !== '') {
            View::flash($notice);
        }
        $page = Page::current();
        if ($title === '') {
            $page->suggestEyebrow($documentLabel);
        } else {
            $page->setTitle($title);
            $page->suggestNavLabel($documentLabel);
        }
        $page->setSectionNav(self::section($place));
        if ($hero !== '') {
            $page->setAppHero($hero);
        }
        $page->setBody($body . self::dialog());
        $page->useLayout(Page::SHELL, ['nav' => self::nav()]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function nav(): array
    {
        $name = trim((string) ($_SESSION['operator_name'] ?? ''));
        $initials = Presence::initials($name);

        return [
            'apps' => ['mode' => 'none', 'items' => []],
            'design' => [],
            'search' => false,
            'home' => Runtime::operatorAddress(),
            'homeLabel' => 'Customer sites',
            'account' => [
                'name' => $name !== '' ? $name : 'Operator',
                'initials' => $initials !== '' ? $initials : 'O',
                'links' => [],
            ],
            'signOutHtml' => self::accountTools(),
        ];
    }

    private static function accountTools(): string
    {
        $html = '<button type="button" role="menuitem" class="menu__item" data-sheet-pop aria-controls="manage-help">';
        $html .= Charts::icon('ic-info') . '<span>Details</span></button>';
        $html .= '<form method="post" action="' . Html::e(Runtime::operatorAddress()) . '" class="menu__signout">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="sign_out">';
        $html .= '<button type="submit" role="menuitem" class="menu__item">';
        $html .= Charts::icon('ic-logoff') . '<span>Sign out</span></button></form>';

        return $html;
    }

    private static function section(string $place): string
    {
        $current = $place === 'site' ? 'sites' : $place;
        $items = [
            'sites' => ['Sites', Runtime::operatorAddress()],
            'rate' => ['Rate', Runtime::operatorAddress('view=rate')],
            'add' => ['Add a site', Runtime::operatorAddress('view=add')],
        ];
        $html = '<div class="sectionbar"><p class="sectionbar__name">Host</p>';
        $html .= '<nav class="sectionnav" aria-label="Sites">';
        foreach ($items as $id => [$label, $href]) {
            $currentAttr = $id === $current ? ' aria-current="page"' : '';
            $html .= '<a class="sectionnav__link" href="' . Html::e($href) . '"' . $currentAttr . '>';
            $html .= '<span class="sectionnav__label">' . Html::e($label) . '</span></a>';
        }

        return $html . '</nav></div>';
    }

    private static function dialog(): string
    {
        return '<dialog id="manage-help" class="sheet-pop" aria-labelledby="manage-help-title">'
            . '<div class="sheet-pop__panel">'
            . '<div class="sheet-pop__head"><h2 class="sheet-pop__title" id="manage-help-title">Customer sites</h2>'
            . '<button type="button" class="btn btn--sm sheet-pop__close" data-sheet-pop-close>Close</button></div>'
            . '<div class="sheet-pop__body setup-help">' . HelpText::operator(Runtime::paths()) . '</div>'
            . '<div class="sheet-pop__actions"><button type="button" class="btn btn--primary" data-sheet-pop-close>Done</button></div>'
            . '</div></dialog>';
    }

    /**
     * @param list<array<string, mixed>> $stores
     * @param array<int, array<string, mixed>|null> $usages
     * @param array{sites: int, active: int, suspended: int, unmeasured: int, unset_counts: int, bytes: int, people: int, applications: int, records: int, cost: int|null} $figures
     */
    private static function hero(array $stores, array $usages, array $figures): string
    {
        $hour = (int) date('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $name = trim((string) ($_SESSION['operator_name'] ?? ''));
        $first = $name !== '' ? (string) (preg_split('/\s+/', $name)[0] ?? '') : '';
        $hello = $greeting . ($first !== '' ? ', ' . $first : '');
        $lead = $stores === []
            ? 'No customer sites yet. Add a site to create the first folder.'
            : 'Each row is one site. The totals are the last measurement of those sites.';

        $tiles = '';
        foreach ([
            'Sites' => [$figures['sites'], $figures['sites'] > 0],
            'Active' => [$figures['active'], $figures['active'] > 0],
            'Suspended' => [$figures['suspended'], $figures['suspended'] > 0],
            'Not measured' => [$figures['unmeasured'], $figures['unmeasured'] > 0],
        ] as $label => [$count, $on]) {
            $tone = $label === 'Suspended' && $on ? ' homehero__tile--warning' : ($on ? '' : ' homehero__tile--none');
            $inner = '<strong class="homehero__n">' . (int) $count . '</strong><span class="homehero__what">' . Html::e($label) . '</span>';
            $tiles .= $label === 'Sites'
                ? '<li><a class="homehero__tile' . $tone . '" href="#sites">' . $inner . '</a></li>'
                : '<li><span class="homehero__tile' . $tone . '">' . $inner . '</span></li>';
        }

        $actions = '';
        if ($stores !== []) {
            $actions = '<div class="homehero__actions"><form method="post" action="' . Html::e(Runtime::operatorAddress()) . '" class="host-actions">';
            $actions .= Csrf::field();
            $actions .= '<input type="hidden" name="operator_action" value="measure">';
            $actions .= '<button type="submit" class="btn btn--primary">Measure usage</button>';
            $actions .= '</form></div>';
        }

        $next = self::next($stores, $usages);
        $card = '<a class="homehero__next" href="' . Html::e($next['href']) . '">'
            . '<span class="homehero__kicker">' . Html::e($next['kicker']) . '</span>'
            . '<span class="homehero__nexttitle">' . Html::e($next['title']) . '</span>'
            . '<span class="homehero__nextmeta">' . Html::e($next['meta']) . '</span>'
            . '<span class="homehero__nextgo">' . Html::e($next['go']) . Charts::icon('ic-arrow-right') . '</span></a>';

        $brief = '<span class="homehero__hello">' . Html::e($hello) . '</span>'
            . '<span>' . Html::e(date('l j F')) . '</span>'
            . '<span>' . Html::e(self::plural($figures['sites'], 'site')) . '</span>';

        return '<details class="homehero" open data-minimise="home.welcome">'
            . '<summary class="homehero__summary">'
            . '<span class="homehero__dateline">' . Html::e(date('l j F')) . '</span>'
            . '<span class="homehero__brief">' . $brief . '</span>'
            . '<span class="homehero__switch"><span class="homehero__switch-hide">Minimise</span><span class="homehero__switch-show">Show</span></span>'
            . '</summary>'
            . '<div class="homehero__body"><div class="homehero__copy">'
            . '<h1 class="homehero__title">' . Html::e($hello) . '</h1>'
            . '<p class="homehero__lead">' . Html::e($lead) . '</p>'
            . '<ul class="homehero__tiles" aria-label="Host totals">' . $tiles . '</ul>'
            . $actions
            . '</div>' . $card . '</div></details>';
    }

    /**
     * @param list<array<string, mixed>> $stores
     * @param array<int, array<string, mixed>|null> $usages
     * @return array{href: string, kicker: string, title: string, meta: string, go: string}
     */
    private static function next(array $stores, array $usages): array
    {
        if ($stores === []) {
            return [
                'href' => Runtime::operatorAddress('view=add'),
                'kicker' => 'Next',
                'title' => 'Add a customer site',
                'meta' => 'A folder, a hostname, and an optional administrator.',
                'go' => 'Add a site',
            ];
        }
        $pick = null;
        $kicker = 'Open';
        foreach ($stores as $store) {
            if (($usages[(int) $store['store_id']] ?? null) === null) {
                $pick = $store;
                $kicker = 'Not measured';
                break;
            }
        }
        if ($pick === null) {
            foreach ($stores as $store) {
                if ((string) $store['status'] !== 'active') {
                    $pick = $store;
                    $kicker = 'Suspended';
                    break;
                }
            }
        }
        if ($pick === null) {
            $pick = $stores[0];
        }

        return [
            'href' => Runtime::operatorAddress('site=' . rawurlencode((string) $pick['slug'])),
            'kicker' => $kicker,
            'title' => (string) $pick['label'],
            'meta' => (string) $pick['host'],
            'go' => 'Open the site',
        ];
    }

    /**
     * @param array{sites: int, active: int, suspended: int, unmeasured: int, unset_counts: int, bytes: int, people: int, applications: int, records: int, cost: int|null} $figures
     */
    private static function banners(array $figures): string
    {
        $html = '';
        if ($figures['unmeasured'] > 0) {
            $n = $figures['unmeasured'];
            $text = $n . ' ' . ($n === 1 ? 'site has not been measured and is not in this cost.' : 'sites have not been measured and are not in this cost.');
            $html .= self::banner($text);
        }
        if ($figures['unset_counts'] > 0) {
            $n = $figures['unset_counts'];
            $text = $n . ' ' . ($n === 1 ? 'site has' : 'sites have') . ' no administrator yet. Their people, applications, and records are not in these totals.';
            $html .= self::banner($text);
        }

        return $html;
    }

    private static function banner(string $text): string
    {
        return '<div class="banner"><span class="banner__icon">' . Charts::icon('ic-alert') . '</span>'
            . '<p class="banner__text">' . Html::e($text) . '</p></div>';
    }

    /**
     * @param array{sites: int, active: int, suspended: int, unmeasured: int, bytes: int, people: int, applications: int, records: int, cost: int|null} $figures
     * @param array<string, mixed>|null $rate
     */
    private static function kpis(array $figures, ?array $rate): string
    {
        $cost = $figures['cost'] === null || $rate === null
            ? 'No rate'
            : SiteUsage::formatMoney($figures['cost'], (string) $rate['currency']);
        $html = '<div class="kpis">';
        $html .= Charts::kpi([
            'label' => 'Folder total',
            'value' => SiteUsage::formatSize($figures['bytes']),
            'icon' => 'ic-database',
            'note' => 'Last measurement',
        ]);
        $html .= Charts::kpi([
            'label' => 'People',
            'value' => (string) $figures['people'],
            'icon' => 'ic-users',
            'tone' => 'info',
        ]);
        $html .= Charts::kpi([
            'label' => 'Applications',
            'value' => (string) $figures['applications'],
            'icon' => 'ic-grid',
        ]);
        $html .= Charts::kpi([
            'label' => 'Records',
            'value' => (string) $figures['records'],
            'icon' => 'ic-layers',
            'tone' => 'neutral',
        ]);
        $html .= Charts::kpi([
            'label' => 'Cost',
            'value' => $cost,
            'icon' => 'ic-activity',
            'tone' => $rate === null ? 'neutral' : 'success',
            'href' => Runtime::operatorAddress('view=rate'),
            'note' => $rate === null ? 'Set a rate' : 'From the last measurement',
        ]);

        return $html . '</div>';
    }

    /**
     * @param array<string, string> $paths
     * @param list<array<string, mixed>> $stores
     * @param array<int, array<string, mixed>|null> $usages
     * @param array<string, mixed>|null $rate
     */
    private static function siteSection(array $paths, array $stores, array $usages, ?array $rate): string
    {
        $tools = '<div class="host-actions">'
            . '<a class="btn btn--primary" href="' . Html::e(Runtime::operatorAddress('view=add')) . '">Add a site</a>'
            . '<a class="btn" href="' . Html::e(Runtime::operatorAddress('view=rate')) . '">Rate</a>'
            . '</div>';
        $html = '<section class="home__section home__section--apps" id="sites">';
        $html .= '<div class="home__heading"><h2>Sites</h2>' . $tools . '</div>';
        if ($stores === []) {
            $html .= Blocks::emptyState([
                'illustration' => 'empty-apps',
                'title' => 'No customer sites yet',
                'copy' => 'Add a site to create the first folder.',
                'action' => '<a class="btn btn--primary" href="' . Html::e(Runtime::operatorAddress('view=add')) . '">Add a site</a>',
            ]);

            return $html . '</section>';
        }
        $html .= '<div class="appgrid">';
        foreach ($stores as $store) {
            $html .= self::card($store, $usages[(int) $store['store_id']] ?? null, $rate);
        }

        return $html . '</div></section>';
    }

    /**
     * @param array<string, mixed> $store
     * @param array<string, mixed>|null $usage
     * @param array<string, mixed>|null $rate
     */
    private static function card(array $store, ?array $usage, ?array $rate): string
    {
        $slug = (string) $store['slug'];
        $active = (string) $store['status'] === 'active';
        $accent = self::accentClass($slug);
        $figures = 'Not measured';
        $when = 'Not measured';
        if ($usage !== null) {
            $bytes = (int) $usage['database_bytes'] + (int) $usage['wal_bytes'] + (int) $usage['shm_bytes']
                + (int) $usage['settings_bytes'] + (int) $usage['uploads_bytes'] + (int) $usage['other_bytes'];
            $cost = $usage['cost_cents'] === null || $rate === null
                ? 'No cost'
                : SiteUsage::formatMoney((int) $usage['cost_cents'], (string) $rate['currency']);
            $figures = SiteUsage::formatSize($bytes) . ' · ' . $cost;
            $when = 'Measured ' . SiteUsage::formatMeasured((int) $usage['measured_at']);
        }
        $class = 'appcard' . ($accent !== '' ? ' ' . $accent : '');

        return '<a class="' . Html::e($class) . '" href="' . Html::e(Runtime::operatorAddress('site=' . rawurlencode($slug))) . '">'
            . '<span class="appcard__top"><span class="appcard__icon">' . Charts::icon('ic-building') . '</span>'
            . '<span class="appcard__go" aria-hidden="true">' . Charts::icon('ic-arrow-right') . '</span></span>'
            . '<span class="appcard__name">' . Html::e((string) $store['label']) . '</span>'
            . '<span class="appcard__hint">' . Html::e((string) $store['host']) . '</span>'
            . '<span class="appcard__figures"><span class="appcard__count">' . Html::e($figures) . '</span></span>'
            . '<span class="appcard__foot">' . Presence::pill($active ? 'Active' : 'Suspended', $active ? 'success' : 'warning')
            . '<span class="appcard__when">' . Html::e($when) . '</span></span></a>';
    }

    /**
     * @param array<string, mixed> $store
     * @param array<string, mixed>|null $usage
     * @param array<string, mixed>|null $rate
     */
    private static function siteHero(array $store, ?array $usage, ?array $rate, int $id, string $action): string
    {
        $slug = (string) $store['slug'];
        $accent = self::accentClass($slug);
        $bytes = 'Not measured';
        $people = 'Not set up';
        $cost = 'No cost';
        if ($usage !== null) {
            $total = (int) $usage['database_bytes'] + (int) $usage['wal_bytes'] + (int) $usage['shm_bytes']
                + (int) $usage['settings_bytes'] + (int) $usage['uploads_bytes'] + (int) $usage['other_bytes'];
            $bytes = SiteUsage::formatSize($total);
            $people = self::countText($usage['people'] ?? null);
            if ($usage['cost_cents'] !== null && $rate !== null) {
                $cost = SiteUsage::formatMoney((int) $usage['cost_cents'], (string) $rate['currency']);
            }
        }
        $facts = '<ul class="apphero__facts">'
            . '<li><strong>' . Html::e($bytes) . '</strong><span>folder</span></li>'
            . '<li><strong>' . Html::e($people) . '</strong><span>people</span></li>'
            . '<li><strong>' . Html::e($cost) . '</strong><span>cost</span></li>'
            . '</ul>';
        $measure = self::miniForm($id, 'measure_site', 'Measure this site', $action, 'btn btn--glass');
        $class = 'apphero' . ($accent !== '' ? ' ' . $accent : '');

        return '<header class="' . Html::e($class) . '"><div class="apphero__inner">'
            . '<span class="apphero__icon">' . Charts::icon('ic-building') . '</span>'
            . '<div class="apphero__copy">' . Breadcrumb::BAND_SLOT
            . '<p class="apphero__name">' . Html::e((string) $store['label']) . '</p>'
            . '<p class="apphero__hint">' . Html::e((string) $store['host'] . '. These figures are the last measurement of this site.') . '</p>'
            . '</div>' . $facts
            . '<div class="apphero__tools">' . $measure . '</div>'
            . '</div></header>';
    }

    /**
     * @param array<string, mixed>|null $usage
     * @param array<string, mixed>|null $rate
     */
    private static function usage(?array $usage, ?array $rate, int $id, string $action): string
    {
        if ($usage === null) {
            return '<p>This site has not been measured.</p>'
                . '<div class="host-actions">' . self::miniForm($id, 'measure_site', 'Measure this site', $action, 'btn') . '</div>';
        }
        $rows = [
            'Database' => SiteUsage::formatSize((int) $usage['database_bytes']),
            'Write-ahead log' => SiteUsage::formatSize((int) $usage['wal_bytes']),
            'Shared memory' => SiteUsage::formatSize((int) $usage['shm_bytes']),
            'Settings' => SiteUsage::formatSize((int) $usage['settings_bytes']),
            'Uploads' => SiteUsage::formatSize((int) $usage['uploads_bytes']),
        ];
        if ((int) $usage['other_bytes'] > 0) {
            $rows['Other'] = SiteUsage::formatSize((int) $usage['other_bytes']);
        }
        $total = (int) $usage['database_bytes'] + (int) $usage['wal_bytes'] + (int) $usage['shm_bytes']
            + (int) $usage['settings_bytes'] + (int) $usage['uploads_bytes'] + (int) $usage['other_bytes'];
        $rows['Folder total'] = SiteUsage::formatSize($total);
        $rows['People'] = self::countText($usage['people'] ?? null);
        $rows['Applications'] = self::countText($usage['applications'] ?? null);
        $rows['Records'] = self::countText($usage['records'] ?? null);
        $html = '<p class="form-help">Measured ' . Html::e(SiteUsage::formatMeasured((int) $usage['measured_at'])) . '.</p>';
        $html .= self::facts($rows, false);
        if ($usage['people'] === null) {
            $html .= '<p class="form-help">This site has no administrator yet.</p>';
        }
        $note = trim((string) ($usage['counts_note'] ?? ''));
        if ($note !== '') {
            $html .= '<p class="form-help">' . Html::e($note) . '</p>';
        }
        if ($rate === null || $usage['cost_cents'] === null) {
            $html .= '<p class="form-help">No rate is set. Set a rate to calculate a cost. Usage is already shown.</p>';
        } else {
            $html .= '<p class="form-help">Cost ' . Html::e(SiteUsage::formatMoney((int) $usage['cost_cents'], (string) $rate['currency'])) . '.</p>';
        }

        return $html;
    }

    private static function folderFacts(string $folder, string $slug): string
    {
        $html = self::facts([
            'Slug' => $slug,
            'Database' => $folder . 'adlexone.sqlite',
            'Settings' => $folder . 'config' . DIRECTORY_SEPARATOR,
            'Uploads' => $folder . 'attachments' . DIRECTORY_SEPARATOR,
        ], true);

        return $html . '<p class="form-help">The slug is ' . Html::e($slug) . '. It is the folder name and it is not changed here.</p>';
    }

    /**
     * @param array<string, mixed> $store
     */
    private static function updateForm(array $store, int $id, string $slug, string $action): string
    {
        $html = '<form method="post" action="' . Html::e($action) . '" class="form-grid" autocomplete="off">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="save_site">';
        $html .= '<input type="hidden" name="store_id" value="' . $id . '">';
        $html .= '<input type="hidden" name="slug" value="' . Html::e($slug) . '">';
        $html .= self::field('Display name', 'label', 'text', (string) $store['label'], true, 'Shown on their sign-in page. For example, Health.');
        $html .= self::field('Hostname', 'host', 'text', (string) $store['host'], true, 'The address their browser uses. For example, health.example.com.');
        $html .= self::field('Admin email', 'admin_email', 'email', (string) $store['admin_email'], false, 'Stored on the operator list so you know who to contact. Optional.');
        $html .= '<div class="form-actions"><button type="submit" class="btn btn--primary">Save changes</button></div>';
        $html .= '</form>';

        return $html;
    }

    /**
     * @param array<string, mixed> $store
     */
    private static function actions(array $store, int $id, string $slug, string $action): string
    {
        $status = (string) $store['status'];
        $html = '<div class="host-actions">';
        $html .= self::miniForm($id, 'measure_site', 'Measure this site', $action, 'btn');
        $html .= self::miniForm($id, $status === 'active' ? 'suspend' : 'resume', $status === 'active' ? 'Suspend' : 'Resume', $action, 'btn');
        $html .= self::miniForm($id, 'backup', 'Download backup', $action, 'btn');
        $html .= '</div>';
        $html .= '<p class="install-section">Remove this site</p>';
        $html .= '<p class="form-help">Type the slug ' . Html::e($slug) . '. Delete removes that folder from disk.</p>';
        $html .= '<form method="post" action="' . Html::e($action) . '" class="form-grid" autocomplete="off">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="delete">';
        $html .= '<input type="hidden" name="store_id" value="' . $id . '">';
        $html .= '<div class="field"><label class="label" for="op_confirm_slug">Slug</label>';
        $html .= '<input class="input" type="text" id="op_confirm_slug" name="confirm_slug" autocomplete="off" spellcheck="false"></div>';
        $html .= '<div class="form-actions"><button type="submit" class="btn btn--danger">Delete from disk</button></div>';
        $html .= '</form>';

        return $html;
    }

    private static function miniForm(int $id, string $action, string $label, string $target, string $buttonClass): string
    {
        $html = '<form method="post" action="' . Html::e($target) . '" class="host-actions">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="' . Html::e($action) . '">';
        $html .= '<input type="hidden" name="store_id" value="' . $id . '">';
        $html .= '<button type="submit" class="' . Html::e($buttonClass) . '">' . Html::e($label) . '</button>';
        $html .= '</form>';

        return $html;
    }

    /**
     * @param array<string, string> $rows
     */
    private static function facts(array $rows, bool $code): string
    {
        $html = '<dl class="install-facts">';
        foreach ($rows as $label => $value) {
            $shown = $code ? '<code>' . Html::e($value) . '</code>' : Html::e($value);
            $html .= '<div><dt>' . Html::e((string) $label) . '</dt><dd>' . $shown . '</dd></div>';
        }

        return $html . '</dl>';
    }

    private static function field(
        string $label,
        string $name,
        string $type,
        string $value,
        bool $required,
        string $help = ''
    ): string {
        $id = 'op_' . $name;
        $html = '<div class="field">';
        $html .= '<label class="label" for="' . Html::e($id) . '">' . Html::e($label) . '</label>';
        $auto = $type === 'password' ? ' autocomplete="new-password"' : ' autocomplete="off"';
        $html .= '<input class="input" type="' . Html::e($type) . '" id="' . Html::e($id) . '" name="' . Html::e($name) . '" value="' . Html::e($value) . '"'
            . ($required ? ' required' : '') . $auto . '>';
        if ($help !== '') {
            $html .= '<p class="form-help">' . Html::e($help) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function alert(string $error): string
    {
        if ($error === '') {
            return '';
        }

        return '<div class="login-error" role="alert">' . Html::e($error) . '</div>';
    }

    private static function countText(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not set up';
        }

        return (string) (int) $value;
    }

    private static function accentClass(string $slug): string
    {
        $palette = Accent::palette();
        if ($palette === []) {
            return '';
        }
        $colour = $palette[abs(crc32($slug)) % count($palette)];

        return Accent::classes($colour);
    }

    private static function plural(int $count, string $word): string
    {
        return $count . ' ' . $word . ($count === 1 ? '' : 's');
    }
}
