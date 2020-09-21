<?php
namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
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
	
	/* @var array */
	private $openIdConfiguration;
	
	/* @var Pdsinterop\Solid\Auth\Config */
	private $authServerConfig;
	
	/* @var Pdsinterop\Solid\Auth\Server */
	private $authServer;
	
	public function __construct($AppName, IRequest $request, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, ServerConfig $config) {
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = $config;
		$this->userId = $userId;
		$this->userManager = $userManager;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;

		$this->keys = $this->getKeys();
		$this->openIdConfiguration = $this->getOpenIdConfiguration();
		
		$this->authServerConfig = $this->createConfig();

		$this->authServer = (new \Pdsinterop\Solid\Auth\Factory\AuthorizationServerFactory($this->authServerConfig))->create();
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
		try {
			$config = (new \Pdsinterop\Solid\Auth\Factory\ConfigFactory(
				'',
				'',
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
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServer, $this->authServerConfig, $response);
		$response = json_decode($server->respondToOpenIdMetadataRequest()->getBody()->getContents());
		return new JSONResponse($response);
	}
	
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function authorize() {
		// Create a request
		$url = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.authorize"));
		$url .= "?" . $_SERVER['QUERY_STRING'];

		$request = (new \Laminas\Diactoros\ServerRequest())
			->withUri(new \Laminas\Diactoros\Uri($url))
			->withMethod($_SERVER['REQUEST_METHOD']);

		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServer, $this->authServerConfig, $response);
		//$response = json_decode(
		$response = $server->respondToAuthorizationRequest($request);
		//->getBody()->getContents());

		return new JSONResponse($response);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function session() {
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
		return new JSONResponse("ok");
	}
				
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function register() {
		$data = json_decode('{"redirect_uris":["https://solid.community/.well-known/solid/login"],"client_id":"e477202ff4046470e329bfe091e030b1","response_types":["id_token token"],"grant_types":["implicit"],"application_type":"web","id_token_signed_response_alg":"RS256","token_endpoint_auth_method":"client_secret_basic","registration_access_token":"eyJhbGciOiJSUzI1NiJ9.eyJpc3MiOiJodHRwczovL3NvbGlkLmNvbW11bml0eSIsImF1ZCI6ImU0NzcyMDJmZjQwNDY0NzBlMzI5YmZlMDkxZTAzMGIxIiwic3ViIjoiZTQ3NzIwMmZmNDA0NjQ3MGUzMjliZmUwOTFlMDMwYjEifQ.IPxCVHnV7mmncngdIWjsr5WLEpNgAytj_m5XyuLut-EjZEwFhOWmdekxFE_xuHEdGgRbP-uqUEXZh1hBqQPHQAMmzIYUA8RT0mLWsZ9-RMXssrCkD8OvFyY6ZY5BJJ-fBViirDXjVlEWfeQQWHGA3LTMvRcyVFZ-4e6xwKfL094jnXaFKb0-mWKwZz8qHFD2_QDEDiQznIj-zLX-z6TZmCzvRns8MKDdncE-Xqu0Wooit4qihO4jGtwzctywl-qgSx31XCNLGuWkzzFS7B1MYjUgbw_uH-KV3ph-QDAHIljQsNPmY8P5JhFUCn8dn2odrj7R9k01-hQQ-pamzkzv8Q","registration_client_uri":"https://solid.community/register/e477202ff4046470e329bfe091e030b1","client_id_issued_at":1600201359}', true);
		
		$data['client_id_issued_at'] = time();
		
		return new JSONResponse($data);
	}
	

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function jwks() {
		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServer, $this->authServerConfig, $response);
		$response = json_decode($server->respondToJwksRequest()->getBody()->getContents());
		return new JSONResponse($response);
	}	
}
