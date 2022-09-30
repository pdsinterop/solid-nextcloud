<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Enum\OAuth2;

/**
 * OAuth Authorization Endpoint Response Types
 *
 * The OAuth 2.0 specification allows for registration of space-separated
 * response_type parameter values. If a Response Type contains one of more space
 * characters (%20), it is compared as a space-delimited list of values in which
 * the order of values does not matter.
 *
 * The Response Type request parameter response_type informs the Authorization
 * Server of the desired authorization processing flow, including what
 * parameters are returned from the endpoints used.
 *
 * Each Response Type value also defines a default Response Mode mechanism to be
 * used, if no Response Mode is specified using the request parameter.
 *
 * Specification Document(s): https://openid.net/specs/oauth-v2-multiple-response-types-1_0.html
 *
 * If omitted, the default is that the Client will use only the code Response Type
 *
 * @see https://www.iana.org/assignments/oauth-parameters/oauth-parameters.xhtml#endpoint
 */
class ResponseType
{
    public const CODE = Parameter::CODE;

    public const DEFAULT = self::CODE;

    /**
     * When supplied as the response_type parameter in an OAuth 2.0
     * Authorization Request, a successful response MUST include the parameter
     * id_token. The Authorization Server SHOULD NOT return an OAuth 2.0
     * Authorization Code, Access Token, or Access Token Type in a successful
     * response to the grant request. If a redirect_uri is supplied, the User
     * Agent SHOULD be redirected there after granting or denying access.
     * The request MAY include a state parameter, and if so, the Authorization
     * Server MUST echo its value as a response parameter when issuing either a
     * successful response or an error response. The default Response Mode for
     * this Response Type is the fragment encoding and the query encoding MUST
     * NOT be used. Both successful and error responses SHOULD be returned using
     * the supplied Response Mode, or if none is supplied, using the default
     * Response Mode.
     */
    public const ID_TOKEN = 'id_token';

    /**
     * When supplied as the response_type parameter in an OAuth 2.0
     * Authorization Request, the Authorization Server SHOULD NOT return an
     * OAuth 2.0 Authorization Code, Access Token, Access Token Type, or ID
     * Token in a successful response to the grant request. If a redirect_uri is
     * supplied, the User Agent SHOULD be redirected there after granting or
     * denying access. The request MAY include a state parameter, and if so, the
     * Authorization Server MUST echo its value as a response parameter when
     * issuing either a successful response or an error response. The default
     * Response Mode for this Response Type is the query encoding. Both
     * successful and error responses SHOULD be returned using the supplied
     * Response Mode, or if none is supplied, using the default Response Mode.
     */
    public const NONE = 'none';

    public const TOKEN = 'token';

    /*/ Multiple Valued Response Types /*/

    /**
     * When supplied as the value for the response_type parameter, a successful
     * response MUST include both an Authorization Code and an id_token. The
     * default Response Mode for this Response Type is the fragment encoding and
     * the query encoding MUST NOT be used. Both successful and error responses
     * SHOULD be returned using the supplied Response Mode, or if none is
     * supplied, using the default Response Mode.
     */
    public const CODE_ID_TOKEN = [
        self::CODE,
        self::ID_TOKEN,
    ];

    /**
     * When supplied as the value for the response_type parameter, a successful
     * response MUST include an Authorization Code, an id_token, an Access Token,
     * and an Access Token Type. The default Response Mode for this Response
     * Type is the fragment encoding and the query encoding MUST NOT be used.
     * Both successful and error responses SHOULD be returned using the supplied
     * Response Mode, or if none is supplied, using the default Response Mode.
     */
    public const CODE_ID_TOKEN_TOKEN = [
        self::CODE,
        self::ID_TOKEN,
        self::TOKEN,
    ];

    /**
     * When supplied as the value for the response_type parameter, a successful
     * response MUST include an Access Token, an Access Token Type, and an
     * Authorization Code. The default Response Mode for this Response Type is
     * the fragment encoding and the query encoding MUST NOT be used. Both
     * successful and error responses SHOULD be returned using the supplied
     * Response Mode, or if none is supplied, using the default Response Mode.
     */
    public const CODE_TOKEN = [
        self::CODE,
        self::TOKEN,
    ];

    /**
     * When supplied as the value for the response_type parameter, a successful
     * response MUST include an Access Token, an Access Token Type, and an
     * id_token. The default Response Mode for this Response Type is the fragment
     * encoding and the query encoding MUST NOT be used. Both successful and
     * error responses SHOULD be returned using the supplied Response Mode, or
     * if none is supplied, using the default Response Mode.
     */
    public const ID_TOKEN_TOKEN = [
        self::ID_TOKEN,
        self::TOKEN,
    ];
}
