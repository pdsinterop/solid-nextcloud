<?php

declare(strict_types=1);

namespace OCA\Solid\AppInfo;

use OCA\Solid\Middleware\SolidCorsMiddleware;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'solid';

	/**
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		$container->registerService(SolidCorsMiddleware::class, function ($c): SolidCorsMiddleware {
			return new SolidCorsMiddleware(
				$c->get(IRequest::class)
			);
		});

		// executed in the order that it is registered
		$container->registerMiddleware(SolidCorsMiddleware::class);

		$container->registerService(SolidWebhookService::class, function ($c): SolidWebhookService {
			return new SolidWebhookService(
				$c->query(SolidWebhookMapper::class)
			);
		});

		$container->registerService(SolidWebhookMapper::class, function ($c): SolidWebhookMapper {
			return new SolidWebhookMapper(
				$c->get(IDBConnection::class)
			);
		});
	}

	public function register(IRegistrationContext $context): void {
		$context->registerWellKnownHandler(\OCA\Solid\WellKnown\OpenIdConfigurationHandler::class);
		$context->registerWellKnownHandler(\OCA\Solid\WellKnown\SolidHandler::class);

		/**
		 * Core class wrappers
		 */

		$context->registerService('UserService', function ($c) {
			return new \OCA\Solid\Service\UserService(
				$c->query('UserSession')
			);
		});
		$context->registerService('UserSession', function ($c) {
			return $c->query('ServerContainer')->getUserSession();
		});

		// currently logged in user, userId can be gotten by calling the
		// getUID() method on it
		$context->registerService('User', function ($c) {
			return $c->query('UserSession')->getUser();
		});
	}

	public function boot(IBootContext $context): void {
	}
}
