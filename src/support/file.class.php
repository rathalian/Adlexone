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
class File
{


    public static function getList($dir, $name)
    {
        $d = opendir('$dir');
        while (($f = readdir($d)) !== false) {
            if (is_file($f) and (preg_match("/$name/", $f))) {
                $List[] = $f;
                list ($t, $n) = explode("-", $f);
                $rnames[] = $n;
            }
        }
        sort($List);
        return $List;
    }

    /**
     * File::parseFile()
     *
     * @param mixed $fileName Full path to file to parse
     * @return Array created from file
     */
    public static function parseFile($fileName)
    {
        if (file_exists($fileName)) {
            $array = array();
            $handle = fopen($fileName, "r");
            while (($data = fgetcsv($handle, 1000, "="))) {
                if (!stristr($data[0], ';')) {
                    $array[trim($data[0])] = trim($data[1]);
                }
            }
        }
        fclose($handle);

        return $array;
    }

//    public static function splitFileName($file)
//    {
//        return $splitName;
//
//    }

    public static function uploadItemAttachment($maximumSize, $inputName, $time, $uploadDirectory)
    {
        $shuffledTime = str_shuffle($time);
        // Lets fix up whitespace re linux and browser incompatibilities
        $fixedName = preg_replace('/[^a-zA-Z0-9\.\$\%\'\`\-\@\{\}\~\!\#\(\)\&\_\^]/', '', str_replace(array(' ', '%20', '\'', '"'), array('_', '_', '_', '_'), basename($_FILES[$inputName]['name'])));
        $uploadfile = $uploadDirectory . $shuffledTime . '_' . $fixedName;
        //$fileName = basename($_FILES[$inputName]['name']);
        $fileSize = $_FILES[$inputName]['size'];
        $fileType = $_FILES[$inputName]['type'];
        $tmpName = $_FILES[$inputName]['tmp_name'];
        $error = $_FILES[$inputName]['error'];
        $fileExtension = strrchr($_FILES[$inputName]['name'], '.');
        // If no error exists lets go (0 means OK)
        if ($_FILES[$inputName]['error'] == 0) {
            $result = move_uploaded_file($_FILES[$inputName]['tmp_name'], $uploadfile);
            if (!$result) {
                echo RenderViews::showResponse('There is a problem moving the uploaded file to ' . $uploadDirectory . '.  Please check the directory exists and permissions are set as per the install documentation.');
                die;
            }
        } else {
            // Delete temp file if any, and display errors.
            if ($_FILES[$inputName]['tmp_name'] != '') {
                unlink($_FILES[$inputName]['tmp_name']);
            }
            switch ($_FILES[$inputName]['error']) {
                case '1' :
                    echo RenderViews::showResponse('Attachment ' . $_FILES[$inputName]['name'] . ' exceeds the maximum allowed file size as set in php.ini. Please contact your system admin.');
                    die;
                    break;
                case '2' :
                    echo RenderViews::showResponse('Attachment ' . $_FILES[$inputName]['name'] . ' exceeds the maximum allowed file size as set in your webservers configuration file. Please contact your system admin.');
                    die;
                    break;
                case '3' :
                    echo RenderViews::showResponse('The file was only partially uploaded. This could be the result of your connection being dropped in the middle of the upload.');
                    die;
                case '4' :
                    echo RenderViews::showResponse('You did not upload anything/* ... */ Please go back and select a file to upload.');
                    die;
                    break;
                case '5' :
                    echo RenderViews::showResponse('The file is 0 kilobytes in length. Is it an empty file?');
                    break;
                    die;
            }
        }
        // return file array with new file name included
        $_FILES[$inputName]['time_name'] = $shuffledTime . '_' . $fixedName;
        // all normal $_FILES fields available in array plus the time_name key we set
        return $_FILES[$inputName];
    }
}