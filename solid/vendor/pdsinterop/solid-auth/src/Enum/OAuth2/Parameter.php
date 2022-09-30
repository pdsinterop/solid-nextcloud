<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Enum\OAuth2;

use Pdsinterop\Solid\Auth\Enum\AbstractEnum;

/**
 * OAuth Parameters
 *
 * The keys/values come from the following specifications:
 *
 * - European Telecommunications Standards Institute (ETSI) Network Functions Virtualization (NFV) Security
 * - OpenID - OAuth 2.0 Multiple Response Type Encoding Practices
 * - OpenID - OpenID Connect (OIDC) Core 1.0
 * - OpenID - OpenID Connect Session Management 1.0
 * - RFC6749 - The OAuth 2.0 Authorization Framework
 * - RFC7521 - Assertion Framework for OAuth 2.0 Client Authentication and Authorization Grants
 * - RFC7636 - OAuth Proof Key for Code Exchange (PKCE)
 * - RFC8485 - Vectors of Trust (vot)
 * - RFC8628 - OAuth 2.0 Device Authorization (DA) Grant
 * - RFC8693 - OAuth 2.0 Token Exchange
 * - RFC8707 - Resource Indicators for OAuth 2.0
 * - User-Managed Access (UMA) 2.0 Grant for OAuth 2.0 Authorization
 *
 * @see https://www.iana.org/assignments/oauth-parameters/oauth-parameters.xhtml#parameters
 */
class Parameter extends AbstractEnum
{
    // RFC7649 - OAuth2: authorization response, token response
    public const ACCESS_TOKEN = 'access_token';

    // OpenID - OIDC Core: authorization request
    public const ACR_VALUES = 'acr_values';

    // RFC8693 - OAuth2 Token Exchange: token request
    public const ACTOR_TOKEN = 'actor_token';

    // RFC8693 - OAuth2 Token Exchange: token request
    public const ACTOR_TOKEN_TYPE = 'actor_token_type';

    // RFC7521 - OAuth2 Assertions: token request
    public const ASSERTION = 'assertion';

    // RFC8693 - OAuth2 Token Exchange: token request
    public const AUDIENCE = 'audience';

    // UMA Grant: client request, token endpoint
    public const CLAIM_TOKEN = 'claim_token';

    // OpenID - OIDC Core: authorization request
    public const CLAIMS = 'claims';

    // OpenID - OIDC Core: authorization request
    public const CLAIMS_LOCALES = 'claims_locales';

    // RFC7521 - OAuth2 Assertions: token request
    public const CLIENT_ASSERTION = 'client_assertion';

    // RFC7521 - OAuth2 Assertions: token request
    public const CLIENT_ASSERTION_TYPE = 'client_assertion_type';

    // RFC7649 - OAuth2: authorization request, token request
    public const CLIENT_ID = 'client_id';

    // RFC7649 - OAuth2: token request
    public const CLIENT_SECRET = 'client_secret';

    // RFC7649 - OAuth2: authorization response, token request
    public const CODE = 'code';

    // RFC7636 - PKCE: authorization request
    public const CODE_CHALLENGE = 'code_challenge';

    // RFC7636 - PKCE: authorization request
    public const CODE_CHALLENGE_METHOD = 'code_challenge_method';

    // RFC7636 - PKCE: token request
    public const CODE_VERIFIER = 'code_verifier';

    // RFC8628 - DA Grant: token request
    public const DEVICE_CODE = 'device_code';

    // OpenID - OIDC Core: authorization request
    public const DISPLAY = 'display';

    // RFC7649 - OAuth2: authorization response, token response
    public const ERROR = 'error';

    // RFC7649 - OAuth2: authorization response, token response
    public const ERROR_DESCRIPTION = 'error_description';

    // RFC7649 - OAuth2: authorization response, token response
    public const ERROR_URI = 'error_uri';

    // RFC7649 - OAuth2: authorization response, token response
    public const EXPIRES_IN = 'expires_in';

    // RFC7649 - OAuth2: token request
    public const GRANT_TYPE = 'grant_type';

    // OpenID - OIDC Core: authorization response, access token response
    public const ID_TOKEN = 'id_token';

    // OpenID - OIDC Core: authorization request
    public const ID_TOKEN_HINT = 'id_token_hint';

    // RFC8693 - OAuth2 Token Exchange: token response
    public const ISSUED_TOKEN_TYPE = 'issued_token_type';

    // OpenID - OIDC Core: authorization request
    public const LOGIN_HINT = 'login_hint';

    // OpenID - OIDC Core: authorization request
    public const MAX_AGE = 'max_age';

    // ETSI - NFV-SEC: Access Token Response
    public const NFV_TOKEN = 'nfv_token';

    // OpenID - OIDC Core: authorization request
    public const NONCE = 'nonce';

    // RFC7649 - OAuth2: token request
    public const PASSWORD = 'password';

    // UMA Grant: authorization server response, token endpoint
    // UMA Grant: client request, token endpoint
    public const PCT = 'pct';

    // OpenID - OIDC Core: authorization request
    public const PROMPT = 'prompt';

    // RFC7649 - OAuth2: authorization request, token request
    public const REDIRECT_URI = 'redirect_uri';

    // RFC7649 - OAuth2: token request, token response
    public const REFRESH_TOKEN = 'refresh_token';

    // OpenID - OIDC Core: authorization request
    public const REGISTRATION = 'registration';

    // OpenID - OIDC Core: authorization request
    public const REQUEST = 'request';

    // OpenID - OIDC Core: authorization request
    public const REQUEST_URI = 'request_uri';

    // RFC8693 - OAuth2 Token Exchange: token request
    public const REQUESTED_TOKEN_TYPE = 'requested_token_type';

    // RFC8707 - Oauth2 Resource Indicators: authorization request, token request
    public const RESOURCE = 'resource';

    // OpenID - OAuth2 Response Types: Authorization Request
    public const RESPONSE_MODE = 'response_mode';

    // RFC7649 - OAuth2: authorization request
    public const RESPONSE_TYPE = 'response_type';

    // UMA Grant: client request, token endpoint
    public const RPT = 'rpt';

    // RFC7649 - OAuth2: authorization request, authorization response, token request, token response
    public const SCOPE = 'scope';

    // OpenID - OIDC Sessions: authorization response, access token response
    public const SESSION_STATE = 'session_state';

    // RFC7649 - OAuth2: authorization request, authorization response
    public const STATE = 'state';

    // RFC8693 - OAuth2 Token Exchange: token request
    public const SUBJECT_TOKEN = 'subject_token';

    // RFC8693 - OAuth2 Token Exchange: token request
    public const SUBJECT_TOKEN_TYPE = 'subject_token_type';

    // UMA Grant: client request, token endpoint
    public const TICKET = 'ticket';

    // RFC7649 - OAuth2: authorization response, token response
    public const TOKEN_TYPE = 'token_type';

    // OpenID - OIDC Core: authorization request
    public const UI_LOCALES = 'ui_locales';

    // UMA Grant: authorization server response, token endpoint
    public const UPGRADED = 'upgraded';

    // RFC7649 - OAuth2: token request
    public const USERNAME = 'username';

    // RFC8485 - Vectors of Trust: authorization request, token request
    public const VTR = 'vtr';
}
