<?php
declare(strict_types=1);

namespace Adlexone\Auth;

/**
 * OAuth sign-in using OpenID Connect (authorization code + PKCE).
 *
 * This is the common standard used by organisation directories. Configure one
 * connection with an issuer URL and app credentials — the same flow works
 * across vendors that support OpenID Connect.
 */
final class OauthClient
{
    public static function authorizationUrl(OauthConnection $connection): string
    {
        $discovery = self::discover($connection->issuer);
        $state = self::randomUrlSafe(32);
        $nonce = self::randomUrlSafe(32);
        $verifier = self::randomUrlSafe(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_nonce'] = $nonce;
        $_SESSION['oauth_verifier'] = $verifier;
        $_SESSION['oauth_connection'] = $connection->id;

        $params = [
            'client_id' => $connection->clientId,
            'response_type' => 'code',
            'redirect_uri' => AuthConfig::callbackUrl(),
            'response_mode' => 'query',
            'scope' => $connection->scopes,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        return $discovery['authorization_endpoint'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, mixed> ID token claims
     */
    public static function handleCallback(string $code, string $state): array
    {
        $savedState = (string) ($_SESSION['oauth_state'] ?? $_SESSION['oidc_state'] ?? '');
        $savedNonce = (string) ($_SESSION['oauth_nonce'] ?? $_SESSION['oidc_nonce'] ?? '');
        $savedVerifier = (string) ($_SESSION['oauth_verifier'] ?? $_SESSION['oidc_verifier'] ?? '');
        $savedConnection = (string) ($_SESSION['oauth_connection'] ?? $_SESSION['oidc_provider'] ?? '');

        if (
            $savedState === ''
            || $savedNonce === ''
            || $savedVerifier === ''
            || $savedConnection === ''
            || !hash_equals($savedState, $state)
        ) {
            throw new AuthException('This sign-in attempt expired. Please try again.');
        }

        $connection = AuthConfig::connection($savedConnection);
        if ($connection === null) {
            throw new AuthException('That OAuth connection is not configured.');
        }

        $discovery = self::discover($connection->issuer);
        $tokenResponse = self::exchangeCode($discovery['token_endpoint'], $connection, $code, $savedVerifier);
        $idToken = (string) ($tokenResponse['id_token'] ?? '');
        if ($idToken === '') {
            throw new AuthException('OAuth sign-in did not return an ID token.');
        }

        $claims = self::validateIdToken($idToken, $connection, $discovery, $savedNonce);

        unset(
            $_SESSION['oauth_state'],
            $_SESSION['oauth_nonce'],
            $_SESSION['oauth_verifier'],
            $_SESSION['oauth_connection'],
            $_SESSION['oidc_state'],
            $_SESSION['oidc_nonce'],
            $_SESSION['oidc_verifier'],
            $_SESSION['oidc_provider']
        );

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    private static function discover(string $issuer): array
    {
        $url = rtrim($issuer, '/') . '/.well-known/openid-configuration';
        $json = self::httpGet($url);
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['authorization_endpoint']) || empty($data['token_endpoint']) || empty($data['jwks_uri'])) {
            throw new AuthException('Could not read OAuth / OpenID Connect settings from the issuer URL.');
        }
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function exchangeCode(string $tokenEndpoint, OauthConnection $connection, string $code, string $verifier): array
    {
        $body = http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $connection->clientId,
            'client_secret' => $connection->clientSecret,
            'code' => $code,
            'redirect_uri' => AuthConfig::callbackUrl(),
            'code_verifier' => $verifier,
        ], '', '&');

        $json = self::httpPost($tokenEndpoint, $body, 'application/x-www-form-urlencoded');
        $data = json_decode($json, true);
        if (!is_array($data) || isset($data['error'])) {
            $detail = is_array($data) ? (string) ($data['error_description'] ?? $data['error'] ?? 'token error') : 'token error';
            throw new AuthException('OAuth token exchange failed: ' . $detail);
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $discovery
     * @return array<string, mixed>
     */
    private static function validateIdToken(string $jwt, OauthConnection $connection, array $discovery, string $expectedNonce): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new AuthException('The OAuth ID token was not valid.');
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $header = json_decode(self::b64UrlDecode($headerB64), true);
        $payload = json_decode(self::b64UrlDecode($payloadB64), true);
        if (!is_array($header) || !is_array($payload)) {
            throw new AuthException('The OAuth ID token was not valid.');
        }

        $alg = (string) ($header['alg'] ?? '');
        if ($alg !== 'RS256') {
            throw new AuthException('Unsupported OAuth ID token signing method.');
        }

        self::verifyRs256($headerB64 . '.' . $payloadB64, $sigB64, (string) ($header['kid'] ?? ''), (string) $discovery['jwks_uri']);

        $now = time();
        if (($payload['exp'] ?? 0) < $now) {
            throw new AuthException('The OAuth sign-in token has expired. Please try again.');
        }
        if (($payload['nbf'] ?? $now) > ($now + 60)) {
            throw new AuthException('The OAuth sign-in token is not valid yet. Please try again.');
        }
        if (!hash_equals((string) ($payload['iss'] ?? ''), $connection->issuer)) {
            throw new AuthException('OAuth issuer did not match the configured connection.');
        }

        $aud = $payload['aud'] ?? '';
        $audOk = is_array($aud)
            ? in_array($connection->clientId, $aud, true)
            : hash_equals((string) $aud, $connection->clientId);
        if (!$audOk) {
            throw new AuthException('OAuth client ID did not match the configured connection.');
        }

        if (!hash_equals((string) ($payload['nonce'] ?? ''), $expectedNonce)) {
            throw new AuthException('OAuth sign-in could not be verified. Please try again.');
        }

        return $payload;
    }

    private static function verifyRs256(string $signingInput, string $sigB64, string $kid, string $jwksUri): void
    {
        $jwks = json_decode(self::httpGet($jwksUri), true);
        if (!is_array($jwks) || empty($jwks['keys']) || !is_array($jwks['keys'])) {
            throw new AuthException('Could not load signing keys.');
        }

        $jwk = null;
        foreach ($jwks['keys'] as $key) {
            if (!is_array($key)) {
                continue;
            }
            if ($kid !== '' && ($key['kid'] ?? '') === $kid) {
                $jwk = $key;
                break;
            }
            if ($kid === '' && ($key['kty'] ?? '') === 'RSA') {
                $jwk = $key;
            }
        }
        if ($jwk === null) {
            throw new AuthException('No matching signing key for ID token.');
        }

        $pem = self::jwkToPem($jwk);
        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) {
            throw new AuthException('Invalid signing key.');
        }

        $signature = self::b64UrlDecode($sigB64);
        $ok = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new AuthException('ID token signature is invalid.');
        }
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private static function jwkToPem(array $jwk): string
    {
        $n = self::b64UrlDecode((string) ($jwk['n'] ?? ''));
        $e = self::b64UrlDecode((string) ($jwk['e'] ?? ''));
        if ($n === '' || $e === '') {
            throw new AuthException('Incomplete RSA signing key.');
        }

        $modulus = self::asn1Integer($n);
        $exponent = self::asn1Integer($e);
        $rsaPublicKey = self::asn1Sequence($modulus . $exponent);
        $bitString = "\x03" . self::asn1Length(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;
        $oid = hex2bin('300d06092a864886f70d0101010500');
        $publicKeyInfo = self::asn1Sequence($oid . $bitString);
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($publicKeyInfo), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private static function asn1Length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function asn1Integer(string $bytes): string
    {
        if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::asn1Length(strlen($bytes)) . $bytes;
    }

    private static function asn1Sequence(string $der): string
    {
        return "\x30" . self::asn1Length(strlen($der)) . $der;
    }

    private static function b64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }

    private static function randomUrlSafe(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    private static function httpGet(string $url): string
    {
        return self::httpRequest('GET', $url);
    }

    private static function httpPost(string $url, string $body, string $contentType): string
    {
        return self::httpRequest('POST', $url, $body, $contentType);
    }

    private static function httpRequest(string $method, string $url, ?string $body = null, ?string $contentType = null): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new AuthException('Unable to start HTTP client.');
            }
            $headers = ['Accept: application/json'];
            if ($contentType !== null) {
                $headers[] = 'Content-Type: ' . $contentType;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                throw new AuthException('HTTP request failed: ' . $error);
            }
            if ($status >= 400) {
                throw new AuthException('The OAuth service returned an error (HTTP ' . $status . ').');
            }
            return (string) $response;
        }

        $header = "Accept: application/json\r\n";
        if ($contentType !== null) {
            $header .= 'Content-Type: ' . $contentType . "\r\n";
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $header,
                'content' => $body ?? '',
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new AuthException('HTTP request failed.');
        }
        return $response;
    }
}
