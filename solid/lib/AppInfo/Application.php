<?php

declare(strict_types=1);

namespace OCA\Solid\AppInfo;

use OC\AppFramework\Utility\TimeFactory;
use OC\Authentication\Events\AppPasswordCreatedEvent;
use OC\Authentication\Token\IProvider;
use OC\Server;

use OCA\Solid\Service\UserService;
use OCA\Solid\WellKnown\OpenIdConfigurationHandler;
use OCA\Solid\Middleware\SolidCorsMiddleware;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCP\Defaults;
use OCP\IServerContainer;
use OCP\Settings\IManager;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'solid';

	/**
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerWellKnownHandler(\OCA\Solid\WellKnown\OpenIdConfigurationHandler::class);

		/**
		 * Core class wrappers
		 */

		$context->registerService('UserService', function($c) {
			return new \OCA\Solid\Service\UserService(
				$c->query('UserSession')
			);
		});
		$context->registerService('UserSession', function($c) {
			return $c->query('ServerContainer')->getUserSession();
		});

		// currently logged in user, userId can be gotten by calling the
		// getUID() method on it
		$context->registerService('User', function($c) {
			return $c->query('UserSession')->getUser();
		});

                /**
                 * Middleware
                 */
                $container->registerService(SolidCorsMiddleware::class, function(IServerContainer $c): SolidCorsMiddleware{
                        return new SolidCorsMiddleware(
                            $c->get(IRequest::class)
                        );
                });

                // executed in the order that it is registered
                $container->registerMiddleware(SolidCorsMiddleware::class);
	}

	public function boot(IBootContext $context): void {
	}
}
