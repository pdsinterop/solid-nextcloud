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

	/* @var array */
	private $keys;

	/* @var array */
	private $openIdConfiguration;
	
	/* @var Pdsinterop\Solid\Auth\Config */
	private $authServerConfig;

	/* @var Pdsinterop\Solid\Auth\Factory\AuthorizationServerFactory */
	private $authServerFactory;
	
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

		$this->keys = $this->getKeys();
		$this->openIdConfiguration = $this->getOpenIdConfiguration();
		
		$this->authServerConfig = $this->createConfig();
		$this->authServerFactory = (new \Pdsinterop\Solid\Auth\Factory\AuthorizationServerFactory($this->authServerConfig))->create();
	}

	private function getOpenIdConfiguration() {
		return [
			'issuer' => $this->urlGenerator->getBaseURL(),
			'authorization_endpoint' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.authorize")),
			'jwks_uri' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.jwks")),
			"response_types_supported" => array("code","code token","code id_token","id_token code","id_token","id_token token","code id_token token","none"),
			"token_types_supported" => array("legacyPop","dpop"),
			"response_modes_supported" => array("query","fragment"),
			"grant_types_supported" => array("authorization_code","implicit","refresh_token","client_credentials"),
			"subject_types_supported" => ["public"],
			"id_token_signing_alg_values_supported" => ["RS256"],
			"token_endpoint_auth_methods_supported" => "client_secret_basic",
			"token_endpoint_auth_signing_alg_values_supported" => ["RS256"],
			"display_values_supported" => [],
			"claim_types_supported" => ["normal"],
			"claims_supported" => [],
			"claims_parameter_supported" => false,
			"request_parameter_supported" => true,
			"request_uri_parameter_supported" => false,
			"require_request_uri_registration" => false,
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
	
	private function createConfig() {
		// if (isset($_GET['client_id'])) {
			$clientId = $_GET['client_id'];
			$client = $this->getClient($clientId);
		// }
		try {
			$config = (new \Pdsinterop\Solid\Auth\Factory\ConfigFactory(
				$client,
				$this->keys['encryptionKey'],
				$this->keys['privateKey'],
				$this->keys['publicKey'],
				$this->openIdConfiguration
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
		header("Access-Control-Allow-Headers: *");
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

		$user = new \Pdsinterop\Solid\Auth\Entity\User();
		$user->setIdentifier($this->getProfilePage());

		$getVars = $_GET;
		if (!isset($getVars['grant_type'])) {
			$getVars['grant_type'] = 'implicit';
		}
		$getVars['response_type'] = $this->getResponseType();
		$getVars['scope'] = "openid";
		
		if (!isset($getVars['redirect_uri'])) {
			$getVars['redirect_uri'] = 'https://solid.community/.well-known/solid/login'; // FIXME: a default could be in the registration, but if none is there we should probably just fail with a 400 bad request;
		}
		$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $getVars, $_POST, $_COOKIE, $_FILES);
		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServerFactory, $this->authServerConfig, $response);

		$clientId = $this->getClientId();

		$approval = $this->checkApproval($clientId);	
		if (!$approval) {
			$result = new JSONResponse('Approval required');
			$result->setStatus(302);
			$approvalUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.approval", array("clientId" => $clientId, "returnUrl" => $_SERVER['REQUEST_URI'])));
			$result->addHeader("Location", $approvalUrl);
			return $result;
		}

		$response = $server->respondToAuthorizationRequest($request, $user, $approval);
		return $this->respond($response);
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
		return new JSONResponse("ok");
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
		$clientId = $this->getClientId();
		
		$registration = array(
			'redirect_uris' => array('https://solid.community/.well-known/solid/login'), // FIXME: grab from registration request
			'response_types' => array("id_token token"),
			'grant_types' => array("implicit"),
			'application_type' => 'web',
			'id_token_signed_response_alg' => "RS256",
			'token_endpoint_auth_method' => 'client_secret_basic',
			'registration_access_token' => $this->generateRegistrationAccessToken($clientId),
			'client_id' => $clientId,
			'registration_client_uri' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.registeredClient", array("clientId" => $clientId))),
			'client_id_issued_at' => time() // FIXME: should the the time that this client registered, not the current time;
		);
		
		return new JSONResponse($registration);
	}
	
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function registeredClient($clientId) {
		return new JSONResponse("ok $clientId");
	}
	

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function jwks() {
//		$response = new \Laminas\Diactoros\Response();
//		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServerFactory, $this->authServerConfig, $response);
//		$response = $server->respondToJwksMetadataRequest();
//		return $this->respond($response);

		// FIXME: this should be the code in Solid\Auth\Server reponseToJwksMetadataRequest, which is currently missing the key_ops;
		$publicKey = $this->getKeys()['publicKey'];
        $jwks = json_decode(json_encode(new \Pdsinterop\Solid\Auth\Utils\Jwks(
			new \Lcobucci\JWT\Signer\Key($publicKey)
		)), true);
		$jwks['keys'][0]['key_ops'] = array("verify");
		return new JSONResponse($jwks);
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
				// FIXME: this should be done in a more specific piece of code just for the final authorize() result;
				if (preg_match("/#access_token=(.*?)&/", $value, $matches)) {
					$idToken = $this->generateIdToken($matches[1]);
					$value = preg_replace("/#access_token=(.*?)&/", "#access_token=\$1&id_token=$idToken&", $value);
				} else if (preg_match("/code=(.*?)&/", $value, $matches)) {
					$idToken = $this->generateIdToken($matches[1]);
					$value = preg_replace("/code=(.*?)&/", "code=\$1&id_token=$idToken&", $value);
				}
				$result->addHeader($header, $value);
			}
		}
		$result->setStatus($statusCode);
		return $result;
	}

	private function getClientId() {
			return "CoolApp"; // FIXME: this should be the generated clientId from the registration
	}
	private function getClient($clientId) {
		if (!$clientId) {
			$clientId = $this->getClientId(); // FIXME: only continue if a clientId is set;
		}
		
		if ($clientId) { // FIXME: and check that we know this client and get the client secret/client name for this client;
			$clientSecret = "super-secret-secret-squirrel"; // FIXME: should be generated on registration instead of hard-coded;
			
			// FIXME: use the redirect URIs as indicated by the client;
			$clientRedirectUris = array(
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.token")),
				'https://solid.community/.well-known/solid/login',
				'http://localhost:3001/redirect',
				'http://localhost:3002/redirect'
			);
			$clientName = "Nextcloud";

			return new \Pdsinterop\Solid\Auth\Config\Client(
				$clientId,
				$clientSecret,
				$clientRedirectUris,
				$clientName
			);
		} else {
			return new \Pdsinterop\Solid\Auth\Config\Client('','',array(),'');
		}
	}
	
	private function generateTokenHash($accessToken) {
		// FIXME: this function should be provided by Solid\Auth\Server
		// generate at_hash
		$atHash = hash('sha256', $accessToken);
		$atHash = substr($atHash, 0, 32);
		$atHash = hex2bin($atHash);
		$atHash = base64_encode($atHash);
		$atHash = rtrim($atHash, '=');
		$atHash = str_replace('/', '_', $atHash);
		$atHash = str_replace('+', '-', $atHash);

		return $atHash;
	}
	
	private function generateIdToken($accessToken) {
		// FIXME: this function should be provided by Solid\Auth\Server
		$privateKey = $this->getKeys()['privateKey'];
		$publicKey = $this->getKeys()['publicKey'];
		$clientId = $this->getClientId();
		$subject = $this->getProfilePage();

		// Create JWT
		$signer = new \Lcobucci\JWT\Signer\Rsa\Sha256();
		$keychain = new \Lcobucci\JWT\Signer\Keychain();
        $jwks = json_decode(json_encode(new \Pdsinterop\Solid\Auth\Utils\Jwks(
			new \Lcobucci\JWT\Signer\Key($publicKey)
		)), true);
		$jwks['keys'][0]['key_ops'] = array("verify");
		
		$tokenHash = $this->generateAccessTokenHash($accessToken);
		
		$builder = new \Lcobucci\JWT\Builder();
		$token = $builder
			->setIssuer($this->urlGenerator->getBaseUrl())
            ->permittedFor($clientId)
			->setIssuedAt(time())
			->setNotBefore(time() - 1)
			->setExpiration(time() + 14*24*60*60)
			->set("azp", $clientId)
			->set("sub", $subject)
			->set("jti", "f5c26b8d481a98c7") // FIXME: should be a generated token identifier
			->set("nonce", $this->session->get("nonce"))
			->set("at_hash", $tokenHash) //FIXME: at_hash should only be added if the response_type is a token
			->set("c_hash", $tokenHash) // FIXME: c_hash should only be added if the response_type is a code
			->set("cnf", array(
				"jwk" => $jwks['keys'][0]
			))
			->withHeader('kid', $jwks['keys'][0]['kid'])
			->sign($signer, $keychain->getPrivateKey($privateKey))
			->getToken();
		$result = $token->__toString();
		return $result;
	}
	
	private function generateRegistrationAccessToken($clientId) {
		// FIXME: this function should be provided by Solid\Auth\Server
		$privateKey = $this->getKeys()['privateKey'];
		
		// Create JWT
		$signer = new \Lcobucci\JWT\Signer\Rsa\Sha256();
		$keychain = new \Lcobucci\JWT\Signer\Keychain();				
		$builder = new \Lcobucci\JWT\Builder();
		$token = $builder
			->setIssuer($this->urlGenerator->getBaseUrl())
            ->permittedFor($clientId)
			->set("sub", $clientId)
			->sign($signer, $keychain->getPrivateKey($privateKey))
			->getToken();
		$result = $token->__toString();
		return $token->__toString();
	}	
}
