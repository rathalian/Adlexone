<?php
declare(strict_types=1);

namespace Adlexone\Platform;

/**
 * Where a single site keeps its database, settings, and uploads, and whether
 * the web server's document root matches a layout Adlexone can protect.
 */
final class InstallLayout
{
    public const PACKAGE = 'package';

    public const PUBLIC_FOLDER = 'public';

    public const UNKNOWN = 'unknown';

    public static function detect(string $install, ?string $documentRoot = null): string
    {
        $root = self::canonical($install);
        $public = self::canonical(rtrim($install, '/\\') . DIRECTORY_SEPARATOR . 'public');
        $doc = self::canonical($documentRoot ?? (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($doc === '') {
            return self::UNKNOWN;
        }
        if ($public !== '' && $doc === $public) {
            return self::PUBLIC_FOLDER;
        }
        if ($root !== '' && $doc === $root) {
            return self::PACKAGE;
        }

        return self::UNKNOWN;
    }

    public static function placementMessage(string $install, ?string $documentRoot = null): string
    {
        $detected = self::detect($install, $documentRoot);
        $doc = self::canonical($documentRoot ?? (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($detected === self::PACKAGE) {
            return 'This server\'s document root is ' . $doc . '. The package is in that folder. Choose "The package is in the document root", then continue.';
        }
        if ($detected === self::PUBLIC_FOLDER) {
            return 'This server\'s document root is ' . $doc . '. That is the public folder. Choose "The document root is the public folder", then continue.';
        }
        if ($doc === '') {
            return 'This server did not report a document root. Set the document root to the public folder, or unzip Adlexone into the document root, then reload this page.';
        }

        return 'This server\'s document root is ' . $doc . '. That is neither the package nor the public folder. Point it at the public folder, or unzip Adlexone into the document root, then reload this page.';
    }

    public static function suggest(string $install): string
    {
        return rtrim($install, '/\\') . DIRECTORY_SEPARATOR . 'storage';
    }

    public static function pointerFile(string $install): string
    {
        return rtrim($install, '/\\') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'platform.json';
    }

    /**
     * A directory the environment already chose. The page cannot override it.
     *
     * @return array{locked: bool, path: string, shape: string}
     */
    public static function environmentStorage(string $install): array
    {
        $storage = self::envString('INLAY_STORAGE');
        if ($storage !== '') {
            $path = self::resolveInput($install, $storage);

            return [
                'locked' => true,
                'path' => $path ?? rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storage), DIRECTORY_SEPARATOR),
                'shape' => 'children',
            ];
        }
        $data = self::envString('INLAY_DATA');
        if ($data !== '') {
            return [
                'locked' => true,
                'path' => rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $data), DIRECTORY_SEPARATOR),
                'shape' => 'legacy',
            ];
        }

        return ['locked' => false, 'path' => '', 'shape' => 'children'];
    }

    public static function savedStorage(string $install): string
    {
        $file = self::pointerFile($install);
        if (!is_file($file)) {
            return '';
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            return '';
        }
        $storage = trim((string) ($decoded['INLAY_STORAGE'] ?? ''));
        if ($storage === '') {
            return '';
        }

        return self::resolveInput($install, $storage) ?? '';
    }

    /**
     * Paths for this request. Environment wins, then the path recorded at setup,
     * then the original layout beside the code.
     *
     * @return array{config: string, sqlite: string, attachments: string, database_dir: string, root: string, shape: string}
     */
    public static function resolve(string $install): array
    {
        $env = self::environmentStorage($install);
        if ($env['locked'] && $env['path'] !== '') {
            $paths = $env['shape'] === 'legacy' ? self::legacyPaths($env['path']) : self::storagePaths($env['path']);

            return $paths + ['root' => $env['path'], 'shape' => $env['shape']];
        }
        $saved = self::savedStorage($install);
        if ($saved !== '') {
            return self::storagePaths($saved) + ['root' => $saved, 'shape' => 'children'];
        }
        $root = rtrim($install, '/\\');

        return self::legacyPaths($root) + ['root' => $root, 'shape' => 'install'];
    }

    /**
     * @return array{config: string, sqlite: string, attachments: string, database_dir: string}
     */
    public static function storagePaths(string $directory): array
    {
        $directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
        $databaseDir = $directory . 'database' . DIRECTORY_SEPARATOR;

        return [
            'config' => $directory . 'config' . DIRECTORY_SEPARATOR,
            'sqlite' => $databaseDir . 'adlexone.sqlite',
            'attachments' => $directory . 'attachments' . DIRECTORY_SEPARATOR,
            'database_dir' => $databaseDir,
        ];
    }

    /**
     * Older layout: config and storage are both directly inside the directory.
     *
     * @return array{config: string, sqlite: string, attachments: string, database_dir: string}
     */
    public static function legacyPaths(string $directory): array
    {
        $directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
        $databaseDir = $directory . 'storage' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR;

        return [
            'config' => $directory . 'config' . DIRECTORY_SEPARATOR,
            'sqlite' => $databaseDir . 'adlexone.sqlite',
            'attachments' => $directory . 'storage' . DIRECTORY_SEPARATOR . 'attachments' . DIRECTORY_SEPARATOR,
            'database_dir' => $databaseDir,
        ];
    }

    /**
     * Create, read, update, and delete a file as the account that is running PHP.
     * Also creates and removes a folder. When $recordPath is true, checks that
     * the path can be written beside the code so the next visit finds it.
     *
     * @return array{
     *   ok: bool,
     *   message: string,
     *   user: string,
     *   directory: string,
     *   created: bool,
     *   checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>
     * }
     */
    public static function probe(
        string $install,
        string $directory,
        string $placement,
        ?string $documentRoot = null,
        bool $recordPath = true
    ): array {
        $user = Runtime::phpUser();
        $result = [
            'ok' => false,
            'message' => '',
            'user' => $user,
            'directory' => '',
            'created' => false,
            'checks' => [],
        ];
        $absolute = self::absolute($directory);
        if ($absolute === null) {
            $result['message'] = 'Enter a full path. On this server it starts with /. For example, /home/health/adlexone-data.';

            return $result;
        }
        $reason = self::reject($install, $absolute);
        if ($reason !== null) {
            $result['message'] = $reason;
            $result['directory'] = $absolute;

            return $result;
        }
        if (is_file($absolute)) {
            $result['message'] = $absolute . ' is a file. Choose a folder.';
            $result['directory'] = $absolute;

            return $result;
        }
        $created = false;
        if (!is_dir($absolute)) {
            if (!@mkdir($absolute, 0700, true) && !is_dir($absolute)) {
                $result['message'] = 'PHP, running as ' . $user . ', cannot create ' . $absolute . '. Create that folder for ' . $user . ', then check again.';
                $result['directory'] = $absolute;

                return $result;
            }
            $created = true;
        }
        $real = realpath($absolute);
        $dir = $real !== false ? rtrim($real, '/\\') : $absolute;
        $reason = self::reject($install, $dir);
        if ($reason !== null) {
            if ($created) {
                @rmdir($dir);
            }
            $result['message'] = $reason;
            $result['directory'] = $dir;

            return $result;
        }

        $checks = [];
        $token = bin2hex(random_bytes(4));
        $file = $dir . DIRECTORY_SEPARATOR . '.adlexone-probe-' . $token;
        $folder = $dir . DIRECTORY_SEPARATOR . '.adlexone-probe-dir-' . $token;
        try {
            $createOk = @file_put_contents($file, 'create') !== false;
            $checks[] = self::check('create', 'Create', $createOk, $createOk
                ? ''
                : 'PHP, running as ' . $user . ', could not create a file in ' . $dir . '.');
            if (!$createOk) {
                $skip = 'Not tried, because a file could not be created.';
                $checks[] = self::check('read', 'Read', false, $skip, 'skip');
                $checks[] = self::check('update', 'Update', false, $skip, 'skip');
                $checks[] = self::check('delete', 'Delete', false, $skip, 'skip');
            } else {
                $listed = @scandir($dir);
                $readOk = is_array($listed)
                    && in_array(basename($file), $listed, true)
                    && @file_get_contents($file) === 'create';
                $checks[] = self::check('read', 'Read', $readOk, $readOk
                    ? ''
                    : 'PHP, running as ' . $user . ', could not read a file in ' . $dir . '.');
                $updateOk = $readOk
                    && @file_put_contents($file, 'update') !== false
                    && @file_get_contents($file) === 'update';
                $checks[] = self::check('update', 'Update', $updateOk, $updateOk
                    ? ''
                    : 'PHP, running as ' . $user . ', could not change a file in ' . $dir . '.');
                $deleteOk = @unlink($file) && !is_file($file);
                $checks[] = self::check('delete', 'Delete', $deleteOk, $deleteOk
                    ? ''
                    : 'PHP, running as ' . $user . ', could not delete a file in ' . $dir . '.');
            }
            $folderOk = @mkdir($folder, 0700) && is_dir($folder) && @rmdir($folder) && !is_dir($folder);
            $checks[] = self::check('folder', 'Folder', $folderOk, $folderOk
                ? ''
                : 'PHP, running as ' . $user . ', could not create a folder in ' . $dir . '.');
            if ($recordPath) {
                $checks[] = self::probePointer($install, $user);
            }
            $checks[] = self::privateAccess($dir, $documentRoot);
        } finally {
            if (is_file($file)) {
                @unlink($file);
            }
            if (is_dir($folder)) {
                @rmdir($folder);
            }
        }

        $ok = true;
        $failure = '';
        foreach ($checks as $check) {
            if ($check['state'] === 'fail') {
                $ok = false;
                $failure = $check['detail'];
                break;
            }
        }
        $result['ok'] = $ok;
        $result['message'] = $ok
            ? 'PHP, running as ' . $user . ', can create, read, update, and delete files in this folder, and the website cannot read it.'
            : $failure;
        $result['directory'] = $dir;
        $result['created'] = $created;
        $result['checks'] = $checks;

        return $result;
    }

    public static function remember(string $install, string $directory): void
    {
        $absolute = self::absolute($directory);
        if ($absolute === null) {
            throw new \RuntimeException('The storage directory is not a full path.');
        }
        $real = realpath($absolute);
        if ($real !== false) {
            $absolute = rtrim($real, '/\\');
        }
        $file = self::pointerFile($install);
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('PHP, running as ' . Runtime::phpUser() . ', cannot create ' . $dir . '.');
        }
        $json = [];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (!is_array($decoded)) {
                throw new \RuntimeException($file . ' is not valid JSON. Fix that file, then run setup again.');
            }
            $json = $decoded;
        }
        $json['INLAY_STORAGE'] = $absolute;
        $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false || file_put_contents($file, $encoded . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('PHP, running as ' . Runtime::phpUser() . ', cannot write ' . $file . '.');
        }
        @chmod($file, 0600);
        clearstatcache(true, $file);
    }

    public static function absolute(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $windows = preg_match('/^[A-Za-z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $path) === 1;
        if (!$windows && !str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return null;
        }
        $prefix = '';
        $rest = $path;
        if ($windows) {
            $prefix = substr($path, 0, 2);
            $rest = substr($path, 2);
        }
        $clean = [];
        foreach (explode(DIRECTORY_SEPARATOR, $rest) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return null;
            }
            $clean[] = $part;
        }
        if ($clean === []) {
            return null;
        }
        $joined = implode(DIRECTORY_SEPARATOR, $clean);
        if ($windows) {
            return $prefix . DIRECTORY_SEPARATOR . $joined;
        }

        return DIRECTORY_SEPARATOR . $joined;
    }

    public static function canonical(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            return '';
        }
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        if ($path === '') {
            return '';
        }
        $probe = $path;
        $resolved = $path;
        while ($probe !== '' && $probe !== dirname($probe)) {
            if (file_exists($probe)) {
                $real = realpath($probe);
                if ($real !== false) {
                    $suffix = substr($path, strlen(rtrim($probe, DIRECTORY_SEPARATOR)));
                    $resolved = rtrim($real, DIRECTORY_SEPARATOR) . $suffix;
                    break;
                }
            }
            $next = dirname($probe);
            if ($next === $probe) {
                break;
            }
            $probe = $next;
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            $resolved = strtolower($resolved);
        }

        return rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public static function contains(string $parent, string $child): bool
    {
        $parent = self::canonical($parent);
        $child = self::canonical($child);
        if ($parent === '' || $child === '') {
            return false;
        }

        return $child === $parent || str_starts_with($child, $parent . DIRECTORY_SEPARATOR);
    }

    /**
     * @return array{id: string, label: string, ok: bool, detail: string, state: string}
     */
    private static function probePointer(string $install, string $user): array
    {
        $file = self::pointerFile($install);
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return self::check(
                'record',
                'Record path',
                false,
                'PHP, running as ' . $user . ', cannot create ' . $dir . '. That folder stores the storage path in platform.json.'
            );
        }
        $probe = $dir . DIRECTORY_SEPARATOR . '.adlexone-probe-' . bin2hex(random_bytes(4));
        $ok = false;
        try {
            $ok = @file_put_contents($probe, 'ok') !== false
                && @file_get_contents($probe) === 'ok'
                && @unlink($probe)
                && !is_file($probe);
        } finally {
            if (is_file($probe)) {
                @unlink($probe);
            }
        }
        if (!$ok) {
            return self::check(
                'record',
                'Record path',
                false,
                'PHP, running as ' . $user . ', cannot write ' . $file . '. That file stores the storage path for the next visit.'
            );
        }

        return self::check('record', 'Record path', true, '');
    }

    private static function reject(string $install, string $directory): ?string
    {
        $dir = self::canonical($directory);
        $root = self::canonical($install);
        if ($dir === '' || $root === '') {
            return 'Enter a full path. On this server it starts with /. For example, /home/health/adlexone-data.';
        }
        if ($dir === $root) {
            return 'The package folder holds the code. Choose the storage folder inside it, or a folder outside the package.';
        }
        if (self::contains($dir, $root)) {
            return 'That folder contains the Adlexone package. Choose a folder that will hold only the database, settings, and uploads.';
        }
        foreach (['src', 'vendor', 'bootstrap', 'layouts', 'site', 'tests', 'translations', 'config.example', 'bin'] as $name) {
            if (self::contains($root . DIRECTORY_SEPARATOR . $name, $dir)) {
                return 'That folder is part of the application. Choose a different folder.';
            }
        }
        if (self::contains($root . DIRECTORY_SEPARATOR . 'config', $dir)) {
            return 'That folder is where Adlexone records the storage path. Choose a different folder for the database and uploads.';
        }

        return null;
    }

    public static function inWebFolder(string $directory, ?string $documentRoot = null): bool
    {
        $doc = self::canonical($documentRoot ?? (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $dir = self::canonical($directory);
        if ($doc === '' || $dir === '') {
            return false;
        }

        return self::contains($doc, $dir);
    }

    /**
     * A folder inside the web folder is strongly recommended against. Setup
     * still requests a trial file from the website and refuses the folder when
     * that request returns the file. A folder outside the web folder has no
     * public address.
     *
     * @param array<string, mixed>|null $server
     * @param (callable(string): array{reached: bool, status: int, body: string})|null $request
     * @return array{id: string, label: string, ok: bool, detail: string, state: string}
     */
    public static function privateAccess(string $directory, ?string $documentRoot = null, ?array $server = null, ?callable $request = null): array
    {
        $server ??= $_SERVER;
        $doc = self::canonical($documentRoot ?? (string) ($server['DOCUMENT_ROOT'] ?? ''));
        $dir = self::canonical($directory);
        if ($doc === '' || $dir === '' || !self::contains($doc, $dir)) {
            $where = $doc !== '' ? $doc : 'the web folder';

            return self::check('private', 'Private', true, 'This folder is outside the web folder (' . $where . '). The website cannot serve it.');
        }

        $host = trim((string) ($server['HTTP_HOST'] ?? ''));
        if (preg_match('/^[A-Za-z0-9.:\[\]-]+$/', $host) !== 1) {
            return self::check(
                'private',
                'Private',
                false,
                'This folder is inside the web folder (' . $doc . '). That is strongly recommended against, and this request did not include a website address to test. Move the folder outside the web folder.'
            );
        }

        $name = 'adlexone-public-check-' . bin2hex(random_bytes(8)) . '.txt';
        $secret = 'adlexone-public-' . bin2hex(random_bytes(16));
        $file = $dir . DIRECTORY_SEPARATOR . $name;
        $url = self::publicFileUrl($dir, $doc, $name, $server);
        try {
            if (@file_put_contents($file, $secret) === false) {
                return self::check(
                    'private',
                    'Private',
                    false,
                    'This folder is inside the web folder (' . $doc . '). That is strongly recommended against. The trial file could not be written, so Adlexone could not prove the folder is private. Move the folder outside the web folder.'
                );
            }
            $response = $request !== null ? $request($url) : self::httpGet($url);
            if (!is_array($response) || empty($response['reached'])) {
                return self::check(
                    'private',
                    'Private',
                    false,
                    'This folder is inside the web folder (' . $doc . '). That is strongly recommended against. Adlexone could not open ' . $url . ', so it cannot confirm the folder is private. Move the folder outside the web folder and check again.'
                );
            }
            $body = (string) ($response['body'] ?? '');
            if ($secret !== '' && str_contains($body, $secret)) {
                return self::check(
                    'private',
                    'Private',
                    false,
                    'The website returned the trial file at ' . $url . '. A browser can download the database and uploads from this folder. Move the folder outside the web folder.'
                );
            }

            return self::check(
                'private',
                'Private',
                true,
                'A request for ' . $url . ' did not return the trial file. Keeping this folder inside the web folder is still strongly recommended against.'
            );
        } finally {
            if (isset($file) && is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function publicFileUrl(string $directory, string $documentRoot, string $name, array $server): string
    {
        $relative = substr(self::canonical($directory), strlen(self::canonical($documentRoot)));
        $relative = trim(str_replace('\\', '/', $relative), '/');
        $path = '/' . ($relative === '' ? '' : $relative . '/') . $name;
        $https = (!empty($server['HTTPS']) && (string) $server['HTTPS'] !== 'off')
            || (string) ($server['REQUEST_SCHEME'] ?? '') === 'https'
            || strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        return ($https ? 'https' : 'http') . '://' . trim((string) $server['HTTP_HOST']) . $path;
    }

    /**
     * @return array{reached: bool, status: int, body: string}
     */
    private static function httpGet(string $url, int $redirects = 0): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array((string) ($parts['scheme'] ?? ''), ['http', 'https'], true) || ($parts['host'] ?? '') === '') {
            return ['reached' => false, 'status' => 0, 'body' => ''];
        }
        $scheme = (string) $parts['scheme'];
        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $path = (string) ($parts['path'] ?? '/');
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }
        $target = ($scheme === 'https' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($target, $errno, $errstr, 3, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            return ['reached' => false, 'status' => 0, 'body' => ''];
        }
        stream_set_timeout($socket, 3);
        $authority = $host;
        if (!(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $authority .= ':' . $port;
        }
        $request = "GET " . $path . " HTTP/1.0\r\nHost: " . $authority . "\r\nUser-Agent: Adlexone-setup\r\nConnection: close\r\n\r\n";
        fwrite($socket, $request);
        $raw = stream_get_contents($socket, 65536);
        fclose($socket);
        if ($raw === false || $raw === '') {
            return ['reached' => false, 'status' => 0, 'body' => ''];
        }
        $split = explode("\r\n\r\n", $raw, 2);
        $headers = $split[0];
        $body = $split[1] ?? '';
        $status = 0;
        $statusLine = explode("\r\n", $headers, 2)[0];
        if (preg_match('/\s(\d{3})(?:\s|$)/', $statusLine, $match) === 1) {
            $status = (int) $match[1];
        }
        if ($redirects < 1 && in_array($status, [301, 302, 307, 308], true) && preg_match('/^Location:\s*(\S+)/mi', $headers, $location) === 1) {
            $next = self::sameHostUrl($url, $location[1]);
            if ($next !== null) {
                return self::httpGet($next, $redirects + 1);
            }
        }

        return ['reached' => true, 'status' => $status, 'body' => $body];
    }

    private static function sameHostUrl(string $current, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }
        if (str_starts_with($location, '/')) {
            $parts = parse_url($current);
            if (!is_array($parts) || ($parts['host'] ?? '') === '') {
                return null;
            }
            $scheme = (string) ($parts['scheme'] ?? 'http');
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';

            return $scheme . '://' . $parts['host'] . $port . $location;
        }
        $next = parse_url($location);
        $now = parse_url($current);
        if (!is_array($next) || !is_array($now)) {
            return null;
        }
        if (strcasecmp((string) ($next['host'] ?? ''), (string) ($now['host'] ?? '')) !== 0) {
            return null;
        }
        if (!in_array((string) ($next['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        return $location;
    }

    private static function resolveInput(string $install, string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        if (!self::isAbsolutePath($path)) {
            $path = rtrim($install, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        }

        return self::absolute($path);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private static function envString(string $name): string
    {
        $value = getenv($name);
        if ($value === false) {
            return '';
        }

        return trim($value);
    }

    /**
     * @return array{id: string, label: string, ok: bool, detail: string, state: string}
     */
    private static function check(string $id, string $label, bool $ok, string $detail, string $state = ''): array
    {
        if ($state === '') {
            $state = $ok ? 'pass' : 'fail';
        }

        return [
            'id' => $id,
            'label' => $label,
            'ok' => $state === 'pass',
            'detail' => $detail,
            'state' => $state,
        ];
    }
}
