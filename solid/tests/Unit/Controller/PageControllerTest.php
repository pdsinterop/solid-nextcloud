<?php

namespace OCA\Solid\Tests\Unit\Controller;

use OCA\Solid\Controller\PageController;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit_Framework_TestCase;

class PageControllerTest extends PHPUnit_Framework_TestCase {
	private $controller;
	private $userId = 'john';

	public function setUp() {
		$request = $this->getMockBuilder('OCP\IRequest')->getMock();

		$this->controller = new PageController('solid', $request, $this->userId);
	}

	public function testIndex() {
		$result = $this->controller->index();

		$this->assertEquals('index', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}
}
