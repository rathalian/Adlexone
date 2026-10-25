<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\ApplicationStore;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\Http\Page;
use Adlexone\Auth\Access;
use Adlexone\Auth\Permission;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\support\FormState;
use Adlexone\Http\View;
use Adlexone\Html\Html;
use Adlexone\Platform\DefaultAccount;

/**
 * Screen formerly loaded from legacy/.
 */
final class SecurityScreen
{
    public static function open(): void
    {
        /**
         * Controller specific constants
         */
        define('SEC_BASE_URL', '?manage=security');

        $securityOption = (string)($_GET['option'] ?? '');
        if ($securityOption !== 'modify_user') {
            $groupSectionOptions = ['manage_groups', 'new_group', 'modify_group', 'add_group', 'update_group', 'delete_group'];
            if (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SECURITY)) {
                $userSectionOptions = ['', 'manage_users_groups', 'new_user', 'admin_modify_user', 'add_user', 'update_user', 'delete_user', 'modify_group_membership', 'update_group_membership'];
                if (in_array($securityOption, $userSectionOptions, true)) {
                    Page::current()->setNavOption('manage_users');
                } elseif (in_array($securityOption, $groupSectionOptions, true)) {
                    Page::current()->setNavOption('manage_groups');
                }
            }
            $groupPage = in_array($securityOption, $groupSectionOptions, true);
            RenderNavigation::applyConfigureNav([['label' => $groupPage ? 'Groups' : 'People']]);
        }







































        /**
         * Logic to render the appropriate template or call wrapper functions
         * Option is captured from the value selected via a hyperlink
         */
        switch (@$_GET['option']) {
            case 'manage_users' :
            case 'manage_users_groups' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::showUsers();
                break;
            case 'manage_groups' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::showGroups();
                break;
            case 'user_group_search' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::showUserGroupResults();
                break;
            case 'new_user' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                // Removes leading VBL_ from any session variables (used for form value persistence)
                self::showUser('', FormState::keys($_SESSION, 'remove'));
                // Unset session variables starting with VBL_
                $_SESSION = FormState::keys($_SESSION, 'unset');
                break;
            case 'admin_modify_user' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::showUser((string) ($_GET['user_id'] ?? ''), '', true);
                break;
            case 'modify_user' :
                Access::require(\Adlexone\Auth\Permission::APP_ACCESS);
                self::showUser($_SESSION['access_user_id'], '', false);
                break;
            case 'new_group' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                // Removes leading VBL_ from any session variables (used for form value persistence)
                self::showGroup('', FormState::keys($_SESSION, 'remove'));
                // Unset session variables starting with VBL_
                $_SESSION = FormState::keys($_SESSION, 'unset');
                break;
            case 'add_user' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::addUser();
                break;
            case 'update_user' :
                Access::require(\Adlexone\Auth\Permission::APP_ACCESS);
                self::updateUser($_POST['user_id'] ?? 0);
                break;
            case 'delete_user' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::deleteUser($_GET['user_id'] ?? 0);
                break;
            case 'add_group' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::addGroup();
                break;
            case 'modify_group' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                // Removes leading VBL_ from any session variables (used for form value persistence)
                self::showGroup((string) ($_GET['group_id'] ?? ''), FormState::keys($_SESSION, 'remove'));
                // Unset session variables starting with VBL_
                $_SESSION = FormState::keys($_SESSION, 'unset');
                break;
            case 'modify_group_membership' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::showGroupMembership($_GET['user_id'] ?? 0);
                break;
            case 'update_group_membership' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::updateGroupMembership($_GET['user_id'] ?? 0);
                break;
            case 'update_group' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::updateGroup($_POST['group_id'] ?? 0);
                break;
            case 'delete_group' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SECURITY);
                self::deleteGroup($_GET['group_id'] ?? 0);
                break;
            default :
                if (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SECURITY)) {
                    self::showUsers();
                } else {
                    self::showUser($_SESSION['access_user_id'], '', false);
                }
                break;
        }
    }
    /**
     * Shows secured security options
     */
    public static function showSecurityOptions(): void
    {
        $html = Access::outputIfAllowed(Blocks::link(SEC_BASE_URL . '&option=manage_users_groups', TXT_73, 'URL'), \Adlexone\Auth\Permission::ADMIN_SECURITY);
        $html .= Access::outputIfAllowed('<br>' . Blocks::link(SEC_BASE_URL . '&option=new_user', TXT_33, 'URL'), \Adlexone\Auth\Permission::ADMIN_SECURITY);
        $html .= Access::outputIfAllowed('<br>' . Blocks::link(SEC_BASE_URL . '&option=new_group', TXT_34, 'URL'), \Adlexone\Auth\Permission::ADMIN_SECURITY);
        Page::current()->setBody(Blocks::vertical([['title' => TXT_28, 'html' => $html]]));
    }

    /**
     * Shows add and edit user pages. Controller logic: new_user, modify_user, admin_modify_user
     *
     * @param string $userID Users ID
     * @param array $values Field values passed in via $_SESSION array for retaining form field values if error occurred during entry
     * @param bool $adminEdit If TRUE the user is being edited by an administrator, if FALSE the user is updating their profile
     */
    public static function showUser($userID = '', $values = [], $adminEdit = true)
    {

        if ($userID === '') {
            $fieldValues = is_array($values) ? $values : [];
        } else {
            $fieldValues = Database::first('users', '*', 'user_id = ?', [(int) $userID]);
            if ($fieldValues === null) {
                View::respond('That person was not found.', Blocks::link(SEC_BASE_URL . '&option=manage_users', TXT_160, 'URL'));
                return;
            }
        }

        $option = $userID === '' ? 'add_user' : 'update_user';

        $fields = [];
        if ($adminEdit) {
            $fields[TXT_149] = Form::text('user_name', @$fieldValues['user_name']);
        } else {
            $fields[TXT_149] = Form::hidden('user_name', @$fieldValues['user_name']) . @$fieldValues['user_name'];
        }
        $message = $userID !== '' ? TXT_159 : '';
        $fields[TXT_165] = Form::password('password_ftype', '') . ' ' . $message;
        if ($userID === '') {
            $fields[TXT_166] = Form::password('password_confirm', '') . ' ' . $message;
        }
        $fields[TXT_167] = Form::text('first_name', @$fieldValues['first_name']);
        $fields[TXT_168] = Form::text('last_name', @$fieldValues['last_name']);
        $fields[TXT_169] = Form::text('email', @$fieldValues['email']);

        if (!\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SYSTEM)) {
            $roleIDs = [1, 2, 3, 4, 5];
            $roleNames = [TXT_191, TXT_192, TXT_193, TXT_194, TXT_303];
        } else {
            $roleIDs = [0, 1, 2, 3, 4, 5];
            $roleNames = [TXT_190, TXT_191, TXT_192, TXT_193, TXT_194, TXT_303];
        }

        $fields[TXT_187] = Access::outputIfAllowed(
            Form::select('role', $roleIDs, $roleNames, @$fieldValues['role']),
            \Adlexone\Auth\Permission::ADMIN_SECURITY
        );
        $fields[TXT_653] = Access::outputIfAllowed(
            Form::select('lastactive', ['active', 'inactive'], [TXT_93, TXT_94], @$fieldValues['lastactive']),
            \Adlexone\Auth\Permission::ADMIN_SECURITY
        );
        if (empty($fields[TXT_187])) {
            unset($fields[TXT_187]);
        }
        if (empty($fields[TXT_653])) {
            unset($fields[TXT_653]);
        }

        $fields[TXT_171] = Form::text('phone', @$fieldValues['phone']);

        $userPreferences = [];
        if ($adminEdit) {
            $themes = \Adlexone\Theme\Theme::installed();
            $userPreferences[TXT_182] = \Adlexone\Theme\Theme::picker('theme', (string) ($fieldValues['theme'] ?? ''), false, 'Follow the site theme');
        }

        $languageFileArray = [];
        foreach (scandir(SET_INSTALL_PATH . 'translations/') as $languageFile) {
            $filePath = SET_INSTALL_PATH . 'translations/' . $languageFile;
            if (is_file($filePath) && preg_match('/\.lang\.php$/', $languageFile)) {
                $languageFileArray[] = str_replace('.lang.php', '', $languageFile);
            }
        }
        $language = empty($fieldValues['language']) ? SET_DEFAULT_LANGUAGE : $fieldValues['language'];
        $userPreferences[TXT_181] = Form::select('language', $languageFileArray, $languageFileArray, $language);

        $nameArray = ['Home'];
        $baseURLArray = ['home}-{Home'];
        ApplicationStore::mergeHomeChoices($baseURLArray, $nameArray);
        $application = empty($fieldValues['home_controller']) ? SET_DEFAULT_APPLICATION : $fieldValues['home_controller'] . '}-{' . @$fieldValues['home_controller_name'];
        $userPreferences[TXT_297] = Form::select('home_controller', $baseURLArray, $nameArray, $application);

        foreach ($userPreferences as $label => $element) {
            $fields[$label] = $element;
        }
        $fields[''] = Form::hidden('user_id', @$fieldValues['user_id']);

        if ($userID === '') {
            $jsFieldNameArray = "['user_name','password_ftype','password_confirm','email','first_name','last_name']";
            $jsRequiredMsgArray = "['" . TXT_471 . "','" . TXT_472 . "','" . TXT_473 . "','" . TXT_474 . "','" . TXT_549 . "','" . TXT_550 . "']";
            $jsRequiredArray = "[true,true,true,true,true,true]";
        } else {
            $jsFieldNameArray = "['user_name','','','email','first_name','last_name']";
            $jsRequiredMsgArray = "['" . TXT_470 . "','','','" . TXT_474 . "','" . TXT_549 . "','" . TXT_550 . "']";
            $jsRequiredArray = "[true,false,false,true,true,true]";
        }
        $jsTestTypeArray = "['','','','email','','']";
        $jsErrorMsgArray = "['','','','" . TXT_475 . "','','']";
        $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";
        $submitText = $userID !== '' ? TXT_74 : TXT_69;
        $heading = $userID !== '' ? ($adminEdit ? 'Edit person' : 'My profile') : 'Add person';
        if (Page::current()->hasSectionNav()) {
            Page::current()->suggestTitle($heading);
        }

        Page::current()->setBody(Form::form(
            $heading,
            SEC_BASE_URL . '&option=' . $option,
            $fields,
            [
                Form::button('submit', 'submit_button', $submitText, $javascript),
                Form::button('reset', 'reset', TXT_75),
            ]
        ));
    }

    /**
     * Shows add and edit group pages.  Controller logic: new_group, modify_group
     *
     * @param string $groupID Groups ID
     * @param string $values Field values passes in via $_SESSION array for retaining form field values if error occurred during entry
     */
    public static function showGroup($groupID = '', $values = '')
    {
        $isNew = ($groupID == '');
        if ($isNew) {
            $fieldValues = is_array($values) ? $values : [];
            $action = SEC_BASE_URL . '&option=add_group';
            $selectedPermissions = [];
        } else {
            $columnArray = array('group_id', 'group_name', 'description', 'role');
            $fieldValues = Database::first('groups', $columnArray, 'group_id = ?', [(int) $groupID]);
            if ($fieldValues === null) {
                View::respond('That group was not found.', Blocks::link(SEC_BASE_URL . '&option=manage_groups', TXT_160, 'URL'));
                return;
            }
            $action = SEC_BASE_URL . '&option=update_group';
            $selectedPermissions = \Adlexone\Auth\Access::permissionsForGroup((int) $groupID);
        }
        $roleIDs = array(1, 2, 3, 4, 5);
        $roleNames = array(TXT_191, TXT_192, TXT_193, TXT_194, TXT_303);
        $fields = [
            TXT_150 => Form::text('group_name', $fieldValues['group_name'] ?? ''),
            TXT_186 => Form::textarea('description', $fieldValues['description'] ?? '', SET_FORM_FIELD_HEIGHT),
            TXT_187 => '<div class="field-stack">'
                . Form::select('role', $roleIDs, $roleNames, $fieldValues['role'] ?? '')
                . '<p class="form-help">' . Html::e(TXT_697) . '</p>'
                . '</div>',
            'Site permissions' => self::sitePermissionsField($selectedPermissions),
            'Application permissions' => self::applicationPermissionsField($selectedPermissions),
        ];
        $fields[] = Form::hidden('group_id', $fieldValues['group_id'] ?? '');
        $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "',[''],['group_name'],[''],['" . TXT_197 . "'],[true]);\"";
        $heading = $isNew ? 'Add group' : 'Edit group';
        Page::current()->suggestTitle($heading);
        Page::current()->setBody(Form::form(
            $heading,
            $action,
            $fields,
            [
                Form::button('submit', 'submit_button', TXT_74, $javascript),
                Form::button('reset', 'reset', TXT_75),
                '<a class="btn btn--quiet" href="' . Html::e(SEC_BASE_URL . '&option=manage_groups') . '">'
                    . Html::e(TXT_160) . '</a>',
            ]
        ));
    }

    /**
     * @param list<string> $selected
     */
    private static function sitePermissionsField(array $selected): string
    {
        $html = '<div class="group-security-list">';
        foreach (Permission::siteChoices() as $key => $label) {
            $checked = in_array($key, $selected, true) ? $key : '';
            $html .= '<div class="group-security-item">'
                . Form::checkbox('permissions[]', $key, $checked, $label)
                . '</div>';
        }
        $html .= '</div>';
        $html .= '<p class="form-help">These apply to the whole site.</p>';

        return $html;
    }

    /**
     * @param list<string> $selected
     */
    private static function applicationPermissionsField(array $selected): string
    {
        $apps = Permission::applicationChoices();
        if ($apps === []) {
            return '<p class="form-help">No applications yet. Create one from Design, then choose who can open it here.</p>';
        }
        $levels = [
            'open' => 'Open',
            'settings' => 'Settings',
        ];
        $html = '<div class="perm-apps">';
        foreach ($apps as $app) {
            $html .= '<div class="perm-app">'
                . '<p class="perm-app__name">' . Html::e($app['name']) . '</p>'
                . '<div class="perm-app__levels">';
            foreach ($levels as $level => $label) {
                $key = $app[$level];
                $checked = in_array($key, $selected, true) ? $key : '';
                $html .= Form::checkbox('permissions[]', $key, $checked, $label);
            }
            $html .= '</div></div>';
        }
        $html .= '</div>';
        $html .= '<p class="form-help">Open lets this group use that application. Settings lets them change how it works.</p>';

        return $html;
    }

    /**
     * Users, listed the same way as Manage Fields.
     */
    public static function showUsers(): void
    {
        $rows = [];
        foreach (Database::select('users', '*', 'ORDER BY last_name ASC, first_name ASC, user_name ASC') as $row) {
            $rows[] = self::userRecord($row);
        }

        Page::current()->setBody(Blocks::vertical([
            ['title' => TXT_40, 'html' => self::userRecordList($rows)],
        ]));
    }

    /**
     * Security groups, listed the same way as Manage Fields.
     */
    public static function showGroups(): void
    {
        $rows = [];
        foreach (Database::select('groups', '*', 'ORDER BY group_name ASC') as $row) {
            $rows[] = self::groupRecord($row);
        }

        Page::current()->setBody(Blocks::vertical([
            ['title' => TXT_35, 'html' => self::groupRecordList($rows)],
        ]));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public static function userRecordList(array $rows): string
    {
        return Blocks::recordList([
            'column' => TXT_151,
            'columns' => [
                ['key' => 'username', 'label' => TXT_38],
                ['key' => 'email', 'label' => TXT_514],
                ['key' => 'role', 'label' => TXT_187],
                ['key' => 'active', 'label' => TXT_653],
            ],
            'searchLabel' => TXT_3,
            'primary' => ['href' => SEC_BASE_URL . '&option=new_user', 'label' => 'Add person'],
            'empty' => TXT_115,
            'emptyIllustration' => 'empty-people',
            'emptyTitle' => 'No users yet',
            'emptyCopy' => 'Add a person when they need access.',
            'noMatch' => TXT_689,
            'groups' => [['rows' => $rows]],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public static function groupRecordList(array $rows): string
    {
        return Blocks::recordList([
            'column' => TXT_151,
            'columns' => [
                ['key' => 'description', 'label' => TXT_153, 'wrap' => true],
                ['key' => 'role', 'label' => TXT_187],
            ],
            'searchLabel' => TXT_3,
            'primary' => ['href' => SEC_BASE_URL . '&option=new_group', 'label' => 'Add group'],
            'empty' => TXT_115,
            'emptyIllustration' => 'empty-people',
            'emptyTitle' => 'No groups yet',
            'emptyCopy' => 'A group is the set of people who share a record.',
            'noMatch' => TXT_689,
            'groups' => [['rows' => $rows]],
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function userRecord(array $row): array
    {
        $userName = (string)($row['user_name'] ?? '');
        $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($name === '') {
            $name = $userName;
            $userName = '';
        }
        $id = rawurlencode((string)$row['user_id']);
        $active = ((string)($row['lastactive'] ?? '') === 'inactive') ? TXT_94 : TXT_93;
        return [
            'name' => $name,
            'mark' => $name,
            'href' => SEC_BASE_URL . '&option=admin_modify_user&user_id=' . $id,
            'cells' => [
                'username' => $userName,
                'email' => (string)($row['email'] ?? ''),
                'role' => self::securityRoleLabel($row['role'] ?? ''),
                'active' => $active,
            ],
            'actions' => [
                [
                    'href' => SEC_BASE_URL . '&option=admin_modify_user&user_id=' . $id,
                    'label' => TXT_626,
                    'tone' => 'quiet',
                ],
                [
                    'href' => SEC_BASE_URL . '&option=modify_group_membership&user_id=' . $id,
                    'label' => TXT_239,
                    'tone' => 'quiet',
                ],
                [
                    'href' => SEC_BASE_URL . '&option=delete_user&user_id=' . $id,
                    'label' => TXT_47,
                    'tone' => 'danger',
                    'confirm' => $name . "\n" . TXT_400,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function groupRecord(array $row): array
    {
        $name = (string)($row['group_name'] ?? '');
        $id = rawurlencode((string)$row['group_id']);
        $description = trim((string)preg_replace('/\s+/', ' ', (string)($row['description'] ?? '')));
        return [
            'name' => $name,
            'href' => SEC_BASE_URL . '&option=modify_group&group_id=' . $id,
            'cells' => [
                'description' => $description,
                'role' => self::securityRoleLabel($row['role'] ?? ''),
            ],
            'actions' => [
                [
                    'href' => SEC_BASE_URL . '&option=modify_group&group_id=' . $id,
                    'label' => TXT_626,
                    'tone' => 'quiet',
                ],
                [
                    'href' => SEC_BASE_URL . '&option=delete_group&group_id=' . $id,
                    'label' => TXT_47,
                    'tone' => 'danger',
                    'confirm' => $name . "\n" . TXT_400,
                ],
            ],
        ];
    }

    public static function securityRoleLabel(mixed $role): string
    {
        $labels = [
            '0' => TXT_190,
            '1' => TXT_191,
            '2' => TXT_192,
            '3' => TXT_193,
            '4' => TXT_194,
            '5' => TXT_303,
        ];

        return $labels[trim((string)$role)] ?? '';
    }

    /**
     * Columns a person may change on their own profile.
     *
     * @var list<string>
     */
    private const PROFILE_COLUMNS = [
        'first_name', 'last_name', 'email', 'phone', 'language',
        'show_header', 'show_graphics',
    ];

    /**
     * Columns only an administrator may change.
     *
     * @var list<string>
     */
    private const ADMIN_COLUMNS = ['user_name', 'theme', 'role', 'lastactive'];

    /**
     * Columns of the users table taken from the posted form.
     *
     * @return array<string, mixed>
     */
    private static function postedUserColumns(bool $admin): array
    {
        $allowed = $admin ? array_merge(self::PROFILE_COLUMNS, self::ADMIN_COLUMNS) : self::PROFILE_COLUMNS;
        $columns = [];
        foreach ($allowed as $name) {
            if (!isset($_POST[$name]) || !is_scalar($_POST[$name])) {
                continue;
            }
            $columns[$name] = trim((string) $_POST[$name]);
        }
        if (isset($columns['role'])) {
            $role = (int) $columns['role'];
            $min = Access::can(Permission::ADMIN_SYSTEM) ? 0 : 1;
            $columns['role'] = max($min, min(5, $role));
        }
        if (isset($columns['lastactive']) && !in_array($columns['lastactive'], ['active', 'inactive'], true)) {
            unset($columns['lastactive']);
        }
        if (isset($columns['show_header']) && !in_array($columns['show_header'], ['Yes', 'No'], true)) {
            unset($columns['show_header']);
        }
        if (isset($columns['show_graphics']) && !in_array($columns['show_graphics'], ['Yes', 'No'], true)) {
            unset($columns['show_graphics']);
        }
        if (isset($columns['language'])) {
            $language = basename($columns['language']);
            if (!is_file(SET_INSTALL_PATH . 'translations/' . $language . '.lang.php')) {
                unset($columns['language']);
            } else {
                $columns['language'] = $language;
            }
        }
        if (isset($columns['theme'])) {
            $theme = \Adlexone\Theme\Theme::canonicalize($columns['theme']);
            if ($theme === '' || isset(\Adlexone\Theme\Theme::installed()[$theme])) {
                $columns['theme'] = $theme;
            } else {
                unset($columns['theme']);
            }
        }

        return $columns;
    }

    /**
     * @return array{0: string, 1: string} home controller and its display name
     */
    private static function postedHomeController(): array
    {
        $posted = $_POST['home_controller'] ?? '';
        $parts = explode('}-{', is_string($posted) ? $posted : '', 2);

        return [trim($parts[0]), trim($parts[1] ?? '')];
    }

    private static function userSettings(string $current = ''): string
    {
        $flag = ($_POST['show_hide'] ?? '') === 'Yes' ? '{SHOW-HIDE=TRUE}' : '{SHOW-HIDE=FALSE}';
        if ($current === '') {
            return $flag;
        }
        $replaced = str_replace(['{SHOW-HIDE=TRUE}', '{SHOW-HIDE=FALSE}'], $flag, $current, $count);

        return $count > 0 ? $replaced : $current . $flag;
    }

    /**
     * Adds a person after checking the username, password, and email.
     */
    public static function addUser()
    {
        $userName = trim((string) ($_POST['user_name'] ?? ''));
        $password = (string) ($_POST['password_ftype'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $email = trim((string) ($_POST['email'] ?? ''));
        $back = Blocks::link(SEC_BASE_URL . '&option=new_user', TXT_161, 'URL');

        if (preg_match('/^[A-Za-z0-9._-]{2,40}$/', $userName) !== 1) {
            View::respond('Enter a username of 2 to 40 characters: letters, numbers, dots, underscores, or hyphens.', $back);
            return;
        }
        try {
            DefaultAccount::assertPassword($password);
        } catch (\RuntimeException $e) {
            View::respond($e->getMessage(), $back);
            return;
        }
        if (!hash_equals($password, $confirm)) {
            View::respond('The two passwords do not match.', $back);
            return;
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            View::respond('The email address does not look valid.', $back);
            return;
        }
        if (Database::exists('users', 'LOWER(user_name) = LOWER(?)', [$userName])) {
            View::respond($userName . ' ' . TXT_163, Blocks::link(SEC_BASE_URL . '&option=new_user', TXT_161, 'URL'));
            return;
        }

        [$homeController, $homeControllerName] = self::postedHomeController();
        $insertData = self::postedUserColumns(true);
        $insertData['user_name'] = $userName;
        $insertData['password'] = password_hash($password, PASSWORD_DEFAULT);
        $insertData['home_controller'] = $homeController;
        $insertData['home_controller_name'] = $homeControllerName;
        $insertData['settings'] = self::userSettings();
        if (!isset($insertData['role'])) {
            $insertData['role'] = 5;
        }
        $userId = Database::insert('users', $insertData);
        self::showGroupMembership($userId);
    }

    /**
     * Updates a person. Without the security permission the person can change only their own profile.
     *
     * @param mixed $userID The posted user id, ignored unless the signed-in person manages security.
     */
    public static function updateUser($userID)
    {
        $admin = Access::can(Permission::ADMIN_SECURITY);
        $userID = $admin ? (int) $userID : (int) ($_SESSION['access_user_id'] ?? 0);
        $current = Database::first('users', ['user_id', 'user_name', 'settings', 'role'], 'user_id = ?', [$userID]);
        $list = Blocks::link(
            SEC_BASE_URL . ($admin ? '&option=manage_users' : '&option=modify_user'),
            $admin ? TXT_160 : 'Return to your profile',
            'URL'
        );
        if ($current === null) {
            View::respond('That person was not found.', $list);
            return;
        }

        $columns = self::postedUserColumns($admin);
        if ((int) ($current['role'] ?? 5) === 0 && !Access::can(Permission::ADMIN_SYSTEM)) {
            unset($columns['role'], $columns['lastactive'], $columns['user_name']);
        }

        if (isset($columns['user_name'])) {
            if (preg_match('/^[A-Za-z0-9._-]{2,40}$/', $columns['user_name']) !== 1) {
                View::respond('Enter a username of 2 to 40 characters: letters, numbers, dots, underscores, or hyphens.', $list);
                return;
            }
            if (Database::exists('users', 'LOWER(user_name) = LOWER(?) AND user_id <> ?', [$columns['user_name'], $userID])) {
                View::respond($columns['user_name'] . ' ' . TXT_163, $list);
                return;
            }
        }
        if (($columns['email'] ?? '') !== '' && filter_var($columns['email'], FILTER_VALIDATE_EMAIL) === false) {
            View::respond('The email address does not look valid.', $list);
            return;
        }
        if ($userID === (int) ($_SESSION['access_user_id'] ?? 0) && ($columns['lastactive'] ?? '') === 'inactive') {
            View::respond('You cannot make your own account inactive.', $list);
            return;
        }
        $password = (string) ($_POST['password_ftype'] ?? '');
        if ($password !== '') {
            try {
                DefaultAccount::assertPassword($password);
            } catch (\RuntimeException $e) {
                View::respond($e->getMessage(), $list);
                return;
            }
            $columns['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if (isset($_POST['home_controller'])) {
            [$columns['home_controller'], $columns['home_controller_name']] = self::postedHomeController();
        }
        if (isset($_POST['show_hide'])) {
            $columns['settings'] = self::userSettings((string) ($current['settings'] ?? ''));
        }

        if ($columns !== []) {
            Database::update('users', $columns, 'user_id = ?', [$userID]);
        }

        View::respond(
            (string) ($columns['user_name'] ?? $current['user_name'] ?? '') . ' ' . TXT_164,
            $list
        );
    }

    /**
     * @return array{group_name: string, description: string, role: int}
     */
    private static function postedGroupColumns(): array
    {
        return [
            'group_name' => trim((string) ($_POST['group_name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'role' => max(1, min(5, (int) ($_POST['role'] ?? 5))),
        ];
    }

    /**
     * @return list<string>
     */
    private static function postedPermissions(): array
    {
        $posted = $_POST['permissions'] ?? [];

        return is_array($posted) ? array_values(array_filter($posted, 'is_string')) : [];
    }

    /**
     * Add group to database
     */
    public static function addGroup()
    {
        $columns = self::postedGroupColumns();
        if ($columns['group_name'] === '') {
            View::respond('Enter a group name.', Blocks::link(SEC_BASE_URL . '&option=new_group', TXT_161, 'URL'));
            return;
        }
        if (Database::exists('groups', 'LOWER(group_name) = LOWER(?)', [$columns['group_name']])) {
            View::respond($columns['group_name'] . ' ' . TXT_198, Blocks::link(SEC_BASE_URL . '&option=new_group', TXT_161, 'URL'));
            return;
        }
        $groupId = Database::insert('groups', $columns);
        Access::setGroupPermissions($groupId, self::postedPermissions());
        View::respond($columns['group_name'] . ' ' . TXT_162, Blocks::link(SEC_BASE_URL . '&option=manage_groups', TXT_160, 'URL'));
    }

    /**
     * Updates group information.
     *
     * @param mixed $groupID Groups ID
     */
    public static function updateGroup($groupID)
    {
        $groupID = (int) $groupID;
        $list = Blocks::link(SEC_BASE_URL . '&option=manage_groups', TXT_160, 'URL');
        if (!Database::exists('groups', 'group_id = ?', [$groupID])) {
            View::respond('That group was not found.', $list);
            return;
        }
        $columns = self::postedGroupColumns();
        if ($columns['group_name'] === '') {
            View::respond('Enter a group name.', $list);
            return;
        }
        if (Database::exists('groups', 'LOWER(group_name) = LOWER(?) AND group_id <> ?', [$columns['group_name'], $groupID])) {
            View::respond($columns['group_name'] . ' ' . TXT_198, $list);
            return;
        }
        Database::update('groups', $columns, 'group_id = ?', [$groupID]);
        Access::setGroupPermissions($groupID, self::postedPermissions());
        View::respond($columns['group_name'] . ' ' . TXT_164, $list);
    }

    /**
     * Updates group membership for user.
     *
     * @param mixed $userID User ID
     */
    /**
     * Updates group membership for a user.
     *
     * This function updates the group memberships for a given user by:
     * - Removing any existing group memberships for the user.
     * - Adding the new group memberships provided in the `$_POST` data.
     * - Rendering a confirmation message to indicate the update was successful.
     *
     * @param mixed $userID The ID of the user whose group memberships are being updated.
     *
     * @return void
     */
    public static function updateGroupMembership($userID)
    {
        $userID = (int) $userID;
        if (!Database::exists('users', 'user_id = ?', [$userID])) {
            View::respond('That person was not found.', Blocks::link(SEC_BASE_URL . '&option=manage_users', TXT_160, 'URL'));
            return;
        }

        \Adlexone\Data\GroupMembership::setUserGroups(
            (int) $userID,
            \Adlexone\Data\GroupMembership::idsFromPost($_POST)
        );

        View::respond(TXT_241, Blocks::link(SEC_BASE_URL . '&option=manage_users', TXT_160, 'URL'));
    }

    /**
     * showUserGroupResults()
     *
     * This function handles the logic for displaying search results for users or groups
     * based on the search criteria provided in the POST request. It dynamically builds
     * the SQL query to fetch data from the `users` or `groups` table and renders the results
     * in a vertical content block.
     *
     * Controller logic: user_group_search
     *
     * @return void
     */
    public static function showUserGroupResults()
    {
        $type = (string) ($_POST['type'] ?? '');
        $table = $type === 'group_name' ? 'groups' : 'users';
        $allowed = $table === 'groups' ? ['group_name', 'description'] : ['user_name', 'first_name', 'last_name', 'email'];
        if (!in_array($type, $allowed, true)) {
            $type = $allowed[0];
        }
        $criteria = (string) ($_POST['criteria'] ?? '');
        if (($_POST['operator'] ?? '') === '=') {
            $result = Database::select($table, '*', $type . ' = ?', [$criteria]);
        } else {
            $result = Database::select($table, '*', $type . " LIKE ? ESCAPE '\\'", ['%' . Database::escapeLike($criteria) . '%']);
        }

        $rows = [];
        $isUsers = $table === 'users';
        if ($result && count($result) > 0) {
            foreach ($result as $row) {
                $rows[] = $isUsers ? self::userRecord($row) : self::groupRecord($row);
            }
        }

        $html = Blocks::vertical([[
            'title' => TXT_113,
            'html' => $isUsers ? self::userRecordList($rows) : self::groupRecordList($rows),
        ]]);

        Page::current()->setBody($html);

        // Include the main page content file to display the results
    }

    /**
     * deleteGroup()
     *
     * Deletes the selected group.  Controller logic: delete_group, delete_group_checked
     *
     * @param mixed $groupID Groups ID
     * @param boolean $checked If true the user is deleted
     */
    public static function deleteGroup($groupID = '')
    {
        $groupID = (int) $groupID;
        $back = Blocks::link(SEC_BASE_URL . '&option=manage_groups', TXT_160, 'URL');
        $group = Database::first('groups', ['group_name'], 'group_id = ?', [$groupID]);
        if ($group === null) {
            View::respond('That group was not found.', $back);
            return;
        }
        if (self::systemAdminsRemaining(0, $groupID) === 0) {
            View::respond('This group holds the only system administrator access. Give that access to another group or person before deleting it.', $back);
            return;
        }
        Database::delete('groups', 'group_id = ?', [$groupID]);
        Database::delete('user_groups', 'group_id = ?', [$groupID]);
        Database::delete('item_groups', 'group_id = ?', [$groupID]);
        Database::delete('item_type_groups', 'group_id = ?', [$groupID]);
        Access::setGroupPermissions($groupID, []);
        View::respond('Group deleted.', $back);
    }

    /**
     * How many people would still hold system administrator access after one
     * person or one group is removed.
     */
    private static function systemAdminsRemaining(int $exceptUser, int $exceptGroup): int
    {
        $rows = Database::rows(
            'SELECT COUNT(*) AS n FROM users WHERE user_id <> ? AND (role = 0 OR user_id IN ('
            . 'SELECT ug.user_id FROM user_groups ug INNER JOIN group_permissions gp ON gp.group_id = ug.group_id '
            . 'WHERE gp.permission = ? AND ug.group_id <> ?))',
            [$exceptUser, Permission::ADMIN_SYSTEM, $exceptGroup]
        );
        return (int) ($rows[0]['n'] ?? 0);
    }

    /**
     * deleteUser()
     *
     * Deletes the selected user. Controller logic: delete_user
     *
     * @param mixed $userID Users ID
     */
    public static function deleteUser($userID = '')
    {
        $userID = (int) $userID;
        if ($userID === (int) ($_SESSION['access_user_id'] ?? 0)) {
            View::respond('You cannot delete the account you are signed in with.', Blocks::link(SEC_BASE_URL . '&option=manage_users', TXT_160, 'URL'));
            return;
        }
        $back = Blocks::link(SEC_BASE_URL . '&option=manage_users', TXT_160, 'URL');
        if (!Database::exists('users', 'user_id = ?', [$userID])) {
            View::respond('That person was not found.', $back);
            return;
        }
        if (self::systemAdminsRemaining($userID, 0) === 0) {
            View::respond('This person is the only system administrator. Give that access to someone else before deleting the account.', $back);
            return;
        }
        Database::delete('users', 'user_id = ?', [$userID]);
        Database::delete('user_identities', 'user_id = ?', [$userID]);
        \Adlexone\Data\GroupMembership::setUserGroups($userID, []);
        View::respond('Person deleted.', $back);
    }

    /**
     * Gets a the full list of security groups and sets the membship options for the user
     *
     * @param integer $userID Users ID
     */
    public static function showGroupMembership($userID = '')
    {
        $userID = (int) $userID;
        $user = Database::first('users', ['user_name'], 'user_id = ?', [$userID]);
        if ($user === null) {
            View::respond('User not found.', Blocks::link(SEC_BASE_URL . '&option=manage_users', TXT_160, 'URL'));
            return;
        }
        $userName = (string) $user['user_name'];

        $groupArray = array_map('strval', \Adlexone\Data\GroupMembership::userGroupIds($userID));

            $result = Database::select('groups', ['group_id', 'group_name', 'description'], 'ORDER BY group_name ASC');

        if (count($result) === 0) {
            Page::current()->setBody(Blocks::vertical([[
                'title' => TXT_239 . ': ' . $userName,
                'html' => '<p class="form-help">' . Html::e(TXT_342) . '</p>'
                    . '<p><a class="btn btn--quiet" href="' . Html::e(SEC_BASE_URL . '&option=admin_modify_user&user_id=' . $userID) . '">' . Html::e(TXT_160) . '</a></p>',
            ]]));
            return;
        }

        $groupMembershipHtml = '<div class="group-security-list">';
        foreach ($result as $row) {
            $groupId = (string) $row['group_id'];
            $checked = in_array($groupId, $groupArray, true) ? $groupId : '';
            $description = trim((string) ($row['description'] ?? ''));
            $groupMembershipHtml .= '<div class="group-security-item">'
                . Form::checkbox(
                    'group_' . $groupId,
                    $groupId,
                    $checked,
                    (string) $row['group_name']
                );
            if ($description !== '') {
                $groupMembershipHtml .= '<div class="record-list__meta">'
                    . Html::e($description)
                    . '</div>';
            }
            $groupMembershipHtml .= '</div>';
        }
        $groupMembershipHtml .= '</div>';

        Page::current()->setBody(Form::form(
            TXT_239 . ': ' . $userName,
            SEC_BASE_URL . '&option=update_group_membership&user_id=' . $userID,
            [
                TXT_71 => $groupMembershipHtml,
            ],
            [
                Form::button('submit', 'submit_button', TXT_74),
                Form::button('reset', 'reset', TXT_75),
                '<a class="btn btn--quiet" href="' . Html::e(SEC_BASE_URL . '&option=admin_modify_user&user_id=' . $userID) . '">'
                    . Html::e(TXT_160) . '</a>',
            ]
        ));
    }
}
