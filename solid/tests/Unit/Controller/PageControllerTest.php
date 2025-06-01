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
	private $controller;

	public function setUp(): void
	{
		$mockConfig = $this->getMockBuilder(IConfig::class)->getMock();
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

}
