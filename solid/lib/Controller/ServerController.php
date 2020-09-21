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

	private $keyPair;
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
		$response = json_decode($server->respondToWellKnownRequest()->getBody()->getContents());
		return new JSONResponse($response);

		/*
		return new JSONResponse(
			array(
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
			)
		);
		*/
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
		/*$response = json_decode('{"keys":[{"kid":"jqxeFtxmIsQ","kty":"RSA","alg":"RS256","key_ops":["verify"],"ext":true,"n":"1jNmgHx9eiMXNNeZeQ_1Zz4NQodPqNl-guAi3guz6bpEuVuUD8zyT4MRcIw1AV3fgXtiQaWsxbq8rsb7nolJeY1Nt_hax2cvHiwaTf1HLZk27ld8lwDb6suorfNEKFWj-frR-yezRf5wo4-r51FEpsn0m9YNrH0ITqBd6tBJbURkY_9s-M1TF1mWBde_rKYQibOgtJ36ld4bMdut7DWgNio9cCHYAlS0kAWgGkYmvW-3FWoPHk6RcZAQ0g4Bf-Wv7B72BuFZ8c3i5_vGzahOIpdsu1Sv7bn0d64uQkoD3bz2Eesr-gT9vF1QBZYDx9K-bTpx5QEUbk8eaAtvn6xb0w","e":"AQAB"},{"kid":"NMotwLxp3ds","kty":"RSA","alg":"RS384","key_ops":["verify"],"ext":true,"n":"4sO5dkyj_RVexbZMF_uD9YNKIHHn8vSZc4i2T95pAI5eo6K6Q2Q_EAon8Ax9OzQRs5UhG6gnXJui4ZZquGTuJ-ifoYsIl7ZEldXda4BUqQIL7-gCl80173hbvCr02GuPUkqnzMOyzMPKWXsIzmnbB3i_2gGY71OCvsUQNoqpemirxB1loSnND9xswMJezUDh7fLNRrkB_BcO3XA4BEUoM0nVPADHY27YSxe-rfdRiyaQVdWDMhkKyCKhFaeCBpLi2t0XLCCdrMHj8H8KFAyomqg9bGVwq0sgFEquud-8Ury1fSmJoqPMdq_RjNV_yDJvZOIjo3_g66IhosK4pkYSkQ","e":"AQAB"},{"kid":"NRH1iuyd9Uo","kty":"RSA","alg":"RS512","key_ops":["verify"],"ext":true,"n":"srsvj0TjcIBif2NtF-VfGXK561inknsp2FVRcdJvpd2LZUKxQVXVeIlKljF2dkA8CEw7QhAxGi63Ql-g-t9aWf_8ikHdkCcVW5N1x4AvZTSis_bGPJ77k0ike6bqTg5Lg3GjNXDQawHhxQaYh2QCwhDRTWnCDbAUPsqDUBZ-vIGl_TtgCc5nf6af_DaeJRpX2OyEgtdIvTHApQnI5Zzta7PCM7FrER04XpvoIbnVtmK_ZpZwB3qvcsX0rFgxBJZ3oY_wGXhu5LELE_M_skSsv8vFYuDSdMwCa56iQmwakVlEilfHRCZeQiqozUM5hME_ilhz-m-lgzSNi6E6CUhvkw","e":"AQAB"},{"kid":"cqKZXOBgHkA","kty":"RSA","alg":"RS256","key_ops":["verify"],"ext":true,"n":"qelQtQjTbUMzD4YOBpk1c4XexmksK5L73xwPaUYFUp0wYp7mBckQNjXDFY-jVTnWCwUh8XdFDeFt5RCdnuM5OUIjAb9XWdDPwmMfiFJ4MjGhbUGLKjn8OJyddf36zxWSkk9R7hwFaKSim6E9Kgl5XRkeFvPknrrNxiWwEKLVQGfBoDJBNT8YIP6iNaFqXxUJAQ99tQsInlJDUGsUyqlRyniwjR7dPR3r7h3bejI_54_KTHvhLfHrMXA0MbAtP2sy-A83F-sEBbme7QVKrWyjdKR7fNkoC2sK-y0mHZ69sFMTVR2_B5SPQ9x5L3uRKK4DBOQuoYQYVO4jAgDPQJcD6w","e":"AQAB"},{"kid":"mUN0oCl9BP4","kty":"RSA","alg":"RS384","key_ops":["verify"],"ext":true,"n":"5K2Vfrol1JcwvugGZLVhKKivnTRkv9C0_Llc3xY9PXi20AcT2Kd6ZV0Hy7QzZs5ErPqjlaG7H95driEUsDo7gQifgv-nhEVf669-Q3fuXsD1P1ggCMT6RfnWKK0jpY5jUnkK6gbUvQHm6ayTSRdC6-kwZJ_bVMe2K2LNwvecEXIcivPSPprugksRVDTUbRhhSrmdxhVUJ4CzTh5GxjxV3q1haPpQFxF1Dqu6GLuwCkSOjuZPTurdbzFFx5n6GXzzyJHh1xod5_sfEfobZKclfA2YbcMow2r9OxoPoIf7z_Thp7Ndni8cAKcxmyHygPIFrk2qFkcfJ2eY6PIW_DkTDQ","e":"AQAB"},{"kid":"vuocsTLoQV0","kty":"RSA","alg":"RS512","key_ops":["verify"],"ext":true,"n":"xSLhaoTnfEby0-OOlIG5d0YPGmM34DGmN5WUPlbcz61a9ryH_WqEUleYo1q69chzh4-JqwfxUPkFjULJmrkwyIEecFWlhNAAcqbWrh7QNaOJA6SxT01ThxSqwHdcilyx_SK7-ddgr35WkAY3RuOhfGisY6V9wM5QQNB14dSFeaIYgsupmRitZQ7NcbtD1ZzR7jsB_yyN4C0-LKm2uR2C06OdgVZGqoJy8fZAZa9QAEVQtEDTlLqNoHpum2R9DNtljnluZfx7fJ-lWR8SSYRearyRU8AQa4Ia0k4Uhxhxn6SqZK1AYdOoyC0F2UYPukG-jQ7avGaWq_oP8c-82_n3lw","e":"AQAB"},{"kid":"L1fIa--Q67M","kty":"RSA","alg":"RS256","key_ops":["verify"],"ext":true,"n":"3ow7BPsclMUm9LA9_vKjE7Y-jjlxEAjL9VzW29EsceoOVy7-GsIKEh9oIgxCYyx7EPNkZN6cosrAlY2i60ligPz9exdA2yOVyqjk-tefJEA1cYFsxLgmK9sOdkmqhi6MMRIVsPrcSphufTQjfgcen3lxgZp-QRHEQ4WdbKum5fCeTvke3lcdTptOtIUDQ1f0dx_1AJyt1Q_c9_1v_xJJA2SMwW6wECSOM5tyjgoX1WoK3GdSe9Ev8lKcaVuo_PCwOVIJSCoAOQ8Z4D7PqJeVzuLlpOb2hqk0f80Bpb4h_Nqd8mnI6f5DJ_9y4oZTXZsMy8YQLxwGrJ7jV0QeThxitQ","e":"AQAB"}]}', true);
		*/
		

/*
		$response = array(
			"keys" => array(
				array(
					'kty' => 'RSA',
					'e'   => 'AQAB',
					"kid" => "2020-09-15T18:35:00Z",
					"alg" => "RS256",
					"n"   => "sDtY361VhdBQVXLl7rfNW5QncO_Ey4Osp-ZJk7I-RWW5tYCYeVLg4sajwX9iWHvtXLfp6Pwtnk0OTKSnDoXqmRYyg8R6Pc-hjwuWWOR120wWwe6xVwsr6Q4iuu3bf3bG6mh7IVl_h3CiaxoFz0GPz6DvUdquGskNCZwqzGN352i_pgHB-7ZBFZGOkg6xVmDWl4jWkzWxSbhhgzVLutn07MffSkjSUox-SL8QP8lPbNFuuuhpr0q5SGaazs7_V4zIau_APtQ1MyZMm2VvWw9IaMupXfaXxw-JEX2GSr1UIWp11RXAhPv-q7arCXIHyurDa2SMapTXLAmJUoxVNpHW0w"
				)
			)
		);
*/
		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServer, $this->authServerConfig, $response);
		$response = json_decode($server->respondToJwksRequest()->getBody()->getContents());
		return new JSONResponse($response);
	}	
}