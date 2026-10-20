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
use Adlexone\Html\Html;

/**
 * Screen formerly loaded from legacy/.
 */
final class SiteSettingsScreen
{
    /** @var list<string> */
    private const ENGINE_KEYS = [
        'SET_CUSTOM_FIELDS_IN_SEARCH',
        'SET_OWNER_MENU',
        'SET_FORM_FIELD_HEIGHT',
        'SET_SECURE_TYPE',
        'SET_LOG_ENTRY',
        'SET_ATTACHMENTS',
        'ADD_ATTACHMENTS',
        'SET_MAX_ATTACHMENT',
        'SET_DEFAULT_ITEM_TYPE',
    ];

    public static function open(): void
    {
        $option = (string) ($_GET['option'] ?? '');
        if ($option === 'my_theme' || $option === 'update_my_theme') {
            self::myTheme($option === 'update_my_theme');
            return;
        }

        /**
         * Settings links sit in the top navigation card. There is no left sidebar.
         */
        RenderNavigation::applySectionNav('Settings', RenderNavigation::systemSettingsURLs());













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
                $settings['SET_HOME_LEAD'] = trim((string) ($posted['SET_HOME_LEAD'] ?? $existing['SET_HOME_LEAD'] ?? 'This site keeps the applications, records, and rules for the work.'));
                SharedMethods::saveSettingsToJson($path, $settings);
                break;
            case 'advanced_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
                self::showAdvancedSettings();
                break;
            case 'update_advanced_settings' :
                Access::require(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
                self::saveAdvanced($_POST);
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
                if ($clientSecret === '' && defined('AUTH_OAUTH_CONNECTIONS_JSON')) {
                    $kept = json_decode((string) AUTH_OAUTH_CONNECTIONS_JSON, true);
                    if (is_array($kept)) {
                        foreach ($kept as $row) {
                            if (is_array($row) && trim((string) ($row['id'] ?? '')) === $id) {
                                $clientSecret = (string) ($row['client_secret'] ?? '');
                                break;
                            }
                        }
                    }
                }
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
                $smtpPassword = (string) ($_POST['SET_SMTP_PASSWORD'] ?? '');
                if ($smtpPassword === '' && defined('SET_SMTP_PASSWORD')) {
                    $smtpPassword = (string) SET_SMTP_PASSWORD;
                }
                SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'email_settings.json', [
                    'SET_SMTP_HOST' => $_POST['SET_SMTP_HOST'] ?? '',
                    'SET_SMTP_PORT' => $_POST['SET_SMTP_PORT'] ?? '',
                    'SET_SMTP_USER' => $_POST['SET_SMTP_USER'] ?? '',
                    'SET_SMTP_PASSWORD' => $smtpPassword,
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
        $nameArray = ['Home'];
        $baseURLArray = ['home}-{Home'];
        ApplicationStore::mergeHomeChoices($baseURLArray, $nameArray);
        $groupValues = ['None'];
        $groupLabels = ['None'];
        foreach (Database::select('groups', ['group_id', 'group_name']) as $row) {
            $groupValues[] = $row['group_id'];
            $groupLabels[] = $row['group_name'];
        }
        $homeLead = defined('SET_HOME_LEAD') ? (string) SET_HOME_LEAD : 'This site keeps the applications, records, and rules for the work.';
        $fields = [
            'Records per page' => Form::text('SET_ITEMS_PAGE', SET_ITEMS_PAGE),
            'Date format' => Form::select(
                'SET_DATE_FORMAT',
                ['d-m-Y, h:i A', 'm-d-Y, h:i A', 'd.m.Y H:i'],
                ['14-10-2026, 10:01 AM', '10-14-2026, 10:01 AM', '14.10.2026 22:01'],
                SET_DATE_FORMAT
            ),
            'Home application' => Form::select('SET_DEFAULT_APPLICATION', $baseURLArray, $nameArray, @SET_DEFAULT_APPLICATION),
            'Group for new accounts' => Form::select('USER_REG_ACTION', $groupValues, $groupLabels, USER_REG_ACTION),
            'Sign-in sentence' => Form::textarea('SET_HOME_LEAD', $homeLead, '3'),
        ];
        Page::current()->setBody(Form::form(
            'Site',
            '?manage=settings&option=update_adlexone_settings',
            $fields,
            [Form::button('submit', 'submit_button', TXT_56)]
        ));
    }

    public static function showAdvancedSettings(): void
    {
        $menuValue = (@SET_OWNER_MENU == '') ? 5 : SET_OWNER_MENU;
        $typeValues = [];
        $typeLabels = [];
        $owners = \Adlexone\Application\ApplicationSettings::typeOwners();
        foreach (Database::select('item_types', ['item_type_id', 'item_type_name'], '', [], 'item_type_name ASC') as $row) {
            $id = (int) $row['item_type_id'];
            if (!isset($owners[$id]) && (string) $id !== (string) SET_DEFAULT_ITEM_TYPE) {
                continue;
            }
            $typeValues[] = $row['item_type_id'];
            $typeLabels[] = $row['item_type_name'];
        }
        $fields = [
            'Search fields to include' => Form::select('SET_CUSTOM_FIELDS_IN_SEARCH', [2, 5, 10, 15], [2, 5, 10, 15], SET_CUSTOM_FIELDS_IN_SEARCH),
            'Role shown on a record' => Form::select('SET_OWNER_MENU', [5, 4, 3, 2, 1, 0], [TXT_303, TXT_194, TXT_193, TXT_192, TXT_191, TXT_190], $menuValue),
            'Note height' => Form::text('SET_FORM_FIELD_HEIGHT', SET_FORM_FIELD_HEIGHT),
            'Ask which record type when creating' => Form::select('SET_SECURE_TYPE', ['yes', 'no'], [TXT_93, TXT_94], SET_SECURE_TYPE),
            'Show the log on a record' => Form::select('SET_LOG_ENTRY', ['yes', 'no'], [TXT_93, TXT_94], SET_LOG_ENTRY),
            'Show files on a record' => Form::select('SET_ATTACHMENTS', ['yes', 'no'], [TXT_93, TXT_94], SET_ATTACHMENTS),
            'Allow a file when creating' => Form::select('ADD_ATTACHMENTS', ['yes', 'no'], [TXT_93, TXT_94], ADD_ATTACHMENTS),
            'Largest file (MB)' => Form::text('SET_MAX_ATTACHMENT', SET_MAX_ATTACHMENT),
            'Default record type' => Form::select('SET_DEFAULT_ITEM_TYPE', $typeValues, $typeLabels, SET_DEFAULT_ITEM_TYPE),
            'Debugging' => Form::select('SET_DEBUG_MODE', ['Yes', 'No'], [TXT_93, TXT_94], SET_DEBUG_MODE),
            'Show SQL queries' => Form::select('SET_SHOW_SQL', ['Yes', 'No'], [TXT_93, TXT_94], SET_SHOW_SQL),
            'PHP error reporting' => Form::select('SET_ERROR_REPORTING_LEVEL', ['6135', '6143'], [TXT_504, TXT_207], SET_ERROR_REPORTING_LEVEL),
        ];
        Page::current()->setBody(Form::form(
            'Advanced',
            '?manage=settings&option=update_advanced_settings',
            $fields,
            [Form::button('submit', 'submit_button', TXT_56)]
        ));
    }

    /**
     * @param array<string, mixed> $posted
     */
    private static function saveAdvanced(array $posted): void
    {
        unset($posted['submit_button'], $posted['reset'], $posted['_csrf']);
        $engine = [];
        $advanced = [];
        foreach ($posted as $key => $value) {
            if (in_array((string) $key, self::ENGINE_KEYS, true)) {
                $engine[$key] = $value;
            } else {
                $advanced[$key] = $value;
            }
        }
        if ($engine !== []) {
            $path = SET_CONFIGURATION_PATH . 'adlexone_settings.json';
            $existing = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
            if (!is_array($existing)) {
                $existing = [];
            }
            file_put_contents($path, json_encode(array_merge($existing, $engine), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        $path = SET_CONFIGURATION_PATH . 'advanced_settings.json';
        $existing = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        if (!is_array($existing)) {
            $existing = [];
        }
        SharedMethods::saveSettingsToJson($path, array_merge($existing, $advanced));
    }

    private static function myTheme(bool $save): void
    {
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        if ($userId <= 0) {
            Access::deny();
        }
        $themes = \Adlexone\Theme\Theme::installed();
        if ($save) {
            $theme = \Adlexone\Theme\Theme::canonicalize(trim((string) ($_POST['theme'] ?? '')));
            if (!isset($themes[$theme])) {
                View::respond('Choose an installed theme.', Blocks::link('?manage=settings&option=my_theme', 'Theme', '', 'btn btn--primary btn--sm'));
                return;
            }
            Database::update('users', ['theme' => $theme], 'user_id = ?', [$userId]);
            $_SESSION['access_theme'] = $theme;
            header('Location: ?manage=settings&option=my_theme');
            exit;
        }
        $current = \Adlexone\Theme\Theme::canonicalize((string) ($_SESSION['access_theme'] ?? ''));
        if (!isset($themes[$current])) {
            $current = defined('SET_DEFAULT_THEME') ? (string) SET_DEFAULT_THEME : 'inlay-stone';
        }
        Page::current()->setBody(Form::form(
            'Theme',
            '?manage=settings&option=update_my_theme',
            [
                'Your theme' => \Adlexone\Theme\Theme::picker('theme', $current)
                    . '<p class="form-help">Used on your account. The site default applies when this is left unset.</p>',
            ],
            [Form::button('submit', 'submit_button', 'Save')]
        ));
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
            return Html::e($value);
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
            'Client secret' => Form::password('connection_client_secret', '')
                . '<p class="form-help">Leave this blank to keep the current client secret. It is stored in the settings folder, mode 600, outside the web root.</p>',
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
            'Default theme' => \Adlexone\Theme\Theme::picker('SET_DEFAULT_THEME', $current)
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
            TXT_650 => Form::password('SET_SMTP_PASSWORD', '')
                . '<p class="form-help">Leave this blank to keep the current SMTP password. It is written to the settings folder beside the SQLite file, not into the page.</p>',
        ];
        Page::current()->setBody(Form::form(
            TXT_565,
            '?manage=settings&option=update_email_settings',
            $fields,
            [Form::button('submit', 'submit_button', TXT_56)]
        ));
    }
}
