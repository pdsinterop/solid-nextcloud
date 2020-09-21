<?php
namespace OCA\Solid\Controller;

use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;

class ServerController extends Controller {
	private $userId;
	private $userManager;
	private $urlGenerator;

	private $keys;
	private $openIdConfiguration;
	private $authServerConfig;
	private $authServer;
	
	public function __construct($AppName, IRequest $request, IUserManager $userManager, IURLGenerator $urlGenerator, $userId){
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
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
		return array(
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
		);
	}

	private function getKeys() {
		// FIXME: read these from the solid config in nextcloud;
		$encryptionKey = 'P76gcBVeXsVzrHiYp4IIwore5rQz4cotdZ2j9GV5V04=';
		$privateKey = <<<EOF
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEAvqb0htUFZaZ+z5rn7cHWg0VzsSoVnusbtJvwWtHfD0T0s6Hb
OqzE5h2fgdGbB49HRtc21SNHx6jeEStGv03yyqYkLUKrJJSg+ksrL+pT3Nd0h25q
sx7YUoPPxnm6sbd3XTg5efCb2yyV2dOoAegUPjK46Ra6PqUvmICQWDsjnv0VJIx+
TdDWmKY2xElk0T6CVNMD08OZVTHPwJgpGdRZyCK/SSmrvmAZ6K3ocKySJdKgYriR
bVMdx9NsczRkYU9b7tUpPmLu3IvsLboTbfRN23Y70Gx3Z8fuI1FRn23sEuQSIRW+
NsAi7l+AEdJ7MdYn0xSY6YMNJ0/aGXi55gagQwIDAQABAoIBAQCz8CNNtnPXkqKR
EmTfk1kAoGYmyc+KI+AMQDlDnlzmrnA9sf+Vi0Zy4XaQMeId6m6dP7Yyx4+Rs6GT
lsK4/7qs5M20If4hEl40nQlvubvY7UjAIch2sh/9EQbjDjTUUpJH2y70FdEjtRrh
cdBZrE6evYSkCZ1STtlzF7QkcfyWqilTHEntrHRaM3N+B6F74Yi5g6VyGE9uqKEM
EuGDHVSXizdUjauTTVEa4o7pxTh+eTIdQsfRewer7iuxFPo2vBNOTU2O/obNUsVK
mgmGM4QDjurgXLL2XPr0dVVo3eiFvIdmtZgGVyLfL/vUXH7bwUIfkV6qWyRmdBiY
Dfsm8BJBAoGBAOGebDUVnP3NgFacWVYrtvBXcH2Q6X1W6JEAxctDDsnjchTdyG9E
zcsMVM/gFKXIDF5VeNoSt2pwCTBL6K0oPC31c01clActbHStaJWOOCuifzrvmu4n
X51TNGoKggbbSVx1UTifKte2t6SPRaZ26EqVrmO44fGkA3ip6TRYnSFzAoGBANhT
J47EieRWiNflq9XqDAZ1fZzo3AHB+b+pO4r8GZr3Dw0ShCAnQXv7Gb2JAJvE3UrC
Aq5r3yZMM7nI+n/OT06+UcJ3/vDGAPx9trNrpWkwmcWBmoBfp86vDRhT0kEIiKbO
wLYMmSNLHNkmQQdBX2ytnsRxRyCWtQmm09bzOJHxAoGBAKEB/nSPnP5elfS5FOPy
xFWWANgK/yWMTOGV7JFWpIocvz/22d/V+QqrHSdP4UxBi9oSIvF1I+FYXKZTtZNE
wFWH8SXHKHhKyTgmvBjmal1xVFyJu0WzYX+TbjcykoI0IZFSw4ilxdw1L67G88yM
1M7NLKtLuCpKgpOspZjOmCvTAoGAGji6KswYCt2SaNkmIx/jpUTInSR8xpnEtD7H
QOmeEPKxmFwON/eKMIUXcaoRsNAEIvOxb4MT4YiLHJIIC0XuxxS6xF/XP0hBBloW
s1jxC/cgLJixKa5uoNcHN1OxwMBQECgvo+GTDnwkWw4QA9kgwAOroxQ4EvMxrqHS
O9Pvn4ECgYA7xr/3Sz8n+BhgOdABW0m91P144rK9QDYiaClSxAha1KiFunmAy3pB
Uxdl4yTCTA9yKIH7X3bShDXnj+RmEZ+SkwzpPuKvAE8ZkZQuXv41anFrZYkR2PZy
oYiERqXgH5yS/mkDeXRFx1nWsVxjoLWfd/Vi7Lr43cuYFy4UjqXZdg==
-----END RSA PRIVATE KEY-----
EOF;

		$key = openssl_pkey_get_private($privateKey);
		$publicKey = openssl_pkey_get_details($key)['key'];
		
		return array(
			"encryptionKey" => $encryptionKey,
			"privateKey" => $privateKey,
			"publicKey" => $publicKey
		);
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
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServer, $this->authServerConfig, $response);
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

		$user = new \Pdsinterop\Solid\Auth\Entity\User();
		$user->setIdentifier('https://nextcloud.local/index.php/apps/solid/@' . $this->userId . '/turtle#me');

		$getVars = $_GET;
		if (!isset($getVars['grant_type'])) {
			$getVars['grant_type'] = 'implicit';
		}
		$getVars['response_type'] = 'token';
		$getVars['redirect_uri'] = 'https://solid.community/.well-known/solid/login';

		$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $getVars, $_POST, $_COOKIE, $_FILES);
		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServer, $this->authServerConfig, $response);

		// FIXME: check if the user has approved - if not, show approval screen;
		$approval = \Pdsinterop\Solid\Auth\Enum\Authorization::APPROVED;
		$response = $server->respondToAuthorizationRequest($request, $user, $approval);

		$response->getBody()->rewind();
		$body = json_decode($response->getBody()->getContents(), true);
	//	var_dump($body);
		$headers = $response->getHeaders();
	//	print_r($headers);

		return $this->respond($response);
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
		$response = $server->respondToJwksMetadataRequest();
		return $this->respond($response);
	}

	private function respond($response) {
//		var_dump($response);

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
		if (!$clientId) {
			$clientId = 'e477202ff4046470e329bfe091e030b1';
		}
		if ($clientId) { // FIXME: and check that we know this client and get the client secret/client name for this client;
			$clientSecret = "super-secret-secret-squirrel";
			$clientRedirectUris = array(
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.token")),
				'https://solid.community/.well-known/solid/login'
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
}
