<?php

namespace OCA\Solid\Controller;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \OCA\Solid\Controller\PageController
 * @covers ::__construct
 */
class PageControllerTest extends TestCase
{
	private const MOCK_USER_ID = 'mock-user';
	private const MOCK_CLIENT_ID = 'mock-client-id';
	private $controller;
	private $mockConfig;

	public function setUp(): void
	{
		$mockConfig = $this->getMockBuilder(IConfig::class)->getMock();
		$this->mockConfig = $mockConfig;
		$mockRequest = $this->getMockBuilder(IRequest::class)->getMock();
		$mockUrlGenerator = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$mockUserManager = $this->getMockBuilder(IUserManager::class)->getMock();

		$this->controller = new PageController(
			'solid',
			$mockRequest,
			$mockConfig,
			$mockUserManager,
			$mockUrlGenerator,
			self::MOCK_USER_ID
		);
	}

	/**
	 * @covers ::index
	 * @uses \OCA\Solid\BaseServerConfig::__construct
	 * @uses \OCA\Solid\ServerConfig::__construct
	 */
	public function testIndex()
	{
		$result = $this->controller->index();

		$this->assertEquals('index', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}

	/**
	 * @testdox PageController should allow the client's redirect origin, including a non-default port, as a form action on the approval page
	 * @covers ::approval
	 * @dataProvider provideRedirectUris
	 * @uses \OCA\Solid\BaseServerConfig
	 * @uses \OCA\Solid\ServerConfig::__construct
	 */
	public function testApprovalAllowsRedirectOriginAsFormAction(string $redirectUri, string $expectedOrigin)
	{
		$this->mockConfig->method('getAppValue')->willReturnMap([
			['solid', 'client-' . self::MOCK_CLIENT_ID, '{}', json_encode([
				'client_name' => 'Mock Client',
				'redirect_uris' => [$redirectUri],
			])],
		]);
		$_GET['returnUrl'] = '/apps/solid/authorize?client_id=' . self::MOCK_CLIENT_ID;

		$result = $this->controller->approval(self::MOCK_CLIENT_ID);

		$policy = $result->getContentSecurityPolicy()->buildPolicy();

		$this->assertStringContainsString("form-action 'self' {$expectedOrigin};", $policy . ';');
	}

	public static function provideRedirectUris(): array
	{
		return [
			'default port' => ['https://app.example/callback', 'https://app.example'],
			'non-default port' => ['https://app.example:8443/callback', 'https://app.example:8443'],
			'loopback redirect' => ['http://localhost:3999/cb', 'http://localhost:3999'],
		];
	}
}
