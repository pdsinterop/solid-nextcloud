<?php

namespace Pdsinterop\Solid\Auth\Enum\OpenId;

/**
 * Client Metadata
 *
 * As described in Section 2 of OpenID Connect Dynamic Client Registration 1.0 [OpenID.Registration]
 *
 * Only the `REDIRECT_URIS` value is required, all other values are optional.
 *
 * @see https://openid.net/specs/openid-connect-registration-1_0.html
 */
class DynamicClientRegistration
{
    /**
     * Kind of the application. The default, if omitted, is web. The defined
     * values are native or web. Web Clients using the OAuth Implicit Grant Type
     * MUST only register URLs using the https scheme as redirect_uris; they
     * MUST NOT use localhost as the hostname. Native Clients MUST only register
     * redirect_uris using custom URI schemes or URLs using the http: scheme
     * with localhost as the hostname. Authorization Servers MAY place
     * additional constraints on Native Clients. Authorization Servers MAY
     * reject Redirection URI values using the http scheme, other than the
     * localhost case for Native Clients. The Authorization Server MUST verify
     * that all the registered redirect_uris conform to these constraints. This
     * prevents sharing a Client ID across different types of Clients.
     */
    public const APPLICATION_TYPE = 'application_type';

    /**
     * Name of the Client to be presented to the End-User. If desired,
     * representation of this Claim in different languages and scripts is
     * represented as described in Section 2.1.
     */
    public const CLIENT_NAME = 'client_name';

    /**
     * URL of the home page of the Client. The value of this field MUST point to
     * a valid Web page. If present, the server SHOULD display this URL to the
     * End-User in a followable fashion. If desired, representation of this
     * Claim in different languages and scripts is represented as described in
     * Section 2.1.
     */
    public const CLIENT_URI = 'client_uri';

    /**
     * URL of the home page of the Client. The value of this field MUST point to
     * a valid Web page. If present, the server SHOULD display this URL to the
     * End-User in a followable fashion. If desired, representation of this
     * Claim in different languages and scripts is represented as described in
     * Section 2.1.
     */
    public const CONTACTS = 'contacts';

    /**
     * Default requested Authentication Context Class Reference values. Array of strings that specifies the default acr values that the OP is being requested to use for processing requests from this Client, with the values appearing in order of preference. The Authentication Context Class satisfied by the authentication performed is returned as the acr Claim Value in the issued ID Token. The acr Claim is requested as a Voluntary Claim by this parameter. The acr_values_supported discovery element contains a list of the supported acr values supported by this server. Values specified in the acr_values request parameter or an individual acr Claim request override these default values.
     */
    public const DEFAULT_ACR_VALUES = 'default_acr_values';

    /**
     * Default Maximum Authentication Age. Specifies that the End-User MUST be actively authenticated if the End-User was authenticated longer ago than the specified number of seconds. The max_age request parameter overrides this default value. If omitted, no default Maximum Authentication Age is specified.
     */
    public const DEFAULT_MAX_AGE = 'default_max_age';

    /**
     * JSON array containing a list of the OAuth 2.0 Grant Types that the Client
     * is declaring that it will restrict itself to using. The Grant Type values
     * used by OpenID Connect are:
     *
     * - authorization_code: The Authorization Code Grant Type described in OAuth 2.0 Section 4.1.
     * - implicit: The Implicit Grant Type described in OAuth 2.0 Section 4.2.
     * - refresh_token: The Refresh Token Grant Type described in OAuth 2.0 Section 6.
     *
     * The following table lists the correspondence between response_type values
     * that the Client will use and grant_type values that MUST be included in
     * the registered grant_types list:
     *
     * - code                : authorization_code
     * - id_token            : implicit
     * - token id_token      : implicit
     * - code id_token       : authorization_code, implicit
     * - code token          : authorization_code, implicit
     * - code token id_token : authorization_code, implicit
     *
     * If omitted, the default is that the Client will use only the authorization_code Grant Type.
     */
    public const GRANT_TYPES = 'grant_types';

    /**
     * JWE alg algorithm [JWA] REQUIRED for encrypting the ID Token issued to
     * this Client. If this is requested, the response will be signed then
     * encrypted, with the result being a Nested JWT, as defined in [JWT].
     * The default, if omitted, is that no encryption is performed.
     */
    public const ID_TOKEN_ENCRYPTED_RESPONSE_ALG = 'id_token_encrypted_response_alg';

    /**
     * JWE enc algorithm [JWA] REQUIRED for encrypting the ID Token issued to
     * this Client. If id_token_encrypted_response_alg is specified, the default
     * for this value is A128CBC-HS256. When id_token_encrypted_response_enc is
     * included, id_token_encrypted_response_alg MUST also be provided.
     */
    public const ID_TOKEN_ENCRYPTED_RESPONSE_ENC = 'id_token_encrypted_response_enc';

    /**
     * JWS alg algorithm [JWA] REQUIRED for signing the ID Token issued to this
     * Client. The value none MUST NOT be used as the ID Token alg value unless
     * the Client uses only Response Types that return no ID Token from the
     * Authorization Endpoint (such as when only using the Authorization Code
     * Flow). The default, if omitted, is RS256. The public key for validating
     * the signature is provided by retrieving the JWK Set referenced by the
     * jwks_uri element from OpenID Connect Discovery 1.0 [OpenID.Discovery].
     */
    public const ID_TOKEN_SIGNED_RESPONSE_ALG = 'id_token_signed_response_alg';

    /**
     * URI using the https scheme that a third party can use to initiate a login
     * by the RP, as specified in Section 4 of OpenID Connect Core 1.0 [OpenID.Core].
     *
     * The URI MUST accept requests via both GET and POST. The Client MUST
     * understand the login_hint and iss parameters and SHOULD support the
     * target_link_uri parameter.
     */
    public const INITIATE_LOGIN_URI = 'initiate_login_uri';

    /**
     * Client's JSON Web Key Set [JWK] document, passed by value. The semantics
     * of the jwks parameter are the same as the jwks_uri parameter, other than
     * that the JWK Set is passed by value, rather than by reference. This
     * parameter is intended only to be used by Clients that, for some reason,
     * are unable to use the jwks_uri parameter, for instance, by native
     * applications that might not have a location to host the contents of the
     * JWK Set. If a Client can use jwks_uri, it MUST NOT use jwks. One
     * significant downside of jwks is that it does not enable key rotation
     * (which jwks_uri does, as described in Section 10 of OpenID Connect Core
     * 1.0 [OpenID.Core]). The jwks_uri and jwks parameters MUST NOT be used
     * together.
     */
    public const JWKS = 'jwks';

    /**
     * URL for the Client's JSON Web Key Set [JWK] document. If the Client signs
     * requests to the Server, it contains the signing key(s) the Server uses to
     * validate signatures from the Client. The JWK Set MAY also contain the
     * Client's encryption keys(s), which are used by the Server to encrypt
     * responses to the Client. When both signing and encryption keys are made
     * available, a use (Key Use) parameter value is REQUIRED for all keys in
     * the referenced JWK Set to indicate each key's intended usage. Although
     * some algorithms allow the same key to be used for both signatures and
     * encryption, doing so is NOT RECOMMENDED, as it is less secure. The JWK
     * x5c parameter MAY be used to provide X.509 representations of keys
     * provided. When used, the bare key values MUST still be present and MUST
     * match those in the certificate.
     */
    public const JWKS_URI = 'jwks_uri';

    /**
     * URL that references a logo for the Client application. If present, the
     * server SHOULD display this image to the End-User during approval. The
     * value of this field MUST point to a valid image file. If desired,
     * representation of this Claim in different languages and scripts is
     * represented as described in Section 2.1.
     */
    public const LOGO_URI = 'logo_uri';


    /**
     * URL that the Relying Party Client provides to the End-User to read about
     * the how the profile data will be used. The value of this field MUST point
     * to a valid web page. The OpenID Provider SHOULD display this URL to the
     * End-User if it is given. If desired, representation of this Claim in
     * different languages and scripts is represented as described in Section 2.1.
     */
    public const POLICY_URI = 'policy_uri';

    /**
     * Array of Redirection URI values used by the Client. One of these
     * registered Redirection URI values MUST exactly match the redirect_uri
     * parameter value used in each Authorization Request, with the matching
     * performed as described in Section 6.2.1 of [RFC3986] (Simple String
     * Comparison).
     */
    public const REDIRECT_URIS = 'redirect_uris';

    /**
     * JWE [JWE] alg algorithm [JWA] the RP is declaring that it may use for
     * encrypting Request Objects sent to the OP. This parameter SHOULD be
     * included when symmetric encryption will be used, since this signals to
     * the OP that a client_secret value needs to be returned from which the
     * symmetric key will be derived, that might not otherwise be returned. The
     * RP MAY still use other supported encryption algorithms or send
     * unencrypted Request Objects, even when this parameter is present. If both
     * signing and encryption are requested, the Request Object will be signed
     * then encrypted, with the result being a Nested JWT, as defined in [JWT].
     * The default, if omitted, is that the RP is not declaring whether it might
     * encrypt any Request Objects.
     */
    public const REQUEST_OBJECT_ENCRYPTION_ALG = 'request_object_encryption_alg';

    /**
     * JWE enc algorithm [JWA] the RP is declaring that it may use for
     * encrypting Request Objects sent to the OP. If
     * request_object_encryption_alg is specified, the default for this value is
     * A128CBC-HS256. When request_object_encryption_enc is included,
     * request_object_encryption_alg MUST also be provided.
     */
    public const REQUEST_OBJECT_ENCRYPTION_ENC = 'request_object_encryption_enc';

    /**
     * JWS [JWS] alg algorithm [JWA] that MUST be used for signing Request
     * Objects sent to the OP. All Request Objects from this Client MUST be
     * rejected, if not signed with this algorithm. Request Objects are
     * described in Section 6.1 of OpenID Connect Core 1.0 [OpenID.Core].
     * This algorithm MUST be used both when the Request Object is passed by
     * value (using the request parameter) and when it is passed by reference
     * (using the request_uri parameter). Servers SHOULD support RS256. The
     * value none MAY be used. The default, if omitted, is that any algorithm
     * supported by the OP and the RP MAY be used.
     */
    public const REQUEST_OBJECT_SIGNING_ALG = 'request_object_signing_alg';

    /**
     * Array of request_uri values that are pre-registered by the RP for use at
     * the OP. Servers MAY cache the contents of the files referenced by these
     * URIs and not retrieve them at the time they are used in a request. OPs
     * can require that request_uri values used be pre-registered with the
     * require_request_uri_registration discovery parameter.
     *
     * If the contents of the request file could ever change, these URI values
     * SHOULD include the base64url encoded SHA-256 hash value of the file
     * contents referenced by the URI as the value of the URI fragment. If the
     * fragment value used for a URI changes, that signals the server that its
     * cached value for that URI with the old fragment value is no longer valid.
     */
    public const REQUEST_URIS = 'request_uris';

    /**
     * Boolean value specifying whether the auth_time Claim in the ID Token is
     * REQUIRED. It is REQUIRED when the value is true. (If this is false, the
     * auth_time Claim can still be dynamically requested as an individual Claim
     * for the ID Token using the claims request parameter described in Section
     * 5.5.1 of OpenID Connect Core 1.0 [OpenID.Core].) If omitted, the default
     * value is false.
     */
    public const REQUIRE_AUTH_TIME = 'require_auth_time';

    /**
     * JSON array containing a list of the OAuth 2.0 response_type values that
     * the Client is declaring that it will restrict itself to using. If omitted,
     * the default is that the Client will use only the code Response Type.
     */
    public const RESPONSE_TYPES = 'response_types';

    /**
     * URL using the https scheme to be used in calculating Pseudonymous
     * Identifiers by the OP. The URL references a file with a single JSON array
     * of redirect_uri values. Please see Section 5. Providers that use pairwise
     * sub (subject) values SHOULD utilize the sector_identifier_uri value
     * provided in the Subject Identifier calculation for pairwise identifiers.
     */
    public const SECTOR_IDENTIFIER_URI = 'sector_identifier_uri';

    /**
     * subject_type requested for responses to this Client. The
     * subject_types_supported Discovery parameter contains a list of the
     * supported subject_type values for this server. Valid types include
     * pairwise and public.
     */
    public const SUBJECT_TYPE = 'subject_type';

    /**
     * Requested Client Authentication method for the Token Endpoint. The
     * options are client_secret_post, client_secret_basic, client_secret_jwt,
     * private_key_jwt, and none, as described in Section 9 of OpenID Connect
     * Core 1.0 [OpenID.Core]. Other authentication methods MAY be defined by
     * extensions. If omitted, the default is client_secret_basic -- the HTTP
     * Basic Authentication Scheme specified in Section 2.3.1 of OAuth 2.0
     * [RFC6749].
     */
    public const TOKEN_ENDPOINT_AUTH_METHOD = 'token_endpoint_auth_method';

    /**
     * JWS [JWS] alg algorithm [JWA] that MUST be used for signing the JWT [JWT]
     * used to authenticate the Client at the Token Endpoint for the
     * private_key_jwt and client_secret_jwt authentication methods. All Token
     * Requests using these authentication methods from this Client MUST be
     * rejected, if the JWT is not signed with this algorithm. Servers SHOULD
     * support RS256. The value none MUST NOT be used. The default, if omitted,
     * is that any algorithm supported by the OP and the RP MAY be used.
     */
    public const TOKEN_ENDPOINT_AUTH_SIGNING_ALG = 'token_endpoint_auth_signing_alg';

    /**
     * URL that the Relying Party Client provides to the End-User to read about
     * the Relying Party's terms of service. The value of this field MUST point
     * to a valid web page. The OpenID Provider SHOULD display this URL to the
     * End-User if it is given. If desired, representation of this Claim in
     * different languages and scripts is represented as described in Section 2.1.
     */
    public const TOS_URI = 'tos_uri';

    /**
     * JWE [JWE] alg algorithm [JWA] REQUIRED for encrypting UserInfo Responses.
     * If both signing and encryption are requested, the response will be signed
     * then encrypted, with the result being a Nested JWT, as defined in [JWT].
     * The default, if omitted, is that no encryption is performed.
     */
    public const USERINFO_ENCRYPTED_RESPONSE_ALG = 'userinfo_encrypted_response_alg';

    /**
     * JWE enc algorithm [JWA] REQUIRED for encrypting UserInfo Responses. If
     * userinfo_encrypted_response_alg is specified, the default for this value
     * is A128CBC-HS256. When userinfo_encrypted_response_enc is included,
     * userinfo_encrypted_response_alg MUST also be provided.
     */
    public const USERINFO_ENCRYPTED_RESPONSE_ENC = 'userinfo_encrypted_response_enc';

    /**
     * JWS alg algorithm [JWA] REQUIRED for signing UserInfo Responses. If this
     * is specified, the response will be JWT [JWT] serialized, and signed using
     * JWS. The default, if omitted, is for the UserInfo Response to return the
     * Claims as a UTF-8 encoded JSON object using the application/json
     * content-type.
     */
    public const USERINFO_SIGNED_RESPONSE_ALG = 'userinfo_signed_response_alg';
}
