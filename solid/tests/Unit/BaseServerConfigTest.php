<?php

namespace OCA\Solid;

use OCA\Solid\AppInfo\Application;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use TypeError;

function random_bytes()
{
	return BaseServerConfigTest::MOCK_RANDOM_BYTES;
}

/**
 * @coversDefaultClass \OCA\Solid\BaseServerConfig
 * @uses \OCA\Solid\BaseServerConfig
 * @covers ::__construct
 */
class BaseServerConfigTest extends TestCase
{
	////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	public const MOCK_RANDOM_BYTES = 'mock random bytes';
	private const MOCK_REDIRECT_URI = 'mock redirect uri';
	private const MOCK_CLIENT_ID = 'mock-client-id';
	private const MOCK_ORIGIN = 'mock origin';

	/////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	/**
	 * @testdox BaseServerConfig should complain when called before given a Configuration
	 * @covers ::__construct
	 */
	public function testConstructorWithoutConfig()
	{
		$this->expectException(TypeError::class);
		$this->expectExceptionMessage('Too few arguments to function');

		new BaseServerConfig();
	}

	/**
	 * @testdox BaseServerConfig should be instantiated when given a valid Configuration
	 * @covers ::__construct
	 */
	public function testConstructorWithValidConfig()
	{
		$configMock = $this->createMock(IConfig::class);

		$baseServerConfig = new BaseServerConfig($configMock);

		$this->assertInstanceOf(BaseServerConfig::class, $baseServerConfig);
	}

	/**
	 * @testdox BaseServerConfig should return a boolean when asked whether UserSubDomains are Enabled
	 * @covers ::getUserSubDomainsEnabled
	 * @covers ::castToBool
	 * @dataProvider provideBooleans
	 */
	public function testGetUserSubDomainsEnabled($value, $expected)
	{
		$configMock = $this->createMock(IConfig::class);
		$configMock->method('getAppValue')->willReturn($value);

		$baseServerConfig = new BaseServerConfig($configMock);
		$actual = $baseServerConfig->getUserSubDomainsEnabled();

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox BaseServerConfig should get value from AppConfig when asked whether UserSubDomains are Enabled
	 * @covers ::getUserSubDomainsEnabled
	 */
	public function testGetUserSubDomainsEnabledFromAppConfig()
	{
		$configMock = $this->createMock(IConfig::class);
		$configMock->expects($this->atLeast(1))
			->method('getAppValue')
			->with(Application::APP_ID, 'userSubDomainsEnabled', false)
			->willReturn(true);

		$baseServerConfig = new BaseServerConfig($configMock);
		$actual = $baseServerConfig->getUserSubDomainsEnabled();

		$this->assertTrue($actual);
	}

	/**
	 * @testdox BaseServerConfig should set value in AppConfig when asked to set UserSubDomainsEnabled
	 * @covers ::setUserSubDomainsEnabled
	 * @covers ::castToBool
	 *
	 * @dataProvider provideBooleans
	 */
	public function testSetUserSubDomainsEnabled($value, $expected)
	{
		$configMock = $this->createMock(IConfig::class);
		$configMock->expects($this->atLeast(1))
			->method('setAppValue')
			->with(Application::APP_ID, 'userSubDomainsEnabled', $expected)
		;

		$baseServerConfig = new BaseServerConfig($configMock);
		$baseServerConfig->setUserSubDomainsEnabled($value);
	}

	/**
	 * @testdox BaseServerConfig should retrieve client ID AppValue when asked to GetClientRegistration for existing client
	 * @covers ::getClientRegistration
	 */
	public function testGetClientRegistrationForExistingClient()
	{
		$configMock = $this->createMock(IConfig::class);
		$baseServerConfig = new BaseServerConfig($configMock);

		$expected = ['mock' => 'client'];

		$configMock->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'client-' . self::MOCK_CLIENT_ID)
			->willReturn(json_encode($expected));

		$actual = $baseServerConfig->getClientRegistration(self::MOCK_CLIENT_ID);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox BaseServerConfig should return empty array when asked to GetClientRegistration for non-existing client
	 * @covers ::getClientRegistration
	 */
	public function testGetClientRegistrationForNonExistingClient()
	{
		$configMock = $this->createMock(IConfig::class);
		$baseServerConfig = new BaseServerConfig($configMock);

		$expected = [];

		$configMock->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'client-' . self::MOCK_CLIENT_ID)
			->willReturnArgument(2);

		$actual = $baseServerConfig->getClientRegistration(self::MOCK_CLIENT_ID);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox BaseServerConfig should complain when asked to save ClientRegistration without client data
	 * @covers ::saveClientRegistration
	 */
	public function testSaveClientRegistrationWithoutClientData()
	{
		$this->expectException(TypeError::class);
		$this->expectExceptionMessage('Too few arguments to function');

		$configMock = $this->createMock(IConfig::class);
		$baseServerConfig = new BaseServerConfig($configMock);

		$baseServerConfig->saveClientRegistration();
	}

	/**
	 * @testdox BaseServerConfig should save ClientRegistration when asked to save ClientRegistration for new client
	 * @covers ::saveClientRegistration
	 */
	public function testSaveClientRegistrationForNewClient()
	{
		$configMock = $this->createMock(IConfig::class);

		$configMock->expects($this->once())
			->method('getAppValue')
			->willReturnArgument(2);

		$configMock->expects($this->exactly(1))
			->method('setAppValue')
			->willReturnMap([
				// Using willReturnMap as withConsecutive is removed since PHPUnit 10
				[Application::APP_ID, 'client-' . self::MOCK_ORIGIN, json_encode('{}')]
			]);

		$baseServerConfig = new BaseServerConfig($configMock);

		$actual = $baseServerConfig->saveClientRegistration([]);

		$this->assertArrayHasKey('client_id', $actual);
		$this->assertArrayHasKey('client_secret', $actual);
		$this->assertArrayHasKey('client_name', $actual);
	}

	/**
	 * @testdox BaseServerConfig should remove ClientRegistration when asked to remove ClientRegistration
	 * @covers ::removeClientRegistration
	 */
	public function testRemoveClientRegistration()
	{
		$configMock = $this->createMock(IConfig::class);
		$baseServerConfig = new BaseServerConfig($configMock);

		$configMock->expects($this->once())
			->method('deleteAppValue')
			->with(Application::APP_ID, 'client-' . self::MOCK_CLIENT_ID);

		$baseServerConfig->removeClientRegistration(self::MOCK_CLIENT_ID);
	}

	/**
	 * @testdox BaseServerConfig should return decoded trusted apps when asked to GetTrustedApps
	 * @covers ::getTrustedApps
	 */
	public function testGetTrustedApps()
	{
		$configMock = $this->createMock(IConfig::class);
		$baseServerConfig = new BaseServerConfig($configMock);

		$expected = [self::MOCK_ORIGIN];

		$configMock->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'trustedApps', '[]')
			->willReturn(json_encode($expected));

		$actual = $baseServerConfig->getTrustedApps();

		$this->assertEquals($expected, $actual);
	}

	/////////////////////////////// DATAPROVIDERS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	public function provideBooleans()
	{
		return [
			// Only 'boolean', 'NULL', 'integer', 'string' are allowed
			// @TODO: Add test for type that trigger a TypeError:
			// - array
			// - callable
			// - float
			// - object
			// - resource
			// @TODO: Add test for values that trigger a TypeError
			// 'integer:-1' => ['value'=> -1],
			// 'integer:2' => ['value'=> 2],
			// 'string:-1' => ['value'=> '-1'],
			// 'string:2' => ['value'=> '2'],
			// 'string:foo' => ['value'=> 'foo'],
			// 'string:NULL' => ['value'=> 'NULL'],
			'boolean:false' => ['value'=> false, 'expected' => false],
			'boolean:true' => ['value'=> true, 'expected' => true],
			'integer:0' => ['value'=> 0, 'expected' => false],
			'integer:1' => ['value'=> 1, 'expected' => true],
			'NULL' => ['value'=> null, 'expected' => false],
			'string:0' => ['value'=> '0', 'expected' => false],
			'string:1' => ['value'=> '1', 'expected' => true],
			'string:empty' => ['value'=> '', 'expected' => false],
			'string:false' => ['value'=> 'false', 'expected' => false],
			'string:FALSE' => ['value'=> 'FALSE', 'expected' => false],
			'string:true' => ['value'=> 'true', 'expected' => true],
			'string:TRUE' => ['value'=> 'TRUE', 'expected' => true],
		];
	}
}
