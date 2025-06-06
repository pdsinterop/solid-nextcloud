<?php

namespace OCA\Solid\Controller;

use Error;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Uri;
use OCA\Solid\ServerConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @coversDefaultClass \OCA\Solid\Controller\GetStorageUrlTrait
 * @covers ::setConfig
 * @covers ::setUrlGenerator
 */
class GetStorageUrlTraitTest extends TestCase
{
	////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	private const MOCK_URL = 'mock url';
	private const MOCK_USER_ID = 'mock user id';

	private $trait;

	protected function setUp(): void
	{
		$this->trait = new class {
			use GetStorageUrlTrait;
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

		$this->trait->getStorageUrl(self::MOCK_USER_ID);
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

		$this->trait->getStorageUrl(self::MOCK_USER_ID);
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

		$actual = $this->trait->getStorageUrl($userId);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox GetStorageUrlTrait should return a string when called with a UrlGenerator and Configuration
	 * @covers ::getStorageUrl
	 * @covers ::buildUrl
	 *
	 * @dataProvider provideSubDomainsEnabledUrls
	 */
	public function testGetStorageUrlWithUserSubDomainsEnabled($url, $userId, $expected)
	{
		$mockUrlGenerator = $this->getMockUrlGenerator($url);
		$mockConfig = $this->getMockConfig(true);

		$this->trait->setUrlGenerator($mockUrlGenerator);
		$this->trait->setConfig($mockConfig);

		$actual = $this->trait->getStorageUrl($userId);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @testdox GetStorageUrlTrait should return expected validity when asked to validateUrl
	 *
	 * @covers ::validateUrl
	 *
	 * @dataProvider provideRequests
	 */
	public function testValidateUrl(RequestInterface $response, $expected)
	{
		$actual = $this->trait->validateUrl($response);

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

	public function provideRequests()
	{
		$request = new Request();

		return [
			'invalid: invalid URL' => ['request' => $request->withUri(new Uri('!@#$%^&*()_')), 'expected' => false],
			'invalid: no domain user' => ['request' => $request->withUri(new Uri('https://example.com/~alice/profile/card#me')), 'expected' => false],
			'invalid: no path or domain user' => ['request' => $request->withUri(new Uri('https://example.com/')), 'expected' => false],
			'invalid: no path user' => ['request' => $request->withUri(new Uri('https://alice.example.com/profile/card#me')), 'expected' => false],
			'invalid: no URL' => ['request' => $request, 'expected' => false],
			'invalid: path and domain user mismatch' => ['request' => $request->withUri(new Uri('https://bob.example.com/~alice/profile/card#me')), 'expected' => false],
			'valid: minimal path and domain user match' => ['request' => $request->withUri(new Uri('https://alice.example.com/apps/~alice')), 'expected' => true],
			'valid: path and domain user match' => ['request' => $request->withUri(new Uri('https://alice.example.com/apps/solid/~alice/profile/card#me')), 'expected' => true],
		];
	}

	public function provideSubDomainsDisabledUrls()
	{
		return [
			['url' => 'example.com/foo', 'userId' => 'alice', 'expected' => 'example.com/'],
			['url' => 'https://example.com/foo', 'userId' => 'alice', 'expected' => 'https://example.com/'],
			['url' => 'http://example.com/foo', 'userId' => 'alice', 'expected' => 'http://example.com/'],
			['url' => 'https://bob.example.com/foo', 'userId' => 'alice', 'expected' => 'https://bob.example.com/'],
			['url' => 'http://bob.example.com/foo', 'userId' => 'alice', 'expected' => 'http://bob.example.com/'],
			['url' => 'https://bob.example.com/foo', 'userId' => 'bob', 'expected' => 'https://bob.example.com/'],
			['url' => 'http://bob.example.com/foo', 'userId' => 'bob', 'expected' => 'http://bob.example.com/'],
		];
	}

	public function provideSubDomainsEnabledUrls()
	{
		return [
			// @FIXME: "Undefined array key 'host'" caused by the use of `parse_url`
			// ['url' => 'example.com/foo', 'userId' => 'alice', 'expected' => 'example.com/'],

			['url' => 'https://example.com/foo', 'userId' => 'alice', 'expected' => 'https://alice.example.com/'],
			['url' => 'http://example.com/foo', 'userId' => 'alice', 'expected' => 'http://alice.example.com/'],
			['url' => 'https://bob.example.com/foo', 'userId' => 'alice', 'expected' => 'https://alice.bob.example.com/'],
			['url' => 'http://bob.example.com/foo', 'userId' => 'alice', 'expected' => 'http://alice.bob.example.com/'],
			['url' => 'https://bob.example.com/foo', 'userId' => 'bob', 'expected' => 'https://bob.example.com/'],
			['url' => 'http://bob.example.com/foo', 'userId' => 'bob', 'expected' => 'http://bob.example.com/'],
		];
	}
}
