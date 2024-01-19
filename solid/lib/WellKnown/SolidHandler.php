<?php

namespace OCA\Solid\WellKnown;

use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\WellKnown\GenericResponse;
use OCP\Http\WellKnown\IHandler;
use OCP\Http\WellKnown\IRequestContext;
use OCP\Http\WellKnown\IResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;

class SolidHandler implements IHandler {
	/* @var IURLGenerator */
	private $urlGenerator;

	public function __construct(IRequest $request, IURLGenerator $urlGenerator, IConfig $config, IUserManager $userManager) {
		require_once(__DIR__ . '/../../vendor/autoload.php');
		$this->urlGenerator = $urlGenerator;
	}

	public function handle(string $service, IRequestContext $context, ?IResponse $previousResponse): ?IResponse {
		if ($service !== 'solid') {
			return $previousResponse;
		}
		$webhooksRegisterEndpoint = $this->urlGenerator->linkToRoute('solid.solidWebhook.register');
		// FIXME: this shouldn't happen:
		if (strlen($webhooksRegisterEndpoint) == 0) {
			$webhooksRegisterEndpoint = $this->urlGenerator->linkToRoute('solid.app.appLauncher') . 'webhook/register';
		}

		$websocketsRegisterEndpoint = $this->urlGenerator->linkToRoute('solid.solidWebsocket.register');
		// FIXME: this shouldn't happen:
		if (strlen($websocketsRegisterEndpoint) == 0) {
			$websocketsRegisterEndpoint = $this->urlGenerator->linkToRoute('solid.app.appLauncher') . 'websocket/register';
		}
		$body = [
			"@context" => [
				"https://www.w3.org/ns/solid/notification/v1"
			],
			"notificationChannel" => [
				[
					"id" => "websocketNotification",
					"type" => ["WebSocketSubscription2021"],
					"subscription" => $websocketsRegisterEndpoint,
					"feature" => []
				],
				[
					"id" => "webhookNotification",
					"type" => ["WebHookSubscription2022"],
					"subscription" => $webhooksRegisterEndpoint,
					"feature" => []
				]
			]
		];
		$result = new JSONResponse($body);
		$result->addHeader("Access-Control-Allow-Origin", "*");
		$result->setStatus(200);
		return new GenericResponse($result);
	}
}
