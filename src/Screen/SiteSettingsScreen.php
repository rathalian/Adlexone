<?php
namespace Adlexone\Screen;

use Adlexone\Application\ApplicationStore;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\support\SharedMethods;
use Adlexone\Http\Page;
use Adlexone\Http\Request;
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
    private const DATE_FORMATS = ['d-m-Y, h:i A', 'm-d-Y, h:i A', 'd.m.Y H:i'];

    /**
     * @param list<string> $problems
     */
    private static function reject(array $problems, string $option): void
    {
        View::respond(
            implode(' ', $problems),
            Blocks::link('?manage=settings&option=' . $option, TXT_55, 'URL')
        );
    }

    /**
     * Values the home application list offers.
     *
     * @return list<string>
     */
    private static function homeValues(): array
    {
        $names = ['Home'];
        $values = ['home}-{Home'];
        ApplicationStore::mergeHomeChoices($values, $names);
        return array_map('strval', $values);
    }

    /**
     * Checks one posted advanced setting. Returns the value to store, or null
     * with a message in $problem.
     */
    private static function advancedValue(string $key, string $value, ?string &$problem): ?string
    {
        $value = trim($value);
        $problem = null;
        $whole = static fn (int $min, int $max): bool => preg_match('/^\d{1,5}$/', $value) === 1
            && (int) $value >= $min && (int) $value <= $max;
        switch ($key) {
            case 'SET_CUSTOM_FIELDS_IN_SEARCH':
                if (in_array($value, ['2', '5', '10', '15'], true)) {
                    return $value;
                }
                $problem = 'Choose how many search fields to include from the list.';
                return null;
            case 'SET_OWNER_MENU':
                if (preg_match('/^[0-5]$/', $value) === 1) {
                    return $value;
                }
                $problem = 'Choose the role shown on a record from the list.';
                return null;
            case 'SET_FORM_FIELD_HEIGHT':
                if ($whole(1, 50)) {
                    return (string) (int) $value;
                }
                $problem = 'Enter the note height as a whole number from 1 to 50.';
                return null;
            case 'SET_SECURE_TYPE':
            case 'SET_LOG_ENTRY':
            case 'SET_ATTACHMENTS':
            case 'ADD_ATTACHMENTS':
                if (in_array($value, ['yes', 'no'], true)) {
                    return $value;
                }
                $problem = 'Choose Yes or No for each record option.';
                return null;
            case 'SET_MAX_ATTACHMENT':
                if ($whole(1, 1000)) {
                    return (string) (int) $value;
                }
                $problem = 'Enter the largest file size as a whole number of megabytes from 1 to 1000.';
                return null;
            case 'SET_DEFAULT_ITEM_TYPE':
                if (ctype_digit($value) && Database::exists('item_types', 'item_type_id = ?', [(int) $value])) {
                    return $value;
                }
                $problem = 'Choose one of the listed record types as the default.';
                return null;
            case 'SET_DEBUG_MODE':
            case 'SET_SHOW_SQL':
                if (in_array($value, ['Yes', 'No'], true)) {
                    return $value;
                }
                $problem = 'Choose Yes or No for debugging and for showing SQL.';
                return null;
            case 'SET_ERROR_REPORTING_LEVEL':
                if (in_array($value, ['6135', '6143'], true)) {
                    return $value;
                }
                $problem = 'Choose a PHP error reporting level from the list.';
                return null;
        }
        $problem = '';
        return null;
    }

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
        $option = Request::query('option');
        if ($option === 'my_theme' || $option === 'update_my_theme') {
            self::myTheme($option === 'update_my_theme');
            return;
        }

        RenderNavigation::applyConfigureNav([['label' => 'Settings', 'href' => '?manage=settings&option=adlexone_settings']], RenderNavigation::systemSettingsURLs());













        switch ($option) {
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
                $problems = [];
                $settings = $existing;
                $posted = $_POST;
                if (array_key_exists('SET_ITEMS_PAGE', $posted)) {
                    $perPage = trim((string) $posted['SET_ITEMS_PAGE']);
                    if (preg_match('/^\d{1,3}$/', $perPage) !== 1 || (int) $perPage < 1 || (int) $perPage > 500) {
                        $problems[] = 'Enter records per page as a whole number from 1 to 500.';
                    } else {
                        $settings['SET_ITEMS_PAGE'] = (string) (int) $perPage;
                    }
                }
                if (array_key_exists('SET_DATE_FORMAT', $posted)) {
                    if (!in_array((string) $posted['SET_DATE_FORMAT'], self::DATE_FORMATS, true)) {
                        $problems[] = 'Choose one of the listed date formats.';
                    } else {
                        $settings['SET_DATE_FORMAT'] = (string) $posted['SET_DATE_FORMAT'];
                    }
                }
                if (array_key_exists('SET_TIMEZONE', $posted)) {
                    if (!in_array((string) $posted['SET_TIMEZONE'], \DateTimeZone::listIdentifiers(), true)) {
                        $problems[] = 'Choose one of the listed time zones.';
                    } else {
                        $settings['SET_TIMEZONE'] = (string) $posted['SET_TIMEZONE'];
                    }
                }
                if (array_key_exists('SET_DEFAULT_APPLICATION', $posted)) {
                    if (!in_array((string) $posted['SET_DEFAULT_APPLICATION'], self::homeValues(), true)) {
                        $problems[] = 'Choose one of the listed home applications.';
                    } else {
                        $settings['SET_DEFAULT_APPLICATION'] = (string) $posted['SET_DEFAULT_APPLICATION'];
                    }
                }
                if (array_key_exists('USER_REG_ACTION', $posted)) {
                    $group = (string) $posted['USER_REG_ACTION'];
                    if ($group !== 'None' && !(ctype_digit($group) && Database::exists('groups', 'group_id = ?', [(int) $group]))) {
                        $problems[] = 'Choose one of the listed groups for new accounts.';
                    } else {
                        $settings['USER_REG_ACTION'] = $group;
                    }
                }
                if (array_key_exists('SET_HOME_LEAD', $posted)) {
                    $lead = trim((string) $posted['SET_HOME_LEAD']);
                    if (mb_strlen($lead) > 300) {
                        $problems[] = 'Keep the sign-in sentence to 300 characters or fewer.';
                    } else {
                        $settings['SET_HOME_LEAD'] = $lead;
                    }
                }
                if ($problems !== []) {
                    self::reject($problems, 'adlexone_settings');
                    break;
                }
                $settings['SET_DEFAULT_THEME'] = defined('SET_DEFAULT_THEME') ? SET_DEFAULT_THEME : 'inlay-stone';
                $settings['SET_DEFAULT_LANGUAGE'] = defined('SET_DEFAULT_LANGUAGE') ? SET_DEFAULT_LANGUAGE : 'English';
                $settings['SET_HOME_LEAD'] = (string) ($settings['SET_HOME_LEAD'] ?? 'This site keeps the applications, records, and rules for the work.');
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
                $problems = [];
                if (preg_match('/^[A-Za-z0-9._-]{1,40}$/', $id) !== 1) {
                    $problems[] = 'The connection identifier may only use letters, numbers, dots, underscores, and hyphens.';
                }
                if ($issuer !== '' && !preg_match('#^https?://[^\s/]+(/\S*)?$#i', $issuer)) {
                    $problems[] = 'Enter the issuer URL as a full address, such as https://login.example.com.';
                }
                $oauthOn = $yes('AUTH_OAUTH_ENABLED') === 'Yes';
                $oauthReady = $issuer !== '' && $clientId !== '';
                if ($oauthOn && !$oauthReady) {
                    $problems[] = 'Enter the issuer URL and client ID before turning on identity provider sign-in.';
                }
                if ($yes('AUTH_OAUTH_AUTO_REDIRECT') === 'Yes' && !$oauthOn) {
                    $problems[] = 'Turn on identity provider sign-in before sending people straight to it.';
                }
                if ($yes('AUTH_LOCAL_ENABLED', 'Yes') === 'No' && !($oauthOn && $oauthReady)) {
                    $problems[] = 'Keep username and password sign-in on until an identity provider is set up. Turning both off would lock everyone out.';
                }
                if ($problems !== []) {
                    self::reject($problems, 'sign_in_settings');
                    break;
                }
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
                $smtpHost = trim((string) ($_POST['SET_SMTP_HOST'] ?? ''));
                $smtpPort = trim((string) ($_POST['SET_SMTP_PORT'] ?? ''));
                $problems = [];
                if ($smtpHost !== '' && preg_match('/^[A-Za-z0-9._:-]{1,255}$/', $smtpHost) !== 1) {
                    $problems[] = 'Enter the mail server as a host name, such as mail.example.com.';
                }
                if ($smtpPort !== '' && (preg_match('/^\d{1,5}$/', $smtpPort) !== 1 || (int) $smtpPort < 1 || (int) $smtpPort > 65535)) {
                    $problems[] = 'Enter the mail port as a number from 1 to 65535.';
                }
                if ($problems !== []) {
                    self::reject($problems, 'email_settings');
                    break;
                }
                SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'email_settings.json', [
                    'SET_SMTP_HOST' => $smtpHost,
                    'SET_SMTP_PORT' => $smtpPort,
                    'SET_SMTP_USER' => trim((string) ($_POST['SET_SMTP_USER'] ?? '')),
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
        $zoneValues = \DateTimeZone::listIdentifiers();
        $zoneLabels = [];
        $now = new \DateTimeImmutable('now');
        foreach ($zoneValues as $zone) {
            $offset = $now->setTimezone(new \DateTimeZone($zone))->format('P');
            $zoneLabels[] = str_replace('_', ' ', $zone) . ' (UTC' . $offset . ')';
        }
        $fields = [
            'Records per page' => Form::text('SET_ITEMS_PAGE', SET_ITEMS_PAGE),
            'Date format' => Form::select(
                'SET_DATE_FORMAT',
                ['d-m-Y, h:i A', 'm-d-Y, h:i A', 'd.m.Y H:i'],
                ['14-10-2026, 10:01 AM', '10-14-2026, 10:01 AM', '14.10.2026 22:01'],
                SET_DATE_FORMAT
            ),
            'Time zone' => Form::select('SET_TIMEZONE', $zoneValues, $zoneLabels, date_default_timezone_get())
                . '<p class="form-help">Greetings, dates, and times are shown in this zone. The offset listed includes daylight saving where it applies today.</p>',
            'Home application' => Form::select('SET_DEFAULT_APPLICATION', $baseURLArray, $nameArray, @SET_DEFAULT_APPLICATION),
            'Group for new accounts' => Form::select('USER_REG_ACTION', $groupValues, $groupLabels, USER_REG_ACTION),
            'Home introduction' => Form::textarea('SET_HOME_LEAD', $homeLead, '3'),
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
        $fields = [
            'Search fields to include' => Form::select('SET_CUSTOM_FIELDS_IN_SEARCH', [2, 5, 10, 15], [2, 5, 10, 15], SET_CUSTOM_FIELDS_IN_SEARCH),
            'Note height' => Form::text('SET_FORM_FIELD_HEIGHT', SET_FORM_FIELD_HEIGHT),
            'Show the log on a record' => Form::select('SET_LOG_ENTRY', ['yes', 'no'], [TXT_93, TXT_94], SET_LOG_ENTRY),
            'Show files on a record' => Form::select('SET_ATTACHMENTS', ['yes', 'no'], [TXT_93, TXT_94], SET_ATTACHMENTS),
            'Allow a file when creating' => Form::select('ADD_ATTACHMENTS', ['yes', 'no'], [TXT_93, TXT_94], ADD_ATTACHMENTS),
            'Largest file (MB)' => Form::text('SET_MAX_ATTACHMENT', SET_MAX_ATTACHMENT),
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
        $engine = [];
        $advanced = [];
        $problems = [];
        foreach ($posted as $key => $value) {
            $key = (string) $key;
            if (!is_scalar($value)) {
                continue;
            }
            $clean = self::advancedValue($key, (string) $value, $problem);
            if ($clean === null) {
                if ($problem !== '') {
                    $problems[$problem] = $problem;
                }
                continue;
            }
            if (in_array($key, self::ENGINE_KEYS, true)) {
                $engine[$key] = $clean;
            } else {
                $advanced[$key] = $clean;
            }
        }
        if ($problems !== []) {
            self::reject(array_values($problems), 'advanced_settings');
            return;
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
            if ($theme !== '' && !isset($themes[$theme])) {
                View::respond('Choose an installed theme.', Blocks::link('?manage=settings&option=my_theme', 'Theme', '', 'btn btn--primary btn--sm'));
                return;
            }
            Database::update('users', ['theme' => $theme], 'user_id = ?', [$userId]);
            $_SESSION['access_theme'] = $theme;
            header('Location: ?manage=settings&option=my_theme');
            exit;
        }
        Page::current()->setCrumbs(\Adlexone\Html\Breadcrumb::trail([['label' => 'My theme']]));
        $own = Database::first('users', ['theme'], 'user_id = ?', [$userId]);
        $current = \Adlexone\Theme\Theme::canonicalize((string) ($own['theme'] ?? ''));
        if (!isset($themes[$current])) {
            $current = '';
        }
        $default = \Adlexone\Theme\Theme::canonicalize(defined('SET_DEFAULT_THEME') ? (string) SET_DEFAULT_THEME : 'inlay-stone');
        $defaultLabel = $themes[$default] ?? $default;
        Page::current()->setBody(Form::form(
            'Theme',
            '?manage=settings&option=update_my_theme',
            [
                'Your theme' => \Adlexone\Theme\Theme::picker('theme', $current, false, 'Follow the site theme')
                    . '<p class="form-help">Follow the site theme to use ' . Html::e($defaultLabel) . ', the default the administrator has set. Choose another theme to keep it on your account only.</p>',
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
        $esc = static fn (string $value): string => $value;

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
