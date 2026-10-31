<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Auth\Csrf;
use Adlexone\Html\Html;
use Adlexone\Http\Page;

/**
 * Operator console. One login for the person who creates and retires customer stores.
 */
final class OperatorScreen
{
    /**
     * A real bcrypt hash so a missing username takes as long to reject as a wrong password.
     * It is not an account.
     */
    private const DUMMY_HASH = '$2y$10$TOgp5fleJ1tJfx1/SLOxIuDFLP5hVOQwqs1kvCfwHYkfEgOW9InKq';

    public static function manageUnavailable(): void
    {
        self::theme();
        self::privateResponse();
        Page::current()->useLayout('setup', [
            'title' => 'Customer sites',
            'documentTitle' => 'Customer sites — Site manager',
            'kicker' => 'Site manager',
            'lead' => 'This copy is not hosting customer sites. Open the SaaS installer to turn it into a host.',
            'error' => '',
            'notice' => '',
            'helpHtml' => '',
            'formHtml' => '<div class="install-actions"><a class="btn btn--primary" href="?host=install">Open the SaaS installer</a></div>',
            'installer' => true,
        ]);
    }

    public static function handle(): void
    {
        self::theme();
        self::privateResponse();
        $paths = Runtime::paths();
        $db = $paths['platform_db'];
        $error = '';
        $notice = (string) ($_SESSION['operator_notice'] ?? '');
        unset($_SESSION['operator_notice']);

        $needsAccount = PlatformDb::operatorCount($db) === 0;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::valid()) {
                $error = 'This form expired. Reload the page and try again.';
            } else {
                $action = (string) ($_POST['operator_action'] ?? '');
                try {
                    if ($needsAccount) {
                        if ($action !== 'create_operator') {
                            throw new \RuntimeException('Create the operator account first.');
                        }
                        self::createOperator($db);
                        $needsAccount = false;
                        $notice = (string) ($_SESSION['operator_notice'] ?? '');
                        unset($_SESSION['operator_notice']);
                    } elseif ($action === 'login') {
                        self::login($db);
                    } elseif (!self::signedIn()) {
                        $error = 'Sign in first.';
                    } else {
                        self::dispatch($paths, $action);
                        return;
                    }
                } catch (\PDOException $e) {
                    error_log('Operator database: ' . $e->getMessage());
                    $error = 'The operator database could not complete that.';
                } catch (\RuntimeException $e) {
                    $error = $e->getMessage();
                } catch (\Throwable $e) {
                    error_log('Operator: ' . $e->getMessage());
                    $error = 'The operator site could not complete that.';
                }
            }
        }

        if ($needsAccount) {
            self::show(
                'Create the operator account',
                'This account manages customer sites. It is stored in the operator database, not in a customer SQLite file.',
                self::accountForm('Create the operator account', 'create_operator'),
                $error,
                $notice
            );
            return;
        }

        if (!self::signedIn()) {
            self::show(
                'Operator sign in',
                'Sign in to add a site, change its hostname, or read what it costs.',
                self::loginForm(),
                $error,
                $notice
            );
            return;
        }

        $siteSlug = strtolower(trim((string) ($_GET['site'] ?? '')));
        if ($siteSlug !== '') {
            $store = PlatformDb::storeBySlug($paths['platform_db'], $siteSlug);
            if ($store === null) {
                HostConsole::missing($error, $notice);

                return;
            }
            HostConsole::site($paths, $store, $error, $notice);

            return;
        }

        $view = strtolower(trim((string) ($_GET['view'] ?? '')));
        if ($view === 'rate') {
            HostConsole::rate($paths, $error, $notice);

            return;
        }
        if ($view === 'add') {
            HostConsole::add($paths, $error, $notice);

            return;
        }

        HostConsole::dashboard($paths, $error, $notice);
    }

    private static function show(
        string $title,
        string $lead,
        string $body,
        string $error,
        string $notice
    ): void {
        Page::current()->useLayout('setup', [
            'title' => $title,
            'documentTitle' => $title . ' — Site manager',
            'kicker' => 'Site manager',
            'lead' => $lead,
            'error' => $error,
            'notice' => $notice,
            'helpHtml' => '',
            'formHtml' => $body . self::dialog('manage-help', 'Customer sites', HelpText::operator(Runtime::paths())),
            'installer' => true,
            'stepsHtml' => '',
            'headingAction' => self::headingActions(),
        ]);
    }

    /**
     * @param array<string, string> $paths
     */
    private static function dispatch(array $paths, string $action): void
    {
        $db = $paths['platform_db'];
        if ($action === 'sign_out') {
            unset($_SESSION['operator_id'], $_SESSION['operator_name'], $_SESSION['operator_notice']);
            Csrf::rotate();
            session_regenerate_id(true);
            header('Location: ' . Runtime::operatorAddress());
            exit;
        }
        if ($action === 'create_store') {
            $slug = self::createStore($paths);
            header('Location: ' . Runtime::operatorAddress('site=' . rawurlencode($slug)));
            exit;
        }
        if ($action === 'measure') {
            $_SESSION['operator_notice'] = SiteUsage::measureAll($db, $paths['stores'], PlatformDb::stores($db));
            header('Location: ' . Runtime::operatorAddress());
            exit;
        }
        if ($action === 'save_rate') {
            self::saveRate($db);
            header('Location: ' . Runtime::operatorAddress('view=rate'));
            exit;
        }
        if ($action === 'clear_rate') {
            PlatformDb::deleteRate($db);
            $_SESSION['operator_notice'] = 'The rate is cleared. Cost is not calculated.';
            header('Location: ' . Runtime::operatorAddress('view=rate'));
            exit;
        }
        $store = PlatformDb::storeById($db, (int) ($_POST['store_id'] ?? 0));
        if ($store === null) {
            throw new \RuntimeException('That customer site was not found.');
        }
        $slug = (string) $store['slug'];
        $folder = SiteUsage::folder($paths['stores'], $slug);
        $site = Runtime::operatorAddress('site=' . rawurlencode($slug));
        if ($action === 'save_site') {
            self::saveSite($paths, $store);
            header('Location: ' . $site);
            exit;
        }
        if ($action === 'measure_site') {
            $result = SiteUsage::measure($db, $store, $folder);
            $_SESSION['operator_notice'] = $result['problem'] !== ''
                ? $result['problem']
                : 'Measured ' . $store['label'] . ' at ' . SiteUsage::formatMeasured(time()) . '.';
            header('Location: ' . $site);
            exit;
        }
        if ($action === 'suspend') {
            PlatformDb::setStatus($db, (int) $store['store_id'], 'suspended');
            $_SESSION['operator_notice'] = $store['label'] . ' is suspended. Files remain at ' . $folder;
            header('Location: ' . $site);
            exit;
        }
        if ($action === 'resume') {
            PlatformDb::setStatus($db, (int) $store['store_id'], 'active');
            $_SESSION['operator_notice'] = $store['label'] . ' is active again.';
            header('Location: ' . $site);
            exit;
        }
        if ($action === 'backup') {
            self::backup($folder, $slug);
            return;
        }
        if ($action === 'delete') {
            SiteUsage::deleteSite($db, $paths['stores'], $store, trim((string) ($_POST['confirm_slug'] ?? '')));
            $_SESSION['operator_notice'] = $store['label'] . ' was deleted from disk.';
            header('Location: ' . Runtime::operatorAddress());
            exit;
        }
        throw new \RuntimeException('Unknown action.');
    }

    /**
     * @param array<string, string> $paths
     */
    private static function createStore(array $paths): string
    {
        $label = trim((string) ($_POST['label'] ?? ''));
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $host = strtolower(trim((string) ($_POST['host'] ?? '')));
        $email = trim((string) ($_POST['admin_email'] ?? ''));
        $username = trim((string) ($_POST['admin_user'] ?? ''));
        $password = (string) ($_POST['admin_password'] ?? '');

        if ($label === '' || strlen($label) > 80) {
            throw new \RuntimeException('Enter a display name (80 characters or fewer).');
        }
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $slug) !== 1) {
            throw new \RuntimeException('The slug must be lowercase letters, numbers, and hyphens, and it must start and end with a letter or number.');
        }
        $host = SiteUsage::assertHostname($host, $paths['operator_host']);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('The admin email does not look valid.');
        }
        if (($username === '') !== ($password === '')) {
            throw new \RuntimeException('To create the first admin now, enter both a username and a password. Leave both blank and the customer completes setup on their own hostname.');
        }
        if ($password !== '') {
            DefaultAccount::assertPassword($password, 'admin password');
        }
        if (preg_match('/^[A-Za-z0-9._-]{2,40}$/', $username) !== 1 && $username !== '') {
            throw new \RuntimeException('The admin username must be 2–40 characters: letters, numbers, dots, underscores, or hyphens.');
        }

        $folder = SiteUsage::folder($paths['stores'], $slug);
        SiteUsage::assertInside($paths['stores'], $folder);
        $admin = [
            'site_name' => $label,
            'public_url' => 'https://' . $host,
            'username' => $username,
            'password' => $password,
            'first_name' => '',
            'last_name' => '',
            'email' => $email,
        ];
        try {
            Provisioner::install(
                $paths['install'],
                $folder . 'config' . DIRECTORY_SEPARATOR,
                $folder . 'adlexone.sqlite',
                $folder . 'attachments' . DIRECTORY_SEPARATOR,
                $admin
            );
            PlatformDb::addStore($paths['platform_db'], $slug, $label, $host, $email);
        } catch (\Throwable $e) {
            Provisioner::removeTree($folder);
            throw $e;
        }
        $ready = $username !== ''
            ? ' The first admin can sign in now.'
            : ' The first visit to https://' . $host . ' shows setup, including where that store’s SQLite file, settings, and attachments live.';
        $created = PlatformDb::storeByHost($paths['platform_db'], $host);
        if ($created !== null) {
            try {
                SiteUsage::measure($paths['platform_db'], $created, $folder);
            } catch (\Throwable $e) {
                $ready .= ' Usage was not measured. ' . $e->getMessage();
            }
        }
        $_SESSION['operator_notice'] = $label . ' is ready at ' . $folder . '.' . $ready;

        return $slug;
    }

    private static function createOperator(string $db): void
    {
        $username = trim((string) ($_POST['user_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if (preg_match('/^[A-Za-z0-9._-]{2,40}$/', $username) !== 1) {
            throw new \RuntimeException('The operator username must be 2–40 characters: letters, numbers, dots, underscores, or hyphens.');
        }
        DefaultAccount::assertPassword($password);
        if (!hash_equals($password, $confirm)) {
            throw new \RuntimeException('The two passwords do not match.');
        }
        PlatformDb::addOperator($db, $username, $password);
        session_regenerate_id(true);
        Csrf::rotate();
        $row = PlatformDb::operatorByName($db, $username);
        $_SESSION['operator_id'] = (int) ($row['operator_id'] ?? 0);
        $_SESSION['operator_name'] = $username;
        $_SESSION['operator_notice'] = 'Operator account created. Customer sites are added below. Their files are not stored in this database.';
    }

    private static function login(string $db): void
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '' || strlen($ip) > 64) {
            $ip = 'unknown';
        }
        if (PlatformDb::loginFailureCount($db, $ip) >= PlatformDb::LOGIN_LIMIT) {
            usleep(200000);
            throw new \RuntimeException('Too many attempts. Try again in 15 minutes.');
        }
        $username = trim((string) ($_POST['user_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $row = PlatformDb::operatorByName($db, $username);
        $hash = (string) ($row['password'] ?? '');
        $check = $row === null || $hash === '' ? self::DUMMY_HASH : $hash;
        $verified = password_verify($password, $check);
        $ok = $row !== null && $hash !== '' && $verified;
        if (!$ok) {
            PlatformDb::recordLoginFailure($db, $ip);
            usleep(500000);
            throw new \RuntimeException('That operator username or password is not recognised.');
        }
        PlatformDb::clearLoginFailures($db, $ip);
        session_regenerate_id(true);
        Csrf::rotate();
        $_SESSION['operator_id'] = (int) $row['operator_id'];
        $_SESSION['operator_name'] = (string) $row['user_name'];
    }

    private static function signedIn(): bool
    {
        return (int) ($_SESSION['operator_id'] ?? 0) > 0;
    }

    private static function saveRate(string $db): void
    {
        PlatformDb::saveRate(
            $db,
            SiteUsage::parseCurrency((string) ($_POST['currency'] ?? '')),
            SiteUsage::parseAmount((string) ($_POST['monthly'] ?? '')),
            SiteUsage::parseAmount((string) ($_POST['person'] ?? '')),
            SiteUsage::parseAmount((string) ($_POST['gigabyte'] ?? ''))
        );
        SiteUsage::applyRate($db);
        $_SESSION['operator_notice'] = 'The rate is saved. Cost uses the last measurement.';
    }

    /**
     * @param array<string, string> $paths
     * @param array<string, mixed> $store
     */
    private static function saveSite(array $paths, array $store): void
    {
        $postedSlug = (string) ($_POST['slug'] ?? '');
        if (!hash_equals((string) $store['slug'], $postedSlug)) {
            throw new \RuntimeException('That customer site was not found.');
        }
        $label = trim((string) ($_POST['label'] ?? ''));
        SiteUsage::updateSite(
            $paths['platform_db'],
            $paths['stores'],
            $store,
            $label,
            (string) ($_POST['host'] ?? ''),
            (string) ($_POST['admin_email'] ?? ''),
            $paths['operator_host']
        );
        $_SESSION['operator_notice'] = $label . ' was updated.';
    }

    private static function headingActions(): string
    {
        return '<div class="install-head__tools">'
            . '<button type="button" class="install-help" data-sheet-pop aria-controls="manage-help">Details</button>'
            . '</div>';
    }

    /**
     * @param array<string, string> $rows
     */
    private static function facts(array $rows, bool $code): string
    {
        $html = '<dl class="install-facts">';
        foreach ($rows as $label => $value) {
            $shown = $code ? '<code>' . Html::e($value) . '</code>' : Html::e($value);
            $html .= '<div><dt>' . Html::e((string) $label) . '</dt><dd>' . $shown . '</dd></div>';
        }

        return $html . '</dl>';
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

    private static function accountForm(string $button, string $action): string
    {
        $saved = Runtime::paths();
        $html = self::facts([
            'Stores directory' => (string) ($saved['stores'] ?? ''),
            'Operator hostname' => (string) ($saved['operator_host'] ?? ''),
        ], true);
        $html .= '<form method="post" action="" class="login-form install-form" autocomplete="off">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="' . Html::e($action) . '">';
        $html .= self::field(
            'Operator username',
            'user_name',
            'text',
            DefaultAccount::postedUsername($_POST['user_name'] ?? null),
            true,
            'Only you use this. It is not created inside customer sites. The default is ' . DefaultAccount::USERNAME . '.'
        );
        $html .= self::field(
            'Password',
            'password',
            'password',
            DefaultAccount::postedPassword($_POST['password'] ?? null),
            true,
            DefaultAccount::allowed()
                ? 'The default is ' . DefaultAccount::PASSWORD . '. Stored as a hash in the operator database.'
                : 'At least 12 characters. Stored as a hash in the operator database.'
        );
        $html .= self::field(
            'Confirm password',
            'password_confirm',
            'password',
            DefaultAccount::postedPassword($_POST['password_confirm'] ?? null),
            true
        );
        $html .= '<div class="install-actions"><button type="submit" class="btn btn--primary">' . Html::e($button) . '</button></div>';
        $html .= '</form>';

        return $html;
    }

    private static function loginForm(): string
    {
        $html = '<form method="post" action="" class="login-form install-form" autocomplete="off">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="login">';
        $html .= self::field('Username', 'user_name', 'text', DefaultAccount::USERNAME, true);
        $html .= self::field('Password', 'password', 'password', DefaultAccount::allowed() ? DefaultAccount::PASSWORD : '', true);
        $html .= '<div class="install-actions"><button type="submit" class="btn btn--primary">Sign in</button></div>';
        $html .= '</form>';

        return $html;
    }

    private static function field(
        string $label,
        string $name,
        string $type,
        string $value,
        bool $required,
        string $help = ''
    ): string {
        $id = 'op_' . $name;
        $html = '<div class="field">';
        $html .= '<label class="login-label label" for="' . Html::e($id) . '">' . Html::e($label) . '</label>';
        $auto = $type === 'password' ? ' autocomplete="current-password"' : '';
        if ($name === 'password' || $name === 'password_confirm' || $name === 'admin_password') {
            $auto = ' autocomplete="new-password"';
        }
        $html .= '<input class="input" type="' . Html::e($type) . '" id="' . Html::e($id) . '" name="' . Html::e($name) . '" value="' . Html::e($value) . '"'
            . ($required ? ' required' : '') . $auto . '>';
        if ($help !== '') {
            $html .= '<p class="form-help">' . Html::e($help) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function backup(string $folder, string $slug): void
    {
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $slug) !== 1) {
            throw new \RuntimeException('That customer site was not found.');
        }
        SiteUsage::assertInside((string) (Runtime::paths()['stores'] ?? ''), $folder);
        $root = realpath($folder);
        if ($root === false) {
            throw new \RuntimeException('That customer site was not found.');
        }
        $prefix = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $sqlite = $folder . 'adlexone.sqlite';
        Provisioner::checkpoint($sqlite);
        $tmp = tempnam(sys_get_temp_dir(), 'inlay');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create a temporary backup file.');
        }
        $zipPath = $tmp . '.zip';
        @unlink($tmp);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $full = $file->getPathname();
            $real = realpath($full);
            if ($real === false || !str_starts_with($real, $prefix)) {
                continue;
            }
            $relative = substr($real, strlen($prefix));
            if ($relative === '' || str_contains($relative, '..') || str_ends_with($relative, '-wal') || str_ends_with($relative, '-shm')) {
                continue;
            }
            $files[$slug . '/' . str_replace('\\', '/', $relative)] = $real;
        }
        ZipStore::write($zipPath, $files);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $slug . '-backup.zip"');
        header('Content-Length: ' . (string) filesize($zipPath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    private static function privateResponse(): void
    {
        if (headers_sent()) {
            return;
        }
        header('Cache-Control: no-store');
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
