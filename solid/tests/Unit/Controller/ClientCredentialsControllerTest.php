<?php

namespace OCA\Solid\Controller;

use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \OCA\Solid\Controller\ClientCredentialsController
 * @covers ::__construct
 * @uses \OCA\Solid\BaseServerConfig
 * @uses \OCA\Solid\ServerConfig
 */
class ClientCredentialsControllerTest extends TestCase
{
	private const MOCK_USER_ID = 'agent';

	/** @var array<string, string> In-memory app values */
	private array $appValues = [];
	/** @var array<string, string> In-memory user values, keyed by "user/key" */
	private array $userValues = [];

	private function createController(?string $userId = self::MOCK_USER_ID): ClientCredentialsController
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn ($app, $key, $default = '') => $this->appValues[$key] ?? $default
		);
		$config->method('setAppValue')->willReturnCallback(
			function ($app, $key, $value) { $this->appValues[$key] = $value; }
		);
		$config->method('deleteAppValue')->willReturnCallback(
			function ($app, $key) { unset($this->appValues[$key]); }
		);
		$config->method('getUserValue')->willReturnCallback(
			fn ($user, $app, $key, $default = '') => $this->userValues["$user/$key"] ?? $default
		);
		$config->method('setUserValue')->willReturnCallback(
			function ($user, $app, $key, $value) { $this->userValues["$user/$key"] = $value; }
		);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('getBaseUrl')->willReturn('https://cloud.example');
		$urlGenerator->method('linkToRoute')->willReturnCallback(
			fn ($route, $params) => '/apps/solid/~' . $params['userId'] . '/profile' . $params['path']
		);
		$urlGenerator->method('getAbsoluteURL')->willReturnCallback(fn ($url) => 'https://cloud.example' . $url);

		return new ClientCredentialsController(
			'solid',
			$this->createMock(IRequest::class),
			$config,
			$urlGenerator,
			$this->createMock(IUserManager::class),
			$userId
		);
	}

	/**
	 * @testdox ClientCredentialsController should create client credentials that are bound to the signed-in user
	 * @covers ::create
	 */
	public function testCreate(): void
	{
		$response = $this->createController()->create('my agent');
		$data = $response->getData();

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('my agent', $data['client_name']);
		$this->assertSame('https://cloud.example/apps/solid/~agent/profile/card#me', $data['webid']);
		$this->assertSame(['client_credentials'], $data['grant_types']);
		$this->assertNotEmpty($data['client_secret']);

		$stored = json_decode($this->appValues['client-' . $data['client_id']], true);
		$this->assertSame(self::MOCK_USER_ID, $stored[ClientCredentialsController::USER_KEY]);
		$this->assertSame(['client_credentials'], $stored['grant_types']);
		$this->assertSame($data['client_secret'], $stored['client_secret']);
	}

	/**
	 * @testdox ClientCredentialsController should list only the credentials of the signed-in user, without secrets
	 * @covers ::index
	 * @covers ::create
	 */
	public function testIndex(): void
	{
		$mine = $this->createController()->create('mine')->getData();

		// A registration of another user, listed (by mistake) under this user's ids
		$this->appValues['client-theirs'] = json_encode([
			'client_id' => 'theirs',
			'client_name' => 'theirs',
			ClientCredentialsController::USER_KEY => 'someone-else',
		]);
		$this->userValues[self::MOCK_USER_ID . '/clientCredentials'] = json_encode([$mine['client_id'], 'theirs']);

		$list = $this->createController()->index()->getData();

		$this->assertCount(1, $list);
		$this->assertSame($mine['client_id'], $list[0]['client_id']);
		$this->assertArrayNotHasKey('client_secret', $list[0]);
	}

	/**
	 * @testdox ClientCredentialsController should let a user revoke their own credentials
	 * @covers ::destroy
	 * @covers ::create
	 */
	public function testDestroy(): void
	{
		$clientId = $this->createController()->create('mine')->getData()['client_id'];

		$response = $this->createController()->destroy($clientId);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertArrayNotHasKey('client-' . $clientId, $this->appValues);
		$this->assertSame([], $this->createController()->index()->getData());
	}

	/**
	 * @testdox ClientCredentialsController should not let a user revoke credentials of someone else
	 * @covers ::destroy
	 * @covers ::create
	 */
	public function testDestroyOtherUsersCredentials(): void
	{
		$clientId = $this->createController('someone-else')->create('theirs')->getData()['client_id'];

		$response = $this->createController()->destroy($clientId);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertArrayHasKey('client-' . $clientId, $this->appValues);
	}
}
