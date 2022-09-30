<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Pdsinterop\Solid\Auth\Entity\User;
use Pdsinterop\Solid\Auth\Enum\OpenId\OpenIdConnectMetadata as OidcMeta;
use Pdsinterop\Solid\Auth\Utils\Jwks;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Server
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /** @var AuthorizationServer */
    private $authorizationServer;
    /** @var Config */
    private $config;
    /** @var Response */
    private $response;

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(
        AuthorizationServer $authorizationServer,
        Config $config,
        Response $response
    ) {
        $this->authorizationServer = $authorizationServer;
        $this->config = $config;
        $this->response = $response;
    }

    final public function respondToAccessTokenRequest(Request $request) : Response
    {
        $authorizationServer = $this->authorizationServer;
        $response = $this->response;

        try {
            return $authorizationServer->respondToAccessTokenRequest($request, $response);
        } catch (OAuthServerException $serverException) {
            return $this->createOauthServerExceptionResponse($response, $serverException);
        }
    }

    /**
     * @param Request $request
     *
     * @return Response
     *
     * @see https://openid.net/specs/openid-connect-registration-1_0.html
     */
    final public function respondToDynamicClientRegistrationRequest(Request $request): Response {
        return $this->response->withStatus(501);
    }

    final public function respondToAuthorizationRequest(
        Request $request,
        User $user = null,
        bool $authorizationApproved = null,
        callable $callback = null
    ) : Response {
        $serverConfig = $this->config->getServer();
        $authorizationServer = $this->authorizationServer;
        $response = $this->response;

        try {
            // Validate the HTTP request and return an AuthorizationRequest object.
            $authRequest = $authorizationServer->validateAuthorizationRequest($request);
        } catch (OAuthServerException $serverException) {
            return $this->createOauthServerExceptionResponse($response, $serverException);
        }

        if ($user instanceof UserEntityInterface) {
            // Once the user has logged in set the user on the AuthorizationRequest
            $authRequest->setUser($user);
        }

        if ($user === null && $authorizationApproved === null) {
            /*/ Step 1 Redirect the user to a login endpoint /*/
            $loginUrl = ''; //@FIXME: The LOGIN_URL is NOT part of any RFC Spec so it can not (yet) be retrieved using `$serverConfig->get(Foo::LOGIN_URL);`
            $response = $response->withHeader('Location', $loginUrl)->withStatus(302);
        } elseif ($user instanceof UserEntityInterface && $authorizationApproved === null) {
            /*/ Step 2: Redirect the user to an authorization form where the user can approve the scopes requested by the client./*/
            $authorizationPageUrl = $serverConfig->get(OidcMeta::AUTHORIZATION_ENDPOINT);
            $response = $response->withHeader('Location', $authorizationPageUrl)->withStatus(302);
        } elseif (is_bool($authorizationApproved)) {
            /*/ Step 3: Update approval status and redirect to Client `redirect_uri /*/

            // Once the user has approved or denied the client update the status
            $authRequest->setAuthorizationApproved($authorizationApproved);

            // Return the HTTP redirect response
            $response = $authorizationServer->completeAuthorizationRequest($authRequest, $response);
        } else {
            // @CHECKME: 404 or throw Exception?
            $response = $response->withStatus(404);
        }

        // Allow calling code to retrieve or validate data from $authRequest
        if (is_callable($callback)) {
            // @CHECKME: Should this give access to the League\OAuth2 object or do we need to inject a Pdsinterop\Solid intermediate?
            $callback($authRequest);
        }

        return $response;
    }

    final public function respondToJwksMetadataRequest(/*Jwks $jwks*/) : Response
    {
        $response = $this->response;
        $key = $this->config->getKeys()->getPublicKey();

        $jwks = new Jwks($key);

        return $this->createJsonResponse($response, $jwks);
    }

    final public function respondToOpenIdMetadataRequest() : Response
    {
        $response = $this->response;

        $serverConfig = $this->config->getServer();

        return $this->createJsonResponse($response, $serverConfig);
    }

    final public function respondTo_Request(): Response {}

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private function createOauthServerExceptionResponse(Response $response, OAuthServerException $serverException): Response
    {
        return $serverException->generateHttpResponse($response, false, JSON_PRETTY_PRINT);
    }

    private function createJsonResponse(Response $response, $json = null) : Response
    {
        if ($json !== null) {

            if ( ! is_string($json)) {
                $json = json_encode($json, JSON_PRETTY_PRINT);
            }

            $response->getBody()->write($json);
        }

        return $response->withHeader('content-type', 'application/json; charset=UTF-8');
    }
}
