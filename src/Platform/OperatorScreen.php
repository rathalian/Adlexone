<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Auth\Csrf;
use Adlexone\Html\Html;
use Adlexone\Http\Page;
use Adlexone\Theme\Theme;

/**
 * Operator console. One login for the person who creates and retires customer stores.
 */
final class OperatorScreen
{
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
            Page::current()->useLayout('setup', [
                'title' => 'Create the operator account',
                'lead' => 'This account manages customer sites. It is stored in the operator database, not in a customer SQLite file.',
                'error' => $error,
                'helpHtml' => HelpText::operator($paths),
                'formHtml' => self::accountForm('Create the operator account', 'create_operator'),
            ]);
            return;
        }

        if (!self::signedIn()) {
            Page::current()->useLayout('setup', [
                'title' => 'Operator sign in',
                'lead' => 'Sign in to add, suspend, back up, or remove a customer store.',
                'error' => $error,
                'helpHtml' => HelpText::operator($paths),
                'formHtml' => self::loginForm(),
            ]);
            return;
        }

        Page::current()->useLayout('setup', [
            'title' => 'Customer sites',
            'lead' => 'Each row is one isolated store. Their database, settings, and uploads stay in the folder shown.',
            'error' => $error,
            'notice' => $notice,
            'helpHtml' => HelpText::operator($paths),
            'formHtml' => self::console($paths),
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
            header('Location: ./');
            exit;
        }
        if ($action === 'create_store') {
            self::createStore($paths);
            header('Location: ./');
            exit;
        }
        $store = PlatformDb::storeById($db, (int) ($_POST['store_id'] ?? 0));
        if ($store === null) {
            throw new \RuntimeException('That customer site was not found.');
        }
        $folder = self::folder($paths['stores'], (string) $store['slug']);
        if ($action === 'suspend') {
            PlatformDb::setStatus($db, (int) $store['store_id'], 'suspended');
            $_SESSION['operator_notice'] = $store['label'] . ' is suspended. Files remain at ' . $folder;
        } elseif ($action === 'resume') {
            PlatformDb::setStatus($db, (int) $store['store_id'], 'active');
            $_SESSION['operator_notice'] = $store['label'] . ' is active again.';
        } elseif ($action === 'backup') {
            self::backup($folder, (string) $store['slug']);
            return;
        } elseif ($action === 'delete') {
            $confirm = trim((string) ($_POST['confirm_slug'] ?? ''));
            if (!hash_equals((string) $store['slug'], $confirm)) {
                throw new \RuntimeException('Type the slug ' . $store['slug'] . ' to delete that store.');
            }
            self::assertInside($paths['stores'], $folder);
            Provisioner::removeTree($folder);
            PlatformDb::deleteStore($db, (int) $store['store_id']);
            $_SESSION['operator_notice'] = $store['label'] . ' was deleted from disk.';
        } else {
            throw new \RuntimeException('Unknown action.');
        }
        header('Location: ./');
        exit;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function createStore(array $paths): void
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

        $folder = self::folder($paths['stores'], $slug);
        self::assertInside($paths['stores'], $folder);
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
        $_SESSION['operator_notice'] = $label . ' is ready at ' . $folder . '.' . $ready;
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
    private static function console(array $paths): string
    {
        $stores = PlatformDb::stores($paths['platform_db']);
        $html = '<p class="form-help">Signed in as ' . Html::e((string) ($_SESSION['operator_name'] ?? '')) . '.</p>';
        $html .= '<form method="post" action="" class="setup-inline">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="sign_out">';
        $html .= '<button type="submit" class="btn">Sign out</button>';
        $html .= '</form>';

        if ($stores === []) {
            $html .= '<p class="login-lead">No customer sites yet. The form below creates the first folder.</p>';
        } else {
            $html .= '<ul class="setup-stores">';
            foreach ($stores as $store) {
                $html .= self::storeRow($paths, $store);
            }
            $html .= '</ul>';
        }

        $html .= '<h2 class="setup-form-title">Add a customer site</h2>';
        $html .= '<p class="form-help">The folder will be ' . Html::e(rtrim($paths['stores'], '/\\') . DIRECTORY_SEPARATOR)
            . '<code>{slug}</code>. Inside it: <code>adlexone.sqlite</code> (mode 600), <code>config/</code> (mode 700), and <code>attachments/</code> (mode 700). PHP user: '
            . Html::e($paths['php_user']) . '.</p>';
        $html .= '<form method="post" action="" class="login-form">';
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
            DefaultAccount::USERNAME,
            false,
            'The customer application login. Leave username and password blank and they choose the first account on their hostname.'
        );
        $html .= self::field(
            'Admin password',
            'admin_password',
            'password',
            DefaultAccount::PASSWORD,
            false,
            'The default is ' . DefaultAccount::PASSWORD . '. Stored as a hash in their SQLite file.'
        );
        $html .= '<button type="submit" class="btn btn--primary login-submit">Create the store</button>';
        $html .= '</form>';

        return $html;
    }

    /**
     * @param array<string, string> $paths
     * @param array<string, mixed> $store
     */
    private static function storeRow(array $paths, array $store): string
    {
        $slug = (string) $store['slug'];
        $folder = self::folder($paths['stores'], $slug);
        $bytes = Provisioner::folderBytes($folder);
        $status = (string) $store['status'];
        $html = '<li class="setup-store">';
        $html .= '<div class="setup-store__name">' . Html::e((string) $store['label']) . '</div>';
        $html .= '<p class="form-help">' . Html::e((string) $store['host']) . ' · ' . Html::e($status)
            . ' · ' . Html::e(self::size($bytes)) . '</p>';
        $html .= '<p class="form-help">Database <code>' . Html::e($folder . 'adlexone.sqlite') . '</code><br>'
            . 'Settings <code>' . Html::e($folder . 'config' . DIRECTORY_SEPARATOR) . '</code><br>'
            . 'Uploads <code>' . Html::e($folder . 'attachments' . DIRECTORY_SEPARATOR) . '</code></p>';
        $id = (int) $store['store_id'];
        $html .= self::miniForm($id, $status === 'active' ? 'suspend' : 'resume', $status === 'active' ? 'Suspend' : 'Resume');
        $html .= self::miniForm($id, 'backup', 'Download backup');
        $html .= '<form method="post" action="" class="setup-inline setup-delete">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="delete">';
        $html .= '<input type="hidden" name="store_id" value="' . $id . '">';
        $html .= '<input class="input" type="text" name="confirm_slug" placeholder="Type ' . Html::e($slug) . ' to delete" aria-label="Slug confirm">';
        $html .= '<button type="submit" class="btn">Delete from disk</button>';
        $html .= '</form>';
        $html .= '</li>';

        return $html;
    }

    private static function miniForm(int $id, string $action, string $label): string
    {
        $html = '<form method="post" action="" class="setup-inline">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="' . Html::e($action) . '">';
        $html .= '<input type="hidden" name="store_id" value="' . $id . '">';
        $html .= '<button type="submit" class="btn">' . Html::e($label) . '</button>';
        $html .= '</form>';

        return $html;
    }

    private static function accountForm(string $button, string $action): string
    {
        $html = '<form method="post" action="" class="login-form">';
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
            'The default is ' . DefaultAccount::PASSWORD . '. Stored as a hash in the operator database.'
        );
        $html .= self::field(
            'Confirm password',
            'password_confirm',
            'password',
            DefaultAccount::postedPassword($_POST['password_confirm'] ?? null),
            true
        );
        $html .= '<button type="submit" class="btn btn--primary login-submit">' . Html::e($button) . '</button>';
        $html .= '</form>';

        return $html;
    }

    private static function loginForm(): string
    {
        $html = '<form method="post" action="" class="login-form">';
        $html .= Csrf::field();
        $html .= '<input type="hidden" name="operator_action" value="login">';
        $html .= self::field('Username', 'user_name', 'text', DefaultAccount::USERNAME, true);
        $html .= self::field('Password', 'password', 'password', DefaultAccount::PASSWORD, true);
        $html .= '<button type="submit" class="btn btn--primary login-submit">Sign in</button>';
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
        self::assertInside((string) (Runtime::paths()['stores'] ?? ''), $folder);
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

    private static function folder(string $stores, string $slug): string
    {
        return rtrim($stores, '/\\') . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR;
    }

    private static function assertInside(string $stores, string $folder): void
    {
        $root = realpath($stores);
        if ($root === false) {
            if (!is_dir($stores) && !mkdir($stores, 0700, true) && !is_dir($stores)) {
                throw new \RuntimeException('Cannot create the stores folder: ' . $stores);
            }
            $root = realpath($stores);
        }
        $parent = realpath(dirname(rtrim($folder, '/\\')));
        if ($root === false || $parent === false || !str_starts_with($parent . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) && $parent !== $root) {
            throw new \RuntimeException('The store folder must stay inside the stores directory.');
        }
    }

    private static function size(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
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
