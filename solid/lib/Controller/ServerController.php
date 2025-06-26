<?php
namespace OCA\Solid\Controller;

use OCA\Solid\DpopFactoryTrait;
use OCA\Solid\ServerConfig;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class ServerController extends Controller
{
	use DpopFactoryTrait;

	private $userId;

	/* @var IUserManager */
	private $userManager;

	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ServerConfig */
	private $config;

	/* @var ISession */
	private $session;

	/* @var Pdsinterop\Solid\Auth\Config */
	private $authServerConfig;

	/* @var Pdsinterop\Solid\Auth\Factory\AuthorizationServerFactory */
	private $authServerFactory;

	/* @var \Pdsinterop\Solid\Auth\TokenGenerator */
	private $tokenGenerator;

	public function __construct(
		$AppName,
		IRequest $request,
		ISession $session,
		IUserManager $userManager,
		IURLGenerator $urlGenerator,
		$userId,
		IConfig $config,
		\OCA\Solid\Service\UserService $UserService,
		IDBConnection $connection,
	) {
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = new \OCA\Solid\ServerConfig($config, $urlGenerator, $userManager);
		$this->userId = $userId;
		$this->userManager = $userManager;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;

		$this->setJtiStorage($connection);

		$this->authServerConfig = $this->createAuthServerConfig();
		$this->authServerFactory = (new \Pdsinterop\Solid\Auth\Factory\AuthorizationServerFactory($this->authServerConfig))->create();

		$this->tokenGenerator = new \Pdsinterop\Solid\Auth\TokenGenerator(
			$this->authServerConfig,
			$this->getDpopValidFor(),
			$this->getDpop()
		);
	}

	private function getOpenIdEndpoints() {
		return [
			'issuer' => $this->urlGenerator->getBaseURL(),
			'authorization_endpoint' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.authorize")),
			'jwks_uri' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.jwks")),
			"check_session_iframe" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.session")),
			"end_session_endpoint" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.logout")),
			"token_endpoint" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.token")),
			"userinfo_endpoint" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.userinfo")),
			"registration_endpoint" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.register"))
		];
	}

	private function getKeys() {
		$encryptionKey = $this->config->getEncryptionKey();
		$privateKey    = $this->config->getPrivateKey();
		$key           = openssl_pkey_get_private($privateKey);
		$publicKey     = openssl_pkey_get_details($key)['key'];
		return [
			"encryptionKey" => $encryptionKey,
			"privateKey"    => $privateKey,
			"publicKey"     => $publicKey
		];
	}

	private function createAuthServerConfig() {
		$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
		$clientId = $request->getParsedBody()['client_id'];
		$client = $this->getClient($clientId);
		$keys = $this->getKeys();
		try {
			return (new \Pdsinterop\Solid\Auth\Factory\ConfigFactory(
				$client,
				$keys['encryptionKey'],
				$keys['privateKey'],
				$keys['publicKey'],
				$this->getOpenIdEndpoints()
			))->create();
		} catch(\Throwable $e) {
			// var_dump($e);
			return null;
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function cors($path) {
		$origin = $_SERVER['HTTP_ORIGIN'];
		return (new DataResponse('OK'));
//		->addHeader('Access-Control-Allow-Origin', $origin)
//		->addHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
//		->addHeader('Access-Control-Allow-Methods', 'POST')
//		->addHeader('Access-Control-Allow-Credentials', 'true');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function authorize() {
		// Create a request
		if (!$this->userManager->userExists($this->userId)) {
			$result = new JSONResponse('Authorization required');
			$result->setStatus(401);
			return $result;
//			return $result->addHeader('Access-Control-Allow-Origin', '*');
		}

		if (isset($_GET['request'])) {
			$jwtConfig = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($this->config->getPrivateKey()));
			try {
				$token = $jwtConfig->parser()->parse($_GET['request']);
				$this->session->set("nonce", $token->claims()->get('nonce'));
			} catch(\Exception $e) {
				$this->session->set("nonce", $_GET['nonce']);
			}
		}

		$getVars = $_GET;
		if (!isset($getVars['grant_type'])) {
			$getVars['grant_type'] = 'implicit';
		}
		$getVars['response_type'] = $this->getResponseType();
		$getVars['scope'] = "openid" ;

		if (!isset($getVars['redirect_uri'])) {
			if (!isset($token)) {
				$result = new JSONResponse('Bad request, does not contain valid token');
				$result->setStatus(400);
				return $result;
//				return $result->addHeader('Access-Control-Allow-Origin', '*');
			}
			try {
				$getVars['redirect_uri'] = $token->claims()->get("redirect_uri");
			} catch(\Exception $e) {
				$result = new JSONResponse('Bad request, missing redirect uri');
				$result->setStatus(400);
				return $result;
//				return $result->addHeader('Access-Control-Allow-Origin', '*');
			}
		}

		if (preg_match("/^http(s)?:/", $getVars['client_id'])) {
			$parsedOrigin = parse_url($getVars['redirect_uri']);
			$origin = $parsedOrigin['scheme'] . '://' . $parsedOrigin['host'];
			if (isset($parsedOrigin['port'])) {
				$origin .= ":" . $parsedOrigin['port'];
			}
			$clientData = array(
				"client_id_issued_at" => time(),
				"client_name" => $getVars['client_id'],
				"origin" => $origin,
				"redirect_uris" => array(
					$getVars['redirect_uri']
				)
			);
			$clientId = $this->config->saveClientRegistration($origin, $clientData)['client_id'];
			$clientId = $this->config->saveClientRegistration($getVars['client_id'], $clientData)['client_id'];
			$returnUrl = $getVars['redirect_uri'];
		} else {
			$clientId = $getVars['client_id'];
			$returnUrl = $_SERVER['REQUEST_URI'];
		}

		$clientRegistration = $this->config->getClientRegistration($clientId);
		if (isset($clientRegistration['blocked']) && ($clientRegistration['blocked'] === true)) {
			$result = new JSONResponse('Unauthorized client');
			$result->setStatus(403);
			return $result;
		}

		$approval = $this->checkApproval($clientId);
		if (!$approval) {
			$result = new JSONResponse('Approval required');
			$result->setStatus(302);
			$approvalUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.approval", array("clientId" => $clientId, "returnUrl" => $returnUrl)));
			$result->addHeader("Location", $approvalUrl);
			return $result; // ->addHeader('Access-Control-Allow-Origin', '*');
		}

		$parsedOrigin = parse_url($clientRegistration['redirect_uris'][0]);
		if (
			$parsedOrigin['scheme'] != "https" &&
			$parsedOrigin['scheme'] != "http" &&
			!isset($_GET['customscheme'])
		) {
			$result = new JSONResponse('Custom schema');
			$result->setStatus(302);
			$originalRequest = parse_url($_SERVER['REQUEST_URI']);
			$customSchemeUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.customscheme")) . ($originalRequest['query'] ? "?" . $originalRequest['query'] . "&customscheme=" . $parsedOrigin['scheme'] : '');
			$result->addHeader("Location", $customSchemeUrl);
			return $result;
		}

		$user = new \Pdsinterop\Solid\Auth\Entity\User();
		$user->setIdentifier($this->getProfilePage());

		$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $getVars, $_POST, $_COOKIE, $_FILES);
		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServerFactory, $this->authServerConfig, $response);

		$response = $server->respondToAuthorizationRequest($request, $user, $approval);
		$response = $this->tokenGenerator->addIdTokenToResponse(
			$response,
			$clientId,
			$this->getProfilePage(),
			$this->session->get("nonce"),
			$this->config->getPrivateKey()
		);

		return $this->respond($response); // ->addHeader('Access-Control-Allow-Origin', '*');
	}

	private function checkApproval($clientId) {
		$allowedClients = $this->config->getAllowedClients($this->userId);
		if (in_array($clientId, $allowedClients)) {
			return \Pdsinterop\Solid\Auth\Enum\Authorization::APPROVED;
		} else {
			return \Pdsinterop\Solid\Auth\Enum\Authorization::DENIED;
		}
	}

	private function getProfilePage() {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleGet", array("userId" => $this->userId, "path" => "/card"))) . "#me";
	}

	private function getResponseType() {
		$responseTypes = explode(" ", $_GET['response_type']);
		foreach ($responseTypes as $responseType) {
			switch ($responseType) {
				case "token":
				return "token";
				break;
				case "code":
				return "code";
				break;
			}
		}
		return "token"; // default to token response type;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function session() {
		return new JSONResponse("ok");
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function token() {
		$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
		$grantType = $request->getParsedBody()['grant_type'];
		switch ($grantType) {
			case "authorization_code":
				$code = $request->getParsedBody()['code'];
				// FIXME: not sure if decoding this here is the way to go.
				// FIXME: because this is a public page, the nonce from the session is not available here.
				$codeInfo = $this->tokenGenerator->getCodeInfo($code);
				$userId = $codeInfo['user_id'];
			break;
			case "refresh_token":
				$refreshToken = $request->getParsedBody()['refresh_token'];
				$tokenInfo = $this->tokenGenerator->getCodeInfo($refreshToken); // FIXME: getCodeInfo should be named 'decrypt' or 'getInfo'?
				$userId = $tokenInfo['user_id'];
			break;
			default:
				$userId = false;
			break;
		}

		$clientId = $request->getParsedBody()['client_id'];

		$httpDpop = $request->getServerParams()['HTTP_DPOP'];

		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServerFactory, $this->authServerConfig, $response);
		$response = $server->respondToAccessTokenRequest($request);

		if ($userId) {
			$response = $this->tokenGenerator->addIdTokenToResponse(
				$response,
				$clientId,
				$userId,
				($_SESSION['nonce'] ?? ''),
				$this->config->getPrivateKey(),
				$httpDpop
			);
		}

		return $this->respond($response); // ->addHeader('Access-Control-Allow-Origin', '*');
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function userinfo() {
		return new JSONResponse("ok");
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function logout() {
		$this->userService->logout();
		return new JSONResponse("ok");
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function register() {
		$clientData = file_get_contents('php://input');
		$clientData = json_decode($clientData, true);
		if (!$clientData['redirect_uris']) {
			return new JSONResponse("Missing redirect URIs");
		}
		$clientData['client_id_issued_at'] = time();
		$parsedOrigin = parse_url($clientData['redirect_uris'][0]);
		$origin = $parsedOrigin['scheme'] . '://' . $parsedOrigin['host'];
		if (isset($parsedOrigin['port'])) {
			$origin .= ":" . $parsedOrigin['port'];
		}

		$clientData = $this->config->saveClientRegistration($origin, $clientData);
		$registration = array(
			'client_id' => $clientData['client_id'],
			/*
				 FIXME: returning client_secret will trigger calls with basic auth to us. To get this to work, we need this patch:
				// File /var/www/vhosts/solid-nextcloud/site/www/lib/base.php not changed so no update needed
				//	($request->getRawPathInfo() !== '/apps/oauth2/api/v1/token') &&
				//	($request->getRawPathInfo() !== '/apps/solid/token')
			*/
			'client_secret' => $clientData['client_secret'], // FIXME: Returning this means we need to patch Nextcloud to accept tokens on calls to

			'registration_client_uri' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.registeredClient", array("clientId" => $clientData['client_id']))),
			'client_id_issued_at' => $clientData['client_id_issued_at'],
			'redirect_uris' => $clientData['redirect_uris'],
		);
		$registration = $this->tokenGenerator->respondToRegistration($registration, $this->config->getPrivateKey());
		return (new JSONResponse($registration));
//		->addHeader('Access-Control-Allow-Origin', $origin)
//		->addHeader('Access-Control-Allow-Methods', 'POST');
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function registeredClient($clientId) {
		$clientRegistration = $this->config->getClientRegistration($clientId);
		unset($clientRegistration['client_secret']);
		return new JSONResponse($clientRegistration);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function jwks() {
		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServerFactory, $this->authServerConfig, $response);
		$response = $server->respondToJwksMetadataRequest();
		return $this->respond($response);
	}

	private function respond($response) {
		$statusCode = $response->getStatusCode();
		$response->getBody()->rewind();
		$headers = $response->getHeaders();

		$body = json_decode($response->getBody()->getContents());
		if ($statusCode > 399) {
			// var_dump($body);
			$reason = $response->getReasonPhrase();
			$result = new JSONResponse($reason, $statusCode);
			return $result;
		}

		if ($body == null) {
			$body = 'ok';
		}
		$result = new JSONResponse($body);

		foreach ($headers as $header => $values) {
			foreach ($values as $value) {
				$result->addHeader($header, $value);
			}
		}
		$result->setStatus($statusCode);
//		$result->addHeader('Access-Control-Allow-Origin', '*');
		return $result;
	}

	private function getClient($clientId) {
		$clientRegistration = $this->config->getClientRegistration($clientId);

		if ($clientId && count($clientRegistration)) {
			return new \Pdsinterop\Solid\Auth\Config\Client(
				$clientId,
				$clientRegistration['client_secret'] ?? '',
				$clientRegistration['redirect_uris'],
				$clientRegistration['client_name']
			);
		} else {
			return new \Pdsinterop\Solid\Auth\Config\Client('','',array(),'');
		}
	}
}
