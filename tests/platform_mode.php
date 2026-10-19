<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

\Adlexone\Platform\Runtime::define(rtrim((string) ($argv[1] ?? ''), '/\\') . DIRECTORY_SEPARATOR);
echo \Adlexone\Platform\Runtime::mode();
