<?php
declare(strict_types=1);

namespace Adlexone\Auth;

/**
 * One OAuth (OpenID Connect) sign-in connection.
 *
 * Fill in the issuer URL and app credentials from whichever directory you use.
 * The same settings work with common systems that support this standard.
 */
final class OauthConnection
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $issuer,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $scopes,
        public readonly bool $autoProvision,
    ) {
    }
}
