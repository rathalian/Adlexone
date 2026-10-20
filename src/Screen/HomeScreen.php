<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Html\Blocks;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;
use Adlexone\Http\Page;
use Adlexone\Http\View;
use Adlexone\support\Database;
use Adlexone\Theme\Theme;

/**
 * Screen formerly loaded from legacy/.
 */
final class HomeScreen
{
    public static function open(): void
    {
        $landing = self::landingHref();
        if ($landing !== '') {
            header('Location: ' . \Adlexone\Http\Router::absolute($landing));
            exit;
        }

        $applications = [];
        $slugs = [];
        foreach (\Adlexone\Application\ApplicationStore::menuItems() as $item) {
            $hint = trim((string) $item['hint']);
            $slug = (string) ($item['app'] ?? '');
            $slugs[] = $slug;
            $pulse = self::pulse($slug);
            $applications[] = self::tile(
                (string) $item['href'],
                (string) $item['label'],
                $hint !== '' ? $hint : (string) $item['label'],
                (string) $item['icon'],
                $pulse['stat'],
                $pulse['when']
            );
        }

        $manage = [];
        if (Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $manage[] = self::tile(
                '?manage=builder',
                'Create application',
                'Create an application, including its record type, fields, and screens.',
                'ic-itemtype-add'
            );
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $manage[] = self::tile(
                '?manage=applications',
                'Applications',
                'Set the order of applications and their navigation.',
                'ic-launch'
            );
        }
        if (Access::can(Permission::ADMIN_ITEMS)) {
            $manage[] = self::tile(
                '?manage=fields',
                'Fields',
                'Fields used across the applications.',
                'ic-manage-fields'
            );
            $manage[] = self::tile(
                '?manage=item-types',
                'Record types',
                'The record type each application keeps.',
                'ic-manage-item-types'
            );
        }
        if (Access::can(Permission::ADMIN_ACTIONS)) {
            $manage[] = self::tile(
                '?manage=rules',
                'Rules',
                'What runs when a record is created or updated.',
                'ic-manage-actions'
            );
        }
        if (Access::can(Permission::ADMIN_SECURITY)) {
            $manage[] = self::tile(
                '?manage=security&option=manage_users',
                'Security',
                'People, groups, and the records they can open.',
                'ic-manage-users'
            );
        }
        if (Access::can(Permission::ADMIN_SETTINGS)) {
            $manage[] = self::tile(
                '?manage=settings&option=adlexone_settings',
                'Settings',
                'Site name, theme, and related settings.',
                'ic-system-settings'
            );
        } elseif (Access::can(Permission::ADMIN_SYSTEM)) {
            $manage[] = self::tile(
                '?manage=settings&option=sign_in_settings',
                'Settings',
                'Sign-in and system settings.',
                'ic-system-settings'
            );
        }

        $intro = '';
        if ($applications === [] && Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            $intro = View::partialToString('launchpad_intro', [
                'copy' => defined('SET_HOME_LEAD') && trim((string) SET_HOME_LEAD) !== ''
                    ? (string) SET_HOME_LEAD
                    : 'This site keeps the applications, records, and rules for the work.',
                'art' => Theme::illustrationHref('empty-apps'),
            ]);
        }

        $html = '<div class="launchpad">'
            . $intro
            . self::desk($slugs)
            . self::group('Applications', $applications)
            . self::group('Design', $manage)
            . '</div>';

        if ($applications === [] && $manage === [] && $intro === '') {
            $html = Blocks::emptyState([
                'illustration' => 'empty-apps',
                'title' => 'Nothing is available for this account',
                'copy' => 'Ask an administrator to grant access to an application.',
            ]);
        }

        Page::current()->setBody($html);
    }
    /**
     * One application opens straight into it. Several applications stay on
     * this page so the person can choose, unless their account names a home app.
     */
    private static function landingHref(): string
    {
        $items = \Adlexone\Application\ApplicationStore::menuItems();
        if ($items === []) {
            return '';
        }
        $bySlug = [];
        foreach ($items as $item) {
            $bySlug[(string) ($item['app'] ?? '')] = (string) ($item['href'] ?? '');
        }
        $stored = (string) ($_SESSION['access_home_controller'] ?? '');
        if (str_starts_with($stored, 'application:')) {
            $slug = substr($stored, strlen('application:'));
            if (($bySlug[$slug] ?? '') !== '') {
                return $bySlug[$slug];
            }
        }
        $default = defined('SET_DEFAULT_APPLICATION') ? (string) SET_DEFAULT_APPLICATION : '';
        if (str_contains($default, '}-{')) {
            [$controller] = explode('}-{', $default, 2);
            if (str_starts_with((string) $controller, 'application:')) {
                $slug = substr((string) $controller, strlen('application:'));
                if (($bySlug[$slug] ?? '') !== '') {
                    return $bySlug[$slug];
                }
            }
        }
        if (count($items) === 1) {
            return (string) ($items[0]['href'] ?? '');
        }

        return '';
    }

    /**
     * Homepage. One tile per destination. Section menus stay inside each app.
     *
     * @return array{href: string, title: string, hint: string, icon: string, stat: string, when: string}
     */
    private static function tile(string $href, string $title, string $hint, string $icon, string $stat = '', string $when = ''): array
    {
        return [
            'href' => $href,
            'title' => $title,
            'hint' => $hint,
            'icon' => $icon,
            'stat' => $stat,
            'when' => $when,
        ];
    }

    /**
     * @return array{stat: string, when: string}
     */
    private static function pulse(string $slug): array
    {
        $typeId = self::typeId($slug);
        if ($typeId <= 0) {
            return ['stat' => '', 'when' => ''];
        }
        $row = Database::rows(
            'SELECT COUNT(*) AS n, MAX(CASE WHEN core_log_updated > create_date THEN core_log_updated ELSE create_date END) AS touched FROM items WHERE item_type_id = ?',
            [$typeId]
        );
        $count = (int) ($row[0]['n'] ?? 0);
        $touched = (int) ($row[0]['touched'] ?? 0);
        $label = $count === 1 ? '1 record' : $count . ' records';

        return [
            'stat' => $label,
            'when' => $touched > 0 ? Presence::ago($touched) : '',
        ];
    }

    /**
     * @param list<string> $slugs
     */
    private static function desk(array $slugs): string
    {
        $slugs = array_values(array_filter($slugs));
        if ($slugs === []) {
            return '';
        }
        $typeToSlug = [];
        foreach ($slugs as $slug) {
            $typeId = self::typeId($slug);
            if ($typeId > 0) {
                $typeToSlug[$typeId] = $slug;
            }
        }
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $items = [];
        if ($userId > 0 && $typeToSlug !== []) {
            $items = Database::rows(
                'SELECT item_id, item_title, item_type_id, create_date, core_log_updated FROM items WHERE user_security = ? AND item_type_id IN ('
                . implode(',', array_map('intval', array_keys($typeToSlug)))
                . ') ORDER BY CASE WHEN core_log_updated > create_date THEN core_log_updated ELSE create_date END DESC LIMIT 4',
                [$userId]
            );
        }
        $list = '';
        foreach ($items as $item) {
            $typeId = (int) ($item['item_type_id'] ?? 0);
            $slug = $typeToSlug[$typeId] ?? '';
            if ($slug === '') {
                continue;
            }
            $title = trim((string) ($item['item_title'] ?? ''));
            if ($title === '') {
                $title = 'Record ' . (int) $item['item_id'];
            }
            $when = (int) ($item['core_log_updated'] ?? 0);
            if ($when <= 0) {
                $when = (int) ($item['create_date'] ?? 0);
            }
            $href = '?application=' . rawurlencode($slug) . '&item=' . (int) $item['item_id'];
            $list .= '<li><a href="' . Html::e($href) . '">' . Html::e($title) . '</a>'
                . ($when > 0 ? ' <span class="desk__meta">' . Html::e(Presence::ago($when)) . '</span>' : '')
                . '</li>';
        }
        $wall = '';
        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $notice = Database::rows(
            'SELECT subject, application, time FROM announcements WHERE application = \'\' OR application IN (' . $placeholders . ') ORDER BY time DESC LIMIT 1',
            $slugs
        );
        if ($notice !== []) {
            $subject = trim((string) ($notice[0]['subject'] ?? ''));
            $app = (string) ($notice[0]['application'] ?? '');
            if ($subject !== '') {
                $href = $app !== '' ? '?application=' . rawurlencode($app) : './';
                $wall = '<p class="desk__wall">Announcement: <a href="' . Html::e($href) . '">' . Html::e($subject) . '</a></p>';
            }
        }
        if ($list === '' && $wall === '') {
            $list = '<li class="desk__meta">Nothing is assigned to you.</li>';
        }
        $art = Theme::illustrationHref('empty-assigned');

        return '<section class="desk" aria-label="Assigned to you">'
            . ($art !== '' ? '<img class="desk__art" src="' . Html::e($art) . '" alt="" width="88" height="66">' : '')
            . '<div><h2 class="desk__title">Assigned to you</h2><ul class="desk__list">' . $list . '</ul>' . $wall . '</div>'
            . '</section>';
    }

    private static function typeId(string $slug): int
    {
        if ($slug === '' || !Database::tableExists('applications')) {
            return 0;
        }
        $row = Database::first('applications', ['settings_json'], 'slug = ?', [$slug]);
        if ($row === null) {
            return 0;
        }
        $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);

        return is_array($settings) ? (int) ($settings['default_item_type_id'] ?? 0) : 0;
    }

    /**
     * @param list<array{href: string, title: string, hint: string, icon: string}> $tiles
     */
    private static function group(string $label, array $tiles): string
    {
        if ($tiles === []) {
            return '';
        }

        return View::partialToString('launch_group', ['label' => $label, 'tiles' => $tiles]);
    }
}
