<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\ApplicationSettings;
use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Capabilities;
use Adlexone\Screen\RecordScreens;
use Adlexone\Auth\Permission;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\Http\Page;
use Adlexone\Auth\Access;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Html\Html;

/**
 * Screen formerly loaded from legacy/.
 */
final class ApplicationsScreen
{
    public static function open(): void
    {
        Access::require(Permission::ADMIN_SETTINGS);

        define('APPLICATIONS_MANAGE_URL', '?manage=applications');
        $option = (string) ($_GET['option'] ?? '');

        $section = RenderNavigation::designURLs();
        if (in_array($option, ['edit', 'update', 'nav', 'new_nav', 'add_nav', 'edit_nav', 'update_nav'], true)) {
            $sectionApp = (int) ($_GET['application_id'] ?? $_POST['application_id'] ?? 0);
            if ($sectionApp > 0) {
                $section .= Blocks::link(APPLICATIONS_MANAGE_URL . '&option=edit&application_id=' . $sectionApp, 'Details', 'ic-sliders');
                $section .= Blocks::link(APPLICATIONS_MANAGE_URL . '&option=nav&application_id=' . $sectionApp, 'Navigation', 'ic-list-tree');
            }
        }
        RenderNavigation::applySectionNav('Design', $section);
        Page::current()->suggestTitle('Applications');

        switch ($option) {
            case 'export':
                self::exportApplicationPackage((int) ($_GET['application_id'] ?? 0));
                break;
            case 'new':
                self::showApplicationForm();
                break;
            case 'add':
                self::saveApplication(0);
                break;
            case 'edit':
                self::showApplicationForm((int) ($_GET['application_id'] ?? 0));
                break;
            case 'update':
                self::saveApplication((int) ($_POST['application_id'] ?? 0));
                break;
            case 'delete':
                ApplicationStore::deleteApplication((int) ($_GET['application_id'] ?? 0));
                header('Location: ' . APPLICATIONS_MANAGE_URL);
                exit;
            case 'move':
                ApplicationStore::moveApplication((int) ($_GET['application_id'] ?? 0), (string) ($_GET['direction'] ?? ''));
                header('Location: ' . APPLICATIONS_MANAGE_URL);
                exit;
            case 'nav':
                self::showNavigation((int) ($_GET['application_id'] ?? 0));
                break;
            case 'new_nav':
                self::showNavForm((int) ($_GET['application_id'] ?? 0));
                break;
            case 'add_nav':
                self::saveNav(0);
                break;
            case 'edit_nav':
                self::showNavForm(0, (int) ($_GET['nav_id'] ?? 0));
                break;
            case 'update_nav':
                self::saveNav((int) ($_POST['nav_id'] ?? 0));
                break;
            case 'delete_nav':
                $nav = ApplicationStore::findNav((int) ($_GET['nav_id'] ?? 0));
                if ($nav !== null) {
                    ApplicationStore::deleteNav((int) $nav['nav_id']);
                    header('Location: ' . APPLICATIONS_MANAGE_URL . '&option=nav&application_id=' . (int) $nav['application_id']);
                    exit;
                }
                header('Location: ' . APPLICATIONS_MANAGE_URL);
                exit;
            case 'move_nav':
                $nav = ApplicationStore::findNav((int) ($_GET['nav_id'] ?? 0));
                if ($nav !== null) {
                    ApplicationStore::moveNav((int) $nav['nav_id'], (string) ($_GET['direction'] ?? ''));
                    header('Location: ' . APPLICATIONS_MANAGE_URL . '&option=nav&application_id=' . (int) $nav['application_id']);
                    exit;
                }
                header('Location: ' . APPLICATIONS_MANAGE_URL);
                exit;
            default:
                self::showApplications();
                break;
        }
    }
    public static function showApplications(): void
    {
        $rows = [];
        foreach (ApplicationStore::all() as $app) {
            $id = (int) $app['application_id'];
            $rows[] = [
                'name' => (string) $app['name'],
                'href' => APPLICATIONS_MANAGE_URL . '&option=edit&application_id=' . $id,
                'cells' => [
                    'menu' => $app['enabled'] ? 'In the menu' : 'Not in the menu',
                ],
                'search' => (string) $app['slug'],
                'actions' => [
                    [
                        'href' => APPLICATIONS_MANAGE_URL . '&option=nav&application_id=' . $id,
                        'label' => 'Navigation',
                    ],
                    [
                        'href' => APPLICATIONS_MANAGE_URL . '&option=export&application_id=' . $id,
                        'label' => 'Export',
                    ],
                    [
                        'href' => APPLICATIONS_MANAGE_URL . '&option=move&application_id=' . $id . '&direction=up',
                        'label' => 'Up',
                    ],
                    [
                        'href' => APPLICATIONS_MANAGE_URL . '&option=move&application_id=' . $id . '&direction=down',
                        'label' => 'Down',
                    ],
                    [
                        'href' => APPLICATIONS_MANAGE_URL . '&option=delete&application_id=' . $id,
                        'label' => 'Delete',
                        'tone' => 'danger',
                        'confirm' => 'Delete ' . $app['name'] . ' and its navigation?',
                    ],
                ],
            ];
        }

        Page::current()->setBody(Blocks::vertical([[
            'title' => 'Applications',
            'html' => Blocks::recordList([
                'column' => 'Application',
                'columns' => [
                    ['key' => 'menu', 'label' => 'Menu'],
                ],
                'searchLabel' => 'Search',
                'primary' => ['href' => '?manage=builder', 'label' => 'Create an application'],
                'empty' => 'No applications yet. Use the builder to create one.',
                'emptyIllustration' => 'empty-apps',
                'emptyTitle' => 'No applications yet',
                'emptyCopy' => 'Use the builder to create the first application.',
                'toolbar' => '<div class="record-list__tools">'
                    . Blocks::link(APPLICATIONS_MANAGE_URL . '&option=new', 'Create manually', '', 'btn btn--sm')
                    . '</div>',
                'groups' => [['rows' => $rows]],
            ]),
        ]]));
    }

    public static function exportApplicationPackage(int $id): void
    {
        try {
            $package = \Adlexone\Application\ApplicationPackage::export($id);
        } catch (\InvalidArgumentException $e) {
            View::respond($e->getMessage(), Blocks::link(APPLICATIONS_MANAGE_URL, 'Applications', 'URL'));
            return;
        }
        $slug = (string) ($package['application']['slug'] ?? 'application');
        $json = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9_-]+/i', '-', $slug) . '-solution.json"');
        echo $json;
        exit;
    }

    public static function showApplicationForm(int $id = 0): void
    {
        $app = $id > 0 ? ApplicationStore::find($id) : null;
        if ($id > 0 && $app === null) {
            View::respond('That application does not exist.', Blocks::link(APPLICATIONS_MANAGE_URL, 'Applications', 'URL'));
            return;
        }

        $recordType = '';
        if (is_array($app['settings'] ?? null)) {
            $recordType = trim((string) ($app['settings']['default_item_type_id'] ?? ''));
        }
        $fields = [
            'Name' => Form::text('name', Html::e((string) ($app['name'] ?? ''))),
            'Slug' => Form::text('slug', Html::e((string) ($app['slug'] ?? '')), 'complaints'),
            'Description' => Form::text('hint', Html::e((string) ($app['hint'] ?? ''))),
            'Record type' => self::recordTypeSelect($recordType),
            'Icon' => self::iconSelect('icon', (string) ($app['icon'] ?? 'ic-grid')),
            'Who can open it' => self::groupAccessField((string) ($app['slug'] ?? '')),
            'Menu' => Form::checkbox('enabled', '1', ($app === null || !empty($app['enabled'])) ? '1' : '0', 'Show in the Applications menu'),
        ];
        if ($app !== null) {
            $fields[''] = Form::hidden('application_id', (string) $app['application_id']);
        }
        $action = $app === null ? APPLICATIONS_MANAGE_URL . '&option=add' : APPLICATIONS_MANAGE_URL . '&option=update';
        $html = Form::form($app === null ? 'New application' : (string) $app['name'], $action, $fields, [
            Form::button('submit', 'submit_button', 'Save'),
        ]);
        Page::current()->setBody($html);
    }

    public static function saveApplication(int $id): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = self::slugify((string) ($_POST['slug'] ?? ''), $name);
        $icon = (string) ($_POST['icon'] ?? 'ic-grid');
        $recordType = self::postedRecordType();
        $groupIds = self::postedGroupIds();
        if ($recordType === false) {
            View::respond('Choose a record type from the list.', Blocks::link(APPLICATIONS_MANAGE_URL . ($id > 0 ? '&option=edit&application_id=' . $id : '&option=new'), 'Back', 'URL'));
            return;
        }
        if ($groupIds === false) {
            View::respond('Choose a group from the list.', Blocks::link(APPLICATIONS_MANAGE_URL . ($id > 0 ? '&option=edit&application_id=' . $id : '&option=new'), 'Back', 'URL'));
            return;
        }
        if ($name === '' || $slug === '' || !in_array($icon, Capabilities::icons(), true)) {
            View::respond('Enter a name for the application.', Blocks::link(APPLICATIONS_MANAGE_URL . '&option=new', 'Back', 'URL'));
            return;
        }
        if (ApplicationStore::slugInUse($slug, $id)) {
            View::respond('That slug is already used.', Blocks::link(APPLICATIONS_MANAGE_URL, 'Applications', 'URL'));
            return;
        }
        $fields = [
            'slug' => $slug,
            'name' => $name,
            'hint' => trim((string) ($_POST['hint'] ?? '')),
            'icon' => $icon,
            'permission' => Permission::appUse($slug),
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
        ];
        if ($id > 0) {
            $existing = ApplicationStore::find($id);
            if ($existing === null) {
                View::respond('That application does not exist.', Blocks::link(APPLICATIONS_MANAGE_URL, 'Applications', 'URL'));
                return;
            }
            // Keep shell apps on their app.{slug}.use permission when the slug changes.
            if ((string) $existing['entry_mode'] === 'shell' && (string) $existing['slug'] !== $slug) {
                $fields['permission'] = Permission::appUse($slug);
            }
            ApplicationStore::updateApplication($id, $fields);
            ApplicationStore::assignRecordType($id, $recordType);
            Permission::setAppUseGroups($slug, $groupIds);
            header('Location: ' . APPLICATIONS_MANAGE_URL . '&option=edit&application_id=' . $id);
            exit;
        }
        $max = 0;
        foreach (ApplicationStore::all() as $app) {
            $max = max($max, (int) $app['sort_order']);
        }
        $fields['sort_order'] = $max + 10;
        $fields['entry_mode'] = 'shell';
        $fields['settings_json'] = json_encode([
            'default_item_type_id' => $recordType,
            'record_type_chosen' => '1',
        ], JSON_UNESCAPED_SLASHES);
        $newId = ApplicationStore::insertApplication($fields);
        Permission::setAppUseGroups($slug, $groupIds);
        header('Location: ' . APPLICATIONS_MANAGE_URL . '&option=nav&application_id=' . $newId);
        exit;
    }

    public static function showNavigation(int $applicationId): void
    {
        $app = ApplicationStore::find($applicationId);
        if ($app === null) {
            View::respond('That application does not exist.', Blocks::link(APPLICATIONS_MANAGE_URL, 'Applications', 'URL'));
            return;
        }

        $rows = [];
        foreach (ApplicationStore::navigation($applicationId) as $link) {
            $id = (int) $link['nav_id'];
            $rows[] = [
                'name' => (string) $link['label'],
                'href' => APPLICATIONS_MANAGE_URL . '&option=edit_nav&nav_id=' . $id,
                'cells' => [
                    'screen' => Capabilities::choiceLabel((string) $link['capability']),
                ],
                'actions' => [
                    ['href' => APPLICATIONS_MANAGE_URL . '&option=move_nav&nav_id=' . $id . '&direction=up', 'label' => 'Up'],
                    ['href' => APPLICATIONS_MANAGE_URL . '&option=move_nav&nav_id=' . $id . '&direction=down', 'label' => 'Down'],
                    [
                        'href' => APPLICATIONS_MANAGE_URL . '&option=delete_nav&nav_id=' . $id,
                        'label' => 'Delete',
                        'tone' => 'danger',
                        'confirm' => 'Remove ' . $link['label'] . ' from the navigation?',
                    ],
                ],
            ];
        }

        Page::current()->setBody(Blocks::vertical([[
            'title' => (string) $app['name'],
            'html' => Blocks::recordList([
                'column' => 'Link',
                'columns' => [
                    ['key' => 'screen', 'label' => 'Screen'],
                ],
                'searchLabel' => 'Search',
                'primary' => [
                    'href' => APPLICATIONS_MANAGE_URL . '&option=new_nav&application_id=' . $applicationId,
                    'label' => 'Add link',
                ],
                'empty' => 'No links yet. Add create, search, or another screen.',
                'groups' => [['rows' => $rows]],
            ]),
        ]]));
    }

    public static function showNavForm(int $applicationId, int $navId = 0): void
    {
        $link = $navId > 0 ? ApplicationStore::findNav($navId) : null;
        if ($navId > 0 && $link === null) {
            View::respond('That link does not exist.', Blocks::link(APPLICATIONS_MANAGE_URL, 'Applications', 'URL'));
            return;
        }
        if ($link !== null) {
            $applicationId = (int) $link['application_id'];
        }
        $app = ApplicationStore::find($applicationId);
        if ($app === null) {
            View::respond('That application does not exist.', Blocks::link(APPLICATIONS_MANAGE_URL, 'Applications', 'URL'));
            return;
        }

        $config = is_array($link['config'] ?? null) ? $link['config'] : [];
        $capability = (string) ($link['capability'] ?? 'items.create');
        $keys = Capabilities::choiceKeys();
        $labels = array_map(static fn (string $key): string => Capabilities::choiceLabel($key), $keys);
        $fields = [
            'Label' => Form::text('label', Html::e((string) ($link['label'] ?? ''))),
            'Screen' => Form::select('capability', $keys, $labels, $capability),
            'Who can see it' => self::permissionSelect(
                'permission',
                (string) ($link['permission'] ?? ''),
                (string) $app['name'],
                (string) $app['slug']
            ),
            'Icon' => self::iconSelect('icon', (string) ($link['icon'] ?? ''), true),
            'Saved search' => self::savedSearchSelect((string) ($config['search_id'] ?? ''), ApplicationStore::scopeKey($app)),
        ];
        $fields += RecordScreens::navFields($capability, $config, ApplicationStore::scopeKey($app));
        $fields += [
            '' => Form::hidden('application_id', (string) $applicationId)
                . ($link === null ? '' : Form::hidden('nav_id', (string) $link['nav_id'])),
        ];
        $action = $link === null ? APPLICATIONS_MANAGE_URL . '&option=add_nav' : APPLICATIONS_MANAGE_URL . '&option=update_nav';
        Page::current()->setBody(Form::form(
            $link === null ? 'Add link' : 'Edit link',
            $action,
            $fields,
            [Form::button('submit', 'submit_button', 'Save')]
        ));
    }

    public static function saveNav(int $navId): void
    {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $existing = null;
        if ($navId > 0) {
            $existing = ApplicationStore::findNav($navId);
            if ($existing === null) {
                View::respond('That link does not exist.', Blocks::link(APPLICATIONS_MANAGE_URL, 'Applications', 'URL'));
                return;
            }
            $applicationId = (int) $existing['application_id'];
        }
        $app = ApplicationStore::find($applicationId);
        $label = trim((string) ($_POST['label'] ?? ''));
        $capability = (string) ($_POST['capability'] ?? '');
        $permission = (string) ($_POST['permission'] ?? '');
        $icon = (string) ($_POST['icon'] ?? '');
        $catalog = Capabilities::catalog();
        $back = APPLICATIONS_MANAGE_URL . '&option=nav&application_id=' . $applicationId;
        if ($app === null || $label === '' || !isset($catalog[$capability])) {
            View::respond('Enter a label and choose a screen.', Blocks::link($back, 'Navigation', 'URL'));
            return;
        }
        if ($permission !== '' && !isset(Permission::catalog()[$permission])) {
            View::respond('Choose a permission from the list.', Blocks::link($back, 'Navigation', 'URL'));
            return;
        }
        if ($icon !== '' && !in_array($icon, Capabilities::icons(), true)) {
            $icon = '';
        }
        $config = [];
        $kind = (string) ($catalog[$capability]['config'] ?? 'none');
        if (in_array($kind, ['item_type', 'field', 'activity', 'register', 'intake'], true)) {
            $typeId = ApplicationSettings::defaultItemTypeId((string) $app['slug']);
            if ($typeId !== '') {
                $config['item_type_id'] = $typeId;
            }
            $config += RecordScreens::postedConfig($capability, $typeId, ApplicationStore::scopeKey($app));
        }
        $previous = is_array($existing) && is_array($existing['config'] ?? null) ? $existing['config'] : [];
        foreach (['field_id', 'field_ids'] as $key) {
            if (array_key_exists($key, $previous) && !array_key_exists($key, $config)) {
                $config[$key] = $previous[$key];
            }
        }
        if ($catalog[$capability]['config'] === 'saved_search') {
            $searchId = trim((string) ($_POST['search_id'] ?? ''));
            if ($searchId === '') {
                View::respond('Choose a saved search. Create one from a Search link first if the list is empty.', Blocks::link($back, 'Navigation', 'URL'));
                return;
            }
            $config['search_id'] = $searchId;
        }
        if ($navId > 0) {
            ApplicationStore::updateNav($navId, $label, $capability, $permission, $icon, $config);
        } else {
            ApplicationStore::insertNav($applicationId, $label, $capability, $permission, $icon, $config);
        }
        header('Location: ' . $back);
        exit;
    }

    public static function groupAccessField(string $slug): string
    {
        $permission = $slug !== '' ? Permission::appUse($slug) : '';
        $rows = Database::select('groups', ['group_id', 'group_name'], '', [], 'group_name ASC');
        if ($rows === []) {
            return '<p class="form-help">No groups yet. Add a group from the account menu, then choose it here.</p>';
        }
        $html = '<div class="group-security-list">';
        foreach ($rows as $row) {
            $id = (string) $row['group_id'];
            $checked = '';
            if ($permission !== '') {
                $have = \Adlexone\Auth\Access::permissionsForGroup((int) $id);
                if (in_array($permission, $have, true)) {
                    $checked = $id;
                }
            } elseif (strtolower((string) $row['group_name']) === 'administrator') {
                $checked = $id;
            }
            $html .= Form::checkbox('groups[]', $id, $checked, (string) $row['group_name']);
        }
        $html .= '</div>';
        $html .= '<p class="form-help">People in a ticked group can open this application. Add a group from the account menu.</p>';

        return $html;
    }

    /**
     * @return list<int>|false
     */
    public static function postedGroupIds(): array|false
    {
        $posted = $_POST['groups'] ?? [];
        if (!is_array($posted)) {
            return false;
        }
        $known = [];
        foreach (Database::select('groups', ['group_id']) as $row) {
            $known[(int) $row['group_id']] = true;
        }
        $ids = [];
        foreach ($posted as $id) {
            if (!ctype_digit((string) $id) || !isset($known[(int) $id])) {
                return false;
            }
            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    public static function permissionSelect(string $name, string $selected, string $applicationName, string $slug): string
    {
        $applicationName = trim($applicationName);
        if ($applicationName === '') {
            $applicationName = 'Application';
        }
        $appOptions = $slug === '' ? [] : [
            Permission::appUse($slug) => 'Open',
            Permission::appSettings($slug) => 'Settings',
        ];
        $siteOptions = Permission::siteChoices();
        $known = $appOptions + $siteOptions;

        $html = Html::open('select', ['name' => $name, 'class' => 'select']);
        $html .= self::permissionOption('', 'Anyone who can open the application', $selected);
        if ($appOptions !== []) {
            $html .= Html::open('optgroup', ['label' => $applicationName]);
            foreach ($appOptions as $value => $label) {
                $html .= self::permissionOption($value, $label, $selected);
            }
            $html .= '</optgroup>';
        }
        $html .= Html::open('optgroup', ['label' => 'Site']);
        foreach ($siteOptions as $value => $label) {
            $html .= self::permissionOption($value, $label, $selected);
        }
        $html .= '</optgroup>';
        if ($selected !== '' && !isset($known[$selected])) {
            $catalog = Permission::catalog();
            $label = isset($catalog[$selected]) ? (string) $catalog[$selected]['label'] : $selected;
            $html .= self::permissionOption($selected, $label, $selected);
        }

        return $html . '</select>';
    }

    private static function permissionOption(string $value, string $label, string $selected): string
    {
        return Html::tag('option', [
            'value' => $value,
            'selected' => $value === $selected ? true : null,
        ], Html::e($label));
    }

    public static function iconSelect(string $name, string $selected, bool $allowEmpty = false): string
    {
        $selected = Capabilities::renamedIcons()[$selected] ?? $selected;
        if ($selected === '' && !$allowEmpty) {
            $selected = 'ic-grid';
        }
        $sprite = Html::e(\Adlexone\Theme\Theme::spriteHref());
        $html = '<div class="builder__icons" role="radiogroup">';
        if ($allowEmpty) {
            $active = $selected === '' ? ' is-selected' : '';
            $html .= '<label class="builder__icon' . $active . '" title="Use the screen icon">'
                . '<input type="radio" name="' . Html::e($name) . '" value=""' . ($selected === '' ? ' checked' : '') . '>'
                . '<span class="builder__icon-mark" aria-hidden="true"><svg class="icon"><use href="' . $sprite . '#ic-grid"></use></svg></span>'
                . '<span class="builder__icon-name">Screen</span>'
                . '</label>';
        }
        foreach (Capabilities::icons() as $icon) {
            $active = $icon === $selected ? ' is-selected' : '';
            $label = Capabilities::iconLabel($icon);
            $html .= '<label class="builder__icon' . $active . '" title="' . Html::e($label) . '">'
                . '<input type="radio" name="' . Html::e($name) . '" value="' . Html::e($icon) . '"' . ($icon === $selected ? ' checked' : '') . '>'
                . '<span class="builder__icon-mark" aria-hidden="true"><svg class="icon"><use href="' . $sprite . '#' . Html::e($icon) . '"></use></svg></span>'
                . '<span class="builder__icon-name">' . Html::e($label) . '</span>'
                . '</label>';
        }

        return $html . '</div>';
    }

    public static function recordTypeSelect(string $selected): string
    {
        $values = [''];
        $labels = ['None'];
        foreach (Database::select('item_types', ['item_type_id', 'item_type_name'], '', [], 'item_type_name ASC') as $row) {
            $values[] = (string) $row['item_type_id'];
            $labels[] = (string) $row['item_type_name'];
        }

        return Form::select('default_item_type_id', $values, $labels, $selected)
            . '<p class="form-help">Records in this application use this record type.</p>';
    }

    /**
     * @return string|false Empty string when none is chosen. False when the posted id is not a record type.
     */
    public static function postedRecordType(): string|false
    {
        $typeId = trim((string) ($_POST['default_item_type_id'] ?? ''));
        if ($typeId === '') {
            return '';
        }
        if (!ctype_digit($typeId)) {
            return false;
        }
        $row = Database::first('item_types', ['item_type_id'], 'item_type_id = ?', [(int) $typeId]);

        return $row === null ? false : $typeId;
    }

    public static function savedSearchSelect(string $selected, string $scope): string
    {
        $values = [''];
        $labels = ['Choose a saved search'];
        $rows = Database::select('saved_searches', ['search_id', 'search_name', 'application'], '', [], 'search_name ASC');
        foreach ($rows as $row) {
            $values[] = (string) $row['search_id'];
            $name = (string) $row['search_name'];
            if ((string) $row['application'] !== '' && (string) $row['application'] !== $scope) {
                $name .= ' (' . $row['application'] . ')';
            }
            $labels[] = Html::e($name);
        }
        return Form::select('search_id', $values, $labels, $selected);
    }

    public static function slugify(string $slug, string $name): string
    {
        $source = trim($slug) !== '' ? $slug : $name;
        $source = strtolower($source);
        $source = preg_replace('/[^a-z0-9]+/', '-', $source) ?? '';
        return trim($source, '-');
    }
}
