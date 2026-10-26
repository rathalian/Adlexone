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
    private const ABSOLUTE_MAX_MB = 256;

    /** @var list<string> */
    private const BLOCKED = [
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht',
        'cgi', 'pl', 'exe', 'sh', 'shtml', 'htaccess',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function uploadItemAttachment($maximumSize, $inputName, $time, $uploadDirectory)
    {
        if (!isset($_FILES[$inputName]) || !is_array($_FILES[$inputName]) || ($_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            View::respond('No file was chosen. Go back and choose a file to upload.');
            Page::current()->send();
            exit;
        }
        $maximumSize = (int) $maximumSize;
        if ($maximumSize <= 0 || $maximumSize > self::ABSOLUTE_MAX_MB) {
            $maximumSize = self::ABSOLUTE_MAX_MB;
        }
        $maximum = $maximumSize * 1000000;
        if ((int) ($_FILES[$inputName]['size'] ?? 0) > $maximum) {
            View::respond('The file is larger than the maximum attachment size of ' . $maximumSize . ' MB.');
            Page::current()->send();
            exit;
        }
        $original = basename((string) ($_FILES[$inputName]['name'] ?? ''));
        if (self::blockedName($original)) {
            View::respond('This file type cannot be uploaded.');
            Page::current()->send();
            exit;
        }
        $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? '';
        $base = (string) pathinfo($original, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]/', '', $base) ?? '';
        if ($base === '') {
            $base = 'file';
        }
        $base = substr($base, 0, 80);
        $storedName = bin2hex(random_bytes(8)) . '_' . $base . ($ext !== '' ? '.' . $ext : '');
        $uploadfile = $uploadDirectory . $storedName;
        if ($_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($uploadDirectory) && !@mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
                View::respond('The attachments folder ' . $uploadDirectory . ' does not exist and could not be created.');
                Page::current()->send();
                exit;
            }
            $result = move_uploaded_file($_FILES[$inputName]['tmp_name'], $uploadfile);
            if (!$result) {
                View::respond('There is a problem moving the uploaded file to ' . $uploadDirectory . '.  Please check the directory exists and permissions are set as per the install documentation.');
                Page::current()->send();
                exit;
            }
            if (self::blockedContents($uploadfile)) {
                unlink($uploadfile);
                View::respond('This file type cannot be uploaded.');
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
                default:
                    View::respond('The file could not be uploaded. The server has no usable temporary folder or cannot write to disk.');
                    Page::current()->send();
                    exit;
            }
        }
        $_FILES[$inputName]['time_name'] = $storedName;
        return $_FILES[$inputName];
    }

    /**
     * True when the name is an executable type, including a type hidden before the last dot.
     */
    public static function blockedName(string $original): bool
    {
        if (str_contains($original, "\0")) {
            return true;
        }
        $parts = preg_split('/[^a-z0-9]+/', strtolower($original)) ?: [];
        foreach ($parts as $part) {
            if ($part !== '' && in_array($part, self::BLOCKED, true)) {
                return true;
            }
        }

        return false;
    }

    private static function blockedContents(string $path): bool
    {
        $head = (string) file_get_contents($path, false, null, 0, 64);
        if (preg_match('/^\s*<\?(php|=)/i', $head) === 1) {
            return true;
        }

        return str_starts_with($head, "\x7fELF");
    }
}
