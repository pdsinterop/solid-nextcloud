<?php

namespace OCA\Solid\Controller;

use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \OCA\Solid\Controller\GetServerUriTrait
 */
class GetServerUriTraitTest extends TestCase
{
	////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	private $trait;

	protected function setUp(): void
	{
		$this->trait = new class {
			use GetServerUriTrait;
		};
	}

	/////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	/**
	 * @testdox GetServerUriTrait should build the request URI from the protocol, host and path that Nextcloud reports
	 * @covers ::getServerUri
	 */
	public function testGetServerUri(): void
	{
		$request = $this->getMockRequest('https', 'cloud.example.com', '/apps/solid/~alice/storage/.acl');

		$actual = $this->trait->getServerUri($request);

		$this->assertEquals('https://cloud.example.com/apps/solid/~alice/storage/.acl', $actual);
	}

	/**
	 * @testdox GetServerUriTrait should keep a non-default port that is part of the server host
	 * @covers ::getServerUri
	 */
	public function testGetServerUriWithPort(): void
	{
		$request = $this->getMockRequest('https', 'cloud.example.com:8443', '/apps/solid/~alice/profile/card');

		$actual = $this->trait->getServerUri($request);

		$this->assertEquals('https://cloud.example.com:8443/apps/solid/~alice/profile/card', $actual);
	}

	/**
	 * @testdox GetServerUriTrait should not read the scheme or host from $_SERVER, which describe the internal hop behind a TLS-terminating proxy
	 * @covers ::getServerUri
	 */
	public function testGetServerUriIgnoresInternalServerVariables(): void
	{
		$backup = $_SERVER;
		$_SERVER['REQUEST_SCHEME'] = 'http';
		$_SERVER['SERVER_NAME'] = 'internal-container-name';

		try {
			$request = $this->getMockRequest('https', 'cloud.example.com', '/apps/solid/~alice/storage/');
			$actual = $this->trait->getServerUri($request);
		} finally {
			$_SERVER = $backup;
		}

		$this->assertEquals('https://cloud.example.com/apps/solid/~alice/storage/', $actual);
	}

	////////////////////////////// MOCKS AND STUBS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	private function getMockRequest(string $protocol, string $host, string $path): IRequest|MockObject
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getServerProtocol')->willReturn($protocol);
		$request->method('getServerHost')->willReturn($host);
		$request->method('getRequestUri')->willReturn($path);

		return $request;
	}
}
