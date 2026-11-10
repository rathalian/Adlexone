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
            self::p('The operator database holds your username and the list of customer addresses. It does not hold their records. It is platform.sqlite inside the stores directory, not inside a customer folder.'),
            self::h('Permissions'),
            self::p('PHP is running as ' . self::code($user) . '. The stores directory must already exist. Adlexone sets mode 700 on each customer folder it creates inside that directory, and mode 600 on the database files. It does not change the stores directory or any folder above it.'),
            $platformDb !== '' ? self::pre(self::commands($user, [], [$platformDb])) : '',
            self::p('SQLite also writes -wal and -shm files next to each database while it is in use. Backup on this screen checkpoints those into the main file, then downloads the folder.'),
            self::h('Turning SaaS mode on'),
            self::p('Open ' . self::code('./?host=install') . ' on a copy that is not in SaaS mode yet and has no administrator. ' . self::code('./?install=host') . ' is the same page. The page writes a setup token one folder above the document root and asks for that value. It does not show the value. That installer writes INLAY_STORES and INLAY_OPERATOR_HOST into ' . self::code('config/platform.json') . ' next to the code. Environment values win over the file. Leave both empty for a single self-hosted site with no operator screen.'),
            self::p('The customer sites screen is the operator hostname, with no query. A customer hostname does not open it. The first operator account asks for a setup token written beside the operator database. The operator password is required after that. Sites, Rate, and Add a site are separate places on that screen. Details holds the notes.'),
            self::h('Creating a customer'),
            self::p(self::customerAddressSentence($operatorHost)),
            self::p('Leave the admin password blank and their first visit writes a setup token beside that site’s database. Read the file and give the value to the customer. The page does not show it. Fill in the admin username and password here if you are creating the first account for them.'),
            self::h('Changing a site'),
            self::p('Open the site from the dashboard. You can change the display name, the own domain, and the contact email. The slug is the folder name and it stays as it is. The display name is written to that site’s settings. When an own domain is saved, that name becomes the public address, and the slug address keeps working. The contact email stays on this list.'),
            self::h('Usage and cost'),
            self::p('Measure usage reads each site’s folder and database, then stores the figures in the operator database. The dashboard uses that measurement. It does not open every customer database on each view.'),
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
        $suggested = $stores !== '' ? $stores : InstallLayout::suggestStores($install);
        $database = $platformDb !== '' ? $platformDb : rtrim($suggested, '/\\') . DIRECTORY_SEPARATOR . 'platform.sqlite';

        return self::article('Hosting customer sites', [
            self::p('This installer turns this copy of Adlexone into a host of customer sites. It is a different page from the self-hosted installer. A customer finishing their own hostname is not this page. Opening it writes a setup token one folder above the document root. Enter that value. The page does not show it.'),
            self::p('There are four steps. The document root, the stores directory, and the operator hostname are saved together. The operator account is created after that, on the operator hostname.'),
            self::facts([
                'Code' => $install,
                'Document root' => $public,
                'Platform settings file' => $platformJson,
                'Stores directory' => $suggested,
                'Operator database' => $database,
                'PHP is running as' => $user,
            ]),
            self::h('Stores directory'),
            self::p('Each customer gets one folder under the stores directory. For example, ' . self::code(rtrim($suggested, '/\\') . '/health/') . '. ' . self::outsideRootSentence() . ' This page does not create it. PHP, running as ' . self::code($user) . ', must be able to create, read, update, and delete files there. The check removes its trial files before the page answers.'),
            self::h('Operator database'),
            self::p('The operator login and the list of sites are stored in platform.sqlite inside the stores directory, not inside a customer folder and not inside the website. That file is mode 600. A customer folder created inside the stores directory is mode 700. The stores directory and every folder above it are left as they are.'),
            $database !== '' ? self::pre(self::commands($user, [], [$database])) : '',
            self::h('Operator hostname'),
            self::p('For example, brospd.adlexone.com. On this machine, localhost is accepted. Point that name at this server. It must not be a customer hostname. A customer slug then opens under the rest of that name. For brospd.adlexone.com, Health is health.adlexone.com, from one wildcard, *.adlexone.com. Environment values for INLAY_STORES and INLAY_OPERATOR_HOST win over the file this step writes.'),
        ]);
    }

    /**
     * Help for the self-hosted installer. The same notes are the Details on each step.
     *
     * @param array<string, string> $paths
     */
    public static function siteInstall(array $paths, string $directory = '', string $shape = 'children', bool $record = true): string
    {
        $user = $paths['php_user'] ?? Runtime::phpUser();
        $install = rtrim((string) ($paths['install'] ?? ''), '/\\');
        $public = $paths['public'] ?? '';
        $publicShow = $public !== '' ? $public : ($install !== '' ? $install . DIRECTORY_SEPARATOR . 'public' : 'public');
        $doc = InstallLayout::canonical((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $docShow = $doc !== '' ? $doc : 'not reported';
        $chosen = $directory !== '' ? $directory : ($install !== '' ? InstallLayout::suggest($install) : '');
        $planned = $shape === 'legacy' ? InstallLayout::legacyPaths($chosen) : InstallLayout::storagePaths($chosen);
        $pointer = $install !== '' ? InstallLayout::pointerFile($install) : 'config/platform.json';
        $tokenPath = $install !== '' ? SaasInstallScreen::installTokenPath($install) : 'adlexone-install-token.txt';

        $blocks = [
            self::p('Adlexone keeps the applications, records, and rules for the work. An application is one kind of work, with its own fields and steps. A record is one piece of that work. When a record moves, a rule can write the log, the next step, and the date.'),
            self::p('Use it when the work is records moving through known steps, and more than one person needs to see them. People sign in and open the application they use. The record holds the files. The database is one SQLite file on this server.'),
            self::p('Setup installs the system. It does not configure the day-to-day work. There are three steps, and nothing is saved until the last one.'),
            self::list([
                '<strong>Document root.</strong> Confirm which folder the website uses.',
                '<strong>Storage directory.</strong> Choose where the database, settings, and uploads are kept. PHP must be able to use the folder, and a browser must not be able to read it.',
                '<strong>Account.</strong> Set the name on the sign-in page, create the first administrator, and choose whether to install sample applications.',
            ]),
            self::p('Opening this page writes a setup token at ' . self::code($tokenPath) . ', one folder above the document root. Enter that value on the first step. The page does not show it. One entry is enough for the rest of this installer. The file is removed when the account is created.'),
            self::h('Document root'),
            self::p('The document root is the folder the website serves. Shared hosting, such as cPanel, often cannot change that folder. Unzip Adlexone into it. The package and the document root are the same folder. A file named .htaccess in the package refuses requests for the database, settings, and code, and only index.php runs. The host must be Apache with mod_rewrite, and it must allow .htaccess (AllowOverride All).'),
            self::p('If you can set the document root, set it to the public folder inside the package. For a package at ' . self::code($install !== '' ? $install : 'the package') . ', the document root is ' . self::code($publicShow) . '. Browsers can then open only the files in public. The database, settings, and code are not on the website.'),
            self::p('This page reads the document root from the server and selects that choice. When it is not the public folder, the public folder is the better document root. Continue accepts the detected document root.'),
            self::p('Package: ' . self::code($install !== '' ? $install : 'not reported') . '. public folder: ' . self::code($publicShow) . '. Document root: ' . self::code($docShow) . '.'),
            self::h('Storage directory'),
            self::p('The storage directory holds the database, the settings, and the uploaded files. A browser must not be able to open it. Create the directory before you check it. This page does not create it.'),
        ];
        if ($shape === 'legacy') {
            $blocks[] = self::p('This server chose the directory. Settings are in ' . self::code($planned['config']) . '. The database is ' . self::code($planned['sqlite']) . '. Uploads are in ' . self::code($planned['attachments']) . '.');
        } else {
            $blocks[] = self::p('Adlexone creates three folders inside the directory you enter. Settings go in ' . self::code('config') . '. The database is ' . self::code('database/adlexone.sqlite') . '. Uploads go in ' . self::code('attachments') . '.');
        }
        $blocks[] = self::p('Check this folder creates a file, reads it, changes it, deletes it, and creates then removes a folder. PHP does this as ' . self::code($user) . '. The trial files are removed before the page answers.');
        $blocks[] = self::p('The folder should sit outside the website. A folder outside the web folder has no public address. A folder inside the web folder is strongly recommended against. Setup still writes a trial file there and requests it from the website. Continue is available only when that request does not return the file. On shared hosting the package rules can refuse a folder named storage, but the check is what decides.');
        if ($record) {
            $blocks[] = self::p('Adlexone writes the path into ' . self::code($pointer) . '. That file stays next to the code so the next visit uses the same folder. It does not hold records. If the code folder cannot be written, set INLAY_STORAGE to this path in the server environment and reload. The environment wins over the file.');
        }
        $blocks[] = self::p('Adlexone sets mode 700 on the folders it creates inside this directory, and mode 600 on the database file and the settings files. It does not change this directory or any folder above it. If you set the permissions yourself, use those modes, and use ' . self::code($user) . ', not root.');
        $blocks[] = self::pre(self::commands($user, [$planned['config'], $planned['database_dir'], $planned['attachments']], [$planned['sqlite']]));
        $blocks[] = self::p('While the site is open, SQLite may add ' . self::code('adlexone.sqlite-wal') . ' and ' . self::code('adlexone.sqlite-shm') . ' beside the database. They are part of the live database. Copy them with the database if you take a manual backup.');
        $blocks[] = self::h('After you sign in');
        $blocks[] = self::p('Configuration comes after installation. Sign in with the administrator you create here. The rest is under Configure.');
        $blocks[] = self::list([
            '<strong>Applications.</strong> Create an application for the work, or remove the samples.',
            '<strong>People.</strong> Add a person when they need access.',
            '<strong>Settings.</strong> Set the time zone, the date format, and the introduction on the home page.',
            '<strong>Sign-in and email.</strong> Turn on username sign-in, an organisation directory, or mail when you need them.',
        ]);

        return self::article('Self-hosted installer', $blocks);
    }

    public static function loginName(): string
    {
        return self::p('This name is shown on the sign-in page and in the header after sign-in. It is not a username.')
            . self::p('You can use the organisation name, the name of the system, or leave Adlexone. For example, Health. Use 80 characters or fewer.');
    }

    public static function publicUrl(): string
    {
        return self::p('For example, https://health.example.com. Leave this blank and Adlexone uses the address in the browser. Set it when a proxy or load balancer sits in front of the site.');
    }

    public static function accountPassword(): string
    {
        $html = self::p('The password is stored as a hash in the SQLite file. It is not written into the settings.');
        if (DefaultAccount::allowed()) {
            $html .= self::p('The prefilled password is ' . self::code(DefaultAccount::PASSWORD) . '. It is only accepted from this machine or a private address. Any other password must be at least 8 characters.');
        } else {
            $html .= self::p('Use at least 12 characters. The prefilled localhost password is not accepted from a public address.');
        }

        return $html;
    }

    public static function startingApplications(): string
    {
        return self::p('Sample applications installs Publishing, Matters, Conveyancing, Claims, Leave requests, Complaints, Committee, Audit, Incidents, Candidates, and Contracts, with example records. You can delete them. Deleting one does not install it again.')
            . self::p('Publishing (Example) installs only that application, with its fields, rules, guide, and sample records.')
            . self::p('No applications leaves the home empty. After you sign in, you create an application.');
    }

    public static function customerToken(string $path): string
    {
        return self::p('Anyone who can open this page before an administrator exists could create the account. The token proves you can read a file beside the database.')
            . self::p('The file is ' . self::code($path) . '. This page does not show the value. The file is removed when the account is created.');
    }

    public static function customerDns(string $parent): string
    {
        $example = 'health.' . $parent;
        $own = 'records.health.example.com';

        return self::article('Point a name at this site', [
            self::p('The site address is the slug, then ' . self::code($parent) . '. For example, ' . self::code($example) . '. Creating the site does not add a DNS record. One wildcard, ' . self::code('*.' . $parent) . ', already points every name under that parent at this server.'),
            self::h('A name the customer owns'),
            self::p('Enter that name in Own domain. For example, ' . self::code($own) . '. It has to be a name outside ' . self::code($parent) . ', because every name under ' . self::code($parent) . ' is a slug on this server. At their DNS host, add a CNAME. The name is the one you entered. The target is this site’s address, ' . self::code($example) . '. When the name resolves, open it. The slug address keeps working.'),
            self::p('A name with nothing in front of the customer’s domain, such as ' . self::code('health.example.com') . ', cannot be a CNAME. Use an A record to the address ' . self::code($example) . ' resolves to.'),
            self::p('The certificate has to cover the name. On this server that is the host’s certificate tool. Adlexone does not request a certificate and does not change DNS.'),
        ]);
    }

    private static function customerAddressSentence(string $operatorHost): string
    {
        $parent = SiteUsage::siteParent($operatorHost);
        if ($parent === '') {
            return 'Choose a short slug, the hostname they will use, and a display name. Point that hostname at this server.';
        }

        return 'Choose a short slug and a display name. The address is the slug, then ' . self::code($parent) . '. For example, ' . self::code('health.' . $parent) . '. Creating the site does not add a DNS record. Own domain is optional. The Details button on this form explains the CNAME.';
    }

    /**
     * @param array<string, string> $paths
     */
    public static function suspended(array $paths): string
    {
        return self::article('This site is turned off', [
            self::p('The operator suspended this address. Nothing was deleted.'),
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
        $operator = (string) ($paths['operator_host'] ?? '');
        if ($operator !== '') {
            $blocks[] = self::p('The operator site is ' . self::code($operator) . '. Customer sites are not managed from this address.');
        }

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

    private static function outsideRootSentence(): string
    {
        $web = InstallLayout::publicRoot();
        $where = $web !== '' ? ', which is ' . self::code($web) : '';

        return 'The stores directory must sit outside the public document root' . $where . '. Create it one directory up, then check it.';
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
