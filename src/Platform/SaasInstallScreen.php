<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Auth\Csrf;
use Adlexone\Html\Html;
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
        if ($result['directory'] === '') {
            return $result;
        }
        $dir = $result['directory'];
        $doc = InstallLayout::canonical($documentRoot ?? (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $where = $doc !== '' ? $doc : 'the web folder';
        if (InstallLayout::inWebFolder($dir, $documentRoot)) {
            $detail = 'This folder is inside the web folder (' . $where . '). The stores directory has to sit outside the website, because it holds every customer’s database, settings, and uploads.';
            $result['checks'][] = self::check('outside', 'Outside the website', false, $detail);
            $result['ok'] = false;
            $result['message'] = $detail;
            self::removeCreated($result);

            return $result;
        }
        $result['checks'][] = self::check('outside', 'Outside the website', true, 'This folder is outside the web folder (' . $where . ').');

        $storageRoot = (string) (InstallLayout::resolve($install)['root'] ?? '');
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

    public static function platformDatabase(string $install, string $stores): string
    {
        $settings = Runtime::saasSettings($install);
        if ($settings['db_from_env'] && $settings['platform_db'] !== '') {
            return $settings['platform_db'];
        }

        return dirname(rtrim($stores, '/\\')) . DIRECTORY_SEPARATOR . 'platform.sqlite';
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
                $action = (string) ($_POST['setup_action'] ?? '');
                try {
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
        $choice = (string) ($_POST['placement'] ?? '');
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
        $path = trim((string) ($_POST['stores_path'] ?? ''));
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
        $host = SiteUsage::assertOperatorHost((string) ($_POST['operator_host'] ?? ''));
        $report = self::assessStores($paths['install'], $state['stores'], $state['placement']);
        if (!$report['ok'] || $report['platform_db'] === '') {
            throw new \RuntimeException($report['message'] !== '' ? $report['message'] : 'This folder cannot be used yet.');
        }
        InstallLayout::rememberSaaS($paths['install'], $report['directory'], $host, $report['platform_db']);
        unset($_SESSION[self::SESSION_KEY]);
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
        $user = Runtime::phpUser();
        $absolute = InstallLayout::absolute($platformDb);
        if ($absolute === null) {
            return self::check('platform', 'Operator database', false, 'The operator database needs a full path.');
        }
        if (InstallLayout::contains($stores, $absolute) || InstallLayout::inWebFolder(dirname($absolute), $documentRoot)) {
            return self::check(
                'platform',
                'Operator database',
                false,
                'The operator database ' . $absolute . ' has to sit outside the stores directory and outside the website.'
            );
        }
        $parent = dirname($absolute);
        if (!PlatformDb::mayRestrictDirectory($parent)) {
            return self::check(
                'platform',
                'Operator database',
                false,
                'The operator database would be ' . $absolute . '. ' . $parent . ' is a shared directory and cannot be made private. Put the stores directory inside a folder this account owns, such as /home/health/adlexone-stores.'
            );
        }
        $created = false;
        if (!is_dir($parent)) {
            if (!@mkdir($parent, 0700, true) && !is_dir($parent)) {
                return self::check(
                    'platform',
                    'Operator database',
                    false,
                    'PHP, running as ' . $user . ', cannot create ' . $parent . '.'
                );
            }
            $created = true;
        }
        $probe = $parent . DIRECTORY_SEPARATOR . '.adlexone-probe-' . bin2hex(random_bytes(4));
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
            if ($created && !$ok) {
                @rmdir($parent);
            }
        }
        if (!$ok) {
            return self::check(
                'platform',
                'Operator database',
                false,
                'PHP, running as ' . $user . ', cannot create ' . $absolute . '.'
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
        $requested = (string) ($_GET['step'] ?? '');
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
        $html = self::openForm(self::STEP_ROOT);
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
        $html .= self::dialog('install-help-root', 'Document root', HelpText::hostInstall($paths));

        return $html;
    }

    /**
     * @param array<string, string> $paths
     * @param array{ok: bool, message: string, user: string, directory: string, created: bool, platform_db: string, checks: list<array{id: string, label: string, ok: bool, detail: string, state: string}>}|null $report
     */
    private static function storesForm(array $paths, ?array $report, bool $focus): string
    {
        $state = self::state();
        $posted = trim((string) ($_POST['stores_path'] ?? ''));
        if ($posted !== '') {
            $path = $posted;
        } elseif ($state['stores'] !== '') {
            $path = $state['stores'];
        } elseif ($state['passed'] !== '') {
            $path = $state['passed'];
        } else {
            $path = '';
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
            'Full path on this server, outside the website. For example, /home/health/adlexone-stores.',
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
        $posted = strtolower(trim((string) ($_POST['operator_host'] ?? '')));
        $host = $posted !== '' ? $posted : Runtime::requestHost();
        $html = self::openForm(self::STEP_HOST);
        $html .= self::facts([
            'Stores directory' => $state['stores'],
            'Operator database' => $state['platform_db'],
        ]);
        $html .= '<div class="field">';
        $html .= '<label class="login-label label" for="host_operator_host">Operator hostname</label>';
        $html .= '<input class="input" type="text" id="host_operator_host" name="operator_host" value="' . Html::e($host) . '" required'
            . ($focus ? ' autofocus' : '')
            . ' spellcheck="false" autocapitalize="off" autocorrect="off">';
        $html .= '<p class="form-help">The address you will use to manage customer sites. For example, operate.example.com. localhost is accepted on this machine.</p>';
        $html .= '</div>';
        $html .= self::actions('Save and continue', 'save_host', '', '', '?install=host&step=stores');
        $html .= '</form>';
        $html .= self::dialog('install-help-host', 'Operator hostname', HelpText::hostInstall(Runtime::paths(), $state['stores'], $state['platform_db']));

        return $html;
    }

    private static function steps(string $current): string
    {
        $items = [
            self::STEP_ROOT => 'Document root',
            self::STEP_STORES => 'Stores directory',
            self::STEP_HOST => 'Operator hostname',
            self::STEP_ACCOUNT => 'Operator account',
        ];
        $order = array_keys($items);
        $currentIndex = (int) array_search($current, $order, true);
        $html = '<nav aria-label="Setup steps"><ol class="install-steps install-steps--four">';
        foreach ($items as $id => $label) {
            $index = (int) array_search($id, $order, true);
            $class = $index === $currentIndex ? 'is-current' : ($index < $currentIndex ? 'is-done' : 'is-ahead');
            $inner = '<span class="install-steps__num">' . ($index + 1) . '</span><span class="install-steps__label">' . Html::e($label) . '</span>';
            if ($index < $currentIndex && $id !== self::STEP_ACCOUNT) {
                $item = '<a class="install-steps__item" href="?install=host&amp;step=' . Html::e($id) . '">' . $inner . '</a>';
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
    private static function options(string $legend, string $name, array $options, string $selected, bool $focus): string
    {
        $html = '<fieldset class="install-choice">';
        $html .= '<legend class="login-label label">' . Html::e($legend) . '</legend>';
        $first = true;
        foreach ($options as $value => [$label, $help]) {
            $id = 'host_' . $name . '_' . $value;
            $checked = $selected === (string) $value;
            $html .= '<label class="install-option" for="' . Html::e($id) . '">'
                . '<input class="radio" type="radio" name="' . Html::e($name) . '" id="' . Html::e($id) . '" value="' . Html::e((string) $value) . '"'
                . ($checked ? ' checked' : '')
                . ($focus && ($checked || ($selected === '' && $first)) ? ' autofocus' : '')
                . ' required>'
                . '<span class="install-option__text"><span class="install-option__title">' . Html::e($label) . '</span>'
                . '<span class="form-help">' . Html::e($help) . '</span></span></label>';
            $first = false;
        }

        return $html . '</fieldset>';
    }

    private static function field(string $label, string $name, string $value, string $help, bool $focus): string
    {
        $id = 'host_' . $name;
        $html = '<div class="field">';
        $html .= '<label class="login-label label" for="' . Html::e($id) . '">' . Html::e($label) . '</label>';
        $html .= '<input class="input install-path" type="text" id="' . Html::e($id) . '" name="' . Html::e($name) . '" value="' . Html::e($value) . '" required'
            . ($focus ? ' autofocus' : '')
            . ' spellcheck="false" autocapitalize="off" autocorrect="off">';
        $html .= '<p class="form-help">' . Html::e($help) . '</p>';
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
            if ($check['detail'] !== '') {
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

    private static function openForm(string $step): string
    {
        return '<form method="post" action="?install=host&amp;step=' . Html::e($step) . '" class="login-form install-form" autocomplete="off">' . Csrf::field();
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
