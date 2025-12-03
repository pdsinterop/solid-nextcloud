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

		new ServerController(...array_values($parameters));
	}

	/**
	 * @testdox ServerController should be instantiable with all required parameters
	 *
	 * @covers ::__construct
	 */
	public function testInstantiation()
	{
		$parameters = $this->createMockConstructorParameters();

		$controller = new ServerController(...array_values($parameters));

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

		$controller = new ServerController(...array_values($parameters));

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

		$parameters['MockUserManager']->method('userExists')->willReturn(true);

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
		$_GET['response_type'] = 'mock-response-type';

		$parameters = $this->createMockConstructorParameters();

		$parameters['MockUserManager']->method('userExists')->willReturn(true);

		$controller = new ServerController(...array_values($parameters));

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

		$parameters['MockConfig']->method('getUserValue')->willReturnArgument(3);

		$parameters['MockUserManager']->method('userExists')->willReturn(true);

		$controller = new ServerController(...array_values($parameters));

		$actual = $controller->authorize();
		$expected = new JSONResponse('Approval required', Http::STATUS_FOUND, ['Location' => '']);

		$this->assertEquals($expected, $actual);
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

		$parameters['MockConfig']->method('getUserValue')
			->with(self::MOCK_USER_ID, Application::APP_ID, 'allowedClients', '[]')
			->willReturn(json_encode([self::MOCK_CLIENT_ID]));

		$parameters['MockUserManager']->method('userExists')->willReturn(true);

		$controller = new ServerController(...array_values($parameters));

		$response = $controller->authorize();

		$expected = [
			'data' => vsprintf($controller::ERROR_UNREGISTERED_URI, [$_GET['redirect_uri']]),
			'headers' => [
				'Cache-Control' => 'no-cache, no-store, must-revalidate',
				'Content-Security-Policy' => "default-src 'none';base-uri 'none';manifest-src 'self';frame-ancestors 'none'",
				'Content-Type' => 'application/json; charset=utf-8',
				'Feature-Policy' => "autoplay 'none';camera 'none';fullscreen 'none';geolocation 'none';microphone 'none';payment 'none'",
				'X-Robots-Tag' => 'noindex, nofollow',
			],
			'status' => Http::STATUS_BAD_REQUEST,
		];

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

        $parameters['MockConfig']->method('getUserValue')
            ->with(self::MOCK_USER_ID, Application::APP_ID, 'allowedClients', '[]')
            ->willReturn(json_encode([self::MOCK_CLIENT_ID]));

        $parameters['MockUserManager']->method('userExists')->willReturn(true);

        $controller = new ServerController(...array_values($parameters));

        $response = $controller->authorize();

        $expected = [
            'data' => 'ok',
            'headers' => [
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Content-Security-Policy' => "default-src 'none';base-uri 'none';manifest-src 'self';frame-ancestors 'none'",
                'Content-Type' => 'application/json; charset=utf-8',
                'Feature-Policy' => "autoplay 'none';camera 'none';fullscreen 'none';geolocation 'none';microphone 'none';payment 'none'",
                'X-Robots-Tag' => 'noindex, nofollow',
            ],
            'status' => Http::STATUS_FOUND,
        ];

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

		$controller = new ServerController(...array_values($parameters));

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

		$parameters['MockURLGenerator']->method('getBaseUrl')
			->willReturn('https://mock.server');

		$controller = new ServerController(...array_values($parameters));

		self::$clientData = json_encode(['redirect_uris' => ['https://mock.client/redirect']]);

		$response = $controller->register();

		$actual = [
			'data' => $response->getData(),
			'headers' => $response->getHeaders(),
			'status' => $response->getStatus(),
		];

		// Not comparing time-sensitive data
		unset($actual['data']['client_id_issued_at'], $actual['headers']['X-Request-Id']);

		$this->assertEquals([
			'data' => [
				'application_type' => 'web',
				'client_id' => 'f4a2d00f7602948a97ff409d7a581ec2',
				'grant_types' => ['implicit'],
				'id_token_signed_response_alg' => 'RS256',
				'redirect_uris' => ['https://mock.client/redirect'],
				'registration_access_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpc3MiOiJodHRwczovL21vY2suc2VydmVyIiwiYXVkIjoiZjRhMmQwMGY3NjAyOTQ4YTk3ZmY0MDlkN2E1ODFlYzIiLCJzdWIiOiJmNGEyZDAwZjc2MDI5NDhhOTdmZjQwOWQ3YTU4MWVjMiJ9.AfOi9YW70rL0EKn4_dvhkyu02iI4yGYV-Xh8hQ9RbHBUnvcXROFfQzn-OL-R3kV3nn8tknmpG-r_8Ouoo7O_Sjo8Hx1QSFfeqjJGOgB8HbXV7WN2spOMicSB-68EyftqfTGH0ksyPyJaNSTbkdIqtawsDaSKUVqTmziEo4IrE5anwDLZrtSUcS0A4KVrOAkJmgYGiC4MC0NMYXeBRxgkr1_h7GN4hekAXs9-5XwRH1mwswUVRL-6prx0IYpPNURFNqkS2NU83xNf-vONThOdLVkADVy-l3PCHT3E1sRdkklCHLjhWiZo7NcMlB0WdS-APnZYCi5hLEr5-jwNI2sxoA',
				'registration_client_uri' => '',
				'response_types' => ['id_token token'],
				'token_endpoint_auth_method' => 'client_secret_basic',
				'client_secret' => '3b5798fddd49e23662ee6fe801085100'
			],
			'headers' => [
				'Cache-Control' => 'no-cache, no-store, must-revalidate',
				'Content-Security-Policy' => "default-src 'none';base-uri 'none';manifest-src 'self';frame-ancestors 'none'",
				'Feature-Policy' => "autoplay 'none';camera 'none';fullscreen 'none';geolocation 'none';microphone 'none';payment 'none'",
				'X-Robots-Tag' => 'noindex, nofollow',
				'Content-Type' => 'application/json; charset=utf-8',
			],
			'status' => Http::STATUS_OK,
		], $actual);
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

		$controller = new ServerController(...array_values($parameters));

		$reflectionObject = new \ReflectionObject($controller);
		$reflectionProperty = $reflectionObject->getProperty('tokenGenerator');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($controller, $mockTokenGenerator);

		$tokenResponse = $controller->token();

		$expected = [
			'data' => "I'm a teapot",
			'headers' => [
				'Cache-Control' => 'no-cache, no-store, must-revalidate',
				'Content-Security-Policy' => "default-src 'none';base-uri 'none';manifest-src 'self';frame-ancestors 'none'",
				'Feature-Policy' => "autoplay 'none';camera 'none';fullscreen 'none';geolocation 'none';microphone 'none';payment 'none'",
				'X-Robots-Tag' => 'noindex, nofollow',
				'Content-Type' => 'application/json; charset=utf-8',
			],
			'status' => Http::STATUS_IM_A_TEAPOT,
		];

		$actual = [
			'data' => $tokenResponse->getData(),
			'headers' => $tokenResponse->getHeaders(),
			'status' => $tokenResponse->getStatus(),
		];
		unset($actual['headers']['X-Request-Id']);


		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox ServerController should return an OK when asked to logout
	 *
	 * @covers ::logout
	 */
	public function testLogout() {
		$parameters = $this->createMockConstructorParameters();

		$controller = new ServerController(...array_values($parameters));

		$actual = $controller->logout();
		$expected = new JSONResponse('ok', Http::STATUS_OK);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox ServerController should complain when asked to logout and the logout fails
	 *
	 * @covers ::logout
	 */
	public function testLogoutError() {
		$parameters = $this->createMockConstructorParameters();

		$mockError = 'Mock logout error';
		$expectedException = new \Exception($mockError);
		$parameters['MockUserService']
			->method('logout')
			->willThrowException($expectedException)
		;

		$controller = new ServerController(...array_values($parameters));

		$this->expectException($expectedException::class);
		$this->expectExceptionMessage($mockError);

		$controller->logout();
	}

	////////////////////////////// MOCKS AND STUBS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	public function createMockConfig($clientData): IConfig|MockObject
	{
		$mockConfig = $this->createMock(IConfig::class);

		$mockConfig->method('getAppValue')->willReturnMap([
			[Application::APP_ID, 'client-' . self::MOCK_CLIENT_ID, '{}', 'return' => $clientData],
			[Application::APP_ID, 'client-d6d7896757f61ac4c397d914053180ff', '{}', 'return' => $clientData],
			[Application::APP_ID, 'client-', '{}', 'return' => $clientData],
			[Application::APP_ID, 'profileData', '', 'return' => ''],
			[Application::APP_ID, 'encryptionKey', '', 'return' => 'mock encryption key'],
			[Application::APP_ID, 'privateKey', '', 'return' => self::$privateKey],
			// Client ID from register() with https://mock.client
			[Application::APP_ID, 'client-f4a2d00f7602948a97ff409d7a581ec2', '{}', 'return' => $clientData],
		]);

		return $mockConfig;
	}

	public function createMockConstructorParameters($clientData = '{}'): array
	{
		$parameters = [
			'mock appname',
			'MockRequest' => $this->createMock(IRequest::class),
			'MockSession' => $this->createMock(ISession::class),
			'MockUserManager' => $this->createMock(IUserManager::class),
			'MockURLGenerator' => $this->createMock(IURLGenerator::class),
			'MOCK_USER_ID' => self::MOCK_USER_ID,
			'MockConfig' => $this->createMockConfig($clientData),
			'MockUserService' => $this->createMock(UserService::class),
			'MockDBConnection' => $this->createMock(IDBConnection::class),
		];

		return $parameters;
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
