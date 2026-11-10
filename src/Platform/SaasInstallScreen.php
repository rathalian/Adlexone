<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Http\Request;

use Adlexone\Auth\Csrf;
use Adlexone\Http\Page;

/**
 * First visit that turns this copy into a host of customer sites.
 *
 * The self-hosted installer stays on SetupScreen. This page is ./?host=install,
 * and ./?install=host is the same page. It runs only while INLAY_STORES is empty.
 */
final class SaasInstallScreen
{
    private const SESSION_KEY = 'adlexone_host_install';

    private const STEP_ROOT = 'root';

    private const STEP_STORES = 'stores';

    private const STEP_HOST = 'host';

    private const STEP_ACCOUNT = 'account';

    public static function handles(string $stores, string $installQuery, string $hostQuery = ''): bool
    {
        if (strtolower(trim($hostQuery)) === 'manage') {
            return false;
        }
        $asked = $installQuery === 'host' || strtolower(trim($hostQuery)) === 'install';

        return $stores === '' && $asked;
    }

    public static function blockReason(string $install): string
    {
        $settings = Runtime::saasSettings($install);
        if ($settings['stores'] !== '' || $settings['stores_from_env']) {
            return 'This server is already in SaaS mode.';
        }
        if ($settings['host_from_env']) {
            return 'The environment already sets INLAY_OPERATOR_HOST. Set INLAY_STORES in the environment as well. This page does not write config/platform.json.';
        }
        $sqlite = InstallLayout::resolve($install)['sqlite'];
        if (!Runtime::needsSetup($sqlite)) {
            return 'This copy is already one site. The database is ' . $sqlite . '. This installer does not move that site into a customer folder.';
        }

        return '';
    }

    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   user: string,
     *   directory: string,
     *   created: bool,
     *   platform_db: string,
     *   checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>
     * }
     */
    public static function assessStores(
        string $install,
        string $directory,
        string $placement,
        ?string $documentRoot = null
    ): array {
        $probe = InstallLayout::probe($install, $directory, $placement, $documentRoot, true);
        $result = $probe;
        $result['platform_db'] = '';
        $dir = $result['directory'];
        $doc = InstallLayout::publicRoot($documentRoot);
        $where = $doc !== '' ? $doc : 'the web folder';
        if ($dir !== '' && $doc !== '' && InstallLayout::inWebFolder($dir, $doc)) {
            $detail = 'This folder is inside the web folder (' . $where . '). The stores directory has to sit outside the website, because it holds every customer’s database, settings, and uploads.';
            $result['checks'][] = self::check('outside', 'Outside the website', false, $detail);
            $result['ok'] = false;
            $result['message'] = $detail;
            self::removeCreated($result);

            return $result;
        }
        if ($dir === '' || !$result['ok']) {
            return $result;
        }
        $result['checks'][] = self::check('outside', 'Outside the website', true, 'This folder is outside the web folder (' . $where . ').');

        $envStorage = InstallLayout::environmentStorage($install);
        $storageRoot = $envStorage['locked'] && $envStorage['path'] !== ''
            ? $envStorage['path']
            : InstallLayout::savedStorage($install);
        if ($storageRoot !== '' && (InstallLayout::contains($storageRoot, $dir) || InstallLayout::canonical($storageRoot) === InstallLayout::canonical($dir))) {
            $detail = 'That folder is inside the self-hosted storage directory (' . $storageRoot . '). Choose a different folder.';
            $result['checks'][] = self::check('storage', 'Separate from this site', false, $detail);
            $result['ok'] = false;
            $result['message'] = $detail;
            self::removeCreated($result);

            return $result;
        }
        $result['checks'][] = self::check('storage', 'Separate from this site', true, '');

        if (is_file($dir . DIRECTORY_SEPARATOR . 'adlexone.sqlite')) {
            $detail = $dir . DIRECTORY_SEPARATOR . 'adlexone.sqlite is already in this folder. Choose the directory that will hold customer folders, not one site’s folder.';
            $result['checks'][] = self::check('site', 'Customer folders', false, $detail);
            $result['ok'] = false;
            $result['message'] = $detail;
            self::removeCreated($result);

            return $result;
        }
        $result['checks'][] = self::check('site', 'Customer folders', true, '');

        $platformDb = self::platformDatabase($install, $dir);
        $result['platform_db'] = $platformDb;
        $dbCheck = self::assessPlatformDatabase($platformDb, $dir, $documentRoot);
        $result['checks'][] = $dbCheck;
        if ($dbCheck['state'] === 'fail') {
            $result['ok'] = false;
            $result['message'] = $dbCheck['detail'];
            self::removeCreated($result);
        }

        return $result;
    }

    /**
     * The installer token sits one folder above the document root, outside the website.
     */
    public static function installTokenPath(string $install): string
    {
        $doc = InstallLayout::canonical((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $anchor = $doc !== '' ? $doc : rtrim($install, '/\\');
        $parent = dirname($anchor);
        if ($parent === '' || $parent === '.' || $parent === $anchor) {
            $parent = rtrim($install, '/\\');
        }

        return $parent . DIRECTORY_SEPARATOR . 'adlexone-install-token.txt';
    }

    public static function platformDatabase(string $install, string $stores): string
    {
        $settings = Runtime::saasSettings($install);
        if ($settings['db_from_env'] && $settings['platform_db'] !== '') {
            return $settings['platform_db'];
        }

        return rtrim($stores, '/\\') . DIRECTORY_SEPARATOR . 'platform.sqlite';
    }

    public static function handle(): void
    {
        self::theme();
        $paths = Runtime::paths();
        $blocked = self::blockReason($paths['install']);
        if ($blocked !== '') {
            self::render('SaaS installer', $blocked, '', '', '', '');

            return;
        }

        $error = '';
        $notice = '';
        $tokenPath = self::installTokenPath($paths['install']);
        if (InstallLayout::inWebFolder($tokenPath)) {
            self::render(
                'SaaS installer',
                InstallChrome::tokenBlockedMessage(),
                '',
                '',
                '',
                ''
            );

            return;
        }
        try {
            SetupToken::issue($tokenPath);
        } catch (\Throwable $e) {
            self::render('SaaS installer', $e->getMessage(), '', '', '', '');

            return;
        }
        /** @var array{ok: bool, message: string, user: string, directory: string, created: bool, platform_db: string, checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>}|null $report */
        $report = null;
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
                    if ($action === 'check_stores' || $action === 'save_stores') {
                        $report = self::takeStores($paths, $action === 'save_stores');
                    } elseif ($action === 'save_host') {
                        self::saveHost($paths);

                        return;
                    }
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $step = self::currentStep();
        if ($report !== null) {
            $step = self::STEP_STORES;
            if ($report['ok']) {
                $notice = $report['message'];
                if ($report['created']) {
                    $notice .= ' The folder did not exist, so PHP created it.';
                }
                $notice .= ' Continue to set the operator hostname.';
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
        if ($choice !== InstallLayout::detect($paths['install'])) {
            throw new \RuntimeException(InstallLayout::placementMessage($paths['install']));
        }
        $state = self::state();
        $state['placement'] = $choice;
        self::saveState($state);
        header('Location: ?install=host&step=stores', true, 303);
        exit;
    }

    /**
     * @param array<string, string> $paths
     * @return array{ok: bool, message: string, user: string, directory: string, created: bool, platform_db: string, checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>}|null
     */
    private static function takeStores(array $paths, bool $advance): ?array
    {
        $state = self::state();
        if ($state['placement'] === '') {
            throw new \RuntimeException('Confirm the document root first.');
        }
        $path = trim((string) (Request::postedValue('stores_path') ?? ''));
        $report = self::assessStores($paths['install'], $path, $state['placement']);
        $already = $report['ok'] && $state['passed'] === $report['directory'];
        $state['passed'] = $report['ok'] ? $report['directory'] : '';
        if ($advance && $already && $report['platform_db'] !== '') {
            $state['stores'] = $report['directory'];
            $state['platform_db'] = $report['platform_db'];
            self::saveState($state);
            header('Location: ?install=host&step=host', true, 303);
            exit;
        }
        self::saveState($state);

        return $report;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function saveHost(array $paths): void
    {
        $state = self::state();
        if ($state['placement'] === '' || $state['stores'] === '') {
            throw new \RuntimeException('Confirm the document root, then choose the stores directory.');
        }
        if ($state['placement'] !== InstallLayout::detect($paths['install'])) {
            throw new \RuntimeException(InstallLayout::placementMessage($paths['install']));
        }
        $host = SiteUsage::assertOperatorHost((string) (Request::postedValue('operator_host') ?? ''));
        $report = self::assessStores($paths['install'], $state['stores'], $state['placement']);
        if (!$report['ok'] || $report['platform_db'] === '') {
            throw new \RuntimeException($report['message'] !== '' ? $report['message'] : 'This folder cannot be used yet.');
        }
        InstallLayout::rememberSaaS($paths['install'], $report['directory'], $host, $report['platform_db']);
        SetupToken::clear(self::installTokenPath($paths['install']));
        unset($_SESSION[self::SESSION_KEY], $_SESSION[InstallChrome::PROVEN_KEY]);
        if (strcasecmp(Runtime::requestHost(), $host) === 0) {
            header('Location: ./', true, 303);
            exit;
        }
        header('Location: https://' . $host . '/', true, 303);
        exit;
    }

    /**
     * @param array{created?: bool, directory?: string} $result
     */
    private static function removeCreated(array $result): void
    {
        $directory = (string) ($result['directory'] ?? '');
        if (empty($result['created']) || $directory === '' || !is_dir($directory)) {
            return;
        }
        @rmdir($directory);
    }

    /**
     * @return array{id: string, label: string, ok: bool, detail: string, state: string}
     */
    private static function assessPlatformDatabase(string $platformDb, string $stores, ?string $documentRoot): array
    {
        $absolute = InstallLayout::absolute($platformDb);
        if ($absolute === null) {
            return self::check('platform', 'Operator database', false, 'The operator database needs a full path.');
        }
        if (InstallLayout::inWebFolder($absolute, $documentRoot)) {
            return self::check(
                'platform',
                'Operator database',
                false,
                'The operator database ' . $absolute . ' has to sit outside the website.'
            );
        }
        $parent = dirname($absolute);
        if (!is_dir($parent)) {
            return self::check(
                'platform',
                'Operator database',
                false,
                'Create ' . $parent . ', then check again. This page does not create that folder.'
            );
        }

        return self::check('platform', 'Operator database', true, 'The operator database will be ' . $absolute . '.');
    }

    /**
     * @return array{id: string, label: string, ok: bool, detail: string, state: string}
     */
    private static function check(string $id, string $label, bool $ok, string $detail): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'ok' => $ok,
            'detail' => $detail,
            'state' => $ok ? 'pass' : 'fail',
        ];
    }

    private static function currentStep(): string
    {
        $state = self::state();
        $requested = (string) (Request::queryValue('step') ?? '');
        if ($state['placement'] === '') {
            return self::STEP_ROOT;
        }
        if ($state['stores'] === '') {
            return $requested === self::STEP_ROOT ? self::STEP_ROOT : self::STEP_STORES;
        }
        if (in_array($requested, [self::STEP_ROOT, self::STEP_STORES, self::STEP_HOST], true)) {
            return $requested;
        }

        return self::STEP_HOST;
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
        $state['placement'] = '';
        $state['stores'] = '';
        $state['passed'] = '';
        $state['platform_db'] = '';
        self::saveState($state);

        return InstallLayout::placementMessage($paths['install']);
    }

    /**
     * @return array{placement: string, stores: string, passed: string, platform_db: string}
     */
    private static function state(): array
    {
        $state = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($state)) {
            $state = [];
        }

        return [
            'placement' => (string) ($state['placement'] ?? ''),
            'stores' => (string) ($state['stores'] ?? ''),
            'passed' => (string) ($state['passed'] ?? ''),
            'platform_db' => (string) ($state['platform_db'] ?? ''),
        ];
    }

    /**
     * @param array{placement: string, stores: string, passed: string, platform_db: string} $state
     */
    private static function saveState(array $state): void
    {
        $_SESSION[self::SESSION_KEY] = $state;
    }

    /**
     * @param array<string, string> $paths
     * @param array{ok: bool, message: string, user: string, directory: string, created: bool, platform_db: string, checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>}|null $report
     */
    private static function renderStep(array $paths, string $step, string $error, string $notice, ?array $report): void
    {
        $focus = $error === '';
        if ($step === self::STEP_STORES) {
            self::render(
                'Stores directory',
                'Choose the directory that will hold one folder per customer site. PHP is running as ' . Runtime::phpUser() . '. That account must be able to create, read, update, and delete files in the directory, and a browser must not be able to read it.',
                $error,
                self::storesForm($paths, $report, $focus),
                self::steps(self::STEP_STORES),
                $notice,
                self::detailsButton('install-help-stores')
            );

            return;
        }
        if ($step === self::STEP_HOST) {
            self::render(
                'Operator hostname',
                'This hostname is the operator site. The operator account is created on that hostname after this step.',
                $error,
                self::hostForm($focus),
                self::steps(self::STEP_HOST),
                $notice,
                self::detailsButton('install-help-host')
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
    private static function placementForm(array $paths, bool $focus): string
    {
        return InstallChrome::placementForm(
            $paths,
            $focus,
            '?install=host&step=' . self::STEP_ROOT,
            'host_',
            HelpText::hostInstall($paths),
            self::installTokenPath($paths['install'])
        );
    }

    /**
     * @param array<string, string> $paths
     * @param array{ok: bool, message: string, user: string, directory: string, created: bool, platform_db: string, checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>}|null $report
     */
    private static function storesForm(array $paths, ?array $report, bool $focus): string
    {
        $state = self::state();
        $posted = trim((string) (Request::postedValue('stores_path') ?? ''));
        if ($posted !== '') {
            $path = $posted;
        } elseif ($state['stores'] !== '') {
            $path = $state['stores'];
        } elseif ($state['passed'] !== '') {
            $path = $state['passed'];
        } else {
            $path = InstallLayout::suggestStores($paths['install']);
        }
        if ($report !== null && $report['directory'] !== '') {
            $path = $report['directory'];
        }
        $preview = InstallLayout::absolute($path) ?? '';
        if ($report !== null && $report['directory'] !== '') {
            $preview = $report['directory'];
        }
        $platformDb = $preview !== '' ? self::platformDatabase($paths['install'], $preview) : '';

        $html = self::openForm(self::STEP_STORES);
        $html .= self::field(
            'Stores directory',
            'stores_path',
            $path,
            InstallChrome::outsideRootHelp('stores directory', InstallLayout::suggestStores($paths['install'])),
            $focus
        );
        if ($preview !== '') {
            $html .= '<p class="install-section">What this directory holds</p>';
            $html .= self::facts([
                'Customer folders' => $preview . DIRECTORY_SEPARATOR . '{slug}' . DIRECTORY_SEPARATOR,
                'Operator database' => $platformDb,
            ]);
        }
        if ($report !== null) {
            $html .= self::checks($report['checks']);
        }
        $ready = $preview !== '' && $state['passed'] === $preview;
        $html .= $ready
            ? self::actions('Continue', 'save_stores', 'Check again', 'check_stores', '?install=host&step=root')
            : self::actions('Check this folder', 'check_stores', '', '', '?install=host&step=root');
        $html .= '</form>';
        $html .= self::dialog('install-help-stores', 'Stores directory', HelpText::hostInstall($paths, $preview, $platformDb));

        return $html;
    }

    private static function hostForm(bool $focus): string
    {
        $state = self::state();
        $posted = strtolower(trim((string) (Request::postedValue('operator_host') ?? '')));
        $host = $posted !== '' ? $posted : Runtime::requestHost();
        $html = self::openForm(self::STEP_HOST);
        $html .= self::facts([
            'Stores directory' => $state['stores'],
            'Operator database' => $state['platform_db'],
        ]);
        $html .= InstallChrome::field(
            'Operator hostname',
            'operator_host',
            'text',
            $host,
            true,
            'The address you will use to manage customer sites. For example, operate.example.com. localhost is accepted on this machine.',
            '',
            '',
            $focus,
            'spellcheck="false" autocapitalize="off" autocorrect="off"',
            'host_'
        );
        $html .= self::actions('Save and continue', 'save_host', '', '', '?install=host&step=stores');
        $html .= '</form>';
        $html .= self::dialog('install-help-host', 'Operator hostname', HelpText::hostInstall(Runtime::paths(), $state['stores'], $state['platform_db']));

        return $html;
    }

    private static function steps(string $current): string
    {
        return self::stepRail($current, true);
    }

    /**
     * The operator account page is the fourth step. Earlier steps are plain,
     * because the installer route is closed once the stores directory is saved.
     */
    public static function accountSteps(): string
    {
        return self::stepRail(self::STEP_ACCOUNT, false);
    }

    private static function stepRail(string $current, bool $linkDone): string
    {
        $plain = [self::STEP_ACCOUNT];
        if (!$linkDone) {
            $plain = [self::STEP_ROOT, self::STEP_STORES, self::STEP_HOST, self::STEP_ACCOUNT];
        }

        return InstallChrome::steps([
            self::STEP_ROOT => 'Document root',
            self::STEP_STORES => 'Stores directory',
            self::STEP_HOST => 'Operator hostname',
            self::STEP_ACCOUNT => 'Operator account',
        ], $current, '?install=host&step=', $plain, 'install-steps install-steps--four', true);
    }

    private static function field(string $label, string $name, string $value, string $help, bool $focus): string
    {
        return InstallChrome::field(
            $label,
            $name,
            'text',
            $value,
            true,
            $help,
            '',
            '',
            $focus,
            'spellcheck="false" autocapitalize="off" autocorrect="off"',
            'host_'
        );
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
            '?install=host&step=' . $step,
            self::installTokenPath((string) (Runtime::paths()['install'] ?? '')),
            'host_'
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
        string $notice,
        string $headingAction = ''
    ): void {
        Page::current()->useLayout('setup', [
            'title' => $title,
            'documentTitle' => $title . ' — SaaS installer',
            'kicker' => 'SaaS installer',
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
