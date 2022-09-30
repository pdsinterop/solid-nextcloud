<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Enum\OAuth2;

/**
 * Enum class representing valid values for Grant Types
 *
 * The values are taken ad-verbatim from RFC6749, they are used as identifiers
 * by the League OAuth2 Server and Client libraries.
 *
 * Any advise whether or not to use specific grants is taken directly from the
 * OAuth 2.0 Security Best Current Practices:
 * https://tools.ietf.org/html/draft-ietf-oauth-security-topics
 */
class GrantType
{
    /**
     * RFC6749 - OAuth2: Authorization Code Grant
     * RFC7636 - Proof Key for Code Exchange (PKCE)
     *
     * It is recommended that all clients use the PKCE extension with this flow
     * as well to provide better security.
     */
    public const AUTH_CODE = 'authorization_code';

    // RFC6749 - OAuth2: Client Credentials Grant
    public const CLIENT_CREDENTIALS = 'client_credentials';

    // RFC8628: OAuth 2.0 Device Authorization Grant
    public const DEVICE_CODE = "urn:ietf:params:oauth:grant-type:device_code";

    /**
     * RFC6749 - OAuth2: Implicit Grant
     *
     * @deprecated Please use Authorization Code flow with PKCE instead!
     */
    public const IMPLICIT = 'implicit';

    /**
     * RFC6749 - OAuth2: Resource Owner Password Credentials Grant
     *
     * @deprecated
     */
    public const PASSWORD = 'password';

    // RFC6749 - OAuth2: Refresh Token Grant
    public const REFRESH_TOKEN = 'refresh_token';

}
