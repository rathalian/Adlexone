<?php
declare(strict_types=1);

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Capabilities;
use Adlexone\Auth\Permission;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\support\RenderViews;

RenderViews::terminateUnlessAllowed(Permission::ADMIN_SETTINGS);

define('APPLICATIONS_ADMIN_URL', 'index.php?controller=administration_applications');
$option = (string) ($_GET['option'] ?? '');

$section = RenderViews::buildURL(APPLICATIONS_ADMIN_URL, 'Applications', 'ic-launch');
if (in_array($option, ['edit', 'update', 'nav', 'new_nav', 'add_nav', 'edit_nav', 'update_nav'], true)) {
    $sectionApp = (int) ($_GET['application_id'] ?? $_POST['application_id'] ?? 0);
    if ($sectionApp > 0) {
        $section .= RenderViews::buildURL(APPLICATIONS_ADMIN_URL . '&option=edit&application_id=' . $sectionApp, 'Details', 'ic-settings');
        $section .= RenderViews::buildURL(APPLICATIONS_ADMIN_URL . '&option=nav&application_id=' . $sectionApp, 'Navigation', 'ic-manage-fields');
    }
}
RenderNavigation::applySectionNav('Applications', $section);
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Applications');
}

switch ($option) {
    case 'new':
        showApplicationForm();
        break;
    case 'add':
        saveApplication(0);
        break;
    case 'edit':
        showApplicationForm((int) ($_GET['application_id'] ?? 0));
        break;
    case 'update':
        saveApplication((int) ($_POST['application_id'] ?? 0));
        break;
    case 'delete':
        ApplicationStore::deleteApplication((int) ($_GET['application_id'] ?? 0));
        header('Location: ' . APPLICATIONS_ADMIN_URL);
        exit;
    case 'move':
        ApplicationStore::moveApplication((int) ($_GET['application_id'] ?? 0), (string) ($_GET['direction'] ?? ''));
        header('Location: ' . APPLICATIONS_ADMIN_URL);
        exit;
    case 'nav':
        showNavigation((int) ($_GET['application_id'] ?? 0));
        break;
    case 'new_nav':
        showNavForm((int) ($_GET['application_id'] ?? 0));
        break;
    case 'add_nav':
        saveNav(0);
        break;
    case 'edit_nav':
        showNavForm(0, (int) ($_GET['nav_id'] ?? 0));
        break;
    case 'update_nav':
        saveNav((int) ($_POST['nav_id'] ?? 0));
        break;
    case 'delete_nav':
        $nav = ApplicationStore::findNav((int) ($_GET['nav_id'] ?? 0));
        if ($nav !== null) {
            ApplicationStore::deleteNav((int) $nav['nav_id']);
            header('Location: ' . APPLICATIONS_ADMIN_URL . '&option=nav&application_id=' . (int) $nav['application_id']);
            exit;
        }
        header('Location: ' . APPLICATIONS_ADMIN_URL);
        exit;
    case 'move_nav':
        $nav = ApplicationStore::findNav((int) ($_GET['nav_id'] ?? 0));
        if ($nav !== null) {
            ApplicationStore::moveNav((int) $nav['nav_id'], (string) ($_GET['direction'] ?? ''));
            header('Location: ' . APPLICATIONS_ADMIN_URL . '&option=nav&application_id=' . (int) $nav['application_id']);
            exit;
        }
        header('Location: ' . APPLICATIONS_ADMIN_URL);
        exit;
    default:
        showApplications();
        break;
}

function showApplications(): void
{
    $rows = [];
    foreach (ApplicationStore::all() as $app) {
        $id = (int) $app['application_id'];
        $rows[] = [
            'name' => (string) $app['name'],
            'href' => APPLICATIONS_ADMIN_URL . '&option=edit&application_id=' . $id,
            'cells' => [
                'menu' => $app['enabled'] ? 'Shown' : 'Hidden',
                'opens' => $app['entry_mode'] === 'legacy' ? 'Existing screens' : 'This application',
            ],
            'search' => (string) $app['slug'],
            'actions' => [
                [
                    'href' => APPLICATIONS_ADMIN_URL . '&option=nav&application_id=' . $id,
                    'label' => 'Navigation',
                ],
                [
                    'href' => APPLICATIONS_ADMIN_URL . '&option=move&application_id=' . $id . '&direction=up',
                    'label' => 'Up',
                ],
                [
                    'href' => APPLICATIONS_ADMIN_URL . '&option=move&application_id=' . $id . '&direction=down',
                    'label' => 'Down',
                ],
                [
                    'href' => APPLICATIONS_ADMIN_URL . '&option=delete&application_id=' . $id,
                    'label' => 'Delete',
                    'tone' => 'danger',
                    'confirm' => 'Delete ' . $app['name'] . ' and its navigation?',
                ],
            ],
        ];
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([[
        'title' => 'Applications',
        'html' => RenderViews::buildRecordList([
            'column' => 'Application',
            'columns' => [
                ['key' => 'menu', 'label' => 'Menu'],
                ['key' => 'opens', 'label' => 'Opens'],
            ],
            'searchLabel' => 'Search',
            'primary' => ['href' => APPLICATIONS_ADMIN_URL . '&option=new', 'label' => 'New application'],
            'empty' => 'No applications yet.',
            'groups' => [['rows' => $rows]],
        ]),
    ]]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showApplicationForm(int $id = 0): void
{
    $app = $id > 0 ? ApplicationStore::find($id) : null;
    if ($id > 0 && $app === null) {
        RenderViews::buildResponse('That application does not exist.', RenderViews::buildURL(APPLICATIONS_ADMIN_URL, 'Applications', 'URL'));
        return;
    }

    $permission = (string) ($app['permission'] ?? Permission::APP_ACCESS);
    $fields = [
        'Name' => RenderViews::buildTextInput('name', htmlspecialchars((string) ($app['name'] ?? ''), ENT_QUOTES, 'UTF-8')),
        'Slug' => RenderViews::buildTextInput('slug', htmlspecialchars((string) ($app['slug'] ?? ''), ENT_QUOTES, 'UTF-8'), 'complaints'),
        'Description' => RenderViews::buildTextInput('hint', htmlspecialchars((string) ($app['hint'] ?? ''), ENT_QUOTES, 'UTF-8')),
        'Icon' => iconSelect('icon', (string) ($app['icon'] ?? 'ic-launch')),
        'Who can open it' => permissionSelect('permission', $permission, false),
        'Menu' => RenderViews::buildCheckBox('enabled', '1', ($app === null || !empty($app['enabled'])) ? '1' : '0', 'checkbox', 'Show in the Applications menu'),
    ];
    if ($app !== null) {
        $fields[''] = RenderViews::buildHiddenInput('application_id', (string) $app['application_id']);
    }
    $action = $app === null ? APPLICATIONS_ADMIN_URL . '&option=add' : APPLICATIONS_ADMIN_URL . '&option=update';
    $html = RenderViews::buildForm($app === null ? 'New application' : (string) $app['name'], $action, $fields, [
        RenderViews::buildFormButton('submit', 'submit_button', 'Save'),
    ]);
    if ($app !== null && $app['entry_mode'] === 'legacy') {
        $html .= '<p class="record-list__empty">This application still opens its existing screens. Its name and menu visibility come from here.</p>';
    }
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function saveApplication(int $id): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = slugify((string) ($_POST['slug'] ?? ''), $name);
    $permission = (string) ($_POST['permission'] ?? '');
    $icon = (string) ($_POST['icon'] ?? 'ic-launch');
    if ($name === '' || $slug === '' || !isset(Permission::catalog()[$permission]) || !in_array($icon, Capabilities::icons(), true)) {
        RenderViews::buildResponse('Enter a name and choose who can open the application.', RenderViews::buildURL(APPLICATIONS_ADMIN_URL . '&option=new', 'Back', 'URL'));
        return;
    }
    if (ApplicationStore::slugInUse($slug, $id)) {
        RenderViews::buildResponse('That slug is already used.', RenderViews::buildURL(APPLICATIONS_ADMIN_URL, 'Applications', 'URL'));
        return;
    }
    $fields = [
        'slug' => $slug,
        'name' => $name,
        'hint' => trim((string) ($_POST['hint'] ?? '')),
        'icon' => $icon,
        'permission' => $permission,
        'enabled' => isset($_POST['enabled']) ? 1 : 0,
    ];
    if ($id > 0) {
        if (ApplicationStore::find($id) === null) {
            RenderViews::buildResponse('That application does not exist.', RenderViews::buildURL(APPLICATIONS_ADMIN_URL, 'Applications', 'URL'));
            return;
        }
        ApplicationStore::updateApplication($id, $fields);
        header('Location: ' . APPLICATIONS_ADMIN_URL . '&option=edit&application_id=' . $id);
        exit;
    }
    $max = 0;
    foreach (ApplicationStore::all() as $app) {
        $max = max($max, (int) $app['sort_order']);
    }
    $fields['sort_order'] = $max + 10;
    $fields['entry_mode'] = 'shell';
    $newId = ApplicationStore::insertApplication($fields);
    header('Location: ' . APPLICATIONS_ADMIN_URL . '&option=nav&application_id=' . $newId);
    exit;
}

function showNavigation(int $applicationId): void
{
    $app = ApplicationStore::find($applicationId);
    if ($app === null) {
        RenderViews::buildResponse('That application does not exist.', RenderViews::buildURL(APPLICATIONS_ADMIN_URL, 'Applications', 'URL'));
        return;
    }
    if ($app['entry_mode'] === 'legacy') {
        RenderViews::buildResponse(
            'This application opens its existing screens. Navigation for it stays in those screens.',
            RenderViews::buildURL(APPLICATIONS_ADMIN_URL . '&option=edit&application_id=' . $applicationId, 'Details', 'URL')
        );
        return;
    }

    $rows = [];
    foreach (ApplicationStore::navigation($applicationId) as $link) {
        $id = (int) $link['nav_id'];
        $rows[] = [
            'name' => (string) $link['label'],
            'href' => APPLICATIONS_ADMIN_URL . '&option=edit_nav&nav_id=' . $id,
            'cells' => [
                'screen' => Capabilities::choiceLabel((string) $link['capability']),
            ],
            'actions' => [
                ['href' => APPLICATIONS_ADMIN_URL . '&option=move_nav&nav_id=' . $id . '&direction=up', 'label' => 'Up'],
                ['href' => APPLICATIONS_ADMIN_URL . '&option=move_nav&nav_id=' . $id . '&direction=down', 'label' => 'Down'],
                [
                    'href' => APPLICATIONS_ADMIN_URL . '&option=delete_nav&nav_id=' . $id,
                    'label' => 'Delete',
                    'tone' => 'danger',
                    'confirm' => 'Remove ' . $link['label'] . ' from the navigation?',
                ],
            ],
        ];
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([[
        'title' => (string) $app['name'],
        'html' => RenderViews::buildRecordList([
            'column' => 'Link',
            'columns' => [
                ['key' => 'screen', 'label' => 'Screen'],
            ],
            'searchLabel' => 'Search',
            'primary' => [
                'href' => APPLICATIONS_ADMIN_URL . '&option=new_nav&application_id=' . $applicationId,
                'label' => 'Add link',
            ],
            'empty' => 'No links yet. Add create, search, or another screen.',
            'groups' => [['rows' => $rows]],
        ]),
    ]]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showNavForm(int $applicationId, int $navId = 0): void
{
    $link = $navId > 0 ? ApplicationStore::findNav($navId) : null;
    if ($navId > 0 && $link === null) {
        RenderViews::buildResponse('That link does not exist.', RenderViews::buildURL(APPLICATIONS_ADMIN_URL, 'Applications', 'URL'));
        return;
    }
    if ($link !== null) {
        $applicationId = (int) $link['application_id'];
    }
    $app = ApplicationStore::find($applicationId);
    if ($app === null || $app['entry_mode'] === 'legacy') {
        RenderViews::buildResponse('That application does not exist.', RenderViews::buildURL(APPLICATIONS_ADMIN_URL, 'Applications', 'URL'));
        return;
    }

    $config = is_array($link['config'] ?? null) ? $link['config'] : [];
    $capability = (string) ($link['capability'] ?? 'items.create');
    $keys = Capabilities::choiceKeys();
    $labels = array_map(static fn (string $key): string => Capabilities::choiceLabel($key), $keys);
    $fields = [
        'Label' => RenderViews::buildTextInput('label', htmlspecialchars((string) ($link['label'] ?? ''), ENT_QUOTES, 'UTF-8')),
        'Screen' => RenderViews::buildSelectDropdown('capability', $keys, $labels, $capability),
        'Who can see it' => permissionSelect('permission', (string) ($link['permission'] ?? ''), true),
        'Icon' => iconSelect('icon', (string) ($link['icon'] ?? ''), true),
        'Item type' => itemTypeSelect((string) ($config['item_type_id'] ?? '')),
        'Saved search' => savedSearchSelect((string) ($config['search_id'] ?? ''), ApplicationStore::scopeKey($app)),
        '' => RenderViews::buildHiddenInput('application_id', (string) $applicationId)
            . ($link === null ? '' : RenderViews::buildHiddenInput('nav_id', (string) $link['nav_id'])),
    ];
    $action = $link === null ? APPLICATIONS_ADMIN_URL . '&option=add_nav' : APPLICATIONS_ADMIN_URL . '&option=update_nav';
    define('BODY_CONTENT', RenderViews::buildForm(
        $link === null ? 'Add link' : 'Edit link',
        $action,
        $fields,
        [RenderViews::buildFormButton('submit', 'submit_button', 'Save')]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function saveNav(int $navId): void
{
    $applicationId = (int) ($_POST['application_id'] ?? 0);
    if ($navId > 0) {
        $existing = ApplicationStore::findNav($navId);
        if ($existing === null) {
            RenderViews::buildResponse('That link does not exist.', RenderViews::buildURL(APPLICATIONS_ADMIN_URL, 'Applications', 'URL'));
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
    $back = APPLICATIONS_ADMIN_URL . '&option=nav&application_id=' . $applicationId;
    if ($app === null || $label === '' || !isset($catalog[$capability])) {
        RenderViews::buildResponse('Enter a label and choose a screen.', RenderViews::buildURL($back, 'Navigation', 'URL'));
        return;
    }
    if ($permission !== '' && !isset(Permission::catalog()[$permission])) {
        RenderViews::buildResponse('Choose a permission from the list.', RenderViews::buildURL($back, 'Navigation', 'URL'));
        return;
    }
    if ($icon !== '' && !in_array($icon, Capabilities::icons(), true)) {
        $icon = '';
    }
    $config = [];
    if ($catalog[$capability]['config'] === 'item_type') {
        $config['item_type_id'] = trim((string) ($_POST['item_type_id'] ?? ''));
    }
    if ($catalog[$capability]['config'] === 'saved_search') {
        $searchId = trim((string) ($_POST['search_id'] ?? ''));
        if ($searchId === '') {
            RenderViews::buildResponse('Choose a saved search. Create one from a Search link first if the list is empty.', RenderViews::buildURL($back, 'Navigation', 'URL'));
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

function permissionSelect(string $name, string $selected, bool $allowEmpty): string
{
    $values = [];
    $labels = [];
    if ($allowEmpty) {
        $values[] = '';
        $labels[] = 'Anyone who can open the application';
    }
    foreach (Permission::catalog() as $key => $meta) {
        $values[] = $key;
        $labels[] = (string) $meta['label'];
    }
    return RenderViews::buildSelectDropdown($name, $values, $labels, $selected);
}

function iconSelect(string $name, string $selected, bool $allowEmpty = false): string
{
    $values = $allowEmpty ? [''] : [];
    $labels = $allowEmpty ? ['Use the screen icon'] : [];
    foreach (Capabilities::icons() as $icon) {
        $values[] = $icon;
        $labels[] = $icon;
    }
    if ($selected === '' && !$allowEmpty) {
        $selected = 'ic-launch';
    }
    return RenderViews::buildSelectDropdown($name, $values, $labels, $selected);
}

function itemTypeSelect(string $selected): string
{
    $values = [''];
    $labels = ['All item types'];
    $rows = Database::select('item_types', ['item_type_id', 'item_type_name'], 'ORDER BY item_type_name ASC');
    foreach ($rows as $row) {
        $values[] = (string) $row['item_type_id'];
        $labels[] = htmlspecialchars((string) $row['item_type_name'], ENT_QUOTES, 'UTF-8');
    }
    return RenderViews::buildSelectDropdown('item_type_id', $values, $labels, $selected);
}

function savedSearchSelect(string $selected, string $scope): string
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
        $labels[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    }
    return RenderViews::buildSelectDropdown('search_id', $values, $labels, $selected);
}

function slugify(string $slug, string $name): string
{
    $source = trim($slug) !== '' ? $slug : $name;
    $source = strtolower($source);
    $source = preg_replace('/[^a-z0-9]+/', '-', $source) ?? '';
    return trim($source, '-');
}
