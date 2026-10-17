<?php
declare(strict_types=1);

namespace Adlexone\support;

use Adlexone\Http\Page;
use Adlexone\Http\View;

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
                View::respond('There is a problem moving the uploaded file to ' . $uploadDirectory . '.  Please check the directory exists and permissions are set as per the install documentation.');
                Page::current()->send();
                exit;
            }
        } else {
            if ($_FILES[$inputName]['tmp_name'] != '') {
                unlink($_FILES[$inputName]['tmp_name']);
            }
            switch ($_FILES[$inputName]['error']) {
                case '1':
                    View::respond('Attachment ' . $_FILES[$inputName]['name'] . ' exceeds the maximum allowed file size as set in php.ini. Please contact your system admin.');
                    Page::current()->send();
                    exit;
                case '2':
                    View::respond('Attachment ' . $_FILES[$inputName]['name'] . ' exceeds the maximum allowed file size as set in your webservers configuration file. Please contact your system admin.');
                    Page::current()->send();
                    exit;
                case '3':
                    View::respond('The file was only partially uploaded. This could be the result of your connection being dropped in the middle of the upload.');
                    Page::current()->send();
                    exit;
                case '4':
                    View::respond('You did not upload anything. Please go back and select a file to upload.');
                    Page::current()->send();
                    exit;
                case '5':
                    View::respond('The file is 0 kilobytes in length. Is it an empty file?');
                    break;
            }
        }
        $_FILES[$inputName]['time_name'] = $shuffledTime . '_' . $fixedName;
        return $_FILES[$inputName];
    }
}
