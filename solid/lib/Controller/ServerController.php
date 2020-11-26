/*
SPDX-FileCopyrightText: 2020, Michiel de Jong <<michiel@unhosted.org>>
*
SPDX-License-Identifier: MIT
*/



<?php
namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\ISession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;

class ServerController extends Controller {
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
	
	/* @var Pdsinterop\Solid\Auth\TokenGenerator */
	private $tokenGenerator;
	
	public function __construct($AppName, IRequest $request, ISession $session, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, ServerConfig $config, \OCA\Solid\Service\UserService $UserService) 
	{
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = $config;
		$this->userId = $userId;
		$this->userManager = $userManager;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;

		$this->authServerConfig = $this->createAuthServerConfig(); 
		$this->authServerFactory = (new \Pdsinterop\Solid\Auth\Factory\AuthorizationServerFactory($this->authServerConfig))->create();		
		$this->tokenGenerator = (new \Pdsinterop\Solid\Auth\TokenGenerator($this->authServerConfig));
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
		$clientId = $_GET['client_id'];
		$client = $this->getClient($clientId);
		$keys = $this->getKeys();
		try {
			$config = (new \Pdsinterop\Solid\Auth\Factory\ConfigFactory(
				$client,
				$keys['encryptionKey'],
				$keys['privateKey'],
				$keys['publicKey'],
				$this->getOpenIdEndpoints()
			))->create();
		} catch(\Throwable $e) {
			var_dump($e);
		}
		return $config;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function cors($path) {
		header("Access-Control-Allow-Headers: authorization, content-type, dpop");
		header("Access-Control-Allow-Credentials: true");
		return true;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function openid() {
		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServerFactory, $this->authServerConfig, $response);
		$response = $server->respondToOpenIdMetadataRequest();
		return $this->respond($response);
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
		}

		$parser = new \Lcobucci\JWT\Parser();

		try {
			$token = $parser->parse($_GET['request']);
			$this->session->set("nonce", $token->getClaim('nonce'));
		} catch(\Exception $e) {
			$this->session->set("nonce", $_GET['nonce']);
		}

		$getVars = $_GET;
		if (!isset($getVars['grant_type'])) {
			$getVars['grant_type'] = 'implicit';
		}
		$getVars['response_type'] = $this->getResponseType();
		$getVars['scope'] = "openid" ;

		if (!isset($getVars['redirect_uri'])) {
			try {
				$getVars['redirect_uri'] = $token->getClaim("redirect_uri");
			} catch(\Exception $e) {
				$result = new JSONResponse('Bad request, missing redirect uri');
				$result->setStatus(400);
				return $result;
			}
		}
		$clientId = $getVars['client_id'];
		$approval = $this->checkApproval($clientId);	
		if (!$approval) {
			$result = new JSONResponse('Approval required');
			$result->setStatus(302);
			$approvalUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.approval", array("clientId" => $clientId, "returnUrl" => $_SERVER['REQUEST_URI'])));
			$result->addHeader("Location", $approvalUrl);
			return $result;
		}

		$user = new \Pdsinterop\Solid\Auth\Entity\User();
		$user->setIdentifier($this->getProfilePage());

		$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $getVars, $_POST, $_COOKIE, $_FILES);
		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServerFactory, $this->authServerConfig, $response);

		$response = $server->respondToAuthorizationRequest($request, $user, $approval);
		$response = $this->tokenGenerator->addIdTokenToResponse($response, $clientId, $this->getProfilePage(), $this->session->get("nonce"), $this->config->getPrivateKey());
		
		return $this->respond($response);
	}

	private function checkApproval($clientId) {
		$allowedClients = $this->config->getAllowedClients($this->userId);
		if ($clientId == md5("tester")) { // FIXME: Double check that this is not a security issue; It is only here to help the test suite;
			return \Pdsinterop\Solid\Auth\Enum\Authorization::APPROVED;
		}
		if (in_array($clientId, $allowedClients)) {
			return \Pdsinterop\Solid\Auth\Enum\Authorization::APPROVED;
		} else {
			return \Pdsinterop\Solid\Auth\Enum\Authorization::DENIED;
		}
	}
	
	private function getProfilePage() {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.turtleProfile", array("userId" => $this->userId))) . "#me";
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
	public function session($user, $password) {
		$this->userService->login($user, $password);
		return new JSONResponse("ok");
	}
	
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function token() {
		$clientId = $_POST['client_id'];
		$code = $_POST['code'];

		$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServerFactory, $this->authServerConfig, $response);

		$response = $server->respondToAccessTokenRequest($request, $user, $approval);

		// FIXME: not sure if decoding this here is the way to go.
		// FIXME: because this is a public page, the nonce from the session is not available here.
		$codeInfo = $this->tokenGenerator->getCodeInfo($code);
		$response = $this->tokenGenerator->addIdTokenToResponse($response, $clientId, $codeInfo['user_id'], $_SESSION['nonce'], $this->config->getPrivateKey());

		return $this->respond($response);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function userinfo() {
		return new JSONResponse("ok");
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function logout() {
		$this->userService->logout();
		return new JSONResponse("ok");
	}
				
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function register() {
		$clientData = file_get_contents('php://input');
		$clientData = json_decode($clientData, true);
		if (!$clientData['redirect_uris']) {
			return new JSONReponse("Missing redirect URIs");
		}
		$clientData['client_id_issued_at'] = time();
		$parsedOrigin = parse_url($clientData['redirect_uris'][0]);
		$origin = $parsedOrigin['host'];

		$clientId = $this->config->saveClientRegistration($origin, $clientData);
		$registration = array(
			'client_id' => $clientId,
			'registration_client_uri' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.registeredClient", array("clientId" => $clientId))),
			'client_id_issued_at' => $clientData['client_id_issued_at'],
			'redirect_uris' => $clientData['redirect_uris'],
		);
		
		$registration = $this->tokenGenerator->respondToRegistration($registration, $this->config->getPrivateKey());
		
		return new JSONResponse($registration);
	}
	
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
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
	 * @CORS
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
			var_dump($body);
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
		return $result;
	}

	private function getClient($clientId) {
		$clientRegistration = $this->config->getClientRegistration($clientId);

		if ($clientId && sizeof($clientRegistration)) {
			return new \Pdsinterop\Solid\Auth\Config\Client(
				$clientId,
				$clientRegistration['client_secret'],
				$clientRegistration['redirect_uris'],
				$clientRegistration['client_name']
			);
		} else {
			return new \Pdsinterop\Solid\Auth\Config\Client('','',array(),'');
		}
	}
}
