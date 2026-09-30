<?php
declare(strict_types=1);
/**
 * Adlexone FlowIQ License Agreement 1.0
 *
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where
 *  Adlexone FlowIQ files contain the Adlexone FlowIQ license in the header of the file, the
 *  Adlexone FlowIQ License header must remain.
 * 3. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the data created or managed by your Adlexone FlowIQ installation.
 * 4. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for your Adlexone FlowIQ configuration or the hosting environment
 *  your Adlexone FlowIQ installation operates in.
 * 5. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the Adlexone FlowIQ where the software has been modified by
 *  third parties (i.e. other than Adlexone), unless an agreement has been
 *  reached with Adlexone.
 * 6. By using the Adlexone FlowIQ, you are indicating your acceptance of the
 *  stated Adlexone FlowIQ License terms and conditions.
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */

use Adlexone\Application\ApplicationStore;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\support\RenderViews;

/**
 * Controller specific constants
 */
define('SEC_BASE_URL', 'index.php?manage=security');

if (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SECURITY)) {
    $securityOption = (string)($_GET['option'] ?? '');
    $userSectionOptions = ['', 'manage_users_groups', 'new_user', 'admin_modify_user', 'add_user', 'update_user', 'delete_user', 'modify_group_membership', 'update_group_membership'];
    $groupSectionOptions = ['new_group', 'modify_group', 'add_group', 'update_group', 'delete_group'];
    if (in_array($securityOption, $userSectionOptions, true)) {
        define('SECTION_NAV_OPTION', 'manage_users');
    } elseif (in_array($securityOption, $groupSectionOptions, true)) {
        define('SECTION_NAV_OPTION', 'manage_groups');
    }
}
RenderNavigation::applySectionNav('Security', RenderNavigation::securityManagementURLs());

/**
 * Shows secured security options
 */
function showSecurityOptions(): void
{
    $html = RenderViews::outputIfAllowed(RenderViews::buildURL(SEC_BASE_URL . '&option=manage_users_groups', TXT_73, 'URL'), \Adlexone\Auth\Permission::ADMIN_SECURITY);
    $html .= RenderViews::outputIfAllowed('<br>' . RenderViews::buildURL(SEC_BASE_URL . '&option=new_user', TXT_33, 'URL'), \Adlexone\Auth\Permission::ADMIN_SECURITY);
    $html .= RenderViews::outputIfAllowed('<br>' . RenderViews::buildURL(SEC_BASE_URL . '&option=new_group', TXT_34, 'URL'), \Adlexone\Auth\Permission::ADMIN_SECURITY);
    define('BODY_CONTENT', RenderViews::buildVerticalCards([['title' => TXT_28, 'html' => $html]]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Shows add and edit user pages. Controller logic: new_user, modify_user, admin_modify_user
 *
 * @param string $userID Users ID
 * @param array $values Field values passed in via $_SESSION array for retaining form field values if error occurred during entry
 * @param bool $adminEdit If TRUE the user is being edited by an administrator, if FALSE the user is updating their profile
 */
function showUser($userID = '', $values = [], $adminEdit = true)
{

        $fieldValues = $userID === '' ? $values : (Database::first('users', '*', 'user_id = ?', [$userID]) ?? []);

    $option = $userID === '' ? 'add_user' : 'update_user';

    $fields = [];
    if ($adminEdit) {
        $fields[TXT_149] = RenderViews::buildTextInput('user_name', @$fieldValues['user_name']);
    } else {
        $fields[TXT_149] = RenderViews::buildHiddenInput('user_name', @$fieldValues['user_name']) . @$fieldValues['user_name'];
    }
    $message = $userID !== '' ? TXT_159 : '';
    $fields[TXT_165] = RenderViews::buildPasswordInput('password_ftype', '') . ' ' . $message;
    if ($userID === '') {
        $fields[TXT_166] = RenderViews::buildPasswordInput('password_confirm', '') . ' ' . $message;
    }
    $fields[TXT_167] = RenderViews::buildTextInput('first_name', @$fieldValues['first_name']);
    $fields[TXT_168] = RenderViews::buildTextInput('last_name', @$fieldValues['last_name']);
    $fields[TXT_169] = RenderViews::buildTextInput('email', @$fieldValues['email']);

    if (!\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SYSTEM)) {
        $roleIDs = [1, 2, 3, 4, 5];
        $roleNames = [TXT_191, TXT_192, TXT_193, TXT_194, TXT_303];
    } else {
        $roleIDs = [0, 1, 2, 3, 4, 5];
        $roleNames = [TXT_190, TXT_191, TXT_192, TXT_193, TXT_194, TXT_303];
    }

    $fields[TXT_187] = RenderViews::outputIfAllowed(
        RenderViews::buildSelectDropdown('role', $roleIDs, $roleNames, @$fieldValues['role']),
        \Adlexone\Auth\Permission::ADMIN_SECURITY
    );
    $fields[TXT_653] = RenderViews::outputIfAllowed(
        RenderViews::buildSelectDropdown('lastactive', ['active', 'inactive'], [TXT_93, TXT_94], @$fieldValues['lastactive']),
        \Adlexone\Auth\Permission::ADMIN_SECURITY
    );
    if (empty($fields[TXT_187])) {
        unset($fields[TXT_187]);
    }
    if (empty($fields[TXT_653])) {
        unset($fields[TXT_653]);
    }

    $fields[TXT_171] = RenderViews::buildTextInput('phone', @$fieldValues['phone']);
    $fields[TXT_172] = RenderViews::buildTextArea('address', @$fieldValues['address'], SET_FORM_FIELD_HEIGHT);
    $fields[TXT_173] = RenderViews::buildTextInput('city', @$fieldValues['city']);
    $fields[TXT_174] = RenderViews::buildTextInput('state_province', @$fieldValues['state_province']);
    $fields[TXT_175] = RenderViews::buildTextInput('zip_postal', @$fieldValues['zip_postal']);
    $fields[TXT_176] = RenderViews::buildTextInput('country', @$fieldValues['country']);
    $fields[TXT_177] = RenderViews::buildTextInput('website', @$fieldValues['website']);
    $fields[TXT_178] = RenderViews::buildTextArea('other', @$fieldValues['other'], SET_FORM_FIELD_HEIGHT);
    $fields[TXT_179] = RenderViews::buildTextInput('secret_question', @$fieldValues['secret_question']);
    $fields[TXT_180] = RenderViews::buildTextInput('secret_answer', @$fieldValues['secret_answer']);

    $userPreferences = [];
    if ($adminEdit) {
        $themes = \Adlexone\Theme\Theme::installed();
        $theme = empty($fieldValues['theme']) ? SET_DEFAULT_THEME : $fieldValues['theme'];
        $userPreferences[TXT_182] = RenderViews::buildSelectDropdown('theme', array_keys($themes), array_values($themes), $theme);
    }

    $languageFileArray = [];
    foreach (scandir(SET_INSTALL_PATH . 'translations/') as $languageFile) {
        $filePath = SET_INSTALL_PATH . 'translations/' . $languageFile;
        if (is_file($filePath) && preg_match('/\.lang\.php$/', $languageFile)) {
            $languageFileArray[] = str_replace('.lang.php', '', $languageFile);
        }
    }
    $language = empty($fieldValues['language']) ? SET_DEFAULT_LANGUAGE : $fieldValues['language'];
    $userPreferences[TXT_181] = RenderViews::buildSelectDropdown('language', $languageFileArray, $languageFileArray, $language);

    $directoryPath = 'app/Http/Controllers/Applications/';
    $applicationFileArray = [];
    foreach (scandir($directoryPath) as $entry) {
        $filePath = $directoryPath . $entry . '/' . $entry . '.xml';
        if (is_file($filePath)) {
            $applicationFileArray[] = $filePath;
        }
    }
    $nameArray = [];
    $baseURLArray = [];
    if (!empty($applicationFileArray)) {
        foreach ($applicationFileArray as $filename) {
            $xmlContent = file_get_contents($filename);
            if ($xmlContent === false) {
                die("Cannot open " . $filename);
            }
            $xmlparser = xml_parser_create('UTF-8');
            xml_parser_set_option($xmlparser, XML_OPTION_SKIP_WHITE, 1);
            if (!xml_parse_into_struct($xmlparser, $xmlContent, $values)) {
                die("Cannot parse XML in " . $filename);
            }
            xml_parser_free($xmlparser);
            $name = '';
            foreach ($values as $value) {
                switch ($value['tag']) {
                    case 'NAME':
                        $nameArray[] = defined($value['value']) ? constant($value['value']) : $value['value'];
                        $name = $value['value'];
                        break;
                    case 'BASE_URL':
                        $baseURLArray[] = $value['value'] . '}-{' . $name;
                        break;
                }
            }
        }
    }
    array_unshift($nameArray, 'Home');
    array_unshift($baseURLArray, 'home}-{Home');
    ApplicationStore::mergeHomeChoices($baseURLArray, $nameArray);
    $application = empty($fieldValues['home_controller']) ? SET_DEFAULT_APPLICATION : $fieldValues['home_controller'] . '}-{' . @$fieldValues['home_controller_name'];
    $userPreferences[TXT_297] = RenderViews::buildSelectDropdown('home_controller', $baseURLArray, $nameArray, $application);
    $userPreferences[TXT_63] = RenderViews::buildSelectDropdown('show_header', ['Yes', 'No'], [TXT_93, TXT_94], @$fieldValues['show_header']);
    $userPreferences[TXT_64] = RenderViews::buildSelectDropdown('show_graphics', ['Yes', 'No'], [TXT_93, TXT_94], @$fieldValues['show_graphics']);
    $userPreferences[TXT_546] = RenderViews::buildSelectDropdown('show_hide', ['Yes', 'No'], [TXT_563, TXT_564], @$fieldValues['show_hide']);

    foreach ($userPreferences as $label => $element) {
        $fields[$label] = $element;
    }
    $fields[''] = RenderViews::buildHiddenInput('user_id', @$fieldValues['user_id']);

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
    $heading = $userID !== '' ? ($adminEdit ? TXT_154 : TXT_148) : TXT_33;

    define('BODY_CONTENT', RenderViews::buildForm(
        $heading,
        SEC_BASE_URL . '&option=' . $option,
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', $submitText, $javascript),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Shows add and edit group pages.  Controller logic: new_group, modify_group
 *
 * @param string $groupID Groups ID
 * @param string $values Field values passes in via $_SESSION array for retaining form field values if error occurred during entry
 */
function showGroup($groupID = '', $values = '')
{
    $isNew = ($groupID == '');
    if ($isNew) {
        $fieldValues = is_array($values) ? $values : [];
        $action = SEC_BASE_URL . '&option=add_group';
        $selectedPermissions = [];
    } else {
        $columnArray = array('group_id', 'group_name', 'description', 'role');
        $condition = "WHERE group_id = '$groupID'";
        $fieldValues = Database::first('groups', $columnArray, $condition);
        $action = SEC_BASE_URL . '&option=update_group';
        $selectedPermissions = \Adlexone\Auth\Access::permissionsForGroup((int) $groupID);
    }
    $roleIDs = array(1, 2, 3, 4, 5);
    $roleNames = array(TXT_191, TXT_192, TXT_193, TXT_194, TXT_303);
    $fields = [
        TXT_150 => RenderViews::buildTextInput('group_name', $fieldValues['group_name'] ?? ''),
        TXT_186 => RenderViews::buildTextArea('description', $fieldValues['description'] ?? '', SET_FORM_FIELD_HEIGHT),
        TXT_187 => '<div class="field-stack">'
            . RenderViews::buildSelectDropdown('role', $roleIDs, $roleNames, $fieldValues['role'] ?? '')
            . '<p class="form-help">' . htmlspecialchars(TXT_697, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>',
        TXT_696 => buildGroupPermissionsField($selectedPermissions),
    ];
    $fields[] = RenderViews::buildHiddenInput('group_id', $fieldValues['group_id'] ?? '');
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "',[''],['group_name'],[''],['" . TXT_197 . "'],[true]);\"";
    define('BODY_CONTENT', RenderViews::buildForm(
        $isNew ? TXT_184 : TXT_185,
        $action,
        $fields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_74, $javascript),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
            '<a class="btn btn--quiet" href="' . htmlspecialchars(SEC_BASE_URL . '&option=manage_groups', ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars(TXT_160, ENT_QUOTES, 'UTF-8') . '</a>',
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * @param list<string> $selected
 */
function buildGroupPermissionsField(array $selected): string
{
    $html = '<div class="group-security-list">';
    foreach (\Adlexone\Auth\Permission::catalog() as $key => $meta) {
        $checked = in_array($key, $selected, true) ? $key : '';
        $html .= '<div class="group-security-item">'
            . RenderViews::buildCheckBox(
                'permissions[]',
                $key,
                $checked,
                'checkbox',
                (string) $meta['label']
            )
            . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Users, listed the same way as Manage Fields.
 */
function showUsers(): void
{
    $rows = [];
    foreach (Database::select('users', '*', 'ORDER BY last_name ASC, first_name ASC, user_name ASC') as $row) {
        $rows[] = userRecord($row);
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        ['title' => TXT_40, 'html' => userRecordList($rows)],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Security groups, listed the same way as Manage Fields.
 */
function showGroups(): void
{
    $rows = [];
    foreach (Database::select('groups', '*', 'ORDER BY group_name ASC') as $row) {
        $rows[] = groupRecord($row);
    }

    define('BODY_CONTENT', RenderViews::buildVerticalCards([
        ['title' => TXT_35, 'html' => groupRecordList($rows)],
    ]));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function userRecordList(array $rows): string
{
    return RenderViews::buildRecordList([
        'column' => TXT_151,
        'columns' => [
            ['key' => 'username', 'label' => TXT_38],
            ['key' => 'email', 'label' => TXT_514],
            ['key' => 'role', 'label' => TXT_187],
            ['key' => 'active', 'label' => TXT_653],
        ],
        'searchLabel' => TXT_3,
        'primary' => ['href' => SEC_BASE_URL . '&option=new_user', 'label' => TXT_692],
        'empty' => TXT_115,
        'noMatch' => TXT_689,
        'groups' => [['rows' => $rows]],
    ]);
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function groupRecordList(array $rows): string
{
    return RenderViews::buildRecordList([
        'column' => TXT_151,
        'columns' => [
            ['key' => 'description', 'label' => TXT_153, 'wrap' => true],
            ['key' => 'role', 'label' => TXT_187],
        ],
        'searchLabel' => TXT_3,
        'primary' => ['href' => SEC_BASE_URL . '&option=new_group', 'label' => TXT_692],
        'empty' => TXT_115,
        'noMatch' => TXT_689,
        'groups' => [['rows' => $rows]],
    ]);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function userRecord(array $row): array
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
        'href' => SEC_BASE_URL . '&option=admin_modify_user&user_id=' . $id,
        'cells' => [
            'username' => $userName,
            'email' => (string)($row['email'] ?? ''),
            'role' => securityRoleLabel($row['role'] ?? ''),
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
function groupRecord(array $row): array
{
    $name = (string)($row['group_name'] ?? '');
    $id = rawurlencode((string)$row['group_id']);
    $description = trim((string)preg_replace('/\s+/', ' ', (string)($row['description'] ?? '')));
    return [
        'name' => $name,
        'href' => SEC_BASE_URL . '&option=modify_group&group_id=' . $id,
        'cells' => [
            'description' => $description,
            'role' => securityRoleLabel($row['role'] ?? ''),
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

function securityRoleLabel(mixed $role): string
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
 * Adds user to database
 */
/**
             * Adds a new user to the database after checking for duplicates.
             *
             * This function checks if a user with the given username already exists in the database.
             * If no duplicate is found, it creates a new user with the provided data, including
             * hashed password, home controller details, and settings. The user data is then inserted
             * into the database. If a duplicate is found, an error message is displayed.
             *
             * @return void
             */
            function addUser()
            {
                // Check for duplicate user name
                $userName = $_POST['user_name'] ?? '';
                $sql = "SELECT user_name FROM users WHERE user_name = '$userName'";
                                $result = Database::rows($sql);

                if (count($result) == 0) {
                    // Prepare user data
                    $password = md5($_POST['password_ftype'] ?? ''); // Consider password_hash for better security

                    // Extract home controller details
                    $controllerArray = explode('}-{', $_POST['home_controller'] ?? '');
                    $homeController = $controllerArray[0] ?? '';
                    $homeControllerName = $controllerArray[1] ?? '';

                    // Determine settings based on 'show_hide' input
                    $settings = ($_POST['show_hide'] ?? '') === "Yes" ? "{SHOW-HIDE=TRUE}" : "{SHOW-HIDE=FALSE}";

                    // Build insert array with user data
                    $insertData = [
                        'user_name' => $userName,
                        'password' => $password,
                        'home_controller' => $homeController,
                        'home_controller_name' => $homeControllerName,
                        'settings' => $settings,
                    ] + $_POST;

                    // Remove unnecessary fields from the insert data
                    unset($insertData['password_confirm'], $insertData['password_ftype'], $insertData['submit_button'], $insertData['reset'], $insertData['user_id'], $insertData['home_controller'], $insertData['show_hide']);

                    // Insert user data into the database
                    $userId = Database::insert('users', $insertData);

                    // Show group membership for the newly added user
                    showGroupMembership($userId);
                } else {
                    // Render duplicate user message
                    RenderViews::buildResponse(
                        $userName . ' ' . TXT_163,
                        RenderViews::buildURL(SEC_BASE_URL . '&option=new_user', TXT_161, 'URL')
                    );
                    return;
                }
            }

/**
         * Updates user information in the database.
         *
         * This function updates the details of a user identified by their user ID. It handles
         * the extraction of home controller details, prevents unauthorized role modifications,
         * updates user settings, and processes the provided form data to update the database.
         * After the update, it renders a success message.
         *
         * @param string $userID The ID of the user to be updated.
         */
        function updateUser($userID)
        {
            // Extract home controller details from the POST data
            [$homeController, $homeControllerName] = explode('}-{', $_POST['home_controller']);
            $controllerFileArray = ['home_controller' => $homeController];
            $controllerNameArray = ['home_controller_name' => $homeControllerName];

            // Define the condition for the database update query
            $condition = "WHERE user_id = '$userID'";

            // Prevent role modification for users with insufficient access rights
            if (!\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SECURITY)) {
                unset($_POST['role']);
            }

            // Fetch the current settings for the user from the database
            $row = Database::first('users', ['settings'], 'user_id = ?', [$userID]);
            $currentSettings = (string) ($row['settings'] ?? '');

            // Update the 'show_hide' setting based on the POST data
            $showHide = ($_POST['show_hide'] ?? '') === "Yes" ? "{SHOW-HIDE=TRUE}" : "{SHOW-HIDE=FALSE}";
            $controllerFileArray['settings'] = str_replace(
                ["{SHOW-HIDE=TRUE}", "{SHOW-HIDE=FALSE}"],
                $showHide,
                $currentSettings
            );

            // Remove the 'show_hide' field from the POST data
            unset($_POST['show_hide']);

            // Prepare the password for update if provided
            $password = [];
            if (!empty($_POST['password_ftype'])) {
                $password['password'] = md5($_POST['password_ftype']);
            }

            // Remove unnecessary fields from the POST data
            unset($_POST['password_ftype'], $_POST['submit_button'], $_POST['reset'], $_POST['user_id'], $_POST['home_controller']);

            // Merge all data into a single array for the update
            $columnArray = array_merge($password, $_POST, $controllerFileArray, $controllerNameArray);

            // Execute the update query in the database
            Database::update('users', $columnArray, $condition);

            // Render a success message after the update
            RenderViews::buildResponse(
                ($_POST['user_name'] ?? '') . ' ' . TXT_164,
                RenderViews::buildURL(SEC_BASE_URL . '&option=manage_users', TXT_160, 'URL')
            );
            return;
        }

/**
 * Add group to database
 */
function addGroup()
{
    // Check for duplicate and error handling
    $columnArray = array('group_name');
    $condition = "WHERE group_name = '" . $_POST['group_name'] . "'";
    $result = Database::select('groups', $columnArray, $condition);
    if (count($result) == 0) {
        $permissions = $_POST['permissions'] ?? [];
        // Remove unwanted POST variables
        unset ($_POST['submit_button'], $_POST['reset'], $_POST['group_id'], $_POST['permissions']);
        // Build insert array
        $columnArray = $_POST;
        // Insert form field values into row
        $groupId = Database::insert('groups', $columnArray);
        \Adlexone\Auth\Access::setGroupPermissions((int) $groupId, is_array($permissions) ? $permissions : []);
        RenderViews::buildResponse($_POST['group_name'] . ' ' . TXT_162, RenderViews::buildURL(SEC_BASE_URL . '&option=manage_groups', TXT_160, 'URL'));
        return;
    }
    RenderViews::buildResponse($_POST['group_name'] . ' ' . TXT_198, RenderViews::buildURL(SEC_BASE_URL . '&option=new_group', TXT_161, 'URL'));
}

/**
 * Updates group information.
 *
 * @param mixed $groupID Groups ID
 */
function updateGroup($groupID)
{
    $permissions = $_POST['permissions'] ?? [];
    // Remove unwanted POST variables
    unset ($_POST['submit_button'], $_POST['reset'], $_POST['permissions']);
    // Build insert array
    $columnArray = $_POST;
    // Set condition
    $condition = "WHERE group_id = '$groupID'";
    // Updates form field values into row
    Database::update('groups', $columnArray, $condition);
    \Adlexone\Auth\Access::setGroupPermissions((int) $groupID, is_array($permissions) ? $permissions : []);
    RenderViews::buildResponse($_POST['group_name'] . ' ' . TXT_164, RenderViews::buildURL(SEC_BASE_URL . '&option=manage_groups', TXT_160, 'URL'));
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
function updateGroupMembership($userID)
{
    $userID = (int) $userID;

    \Adlexone\Data\GroupMembership::setUserGroups(
        (int) $userID,
        \Adlexone\Data\GroupMembership::idsFromPost($_POST)
    );

    RenderViews::buildResponse(TXT_241, RenderViews::buildURL(SEC_BASE_URL . '&option=manage_users', TXT_160, 'URL'));
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
function showUserGroupResults()
{
    // Determine the table to query based on the search type
    if ($_POST['type'] == 'group_name') {
        $table = 'groups'; // Query the groups table for group-related searches
    } else {
        $table = 'users'; // Query the users table for user-related searches
    }

    // Define the columns to select and the condition for the query
    $columnArray = array('*'); // Select all columns
    if ($_POST['operator'] == '=') {
        // Exact match condition
        $condition = "WHERE " . $_POST['type'] . " = '" . $_POST['criteria'] . "'";
    } else {
        // Partial match condition using LIKE
        $condition = "WHERE " . $_POST['type'] . " LIKE '%" . $_POST['criteria'] . "%'";
    }

    // Execute the SQL query
    $result = Database::select($table, $columnArray, $condition);

    $rows = [];
    $isUsers = $table === 'users';
    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $rows[] = $isUsers ? userRecord($row) : groupRecord($row);
        }
    }

    $html = RenderViews::buildVerticalCards([[
        'title' => TXT_113,
        'html' => $isUsers ? userRecordList($rows) : groupRecordList($rows),
    ]]);

    // Define the BODY_CONTENT constant with the generated HTML
    define('BODY_CONTENT', $html);

    // Include the main page content file to display the results
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}
/**
 * deleteGroup()
 *
 * Deletes the selected group.  Controller logic: delete_group, delete_group_checked
 *
 * @param mixed $groupID Groups ID
 * @param boolean $checked If true the user is deleted
 */
function deleteGroup($groupID = '')
{
    $groupID = (int) $groupID;
    Database::run("DELETE FROM groups WHERE group_id='" . $groupID . "'");
    Database::delete('user_groups', 'group_id = ?', [$groupID]);
    Database::delete('item_groups', 'group_id = ?', [$groupID]);
    Database::delete('item_type_groups', 'group_id = ?', [$groupID]);
    \Adlexone\Auth\Access::setGroupPermissions($groupID, []);
    showGroups();
}

/**
 * deleteUser()
 *
 * Deletes the selected user. Controller logic: delete_user, delete_user_checked
 *
 * @param mixed $userID Users ID
 * @param boolean $checked If true the user is deleted
 */
function deleteUser($userID = '')
{

    $sql = "DELETE FROM users WHERE user_id='" . $userID . "'";
    Database::run($sql);
    \Adlexone\Data\GroupMembership::setUserGroups((int) $userID, []);
    showUsers();
}

/**
 * Gets a the full list of security groups and sets the membship options for the user
 *
 * @param integer $userID Users ID
 */
function showGroupMembership($userID = '')
{
    $userID = (int) $userID;
    $user = Database::first('users', ['user_name'], 'user_id = ?', [$userID]);
    if ($user === null) {
        RenderViews::buildResponse('User not found.', RenderViews::buildURL(SEC_BASE_URL . '&option=manage_users', TXT_160, 'URL'));
        return;
    }
    $userName = (string) $user['user_name'];

    $groupArray = array_map('strval', \Adlexone\Data\GroupMembership::userGroupIds($userID));

        $result = Database::select('groups', ['group_id', 'group_name', 'description'], 'ORDER BY group_name ASC');

    if (count($result) === 0) {
        define('BODY_CONTENT', RenderViews::buildVerticalCards([[
            'title' => TXT_239 . ': ' . $userName,
            'html' => '<p class="form-help">' . htmlspecialchars(TXT_342, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><a class="btn btn--quiet" href="' . htmlspecialchars(SEC_BASE_URL . '&option=admin_modify_user&user_id=' . $userID, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(TXT_160, ENT_QUOTES, 'UTF-8') . '</a></p>',
        ]]));
        RenderViews::renderThemePage('main_page_content', SET_THEME);
        return;
    }

    $groupMembershipHtml = '<div class="group-security-list">';
    foreach ($result as $row) {
        $groupId = (string) $row['group_id'];
        $checked = in_array($groupId, $groupArray, true) ? $groupId : '';
        $description = trim((string) ($row['description'] ?? ''));
        $groupMembershipHtml .= '<div class="group-security-item">'
            . RenderViews::buildCheckBox(
                'group_' . $groupId,
                $groupId,
                $checked,
                'checkbox',
                (string) $row['group_name']
            );
        if ($description !== '') {
            $groupMembershipHtml .= '<div class="record-list__meta">'
                . htmlspecialchars($description, ENT_QUOTES, 'UTF-8')
                . '</div>';
        }
        $groupMembershipHtml .= '</div>';
    }
    $groupMembershipHtml .= '</div>';

    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_239 . ': ' . $userName,
        SEC_BASE_URL . '&option=update_group_membership&user_id=' . $userID,
        [
            TXT_71 => $groupMembershipHtml,
        ],
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_74),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
            '<a class="btn btn--quiet" href="' . htmlspecialchars(SEC_BASE_URL . '&option=admin_modify_user&user_id=' . $userID, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars(TXT_160, ENT_QUOTES, 'UTF-8') . '</a>',
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}
/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
    case 'manage_users' :
    case 'manage_users_groups' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        showUsers();
        break;
    case 'manage_groups' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        showGroups();
        break;
    case 'user_group_search' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        showUserGroupResults();
        break;
    case 'new_user' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        // Removes leading VBL_ from any session variables (used for form value persistence)
        showUser('', RenderViews::processVBLPrefixedKeys($_SESSION, 'remove'));
        // Unset session variables starting with VBL_
        $_SESSION = RenderViews::processVBLPrefixedKeys($_SESSION, 'unset');
        break;
    case 'admin_modify_user' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        showUser($_GET['user_id'], '', true);
        break;
    case 'modify_user' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::APP_ACCESS);
        showUser($_SESSION['access_user_id'], '', false);
        break;
    case 'new_group' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        // Removes leading VBL_ from any session variables (used for form value persistence)
        showGroup('', RenderViews::processVBLPrefixedKeys($_SESSION, 'remove'));
        // Unset session variables starting with VBL_
        $_SESSION = RenderViews::processVBLPrefixedKeys($_SESSION, 'unset');
        break;
    case 'add_user' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        addUser();
        break;
    case 'update_user' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::APP_ACCESS);
        updateUser($_POST['user_id']);
        break;
    case 'delete_user' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        deleteUser($_GET['user_id']);
        break;
    case 'add_group' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        addGroup();
        break;
    case 'modify_group' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        // Removes leading VBL_ from any session variables (used for form value persistence)
        showGroup($_GET['group_id'], RenderViews::processVBLPrefixedKeys($_SESSION, 'remove'));
        // Unset session variables starting with VBL_
        $_SESSION = RenderViews::processVBLPrefixedKeys($_SESSION, 'unset');
        break;
    case 'modify_group_membership' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        showGroupMembership($_GET['user_id']);
        break;
    case 'update_group_membership' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        updateGroupMembership($_GET['user_id']);
        break;
    case 'update_group' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        updateGroup($_POST['group_id']);
        break;
    case 'delete_group' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SECURITY);
        deleteGroup($_GET['group_id']);
        break;
    default :
        if (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SECURITY)) {
            showUsers();
        } else {
            showUser($_SESSION['access_user_id'], '', false);
        }
        break;
}
?>