<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Enum\OpenId;

/**
 * Client Authentication Methods
 *
 * As described in Section 9 of OpenID Connect Core 1.0 [OpenID.Core]
 *
 * Other authentication methods MAY be defined by extensions.
 *
 * If omitted, the default is `client_secret_basic` the HTTP Basic
 * Authentication Scheme specified in Section 2.3.1 of OAuth 2.0 [RFC6749].
 */
class AuthenticationMethod
{
    public const DEFAULT = self::CLIENT_SECRET_BASIC;

    /** OpenID Connect Core */
    public const CLIENT_SECRET_BASIC = 'client_secret_basic';

    /** OpenID Connect Core */
    public const CLIENT_SECRET_JWT = 'client_secret_jwt';

    /** OpenID Connect Core */
    public const CLIENT_SECRET_POST = 'client_secret_post';

    /** OpenID Connect Core */
    public const NONE = 'none';

    /** OpenID Connect Core */
    public const PRIVATE_KEY_JWT = 'private_key_jwt';
}
