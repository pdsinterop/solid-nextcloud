<?php
namespace OCA\Solid\AppInfo;

use OCA\Solid\Service\UserService;
use OCA\Solid\WellKnown\OpenIdConfigurationHandler;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class SolidApp extends App implements IBootstrap {

    public const APP_ID = 'solid';

    public function __construct(){
        parent::__construct(self::APP_ID);

        $container = $this->getContainer();
        $container->registerService('UserService', function($c) {
            return new \OCA\Solid\Service\UserService(
                $c->query('UserSession')
            );
        });
        $container->registerService('UserSession', function($c) {
            return $c->query('ServerContainer')->getUserSession();
        });

        // currently logged in user, userId can be gotten by calling the
        // getUID() method on it
        $container->registerService('User', function($c) {
            return $c->query('UserSession')->getUser();
        });
    }

    public function register(IRegistrationContext $context): void {
        $context->registerWellKnownHandler(\OCA\Solid\WellKnown\OpenIdConfigurationHandler::class);
    }

    public function boot(IBootContext $context): void {}
}
