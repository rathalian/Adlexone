<?php
namespace Adlexone\Screen;

use Adlexone\Application\ApplicationStore;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\support\SharedMethods;
use Adlexone\Http\Page;
use Adlexone\Auth\Access;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;

/**
 * Screen formerly loaded from legacy/.
 */
final class SiteSettingsScreen
{
    public static function open(): void
    {
        /**
         * Settings links sit in the top navigation card. There is no left sidebar.
         */
        RenderNavigation::applySectionNav('System Settings', RenderNavigation::systemSettingsURLs());













        switch (@$_GET['option']) {
            case 'adlexone_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SETTINGS);
                self::showAdlexoneSettings();
                break;
            case 'theme_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SETTINGS);
                self::showThemeSettings();
                break;
            case 'update_theme_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SETTINGS);
                $themes = self::installedThemes();
                $theme = trim((string) ($_POST['SET_DEFAULT_THEME'] ?? ''));
                if (!isset($themes[$theme])) {
                    View::respond(
                        'Choose an installed theme.',
                        Blocks::link('?manage=settings&option=theme_settings', 'Theme', 'URL')
                    );
                    break;
                }
                $path = SET_CONFIGURATION_PATH . 'adlexone_settings.json';
                $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
                if (!is_array($decoded)) {
                    View::respond(
                        'Theme settings could not be read.',
                        Blocks::link('?manage=settings&option=theme_settings', 'Theme', 'URL')
                    );
                    break;
                }
                $previous = defined('SET_DEFAULT_THEME') ? (string) SET_DEFAULT_THEME : (string) ($decoded['SET_DEFAULT_THEME'] ?? 'inlay-stone');
                $previous = \Adlexone\Theme\Theme::canonicalize($previous);
                $decoded['SET_DEFAULT_THEME'] = $theme;
                if ($previous !== $theme) {
                    Database::update('users', ['theme' => $theme], 'theme = ? OR theme = ? OR theme = ? OR theme IS NULL', [$previous, 'new', '']);
                }
                if (!empty($_SESSION['access_user_id'])) {
                    Database::update('users', ['theme' => $theme], 'user_id = ?', [(int) $_SESSION['access_user_id']]);
                    $_SESSION['access_theme'] = $theme;
                }
                SharedMethods::saveSettingsToJson($path, $decoded);
                break;
            case 'update_adlexone_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SETTINGS);
                $path = SET_CONFIGURATION_PATH . 'adlexone_settings.json';
                $existing = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
                if (!is_array($existing)) {
                    $existing = [];
                }
                $posted = $_POST;
                unset($posted['submit_button']);
                $settings = array_merge($existing, $posted);
                $settings['SET_DEFAULT_THEME'] = defined('SET_DEFAULT_THEME') ? SET_DEFAULT_THEME : 'inlay-stone';
                $settings['SET_DEFAULT_LANGUAGE'] = defined('SET_DEFAULT_LANGUAGE') ? SET_DEFAULT_LANGUAGE : 'English';
                $settings['SET_HOME_LEAD'] = trim((string) ($posted['SET_HOME_LEAD'] ?? $existing['SET_HOME_LEAD'] ?? 'Create the records, rules, and screens your organisation works with every day.'));
                SharedMethods::saveSettingsToJson($path, $settings);
                break;
            case 'advanced_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
                self::showAdvancedSettings();
                break;
            case 'update_advanced_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
                SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'advanced_settings.json', $_POST);
                break;
            case 'sign_in_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
                self::showSignInSettings();
                break;
            case 'update_sign_in_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
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
                Access::require(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
                self::showEmailSettings();
                break;
            case 'update_email_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
                SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'email_settings.json', [
                    'SET_SMTP_HOST' => $_POST['SET_SMTP_HOST'] ?? '',
                    'SET_SMTP_PORT' => $_POST['SET_SMTP_PORT'] ?? '',
                    'SET_SMTP_USER' => $_POST['SET_SMTP_USER'] ?? '',
                    'SET_SMTP_PASSWORD' => $_POST['SET_SMTP_PASSWORD'] ?? '',
                ]);
                break;
            default :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
                View::respond('Invalid Option', Blocks::link('?manage=settings&option=adlexone_settings', TXT_55, 'URL'));
                break;
        }
    }
    public static function showAdlexoneSettings(): void
    {
        $fields[TXT_199] = Form::select('SET_CUSTOM_FIELDS_IN_SEARCH', array(2, 5, 10, 15), array(2, 5, 10, 15), SET_CUSTOM_FIELDS_IN_SEARCH);
        $menuValue = (@SET_OWNER_MENU == '') ? 5 : SET_OWNER_MENU;
        $fields[TXT_613] = Form::select('SET_OWNER_MENU', array(5, 4, 3, 2, 1, 0), array(TXT_303, TXT_194, TXT_193, TXT_192, TXT_191, TXT_190), $menuValue);
        $fields[TXT_515] = Form::text('SET_ITEMS_PAGE', SET_ITEMS_PAGE);
        $fields[TXT_228] = Form::text('SET_FORM_FIELD_HEIGHT', SET_FORM_FIELD_HEIGHT);
        $fields[TXT_599] = Form::select('SET_SECURE_TYPE', array('yes', 'no'), array(TXT_93, TXT_94), SET_SECURE_TYPE);
        $fields[TXT_145] = Form::select('SET_LOG_ENTRY', array('yes', 'no'), array(TXT_93, TXT_94), SET_LOG_ENTRY);
        $fields[TXT_390] = Form::select('SET_ATTACHMENTS', array('yes', 'no'), array(TXT_93, TXT_94), SET_ATTACHMENTS);
        $fields[TXT_593] = Form::select('ADD_ATTACHMENTS', array('yes', 'no'), array(TXT_93, TXT_94), ADD_ATTACHMENTS);
        $fields[TXT_391] = Form::text('SET_MAX_ATTACHMENT', SET_MAX_ATTACHMENT);
        $fields[TXT_329] = Form::select('SET_DATE_FORMAT', array('d-m-Y, h:i A', 'm-d-Y, h:i A', 'd.m.Y H:i'), array(TXT_330, TXT_481, TXT_654), SET_DATE_FORMAT);
        $nameArray = ['Home'];
        $baseURLArray = ['home}-{Home'];
        ApplicationStore::mergeHomeChoices($baseURLArray, $nameArray);
        $fields[TXT_297] = Form::select('SET_DEFAULT_APPLICATION', $baseURLArray, $nameArray, @SET_DEFAULT_APPLICATION, 'form-control');
        // Get all item types
        $columnArray = array('item_type_id', 'item_type_name');
        foreach (Database::select('item_types', $columnArray) as $row) {
            $valueArray[] = $row['item_type_id'];
            $displayArray[] = $row['item_type_name'];
        }
        $fields[TXT_229] = Form::select('SET_DEFAULT_ITEM_TYPE', $valueArray, $displayArray, SET_DEFAULT_ITEM_TYPE);
        $columnArray = array('group_id', 'group_name');
        $result = Database::select('groups', $columnArray);
        $listValuesArray[] = "None";
        $listDisplayValuesArray[] = "None";
        foreach ($result as $row) {
            $listValuesArray[] = $row['group_id'];
            $listDisplayValuesArray[] = $row['group_name'];
        }
        $fields[TXT_604] = Form::select('USER_REG_ACTION', $listValuesArray, $listDisplayValuesArray, USER_REG_ACTION);
        $homeLead = defined('SET_HOME_LEAD') ? (string) SET_HOME_LEAD : 'Create the records, rules, and screens your organisation works with every day.';
        $fields['Home page blurb'] = Form::textarea('SET_HOME_LEAD', $homeLead, '3');
        $buttons[] = Form::button('submit', 'submit_button', TXT_56);
        $bodyContent = Form::form(TXT_42,'?manage=settings&option=update_adlexone_settings',$fields,$buttons);
        Page::current()->setBody($bodyContent);
    }

    public static function showAdvancedSettings(): void
    {
        // Setup form fields with values from settings file
        $fields[TXT_355] = Form::select('SET_DEBUG_MODE', array('Yes', 'No'), array(TXT_93, TXT_94), SET_DEBUG_MODE);
        $fields[TXT_351] = Form::select('SET_SHOW_SQL', array('Yes', 'No'), array(TXT_93, TXT_94), SET_SHOW_SQL);
        $fields[TXT_205] = Form::select('SET_ERROR_REPORTING_LEVEL', array('6135', '6143'), array(TXT_504, TXT_207), SET_ERROR_REPORTING_LEVEL);
        $buttons[] = Form::button('submit', 'submit_button', TXT_56);
        $bodyContent = Form::form(TXT_130,'?manage=settings&option=update_advanced_settings',$fields,$buttons);
        Page::current()->setBody($bodyContent);
    }

    public static function showSignInSettings(): void
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
            'Username and password sign-in' => Form::select('AUTH_LOCAL_ENABLED', ['Yes', 'No'], [TXT_93, TXT_94], $on('AUTH_LOCAL_ENABLED', 'Yes')),
            'Identity provider sign-in' => Form::select('AUTH_OAUTH_ENABLED', ['Yes', 'No'], [TXT_93, TXT_94], $on('AUTH_OAUTH_ENABLED')),
            'Go straight to the identity provider' => Form::select('AUTH_OAUTH_AUTO_REDIRECT', ['Yes', 'No'], [TXT_93, TXT_94], $on('AUTH_OAUTH_AUTO_REDIRECT')),
            'Button label' => Form::text('connection_label', $esc((string) ($connection['label'] ?? 'Sign in with OAuth')))
                . Form::hidden('connection_id', $esc((string) ($connection['id'] ?? 'organisation'))),
            'Issuer URL' => Form::text('connection_issuer', $esc((string) ($connection['issuer'] ?? ''))),
            'Client ID' => Form::text('connection_client_id', $esc((string) ($connection['client_id'] ?? ''))),
            'Client secret' => Form::password('connection_client_secret', $esc((string) ($connection['client_secret'] ?? ''))),
            'Scopes' => Form::text('connection_scopes', $esc((string) ($connection['scopes'] ?? 'openid profile email'))),
            'Create an account on first sign-in' => Form::select('connection_auto_provision', ['Yes', 'No'], [TXT_93, TXT_94], $autoProvision),
            'Redirect URI' => Form::text('redirect_uri', $esc(\Adlexone\Auth\AuthConfig::callbackUrl()), '', true),
        ];

        Page::current()->setBody(Form::form(
            'Sign-in',
            '?manage=settings&option=update_sign_in_settings',
            $fields,
            [Form::button('submit', 'submit_button', TXT_56)]
        ));
    }

    /**
     * Installed themes, keyed by directory name.
     *
     * @return array<string, string>
     */
    public static function installedThemes(): array
    {
        return \Adlexone\Theme\Theme::installed();
    }

    public static function showThemeSettings(): void
    {
        $themes = self::installedThemes();
        $current = defined('SET_DEFAULT_THEME') ? (string) SET_DEFAULT_THEME : 'inlay-stone';
        $current = \Adlexone\Theme\Theme::canonicalize($current);
        if (!isset($themes[$current]) && $current !== '') {
            $themes[$current] = $current;
        }
        $fields = [
            'Default theme' => Form::select('SET_DEFAULT_THEME', array_keys($themes), array_values($themes), $current)
                . '<p class="form-help">Used when a person has not chosen a theme on their own account.</p>',
        ];
        Page::current()->setBody(Form::form(
            'Theme',
            '?manage=settings&option=update_theme_settings',
            $fields,
            [Form::button('submit', 'submit_button', TXT_56)]
        ));
    }

    public static function showEmailSettings(): void
    {
        $smtp = static function (string $name): string {
            return defined($name) ? (string) constant($name) : '';
        };
        $fields = [
            TXT_645 => Form::text('SET_SMTP_HOST', $smtp('SET_SMTP_HOST')),
            TXT_646 => Form::text('SET_SMTP_PORT', $smtp('SET_SMTP_PORT')),
            TXT_649 => Form::text('SET_SMTP_USER', $smtp('SET_SMTP_USER')),
            TXT_650 => Form::password('SET_SMTP_PASSWORD', $smtp('SET_SMTP_PASSWORD')),
        ];
        Page::current()->setBody(Form::form(
            TXT_565,
            '?manage=settings&option=update_email_settings',
            $fields,
            [Form::button('submit', 'submit_button', TXT_56)]
        ));
    }
}
