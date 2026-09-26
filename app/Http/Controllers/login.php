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

    use Adlexone\support\RenderViews;
    use Adlexone\support\Database;

    /**
     * Handles the login process for users.
     * Supports LDAP authentication and database authentication.
     * Redirects users to the appropriate page based on the login result.
     */
    function login(): void
    {
        if (isset($_POST['Login'])) {
            $LDAPAuthorised = false;

            // Check if LDAP authentication is enabled and handle it
            if (SET_LDAP_ENABLED === 'Yes') {
                $LDAPAuthorised = handleLDAPAuthentication();
            }

            // If LDAP authentication fails, fallback to database authentication
            if (!$LDAPAuthorised) {
                $userResult = authenticateWithDatabase($_POST['access_user_name'], $_POST['access_password']);
            }
        } elseif (isset($_POST['NewLogon'])) {
            // Handle new user registration
            $errorMessage = createAdhocUser();
            $_POST['access_user_name'] = $_POST['new_username'];

            if ($errorMessage) {
                define('LOGON_ERROR', '<strong>' . TXT_674 . ':  </strong>' . $errorMessage);
                RenderViews::renderThemePage('login', SET_THEME);
                return;
            }

            // Query the database for the newly created user
            $userResult = Database::query(
                "SELECT * FROM users WHERE user_name = '" . addslashes(html_entity_decode($_POST['access_user_name'])) . "'",
                DSN,
                SET_SHOW_SQL
            );
        }

        // Handle successful or failed login attempts
        if (isset($userResult) && Database::numRows($userResult) > 0) {
            handleSuccessfulLogin(Database::fetchArray($userResult));
        } else {
            define('LOGON_ERROR', $errorMessage ?? TXT_219);
            RenderViews::renderThemePage('login', SET_THEME);
        }
    }

    /**
     * Handles LDAP authentication for users.
     * Iterates through configured LDAP servers and attempts to authenticate the user.
     *
     * @return bool True if LDAP authentication is successful, false otherwise.
     */
    function handleLDAPAuthentication(): bool
    {
        for ($i = 1; $i <= SET_LDAP_SEARCH_COUNT; $i++) {
            $ldapConnection = ldap_connect(getLDAPProtocol($i) . constant('SET_LDAP_SERVER_NAME_' . $i));
            if (!$ldapConnection || !ldap_bind($ldapConnection, constant('SET_LDAP_USERNAME_' . $i), constant('SET_LDAP_PASSWORD_' . $i))) {
                continue;
            }

            $searchFilter = getLDAPSearchFilter($i);
            $attributesArray = getLDAPAttributes($i);
            $searchResult = ldap_search($ldapConnection, constant('SET_LDAP_BASE_DN_' . $i), $searchFilter, $attributesArray);

            if ($searchResult && ldap_bind($ldapConnection, getLDAPUsername($i), getLDAPPassword())) {
                syncUserWithDatabase($attributesArray, $i);
                return true;
            }
        }

        return false;
    }

    /**
     * Authenticates a user using the database.
     *
     * @param string $username The username provided by the user.
     * @param string $password The password provided by the user.
     * @return mixed The result of the database query.
     */
    function authenticateWithDatabase(string $username, string $password)
    {
        $sql = "SELECT * FROM users WHERE user_name = '" . addslashes(html_entity_decode($username, ENT_COMPAT, 'UTF-8')) . "' AND password = '" . md5($password) . "'";
        return Database::query($sql, DSN, SET_SHOW_SQL);
    }

    /**
     * Handles a successful login attempt.
     * Initializes the user session and redirects to the home page.
     *
     * @param array $user The user data retrieved from the database.
     */
    function handleSuccessfulLogin(array $user): void
    {
        if ($user['lastactive'] === 'inactive') {
            define('LOGON_ERROR', TXT_652);
            RenderViews::renderThemePage('login', SET_THEME);
            exit;
        }

        if (!empty($_POST['rememberme']) && REMEMBERME_ACTIVE === 'Yes') {
            setRememberMeCookie($_POST['access_user_name']);
        }

        initializeSession($user);
        redirectToHomePage();
    }

    /**
     * Initializes the user session with the provided user data.
     *
     * @param array $user The user data retrieved from the database.
     */
    function initializeSession(array $user): void
    {
        $_SESSION['access_user_id'] = $user['user_id'];
        $_SESSION['access_theme'] = $user['theme'];
        $_SESSION['access_language'] = $user['language'];
        $_SESSION['access_home_controller_name'] = $user['home_controller_name'];
        $_SESSION['access_show_header'] = $user['show_header'];
        $_SESSION['access_show_graphics'] = $user['show_graphics'];
        $_SESSION['access_home_controller'] = $user['home_controller'] ?: 'launch';
        $_SESSION['access_role_id'] = determineUserRole($user);
    }

    /**
     * Determines the user's role based on their group memberships.
     *
     * @param array $user The user data retrieved from the database.
     * @return int The user's role ID.
     */
function determineUserRole(array $user): int
{
    // Ensure base role is an int
    $lowestRoleValue = (int) ($user['role'] ?? 0);

    $sql = "SELECT groups FROM group_members WHERE user_id = '" . $user['user_id'] . "'";
    $result = Database::query($sql, DSN, SET_SHOW_SQL);

    if (Database::numRows($result) > 0) {
        $groupRow = Database::fetchArray($result);
        $groupArray = array_filter(explode('}-{', $groupRow['groups']), 'strlen');

        foreach ($groupArray as $groupId) {
            $groupId = trim($groupId);
            if ($groupId === '') {
                continue;
            }

            // Cast lookup to int to satisfy return type
            $role = (int) Database::sqlLookup('groups', 'role', "WHERE group_id = '$groupId'", DSN, SET_SHOW_SQL);
            if ($role > $lowestRoleValue) {
                $lowestRoleValue = $role;
            }
        }
    }
    return (int) $lowestRoleValue;
}

    /**
     * Redirects the user to their home page.
     */
    function redirectToHomePage(): void
    {
        $homeURL = '?controller=' . ($_SESSION['access_home_controller'] ?? 'quick_launch');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] . $homeURL));
    }

    /**
     * Sets a "remember me" cookie for the user.
     *
     * @param string $username The username of the user.
     */
    function setRememberMeCookie(string $username): void
    {
        $val = gzcompress(serialize($username) . COOKIE_CODE);
        $val = urlencode(base64_encode($val));
        setcookie('oozimsrememberme', $val, time() + 60 * 60 * 24 * COOKIE_LENGTH, '', $_SERVER['HTTP_HOST'], false);
    }

    /**
     * Creates a new user based on the provided registration data.
     *
     * @return string|null An error message if the user creation fails, or null on success.
     */
    function createAdhocUser()
    {
        if ($_POST['new_password'] != $_POST['new_confirm']) {
            $errorMessage = TXT_610;
        } elseif ($_POST['new_username'] == '') {
            $errorMessage = TXT_471;
        } else {
            if (base64_decode($_POST['CODE']) != $_POST['new_unique']) {
                $errorMessage = TXT_611;
            } else {
                if (Database::sqlLookup('users', ' WHERE user_name = "' . $_POST['new_username'] . '"', DSN, SET_SHOW_SQL)) {
                    $errorMessage = TXT_612;
                } else {
                    $userArray['user_id'] = Database::newID('users', 'user_id');
                    $userArray['password'] = md5($_POST['new_password']);
                    $userArray['first_name'] = $_POST['new_firstname'];
                    $userArray['last_name'] = $_POST['new_lastname'];
                    $userArray['user_name'] = $_POST['new_username'];
                    $userArray['email'] = $_POST['new_email'];
                    $userArray['theme'] = SET_DEFAULT_THEME;
                    $userArray['language'] = SET_DEFAULT_LANGUAGE;
                    $userArray['show_header'] = 'Yes';
                    $userArray['show_graphics'] = 'Yes';
                    $userArray['role'] = 4;
                    $defaultApplicationArray = explode('}-{', SET_DEFAULT_APPLICATION);
                    $userArray['home_controller'] = $defaultApplicationArray[0];
                    $userArray['home_controller_name'] = $defaultApplicationArray[1];
                    $sql = Database::sqlInsert('users', $userArray);
                    Database::query($sql, DSN, SET_SHOW_SQL);
                    $columnArray['groups'] = USER_REG_ACTION;
                    $columnArray['user_id'] = $userArray['user_id'];
                    $sql = Database::sqlInsert('group_members', $columnArray);
                }
            }
        }
        return $errorMessage;
    }

    /**
     * Renders the login form and registration form (if allowed).
     *
     * @return string The HTML content of the login/registration form.
     */
    function showLoginData()
    {
        if (!isset($_POST['Register'])) {
            $fields = [
                TXT_38 => RenderViews::buildTextInput('access_user_name', '', TXT_38),
                TXT_39 => RenderViews::buildPasswordInput('access_password', ''),
            ];
            $buttons = [RenderViews::buildFormButton('submit', 'Login', TXT_543)];
            if (ALLOW_USER_REG == "yes") {
                $buttons[] = RenderViews::buildFormButton('submit', 'Register', TXT_630, '', 'secondary');
            }
            return RenderViews::buildForm(TXT_543, 'index.php', $fields, $buttons);
        }

        if ((ALLOW_USER_REG == "yes") && isset($_POST['Register'])) {
            $uniqueID = time() . rand(10, 20) . rand(30, 40);
            $fields = [
                TXT_38 => RenderViews::buildTextInput('new_username', '', TXT_38),
                TXT_39 => RenderViews::buildPasswordInput('new_password', ''),
                TXT_166 => RenderViews::buildPasswordInput('new_confirm', ''),
                TXT_167 => RenderViews::buildTextInput('new_firstname', '', TXT_167),
                TXT_168 => RenderViews::buildTextInput('new_lastname', '', TXT_168),
                TXT_169 => RenderViews::buildTextInput('new_email', '', TXT_169),
                TXT_615 => RenderViews::buildTextInput('new_unique', '', TXT_615),
                TXT_606 => $uniqueID,
                '' => RenderViews::buildHiddenInput('CODE', base64_encode($uniqueID)) . RenderViews::buildHiddenInput('CREATE_USER', 'TRUE'),
            ];
            $javascript = 'onClick="javascript:return fieldCheck(\'' . TXT_468 . '\',[\'\',\'\',\'\',\'email\',\'\'],[\'new_username\',\'new_firstname\',\'new_lastname\',\'new_email\',\'new_unique\'],[\'\',\'\',\'\',\'' . TXT_475 . '\',\'\'],[\'' . TXT_471 . '\',\'' . TXT_549 . '\',\'' . TXT_550 . '\',\'' . TXT_474 . '\',\'' . TXT_609 . '\'],[true,true,true,true,true])"';
            return RenderViews::buildForm(
                TXT_673,
                'index.php',
                $fields,
                [RenderViews::buildFormButton('submit', 'NewLogon', TXT_673, $javascript)]
            );
        }

        return '';
    }

    /**
     * Displays an error message if a login error is defined.
     */
    function showError()
    {
        if (defined('LOGON_ERROR')) {
            RenderViews::buildResponse(LOGON_ERROR);
        }
    }

    /**
     * Handles the logic for loading the appropriate template or performing actions like logoff.
     */
    if ((isset($_GET['action']) and $_GET['action'] == 'logoff') and isset($_SESSION['access_user_id'])) {
        // Logoff logic: Clear session data and redirect to the base page
        unset($_SESSION['access_role_id'], $_SESSION['access_user_id'], $_SESSION['access_theme'], $_SESSION['access_language'], $_SESSION['access_home_controller_name'], $_SESSION['access_show_header'], $_SESSION['access_show_graphics'], $_SESSION['access_home_controller']);
        $locationURL = (strpos($_SERVER['HTTP_REFERER'], '?')) ? substr($_SERVER['HTTP_REFERER'], 0, strpos($_SERVER['HTTP_REFERER'], '?')) : $_SERVER['HTTP_REFERER'];
        header('Location: ' . $locationURL);
    } elseif (isset($_POST['Login']) or isset($_POST['NewLogon'])) {
        // Handle login or new user registration
        login();
    } else {
        // Load the login page template
        RenderViews::renderThemePage('login', 'new');
    }