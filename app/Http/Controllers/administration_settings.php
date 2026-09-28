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

use Adlexone\support\RenderViews;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\support\SharedMethods;
use Adlexone\support\Communications;

/**
 * Settings links sit in the top navigation card. There is no left sidebar.
 */
RenderNavigation::applySectionNav('System Settings', RenderNavigation::systemSettingsURLs());

function showAdlexoneSettings(): void
{
    // Setup form fields with values from settings file
    $fields[TXT_457] = RenderViews::buildSelectDropdown('SET_CACHED', array('On', 'Off'), array('On', 'Off'), SET_CACHED);
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
    // Setup Timezone
    $timeZoneArray = array('Africa/Abidjan', 'Africa/Accra', 'Africa/Addis_Ababa', 'Africa/Algiers', 'Africa/Asmera', 'Africa/Bamako', 'Africa/Bangui', 'Africa/Banjul', 'Africa/Bissau', 'Africa/Blantyre', 'Africa/Brazzaville', 'Africa/Bujumbura', 'Africa/Cairo', 'Africa/Casablanca', 'Africa/Ceuta', 'Africa/Conakry', 'Africa/Dakar', 'Africa/Dar_es_Salaam', 'Africa/Djibouti', 'Africa/Douala', 'Africa/El_Aaiun', 'Africa/Freetown', 'Africa/Gaborone', 'Africa/Hara', 'Africa/Johannesburg', 'Africa/Kampala', 'Africa/Khartoum', 'Africa/Kigali', 'Africa/Kinshasa', 'Africa/Lagos', 'Africa/Libreville', 'Africa/Lome', 'Africa/Luanda', 'Africa/Lubumbashi', 'Africa/Lusaka', 'Africa/Malabo', 'Africa/Maputo', 'Africa/Maseru', 'Africa/Mbabane', 'Africa/Mogadishu', 'Africa/Monrovia', 'Africa/Nairobi', 'Africa/Ndjamena', 'Africa/Niamey', 'Africa/Nouakchott', 'Africa/Ouagadougou', 'Africa/Porto-Novo', 'Africa/Sao_Tome', 'Africa/Timbuktu', 'Africa/Tripoli', 'Africa/Tunis', 'Africa/Windhoek', 'America/Adak', 'America/Anchorage', 'America/Anguilla', 'America/Antigua', 'America/Araguaina', 'America/Aruba', 'America/Asuncion', 'America/Barbados', 'America/Belem', 'America/Belize', 'America/Bogota', 'America/Boise', 'America/Buenos_Aires', 'America/Cancun', 'America/Caracas', 'America/Catamarca', 'America/Cayenne', 'America/Cayman', 'America/Chicago', 'America/Chihuahua', 'America/Cordoba', 'America/Costa_Rica', 'America/Cuiaba', 'America/Curacao', 'America/Dawson', 'America/Dawson_Creek', 'America/Denver', 'America/Detroit', 'America/Dominica', 'America/Edmonton', 'America/El_Salvador', 'America/Ensenada', 'America/Fortaleza', 'America/Glace_Bay', 'America/Godthab', 'America/Goose_Bay', 'America/Grand_Turk', 'America/Grenada', 'America/Guadeloupe', 'America/Guatemala', 'America/Guayaquil', 'America/Guyana', 'America/Halifax', 'America/Havana', 'America/Indiana/Knox', 'America/Indiana/Marengo', 'America/Indiana/Vevay', 'America/Indianapolis', 'America/Inuvik', 'America/Iqaluit', 'America/Jamaica', 'America/Jujuy', 'America/Juneau', 'America/La_Paz', 'America/Lima', 'America/Los_Angeles', 'America/Louisville', 'America/Maceio', 'America/Managua', 'America/Manaus', 'America/Martinique', 'America/Mazatlan', 'America/Mendoza', 'America/Menominee', 'America/Mexico_City', 'America/Miquelon', 'America/Montevideo', 'America/Montreal', 'America/Montserrat', 'America/Nassau', 'America/New_York', 'America/Nipigon', 'America/Nome', 'America/Noronha', 'America/Panama', 'America/Pangnirtung', 'America/Paramaribo', 'America/Phoenix', 'America/Port-au-Prince', 'America/Port_of_Spain', 'America/Porto_Acre', 'America/Porto_Velho', 'America/Puerto_Rico', 'America/Rainy_River', 'America/Rankin_Inlet', 'America/Regina', 'America/Rosario', 'America/Santiago', 'America/Santo_Domingo', 'America/Sao_Paulo', 'America/Scoresbysund', 'America/Shiprock', 'America/St_Johns', 'America/St_Kitts', 'America/St_Lucia', 'America/St_Thomas', 'America/St_Vincent', 'America/Swift_Current', 'America/Tegucigalpa', 'America/Thule', 'America/Thunder_Bay', 'America/Tijuana', 'America/Tortola', 'America/Vancouver', 'America/Whitehorse', 'America/Winnipeg', 'America/Yakutat', 'America/Yellowknife', 'Antarctica/Casey', 'Antarctica/Davis', 'Antarctica/DumontDUrville', 'Antarctica/Mawson', 'Antarctica/McMurdo', 'Antarctica/Palmer', 'Antarctica/South_Pole', 'Arctic/Longyearbyen', 'Asia/Aden', 'Asia/Almaty', 'Asia/Amman', 'Asia/Anadyr', 'Asia/Aqtau', 'Asia/Aqtobe', 'Asia/Ashkhabad', 'Asia/Baghdad', 'Asia/Bahrain', 'Asia/Baku', 'Asia/Bangkok', 'Asia/Beirut', 'Asia/Bishkek', 'Asia/Brunei', 'Asia/Calcutta', 'Asia/Chungking', 'Asia/Colombo', 'Asia/Dacca', 'Asia/Damascus', 'Asia/Dubai', 'Asia/Dushanbe', 'Asia/Gaza', 'Asia/Harbin', 'Asia/Hong_Kong', 'Asia/Irkutsk', 'Asia/Jakarta', 'Asia/Jayapura', 'Asia/Jerusalem', 'Asia/Kabul', 'Asia/Kamchatka', 'Asia/Karachi', 'Asia/Kashgar', 'Asia/Katmandu', 'Asia/Krasnoyarsk', 'Asia/Kuala_Lumpur', 'Asia/Kuching', 'Asia/Kuwait', 'Asia/Macao', 'Asia/Magadan', 'Asia/Manila', 'Asia/Muscat', 'Asia/Nicosia', 'Asia/Novosibirsk', 'Asia/Omsk', 'Asia/Phnom_Penh', 'Asia/Pyongyang', 'Asia/Qatar', 'Asia/Rangoon', 'Asia/Riyadh', 'Asia/Saigon', 'Asia/Samarkand', 'Asia/Seoul', 'Asia/Shanghai', 'Asia/Singapore', 'Asia/Taipei', 'Asia/Tashkent', 'Asia/Tbilisi', 'Asia/Tehran', 'Asia/Thimbu', 'Asia/Tokyo', 'Asia/Ujung_Pandang', 'Asia/Ulan_Bator', 'Asia/Urumqi', 'Asia/Vientiane', 'Asia/Vladivostok', 'Asia/Yakutsk', 'Asia/Yekaterinburg', 'Asia/Yerevan', 'Atlantic/Azores', 'Atlantic/Bermuda', 'Atlantic/Canary', 'Atlantic/Cape_Verde', 'Atlantic/Faeroe', 'Atlantic/Jan_Mayen', 'Atlantic/Madeira', 'Atlantic/Reykjavik', 'Atlantic/South_Georgia', 'Atlantic/St_Helena', 'Atlantic/Stanley', 'Australia/Adelaide', 'Australia/Brisbane', 'Australia/Broken_Hill', 'Australia/Darwin', 'Australia/Hobart', 'Australia/Lindeman', 'Australia/Lord_Howe', 'Australia/Melbourne', 'Australia/Perth', 'Australia/Sydney', 'Europe/Amsterdam', 'Europe/Andorra', 'Europe/Athens', 'Europe/Belfast', 'Europe/Belgrade', 'Europe/Berlin', 'Europe/Bratislava', 'Europe/Brussels', 'Europe/Bucharest', 'Europe/Budapest', 'Europe/Chisinau', 'Europe/Copenhagen', 'Europe/Dublin', 'Europe/Gibraltar', 'Europe/Helsinki', 'Europe/Istanbul', 'Europe/Kaliningrad', 'Europe/Kiev', 'Europe/Lisbon', 'Europe/Ljubljana', 'Europe/London', 'Europe/Luxembourg', 'Europe/Madrid', 'Europe/Malta', 'Europe/Minsk', 'Europe/Monaco', 'Europe/Moscow', 'Europe/Oslo', 'Europe/Paris', 'Europe/Prague', 'Europe/Riga', 'Europe/Rome', 'Europe/Samara', 'Europe/San_Marino', 'Europe/Sarajevo', 'Europe/Simferopol', 'Europe/Skopje', 'Europe/Sofia', 'Europe/Stockholm', 'Europe/Tallinn', 'Europe/Tirane', 'Europe/Vaduz', 'Europe/Vatican', 'Europe/Vienna', 'Europe/Vilnius', 'Europe/Warsaw', 'Europe/Zagreb', 'Europe/Zurich', 'Indian/Antananarivo', 'Indian/Chagos', 'Indian/Christmas', 'Indian/Cocos', 'Indian/Comoro', 'Indian/Kerguelen', 'Indian/Mahe', 'Indian/Maldives', 'Indian/Mauritius', 'Indian/Mayotte', 'Indian/Reunion', 'Pacific/Apia', 'Pacific/Auckland', 'Pacific/Chatham', 'Pacific/Easter', 'Pacific/Efate', 'Pacific/Enderbury', 'Pacific/Fakaofo', 'Pacific/Fiji', 'Pacific/Funafuti', 'Pacific/Galapagos', 'Pacific/Gambier', 'Pacific/Guadalcanal', 'Pacific/Guam', 'Pacific/Honolulu', 'Pacific/Johnston', 'Pacific/Kiritimati', 'Pacific/Kosrae', 'Pacific/Kwajalein', 'Pacific/Majuro', 'Pacific/Marquesas', 'Pacific/Midway', 'Pacific/Nauru', 'Pacific/Niue', 'Pacific/Norfolk', 'Pacific/Noumea', 'Pacific/Pago_Pago', 'Pacific/Palau', 'Pacific/Pitcairn', 'Pacific/Ponape', 'Pacific/Port_Moresby', 'Pacific/Rarotonga', 'Pacific/Saipan', 'Pacific/Tahiti', 'Pacific/Tarawa', 'Pacific/Tongatapu', 'Pacific/Truk', 'Pacific/Wake', 'Pacific/Wallis', 'Pacific/Yap');
    $fields[TXT_386] = RenderViews::buildSelectDropdown('SET_TIMEZONE', $timeZoneArray, $timeZoneArray, SET_TIMEZONE);
    $fields[TXT_329] = RenderViews::buildSelectDropdown('SET_DATE_FORMAT', array('d-m-Y, h:i A', 'm-d-Y, h:i A', 'd.m.Y H:i'), array(TXT_330, TXT_481, TXT_654), SET_DATE_FORMAT);
    // Create a theme directory array
    if ($directory = opendir(THEME_PATH)) {
        $i = 0;
        while (($themeName = readdir($directory)) !== false) {
            if ($themeName != '.' && $themeName != '..' && is_dir(THEME_PATH . $themeName)) {
                $themeArray[$i++] = $themeName;
            }
        }
        closedir($directory);
    }
    $fields[TXT_182] = RenderViews::buildSelectDropdown('SET_DEFAULT_THEME', $themeArray, $themeArray, SET_DEFAULT_THEME);
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
    $fields[TXT_297] = RenderViews::buildSelectDropdown('SET_DEFAULT_APPLICATION', $baseURLArray, $nameArray, @SET_DEFAULT_APPLICATION, 'form-control');
    $fields[TXT_561] = RenderViews::buildSelectDropdown('SET_DEFAULT_START_PAGE', array('quick_launch', 'application'), array(TXT_562, TXT_297), @SET_DEFAULT_START_PAGE);
    // Get all item types
    $columnArray = array('item_type_id', 'item_type_name');
    $sql = Database::sqlSelect('item_types', $columnArray);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    while ($row = Database::fetchArray($result)) {
        $valueArray[] = $row['item_type_id'];
        $displayArray[] = $row['item_type_name'];
    }
    $fields[TXT_229] = RenderViews::buildSelectDropdown('SET_DEFAULT_ITEM_TYPE', $valueArray, $displayArray, SET_DEFAULT_ITEM_TYPE);
    $directory = opendir(SET_INSTALL_PATH . 'translations/');
    $i = 0;
    $languageFileArray = [];
    while (($languageFile = readdir($directory)) !== false) {
        $filePath = SET_INSTALL_PATH . 'translations/' . $languageFile;
        if (is_file($filePath) && preg_match('/\.lang\.php$/', $languageFile)) {
            $languageFileArray[$i++] = str_replace('.lang.php', '', $languageFile);
        }
    }
    $fields[TXT_325] = RenderViews::buildSelectDropdown('SET_DEFAULT_LANGUAGE', $languageFileArray, $languageFileArray, SET_DEFAULT_LANGUAGE);
    $fields[TXT_546] = RenderViews::buildSelectDropdown('SET_DEFAULT_LOG', array('yes', 'no'), array(TXT_373, TXT_374), SET_DEFAULT_LOG);
    $fields[TXT_608] = RenderViews::buildSelectDropdown('ALLOW_USER_REG', array('yes', 'no'), array(TXT_93, TXT_94), ALLOW_USER_REG);
    $columnArray = array('group_id', 'group_name');
    $sql = Database::sqlSelect('groups', $columnArray);
    $result = Database::query($sql, DSN, SET_SHOW_SQL);
    $listValuesArray[] = "None";
    $listDisplayValuesArray[] = "None";
    while ($row = Database::fetchArray($result)) {
        $listValuesArray[] = $row['group_id'];
        $listDisplayValuesArray[] = $row['group_name'];
    }
    $fields[TXT_604] = RenderViews::buildSelectDropdown('USER_REG_ACTION', $listValuesArray, $listDisplayValuesArray, USER_REG_ACTION);
    $fields[TXT_605] = RenderViews::buildSelectDropdown('SHOW_USER_REG', array('yes', 'no'), array(TXT_93, TXT_94), SHOW_USER_REG);
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_56);
    $bodyContent = RenderViews::buildForm(TXT_42,'index.php?controller=administration_settings&option=update_adlexone_settings',$fields,$buttons);
    define('BODY_CONTENT', $bodyContent);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showDataSourceSettings(): void
{
    $fields = [
        TXT_632 => RenderViews::buildTextInput('SET_DS_DATA_SOURCE_COUNT', SET_DS_DATA_SOURCE_COUNT) . ' * ' . TXT_633,
    ];
    $i = 1;
    while ($i <= SET_DS_DATA_SOURCE_COUNT) {
        $prefix = TXT_638 . ' ' . $i . ' — ';
        $fields[$prefix . TXT_636] = RenderViews::buildTextInput('SET_DS_NAME_' . $i, defined('SET_DS_NAME_' . $i) ? constant('SET_DS_NAME_' . $i) : '');
        $fields[$prefix . TXT_458] = RenderViews::buildSelectDropdown('SET_DS_DB_TYPE_' . $i, array('mysql'), array('MySQL - Version 4 and 5'), defined('SET_DS_DB_TYPE_' . $i) ? constant('SET_DS_DB_TYPE_' . $i) : '');
        $fields[$prefix . TXT_459] = RenderViews::buildTextInput('SET_DS_DRIVER_' . $i, defined('SET_DS_DRIVER_' . $i) ? constant('SET_DS_DRIVER_' . $i) : '');
        $fields[$prefix . TXT_460] = RenderViews::buildTextInput('SET_DS_DATABASE_HOST_' . $i, defined('SET_DS_DATABASE_HOST_' . $i) ? constant('SET_DS_DATABASE_HOST_' . $i) : '');
        $fields[$prefix . TXT_461] = RenderViews::buildTextInput('SET_DS_DATABASE_PORT_' . $i, defined('SET_DS_DATABASE_PORT_' . $i) ? constant('SET_DS_DATABASE_PORT_' . $i) : '');
        $fields[$prefix . TXT_462] = RenderViews::buildTextInput('SET_DS_DATABASE_NAME_' . $i, defined('SET_DS_DATABASE_NAME_' . $i) ? constant('SET_DS_DATABASE_NAME_' . $i) : '');
        $fields[$prefix . TXT_464] = RenderViews::buildTextInput('SET_DS_DATABASE_USER_' . $i, defined('SET_DS_DATABASE_USER_' . $i) ? constant('SET_DS_DATABASE_USER_' . $i) : '');
        $fields[$prefix . TXT_465] = RenderViews::buildPasswordInput('SET_DS_DATABASE_PWD_' . $i, defined('SET_DS_DATABASE_PWD_' . $i) ? constant('SET_DS_DATABASE_PWD_' . $i) : '');
        $fields[$prefix . TXT_423] = RenderViews::buildTextArea('SET_DS_SQL_' . $i, defined('SET_DS_SQL_' . $i) ? constant('SET_DS_SQL_' . $i) : '', SET_FORM_FIELD_HEIGHT);
        $i++;
    }

    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_632,
        'index.php?controller=administration_settings&option=update_data_source_settings',
        $fields,
        [RenderViews::buildFormButton('submit', 'submit_button', TXT_56)]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showLDAPSettings(): void
{
    $fields = [
        TXT_484 => RenderViews::buildSelectDropdown('SET_LDAP_ENABLED', array('Yes', 'No'), array(TXT_499, TXT_500), SET_LDAP_ENABLED),
        TXT_483 => RenderViews::buildTextInput('SET_LDAP_SEARCH_COUNT', SET_LDAP_SEARCH_COUNT) . ' * ' . TXT_503,
    ];
    $i = 1;
    while ($i <= SET_LDAP_SEARCH_COUNT) {
        $prefix = TXT_485 . ' ' . $i . ' — ';
        $fields[$prefix . TXT_496] = RenderViews::buildSelectDropdown('SET_LDAP_ADLDAP_' . $i, array('AD', 'LDAP'), array(TXT_494, TXT_495), defined('SET_LDAP_ADLDAP_' . $i) ? constant('SET_LDAP_ADLDAP_' . $i) : '');
        $fields[$prefix . TXT_486] = RenderViews::buildTextInput('SET_LDAP_SERVER_NAME_' . $i, defined('SET_LDAP_SERVER_NAME_' . $i) ? constant('SET_LDAP_SERVER_NAME_' . $i) : '');
        $fields[$prefix . TXT_487] = RenderViews::buildSelectDropdown('SET_LDAP_SSL_' . $i, array('No', 'Yes'), array(TXT_94, TXT_93), defined('SET_LDAP_SSL_' . $i) ? constant('SET_LDAP_SSL_' . $i) : '');
        $fields[$prefix . TXT_491] = RenderViews::buildTextInput('SET_LDAP_DOMAIN_NAME_' . $i, defined('SET_LDAP_DOMAIN_NAME_' . $i) ? constant('SET_LDAP_DOMAIN_NAME_' . $i) : '');
        $fields[$prefix . TXT_493] = RenderViews::buildTextInput('SET_LDAP_BASE_DN_' . $i, defined('SET_LDAP_BASE_DN_' . $i) ? constant('SET_LDAP_BASE_DN_' . $i) : '');
        $fields[$prefix . TXT_488] = RenderViews::buildTextInput('SET_LDAP_USERNAME_' . $i, defined('SET_LDAP_USERNAME_' . $i) ? constant('SET_LDAP_USERNAME_' . $i) : '');
        $fields[$prefix . TXT_489] = RenderViews::buildPasswordInput('SET_LDAP_PASSWORD_' . $i, defined('SET_LDAP_PASSWORD_' . $i) ? constant('SET_LDAP_PASSWORD_' . $i) : '');
        $columnArray = array('group_id', 'group_name');
        $sql = Database::sqlSelect('groups', $columnArray);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $idArray = [''];
        $nameArray = [TXT_651];
        while ($row = Database::fetchArray($result)) {
            $idArray[] = $row['group_id'];
            $nameArray[] = $row['group_name'];
        }
        $fields[$prefix . TXT_618] = RenderViews::buildSelectDropdown('SET_LDAP_DEFAULT_GROUP_' . $i, $idArray, $nameArray, defined('SET_LDAP_DEFAULT_GROUP_' . $i) ? constant('SET_LDAP_DEFAULT_GROUP_' . $i) : '');
        $i++;
    }

    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_43,
        'index.php?controller=administration_settings&option=update_ldap_settings',
        $fields,
        [RenderViews::buildFormButton('submit', 'submit_button', TXT_56)]
    ));
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

function showAutologonSettings(): void
{
    // Setup form fields with values from settings file
    $fields[TXT_537] = RenderViews::buildSelectDropdown('REMEMBERME_ACTIVE', array('Yes', 'No'), array(TXT_93, TXT_94), REMEMBERME_ACTIVE);
    $fields[TXT_539] = RenderViews::buildTextInput('COOKIE_LENGTH', COOKIE_LENGTH);
    $fields[TXT_540] = RenderViews::buildTextInput('COOKIE_CODE', COOKIE_CODE);
    $buttons[] = RenderViews::buildFormButton('submit', 'submit_button', TXT_56);
    $bodyContent = RenderViews::buildForm(TXT_536,'index.php?controller=administration_settings&option=update_autologon_settings',$fields,$buttons);
    define('BODY_CONTENT', $bodyContent);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showDataSharingSettings(): void
{
    $fields = [
        TXT_427 => RenderViews::buildTextInput('DATA_LINK_COUNT', DATA_LINK_COUNT),
    ];
    $i = 1;
    while ($i <= DATA_LINK_COUNT) {
        $prefix = TXT_422 . ' ' . $i . ' — ';
        $fields[$prefix . TXT_416] = RenderViews::buildTextInput('TOKEN_' . $i, defined('TOKEN_' . $i) ? constant('TOKEN_' . $i) : '');
        $fields[$prefix . TXT_425] = RenderViews::buildTextInput('IP_ADDRESS_' . $i, defined('IP_ADDRESS_' . $i) ? constant('IP_ADDRESS_' . $i) : '');
        $fields[$prefix . TXT_423] = RenderViews::buildTextArea('SQL_QUERY_' . $i, defined('SQL_QUERY_' . $i) ? constant('SQL_QUERY_' . $i) : '', SET_FORM_FIELD_HEIGHT) . '<br />' . TXT_424;
        $i++;
    }

    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_427,
        'index.php?controller=administration_settings&option=update_data_sharing_settings',
        $fields,
        [RenderViews::buildFormButton('submit', 'submit_button', TXT_56)]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showEmailSettings(): void
{
    $fields = [];
    $fields[TXT_645] = RenderViews::buildTextInput('SET_SMTP_HOST', SET_SMTP_HOST);
    $fields[TXT_646] = RenderViews::buildTextInput('SET_SMTP_PORT', SET_SMTP_PORT);
    $fields[TXT_647] = RenderViews::buildTextInput('SET_SMTP_REPLY', SET_SMTP_REPLY);
    $fields[TXT_648] = RenderViews::buildSelectDropdown('SET_SMTP_AUTHENTICATION', array('Yes', 'No'), array(TXT_93, TXT_94), SET_SMTP_AUTHENTICATION);
    $fields[TXT_672] = RenderViews::buildSelectDropdown('SET_SMTP_TLS', array('Yes', 'No'), array(TXT_93, TXT_94), SET_SMTP_TLS);
    $fields[TXT_649] = RenderViews::buildTextInput('SET_SMTP_USER', SET_SMTP_USER);
    $fields[TXT_650] = RenderViews::buildPasswordInput('SET_SMTP_PASSWORD', SET_SMTP_PASSWORD);
    $emailCount = defined('EMAIL_COUNT') ? (int) constant('EMAIL_COUNT') : 0;
    $token = defined('TOKEN') ? (string) constant('TOKEN') : '';
    $fields[TXT_566] = RenderViews::buildTextInput('EMAIL_COUNT', (string)$emailCount);
    $fields[TXT_416] = RenderViews::buildTextInput('TOKEN', $token);

    $protocol = (isset($_SERVER['HTTPS']) and ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)) ? 'https://' : 'http://';
    $port = ($_SERVER['SERVER_PORT'] != '80') ? ':' . $_SERVER['SERVER_PORT'] : '';
    if (!empty($inboundEmailSettings['TOKEN'])) {
        $url = str_replace('index.php', 'external_scripts/email.php', $protocol . $_SERVER['SERVER_NAME'] . $port . $_SERVER['PHP_SELF']) . '?token=' . TOKEN;
        $fields[TXT_589] = $url;
    }
    $fields[TXT_590] = TXT_591 . ' ' . SET_INSTALL_PATH . 'external_scripts/email.php';

    $i = 1;

    while ($i <= $emailCount) {

        // Get all item item definitions from database
        $columnArray = array('item_type_id', 'item_type_name');
        $sql = Database::sqlSelect('item_types', $columnArray);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        unset($listValues);
        unset($listDisplayValues);
        while ($row = Database::fetchArray($result)) {
            $listValues[] = $row['item_type_id'];
            $listDisplayValues[] = $row['item_type_name'];
        }

        $prefix = TXT_567 . ' ' . $i . ' — ';
        $fields[$prefix . TXT_568] = RenderViews::buildSelectDropdown('ITEM_TYPE_ID_' . $i, $listValues, $listDisplayValues, defined('ITEM_TYPE_ID_' . $i) ? constant('ITEM_TYPE_ID_' . $i) : '');
        $columnArray = array('custom_field_id', 'custom_field_name');
        $condition = "ORDER BY custom_field_name ASC";
        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
        $result = Database::query($sql, DSN);
        $customFieldIDArray[] = '';
        $customFieldNameArray[] = 'Field Mapping Must Be Set';
        unset($customFieldIDArray);
        unset($customFieldNameArray);
        while ($row = Database::fetchArray($result)) {
            $customFieldIDArray[] = $row['custom_field_id'];
            $customFieldNameArray[] = $row['custom_field_name'];
        }

        $fields[$prefix . TXT_597] = RenderViews::buildSelectDropdown('EMAIL_BODY_CONTENT_CUSTOM_FIELD_ID_' . $i, $customFieldIDArray, $customFieldNameArray, defined('EMAIL_BODY_CONTENT_CUSTOM_FIELD_ID_' . $i) ? constant('EMAIL_BODY_CONTENT_CUSTOM_FIELD_ID_' . $i) : '');
        $fields[$prefix . TXT_572] = RenderViews::buildSelectDropdown('SERVER_TYPE_' . $i, array('pop3'), array(TXT_569), defined('SERVER_TYPE_' . $i) ? constant('SERVER_TYPE_' . $i) : '');
        $fields[$prefix . TXT_487] = RenderViews::buildSelectDropdown('SSL_TYPE_' . $i, array('none', 'SSL', 'TLS'), array(TXT_596, TXT_594, TXT_595), defined('SSL_TYPE_' . $i) ? constant('SSL_TYPE_' . $i) : '');
        $fields[$prefix . TXT_579] = RenderViews::buildTextInput('MAIL_HOST_NAME_' . $i, defined('MAIL_HOST_NAME_' . $i) ? constant('MAIL_HOST_NAME_' . $i) : '');
        $fields[$prefix . TXT_571] = RenderViews::buildTextInput('MAIL_HOST_PORT_' . $i, defined('MAIL_HOST_PORT_' . $i) ? constant('MAIL_HOST_PORT_' . $i) : '');
        $fields[$prefix . TXT_580] = RenderViews::buildTextInput('MAIL_USER_' . $i, defined('MAIL_USER_' . $i) ? constant('MAIL_USER_' . $i) : '');
        $fields[$prefix . TXT_581] = RenderViews::buildPasswordInput('MAIL_PASSWORD_' . $i, defined('MAIL_PASSWORD_' . $i) ? constant('MAIL_PASSWORD_' . $i) : '');
        $fields[$prefix . TXT_575] = RenderViews::buildSelectDropdown('MATCH_METHOD_' . $i, array('domain', 'email'), array(TXT_576, TXT_577), defined('MATCH_METHOD_' . $i) ? constant('MATCH_METHOD_' . $i) : '');
        $columnArray = array('user_id', 'user_name');
        $condition = "ORDER BY user_name ASC";
        $sql = Database::sqlSelect('users', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        unset($userIDArray);
        unset($userArray);
        while ($row = Database::fetchArray($result)) {
            $userIDArray[] = $row['user_id'];
            $userArray[] = $row['user_name'];
        }
        $fields[$prefix . TXT_578] = RenderViews::buildSelectDropdown('USER_ID_' . $i, $userIDArray, $userArray, defined('USER_ID_' . $i) ? constant('USER_ID_' . $i) : '');
        $fields[$prefix . TXT_269] = RenderViews::buildSelectDropdown('OWNER_' . $i, array_merge(array(''), $userIDArray), array_merge(array(TXT_267), $userArray), defined('OWNER_' . $i) ? constant('OWNER_' . $i) : '');
// Show available custom fields (render a semantic table of names)
        $columnArray = array('custom_field_id', 'custom_field_name');
        $condition = "ORDER BY custom_field_id ASC";
        $sql = Database::sqlSelect('custom_fields', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        $dynamicValues = '';
        while ($row = Database::fetchArray($result)) {
            $dynamicValues .= 'CUSTOM_FIELD_' . $row['custom_field_id'] . ' (' . $row['custom_field_name'] . '), ';
        }
        $fields[$prefix . TXT_655] = RenderViews::buildTextArea('SUBJECT_MATCH_' . $i, defined('SUBJECT_MATCH_' . $i) ? constant('SUBJECT_MATCH_' . $i) : '', SET_FORM_FIELD_HEIGHT) . '<br>* ' . TXT_657;
        $fields[$prefix . TXT_582] = RenderViews::buildTextArea('CUSTOM_FIELD_POPULATION_' . $i, defined('CUSTOM_FIELD_POPULATION_' . $i) ? constant('CUSTOM_FIELD_POPULATION_' . $i) : '', SET_FORM_FIELD_HEIGHT);
        $fields[$prefix . TXT_583] = $dynamicValues;
        $optionArray = array('creator', 'owner', 'creator_owner', 'creator_groups', 'owner_groups', 'creator_owner_groups', 'groups');
        $displayNameArray = array(TXT_587, TXT_588, TXT_589, TXT_590, TXT_591, TXT_592, TXT_593);
        // Get list of existing email notification actions defined
        $columnArray = array('action_id', 'action_name');
        $condition = "WHERE package_function = 'SendEmail' AND enabled = 'Yes'";
        //exclude these action types as they are this action package	$sql = Database::sqlSelect('action_definitions', $columnArray,$condition);
        $sql = Database::sqlSelect('action_definitions', $columnArray, $condition);
        $result = Database::query($sql, DSN, SET_SHOW_SQL);
        unset($actionIDArray);
        unset($actionNameArray);
        while ($row = Database::fetchArray($result)) {
            $actionIDArray[] = $row['action_id'];
            $actionNameArray[] = $row['action_name'];
        }

        $fields[$prefix . TXT_573] = RenderViews::buildSelectDropdown('ACTION_NAME_CREATE_' . $i, $actionIDArray ?? [], $actionNameArray ?? [], defined('ACTION_NAME_CREATE_' . $i) ? constant('ACTION_NAME_CREATE_' . $i) : '');
        $fields[$prefix . TXT_574] = RenderViews::buildSelectDropdown('ACTION_NAME_UPDATE_' . $i, $actionIDArray ?? [], $actionNameArray ?? [], defined('ACTION_NAME_UPDATE_' . $i) ? constant('ACTION_NAME_UPDATE_' . $i) : '');
        $i++;
    }

    define('BODY_CONTENT', RenderViews::buildForm(
        TXT_565,
        'index.php?controller=administration_settings&option=update_email_settings',
        $fields,
        [RenderViews::buildFormButton('submit', 'submit_button', TXT_56)]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
    case 'adlexone_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SETTINGS);
        showAdlexoneSettings();
        break;
    case 'update_adlexone_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SETTINGS);
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'adlexone_settings.json', $_POST);
        break;
    case 'server_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        showServerSettings();
        break;
    case 'update_server_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'server_settings.json', $_POST);
        break;
    case 'data_source_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        showDataSourceSettings();
        break;
    case 'update_data_source_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'data_source_settings.json', $_POST);
        break;
    case 'data_sharing_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        showDataSharingSettings();
        break;
    case 'update_data_sharing_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'data_sharing_settings.json', $_POST);
        break;
    case 'ldap_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        showLDAPSettings();
        break;
    case 'update_ldap_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'ldap_settings.json', $_POST);
        break;
    case 'autologon_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SETTINGS);
        showAutologonSettings();
        break;
    case 'update_autologon_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SETTINGS);
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'autologon_settings.json', $_POST);
        break;
    case 'advanced_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        showAdvancedSettings();
        break;
    case 'update_advanced_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'advanced_settings.json', $_POST);
        break;
    case 'email_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        showEmailSettings();
        break;
    case 'update_email_settings' :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        SharedMethods::saveSettingsToJson(SET_CONFIGURATION_PATH . 'email_settings.json', $_POST);
        break;
    default :
        RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_SYSTEM);
        RenderViews::buildResponse('Invalid Option', 'index . php ? controller = administration_main & subcontroller = administration_settings & option = adlexone_settings');
        break;
}