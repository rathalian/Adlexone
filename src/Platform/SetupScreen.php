<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Auth\Csrf;
use Adlexone\Html\Html;
use Adlexone\Http\Page;
use Adlexone\Theme\Theme;

/**
 * First visit for a site that has no users yet.
 *
 * A self-hosted install confirms the document root, chooses a storage directory,
 * then creates the first administrator. A customer store already has its folder.
 */
final class SetupScreen
{
    private const SESSION_KEY = 'adlexone_setup';

    private const STEP_ROOT = 'root';

    private const STEP_STORAGE = 'storage';

    private const STEP_ACCOUNT = 'account';

    public static function handle(): void
    {
        self::theme();
        $paths = Runtime::paths();
        $hosted = ($paths['stores'] ?? '') !== '';
        $error = '';
        $notice = '';
        /** @var array{ok: bool, message: string, user: string, directory: string, created: bool, checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>}|null $report */
        $report = null;

        if (!$hosted) {
            $stale = self::dropStalePlacement($paths);
            if ($stale !== '') {
                $error = $stale;
            }
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::valid()) {
                $error = 'This form expired. Reload the page and try again.';
            } elseif ($hosted) {
                try {
                    self::installHosted($paths);

                    return;
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            } else {
                $action = (string) ($_POST['setup_action'] ?? '');
                try {
                    if ($action === 'placement') {
                        self::savePlacement($paths);

                        return;
                    }
                    if ($action === 'check_storage' || $action === 'save_storage') {
                        $report = self::takeStorage($paths, $action === 'save_storage');
                    } elseif ($action === 'install') {
                        self::installSelf($paths);

                        return;
                    }
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        if ($hosted) {
            try {
                SetupToken::issue();
            } catch (\Throwable $e) {
                if ($error === '') {
                    $error = $e->getMessage();
                }
            }
            self::renderHosted($paths, $error);

            return;
        }

        $step = self::currentStep();
        if ($report !== null) {
            $step = self::STEP_STORAGE;
            if ($report['ok']) {
                $notice = $report['message'];
                if ($report['created']) {
                    $notice .= ' The folder did not exist, so PHP created it.';
                }
                $notice .= ' Continue to create the account.';
            } elseif ($error === '') {
                $error = $report['checks'] === [] ? $report['message'] : 'This folder cannot be used yet.';
            }
        }
        if ($step === self::STEP_ACCOUNT) {
            try {
                $chosen = self::chosenPaths();
                SetupToken::issue($chosen['database_dir'] . 'setup-token.txt');
            } catch (\Throwable $e) {
                if ($error === '') {
                    $error = $e->getMessage();
                }
            }
        }
        self::renderStep($paths, $step, $error, $notice, $report);
    }

    /**
     * @param array<string, string> $paths
     */
    private static function savePlacement(array $paths): void
    {
        $choice = (string) ($_POST['placement'] ?? '');
        if (!in_array($choice, [InstallLayout::PACKAGE, InstallLayout::PUBLIC_FOLDER], true)) {
            throw new \RuntimeException('Choose where this copy of Adlexone is installed.');
        }
        $detected = InstallLayout::detect($paths['install']);
        if ($choice !== $detected) {
            throw new \RuntimeException(InstallLayout::placementMessage($paths['install']));
        }
        $state = self::state();
        $state['placement'] = $choice;
        self::saveState($state);
        header('Location: ./?step=storage', true, 303);
        exit;
    }

    /**
     * @param array<string, string> $paths
     * @return array{ok: bool, message: string, user: string, directory: string, created: bool, checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>}|null
     */
    private static function takeStorage(array $paths, bool $advance): ?array
    {
        $state = self::state();
        if ($state['placement'] === '') {
            throw new \RuntimeException('Confirm the document root first.');
        }
        $env = InstallLayout::environmentStorage($paths['install']);
        if ($env['locked']) {
            $path = $env['path'];
            $shape = $env['shape'];
            $record = false;
        } else {
            $path = trim((string) ($_POST['storage_path'] ?? ''));
            $shape = 'children';
            $record = true;
        }
        $probe = InstallLayout::probe($paths['install'], $path, $state['placement'], null, $record);
        $already = $probe['ok'] && $state['passed'] === $probe['directory'];
        $state['passed'] = $probe['ok'] ? $probe['directory'] : '';
        if ($advance && $already) {
            $state['storage'] = $probe['directory'];
            $state['shape'] = $shape;
            $state['locked'] = $env['locked'];
            self::saveState($state);
            header('Location: ./?step=account', true, 303);
            exit;
        }
        self::saveState($state);

        return $probe;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function installSelf(array $paths): void
    {
        $state = self::state();
        if ($state['placement'] === '' || $state['storage'] === '') {
            throw new \RuntimeException('Confirm the document root, then choose the storage directory.');
        }
        if ($state['placement'] !== InstallLayout::detect($paths['install'])) {
            throw new \RuntimeException(InstallLayout::placementMessage($paths['install']));
        }
        $probe = InstallLayout::probe(
            $paths['install'],
            $state['storage'],
            $state['placement'],
            null,
            !$state['locked']
        );
        if (!$probe['ok']) {
            throw new \RuntimeException($probe['message'] !== '' ? $probe['message'] : 'This folder cannot be used yet.');
        }
        $chosen = $state['shape'] === 'legacy'
            ? InstallLayout::legacyPaths($probe['directory'])
            : InstallLayout::storagePaths($probe['directory']);
        $token = $chosen['database_dir'] . 'setup-token.txt';
        $admin = self::adminFromPost($token);
        if (!$state['locked']) {
            InstallLayout::remember($paths['install'], $probe['directory']);
        }
        Provisioner::install(
            $paths['install'],
            $chosen['config'],
            $chosen['sqlite'],
            $chosen['attachments'],
            $admin
        );
        SetupToken::clear($token);
        unset($_SESSION[self::SESSION_KEY]);
        header('Location: ./');
        exit;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function installHosted(array $paths): void
    {
        $admin = self::adminFromPost(SetupToken::path());
        Provisioner::install(
            $paths['install'],
            $paths['config'],
            $paths['sqlite'],
            $paths['attachments'],
            $admin
        );
        SetupToken::clear();
        header('Location: ./');
        exit;
    }

    /**
     * @return array{site_name: string, public_url: string, username: string, password: string, first_name: string, last_name: string, email: string, starting_applications: string}
     */
    private static function adminFromPost(string $tokenPath): array
    {
        $site = trim((string) ($_POST['site_name'] ?? ''));
        $username = trim((string) ($_POST['user_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $email = trim((string) ($_POST['email'] ?? ''));
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $last = trim((string) ($_POST['last_name'] ?? ''));
        $public = trim((string) ($_POST['public_url'] ?? ''));

        if ($site === '' || strlen($site) > 80) {
            throw new \RuntimeException('Enter the login page application name (80 characters or fewer).');
        }
        if (preg_match('/^[A-Za-z0-9._-]{2,40}$/', $username) !== 1) {
            throw new \RuntimeException('The username must be 2–40 characters: letters, numbers, dots, underscores, or hyphens.');
        }
        DefaultAccount::assertPassword($password);
        if (!hash_equals($password, $confirm)) {
            throw new \RuntimeException('The two passwords do not match.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('The email address does not look valid.');
        }
        if ($public !== '' && preg_match('#^https?://#', $public) !== 1) {
            throw new \RuntimeException('The public URL should start with https:// or http://, or be left blank.');
        }
        if (!SetupToken::check((string) ($_POST['setup_token'] ?? ''), $tokenPath)) {
            throw new \RuntimeException('The setup token does not match the file beside the database. Open that file on the server and enter the value.');
        }
        $starting = (string) ($_POST['starting_applications'] ?? '');
        if (!in_array($starting, ['samples', 'exemplar', 'none'], true)) {
            throw new \RuntimeException('Choose the applications to install.');
        }

        return [
            'site_name' => $site,
            'public_url' => $public,
            'username' => $username,
            'password' => $password,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'starting_applications' => $starting,
        ];
    }

    /**
     * @param array<string, string> $paths
     * @param array{ok: bool, message: string, user: string, directory: string, created: bool, checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>}|null $report
     */
    private static function renderStep(array $paths, string $step, string $error, string $notice, ?array $report): void
    {
        $focus = $error === '';
        if ($step === self::STEP_STORAGE) {
            self::render(
                'Storage directory',
                self::storageLead($paths),
                $error,
                self::storageForm($paths, $report, $focus),
                self::steps(self::STEP_STORAGE),
                $notice,
                self::detailsButton('install-help-storage')
            );

            return;
        }
        if ($step === self::STEP_ACCOUNT) {
            self::render(
                'Account',
                'Create the first administrator. You can add other people after you sign in.',
                $error,
                self::accountForm($paths, false, $focus),
                self::steps(self::STEP_ACCOUNT),
                $notice
            );

            return;
        }
        self::render(
            'Document root',
            'Shared hosting needs the Adlexone package in the document root. If the package is not in the document root, the document root must be the public folder.',
            $error,
            self::placementForm($paths, $focus),
            self::steps(self::STEP_ROOT),
            $notice,
            self::detailsButton('install-help-root')
        );
    }

    /**
     * @param array<string, string> $paths
     */
    private static function renderHosted(array $paths, string $error): void
    {
        $label = trim((string) ($paths['store_label'] ?? ''));
        $form = '<p class="install-callout">The database, settings, and uploads for this site are already in place.</p>';
        $form .= self::accountForm($paths, true, $error === '');
        $form .= self::dialog('install-help-files', 'Files for this site', HelpText::customer($paths));
        self::render(
            'Account',
            $label !== ''
                ? $label . ' does not have an administrator yet. Create the first account.'
                : 'Create the first administrator.',
            $error,
            $form,
            '',
            '',
            self::detailsButton('install-help-files')
        );
    }

    /**
     * @param array<string, string> $paths
     */
    private static function placementForm(array $paths, bool $focus): string
    {
        $install = rtrim($paths['install'], '/\\');
        $package = InstallLayout::canonical($install);
        $public = InstallLayout::canonical($install . DIRECTORY_SEPARATOR . 'public');
        $doc = InstallLayout::canonical((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $detected = InstallLayout::detect($paths['install']);
        $selected = (string) ($_POST['placement'] ?? '');
        if ($selected === '' && ($detected === InstallLayout::PACKAGE || $detected === InstallLayout::PUBLIC_FOLDER)) {
            $selected = $detected;
        }

        $html = self::openForm();
        $html .= '<p class="install-section">Prerequisite</p>';
        $html .= self::facts([
            'Package' => $package !== '' ? $package : $install,
            'public folder' => $public !== '' ? $public : $install . DIRECTORY_SEPARATOR . 'public',
            'Document root' => $doc !== '' ? $doc : 'Not reported',
        ]);
        $html .= self::matchLine($detected);
        $html .= self::options(
            'Where is this copy of Adlexone?',
            'placement',
            [
                InstallLayout::PACKAGE => [
                    'The package is in the document root',
                    'The website folder is the unzipped package. This is the usual shared-hosting layout.',
                ],
                InstallLayout::PUBLIC_FOLDER => [
                    'The document root is the public folder',
                    'The website folder is public, inside the package. The rest of the package is off the website.',
                ],
            ],
            $selected,
            $focus
        );
        $html .= self::actions('Continue', 'placement');
        $html .= '</form>';
        $html .= self::dialog('install-help-root', 'Document root', self::helpDocument($install, $public, $doc));

        return $html;
    }

    /**
     * @param array<string, string> $paths
     * @param array{ok: bool, message: string, user: string, directory: string, created: bool, checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>}|null $report
     */
    private static function storageForm(array $paths, ?array $report, bool $focus): string
    {
        $install = $paths['install'];
        $env = InstallLayout::environmentStorage($install);
        $state = self::state();
        $shape = $env['locked'] ? $env['shape'] : 'children';
        if ($env['locked']) {
            $path = $env['path'];
        } else {
            $posted = trim((string) ($_POST['storage_path'] ?? ''));
            if ($posted !== '') {
                $path = $posted;
            } elseif ($state['storage'] !== '') {
                $path = $state['storage'];
            } else {
                $saved = InstallLayout::savedStorage($install);
                $path = $saved !== '' ? $saved : InstallLayout::suggest($install);
            }
        }
        if ($report !== null && $report['ok'] && $report['directory'] !== '') {
            $path = $report['directory'];
        }
        $preview = InstallLayout::absolute($path);
        if ($report !== null && $report['directory'] !== '') {
            $preview = $report['directory'];
        }

        $html = self::openForm();
        if ($env['locked']) {
            $html .= '<div class="field">';
            $html .= '<div class="install-labelrow"><span class="login-label label">Storage directory</span></div>';
            $html .= '<p class="install-locked"><code>' . Html::e($path) . '</code></p>';
            $html .= '<p class="form-help">Set by the server. This page cannot change it.</p>';
            $html .= '</div>';
        } else {
            $html .= self::field(
                'Storage directory',
                'storage_path',
                'text',
                $path,
                true,
                'Full path on this server. Adlexone creates config, database, and attachments inside it.',
                '',
                'off',
                $focus,
                'spellcheck="false" autocapitalize="off" autocorrect="off"'
            );
        }
        if ($preview !== null && $preview !== '') {
            $planned = $shape === 'legacy' ? InstallLayout::legacyPaths($preview) : InstallLayout::storagePaths($preview);
            $html .= '<p class="install-section">Folders Adlexone will use</p>';
            $html .= self::facts([
                'Settings' => $planned['config'],
                'Database' => $planned['sqlite'],
                'Uploads' => $planned['attachments'],
            ]);
        }
        if ($report !== null) {
            $html .= self::checks($report['checks']);
        }
        $ready = $preview !== null && $preview !== '' && self::state()['passed'] === $preview;
        $html .= $ready
            ? self::actions('Continue', 'save_storage', 'Check again', 'check_storage')
            : self::actions('Check this folder', 'check_storage');
        $html .= '</form>';
        $html .= self::dialog(
            'install-help-storage',
            'Storage directory',
            self::helpStorage($install, $preview !== null && $preview !== '' ? $preview : InstallLayout::suggest($install), $shape, !$env['locked'])
        );

        return $html;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function accountForm(array $paths, bool $hosted, bool $focus): string
    {
        if (isset($_POST['site_name'])) {
            $site = (string) $_POST['site_name'];
        } else {
            $label = trim((string) ($paths['store_label'] ?? ''));
            $site = $label !== '' ? $label : 'Adlexone';
        }
        $tokenPath = $hosted
            ? SetupToken::path()
            : self::chosenPaths()['database_dir'] . 'setup-token.txt';
        $passwordHelp = DefaultAccount::allowed()
            ? 'At least 8 characters. The prefilled password is for this machine only.'
            : 'At least 12 characters.';

        $html = self::openForm();
        $html .= '<p class="form-help">A star marks a required field.</p>';
        $html .= '<p class="install-section">Sign-in page</p>';
        $html .= self::field(
            'Login page application name',
            'site_name',
            'text',
            $site,
            true,
            'Shown on the sign-in page. You can use your organisation name, a system name, or leave Adlexone.',
            'install-help-name',
            'off',
            $focus,
            'maxlength="80"'
        );
        $html .= self::field(
            'Public URL',
            'public_url',
            'text',
            (string) ($_POST['public_url'] ?? ''),
            false,
            'Leave this blank unless a proxy sits in front of the site.',
            'install-help-url',
            'url'
        );
        $html .= '<p class="install-section">Administrator</p>';
        $html .= self::field('Your name', 'first_name', 'text', (string) ($_POST['first_name'] ?? ''), false, '', '', 'given-name');
        $html .= self::field('Last name', 'last_name', 'text', (string) ($_POST['last_name'] ?? ''), false, '', '', 'family-name');
        $html .= self::field(
            'Email',
            'email',
            'email',
            (string) ($_POST['email'] ?? ''),
            false,
            'Saved on the account. Sign-in uses the username.',
            '',
            'email'
        );
        $html .= self::field(
            'Username',
            'user_name',
            'text',
            DefaultAccount::postedUsername($_POST['user_name'] ?? null),
            true,
            '2–40 characters: letters, numbers, dots, underscores, or hyphens.',
            '',
            'username',
            false,
            'spellcheck="false" maxlength="40"'
        );
        $html .= self::field(
            'Password',
            'password',
            'password',
            DefaultAccount::postedPassword($_POST['password'] ?? null),
            true,
            $passwordHelp,
            'install-help-password',
            'new-password'
        );
        $html .= self::field(
            'Confirm password',
            'password_confirm',
            'password',
            DefaultAccount::postedPassword($_POST['password_confirm'] ?? null),
            true,
            '',
            '',
            'new-password'
        );
        $html .= '<p class="install-section">Applications</p>';
        $html .= '<div class="install-labelrow"><span class="login-label label" id="setup-apps-label">Starting applications</span>'
            . self::detailsButton('install-help-apps') . '</div>';
        $html .= self::options(
            '',
            'starting_applications',
            [
                'samples' => [
                    'Sample applications',
                    'Install the sample applications, with example records. They can be deleted.',
                ],
                'exemplar' => [
                    'Publishing (Example)',
                    'Install Publishing (Example), with its fields, rules, and sample records.',
                ],
                'none' => [
                    'No applications',
                    'Start with an empty home. You create an application after you sign in.',
                ],
            ],
            (string) ($_POST['starting_applications'] ?? ''),
            false,
            'setup-apps-label'
        );
        $html .= '<p class="install-section">Confirm it is you</p>';
        $html .= self::field(
            'Setup token',
            'setup_token',
            'text',
            '',
            true,
            'Open ' . $tokenPath . ' on the server and paste the value. This page does not show it.',
            'install-help-token',
            'off',
            false,
            'spellcheck="false" autocapitalize="off"'
        );
        $html .= self::actions('Create the site', 'install', '', '', $hosted ? '' : '?step=storage');
        $html .= '</form>';
        $html .= self::dialog('install-help-name', 'Login page application name', self::helpName());
        $html .= self::dialog('install-help-url', 'Public URL', self::helpUrl());
        $html .= self::dialog('install-help-password', 'Password', self::helpPassword());
        $html .= self::dialog('install-help-apps', 'Starting applications', self::helpApplications());
        $html .= self::dialog('install-help-token', 'Setup token', self::helpToken($tokenPath));

        return $html;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function storageLead(array $paths): string
    {
        $user = Runtime::phpUser();
        $env = InstallLayout::environmentStorage($paths['install']);
        if ($env['locked']) {
            return 'The server has set this directory. PHP is running as ' . $user . '. That account must be able to create, read, update, and delete files here.';
        }

        return 'Choose a folder for the database, settings, and uploaded files. PHP is running as ' . $user . '. That account must be able to create, read, update, and delete files in the folder.';
    }

    private static function steps(string $current): string
    {
        $items = [
            self::STEP_ROOT => 'Document root',
            self::STEP_STORAGE => 'Storage',
            self::STEP_ACCOUNT => 'Account',
        ];
        $order = array_keys($items);
        $currentIndex = array_search($current, $order, true);
        $html = '<nav aria-label="Setup steps"><ol class="install-steps">';
        foreach ($items as $id => $label) {
            $index = (int) array_search($id, $order, true);
            $class = $index === $currentIndex ? 'is-current' : ($index < $currentIndex ? 'is-done' : 'is-ahead');
            $inner = '<span class="install-steps__num">' . ($index + 1) . '</span><span class="install-steps__label">' . Html::e($label) . '</span>';
            if ($index < $currentIndex) {
                $item = '<a class="install-steps__item" href="?step=' . Html::e($id) . '">' . $inner . '</a>';
            } elseif ($index === $currentIndex) {
                $item = '<span class="install-steps__item" aria-current="step">' . $inner . '</span>';
            } else {
                $item = '<span class="install-steps__item">' . $inner . '</span>';
            }
            $html .= '<li class="' . $class . '">' . $item . '</li>';
        }

        return $html . '</ol></nav>';
    }

    private static function matchLine(string $detected): string
    {
        if ($detected === InstallLayout::PACKAGE) {
            return '<p class="install-match is-ok">This matches "The package is in the document root".</p>';
        }
        if ($detected === InstallLayout::PUBLIC_FOLDER) {
            return '<p class="install-match is-ok">This matches "The document root is the public folder".</p>';
        }

        return '<p class="install-match is-bad">This does not match either choice. Set the document root, then reload this page.</p>';
    }

    /**
     * @param array<string, array{0: string, 1: string}> $options
     */
    private static function options(
        string $legend,
        string $name,
        array $options,
        string $selected,
        bool $focus,
        string $labelledBy = ''
    ): string {
        $html = '<fieldset class="install-choice"' . ($labelledBy !== '' ? ' aria-labelledby="' . Html::e($labelledBy) . '"' : '') . '>';
        if ($legend !== '') {
            $html .= '<legend class="login-label label">' . Html::e($legend) . '</legend>';
        }
        $first = true;
        foreach ($options as $value => [$label, $help]) {
            $id = 'setup_' . $name . '_' . $value;
            $checked = $selected === (string) $value;
            $autofocus = $focus && ($checked || ($selected === '' && $first));
            $html .= '<label class="install-option" for="' . Html::e($id) . '">'
                . '<input class="radio" type="radio" name="' . Html::e($name) . '" id="' . Html::e($id) . '" value="' . Html::e((string) $value) . '"'
                . ($checked ? ' checked' : '')
                . ($autofocus ? ' autofocus' : '')
                . ' required>'
                . '<span class="install-option__text"><span class="install-option__title">' . Html::e($label) . '</span>'
                . '<span class="form-help">' . Html::e($help) . '</span></span></label>';
            $first = false;
        }

        return $html . '</fieldset>';
    }

    private static function field(
        string $label,
        string $name,
        string $type,
        string $value,
        bool $required,
        string $help = '',
        string $detailsId = '',
        string $autocomplete = '',
        bool $focus = false,
        string $attributes = ''
    ): string {
        $id = 'setup_' . $name;
        $class = 'input' . ($name === 'storage_path' ? ' install-path' : '');
        $html = '<div class="field">';
        $html .= '<div class="install-labelrow">';
        $html .= '<label class="login-label label" for="' . Html::e($id) . '">' . Html::e($label) . '</label>';
        if ($detailsId !== '') {
            $html .= self::detailsButton($detailsId);
        }
        $html .= '</div>';
        $html .= '<input class="' . $class . '" type="' . Html::e($type) . '" id="' . Html::e($id) . '" name="' . Html::e($name) . '" value="' . Html::e($value) . '"'
            . ($required ? ' required' : '')
            . ($autocomplete !== '' ? ' autocomplete="' . Html::e($autocomplete) . '"' : '')
            . ($focus ? ' autofocus' : '')
            . ($attributes !== '' ? ' ' . $attributes : '')
            . '>';
        if ($help !== '') {
            $html .= '<p class="form-help">' . Html::e($help) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param list<array{id: string, label: string, ok: bool, detail: string, state: string}> $checks
     */
    private static function checks(array $checks): string
    {
        if ($checks === []) {
            return '';
        }
        $html = '<ul class="install-checks">';
        foreach ($checks as $check) {
            $state = $check['state'];
            $result = $state === 'pass' ? 'Passed' : ($state === 'skip' ? 'Not tried' : 'Failed');
            $html .= '<li class="is-' . Html::e($state) . '">'
                . '<span class="install-checks__name">' . Html::e($check['label']) . '</span>'
                . '<span class="install-checks__result">' . $result . '</span>';
            if ($state !== 'pass' && $check['detail'] !== '') {
                $html .= '<span class="install-checks__detail">' . Html::e($check['detail']) . '</span>';
            }
            $html .= '</li>';
        }

        return $html . '</ul>';
    }

    /**
     * @param array<string, string> $rows
     */
    private static function facts(array $rows): string
    {
        $html = '<dl class="install-facts">';
        foreach ($rows as $label => $value) {
            $html .= '<div><dt>' . Html::e((string) $label) . '</dt><dd><code>' . Html::e($value) . '</code></dd></div>';
        }

        return $html . '</dl>';
    }

    private static function openForm(): string
    {
        return '<form method="post" action="" class="login-form install-form" autocomplete="off">' . Csrf::field();
    }

    private static function actions(
        string $primary,
        string $primaryValue,
        string $secondary = '',
        string $secondaryValue = '',
        string $backHref = ''
    ): string {
        $html = '<div class="install-actions">';
        if ($backHref !== '') {
            $html .= '<a class="btn" href="' . Html::e($backHref) . '">Back</a>';
        }
        if ($secondary !== '') {
            $html .= '<button type="submit" class="btn" name="setup_action" value="' . Html::e($secondaryValue) . '">' . Html::e($secondary) . '</button>';
        }
        $html .= '<button type="submit" class="btn btn--primary" name="setup_action" value="' . Html::e($primaryValue) . '">' . Html::e($primary) . '</button>';

        return $html . '</div>';
    }

    private static function detailsButton(string $id): string
    {
        return '<button type="button" class="install-help" data-sheet-pop aria-controls="' . Html::e($id) . '">Details</button>';
    }

    private static function dialog(string $id, string $title, string $body): string
    {
        return '<dialog id="' . Html::e($id) . '" class="sheet-pop" aria-labelledby="' . Html::e($id) . '-title">'
            . '<div class="sheet-pop__panel">'
            . '<div class="sheet-pop__head"><h2 class="sheet-pop__title" id="' . Html::e($id) . '-title">' . Html::e($title) . '</h2>'
            . '<button type="button" class="btn btn--sm sheet-pop__close" data-sheet-pop-close>Close</button></div>'
            . '<div class="sheet-pop__body setup-help">' . $body . '</div>'
            . '<div class="sheet-pop__actions"><button type="button" class="btn btn--primary" data-sheet-pop-close>Done</button></div>'
            . '</div></dialog>';
    }

    private static function helpDocument(string $install, string $public, string $doc): string
    {
        $publicShow = $public !== '' ? $public : rtrim($install, '/\\') . DIRECTORY_SEPARATOR . 'public';
        $docShow = $doc !== '' ? $doc : 'not reported';

        return self::para('The document root is the folder the website serves.')
            . self::para('Shared hosting, such as cPanel, often cannot change that folder. Unzip Adlexone into it. The package and the document root are the same folder. A file named .htaccess in the package refuses requests for the database, settings, and code, and only index.php runs. The host must be Apache with mod_rewrite, and it must allow .htaccess (AllowOverride All).')
            . self::para('If you can set the document root, set it to the public folder inside the package. For a package at ' . self::code($install) . ', the document root is ' . self::code($publicShow) . '. Browsers can then open only the files in public. The database, settings, and code are not on the website.')
            . self::para('This page reads the document root from the server. Continue accepts only the choice that matches it.')
            . self::para('Package: ' . self::code(rtrim($install, '/\\')) . '. public folder: ' . self::code($publicShow) . '. Document root: ' . self::code($docShow) . '.');
    }

    private static function helpStorage(string $install, string $directory, string $shape, bool $record): string
    {
        $planned = $shape === 'legacy' ? InstallLayout::legacyPaths($directory) : InstallLayout::storagePaths($directory);
        $user = Runtime::phpUser();
        $pointer = InstallLayout::pointerFile($install);
        $dirs = [$directory];
        if ($record) {
            $dirs[] = dirname($pointer);
        }
        $html = self::para('The storage directory holds the database, the settings, and the uploaded files. A browser must not be able to open it.');
        if ($shape === 'legacy') {
            $html .= self::para('This server chose the directory. Settings are in ' . self::code($planned['config']) . '. The database is ' . self::code($planned['sqlite']) . '. Uploads are in ' . self::code($planned['attachments']) . '.');
        } else {
            $html .= self::para('Adlexone creates three folders inside the directory you enter. Settings go in ' . self::code('config') . '. The database is ' . self::code('database/adlexone.sqlite') . '. Uploads go in ' . self::code('attachments') . '.');
        }
        $html .= self::para('Check this folder creates a file, reads it, changes it, deletes it, and creates then removes a folder. PHP does this as ' . self::code($user) . '. The trial files are removed before the page answers.');
        $html .= self::para('Keep the directory off the website. When the package is the document root, a folder under ' . self::code('storage') . ' is blocked by the package rules. Any other folder inside the document root is not, and uploaded files could be downloaded.');
        if ($record) {
            $html .= self::para('Adlexone writes the path into ' . self::code($pointer) . '. That file stays next to the code so the next visit uses the same folder. It does not hold records. If the code folder cannot be written, set INLAY_STORAGE to this path in the server environment and reload. The environment wins over the file.');
        }
        $html .= self::para('Adlexone sets the folders to mode 700 and the database file to mode 600 when it creates them. If you set the permissions yourself, use those modes, and use ' . self::code($user) . ', not root.');
        $html .= '<pre class="setup-help__pre">' . Html::e(self::commands($user, $dirs)) . '</pre>';
        $html .= self::para('While the site is open, SQLite may add ' . self::code('adlexone.sqlite-wal') . ' and ' . self::code('adlexone.sqlite-shm') . ' beside the database. They are part of the live database. Copy them with the database if you take a manual backup.');

        return $html;
    }

    private static function helpName(): string
    {
        return self::para('This name is shown on the sign-in page and in the header after sign-in. It is not a username.')
            . self::para('You can use the organisation name, the name of the system, or leave Adlexone. For example, Health. Use 80 characters or fewer.');
    }

    private static function helpUrl(): string
    {
        return self::para('For example, https://health.example.com. Leave this blank and Adlexone uses the address in the browser. Set it when a proxy or load balancer sits in front of the site.');
    }

    private static function helpPassword(): string
    {
        $html = self::para('The password is stored as a hash in the SQLite file. It is not written into the settings.');
        if (DefaultAccount::allowed()) {
            $html .= self::para('The prefilled password is ' . self::code(DefaultAccount::PASSWORD) . '. It is only accepted from this machine or a private address. Any other password must be at least 8 characters.');
        } else {
            $html .= self::para('Use at least 12 characters. The prefilled localhost password is not accepted from a public address.');
        }

        return $html;
    }

    private static function helpApplications(): string
    {
        return self::para('Sample applications installs Publishing, Matters, Conveyancing, Claims, Repairs, Complaints, Committee, Audit, Incidents, Candidates, and Contracts, with example records. You can delete them. Deleting one does not install it again.')
            . self::para('Publishing (Example) installs only that application, with its fields, rules, guide, and sample records.')
            . self::para('No applications leaves the home empty. After you sign in, you create an application.');
    }

    private static function helpToken(string $path): string
    {
        return self::para('Anyone who can open this page before an administrator exists could create the account. The token proves you can read a file beside the database.')
            . self::para('The file is ' . self::code($path) . '. This page does not show the value. The file is removed when the account is created.');
    }

    private static function para(string $html): string
    {
        return '<p class="setup-help__text">' . $html . '</p>';
    }

    private static function code(string $text): string
    {
        return '<code>' . Html::e($text) . '</code>';
    }

    /**
     * @param list<string> $dirs
     */
    private static function commands(string $user, array $dirs): string
    {
        $dirs = array_values(array_filter($dirs, static fn (string $dir): bool => $dir !== '' && $dir !== '.'));
        $lines = [];
        foreach ($dirs as $dir) {
            $lines[] = 'mkdir -p ' . escapeshellarg($dir);
        }
        if ($dirs !== []) {
            $lines[] = 'chown -R ' . escapeshellarg($user) . ' ' . implode(' ', array_map('escapeshellarg', $dirs));
            $lines[] = 'chmod 700 ' . implode(' ', array_map('escapeshellarg', $dirs));
        }

        return implode("\n", $lines);
    }

    private static function render(
        string $title,
        string $lead,
        string $error,
        string $form,
        string $steps,
        string $notice = '',
        string $headingAction = ''
    ): void {
        Page::current()->useLayout('setup', [
            'title' => $title,
            'documentTitle' => $title . ' — Set up Adlexone',
            'lead' => $lead,
            'error' => $error,
            'notice' => $notice,
            'helpHtml' => '',
            'formHtml' => $form,
            'installer' => true,
            'stepsHtml' => $steps,
            'headingAction' => $headingAction,
        ]);
    }

    /**
     * @param array<string, string> $paths
     */
    private static function dropStalePlacement(array $paths): string
    {
        $state = self::state();
        if ($state['placement'] === '') {
            return '';
        }
        if ($state['placement'] === InstallLayout::detect($paths['install'])) {
            return '';
        }
        unset($_SESSION[self::SESSION_KEY]);

        return 'The document root changed. Confirm it again.';
    }

    private static function currentStep(): string
    {
        $requested = (string) ($_GET['step'] ?? self::STEP_ROOT);
        if (!in_array($requested, [self::STEP_ROOT, self::STEP_STORAGE, self::STEP_ACCOUNT], true)) {
            $requested = self::STEP_ROOT;
        }
        $state = self::state();
        if ($state['placement'] === '') {
            return self::STEP_ROOT;
        }
        if ($requested === self::STEP_ACCOUNT && $state['storage'] === '') {
            return self::STEP_STORAGE;
        }
        if ($requested === self::STEP_ROOT) {
            return self::STEP_ROOT;
        }

        return $requested;
    }

    /**
     * @return array{config: string, sqlite: string, attachments: string, database_dir: string}
     */
    private static function chosenPaths(): array
    {
        $state = self::state();
        if ($state['shape'] === 'legacy') {
            return InstallLayout::legacyPaths($state['storage']);
        }

        return InstallLayout::storagePaths($state['storage']);
    }

    /**
     * @return array{placement: string, storage: string, shape: string, locked: bool, passed: string}
     */
    private static function state(): array
    {
        $raw = $_SESSION[self::SESSION_KEY] ?? null;
        $raw = is_array($raw) ? $raw : [];

        return [
            'placement' => (string) ($raw['placement'] ?? ''),
            'storage' => (string) ($raw['storage'] ?? ''),
            'shape' => (string) ($raw['shape'] ?? 'children'),
            'locked' => (bool) ($raw['locked'] ?? false),
            'passed' => (string) ($raw['passed'] ?? ''),
        ];
    }

    /**
     * @param array{placement: string, storage: string, shape: string, locked: bool, passed: string} $state
     */
    private static function saveState(array $state): void
    {
        $_SESSION[self::SESSION_KEY] = $state;
    }

    private static function theme(): void
    {
        if (!defined('SET_DEFAULT_THEME')) {
            define('SET_DEFAULT_THEME', 'inlay-stone');
        }
        if (!defined('SET_DEFAULT_LANGUAGE')) {
            define('SET_DEFAULT_LANGUAGE', 'English');
        }
        if (!defined('SET_THEME')) {
            define('SET_THEME', Theme::forLogin());
        }
    }
}
