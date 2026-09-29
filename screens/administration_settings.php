<?php
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
use Adlexone\support\RenderViews;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\support\SharedMethods;

/**
 * Settings links sit in the top navigation card. There is no left sidebar.
 */
RenderNavigation::applySectionNav('System Settings', RenderNavigation::systemSettingsURLs());

function showAdlexoneSettings(): void
{
    $fields[TXT_199] = RenderViews::buildSelectDropdown('SET_CUSTOM_FIELDS_IN_SEARCH', array(2, 5, 10, 15), array(2, 5, 10, 15), SET_CUSTOM_FIELDS_IN_SEARCH);
    $menuValue = (@SET_OWNER_MENU == '') ? 5 : SET_OWNER_MENU;
    $fields[TXT_613] = RenderViews::buildSelectDropdown('SET_OWNER_MENU', array(5, 4, 3, 2, 1, 0), array(TXT_303, TXT_194, TXT_193, TXT_192, TXT_191, TXT_190), $menuValue);
    $fields[TXT_515] = RenderViews::buildTextInput('SET_ITEMS_PAGE', SET_ITEMS_PAGE);
    $fields[TXT_228] = RenderViews::buildTextInput('SET_FORM_FIELD_HEIGHT', SET_FORM_FIELD_HEIGHT);
    $fields[TXT_599] = RenderViews::buildSelectDropdown('SET_SECURE_TYPE', array('yes', 'no'), array(TXT_93, TXT_94), SET_SECURE_TYPE);
    $fields[TXT_145] = RenderViews::buildSelectDropdown('SET_LOG_ENTRY', array('yes', 'no'), array(TXT_93, TXT_94), SET_LOG_ENTRY);
    $fields[TXT_390] = RenderViews::buildSelectDropdown('SET_ATTACHMENTS', array('yes', 'no'), array(TXT_93, TXT_94), SET_ATTACHMENTS);
    $fields[TXT_593] = RenderViews::buildSelectDropdown('ADD_ATTACHMENTS', array('yes', 'no'), array(TXT_93, TXT_94), ADD_ATTACHMENTS);
    $fields[TXT_391] = RenderViews::buildTextInput('SET_MAX_ATTACHMENT', SET_MAX_ATTACHMENT);
    $fields[TXT_329] = RenderViews::buildSelectDropdown('SET_DATE_FORMAT', array('d-m-Y, h:i A', 'm-d-Y, h:i A', 'd.m.Y H:i'), array(TXT_330, TXT_481, TXT_654), SET_DATE_FORMAT);
    $directories = opendir('app/Http/Controllers/Applications/');
    $applicationFileArray = [];
    $i = 0;
    while ($a = readdir($directories)) {
        $appXMLFile = $a . '.xml';
        if (is_file('app/Http/Controllers/Applications/' . $a . '/' . $appXMLFile)) {
            $applicationFileArray[$i++] = 'app/Http/Controllers/Applications/' . $a . '/' . $appXMLFile;
        }
    }
    // If any applications are found read them and create the applications array to set a home page
    if (is_array($applicationFileArray)) {
        foreach ($applicationFileArray as $filename) {
            if (!($fp = fopen($filename, "r"))) {
                die("Cannot open " . $filename);
            }
            $xml = fread($fp, filesize($filename));
            fclose($fp);
            $xmlparser = xml_parser_create('UTF-8') or die("Cannot create parser");
            xml_parser_set_option($xmlparser, XML_OPTION_SKIP_WHITE, 1);
            xml_parse_into_struct($xmlparser, $xml, $values);
            xml_parser_free($xmlparser);
            // Get the required xml values for each of the tags and build an array for the buildSelectDropdown
            foreach ($values as $key => $value) {
                switch ($value['tag']) {
                    // Name of application
                    case 'NAME' :
                        $nameArray[] = $value['value'];
                        // Use this when the base buildURL is returned (next foreach)
                        $name = $value['value'];
                        break;
                    // Base buildURL of the application (controller file GET value)
                    case 'BASE_URL' :
                        $baseURLArray[] = $value['value'] . '}-{' . $name;
                        break;
                    default :
                } // switch
            }
        }
    }
    $nameArray = $nameArray ?? [];
    $baseURLArray = $baseURLArray ?? [];
    array_unshift($nameArray, 'Home');
    array_unshift($baseURLArray, 'quick_launch}-{Home');
    ApplicationStore::mergeHomeChoices($baseURLArray, $nameArray);
    $fields[TXT_297] = RenderViews::buildSelectDropdown('SET_DEFAULT_APPLICATION', $baseURLArray, $nameArray, @SET_DEFAULT_APPLICATION, 'form-control');
    // Get all item types
    $columnArray = array('item_type_id', 'item_type_name');
    foreach (Database::select('item_types', $columnArray) as $row) {
        $valueArray[] = $row['item_type_id'];
        $displayArray[] = $row['item_type_name'];
    }
    $fields[TXT_229] = RenderViews::buildSelectDropdown('SET_DEFAULT_ITEM_TYPE', $valueArray, $displayArray, SET_DEFAULT_ITEM_TYPE);
    $columnArray = array('group_id', 'group_name');
    $result = Database::select('groups', $columnArray);
    $listValuesArray[] = "None";
    $listDisplayValuesArray[] = "None";
    foreach ($result as $row) {
        $listValuesArray[] = $row['group_id'];
        $listDisplayValuesArray[] = $row['group_name'];
    }
    $fields[TXT_604] = RenderViews::buildSelectDropdown('USER_REG_ACTION', $listValuesArray, $listDisplayValuesArray, USER_REG_ACTION);
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_56);
    $bodyContent = RenderViews::buildForm(TXT_42,'index.php?controller=administration_settings&option=update_adlexone_settings',$fields,$buttons);
    define('BODY_CONTENT', $bodyContent);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showAdvancedSettings(): void
{
    // Setup form fields with values from settings file
    $fields[TXT_355] = RenderViews::buildSelectDropdown('SET_DEBUG_MODE', array('Yes', 'No'), array(TXT_93, TXT_94), SET_DEBUG_MODE);
    $fields[TXT_351] = RenderViews::buildSelectDropdown('SET_SHOW_SQL', array('Yes', 'No'), array(TXT_93, TXT_94), SET_SHOW_SQL);
    $fields[TXT_205] = RenderViews::buildSelectDropdown('SET_ERROR_REPORTING_LEVEL', array('6135', '6143'), array(TXT_504, TXT_207), SET_ERROR_REPORTING_LEVEL);
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_56);
    $bodyContent = RenderViews::buildForm(TXT_130,'index.php?controller=administration_settings&option=update_advanced_settings',$fields,$buttons);
    define('BODY_CONTENT', $bodyContent);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showSignInSettings(): void
{
    $on = static function (string $name, string $default = 'No'): string {
        if (!defined($name)) {
            return $default;
        }
        $value = strtolower(trim((string) constant($name)));
        return in_array($value, ['1', 'true', 'yes', 'on'], true) ? 'Yes' : 'No';
    };
    $esc = static function (string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    };

    $rows = [];
    if (defined('AUTH_OAUTH_CONNECTIONS_JSON')) {
        $decoded = json_decode((string) AUTH_OAUTH_CONNECTIONS_JSON, true);
        if (is_array($decoded)) {
            $rows = $decoded;
        }
    }
    $connection = (isset($rows[0]) && is_array($rows[0])) ? $rows[0] : [];
    $autoProvision = strtolower(trim((string) ($connection['auto_provision'] ?? 'No')));
    $autoProvision = in_array($autoProvision, ['1', 'true', 'yes', 'on'], true) ? 'Yes' : 'No';

    $fields = [
        'Username and password sign-in' => RenderViews::buildSelectDropdown('AUTH_LOCAL_ENABLED', ['Yes', 'No'], [TXT_93, TXT_94], $on('AUTH_LOCAL_ENABLED', 'Yes')),
        'Identity provider sign-in' => RenderViews::buildSelectDropdown('AUTH_OAUTH_ENABLED', ['Yes', 'No'], [TXT_93, TXT_94], $on('AUTH_OAUTH_ENABLED')),
        'Go straight to the identity provider' => RenderViews::buildSelectDropdown('AUTH_OAUTH_AUTO_REDIRECT', ['Yes', 'No'], [TXT_93, TXT_94], $on('AUTH_OAUTH_AUTO_REDIRECT')),
        'Button label' => RenderViews::buildTextInput('connection_label', $esc((string) ($connection['label'] ?? 'Sign in with OAuth')))
            . RenderViews::buildHiddenInput('connection_id', $esc((string) ($connection['id'] ?? 'organisation'))),
        'Issuer URL' => RenderViews::buildTextInput('connection_issuer', $esc((string) ($connection['issuer'] ?? ''))),
        'Client ID' => RenderViews::buildTextInput('connection_client_id', $esc((string) ($connection['client_id'] ?? ''))),
        'Client secret' => RenderViews::buildPasswordInput('connection_client_secret', $esc((string) ($connection['client_secret'] ?? ''))),
        'Scopes' => RenderViews::buildTextInput('connection_scopes', $esc((string) ($connection['scopes'] ?? 'openid profile email'))),
        'Create an account on first sign-in' => RenderViews::buildSelectDropdown('connection_auto_provision', ['Yes', 'No'], [TXT_93, TXT_94], $autoProvision),
        'Redirect URI' => RenderViews::buildTextInput('redirect_uri', $esc(\Adlexone\Auth\AuthConfig::callbackUrl()), '', true),
    ];

    define('BODY_CONTENT', RenderViews::buildForm(
        'Sign-in',
        'index.php?controller=administration_settings&option=update_sign_in_settings',
        $fields,
        [RenderViews::buildFormButton('submit', 'submit_button', TXT_56)]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showEmailSettings(): void
{
    $smtp = static function (string $name): string {
        return defined($name) ? (string) constant($name) : '';
    };
    $fields = [
        TXT_645 => RenderViews::buildTextInput('SET_SMTP_HOST', $smtp('SET_SMTP_HOST')),
        TXT_646 => RenderViews::buildTextInput('SET_SMTP_PORT', $smtp('SET_SMTP_PORT')),
        TXT_649 => RenderViews::buildTextInput('SET_SMTP_USER', $smtp('SET_SMTP_USER')),
        TXT_650 => RenderViews::buildPasswordInput('SET_SMTP_PASSWORD', $smtp('SET_SMTP_PASSWORD')),
    ];
    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_565,
        'index.php?controller=administration_settings&option=update_email_settings',
        $fields,
        [RenderViews::buildFormButton('submit', 'submit_button', TXT_56)]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

switch (@$_GET['option']) {
    case 'adlexone_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SETTINGS);
        showAdlexoneSettings();
        break;
    case 'update_adlexone_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SETTINGS);
        $settings = $_POST;
        unset($settings['submit_button']);
        $settings['SET_DEFAULT_THEME'] = defined('SET_DEFAULT_THEME') ? SET_DEFAULT_THEME : 'new';
        $settings['SET_DEFAULT_LANGUAGE'] = defined('SET_DEFAULT_LANGUAGE') ? SET_DEFAULT_LANGUAGE : 'English';
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'adlexone_settings.json', $settings);
        break;
    case 'advanced_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        showAdvancedSettings();
        break;
    case 'update_advanced_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'advanced_settings.json', $_POST);
        break;
    case 'sign_in_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        showSignInSettings();
        break;
    case 'update_sign_in_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        $yes = static function (string $key, string $default = 'No'): string {
            return strcasecmp((string) ($_POST[$key] ?? $default), 'Yes') === 0 ? 'Yes' : 'No';
        };
        $id = trim((string) ($_POST['connection_id'] ?? 'organisation'));
        if ($id === '') {
            $id = 'organisation';
        }
        $issuer = rtrim(trim((string) ($_POST['connection_issuer'] ?? '')), '/');
        $clientId = trim((string) ($_POST['connection_client_id'] ?? ''));
        $clientSecret = (string) ($_POST['connection_client_secret'] ?? '');
        $connection = [
            'id' => $id,
            'label' => trim((string) ($_POST['connection_label'] ?? '')) ?: 'Sign in with OAuth',
            'issuer' => $issuer,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scopes' => trim((string) ($_POST['connection_scopes'] ?? '')) ?: 'openid profile email',
            'auto_provision' => $yes('connection_auto_provision'),
        ];
        $existing = [];
        if (defined('AUTH_OAUTH_CONNECTIONS_JSON')) {
            $decoded = json_decode((string) AUTH_OAUTH_CONNECTIONS_JSON, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        $rest = [];
        foreach ($existing as $row) {
            if (is_array($row) && trim((string) ($row['id'] ?? '')) !== $id) {
                $rest[] = $row;
            }
        }
        $connections = ($issuer !== '' || $clientId !== '' || $clientSecret !== '')
            ? array_merge([$connection], $rest)
            : $rest;
        $settings = [
            'AUTH_LOCAL_ENABLED' => $yes('AUTH_LOCAL_ENABLED', 'Yes'),
            'AUTH_LOCAL_ALLOW_REGISTRATION' => defined('AUTH_LOCAL_ALLOW_REGISTRATION') ? (string) AUTH_LOCAL_ALLOW_REGISTRATION : 'No',
            'AUTH_OAUTH_ENABLED' => $yes('AUTH_OAUTH_ENABLED'),
            'AUTH_OAUTH_AUTO_REDIRECT' => $yes('AUTH_OAUTH_AUTO_REDIRECT'),
            'AUTH_OAUTH_CONNECTIONS_JSON' => json_encode(array_values($connections), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        if (defined('AUTH_HELP')) {
            $settings = ['AUTH_HELP' => (string) AUTH_HELP] + $settings;
        }
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'auth_settings.json', $settings);
        break;
    case 'email_settings' :
    case 'inbound_email_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        showEmailSettings();
        break;
    case 'update_email_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'email_settings.json', [
            'SET_SMTP_HOST' => $_POST['SET_SMTP_HOST'] ?? '',
            'SET_SMTP_PORT' => $_POST['SET_SMTP_PORT'] ?? '',
            'SET_SMTP_USER' => $_POST['SET_SMTP_USER'] ?? '',
            'SET_SMTP_PASSWORD' => $_POST['SET_SMTP_PASSWORD'] ?? '',
        ]);
        break;
    default :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        RenderViews::buildResponse('Invalid Option', RenderViews::buildURL('index.php?controller=administration_settings&option=adlexone_settings', TXT_55, 'URL'));
        break;
}
