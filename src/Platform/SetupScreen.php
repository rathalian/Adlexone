<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Http\Request;

use Adlexone\Auth\Csrf;
use Adlexone\Html\Html;
use Adlexone\Http\Page;

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

        if ($hosted) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                if (!Csrf::valid()) {
                    $error = 'This form expired. Reload the page and try again.';
                } else {
                    try {
                        self::installHosted($paths);

                        return;
                    } catch (\Throwable $e) {
                        $error = $e->getMessage();
                    }
                }
            }
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

        $tokenPath = SaasInstallScreen::installTokenPath($paths['install']);
        if (InstallLayout::inWebFolder($tokenPath)) {
            self::render('Self-hosted installer', InstallChrome::tokenBlockedMessage(), '', '', '', '');

            return;
        }
        try {
            SetupToken::issue($tokenPath);
        } catch (\Throwable $e) {
            self::render('Self-hosted installer', $e->getMessage(), '', '', '', '');

            return;
        }

        $stale = self::dropStalePlacement($paths);
        if ($stale !== '') {
            $error = $stale;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::valid()) {
                $error = 'This form expired. Reload the page and try again.';
            } else {
                $action = (string) (Request::postedValue('setup_action') ?? '');
                try {
                    InstallChrome::proveToken($tokenPath);
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
        self::renderStep($paths, $step, $error, $notice, $report);
    }

    /**
     * @param array<string, string> $paths
     */
    private static function savePlacement(array $paths): void
    {
        $choice = (string) (Request::postedValue('placement') ?? '');
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
            $path = trim((string) (Request::postedValue('storage_path') ?? ''));
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
        $admin = self::adminFromPost();
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
        SetupToken::clear(SaasInstallScreen::installTokenPath($paths['install']));
        unset($_SESSION[self::SESSION_KEY], $_SESSION[InstallChrome::PROVEN_KEY]);
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
    private static function adminFromPost(string $tokenPath = ''): array
    {
        $site = trim((string) (Request::postedValue('site_name') ?? ''));
        $username = trim((string) (Request::postedValue('user_name') ?? ''));
        $password = (string) (Request::postedValue('password') ?? '');
        $confirm = (string) (Request::postedValue('password_confirm') ?? '');
        $email = trim((string) (Request::postedValue('email') ?? ''));
        $first = trim((string) (Request::postedValue('first_name') ?? ''));
        $last = trim((string) (Request::postedValue('last_name') ?? ''));
        $public = trim((string) (Request::postedValue('public_url') ?? ''));

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
        if ($tokenPath !== '' && !SetupToken::check((string) (Request::postedValue('setup_token') ?? ''), $tokenPath)) {
            throw new \RuntimeException('The setup token does not match the file beside the database. Open that file on the server and enter the value.');
        }
        $starting = (string) (Request::postedValue('starting_applications') ?? '');
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
                $notice,
                self::detailsButton('install-help-account')
            );

            return;
        }
        self::render(
            'Document root',
            'The selected choice is the document root this server reported.',
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
            self::detailsButton('install-help-files'),
            false
        );
    }

    /**
     * @param array<string, string> $paths
     */
    private static function placementForm(array $paths, bool $focus): string
    {
        return InstallChrome::placementForm(
            $paths,
            $focus,
            '?step=' . self::STEP_ROOT,
            'setup_',
            HelpText::siteInstall($paths),
            SaasInstallScreen::installTokenPath($paths['install'])
        );
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
            $posted = trim((string) (Request::postedValue('storage_path') ?? ''));
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

        $html = self::openForm(self::STEP_STORAGE);
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
                InstallChrome::outsideRootHelp('storage directory', InstallLayout::suggest($install)),
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
            if (InstallLayout::inWebFolder($preview)) {
                $html .= '<p class="install-match is-bad">This folder is inside the web folder. That is strongly recommended against. The database and uploads should sit outside the website. Setup requests a trial file from the website and continues only when that request does not return the file.</p>';
            }
        }
        if ($report !== null) {
            $html .= self::checks($report['checks']);
        }
        $ready = $preview !== null && $preview !== '' && self::state()['passed'] === $preview;
        $html .= $ready
            ? self::actions('Continue', 'save_storage', 'Check again', 'check_storage', '?step=' . self::STEP_ROOT)
            : self::actions('Check this folder', 'check_storage', '', '', '?step=' . self::STEP_ROOT);
        $html .= '</form>';
        $html .= self::dialog(
            'install-help-storage',
            'Storage directory',
            HelpText::siteInstall(
                $paths,
                $preview !== null && $preview !== '' ? $preview : InstallLayout::suggest($install),
                $shape,
                !$env['locked']
            )
        );

        return $html;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function accountForm(array $paths, bool $hosted, bool $focus): string
    {
        if (Request::hasPostedKey('site_name')) {
            $site = (string) Request::postedValue('site_name');
        } else {
            $label = trim((string) ($paths['store_label'] ?? ''));
            $site = $label !== '' ? $label : 'Adlexone';
        }
        $tokenPath = $hosted ? SetupToken::path() : '';
        $passwordHelp = DefaultAccount::allowed()
            ? 'At least 8 characters. The prefilled password is for this machine only.'
            : 'At least 12 characters.';

        $html = $hosted
            ? InstallChrome::openForm('?step=' . self::STEP_ACCOUNT)
            : self::openForm(self::STEP_ACCOUNT);
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
            (string) (Request::postedValue('public_url') ?? ''),
            false,
            'Leave this blank unless a proxy sits in front of the site.',
            'install-help-url',
            'url'
        );
        $html .= '<p class="install-section">Administrator</p>';
        $html .= self::field('Your name', 'first_name', 'text', (string) (Request::postedValue('first_name') ?? ''), false, '', '', 'given-name');
        $html .= self::field('Last name', 'last_name', 'text', (string) (Request::postedValue('last_name') ?? ''), false, '', '', 'family-name');
        $html .= self::field(
            'Email',
            'email',
            'email',
            (string) (Request::postedValue('email') ?? ''),
            false,
            'Saved on the account. Sign-in uses the username.',
            '',
            'email'
        );
        $html .= self::field(
            'Username',
            'user_name',
            'text',
            DefaultAccount::postedUsername(Request::postedValue('user_name') ?? null),
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
            DefaultAccount::postedPassword(Request::postedValue('password') ?? null),
            true,
            $passwordHelp,
            'install-help-password',
            'new-password'
        );
        $html .= self::field(
            'Confirm password',
            'password_confirm',
            'password',
            DefaultAccount::postedPassword(Request::postedValue('password_confirm') ?? null),
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
            (string) (Request::postedValue('starting_applications') ?? ''),
            false,
            'setup-apps-label'
        );
        if ($hosted) {
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
        }
        $html .= self::actions('Create the site', 'install', '', '', $hosted ? '' : '?step=' . self::STEP_STORAGE);
        $html .= '</form>';
        $html .= self::dialog('install-help-name', 'Login page application name', HelpText::loginName());
        $html .= self::dialog('install-help-url', 'Public URL', HelpText::publicUrl());
        $html .= self::dialog('install-help-password', 'Password', HelpText::accountPassword());
        $html .= self::dialog('install-help-apps', 'Starting applications', HelpText::startingApplications());
        if ($hosted) {
            $html .= self::dialog('install-help-token', 'Setup token', HelpText::customerToken($tokenPath));
        } else {
            $html .= self::dialog('install-help-account', 'Account', HelpText::siteInstall($paths));
        }

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
        return InstallChrome::steps([
            self::STEP_ROOT => 'Document root',
            self::STEP_STORAGE => 'Storage',
            self::STEP_ACCOUNT => 'Account',
        ], $current, '?step=');
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
        return InstallChrome::options($legend, $name, $options, $selected, $focus, 'setup_', $labelledBy);
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
        return InstallChrome::field($label, $name, $type, $value, $required, $help, $detailsId, $autocomplete, $focus, $attributes);
    }

    /**
     * @param list<array{id: string, label: string, ok: bool, detail: string, state: string}> $checks
     */
    private static function checks(array $checks): string
    {
        return InstallChrome::checks($checks);
    }

    /**
     * @param array<string, string> $rows
     */
    private static function facts(array $rows): string
    {
        return InstallChrome::facts($rows);
    }

    private static function openForm(string $step): string
    {
        return InstallChrome::openForm(
            '?step=' . $step,
            SaasInstallScreen::installTokenPath((string) (Runtime::paths()['install'] ?? '')),
            'setup_'
        );
    }

    private static function actions(
        string $primary,
        string $primaryValue,
        string $secondary = '',
        string $secondaryValue = '',
        string $backHref = ''
    ): string {
        return InstallChrome::actions($primary, $primaryValue, $secondary, $secondaryValue, $backHref);
    }

    private static function detailsButton(string $id): string
    {
        return InstallChrome::detailsButton($id);
    }

    private static function dialog(string $id, string $title, string $body): string
    {
        return InstallChrome::dialog($id, $title, $body);
    }

    private static function render(
        string $title,
        string $lead,
        string $error,
        string $form,
        string $steps,
        string $notice = '',
        string $headingAction = '',
        bool $selfHosted = true
    ): void {
        $name = $selfHosted ? 'Self-hosted installer' : 'Set up this site';
        Page::current()->useLayout('setup', [
            'title' => $title,
            'documentTitle' => $title . ' — ' . $name,
            'kicker' => $name,
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
        $requested = (string) (Request::queryValue('step') ?? '');
        $known = in_array($requested, [self::STEP_ROOT, self::STEP_STORAGE, self::STEP_ACCOUNT], true);
        $state = self::state();
        if (!$known) {
            if ($state['placement'] === '') {
                return self::STEP_ROOT;
            }
            if ($state['storage'] === '') {
                return self::STEP_STORAGE;
            }

            return self::STEP_ACCOUNT;
        }
        if ($state['placement'] === '') {
            return self::STEP_ROOT;
        }
        if ($requested === self::STEP_ACCOUNT && $state['storage'] === '') {
            return self::STEP_STORAGE;
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
            define('SET_DEFAULT_THEME', 'inlay-slate');
        }
        if (!defined('SET_DEFAULT_LANGUAGE')) {
            define('SET_DEFAULT_LANGUAGE', 'English');
        }
        if (!defined('SET_THEME')) {
            define('SET_THEME', 'inlay-slate');
        }
    }
}
