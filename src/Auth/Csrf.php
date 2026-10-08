<?php
declare(strict_types=1);

namespace Adlexone\Auth;

/**
 * Session CSRF tokens for authenticated POST forms.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    public const FIELD = '_csrf';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        $token = (string) ($_SESSION[self::SESSION_KEY] ?? '');
        if ($token === '' || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }
        return $token;
    }

    public static function field(): string
    {
        $token = self::token();
        if ($token === '') {
            return '';
        }
        return '<input type="hidden" name="' . self::FIELD . '" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function valid(?string $submitted = null): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $expected = (string) ($_SESSION[self::SESSION_KEY] ?? '');
        if ($expected === '') {
            return false;
        }
        $got = $submitted ?? (string) ($_POST[self::FIELD] ?? '');
        return hash_equals($expected, $got);
    }

    public static function enforceOrDeny(): void
    {
        if (self::valid()) {
            return;
        }
        Access::deny();
    }
}
