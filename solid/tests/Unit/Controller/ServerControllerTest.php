<?php

namespace OCA\Solid\Controller\ServerController;

use OCA\Solid\AppInfo\Application;
use OCA\Solid\BaseServerConfig;
use OCA\Solid\Controller\ServerController;
use OCA\Solid\Service\UserService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \OCA\Solid\Controller\ServerController
 * @covers ::__construct
 *
 * @uses \OCA\Solid\Controller\ServerController
 */
class ServerControllerTest extends TestCase
{
	////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	private const MOCK_CLIENT_ID = 'mock-client-id';
	private static string $encryptionKey;
	private static string $privateKey;
	private static string $publicKey;


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
	public function testInstatiationWithoutRequiredParameter($index)
	{
		$parameters = [
			'mock appname',
			$this->createMock(IRequest::class),
			$this->createMock(ISession::class),
			$this->createMock(IUserManager::class),
			$this->createMock(IURLGenerator::class),
			'mock user id',
			$this->createMock(IConfig::class),
			$this->createMock(UserService::class),
			$this->createMock(IDBConnection::class),
		];

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
	 *
	 * @uses \League\OAuth2\Server\AuthorizationServer
	 * @uses \OCA\Solid\BaseServerConfig
	 * @uses \OCA\Solid\JtiReplayDetector
	 * @uses \OCA\Solid\ServerConfig
	 * @uses \Pdsinterop\Solid\Auth\Factory\AuthorizationServerFactory
	 * @uses \Pdsinterop\Solid\Auth\Factory\GrantTypeFactory
	 * @uses \Pdsinterop\Solid\Auth\Factory\RepositoryFactory
	 * @uses \Pdsinterop\Solid\Auth\TokenGenerator
	 */
	public function testInstatiation()
	{
		$configMock = $this->createMock(IConfig::class);

		$configMock->method('getAppValue')->willReturnMap([
			[Application::APP_ID, 'client-' . self::MOCK_CLIENT_ID, '{}', 'return' => '{}'],
			[Application::APP_ID, 'client-d6d7896757f61ac4c397d914053180ff', '{}', 'return' => '{}'],
			[Application::APP_ID, 'client-', '{}', 'return' => '{}'],
			[Application::APP_ID, 'profileData', '', 'return' => ''],
			[Application::APP_ID, 'encryptionKey', '', 'return' => 'mock encryption key'],
			[Application::APP_ID, 'privateKey', '', 'return' => self::$privateKey],
		]);

		$parameters = [
			'mock appname',
			$this->createMock(IRequest::class),
			$this->createMock(ISession::class),
			$this->createMock(IUserManager::class),
			$this->createMock(IURLGenerator::class),
			'mock user id',
			$configMock,
			$this->createMock(UserService::class),
			$this->createMock(IDBConnection::class),
		];

		$controller = new ServerController(...$parameters);

		$this->assertInstanceOf(ServerController::class, $controller);
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
