<?php

namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;

/**
 * Lets a signed-in user create client credentials that are bound to their own WebID.
 *
 * A non-interactive client (a script, a service or an AI agent) can then use
 * the OAuth 2.0 client_credentials grant with DPoP to get an access token for
 * that WebID, without a browser. The user is signed in with a normal Nextcloud
 * session or with an app password (HTTP Basic). Because these routes require
 * the CSRF check, API clients send the `OCS-APIRequest: true` header.
 */
class ClientCredentialsController extends Controller
{
	public const GRANT_TYPE = 'client_credentials';
	public const USER_KEY = 'solid_user_id';

	private const USER_VALUE_KEY = 'clientCredentials';

	private IConfig $nextcloudConfig;
	private ServerConfig $config;
	private IURLGenerator $urlGenerator;
	private ?string $userId;

	public function __construct(
		$AppName,
		IRequest $request,
		IConfig $config,
		IURLGenerator $urlGenerator,
		IUserManager $userManager,
		?string $userId
	) {
		parent::__construct($AppName, $request);
		require_once(__DIR__ . '/../../vendor/autoload.php');

		$this->nextcloudConfig = $config;
		$this->config = new ServerConfig($config, $urlGenerator, $userManager);
		$this->urlGenerator = $urlGenerator;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(): JSONResponse
	{
		$credentials = [];

		foreach ($this->getClientIds() as $clientId) {
			$registration = $this->config->getClientRegistration($clientId);
			if (($registration[self::USER_KEY] ?? null) === $this->userId) {
				$credentials[] = [
					'client_id' => $clientId,
					'client_name' => $registration['client_name'] ?? '',
					'client_id_issued_at' => $registration['client_id_issued_at'] ?? null,
				];
			}
		}

		return new JSONResponse($credentials);
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(?string $name = null): JSONResponse
	{
		$name = trim((string) $name);
		if ($name === '') {
			$name = 'Client credentials';
		}

		$registration = $this->config->saveClientRegistration([
			'client_id_issued_at' => time(),
			'client_name' => mb_substr($name, 0, 100),
			'grant_types' => [self::GRANT_TYPE],
			'redirect_uris' => [],
			'token_endpoint_auth_method' => 'client_secret_basic',
			self::USER_KEY => $this->userId,
		]);

		$clientIds = $this->getClientIds();
		$clientIds[] = $registration['client_id'];
		$this->setClientIds($clientIds);

		return new JSONResponse([
			'client_id' => $registration['client_id'],
			'client_secret' => $registration['client_secret'],
			'client_name' => $registration['client_name'],
			'grant_types' => [self::GRANT_TYPE],
			'token_endpoint_auth_method' => 'client_secret_basic',
			'issuer' => $this->urlGenerator->getBaseUrl(),
			'webid' => $this->getWebId(),
		], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(string $clientId): JSONResponse
	{
		$clientIds = $this->getClientIds();
		$registration = $this->config->getClientRegistration($clientId);

		if (!in_array($clientId, $clientIds, true) || ($registration[self::USER_KEY] ?? null) !== $this->userId) {
			return new JSONResponse('Not found', Http::STATUS_NOT_FOUND);
		}

		$this->config->removeClientRegistration($clientId);
		$this->setClientIds(array_values(array_diff($clientIds, [$clientId])));

		return new JSONResponse('ok');
	}

	private function getWebId(): string
	{
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('solid.profile.handleGet', ['userId' => $this->userId, 'path' => '/card'])
		) . '#me';
	}

	private function getClientIds(): array
	{
		$value = $this->nextcloudConfig->getUserValue($this->userId, 'solid', self::USER_VALUE_KEY, '[]');

		return json_decode($value, true) ?: [];
	}

	private function setClientIds(array $clientIds): void
	{
		$this->nextcloudConfig->setUserValue($this->userId, 'solid', self::USER_VALUE_KEY, json_encode(array_values($clientIds)));
	}
}
