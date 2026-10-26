<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\support\AttachmentUpload;

$failed = 0;

function check(bool $ok, string $message): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

check(AttachmentUpload::blockedName('notes.pdf') === false, 'a pdf name is allowed');
check(AttachmentUpload::blockedName('photo.PNG') === false, 'a png name is allowed');
check(AttachmentUpload::blockedName('shell.php') === true, 'a php name is refused');
check(AttachmentUpload::blockedName('notes.php.pdf') === true, 'a php type hidden before the last dot is refused');
check(AttachmentUpload::blockedName('run.phar.jpg') === true, 'a phar type hidden before the last dot is refused');
check(AttachmentUpload::blockedName("notes.php\0.pdf") === true, 'a null in the name is refused');

if ($failed > 0) {
    fwrite(STDERR, $failed . " checks failed\n");
    exit(1);
}
echo "all checks passed\n";
