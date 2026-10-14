<?php
declare(strict_types=1);

namespace Adlexone\support;

/**
 * Saves an uploaded file into the attachments directory.
 */
class AttachmentUpload
{
    /**
     * @return array<string, mixed>
     */
    public static function uploadItemAttachment($maximumSize, $inputName, $time, $uploadDirectory)
    {
        $shuffledTime = str_shuffle((string) $time);
        $fixedName = preg_replace('/[^a-zA-Z0-9\.\$\%\'\`\-\@\{\}\~\!\#\(\)\&\_\^]/', '', str_replace([' ', '%20', '\'', '"'], ['_', '_', '_', '_'], basename($_FILES[$inputName]['name'])));
        $uploadfile = $uploadDirectory . $shuffledTime . '_' . $fixedName;
        if ($_FILES[$inputName]['error'] == 0) {
            $result = move_uploaded_file($_FILES[$inputName]['tmp_name'], $uploadfile);
            if (!$result) {
                echo RenderViews::showResponse('There is a problem moving the uploaded file to ' . $uploadDirectory . '.  Please check the directory exists and permissions are set as per the install documentation.');
                die;
            }
        } else {
            if ($_FILES[$inputName]['tmp_name'] != '') {
                unlink($_FILES[$inputName]['tmp_name']);
            }
            switch ($_FILES[$inputName]['error']) {
                case '1':
                    echo RenderViews::showResponse('Attachment ' . $_FILES[$inputName]['name'] . ' exceeds the maximum allowed file size as set in php.ini. Please contact your system admin.');
                    die;
                case '2':
                    echo RenderViews::showResponse('Attachment ' . $_FILES[$inputName]['name'] . ' exceeds the maximum allowed file size as set in your webservers configuration file. Please contact your system admin.');
                    die;
                case '3':
                    echo RenderViews::showResponse('The file was only partially uploaded. This could be the result of your connection being dropped in the middle of the upload.');
                    die;
                case '4':
                    echo RenderViews::showResponse('You did not upload anything. Please go back and select a file to upload.');
                    die;
                case '5':
                    echo RenderViews::showResponse('The file is 0 kilobytes in length. Is it an empty file?');
                    break;
            }
        }
        $_FILES[$inputName]['time_name'] = $shuffledTime . '_' . $fixedName;
        return $_FILES[$inputName];
    }
}
