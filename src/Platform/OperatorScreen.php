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
    public static function manageUnavailable(): void
    {
        self::theme();
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
                    if ($needsAccount || $action === 'create_operator') {
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
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        if ($needsAccount) {
            self::show(
                'Create the operator account',
                'This account manages customer sites. It is stored in the operator database, not in a customer SQLite file.',
                self::accountForm('Create the operator account', 'create_operator'),
                $error,
                $notice,
                ''
            );
            return;
        }

        if (!self::signedIn()) {
            self::show(
                'Operator sign in',
                'Sign in to add a site, change its hostname, or read what it costs.',
                self::loginForm(),
                $error,
                $notice,
                ''
            );
            return;
        }

        $siteSlug = strtolower(trim((string) ($_GET['site'] ?? '')));
        if ($siteSlug !== '') {
            $store = PlatformDb::storeBySlug($paths['platform_db'], $siteSlug);
            if ($store === null) {
                self::show(
                    'Customer site',
                    $error !== '' ? $error : 'That customer site was not found.',
                    '<div class="install-actions"><a class="btn" href="' . Html::e(Runtime::operatorAddress()) . '">Sites</a></div>',
                    '',
                    $notice,
                    'sites'
                );

                return;
            }
            self::show(
                (string) $store['label'],
                (string) $store['host'] . '. These figures are the last measurement of this site.',
                self::sitePage($paths, $store),
                $error,
                $notice,
                'site'
            );

            return;
        }

        $view = strtolower(trim((string) ($_GET['view'] ?? '')));
        if ($view === 'rate') {
            self::show(
                'Rate',
                'One rate for every site. There is no invoice. The cost is this rate applied to the last measurement.',
                self::rateForm($paths),
                $error,
                $notice,
                'rate'
            );

            return;
        }
        if ($view === 'add') {
            self::show(
                'Add a customer site',
                'The folder is created under the stores directory. Leave the admin username and password blank and they finish setup on their hostname.',
                self::addForm($paths),
                $error,
                $notice,
                'add'
            );

            return;
        }

        self::show(
            'Customer sites',
            'Each row is one site. The totals are the last measurement of those sites.',
            self::sitesPage($paths),
            $error,
            $notice,
            'sites'
        );
    }

    private static function show(
        string $title,
        string $lead,
        string $body,
        string $error,
        string $notice,
        string $place
    ): void {
        $signedIn = $place !== '';
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
            'stepsHtml' => $signedIn ? self::steps($place) : '',
            'headingAction' => self::headingActions($signedIn),
        ]);
    }

    /**
     * @param array<string, string> $paths
     */
    private static function dispatch(array $paths, string $action): void
    {
        $db = $paths['platform_db'];
        if ($action === 'sign_out') {
            unset($_SESSION['operator_id'], $_SESSION['operator_name']);
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
        if (preg_match('/^[a-z0-9.-]+$/', $host) !== 1 || !str_contains($host, '.')) {
            throw new \RuntimeException('Enter the full hostname customers will use, such as health.example.com.');
        }
        if (strcasecmp($host, $paths['operator_host']) === 0) {
            throw new \RuntimeException('That hostname is the operator site. Use a different hostname for the customer.');
        }
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
        $row = PlatformDb::operatorByName($db, $username);
        $_SESSION['operator_id'] = (int) ($row['operator_id'] ?? 0);
        $_SESSION['operator_name'] = $username;
        $_SESSION['operator_notice'] = 'Operator account created. Customer sites are added below. Their files are not stored in this database.';
    }

    private static function login(string $db): void
    {
        $username = trim((string) ($_POST['user_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $row = PlatformDb::operatorByName($db, $username);
        $hash = (string) ($row['password'] ?? '');
        if ($row === null || !password_verify($password, $hash)) {
            usleep(500000);
            throw new \RuntimeException('That operator username or password is not recognised.');
        }
        session_regenerate_id(true);
        $_SESSION['operator_id'] = (int) $row['operator_id'];
        $_SESSION['operator_name'] = (string) $row['user_name'];
    }

    private static function signedIn(): bool
    {
        return (int) ($_SESSION['operator_id'] ?? 0) > 0;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function sitesPage(array $paths): string
    {
        $stores = PlatformDb::stores($paths['platform_db']);
        $html = self::totals($paths);
        $html .= '<p class="install-section">Sites</p>';
        if ($stores === []) {
            $html .= '<p class="install-callout">No customer sites yet. Add a site to create the first folder.</p>';
            $html .= '<div class="install-actions"><a class="btn btn--primary" href="' . Html::e(Runtime::operatorAddress('view=add')) . '">Add a site</a></div>';

            return $html;
        }
        $html .= '<ul class="manage-sites">';
        foreach ($stores as $store) {
            $html .= self::storeRow($paths, $store);
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function addForm(array $paths): string
    {
        $folder = rtrim($paths['stores'], '/\\') . DIRECTORY_SEPARATOR . '{slug}' . DIRECTORY_SEPARATOR;
        $action = Runtime::operatorAddress('view=add');
        $html = '<p class="install-section">Folder</p>';
        $html .= self::facts([
            'Stores directory' => rtrim($paths['stores'], '/\\') . DIRECTORY_SEPARATOR,
            'This site' => $folder,
            'PHP is running as' => $paths['php_user'],
        ], true);
        $html .= '<form method="post" action="' . Html::e($action) . '" class="login-form install-form" autocomplete="off">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="create_store">';
        $html .= self::field('Display name', 'label', 'text', '', true, 'Shown on their setup page. For example, Health.');
        $html .= self::field('Slug', 'slug', 'text', '', true, 'Folder name. Lowercase letters, numbers, and hyphens. For example, health.');
        $html .= self::field('Hostname', 'host', 'text', '', true, 'The address their browser uses. For example, health.example.com. Point DNS at this server. Do not use the operator hostname.');
        $html .= self::field('Admin email', 'admin_email', 'email', '', false, 'Stored on the operator list so you know who to contact. Optional.');
        $html .= self::field(
            'Admin username',
            'admin_user',
            'text',
            DefaultAccount::allowed() ? DefaultAccount::USERNAME : '',
            false,
            'The customer application login. Leave username and password blank and they choose the first account on their hostname.'
        );
        $html .= self::field(
            'Admin password',
            'admin_password',
            'password',
            DefaultAccount::allowed() ? DefaultAccount::PASSWORD : '',
            false,
            DefaultAccount::allowed()
                ? 'The default is ' . DefaultAccount::PASSWORD . '. Stored as a hash in their SQLite file.'
                : 'At least 12 characters, or leave blank to let them choose on first visit. Stored as a hash in their SQLite file.'
        );
        $html .= '<div class="install-actions">';
        $html .= '<a class="btn" href="' . Html::e(Runtime::operatorAddress()) . '">Sites</a>';
        $html .= '<button type="submit" class="btn btn--primary">Create the store</button>';
        $html .= '</div></form>';

        return $html;
    }

    /**
     * @param array<string, string> $paths
     * @param array<string, mixed> $store
     */
    private static function storeRow(array $paths, array $store): string
    {
        $slug = (string) $store['slug'];
        $usage = PlatformDb::usage($paths['platform_db'], (int) $store['store_id']);
        $status = (string) $store['status'];
        $figures = 'Not measured';
        if ($usage !== null) {
            $bytes = (int) $usage['database_bytes'] + (int) $usage['wal_bytes'] + (int) $usage['shm_bytes']
                + (int) $usage['settings_bytes'] + (int) $usage['uploads_bytes'] + (int) $usage['other_bytes'];
            $rate = PlatformDb::rate($paths['platform_db']);
            $cost = $usage['cost_cents'] === null || $rate === null
                ? 'No cost'
                : SiteUsage::formatMoney((int) $usage['cost_cents'], (string) $rate['currency']);
            $figures = SiteUsage::formatSize($bytes) . ' · ' . $cost;
        }
        $html = '<li><a class="manage-site" href="' . Html::e(Runtime::operatorAddress('site=' . rawurlencode($slug))) . '">';
        $html .= '<span class="manage-site__name">' . Html::e((string) $store['label']) . '</span>';
        $html .= '<span class="manage-site__meta">' . Html::e((string) $store['host']) . ' · ' . Html::e($status)
            . ' · ' . Html::e($figures) . '</span>';
        $html .= '</a></li>';

        return $html;
    }

    /**
     * @param array<string, string> $paths
     * @param array<string, mixed> $store
     */
    private static function sitePage(array $paths, array $store): string
    {
        $slug = (string) $store['slug'];
        $folder = SiteUsage::folder($paths['stores'], $slug);
        $id = (int) $store['store_id'];
        $usage = PlatformDb::usage($paths['platform_db'], $id);
        $rate = PlatformDb::rate($paths['platform_db']);
        $action = Runtime::operatorAddress('site=' . rawurlencode($slug));
        $html = '<p class="install-section">Folder</p>';
        $html .= self::facts([
            'Slug' => $slug,
            'Database' => $folder . 'adlexone.sqlite',
            'Settings' => $folder . 'config' . DIRECTORY_SEPARATOR,
            'Uploads' => $folder . 'attachments' . DIRECTORY_SEPARATOR,
        ], true);
        $html .= '<p class="form-help">The slug is ' . Html::e($slug) . '. It is the folder name and it is not changed here.</p>';
        $html .= self::usageBlock($usage, $rate);
        $html .= '<div class="install-actions">' . self::miniForm($id, 'measure_site', 'Measure this site', $action) . '</div>';
        $html .= '<p class="install-section">Update this site</p>';
        $html .= '<form method="post" action="' . Html::e($action) . '" class="login-form install-form" autocomplete="off">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="save_site">';
        $html .= '<input type="hidden" name="store_id" value="' . $id . '">';
        $html .= '<input type="hidden" name="slug" value="' . Html::e($slug) . '">';
        $html .= self::field('Display name', 'label', 'text', (string) $store['label'], true, 'Shown on their sign-in page. For example, Health.');
        $html .= self::field('Hostname', 'host', 'text', (string) $store['host'], true, 'The address their browser uses. For example, health.example.com.');
        $html .= self::field('Admin email', 'admin_email', 'email', (string) $store['admin_email'], false, 'Stored on the operator list so you know who to contact. Optional.');
        $html .= '<div class="install-actions"><button type="submit" class="btn btn--primary">Save changes</button></div>';
        $html .= '</form>';
        $status = (string) $store['status'];
        $html .= '<div class="install-actions">';
        $html .= self::miniForm($id, $status === 'active' ? 'suspend' : 'resume', $status === 'active' ? 'Suspend' : 'Resume', $action);
        $html .= self::miniForm($id, 'backup', 'Download backup', $action);
        $html .= '</div>';
        $html .= '<p class="install-section">Remove this site</p>';
        $html .= '<p class="form-help">Type the slug ' . Html::e($slug) . '. Delete removes that folder from disk.</p>';
        $html .= '<form method="post" action="' . Html::e($action) . '" class="login-form install-form" autocomplete="off">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="delete">';
        $html .= '<input type="hidden" name="store_id" value="' . $id . '">';
        $html .= '<div class="field"><label class="login-label label" for="op_confirm_slug">Slug</label>';
        $html .= '<input class="input" type="text" id="op_confirm_slug" name="confirm_slug" autocomplete="off" spellcheck="false">';
        $html .= '</div>';
        $html .= '<div class="install-actions"><button type="submit" class="btn">Delete from disk</button></div>';
        $html .= '</form>';

        return $html;
    }

    /**
     * @param array<string, mixed>|null $usage
     * @param array<string, mixed>|null $rate
     */
    private static function usageBlock(?array $usage, ?array $rate): string
    {
        if ($usage === null) {
            return '<p class="form-help">This site has not been measured.</p>';
        }
        $html = '<p class="install-section">Usage</p>';
        $html .= '<p class="form-help">Measured ' . Html::e(SiteUsage::formatMeasured((int) $usage['measured_at'])) . '.</p>';
        $rows = [
            'Database' => SiteUsage::formatSize((int) $usage['database_bytes']),
            'Write-ahead log' => SiteUsage::formatSize((int) $usage['wal_bytes']),
            'Shared memory' => SiteUsage::formatSize((int) $usage['shm_bytes']),
            'Settings' => SiteUsage::formatSize((int) $usage['settings_bytes']),
            'Uploads' => SiteUsage::formatSize((int) $usage['uploads_bytes']),
        ];
        if ((int) $usage['other_bytes'] > 0) {
            $rows['Other'] = SiteUsage::formatSize((int) $usage['other_bytes']);
        }
        $total = (int) $usage['database_bytes'] + (int) $usage['wal_bytes'] + (int) $usage['shm_bytes']
            + (int) $usage['settings_bytes'] + (int) $usage['uploads_bytes'] + (int) $usage['other_bytes'];
        $rows['Folder total'] = SiteUsage::formatSize($total);
        $rows['People'] = self::countText($usage['people'] ?? null);
        $rows['Applications'] = self::countText($usage['applications'] ?? null);
        $rows['Records'] = self::countText($usage['records'] ?? null);
        $html .= self::facts($rows, false);
        if ($usage['people'] === null) {
            $html .= '<p class="form-help">This site has no administrator yet.</p>';
        }
        $note = trim((string) ($usage['counts_note'] ?? ''));
        if ($note !== '') {
            $html .= '<p class="form-help">' . Html::e($note) . '</p>';
        }
        if ($rate === null || $usage['cost_cents'] === null) {
            $html .= '<p class="form-help">No rate is set. Set a rate to calculate a cost. Usage is already shown.</p>';
        } else {
            $html .= '<p class="form-help">Cost ' . Html::e(SiteUsage::formatMoney((int) $usage['cost_cents'], (string) $rate['currency'])) . '.</p>';
        }

        return $html;
    }

    private static function countText(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not set up';
        }

        return (string) (int) $value;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function totals(array $paths): string
    {
        $figures = SiteUsage::hostFigures($paths['platform_db']);
        $rate = PlatformDb::rate($paths['platform_db']);
        $cost = $figures['cost'] === null || $rate === null
            ? 'No rate is set'
            : SiteUsage::formatMoney($figures['cost'], (string) $rate['currency']);
        $html = '<p class="install-section">Host</p>';
        $html .= self::facts([
            'Sites' => (string) $figures['sites'],
            'Active' => (string) $figures['active'],
            'Suspended' => (string) $figures['suspended'],
            'Folder total' => SiteUsage::formatSize($figures['bytes']),
            'People' => (string) $figures['people'],
            'Applications' => (string) $figures['applications'],
            'Records' => (string) $figures['records'],
            'Cost' => $cost,
        ], false);
        if ($figures['cost'] === null || $rate === null) {
            $html .= '<p class="form-help">Set a rate to calculate a cost. Usage is already shown.</p>';
        }
        if ($figures['unmeasured'] > 0) {
            $n = $figures['unmeasured'];
            $html .= '<p class="form-help">' . $n . ' ' . ($n === 1 ? 'site has not been measured and is not in this cost.' : 'sites have not been measured and are not in this cost.') . '</p>';
        }
        if ($figures['unset_counts'] > 0) {
            $n = $figures['unset_counts'];
            $html .= '<p class="form-help">' . $n . ' ' . ($n === 1 ? 'site has' : 'sites have') . ' no administrator yet. Their people, applications, and records are not in these totals.</p>';
        }
        $html .= '<form method="post" action="' . Html::e(Runtime::operatorAddress()) . '" class="install-actions">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="measure">';
        $html .= '<button type="submit" class="btn">Measure usage</button>';
        $html .= '</form>';

        return $html;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function rateForm(array $paths): string
    {
        $rate = PlatformDb::rate($paths['platform_db']);
        $action = Runtime::operatorAddress('view=rate');
        $html = '<form method="post" id="manage-rate" action="' . Html::e($action) . '" class="login-form install-form" autocomplete="off">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="save_rate">';
        $html .= self::field('Currency', 'currency', 'text', $rate === null ? 'AUD' : (string) $rate['currency'], true, 'Three letters. For example, AUD.');
        $html .= self::field(
            'Monthly amount',
            'monthly',
            'text',
            $rate === null ? '' : SiteUsage::formatAmount((int) $rate['monthly_cents']),
            true,
            'Applied once to each site. For example, 12.50.'
        );
        $html .= self::field(
            'Amount per person',
            'person',
            'text',
            $rate === null ? '' : SiteUsage::formatAmount((int) $rate['person_cents']),
            true,
            'Multiplied by the number of people on the site.'
        );
        $html .= self::field(
            'Amount per gigabyte',
            'gigabyte',
            'text',
            $rate === null ? '' : SiteUsage::formatAmount((int) $rate['gigabyte_cents']),
            true,
            'A gigabyte is 1,024 MB. The figure uses the folder total.'
        );
        $html .= '</form>';
        $html .= '<div class="install-actions">';
        if ($rate !== null) {
            $html .= '<form method="post" action="' . Html::e($action) . '">';
            $html .= Csrf::field();
            $html .= '<input type="hidden" name="operator_action" value="clear_rate">';
            $html .= '<button type="submit" class="btn">Clear the rate</button>';
            $html .= '</form>';
        }
        $html .= '<button type="submit" class="btn btn--primary" form="manage-rate">Save the rate</button>';
        $html .= '</div>';

        return $html;
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

    private static function headingActions(bool $signedIn): string
    {
        $html = '<div class="install-head__tools">';
        $html .= '<button type="button" class="install-help" data-sheet-pop aria-controls="manage-help">Details</button>';
        if ($signedIn) {
            $html .= '<span class="install-who">' . Html::e((string) ($_SESSION['operator_name'] ?? '')) . '</span>';
            $html .= '<form method="post" action="' . Html::e(Runtime::operatorAddress()) . '" class="install-signout">';
            $html .= Csrf::field();
            $html .= '<input type="hidden" name="operator_action" value="sign_out">';
            $html .= '<button type="submit" class="install-help">Sign out</button>';
            $html .= '</form>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function steps(string $place): string
    {
        $items = [
            'sites' => ['Sites', Runtime::operatorAddress()],
            'rate' => ['Rate', Runtime::operatorAddress('view=rate')],
            'add' => ['Add a site', Runtime::operatorAddress('view=add')],
        ];
        $current = $place === 'site' ? 'sites' : $place;
        $html = '<nav aria-label="Site manager"><ol class="install-steps">';
        $index = 1;
        foreach ($items as $id => [$label, $href]) {
            $class = $id === $current ? 'is-current' : 'is-done';
            $inner = '<span class="install-steps__num">' . $index . '</span><span class="install-steps__label">' . Html::e($label) . '</span>';
            $currentAttr = $id === $current ? ' aria-current="page"' : '';
            $item = '<a class="install-steps__item" href="' . Html::e($href) . '"' . $currentAttr . '>' . $inner . '</a>';
            $html .= '<li class="' . $class . '">' . $item . '</li>';
            $index++;
        }

        return $html . '</ol></nav>';
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

    private static function miniForm(int $id, string $action, string $label, string $target = ''): string
    {
        $html = '<form method="post" action="' . Html::e($target) . '">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="' . Html::e($action) . '">';
        $html .= '<input type="hidden" name="store_id" value="' . $id . '">';
        $html .= '<button type="submit" class="btn">' . Html::e($label) . '</button>';
        $html .= '</form>';

        return $html;
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
        SiteUsage::assertInside((string) (Runtime::paths()['stores'] ?? ''), $folder);
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
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $full = $file->getPathname();
            $relative = substr($full, strlen($folder));
            if (str_ends_with($relative, '-wal') || str_ends_with($relative, '-shm')) {
                continue;
            }
            $files[$slug . '/' . str_replace('\\', '/', ltrim($relative, '/\\'))] = $full;
        }
        ZipStore::write($zipPath, $files);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $slug . '-backup.zip"');
        header('Content-Length: ' . (string) filesize($zipPath));
        header('X-Content-Type-Options: nosniff');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
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
