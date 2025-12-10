<?php

namespace OCA\Solid\Controller;

use Laminas\Diactoros\Response;
use OC\AppFramework\Http;
use OCA\Solid\AppInfo\Application;
use OCA\Solid\Service\UserService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

function file_get_contents($filename)
{
	if ($filename === 'php://input') {
		return ServerControllerTest::$clientData;
	}

	return \file_get_contents($filename);
}

/**
 * @coversDefaultClass \OCA\Solid\Controller\ServerController
 * @covers ::__construct
 *
 * @uses \OCA\Solid\Controller\ServerController
 * @uses \OCA\Solid\BaseServerConfig
 * @uses \OCA\Solid\JtiReplayDetector
 * @uses \OCA\Solid\ServerConfig
 */
class ServerControllerTest extends TestCase
{
	////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	private const MOCK_CLIENT_ID = 'mock-client-id';
	private const MOCK_USER_ID = 'mock user id';

	public static string $clientData = '';
	private static string $privateKey;

	private IConfig|MockObject $mockConfig;
	private IURLGenerator|MockObject $mockURLGenerator;
	private IUserManager|MockObject $mockUserManager;

	public static function setUpBeforeClass(): void
	{
		$keyPath = __DIR__ . '/../../fixtures/keys';
		self::$privateKey = file_get_contents($keyPath . '/private.key');
	}

	/////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	/**
	 * @testdox ServerController should complain when constructed without required parameter
	 *
	 * @covers ::__construct
	 *
	 * @dataProvider provideConstructorParameterIndex
	 */
	public function testInstantiationWithoutRequiredParameter($index)
	{
		$parameters = $this->createMockConstructorParameters();

		$parameters = array_slice($parameters, 0, $index);

		$this->expectException(\ArgumentCountError::class);
		$message = vsprintf(
			'Too few arguments to function %s::%s, %s passed in %s on line %s and exactly %s expected',
			[
				'class' => preg_quote('OCA\Solid\Controller\ServerController', '/'),
				'method' => preg_quote('__construct()', '/'),
				'index' => $index,
				'file' => '.*',
				'line' => '\d+',
				'count' => 9,

			]
		);

		$this->expectExceptionMessageMatches('/^' . $message . '$/');

		new ServerController(...$parameters);
	}

	/**
	 * @testdox ServerController should be instantiable with all required parameters
	 *
	 * @covers ::__construct
	 */
	public function testInstantiation()
	{
		$parameters = $this->createMockConstructorParameters();

		$controller = new ServerController(...$parameters);

		$this->assertInstanceOf(ServerController::class, $controller);
	}

	/**
	 * @testdox ServerController should return a 401 when asked to authorize without signed-in user
	 *
	 * @covers ::authorize
	 */
	public function testAuthorizeWithoutUser()
	{
		$parameters = $this->createMockConstructorParameters();

		$controller = new ServerController(...$parameters);

		$expected = new JSONResponse('Authorization required', Http::STATUS_UNAUTHORIZED);
		$actual = $controller->authorize();

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox ServerController should return a 400 when asked to authorize with a user but without client_id
	 *
	 * @covers ::authorize
	 */
	public function testAuthorizeWithoutClientId()
	{
		$parameters = $this->createMockConstructorParameters();

		$this->mockUserManager->method('userExists')->willReturn(true);

		$controller = new ServerController(...array_values($parameters));

		$actual = $controller->authorize();
		$expected = new JSONResponse('Bad request, missing client_id', Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox ServerController should return a 400 when asked to authorize with a user but without valid token
	 *
	 * @covers ::authorize
	 */
	public function testAuthorizeWithoutValidToken()
	{
		$_GET['client_id'] = self::MOCK_CLIENT_ID;
		$_GET['response_type'] = 'mock-response-type';

		$parameters = $this->createMockConstructorParameters();

		$this->mockUserManager->method('userExists')->willReturn(true);

		$controller = new ServerController(...$parameters);

		$actual = $controller->authorize();
		$expected = new JSONResponse('Bad request, does not contain valid token', Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox ServerController should return a 302 redirect when asked to authorize client that has not been approved
	 *
	 * @covers ::authorize
	 */
	public function testAuthorizeWithoutApprovedClient()
	{
		$_GET['client_id'] = self::MOCK_CLIENT_ID;
		$_GET['nonce'] = 'mock-nonce';
		// JWT with empty payload, HS256 encoded, created with `private.key` from fixtures
		$_GET['request'] = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.8VKCTiBegJPuPIZlp0wbV0Sbdn5BS6TE5DCx6oYNc5o';
		$_GET['response_type'] = 'mock-response-type';

		$_SERVER['REQUEST_URI'] = 'mock uri';

		$parameters = $this->createMockConstructorParameters();

		$this->mockConfig->method('getUserValue')->willReturnArgument(3);

		$this->mockUserManager->method('userExists')->willReturn(true);

		$controller = new ServerController(...$parameters);

		$actual = $controller->authorize();
		$expected = new JSONResponse('Approval required', Http::STATUS_FOUND, ['Location' => '']);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox
	 *
	 * @covers ::authorize
	 */
	public function testAuthorizeWithTrustedApp()
	{
		$_GET['client_id'] = self::MOCK_CLIENT_ID;
		$_GET['redirect_uri'] = 'https://mock.client/redirect';

		$origin = 'https://mock.client/';
		$clientData = json_encode([
			'client_name' => 'Mock Client',
			'origin' => $origin,
			'redirect_uris' => ['https://mock.client/redirect'],
		], JSON_THROW_ON_ERROR);
		$trustedApps = json_encode([$origin], JSON_THROW_ON_ERROR);

		$parameters = $this->createMockConstructorParameters($clientData, $trustedApps);

		$this->mockConfig->method('getUserValue')->willReturnArgument(3);

		$this->mockUserManager->method('userExists')->willReturn(true);

		$controller = new ServerController(...$parameters);

		$response = $controller->authorize();

		$expected = $this->createExpectedResponse();

		$actual = [
			'data' => $response->getData(),
			'headers' => $response->getHeaders(),
			'status' => $response->getStatus(),
		];

		$location = $actual['headers']['Location'] ?? '';

		// Not comparing time-sensitive data
		unset($actual['headers']['X-Request-Id'], $actual['headers']['Location']);

		$this->assertEquals($expected, $actual);

		// @TODO: Move $location assert to a separate test
		$url = parse_url($location);

		parse_str($url['fragment'], $url['fragment']);

		unset($url['fragment']['access_token'], $url['fragment']['id_token']);

		$this->assertEquals([
			'scheme' => 'https',
			'host' => 'mock.client',
			'path' => '/redirect',
			'fragment' => [
				'token_type' => 'Bearer',
				'expires_in' => '3600',
			],
		], $url);
	}

	/**
	 * @testdox ServerController should return a 400 when asked to authorize a client that sends an incorrect redirect URI
	 *
	 * @covers ::authorize
	 */
	public function testAuthorizeWithInvalidRedirectUri()
	{
		$_GET['client_id'] = self::MOCK_CLIENT_ID;
        $_GET['redirect_uri'] = 'https://some.other.client/redirect';

		$clientData = json_encode(['client_name' => 'Mock Client', 'redirect_uris' => ['https://mock.client/redirect']]);

		$parameters = $this->createMockConstructorParameters($clientData);

		$this->mockConfig->method('getUserValue')
			->with(self::MOCK_USER_ID, Application::APP_ID, 'allowedClients', '[]')
			->willReturn(json_encode([self::MOCK_CLIENT_ID]));

		$this->mockUserManager->method('userExists')->willReturn(true);

		$controller = new ServerController(...$parameters);

		$response = $controller->authorize();

		$data = vsprintf($controller::ERROR_UNREGISTERED_URI, [$_GET['redirect_uri']]);
		$expected = $this->createExpectedResponse(Http::STATUS_BAD_REQUEST, $data);

		$actual = [
			'data' => $response->getData(),
			'headers' => $response->getHeaders(),
			'status' => $response->getStatus(),
		];

		// Not comparing time-sensitive data
		unset($actual['headers']['X-Request-Id']);

		$this->assertEquals($expected, $actual);
	}

    /**
     * @testdox ServerController should return a 302 redirect when asked to authorize client that has been approved
     *
     * @covers ::authorize
     */
    public function testAuthorize()
    {
        $_GET['client_id'] = self::MOCK_CLIENT_ID;
        $_GET['nonce'] = 'mock-nonce';
        // JWT with empty payload, HS256 encoded, created with `private.key` from fixtures
        $_GET['request'] = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.8VKCTiBegJPuPIZlp0wbV0Sbdn5BS6TE5DCx6oYNc5o';
        $_GET['response_type'] = 'mock-response-type';
        $_GET['redirect_uri'] = 'https://mock.client/redirect';

        $_SERVER['REQUEST_URI'] = 'https://mock.server';
        $_SERVER['REQUEST_URI'];

        $clientData = json_encode(['client_name' => 'Mock Client', 'redirect_uris' => ['https://mock.client/redirect']]);

        $parameters = $this->createMockConstructorParameters($clientData);

        $this->mockConfig->method('getUserValue')
            ->with(self::MOCK_USER_ID, Application::APP_ID, 'allowedClients', '[]')
            ->willReturn(json_encode([self::MOCK_CLIENT_ID]));

        $this->mockUserManager->method('userExists')->willReturn(true);

        $controller = new ServerController(...$parameters);

        $response = $controller->authorize();

        $expected = $this->createExpectedResponse();

        $actual = [
            'data' => $response->getData(),
            'headers' => $response->getHeaders(),
            'status' => $response->getStatus(),
        ];

        $location = $actual['headers']['Location'] ?? '';

        // Not comparing time-sensitive data
        unset($actual['headers']['X-Request-Id'], $actual['headers']['Location']);

        $this->assertEquals($expected, $actual);

        // @TODO: Move $location assert to a separate test
        $url = parse_url($location);

        parse_str($url['fragment'], $url['fragment']);

        unset($url['fragment']['access_token'], $url['fragment']['id_token']);

        $this->assertEquals([
            'scheme' => 'https',
            'host' => 'mock.client',
            'path' => '/redirect',
            'fragment' => [
                'token_type' => 'Bearer',
                'expires_in' => '3600',
            ],
        ], $url);
    }

    /**
	 * @testdox ServerController should return a 400 when asked to register without valid client data
	 *
	 * @covers ::register
	 */
	public function testRegisterWithoutClientData()
	{
		$parameters = $this->createMockConstructorParameters();

		$controller = new ServerController(...array_values($parameters));

		$actual = $controller->register();

		$this->assertEquals(
			new JSONResponse('Missing client data', Http::STATUS_BAD_REQUEST),
			$actual
		);
	}

	/**
	 * @testdox ServerController should return a 400 when asked to register without redirect URIs
	 *
	 * @covers ::register
	 */
	public function testRegisterWithoutRedirectUris()
	{
		$parameters = $this->createMockConstructorParameters();

		$controller = new ServerController(...$parameters);

		self::$clientData = json_encode([]);

		$actual = $controller->register();

		$this->assertEquals(
			new JSONResponse('Missing redirect URIs', Http::STATUS_BAD_REQUEST),
			$actual
		);
	}

	/**
	 * @testdox ServerController should return a 200 with client data when asked to register with valid redirect URIs
	 *
	 * @covers ::register
	 */
	public function testRegisterWithRedirectUris()
	{
		$parameters = $this->createMockConstructorParameters();

		$this->mockURLGenerator->method('getBaseUrl')
			->willReturn('https://mock.server');

		$controller = new ServerController(...$parameters);

		self::$clientData = json_encode(['redirect_uris' => ['https://mock.client/redirect']]);

		$response = $controller->register();

		$data = [
			'application_type' => 'web',
			'grant_types' => ['implicit'],
			'id_token_signed_response_alg' => 'RS256',
			'redirect_uris' => ['https://mock.client/redirect'],
			'registration_client_uri' => '',
			'response_types' => ['id_token token'],
			'token_endpoint_auth_method' => 'client_secret_basic',
		];
		$expected = $this->createExpectedResponse(Http::STATUS_OK, $data);

		$actualData = $response->getData();
		$this->assertArrayHasKey('client_id', $actualData);
		$this->assertArrayHasKey('client_secret', $actualData);
		$this->assertArrayHasKey('registration_access_token', $actualData);

		unset(
			$actualData['client_id'],
			$actualData['client_secret'],
			$actualData['registration_access_token'],
		);

		$actual = [
			'data' => $actualData,
			'headers' => $response->getHeaders(),
			'status' => $response->getStatus(),
		];

		// Not comparing time-sensitive data
		unset($actual['data']['client_id_issued_at'], $actual['headers']['X-Request-Id']);

		$this->assertEquals($expected, $actual);
	}

	public function testTokenWithoutPostBody()
	{
		$parameters = $this->createMockConstructorParameters();

		$controller = new ServerController(...$parameters);

		$tokenResponse = $controller->token();

		$expected = $this->createExpectedResponse(Http::STATUS_BAD_REQUEST, 'Bad Request');

		$actual = [
			'data' => $tokenResponse->getData(),
			'headers' => $tokenResponse->getHeaders(),
			'status' => $tokenResponse->getStatus(),
		];
		unset($actual['headers']['X-Request-Id']);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox ServerController should consume Post, Server, and Session variables when generating a token
	 *
	 * @covers ::token
	 */
	public function testToken()
	{
		$_POST['client_id'] = self::MOCK_CLIENT_ID;
		$_POST['code'] = '';
		$_POST['grant_type'] = 'authorization_code';
		$_SERVER['HTTP_DPOP'] = 'mock dpop';
		$_SESSION['nonce'] = 'mock nonce';

		$parameters = $this->createMockConstructorParameters();

		// @FIXME: Use actual TokenGenerator when we know how to make a valid 'code' for the test
		$mockTokenGenerator = $this->createMock(\Pdsinterop\Solid\Auth\TokenGenerator::class);
		$mockTokenGenerator->method('getCodeInfo')->willReturn(['user_id' => self::MOCK_USER_ID]);
		$mockTokenGenerator->expects($this->once())
			->method('addIdTokenToResponse')
			->with(
				$this->isInstanceOf(Response::class),
				$_POST['client_id'],
				self::MOCK_USER_ID,
				$_SESSION['nonce'],
				self::$privateKey,
				$_SERVER['HTTP_DPOP'],
			)
			->willReturn(new Response('php://memory', Http::STATUS_IM_A_TEAPOT, [
				'Content-Type' => 'mock application type'
			]));

		$controller = new ServerController(...$parameters);

		$reflectionObject = new \ReflectionObject($controller);
		$reflectionProperty = $reflectionObject->getProperty('tokenGenerator');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($controller, $mockTokenGenerator);

		$tokenResponse = $controller->token();

		$expected = $this->createExpectedResponse(Http::STATUS_IM_A_TEAPOT, "I'm a teapot");

		$actual = [
			'data' => $tokenResponse->getData(),
			'headers' => $tokenResponse->getHeaders(),
			'status' => $tokenResponse->getStatus(),
		];
		unset($actual['headers']['X-Request-Id']);


		$this->assertEquals($expected, $actual);
	}

	////////////////////////////// MOCKS AND STUBS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	public function createMockConfig($clientData, $trustedApps): IConfig|MockObject
	{
		$this->mockConfig = $this->createMock(IConfig::class);

		$this->mockConfig->method('getAppValue')->willReturnMap([
			[Application::APP_ID, 'client-' . self::MOCK_CLIENT_ID, '{}', 'return' => $clientData],
			[Application::APP_ID, 'client-6d6f636b2072616e646f6d206279746573', '{}', 'return' => $clientData],
			[Application::APP_ID, 'client-', '{}', 'return' => $clientData],
			[Application::APP_ID, 'encryptionKey', '', 'return' => 'mock encryption key'],
			[Application::APP_ID, 'privateKey', '', 'return' => self::$privateKey],
			[Application::APP_ID, 'trustedApps', '[]', 'return' => $trustedApps],
		]);

		return $this->mockConfig;
	}

	public function createMockConstructorParameters($clientData = '{}', $trustedApps = '[]'): array
	{
		$parameters = [
			'mock appname',
			$this->createMock(IRequest::class),
			$this->createMock(ISession::class),
			$this->createMockUserManager(),
			$this->createMockUrlGenerator(),
			self::MOCK_USER_ID,
			$this->createMockConfig($clientData, $trustedApps),
			$this->createMock(UserService::class),
			$this->createMock(IDBConnection::class),
		];

		return $parameters;
	}

	public function createMockUrlGenerator(): IURLGenerator|MockObject
	{
		$this->mockURLGenerator = $this->createMock(IURLGenerator::class);

		return $this->mockURLGenerator;
	}

	public function createMockUserManager(): IUserManager|MockObject
	{
		$this->mockUserManager = $this->createMock(IUserManager::class);

		return $this->mockUserManager;
	}

	public function createExpectedResponse($status = Http::STATUS_FOUND, $data = 'ok', $headers = []): array
	{
		if (empty($headers)) {
			$headers = [
				'Cache-Control' => 'no-cache, no-store, must-revalidate',
				'Content-Security-Policy' => "default-src 'none';base-uri 'none';manifest-src 'self';frame-ancestors 'none'",
				'Content-Type' => 'application/json; charset=utf-8',
				'Feature-Policy' => "autoplay 'none';camera 'none';fullscreen 'none';geolocation 'none';microphone 'none';payment 'none'",
				'X-Robots-Tag' => 'noindex, nofollow',
			];
		}

		return [
			'data' => $data,
			'headers' => $headers,
			'status' => $status,
		];
	}

	/////////////////////////////// DATAPROVIDERS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	public static function provideConstructorParameterIndex()
	{
		return [
			'appName' => [0],
			'request' => [1],
			'session' => [2],
			'userManager' => [3],
			'urlGenerator' => [4],
			'userId' => [5],
			'config' => [6],
			'userService' => [7],
			'connection' => [8],
		];
	}
}
