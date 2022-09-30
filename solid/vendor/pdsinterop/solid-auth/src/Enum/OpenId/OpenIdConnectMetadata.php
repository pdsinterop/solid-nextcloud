<?php

namespace Pdsinterop\Solid\Auth\Enum\OpenId;

use Pdsinterop\Solid\Auth\Enum\AbstractEnum;

/**
 * Metadata describing an OpenID Provider's configuration for OpenID Connect.
 *
 * @see https://openid.net/specs/openid-connect-discovery-1_0.html
 */
class OpenIdConnectMetadata extends AbstractEnum
{
    /**
     * JSON array containing a list of the Authentication Context Class References that this OP supports.
     */
    public const ACR_VALUES_SUPPORTED = 'acr_values_supported';

    /**
     * URL of the OP's OAuth 2.0 Authorization Endpoint [OpenID.Core].
     *
     * @required
     */
    public const AUTHORIZATION_ENDPOINT = 'authorization_endpoint';

    /**
     * JSON array containing a list of the Claim Types that the OpenID Provider supports. These Claim Types are described in Section 5.6 of OpenID Connect Core 1.0 [OpenID.Core]. Values defined by this specification are normal, aggregated, and distributed. If omitted, the implementation supports only normal Claims.
     */
    public const CLAIM_TYPES_SUPPORTED = 'claim_types_supported';

    /**
     * Languages and scripts supported for values in Claims being returned, represented as a JSON array of BCP47 [RFC5646] language tag values. Not all languages and scripts are necessarily supported for all Claim values.
     */
    public const CLAIMS_LOCALES_SUPPORTED = 'claims_locales_supported';

    /**
     * Boolean value specifying whether the OP supports use of the claims parameter, with true indicating support. If omitted, the default value is false.
     */
    public const CLAIMS_PARAMETER_SUPPORTED = 'claims_parameter_supported';

    /**
     * JSON array containing a list of the Claim Names of the Claims that the OpenID Provider MAY be able to supply values for. Note that for privacy or other reasons, this might not be an exhaustive list.
     *
     * @recommended
     */
    public const CLAIMS_SUPPORTED = 'claims_supported';

    /**
     * JSON array containing a list of the Code challenge methods supported.
     *
     * @recommended
     */
    public const CODE_CHALLENGE_METHODS_SUPPORTED = 'code_challenge_methods_supported';

    /**
     * JSON array containing a list of the JWS alg values supported by the authorization server for DPoP proof JWTs.
     *
     * @recommended
     */
    public const DPOP_SIGNING_ALG_VALUES_SUPPORTED = 'dpop_signing_alg_values_supported';

    /**
     * JSON array containing a list of the display parameter values that the OpenID Provider supports. These values are described in Section 3.1.2.1 of OpenID Connect Core 1.0 [OpenID.Core].
     */
    public const DISPLAY_VALUES_SUPPORTED = 'display_values_supported';

    /**
     * JSON array containing a list of the OAuth 2.0 Grant Type values that this OP supports. Dynamic OpenID Providers MUST support the authorization_code and implicit Grant Type values and MAY support other Grant Types. If omitted, the default value is ["authorization_code", "implicit"].
     */
    public const GRANT_TYPES_SUPPORTED = 'grant_types_supported';

    /**
     * JSON array containing a list of the JWE encryption algorithms (alg values) supported by the OP for the ID Token to encode the Claims in a JWT [JWT].
     */
    public const ID_TOKEN_ENCRYPTION_ALG_VALUES_SUPPORTED = 'id_token_encryption_alg_values_supported';

    /**
     * JSON array containing a list of the JWE encryption algorithms (enc values) supported by the OP for the ID Token to encode the Claims in a JWT [JWT].
     */
    public const ID_TOKEN_ENCRYPTION_ENC_VALUES_SUPPORTED = 'id_token_encryption_enc_values_supported';

    /**
     * JSON array containing a list of the JWS signing algorithms (alg values) supported by the OP for the ID Token to encode the Claims in a JWT [JWT]. The algorithm RS256 MUST be included. The value none MAY be supported, but MUST NOT be used unless the Response Type used returns no ID Token from the Authorization Endpoint (such as when using the Authorization Code Flow).
     *
     * @required
     */
    public const ID_TOKEN_SIGNING_ALG_VALUES_SUPPORTED = 'id_token_signing_alg_values_supported';

    /**
     * URL using the https scheme with no query or fragment component that the OP asserts as its Issuer Identifier. If Issuer discovery is supported (see Section 2), this value MUST be identical to the issuer value returned by WebFinger. This also MUST be identical to the iss Claim value in ID Tokens issued from this Issuer.
     *
     * @required
     */
    public const ISSUER = 'issuer';

    /**
     * URL of the OP's JSON Web Key Set [JWK] document. This contains the signing key(s) the RP uses to validate signatures from the OP. The JWK Set MAY also contain the Server's encryption key(s), which are used by RPs to encrypt requests to the Server. When both signing and encryption keys are made available, a use (Key Use) parameter value is REQUIRED for all keys in the referenced JWK Set to indicate each key's intended usage. Although some algorithms allow the same key to be used for both signatures and encryption, doing so is NOT RECOMMENDED, as it is less secure. The JWK x5c parameter MAY be used to provide X.509 representations of keys provided. When used, the bare key values MUST still be present and MUST match those in the certificate.
     *
     * @required
     */
    public const JWKS_URI = 'jwks_uri';

    /**
     * URL that the OpenID Provider provides to the person registering the Client to read about the OP's requirements on how the Relying Party can use the data provided by the OP. The registration process SHOULD display this URL to the person registering the Client if it is given.
     */
    public const OP_POLICY_URI = 'op_policy_uri';

    /**
     * URL that the OpenID Provider provides to the person registering the Client to read about OpenID Provider's terms of service. The registration process SHOULD display this URL to the person registering the Client if it is given.
     */
    public const OP_TOS_URI = 'op_tos_uri';

    /**
     * URL of the OP's Dynamic Client Registration Endpoint [OpenID.Registration].
     *
     * @recommended
     */
    public const REGISTRATION_ENDPOINT = 'registration_endpoint';

    /**
     * JSON array containing a list of the JWE encryption algorithms (alg values) supported by the OP for Request Objects. These algorithms are used both when the Request Object is passed by value and when it is passed by reference.
     */
    public const REQUEST_OBJECT_ENCRYPTION_ALG_VALUES_SUPPORTED = 'request_object_encryption_alg_values_supported';

    /**
     * JSON array containing a list of the JWE encryption algorithms (enc values) supported by the OP for Request Objects. These algorithms are used both when the Request Object is passed by value and when it is passed by reference.
     */
    public const REQUEST_OBJECT_ENCRYPTION_ENC_VALUES_SUPPORTED = 'request_object_encryption_enc_values_supported';

    /**
     * JSON array containing a list of the JWS signing algorithms (alg values) supported by the OP for Request Objects, which are described in Section 6.1 of OpenID Connect Core 1.0 [OpenID.Core]. These algorithms are used both when the Request Object is passed by value (using the request parameter) and when it is passed by reference (using the request_uri parameter). Servers SHOULD support none and RS256.
     */
    public const REQUEST_OBJECT_SIGNING_ALG_VALUES_SUPPORTED = 'request_object_signing_alg_values_supported';

    /**
     * Boolean value specifying whether the OP supports use of the request parameter, with true indicating support. If omitted, the default value is false.
     */
    public const REQUEST_PARAMETER_SUPPORTED = 'request_parameter_supported';

    /**
     * Boolean value specifying whether the OP supports use of the request_uri parameter, with true indicating support. If omitted, the default value is true.
     */
    public const REQUEST_URI_PARAMETER_SUPPORTED = 'request_uri_parameter_supported';

    /**
     * Boolean value specifying whether the OP requires any request_uri values used to be pre-registered using the request_uris registration parameter. Pre-registration is REQUIRED when the value is true. If omitted, the default value is false.
     */
    public const REQUIRE_REQUEST_URI_REGISTRATION = 'require_request_uri_registration';

    /**
     * JSON array containing a list of the OAuth 2.0 response_mode values that this OP supports, as specified in OAuth 2.0 Multiple Response Type Encoding Practices [OAuth.Responses]. If omitted, the default for Dynamic OpenID Providers is ["query", "fragment"].
     */
    public const RESPONSE_MODES_SUPPORTED = 'response_modes_supported';

    /**
     * JSON array containing a list of the OAuth 2.0 response_type values that this OP supports. Dynamic OpenID Providers MUST support the code, id_token, and the token id_token Response Type values.
     *
     * @required
     */
    public const RESPONSE_TYPES_SUPPORTED = 'response_types_supported';

    /**
     * JSON array containing a list of the OAuth 2.0 [RFC6749] scope values that this server supports. The server MUST support the openid scope value. Servers MAY choose not to advertise some supported scope values even when this parameter is used, although those defined in [OpenID.Core] SHOULD be listed, if supported.
     *
     * @recommended
     */
    public const SCOPES_SUPPORTED = 'scopes_supported';

    /**
     * URL of a page containing human-readable information that developers might want or need to know when using the OpenID Provider. In particular, if the OpenID Provider does not support Dynamic Client Registration, then information on how to register Clients needs to be provided in this documentation.
     */
    public const SERVICE_DOCUMENTATION = 'service_documentation';

    /**
     * JSON array containing a list of the Subject Identifier types that this OP supports. Valid types include pairwise and public.
     *
     * @required
     */
    public const SUBJECT_TYPES_SUPPORTED = 'subject_types_supported';

    /** URL of the OP's OAuth 2.0 Token Endpoint [OpenID.Core]. This is REQUIRED unless only the Implicit Flow is used.
     */
    public const TOKEN_ENDPOINT = 'token_endpoint';

    /**
     * JSON array containing a list of Client Authentication methods supported by this Token Endpoint. The options are client_secret_post, client_secret_basic, client_secret_jwt, and private_key_jwt, as described in Section 9 of OpenID Connect Core 1.0 [OpenID.Core]. Other authentication methods MAY be defined by extensions. If omitted, the default is client_secret_basic -- the HTTP Basic Authentication Scheme specified in Section 2.3.1 of OAuth 2.0 [RFC6749].
     */
    public const TOKEN_ENDPOINT_AUTH_METHODS_SUPPORTED = 'token_endpoint_auth_methods_supported';

    /**
     * JSON array containing a list of the JWS signing algorithms (alg values) supported by the Token Endpoint for the signature on the JWT [JWT] used to authenticate the Client at the Token Endpoint for the private_key_jwt and client_secret_jwt authentication methods. Servers SHOULD support RS256. The value none MUST NOT be used.
     */
    public const TOKEN_ENDPOINT_AUTH_SIGNING_ALG_VALUES_SUPPORTED = 'token_endpoint_auth_signing_alg_values_supported';

    /**
     * Languages and scripts supported for the user interface, represented as a JSON array of BCP47 [RFC5646] language tag values.
     */
    public const UI_LOCALES_SUPPORTED = 'ui_locales_supported';

    /**
     * JSON array containing a list of the JWE [JWE] encryption algorithms (alg values) [JWA] supported by the UserInfo Endpoint to encode the Claims in a JWT [JWT].
     */
    public const USERINFO_ENCRYPTION_ALG_VALUES_SUPPORTED = 'userinfo_encryption_alg_values_supported';

    /**
     * JSON array containing a list of the JWE encryption algorithms (enc values) [JWA] supported by the UserInfo Endpoint to encode the Claims in a JWT [JWT].
     */
    public const USERINFO_ENCRYPTION_ENC_VALUES_SUPPORTED = 'userinfo_encryption_enc_values_supported';

    /**
     * URL of the OP's UserInfo Endpoint [OpenID.Core]. This URL MUST use the https scheme and MAY contain port, path, and query parameter components.
     *
     * @recommended
     */
    public const USERINFO_ENDPOINT = 'userinfo_endpoint';

    /**
     * JSON array containing a list of the JWS [JWS] signing algorithms (alg values) [JWA] supported by the UserInfo Endpoint to encode the Claims in a JWT [JWT]. The value none MAY be included.
     */
    public const USERINFO_SIGNING_ALG_VALUES_SUPPORTED = 'userinfo_signing_alg_values_supported';
	
	/* FIXME: Solid types here */
	public const TOKEN_TYPES_SUPPORTED = 'token_types_supported';
	public const CHECK_SESSION_IFRAME = 'check_session_iframe';
	public const END_SESSION_ENDPOINT = 'end_session_endpoint';
}
