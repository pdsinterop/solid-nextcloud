<?php

namespace OCA\Solid\Controller;

use Error;
use OCA\Solid\ServerConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 * @coversDefaultClass \OCA\Solid\Controller\GetStorageUrlTrait
 * @covers ::setConfig
 * @covers ::setUrlGenerator
 */
class GetStorageUrlTraitTest extends TestCase
{
	////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	const MOCK_URL = 'mock url';
	const MOCK_USER_ID = 'mock user id';

	private $trait;

	protected function setUp(): void
	{
		$this->trait = new class {
			use GetStorageUrlTrait;

			public function _getStorageUrl($userId)
			{
				$class = new ReflectionObject($this);
				$method = $class->getMethod('getStorageUrl');
				// Only needed for PHP 8.1 and lower
				$method->setAccessible(true);

				return $method->invokeArgs($this, [$userId]);
			}
		};
	}

	/////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	/**
	 * @testdox GetStorageUrlTrait should complain when called before given a UrlGenerator
	 * @covers ::getStorageUrl
	 */
	public function testGetStorageUrlWithoutUrlGenerator()
	{
		$this->expectException(Error::class);
		$this->expectExceptionMessage('urlGenerator must not be accessed before initialization');

		$this->trait->_getStorageUrl(self::MOCK_USER_ID);
	}

	/**
	 * @testdox GetStorageUrlTrait should complain when called before given a Configuration
	 * @covers ::getStorageUrl
	 */
	public function testGetStorageUrlWithoutConfig()
	{
		$mockUrlGenerator = $this->getMockUrlGenerator(self::MOCK_URL);

		$this->expectException(Error::class);
		$this->expectExceptionMessage('config must not be accessed before initialization');

		$this->trait->setUrlGenerator($mockUrlGenerator);

		$this->trait->_getStorageUrl(self::MOCK_USER_ID);
	}

	/**
	 * @testdox GetStorageUrlTrait should return a string when called with a UrlGenerator and Configuration
	 * @covers ::getStorageUrl
	 * @dataProvider provideSubDomainsDisabledUrls
	 */
	public function testGetStorageUrlWithUserSubDomainsDisabled($url, $userId, $expected)
	{
		$mockConfig = $this->getMockConfig();
		$mockUrlGenerator = $this->getMockUrlGenerator($url);

		$this->trait->setUrlGenerator($mockUrlGenerator);
		$this->trait->setConfig($mockConfig);

		$actual = $this->trait->_getStorageUrl($userId);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox GetStorageUrlTrait should return a string when called with a UrlGenerator and Configuration
	 * @covers ::getStorageUrl
	 * @covers ::build_url
	 *
	 * @dataProvider provideSubDomainsEnabledUrls
	 */
	public function testGetStorageUrlWithUserSubDomainsEnabled($url, $userId, $expected)
	{
		$mockUrlGenerator = $this->getMockUrlGenerator($url);
		$mockConfig = $this->getMockConfig(true);

		$this->trait->setUrlGenerator($mockUrlGenerator);
		$this->trait->setConfig($mockConfig);

		$actual = $this->trait->_getStorageUrl($userId);

		$this->assertEquals($expected, $actual);
	}

	////////////////////////////// MOCKS AND STUBS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	public function getMockConfig($enabled = false): MockObject|ServerConfig
	{
		$mockConfig = $this->getMockBuilder(ServerConfig::class)
			->disableOriginalConstructor()
			->getMock();

		$mockConfig->expects($this->any())
			->method('getUserSubDomainsEnabled')
			->willReturn($enabled);

		return $mockConfig;
	}

	public function getMockUrlGenerator($url): MockObject|IURLGenerator
	{
		$mockUrlGenerator = $this
			->getMockBuilder(IURLGenerator::class)
			->getMock();

		$mockUrlGenerator->expects($this->atLeast(1))
			->method('getAbsoluteURL')
			->willReturn($url);

		return $mockUrlGenerator;
	}

	/////////////////////////////// DATAPROVIDERS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	public function provideSubDomainsDisabledUrls()
	{
		return [
			['url' => 'example.com/foo', 'userId' => 'alice', 'expected' => 'example.com//'],
			['url' => 'https://example.com/foo', 'userId' => 'alice', 'expected' => 'https://example.com//'],
			['url' => 'http://example.com/foo', 'userId' => 'alice', 'expected' => 'http://example.com//'],
			['url' => 'https://bob.example.com/foo', 'userId' => 'alice', 'expected' => 'https://bob.example.com//'],
			['url' => 'http://bob.example.com/foo', 'userId' => 'alice', 'expected' => 'http://bob.example.com//'],
			['url' => 'https://bob.example.com/foo', 'userId' => 'bob', 'expected' => 'https://bob.example.com//'],
			['url' => 'http://bob.example.com/foo', 'userId' => 'bob', 'expected' => 'http://bob.example.com//'],
		];
	}

	public function provideSubDomainsEnabledUrls()
	{
		return [
			// @FIXME: "Undefined array key 'host'" caused by the use of `parse_url`
			// ['url' => 'example.com/foo', 'userId' => 'alice', 'expected' => 'example.com//'],

			['url' => 'https://example.com/foo', 'userId' => 'alice', 'expected' => 'https://alice.example.com//'],
			['url' => 'http://example.com/foo', 'userId' => 'alice', 'expected' => 'http://alice.example.com//'],
			['url' => 'https://bob.example.com/foo', 'userId' => 'alice', 'expected' => 'https://alice.bob.example.com//'],
			['url' => 'http://bob.example.com/foo', 'userId' => 'alice', 'expected' => 'http://alice.bob.example.com//'],
			['url' => 'https://bob.example.com/foo', 'userId' => 'bob', 'expected' => 'https://bob.example.com//'],
			['url' => 'http://bob.example.com/foo', 'userId' => 'bob', 'expected' => 'http://bob.example.com//'],
		];
	}
}
