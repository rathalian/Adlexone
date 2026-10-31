<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Html\Html;

/**
 * Plain-language placement and permission notes for setup and the operator screen.
 */
final class HelpText
{
    /**
     * @param array<string, string> $paths
     */
    public static function customer(array $paths): string
    {
        $user = $paths['php_user'] ?? Runtime::phpUser();
        $config = $paths['config'] ?? '';
        $sqlite = $paths['sqlite'] ?? '';
        $attachments = $paths['attachments'] ?? '';
        $databaseDir = $paths['database_dir'] ?? dirname($sqlite);
        $public = $paths['public'] ?? '';
        $install = $paths['install'] ?? '';

        $checks = self::writableLines([
            'Settings' => $config,
            'Database folder' => $databaseDir,
            'Attachments' => $attachments,
        ]);

        return self::article('Where this site keeps its data', [
            self::p('The SQLite database, the JSON settings, and uploaded files stay off the public folder. A browser must not be able to download them by guessing a URL.'),
            self::facts([
                'Code' => $install,
                'Document root' => $public,
                'Settings' => $config,
                'SQLite database' => $sqlite,
                'Uploaded files' => $attachments,
                'PHP is running as' => $user,
            ]),
            $checks,
            self::h('Permissions'),
            self::p('The account that runs PHP must own these folders and be able to create files in them. Recommended modes:'),
            self::list([
                'Directories: 700. The owner can read, write, and enter. Nobody else can.',
                'The SQLite file: 600. The owner can read and write. Nobody else can.',
                'JSON files that hold an SMTP password or an OAuth client secret: 600 as well.',
            ]),
            self::p('On Linux, as a user who may change ownership:'),
            self::pre(self::commands($user, [$config, $databaseDir, $attachments], [$sqlite])),
            self::p('Use the PHP user shown above, not root. If root owns the folders, setup cannot write the database and this page stays in place.'),
            self::h('Files SQLite creates beside the database'),
            self::p('While the site is open, SQLite may add ' . self::code(basename($sqlite) . '-wal') . ' and ' . self::code(basename($sqlite) . '-shm') . ' in the same folder. They are part of the live database. Copy them together with the main file if you take a manual backup, or use the operator backup, which folds them back into the main file first.'),
            self::h('Shared hosting'),
            self::p('Unzip Adlexone outside the web folder, for example in the account home as adlexone/. Point the domain’s document root at adlexone/public. If the host only lets you pick a folder under public_html, pick adlexone/public. Leave config and storage next to public, not inside it.'),
            self::p('If the host cannot change the document root, unzip Adlexone into the document root itself. The .htaccess file there refuses requests for config, storage, and code, and only index.php runs. This needs Apache with mod_rewrite and AllowOverride All.'),
            self::h('Secrets'),
            self::p('SMTP passwords and OAuth client secrets are written into the settings folder above when an administrator saves them later. Leave the password field blank on that screen to keep the current value. Do not commit that folder or the SQLite file to git.'),
            self::h('Setup token'),
            self::p('Opening this page writes a setup token beside the database, at ' . self::code(SetupToken::path()) . '. Enter that value in the form. The page does not show it. The file is removed when the first administrator is created.'),
            self::h('First sign-in'),
            self::p('The first administrator defaults to username ' . Html::e(DefaultAccount::USERNAME) . ' and password ' . Html::e(DefaultAccount::PASSWORD) . '.'),
        ]);
    }

    /**
     * @param array<string, string> $paths
     */
    public static function operator(array $paths): string
    {
        $user = $paths['php_user'] ?? Runtime::phpUser();
        $stores = $paths['stores'] ?? '';
        $platformDb = $paths['platform_db'] ?? '';
        $operatorHost = $paths['operator_host'] ?? '';
        $install = $paths['install'] ?? '';
        $public = $paths['public'] ?? '';
        $platformJson = rtrim($install, '/\\') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'platform.json';

        $storeExample = rtrim($stores, '/\\') . DIRECTORY_SEPARATOR . 'health' . DIRECTORY_SEPARATOR;

        return self::article('How customer stores are kept apart', [
            self::p('This screen is the operator. It is a separate login in a separate database. It is not a user inside a customer site, and it does not appear in their user list or their backup. The default username is ' . Html::e(DefaultAccount::USERNAME) . '. On a local address the password field is filled with the local default. On a public address, choose a password of at least 12 characters.'),
            self::p('Each customer gets one folder. Their people, records, settings, and uploads stay in that folder. One request opens one SQLite file.'),
            self::facts([
                'Code' => $install,
                'Document root' => $public,
                'Operator address' => $operatorHost !== '' ? $operatorHost : '(not set)',
                'Operator database' => $platformDb,
                'Customer folders' => $stores,
                'Platform settings file' => $platformJson,
                'PHP is running as' => $user,
            ]),
            self::h('What one customer folder contains'),
            self::list([
                self::code($storeExample . 'adlexone.sqlite') . ' — their database. Mode 600.',
                self::code($storeExample . 'config' . DIRECTORY_SEPARATOR) . ' — their settings, including SMTP and OAuth secrets. Mode 700 on the folder, 600 on the JSON files.',
                self::code($storeExample . 'attachments' . DIRECTORY_SEPARATOR) . ' — their uploaded files. Mode 700.',
            ]),
            self::p('The operator database holds your username and the list of customer addresses. It does not hold their records. Keep it beside the stores folder, not inside public and not inside a customer folder.'),
            self::h('Permissions'),
            self::p('PHP is running as ' . self::code($user) . '. That account must be able to create the stores folder and the operator database. Recommended:'),
            self::pre(self::commands($user, [dirname($platformDb), $stores], [$platformDb])),
            self::p('SQLite also writes -wal and -shm files next to each database while it is in use. Backup on this screen checkpoints those into the main file, then downloads the folder.'),
            self::h('Turning SaaS mode on'),
            self::p('Open ' . self::code('./?host=install') . ' on a copy that is not in SaaS mode yet and has no administrator. ' . self::code('./?install=host') . ' is the same page. That installer writes INLAY_STORES and INLAY_OPERATOR_HOST into ' . self::code('config/platform.json') . ' next to the code. Environment values win over the file. Leave both empty for a single self-hosted site with no operator screen.'),
            self::p('Open ' . self::code('./?host=manage') . ' on any address this server answers to reach the customer sites screen. The operator hostname opens that screen with no query. The operator password is still required.'),
            self::h('Creating a customer'),
            self::p('Choose a short slug, the hostname they will use, and a display name. Leave the admin password blank if they should open that hostname and finish setup themselves. The setup page they see names their database, settings folder, and attachments folder, and it repeats the permission commands. Fill in the admin username and password here if you are creating the first account for them.'),
            self::h('Changing a site'),
            self::p('Open the site from the list. You can change the display name, the hostname, and the contact email. The slug is the folder name and it stays as it is. The display name is written to that site’s settings. When the hostname changes, that site’s public URL is updated. The contact email stays on this list.'),
            self::h('Usage and cost'),
            self::p('Measure usage reads each site’s folder and database, then stores the figures in the operator database. The list uses that measurement. It does not open every customer database on each view.'),
            self::p('The rate is a monthly amount, an amount per person, and an amount per gigabyte, kept in the operator database. A gigabyte is 1,024 MB. The figure uses the folder total. There is no invoice. Clear the rate and cost is not calculated.'),
            self::h('Suspend, backup, delete'),
            self::p('Suspend leaves every file in place and stops sign-in. Resume turns the site back on. Backup downloads their SQLite file, settings, and attachments. Delete removes the folder from disk after you type the slug. Delete is permanent.'),
        ]);
    }

    /**
     * @param array<string, string> $paths
     */
    public static function hostInstall(array $paths, string $stores = '', string $platformDb = ''): string
    {
        $user = $paths['php_user'] ?? Runtime::phpUser();
        $install = $paths['install'] ?? '';
        $public = $paths['public'] ?? '';
        $platformJson = rtrim($install, '/\\') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'platform.json';
        $dirs = [];
        if ($stores !== '') {
            $dirs[] = $stores;
        }
        if ($platformDb !== '') {
            $dirs[] = dirname($platformDb);
        }

        return self::article('Hosting customer sites', [
            self::p('This installer turns this copy of Adlexone into a host of customer sites. It is a different page from the self-hosted installer. A customer finishing their own hostname is not this page.'),
            self::p('There are four steps. The document root, the stores directory, and the operator hostname are saved together. The operator account is created after that, on the operator hostname.'),
            self::facts([
                'Code' => $install,
                'Document root' => $public,
                'Platform settings file' => $platformJson,
                'Stores directory' => $stores !== '' ? $stores : '/home/health/adlexone-stores',
                'Operator database' => $platformDb !== '' ? $platformDb : '/home/health/platform.sqlite',
                'PHP is running as' => $user,
            ]),
            self::h('Stores directory'),
            self::p('Each customer gets one folder under the stores directory. For example, ' . self::code('/home/health/adlexone-stores/health/') . '. The directory has to sit outside the website. PHP, running as ' . self::code($user) . ', must be able to create, read, update, and delete files there. The check removes its trial files before the page answers.'),
            self::h('Operator database'),
            self::p('The operator login and the list of sites are stored in platform.sqlite beside the stores directory, not inside a customer folder and not inside the website. Directories are mode 700. The database file is mode 600.'),
            $dirs === [] ? '' : self::pre(self::commands($user, $dirs, $platformDb !== '' ? [$platformDb] : [])),
            self::h('Operator hostname'),
            self::p('For example, operate.example.com. Point that name at this server. It must not be a customer hostname. Environment values for INLAY_STORES and INLAY_OPERATOR_HOST win over the file this step writes.'),
        ]);
    }

    /**
     * @param array<string, string> $paths
     */
    public static function suspended(array $paths): string
    {
        return self::article('This site is turned off', [
            self::p('The operator suspended this address. Nothing was deleted.'),
            self::facts([
                'SQLite database' => $paths['sqlite'] ?? '',
                'Settings' => $paths['config'] ?? '',
                'Uploaded files' => $paths['attachments'] ?? '',
            ]),
            self::p('The operator can resume the site from ' . self::code($paths['operator_host'] ?? 'the operator host') . '. Until then this address stays on this page.'),
        ]);
    }

    /**
     * @param array<string, string> $paths
     */
    public static function unknown(array $paths, string $notice): string
    {
        $blocks = [];
        if ($notice !== '') {
            $blocks[] = self::p($notice);
        }
        $blocks[] = self::p('Customer sites answer only on a hostname the operator has registered. This address is not one of them.');
        $blocks[] = self::facts([
            'Operator address' => ($paths['operator_host'] ?? '') !== '' ? $paths['operator_host'] : '(not set)',
            'Customer folders' => ($paths['stores'] ?? '') !== '' ? $paths['stores'] : '(single site, no stores directory)',
            'Operator database' => $paths['platform_db'] ?? '',
            'Document root' => $paths['public'] ?? '',
        ]);
        $blocks[] = self::p('Open ' . self::code('./?host=manage') . ' on this address to sign in and manage customer sites. The operator hostname opens that screen with no query.');
        $blocks[] = self::p('A customer database, settings, and uploads are created under the stores folder, outside the document root, owned by ' . self::code($paths['php_user'] ?? Runtime::phpUser()) . ' with directories at mode 700 and the SQLite file at mode 600.');

        return self::article('Address not recognised', $blocks);
    }

    /**
     * @param list<string> $blocks
     */
    private static function article(string $title, array $blocks): string
    {
        return '<h2 class="setup-help__title">' . Html::e($title) . '</h2>' . implode('', $blocks);
    }

    private static function h(string $text): string
    {
        return '<h3 class="setup-help__heading">' . Html::e($text) . '</h3>';
    }

    private static function p(string $text): string
    {
        return '<p class="setup-help__text">' . $text . '</p>';
    }

    private static function code(string $text): string
    {
        return '<code>' . Html::e($text) . '</code>';
    }

    /**
     * @param array<string, string> $rows
     */
    private static function facts(array $rows): string
    {
        $html = '<dl class="setup-facts">';
        foreach ($rows as $label => $value) {
            $html .= '<dt>' . Html::e($label) . '</dt><dd>' . ($value !== '' ? self::code($value) : '—') . '</dd>';
        }
        $html .= '</dl>';

        return $html;
    }

    /**
     * @param list<string> $items
     */
    private static function list(array $items): string
    {
        $html = '<ul class="setup-help__list">';
        foreach ($items as $item) {
            $html .= '<li>' . $item . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private static function pre(string $text): string
    {
        return '<pre class="setup-help__pre">' . Html::e($text) . '</pre>';
    }

    /**
     * @param list<string> $dirs
     * @param list<string> $files
     */
    private static function commands(string $user, array $dirs, array $files): string
    {
        $dirs = array_values(array_filter($dirs, static fn (string $dir): bool => $dir !== '' && $dir !== '.' && PlatformDb::mayRestrictDirectory($dir)));
        $files = array_values(array_filter($files, static fn (string $file): bool => $file !== ''));
        $lines = [];
        foreach ($dirs as $dir) {
            $lines[] = 'mkdir -p ' . self::shell($dir);
        }
        if ($dirs !== []) {
            $lines[] = 'chown -R ' . self::shell($user) . ' ' . implode(' ', array_map([self::class, 'shell'], $dirs));
            $lines[] = 'chmod 700 ' . implode(' ', array_map([self::class, 'shell'], $dirs));
        }
        if ($files !== []) {
            $lines[] = 'chmod 600 ' . implode(' ', array_map([self::class, 'shell'], $files));
        }

        return implode("\n", $lines);
    }

    private static function shell(string $value): string
    {
        return escapeshellarg($value);
    }

    /**
     * @param array<string, string> $dirs
     */
    private static function writableLines(array $dirs): string
    {
        $items = [];
        foreach ($dirs as $label => $dir) {
            if ($dir === '') {
                continue;
            }
            $ok = Runtime::directoryWritable($dir);
            $items[] = Html::e($label) . ($ok
                ? ' can be written by this PHP process.'
                : ' cannot be written yet. Run the commands below, then reload.');
        }

        return $items === [] ? '' : self::list($items);
    }
}
