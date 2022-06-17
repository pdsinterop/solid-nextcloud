<?php
namespace OCA\Solid\WellKnown;

use OCA\Solid\ServerConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\ISession;
use OCP\IConfig;

use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\WellKnown\GenericResponse;
use OCP\Http\WellKnown\IHandler;
use OCP\Http\WellKnown\IRequestContext;
use OCP\Http\WellKnown\IResponse;

class OpenIdConfigurationHandler implements IHandler {
	/* @var IUserManager */
	private $userManager;

	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ServerConfig */
	private $config;

	/* @var Pdsinterop\Solid\Auth\Config */
	private $authServerConfig;

	/* @var Pdsinterop\Solid\Auth\Factory\AuthorizationServerFactory */
	private $authServerFactory;
	
	public function __construct(IRequest $request, IURLGenerator $urlGenerator, IConfig $config, IUserManager $userManager) 
	{
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = new \OCA\Solid\ServerConfig($config, $urlGenerator, $userManager);
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;

		$this->authServerConfig = $this->createAuthServerConfig(); 
		$this->authServerFactory = (new \Pdsinterop\Solid\Auth\Factory\AuthorizationServerFactory($this->authServerConfig))->create();		
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
		$keys = $this->getKeys();
		try {
			return (new \Pdsinterop\Solid\Auth\Factory\ConfigFactory(
				new \Pdsinterop\Solid\Auth\Config\Client('','',array(),''),
				$keys['encryptionKey'],
				$keys['privateKey'],
				$keys['publicKey'],
				$this->getOpenIdEndpoints()
			))->create();
		} catch(\Throwable $e) {
			return null;
		}
	}

        public function handle(string $service, IRequestContext $context, ?IResponse $previousResponse): ?IResponse {
                if ($service !== 'openid-configuration') {
                        return $previousResponse;
                }

		$response = new \Laminas\Diactoros\Response();
		$server	= new \Pdsinterop\Solid\Auth\Server($this->authServerFactory, $this->authServerConfig, $response);
		$response = $server->respondToOpenIdMetadataRequest();

		return new GenericResponse($this->respond($response));
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
		return $result;
	}
}
