<?php
declare(strict_types=1);

namespace Adlexone\Auth;

use Adlexone\support\RenderViews;

/**
 * Front controller for unauthenticated requests.
 */
final class AuthRouter
{
    public static function dispatch(): void
    {
        UserIdentityRepository::ensureSchema();

        $action = (string) ($_GET['action'] ?? '');

        try {
            match ($action) {
                'logoff' => self::logoff(),
                'local' => self::local(),
                'oauth_start', 'oidc_start' => self::oauthStart(),
                'oauth_callback', 'oidc_callback' => self::oauthCallback(),
                default => self::options(),
            };
        } catch (AuthException $e) {
            AuthSession::redirectToLogin($e->getMessage());
        }
    }

    private static function logoff(): never
    {
        AuthSession::clear();
        header('Location: ' . rtrim((string) BASE_URL, '/') . '/index.php');
        exit;
    }

    private static function options(): void
    {
        if (
            AuthConfig::oauthEnabled()
            && AuthConfig::oauthAutoRedirect()
            && !AuthConfig::localEnabled()
            && count(AuthConfig::connections()) === 1
            && !isset($_GET['stay'])
        ) {
            header('Location: ' . OauthClient::authorizationUrl(AuthConfig::connections()[0]));
            exit;
        }

        // Username-only installs: go straight to that form.
        if (AuthConfig::localEnabled() && !AuthConfig::oauthEnabled()) {
            self::renderLoginPage('local');
            return;
        }

        self::renderLoginPage('options');
    }

    private static function local(): void
    {
        if (!AuthConfig::localEnabled()) {
            throw new AuthException('Username and password sign-in is turned off.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user = LocalAuthenticator::authenticate(
                (string) ($_POST['access_user_name'] ?? ''),
                (string) ($_POST['access_password'] ?? '')
            );
            AuthSession::establish($user);
            AuthSession::redirectHome();
        }

        self::renderLoginPage('local');
    }

    private static function oauthStart(): never
    {
        if (!AuthConfig::oauthEnabled()) {
            throw new AuthException('OAuth sign-in is not configured yet.');
        }

        $id = (string) ($_GET['connection'] ?? $_GET['provider'] ?? '');
        $connection = AuthConfig::connection($id);
        if ($connection === null && count(AuthConfig::connections()) === 1) {
            $connection = AuthConfig::connections()[0];
        }
        if ($connection === null) {
            throw new AuthException('That OAuth connection is not configured.');
        }

        header('Location: ' . OauthClient::authorizationUrl($connection));
        exit;
    }

    private static function oauthCallback(): never
    {
        if (!empty($_GET['error'])) {
            $desc = (string) ($_GET['error_description'] ?? $_GET['error']);
            throw new AuthException('OAuth sign-in was cancelled or failed: ' . $desc);
        }

        $code = (string) ($_GET['code'] ?? '');
        $state = (string) ($_GET['state'] ?? '');
        if ($code === '' || $state === '') {
            throw new AuthException('OAuth sign-in did not complete. Please try again.');
        }

        $connectionId = (string) ($_SESSION['oauth_connection'] ?? $_SESSION['oidc_provider'] ?? '');
        $connection = AuthConfig::connection($connectionId);
        if ($connection === null) {
            throw new AuthException('That OAuth connection is not configured.');
        }

        $claims = OauthClient::handleCallback($code, $state);
        $user = UserIdentityRepository::resolveUser($connection, $claims);
        AuthSession::establish($user);
        AuthSession::redirectHome();
    }

    private static function renderLoginPage(string $mode): void
    {
        $error = '';
        if (!empty($_SESSION['auth_error'])) {
            $error = (string) $_SESSION['auth_error'];
            unset($_SESSION['auth_error']);
        } elseif (defined('LOGON_ERROR')) {
            $error = (string) LOGON_ERROR;
        }

        // AUTH_PAGE_* avoids clashing with config string constants such as AUTH_OAUTH_ENABLED.
        define('AUTH_PAGE_MODE', $mode);
        define('AUTH_ERROR_MESSAGE', $error);
        define('AUTH_PAGE_CONNECTIONS', AuthConfig::connections());
        define('AUTH_PAGE_SHOW_LOCAL', AuthConfig::localEnabled());
        define('AUTH_PAGE_SHOW_OAUTH', AuthConfig::oauthEnabled());

        RenderViews::renderThemePage('login', defined('SET_THEME') ? SET_THEME : 'inlay-stone');
    }
}
