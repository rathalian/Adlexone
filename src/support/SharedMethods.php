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

namespace Adlexone\support {

    /**
     * SharedMethods
     *
     * A class containing methods shared across the application
     */
    class SharedMethods
    {

        /** Load settings from a JSON file and define them as constants
         *
         * @param string $jsonPath Full path to JSON settings file
         * @throws \Exception if the file cannot be read or parsed
         */
        public static function loadJsonSettingsToConstants($jsonPath): void
        {
            if (!file_exists($jsonPath)) {
                throw new \Exception("Settings file not found: $jsonPath");
            }
            $settings = json_decode(file_get_contents($jsonPath), true);
            if (!is_array($settings)) {
                throw new \Exception("Invalid settings format in: $jsonPath");
            }
            foreach ($settings as $key => $value) {
                if (!defined($key)) {
                    define($key, $value);
                }
            }
        }

/**
   * Save settings from an associative array to a JSON file.
   *
   * This method processes an associative array of settings by trimming double quotes
   * from string values, encoding the array as a JSON string, and writing it to the
   * specified file path. It provides user feedback on success or failure using
   * the `RenderViews::buildResponse` method.
   *
   * @param string $jsonPath The full path to the JSON settings file where the settings will be saved.
   * @param array $settingsArray An associative array of settings to save. Each key-value pair represents a setting.
   *                              String values will have surrounding double quotes trimmed.
   * @return bool Returns true if the operation succeeds, or false if encoding or writing fails.
   *
   * @throws \Exception If the provided path is invalid or inaccessible.
   *
   * @example
   * $settings = ['site_name' => 'My Website', 'debug_mode' => true];
   * SharedMethods::saveSettingsToJson('/path/to/settings.json', $settings);
   */
  public static function saveSettingsToJson(string $jsonPath, array $settingsArray): bool
  {
      unset($_POST['submit_button']);
      // Iterate through the settings array and trim double quotes from string values
      foreach ($settingsArray as &$value) {
          if (is_string($value)) {
              $value = trim($value, '"');
          }
      }

      // Encode the settings array as a JSON string with pretty print and unescaped slashes
      $json = json_encode($settingsArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($json === false) {
          // Handle JSON encoding errors and render a failure message
          $err = json_last_error_msg();
          RenderViews::buildResponse("Failed to encode settings to JSON: {$err}");
          return false;

      } elseif (file_put_contents($jsonPath, $json) === false) {
          // Handle file writing errors and render a failure message
          RenderViews::buildResponse("Failed to write settings file: {$jsonPath}");
          return false;

      } else {
          // Render a success message if the operation completes successfully
          RenderViews::buildResponse(TXT_201);
          return true;
      }
  }
        /**
         * Write an associative array to an ini file
         *
         * @param string $path Full path to file
         * @param string $fileHeader Header to add to file
         * @param array $associativeArray Associative array to write
         * @return boolean True on success, false on failure
         */
        public static function writeFileFromArray($path, $fileHeader, $associativeArray): bool
        {
            $content = $fileHeader . "\n;\n";

            foreach ($associativeArray as $key => $item) {
                $content .= is_array($item)
                    ? "\n[{$key}]\n" . self::formatArrayContent($item)
                    : self::formatContent($key, $item);
            }

            if (!($handle = fopen($path, 'w'))) {
                trigger_error("Failed to open file: {$path}", E_USER_WARNING);
                return false;
            }

            if (!fwrite($handle, trim($content))) {
                trigger_error("Failed to write to file: {$path}", E_USER_WARNING);
                fclose($handle);
                return false;
            }

            fclose($handle);
            return true;
        }

        /** Format array content for ini file
         *
         * @param array $array Array to format
         * @return string Formatted content
         */
        private static function formatArrayContent(array $array): string
        {
            $content = '';
            foreach ($array as $key => $value) {
                $content .= self::formatContent($key, $value);
            }
            return $content;
        }

        /**Format content for ini file
         *
         * @param string $key Key
         * @param string $value Value
         * @return string Formatted content
         */
        private static function formatContent($key, $value): string
        {
            $value = html_entity_decode($value, ENT_COMPAT, 'UTF-8');
            return is_numeric($value)
                ? "{$key} = {$value}\n"
                : "{$key} = \"{$value}\"\n";
        }


        /**
         * Load constants from an ini file
         *
         * @param string $iniFileName Full path to ini file
         * @return array|null Array of constants or null on failure
         */
        public static function loadConstantFromIni($iniFileName): ?array
        {
            $constants = parse_ini_file($iniFileName);
            if (!$constants) {
                trigger_error("Unable to load constants file: $iniFileName", E_USER_WARNING);
                return null;
            }

            foreach ($constants as $key => $value) {
                if (!define($key, $value)) {
                    trigger_error("Failed to define constant: $key", E_USER_WARNING);
                }
            }
            return $constants;
        }

//        /**
//         * Creates a distinct ID
//         *
//         * @return integer 14 digit distict id
//         */
////        public static function DistinctID(): int|string
//        {
//            // Combine current epoch time and 2 random numbers to create a
//            // 12 digit distinct id
//            return (time() . rand(10, 20) . rand(30, 40));
//        }

/**
          * Check if an email address is valid
          *
          * @param mixed $address Email Address
          * @return bool
          */
         public static function validEmailAddress($address): bool
         {
             return (bool)preg_match(
                 '/^[-!#$%&\'*+\\.\/0-9=?A-Z^_`a-z{|}~]+@[-!#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.[-!#$%&\'*+\\.\/0-9=?A-Z^_`a-z{|}~]+$/',
                 $address
             );
         }
//        /**
//         * @param string $text Text for conversion
//         */
//        public static function stripScripts($text): string
//        {
//            return htmlentities($text, ENT_COMPAT, "UTF-8");
//        }

//        /**
//         * SharedMethods::stripScriptsRecursive()
//         *
//         * @param array $array Array
//         * @return array stripped of html scripts
//         */
//        public static function stripScriptsRecursive($array): array
//        {
//            $newArray = [];
//            foreach ($array as $key => $value) {
//                $value = SharedMethods::stripScripts($value);
//                $newArray[$key] = $value;
//            }
//            return $newArray;
//        }
//        /**
//         * SharedMethods::addslashesRecursive()
//         *
//         * @param array $arr Array
//         * @param string $databaseType Database type
//         * @return Array with slashes added (database escaping)
//         */
////        public static function addslashesRecursive($arr): array
//        {
//            if (is_array($arr)) {
//                foreach ($arr as $index => $val) {
//                    $arr[$index] = SharedMethods::addslashesRecursive($val);
//                }
//                return $arr;
//            } else {
//                return SharedMethods::customAddSlashes($arr);
//            }
//        }
//        /**
//         * SharedMethods::stripMagicQuotes()
//         *
//         * @param array $arr Array
//         * @return Array with escaping remove where magic quotes is enabled
//         */
//        public static function stripMagicQuotes($arr): array
//        {
//            foreach ($arr as $k => $v) {
//                if (is_array($v)) {
//                    $arr[$k] = SharedMethods::stripMagicQuotes($v);
//                } else {
//                    $arr[$k] = stripslashes($v);
//                }
//            }
//            return $arr;
//        }

//        /**
//         * Unset session array
//         *
//         * @param array $sessionArray SESSION array
//         */
//        public static function unsetSession($sessionArray): void
//        {
//            foreach($sessionArray as $a) {
//                $_SESSION[$a] = '';
//                unset($_SESSION[$a]);
//            }
//        }
//        /**
//         * Converts minutes to hours and minutes
//         *
//         * @param $minutes Minutes
//         */
//        public static function minutesToHours($minutes): string
//        {
//            if ($minutes < 0){
//                $min = Abs($minutes);
//            }else{
//                $min = $minutes;
//            }
//            $iHours = floor($min / 60);
//            $minutes = ($min - ($iHours * 60)) / 100;
//            $tHours = $iHours + $minutes;
//            if ($minutes < 0){
//                $tHours = $tHours * (-1);
//            }
//            $aHours = explode(".", $tHours);
//            $iHours = $aHours[0];
//            if (empty($aHours[1])){
//                $aHours[1] = "00";
//            }
//            $minutes = $aHours[1];
//            if (strlen($minutes) < 2){
//                $minutes = $minutes ."0";
//            }
//            $tHours = round($iHours,-2) .":". $minutes;
//            return $tHours;
//        }
    }
}