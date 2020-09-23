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
		
		session_start();
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
			"registration_endpoint" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.register")),
	//		"sharing_endpoint" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.sharing"))
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

		$parser = new \Lcobucci\JWT\Parser();
		$token = $parser->parse($_GET['request']);
		$_SESSION['token'] = $token;
	
		$user = new \Pdsinterop\Solid\Auth\Entity\User();
		$user->setIdentifier('https://nextcloud.local/index.php/apps/solid/@' . $this->userId . '/turtle#me');

		$getVars = $_GET;
		if (!isset($getVars['grant_type'])) {
			$getVars['grant_type'] = 'implicit';
		}
		$getVars['response_type'] = 'token';
		$getVars['scope'] = "openid";
		
		if (!isset($getVars['redirect_uri'])) {
			$getVars['redirect_uri'] = 'https://solid.community/.well-known/solid/login';
		}
		$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $getVars, $_POST, $_COOKIE, $_FILES);
		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServer, $this->authServerConfig, $response);

		if (!$this->checkApproval()) {
			$result = new JSONResponse('Approval required');
			$result->setStatus(302);
			$result->addHeader("Location", $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.sharing")));
			return $result;
		}

		// FIXME: check if the user has approved - if not, show approval screen;
		$approval = \Pdsinterop\Solid\Auth\Enum\Authorization::APPROVED;
//		$approval = false;
		$response = $server->respondToAuthorizationRequest($request, $user, $approval);

		return $this->respond($response);
	}

	private function checkApproval() {
		return true;
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
		return new JSONResponse("ok");
	}
				
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function sharing() {
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
		
		$clientId = $this->getClientId();
		$data['registration_access_token'] = $this->generateRegistrationAccessToken($clientId);
		$data['client_id'] = $clientId;		
		$data['registration_client_uri'] = 'https://nextcloud.local/index.php/apps/solid/register/' . $clientId;
		$data['client_id_issued_at'] = time();
		
		return new JSONResponse($data);
		
		$data['client_id_issued_at'] = time();
		
		return new JSONResponse($data);
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
//		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServer, $this->authServerConfig, $response);
//		$response = $server->respondToJwksMetadataRequest();
//		return $this->respond($response);
		$publicKey = $this->getKeys()['publicKey'];
        $jwks = json_decode(json_encode(new \Pdsinterop\Solid\Auth\Utils\Jwks(
			new \Lcobucci\JWT\Signer\Key($publicKey)
		)), true);
		$jwks['keys'][0]['key_ops'] = array("verify");
		return new JSONResponse($jwks);
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

/*
$tokenStub = 'access_token=
eyJhbGciOiJSUzI1NiIsImtpZCI6ImNxS1pYT0JnSGtBIn0.eyJpc3MiOiJodHRwczovL3NvbGlkLmNvbW11bml0eSIsImF1ZCI6WyJlYjgwMTEwZGUyZjkyZGIwNjAwMGFjNjAxNjdiZTQ0NCJdLCJzdWIiOiJodHRwczovL3lsZWJyZS5zb2xpZC5jb21tdW5pdHkvcHJvZmlsZS9jYXJkI21lIiwiZXhwIjoxNjAxOTA2NTA0LCJpYXQiOjE2MDA2OTY5MDQsImp0aSI6Ijk4NThmNzZkYTJiN2EwMGUiLCJzY29wZSI6Im9wZW5pZCJ9.mBkFqOUWal8hluLKe2-xuaTRPJXtYd_a6LEvH3YfmfYBrUd_cCJgT48k4WMKfe0jZE72qPZUy0XjeXHrZZkPOP3j9Cyxoo7GQiImKmr_vftIDzvYeXP3yKy1T_M8gmsPrz-32y7CbrNTKPCa40i20sEyMnP2kSmmtI6GFF76HCgN29mZztFinqnb8mI7u2gvlh126lv25_lqaRGAPalgJ2uc_fb2rb1v8xnndano26EzPEh_PmtkvyB8JhEFkZKHwHpyIUgYQkCiONQZPnpkrPiTP38kG1SW1DMfbDuycE3ngWyZ-xxjHXGxjOUs594KXNaPIuRINXlOevKzzbmSkg
&
id_token=
eyJhbGciOiJSUzI1NiIsImtpZCI6ImpxeGVGdHhtSXNRIn0.eyJpc3MiOiJodHRwczovL3NvbGlkLmNvbW11bml0eSIsImF1ZCI6ImViODAxMTBkZTJmOTJkYjA2MDAwYWM2MDE2N2JlNDQ0IiwiYXpwIjoiZWI4MDExMGRlMmY5MmRiMDYwMDBhYzYwMTY3YmU0NDQiLCJzdWIiOiJodHRwczovL3lsZWJyZS5zb2xpZC5jb21tdW5pdHkvcHJvZmlsZS9jYXJkI21lIiwiZXhwIjoxNjAxOTA2NTA0LCJpYXQiOjE2MDA2OTY5MDQsImp0aSI6ImY1YzI2YjhkNDgxYTk4YzciLCJub25jZSI6ImZ6M2xaZkU3MUtnZGVYeFNlTXBQWlRvVXphb0w2bmVrclRQT1dqQzJWcU0iLCJhdF9oYXNoIjoiVXNNRTgwZlczT0k0aURwbUVVR0UwUSIsImNuZiI6eyJqd2siOnsiYWxnIjoiUlMyNTYiLCJlIjoiQVFBQiIsImV4dCI6dHJ1ZSwia2V5X29wcyI6WyJ2ZXJpZnkiXSwia3R5IjoiUlNBIiwibiI6Ijc1R3FHVEZvaFY5Y2tzbkplZ3Y2SncwR05SOERUV2xIRnRxa0llSkJvdnBUUlB0dzh2Q3FxTUtTRXFmaVJydUpkMlByNllaYzNSUl9jQ3pOdGZXeEZ1b0VXNFd1NzhvcEctemFZVFhHVVNRNUdNV1J6cktsdTFvTFp3aUhwMHIzZU4tSTdfaGlyZ1hHRWRYaWsyOXZBWjlOeVJVMkcydXRleW9CNjhCX2xQMHhSeFNQemctWjU5cnNRQTJrTFBLcFc4aDQ3TTZDZy1hdUVHYXJNa3hOVGYwVXdSSl9HanNubUYwM0pjTDZNRV9oVUpyS1A2NHdmUy1tdTRNSlo1M0pRRmlja2kzQTlUMlduMkZtQWZHQ1p2LVZHdVI5OW1Na1F5N0ZYZFlMblZNeWZlR0pjRDJRaVkzc0hZUWM4a0FObHY4SXNqRi04aEhzcXVZVlRQSkdvdyJ9fX0.NORg9O94ulaHZeiXW1Bs6ZyrUNQPrL3EM6DjcOZOcrLAYgWSpp0uwX0m18qiv2_pnjOihQ3QrQgb0YRYuqahxj-nshQBd-axuETvcW3iOo-eVFEkBUk6hGVcLr_1GUXgNkkNbDpyEvlqcTRNx7pXEhLb0A74PrUxj8OqF4wHtj-4EzAnCq5ffqSj5VVsCYppwnesz7EJyVjqlIgjH8zRmsVKdarhrIWL1IzwQjroSsIOSuXvlCoX8xBS2ndlFZXY12euknK2Epuo_tlxCyVQUbUxmwzV_JHWGGGBIi_3L3ok8gcfrrHpz6tB_tF2wmaMhcgUAulLzQVfRNOg4YY0vQ
&';
*/
		if ($body == null) {
			$body = 'ok';
		}
		$result = new JSONResponse($body);
		foreach ($headers as $header => $values) {
			foreach ($values as $value) {
				if (preg_match("/#access_token=(.*?)&/", $value, $matches)) {
					$idToken = $this->generateIdToken($matches[1]);
					$value = preg_replace("/#access_token=(.*?)&/", "#access_token=\$1&id_token=$idToken&", $value);
				}
//				$value = preg_replace("/#access_token=(.*?)&/", "#" . $tokenStub, $value);
				$result->addHeader($header, $value);
// echo $value;
			}
		}
		$result->setStatus($statusCode);
		return $result;
	}

	private function getClientId() {
			return "CoolApp";
	}
	private function getClient($clientId) {
		if (!$clientId) {
			$clientId = $this->getClientId(); // FIXME: only continue if a clientId is set;
		}
		
		if ($clientId) { // FIXME: and check that we know this client and get the client secret/client name for this client;
			$clientSecret = "super-secret-secret-squirrel";
			
			// FIXME: use the redirect URIs as indicated by the client;
			$clientRedirectUris = array(
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.server.token")),
				'https://solid.community/.well-known/solid/login',
				'http://localhost:3001/redirect'
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
	
	private function generateAccessTokenHash($accessToken) {
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
		$privateKey = $this->getKeys()['privateKey'];
		$publicKey = $this->getKeys()['publicKey'];
		$clientId = $this->getClientId();
		$subject = 'https://nextcloud.local/index.php/apps/solid/@' . $this->userId . '/turtle#me';

		// Create JWT
		$signer = new \Lcobucci\JWT\Signer\Rsa\Sha256();
		$keychain = new \Lcobucci\JWT\Signer\Keychain();
        $jwks = json_decode(json_encode(new \Pdsinterop\Solid\Auth\Utils\Jwks(
			new \Lcobucci\JWT\Signer\Key($publicKey)
		)), true);
		$jwks['keys'][0]['key_ops'] = array("verify");
		
		// var_dump($jwks);

		$atHash = $this->generateAccessTokenHash($accessToken);
		
		$requestToken = $_SESSION['token'];
		
		$builder = new \Lcobucci\JWT\Builder();
		$token = $builder
			->setIssuer("https://nextcloud.local")
            ->permittedFor($clientId)
			->setIssuedAt(time())
			->setNotBefore(time() - 1)
			->setExpiration(time() + 7*24*60*60)
			->set("azp", $clientId)
			->set("sub", $subject)
			->set("jti", "f5c26b8d481a98c7")
			->set("nonce", $requestToken->getClaim('nonce'))
			->set("at_hash", $atHash)
			->set("cnf", array(
				"jwk" => $jwks['keys'][0]
			))
			->withHeader('kid', $jwks['keys'][0]['kid'])
			->sign($signer, $keychain->getPrivateKey($privateKey))
			->getToken();
		$result = $token->__toString();
//		echo $result;
		return $result;
	}
	
	private function generateRegistrationAccessToken($clientId) {
		$privateKey = $this->getKeys()['privateKey'];
		// Create JWT
		$signer = new \Lcobucci\JWT\Signer\Rsa\Sha256();
		$keychain = new \Lcobucci\JWT\Signer\Keychain();		
		// var_dump($jwks);
		
		$builder = new \Lcobucci\JWT\Builder();
		$token = $builder
			->setIssuer("https://nextcloud.local")
            ->permittedFor($clientId)
			->set("sub", $clientId)
			->sign($signer, $keychain->getPrivateKey($privateKey))
			->getToken();
		$result = $token->__toString();
//		echo $result;
		return $token->__toString();
	}	
}
