<?php

namespace OCA\Solid;

use DateInterval;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class JtiReplayDetectorTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/../../lib/JtiReplayDetector.php';
	}

	private function createMocks($result) {
		$mockIDBConnection = $this->createMock(IDBConnection::class);
		$mockQueryBuilder = $this->createMock(IQueryBuilder::class);
		$mockExpr = $this->createMock(IExpressionBuilder::class);
		$mockResult = $this->createMock(IResult::class);

		$mockIDBConnection->expects($this->any())
			->method('getQueryBuilder')
			->willReturn($mockQueryBuilder);
		$mockQueryBuilder->expects($this->once())
			->method('select')
			->willReturnSelf();
		$mockQueryBuilder->expects($this->any())
			->method('expr')
			->willReturn($mockExpr);
		$mockExpr->expects($this->any())
			->method('eq')
			->willReturn("");
		$mockQueryBuilder->expects($this->once())
			->method('from')
			->willReturnSelf();
		$mockQueryBuilder->expects($this->once())
			->method('where')
			->willReturnSelf();
		$mockQueryBuilder->expects($this->any())
			->method('andWhere')
			->willReturnSelf();
		$mockQueryBuilder->expects($this->once())
			->method('setParameter')
			->willReturnSelf();
		$mockQueryBuilder->expects($this->once())
			->method('execute')
			->willReturn($mockResult);
		$mockResult->expects($this->once())
			->method('fetch')
			->willReturn($result);
		$mockResult->expects($this->once())
			->method('closeCursor');
		$mockQueryBuilder->expects($this->any())
			->method('insert')
			->willReturnSelf();
		$mockQueryBuilder->expects($this->any())
			->method('values')
			->willReturnSelf();
		$mockQueryBuilder->expects($this->any())
			->method('executeStatement');

		return $mockIDBConnection;
	}

	public function testJtiDetected(): void {
		$dateInterval = new DateInterval('PT90S');
		$mockIDBConnection = $this->createMocks(true);

		$detector = new JtiReplayDetector($dateInterval, $mockIDBConnection);

		$mockUUID = 'mockUUID-with-some-more-text';
		$mockURI = 'mockURI';
		$result = $detector->detect($mockUUID, $mockURI);

		$this->assertTrue($result);
	}

	public function testJtiNotDetected(): void {
		$dateInterval = new DateInterval('PT90S');
		$mockIDBConnection = $this->createMocks(false);

		$detector = new JtiReplayDetector($dateInterval, $mockIDBConnection);

		$mockUUID = 'mockUUID-with-some-more-text';
		$mockURI = 'mockURI';
		$result = $detector->detect($mockUUID, $mockURI);

		$this->assertFalse($result);
	}
}
