<?php
declare(strict_types=1);

namespace Adlexone\Auth;

use Adlexone\support\Database;

/**
 * Establishes the session contract the rest of Adlexone already expects.
 * Authorization is permission-based; access_role_id is derived for legacy checks.
 */
final class AuthSession
{
    /**
     * @param array<string, mixed> $user
     */
    public static function establish(array $user): void
    {
        if (($user['lastactive'] ?? '') === 'inactive') {
            throw new AuthException('This account is inactive.');
        }

        session_regenerate_id(true);

        $_SESSION['access_user_id'] = $user['user_id'];
        $_SESSION['access_theme'] = $user['theme'] ?? '';
        $_SESSION['access_language'] = $user['language'] ?? (defined('SET_DEFAULT_LANGUAGE') ? SET_DEFAULT_LANGUAGE : 'English');
        $_SESSION['access_home_controller_name'] = $user['home_controller_name'] ?? '';
        $_SESSION['access_show_header'] = $user['show_header'] ?? 'Yes';
        $_SESSION['access_show_graphics'] = $user['show_graphics'] ?? 'Yes';
        $_SESSION['access_home_controller'] = !empty($user['home_controller']) ? $user['home_controller'] : 'quick_launch';

        Access::hydrateSession((int) $user['user_id']);
    }

    public static function clear(): void
    {
        unset(
            $_SESSION['access_role_id'],
            $_SESSION['access_permissions'],
            $_SESSION['access_user_id'],
            $_SESSION['access_user_name'],
            $_SESSION['access_theme'],
            $_SESSION['access_language'],
            $_SESSION['access_home_controller_name'],
            $_SESSION['access_show_header'],
            $_SESSION['access_show_graphics'],
            $_SESSION['access_home_controller'],
            $_SESSION['oauth_state'],
            $_SESSION['oauth_nonce'],
            $_SESSION['oauth_verifier'],
            $_SESSION['oauth_connection'],
            $_SESSION['oidc_state'],
            $_SESSION['oidc_nonce'],
            $_SESSION['oidc_verifier'],
            $_SESSION['oidc_provider'],
            $_SESSION['auth_error']
        );
    }

    public static function redirectHome(): never
    {
        $controller = $_SESSION['access_home_controller'] ?? 'quick_launch';
        $url = rtrim((string) BASE_URL, '/') . '/index.php?controller=' . rawurlencode((string) $controller);
        header('Location: ' . $url);
        exit;
    }

    public static function redirectToLogin(?string $error = null): never
    {
        if ($error !== null) {
            $_SESSION['auth_error'] = $error;
        }
        header('Location: ' . rtrim((string) BASE_URL, '/') . '/index.php');
        exit;
    }
}
