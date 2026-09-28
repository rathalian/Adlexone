<?php
declare(strict_types=1);

namespace Adlexone\Auth;

/**
 * Sign-in settings from config/auth_settings.json.
 *
 * OAuth here means OpenID Connect (built on OAuth 2.0) — the common standard
 * used by organisation directories. You configure the connection once; you do
 * not need a separate code path per vendor.
 */
final class AuthConfig
{
    public static function localEnabled(): bool
    {
        return self::flag('AUTH_LOCAL_ENABLED', true);
    }

    public static function localRegistrationEnabled(): bool
    {
        return self::flag('AUTH_LOCAL_ALLOW_REGISTRATION', false);
    }

    public static function oauthEnabled(): bool
    {
        return self::flagAny(['AUTH_OAUTH_ENABLED', 'AUTH_OIDC_ENABLED'], false)
            && count(self::connections()) > 0;
    }

    /** @deprecated Use oauthEnabled() */
    public static function oidcEnabled(): bool
    {
        return self::oauthEnabled();
    }

    public static function oauthAutoRedirect(): bool
    {
        return self::flagAny(['AUTH_OAUTH_AUTO_REDIRECT', 'AUTH_OIDC_AUTO_REDIRECT'], false);
    }

    /** @deprecated Use oauthAutoRedirect() */
    public static function oidcAutoRedirect(): bool
    {
        return self::oauthAutoRedirect();
    }

    /**
     * @return list<OauthConnection>
     */
    public static function connections(): array
    {
        $raw = self::stringAny(['AUTH_OAUTH_CONNECTIONS_JSON', 'AUTH_OIDC_PROVIDERS_JSON'], '[]');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $connections = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $issuer = rtrim(trim((string) ($row['issuer'] ?? '')), '/');
            $clientId = trim((string) ($row['client_id'] ?? ''));
            if ($id === '' || $issuer === '' || $clientId === '') {
                continue;
            }
            $defaultLabel = 'Sign in with OAuth';
            $connections[] = new OauthConnection(
                id: $id,
                label: trim((string) ($row['label'] ?? $defaultLabel)) ?: $defaultLabel,
                issuer: $issuer,
                clientId: $clientId,
                clientSecret: (string) ($row['client_secret'] ?? ''),
                scopes: trim((string) ($row['scopes'] ?? 'openid profile email')) ?: 'openid profile email',
                autoProvision: self::truthy($row['auto_provision'] ?? 'No'),
            );
        }

        return $connections;
    }

    /** @deprecated Use connections() */
    public static function providers(): array
    {
        return self::connections();
    }

    public static function connection(string $id): ?OauthConnection
    {
        foreach (self::connections() as $connection) {
            if ($connection->id === $id) {
                return $connection;
            }
        }
        return null;
    }

    /** @deprecated Use connection() */
    public static function provider(string $id): ?OauthConnection
    {
        return self::connection($id);
    }

    public static function callbackUrl(): string
    {
        return rtrim((string) BASE_URL, '/') . '/index.php?action=oauth_callback';
    }

    private static function flag(string $name, bool $default): bool
    {
        if (!defined($name)) {
            return $default;
        }
        return self::truthy(constant($name));
    }

    /** @param list<string> $names */
    private static function flagAny(array $names, bool $default): bool
    {
        foreach ($names as $name) {
            if (defined($name)) {
                return self::truthy(constant($name));
            }
        }
        return $default;
    }

    /** @param list<string> $names */
    private static function stringAny(array $names, string $default): string
    {
        foreach ($names as $name) {
            if (defined($name)) {
                return (string) constant($name);
            }
        }
        return $default;
    }

    private static function truthy(mixed $value): bool
    {
        $v = strtolower(trim((string) $value));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
