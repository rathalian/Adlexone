<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Auth\Csrf;
use Adlexone\Html\Html;
use Adlexone\Http\Page;
use Adlexone\Theme\Theme;

/**
 * First visit for a site that has no users yet.
 */
final class SetupScreen
{
    public static function handle(): void
    {
        self::theme();
        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::valid()) {
                $error = 'This form expired. Reload the page and try again.';
            } else {
                try {
                    $admin = self::adminFromPost();
                    $paths = Runtime::paths();
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
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $paths = Runtime::paths();
        SetupToken::issue();
        $label = trim((string) ($paths['store_label'] ?? ''));
        Page::current()->useLayout('setup', [
            'title' => 'Set up this site',
            'lead' => $label !== ''
                ? $label . ' does not have an administrator yet. Create the first account. The files below are only for this customer.'
                : 'Create the first administrator. Adlexone will create the database and settings in the folders listed here, outside the web root.',
            'error' => $error,
            'helpHtml' => HelpText::customer($paths),
            'formHtml' => self::form($label),
        ]);
    }

    /**
     * @return array{site_name: string, public_url: string, username: string, password: string, first_name: string, last_name: string, email: string, starting_applications: string}
     */
    private static function adminFromPost(): array
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
            throw new \RuntimeException('Enter the organisation name (80 characters or fewer).');
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
        if (!SetupToken::check((string) ($_POST['setup_token'] ?? ''))) {
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

    private static function form(string $label): string
    {
        $site = (string) ($_POST['site_name'] ?? $label);
        $html = '<form method="post" action="" class="login-form">';
        $html .= Csrf::field();
        $html .= self::field(
            'Setup token',
            'setup_token',
            'text',
            '',
            true,
            'Open ' . SetupToken::path() . ' on the server and enter the value. This page does not show it.'
        );
        $html .= self::field('Organisation name', 'site_name', 'text', $site, true, 'The name people see for this site.');
        $html .= self::field('Your name', 'first_name', 'text', (string) ($_POST['first_name'] ?? ''), false);
        $html .= self::field('Last name', 'last_name', 'text', (string) ($_POST['last_name'] ?? ''), false);
        $html .= self::field('Email', 'email', 'email', (string) ($_POST['email'] ?? ''), false);
        $html .= self::field(
            'Username',
            'user_name',
            'text',
            DefaultAccount::postedUsername($_POST['user_name'] ?? null),
            true,
            'The first administrator. The default is ' . DefaultAccount::USERNAME . '.'
        );
        $html .= self::field(
            'Password',
            'password',
            'password',
            DefaultAccount::postedPassword($_POST['password'] ?? null),
            true,
            DefaultAccount::allowed()
                ? 'The default is ' . DefaultAccount::PASSWORD . '. It is stored as a hash in the SQLite file, not in the settings JSON.'
                : 'At least 12 characters. It is stored as a hash in the SQLite file, not in the settings JSON.'
        );
        $html .= self::field(
            'Confirm password',
            'password_confirm',
            'password',
            DefaultAccount::postedPassword($_POST['password_confirm'] ?? null),
            true
        );
        $html .= self::field(
            'Public URL',
            'public_url',
            'text',
            (string) ($_POST['public_url'] ?? ''),
            false,
            'Optional. For example, https://health.example.com. Leave this blank and Adlexone uses the address in the browser. Set it when the site sits behind a proxy.'
        );
        $html .= self::startingApplications((string) ($_POST['starting_applications'] ?? ''));
        $html .= '<button type="submit" class="btn btn--primary login-submit">Create the site</button>';
        $html .= '</form>';

        return $html;
    }

    private static function startingApplications(string $selected): string
    {
        $options = [
            'samples' => [
                'Sample applications',
                'Install the sample applications to help with the first setup. They can be deleted.',
            ],
            'exemplar' => [
                'Publishing (Example)',
                'Install Publishing, with its fields, rules, guide, and sample records.',
            ],
            'none' => [
                'No applications',
                'Start with an empty home. Create an application is the next step.',
            ],
        ];
        $html = '<fieldset class="setup-choice"><legend class="login-label label">Starting applications</legend>';
        foreach ($options as $value => [$label, $help]) {
            $id = 'setup_start_' . $value;
            $html .= '<label class="setup-choice__option" for="' . Html::e($id) . '">'
                . '<input class="radio" type="radio" name="starting_applications" id="' . Html::e($id) . '" value="' . Html::e($value) . '"'
                . ($selected === $value ? ' checked' : '')
                . ' required>'
                . '<span class="setup-choice__text"><span class="setup-choice__label">' . Html::e($label) . '</span>'
                . '<span class="form-help">' . Html::e($help) . '</span></span></label>';
        }

        return $html . '</fieldset>';
    }

    private static function field(
        string $label,
        string $name,
        string $type,
        string $value,
        bool $required,
        string $help = ''
    ): string {
        $id = 'setup_' . $name;
        $html = '<div class="field">';
        $html .= '<label class="login-label label" for="' . Html::e($id) . '">' . Html::e($label) . '</label>';
        $html .= '<input class="input" type="' . Html::e($type) . '" id="' . Html::e($id) . '" name="' . Html::e($name) . '" value="' . Html::e($value) . '"'
            . ($required ? ' required' : '')
            . ($type === 'password' ? ' autocomplete="new-password"' : '')
            . '>';
        if ($help !== '') {
            $html .= '<p class="form-help">' . Html::e($help) . '</p>';
        }
        $html .= '</div>';

        return $html;
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
